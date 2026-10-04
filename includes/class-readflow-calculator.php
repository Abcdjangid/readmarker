<?php
/**
 * Shared, stateless reading-time service for all future ReadFlow consumers.
 *
 * Use after plugins_loaded:
 * $calculator = new ReadFlow_Calculator();
 * $result     = $calculator->calculate_from_post( 123, array( 'wpm' => 250 ) );
 * $result     = $calculator->calculate_from_content( '<p>Hello world.</p>' );
 * Always check is_wp_error() before consuming a result.
 *
 * Reads stored post_content, never the_content filters, block rendering, or
 * shortcode callbacks. No permissions are inferred: callers must authorize
 * access to private/password-protected posts before exposing any result.
 * No content, titles, or other post details are returned.
 *
 * Limitations: English-oriented tokenization, not universal language support.
 * Dynamic/synced blocks and shortcode-generated text/images are not resolved.
 * Only registered shortcode delimiters are removed; enclosed text is retained.
 * Unknown shortcodes and escaped shortcodes remain literal text. Malformed
 * HTML is handled conservatively; this is not a browser DOM/CSS visibility
 * engine. Hidden-by-CSS text is counted. Image attributes/alt text are not words.
 * No image duration, persistent caching, settings, rendering, or remote I/O.
 * The post adapter delegates to the content API so caching can wrap it later.
 *
 * @package ReadFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadFlow_Calculator {

	const DEFAULT_WPM = 200;
	const MIN_WPM     = 1;
	const MAX_WPM     = 10000;

	/**
	 * Calculates from a positive integer post ID (or digit string without leading zeros).
	 *
	 * Performs one get_post() lookup; invalid IDs do not trigger a lookup.
	 *
	 * @param mixed $post_id Post ID; objects and fractional IDs are rejected.
	 * @param array $options Optional wpm override, as in calculate_from_content().
	 * @return array|WP_Error Result, or readflow_invalid_post_id/readflow_post_not_found.
	 */
	public function calculate_from_post( $post_id, $options = array() ) {
		if ( ! is_int( $post_id ) && ! ( is_string( $post_id ) && 1 === preg_match( '/\A[0-9]+\z/', $post_id ) ) ) {
			return new WP_Error( 'readflow_invalid_post_id', __( 'A positive post ID is required.', 'readflow' ) );
		}

		$post_id = filter_var( $post_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		if ( false === $post_id ) {
			return new WP_Error( 'readflow_invalid_post_id', __( 'A positive post ID is required.', 'readflow' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'readflow_post_not_found', __( 'The requested post was not found.', 'readflow' ) );
		}

		$result = $this->calculate_from_content( $post->post_content, $options );
		if ( ! is_wp_error( $result ) ) {
			$result['post_id'] = $post->ID;
		}

		return $result;
	}

	/**
	 * Calculates readable words and literal img elements in supplied UTF-8 HTML.
	 *
	 * Numeric, finite WPM in [1, 10000] is accepted, including fractional speeds;
	 * everything else falls back to 200. Non-array options use defaults.
	 * Seconds = words / WPM * 60, retained as a float without early rounding.
	 * Minutes = seconds / 60. Labels come from the separate time formatter.
	 *
	 * @param string $content Raw stored HTML or plain text, not rendered output.
	 * @param array  $options Optional 'wpm' value.
	 * @return array|WP_Error On success: post_id (null), word_count (int),
	 *                       image_count (int), words_per_minute (float),
	 *                       reading_seconds/reading_minutes (float),
	 *                       formatted_time/formatted_short_time (plain strings).
	 *                       Invalid types/UTF-8 or parser failures return WP_Error.
	 */
	public function calculate_from_content( $content, $options = array() ) {
		if ( ! is_string( $content ) || 1 !== preg_match( '//u', $content ) ) {
			return new WP_Error( 'readflow_invalid_content', __( 'Content must be a valid UTF-8 string.', 'readflow' ) );
		}

		$wpm  = $this->validate_wpm( is_array( $options ) && isset( $options['wpm'] ) ? $options['wpm'] : self::DEFAULT_WPM );
		$data = $this->extract_content( $content );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$word_count = $this->count_words( $data['text'] );
		if ( false === $word_count ) {
			return $this->parsing_error();
		}

		$seconds = ( $word_count / $wpm ) * 60;

		return array_merge(
			array(
				'post_id'          => null,
				'word_count'       => $word_count,
				'image_count'      => $data['image_count'],
				'words_per_minute' => $wpm,
				'reading_seconds' => $seconds,
				'reading_minutes' => $seconds / 60,
			),
			ReadFlow_Time_Formatter::format( $seconds )
		);
	}

	/**
	 * @param mixed $value Requested WPM.
	 * @return float Validated WPM.
	 */
	private function validate_wpm( $value ) {
		if ( ! is_numeric( $value ) ) {
			return (float) self::DEFAULT_WPM;
		}
		$value = (float) $value;
		return is_finite( $value ) && $value >= self::MIN_WPM && $value <= self::MAX_WPM ? $value : (float) self::DEFAULT_WPM;
	}

	/**
	 * Extracts text and images together; quoted '>' characters stay in tags.
	 * Block tags become spaces; inline tags preserve split words (hel<em>lo</em>).
	 * Entities are decoded AFTER markup removal so literal encoded tags survive.
	 *
	 * @param string $content Content to parse.
	 * @return array|WP_Error Text and image count, or a controlled parser error.
	 */
	private function extract_content( $content ) {
		$images = 0;
		$attrs  = '(?:[^>\'\"]|\'[^\']*\'|"[^"]*")*';
		$regex  = '~<!--[\s\S]*?(?:-->|$)|<(script|style|noscript)(?=[\s/>])' . $attrs . '>[\s\S]*?(?:</\1\s*>|$)|<!DOCTYPE\s' . $attrs . '>|</?[a-z][a-z0-9:-]*\b' . $attrs . '>~i';
		$text   = preg_replace_callback(
			$regex,
			static function ( $match ) use ( &$images ) {
				if ( 0 === strpos( $match[0], '<!' ) ) {
					return '';
				}
				if ( ! empty( $match[1] ) ) {
					return ' ';
				}
				preg_match( '~^</?([a-z][a-z0-9:-]*)~i', $match[0], $tag );
				$name = strtolower( $tag[1] );
				if ( 'img' === $name && '/' !== $match[0][1] ) {
					++$images;
				}
				$boundaries = array( 'address', 'article', 'aside', 'blockquote', 'br', 'caption', 'dd', 'details', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'img', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul' );
				return in_array( $name, $boundaries, true ) ? ' ' : '';
			},
			$content
		);
		if ( null === $text ) {
			return $this->parsing_error();
		}

		// Strip registered shortcode tokens only; never execute callbacks or discard enclosed prose.
		global $shortcode_tags;
		if ( ! empty( $shortcode_tags ) && false !== strpos( $text, '[' ) ) {
			$names = implode(
				'|',
				array_map(
					static function ( $name ) {
						return preg_quote( $name, '~' );
					},
					array_keys( $shortcode_tags )
				)
			);
			$text  = preg_replace( '~(?<!\[)\[/?(?:' . $names . ')(?=[\s/\]])(?:[^\]\'\"]|\'[^\']*\'|"[^"]*")*\](?!\])~', ' ', $text );
			if ( null === $text ) {
				return $this->parsing_error();
			}
		}

		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Soft hyphens are discretionary line breaks, not word boundaries.
		$text = str_replace( "\xC2\xAD", '', $text );
		$text = preg_replace( '/[\s\p{Z}]+/u', ' ', $text );
		if ( null === $text ) {
			return $this->parsing_error();
		}

		return array( 'text' => trim( $text ), 'image_count' => $images );
	}

	/**
	 * English-oriented word policy isolated for future multilingual strategies.
	 * Letters/numbers count; contractions, hyphenated words and decimals stay whole.
	 * Punctuation/emoji alone do not count. No matches array is allocated.
	 *
	 * @param string $text Normalized readable text.
	 * @return int|false Word count, or regex failure.
	 */
	private function count_words( $text ) {
		return preg_match_all( '/\p{N}+(?:[.,]\p{N}+)+|[\p{L}\p{N}][\p{L}\p{M}\p{N}]*(?:[\x{0027}\x{2019}\x{002D}\x{2010}\x{2011}][\p{L}\p{N}][\p{L}\p{M}\p{N}]*)*/u', $text );
	}

	/** @return WP_Error Controlled failure instead of silently returning partial counts. */
	private function parsing_error() {
		return new WP_Error( 'readflow_parse_error', __( 'The content could not be analyzed.', 'readflow' ) );
	}
}
