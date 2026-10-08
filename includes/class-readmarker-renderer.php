<?php
/**
 * Pure HTML renderer: structured calculator data + normalized settings -> HTML.
 * No calculation, option reads, asset loading, or placement decisions occur here.
 * Future integrations may call render() then ReadMarker_Assets::for_output().
 * All styles honor the metadata toggles; styles change layout, not data policy.
 *
 * @package ReadMarker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadMarker_Renderer {
	/**
	 * Wrap only filtered article HTML, excluding the reading-time display.
	 * Content is trusted WordPress-rendered HTML, not a new user-input sink.
	 * The wrapper can affect theme child selectors; no theme-specific selector is used.
	 */
	public function progress_article( $content, $post_id ) {
		return '<div class="readmarker-article-content" data-readmarker-article="' . esc_attr( (string) $post_id ) . '">' . $content . '</div>';
	}

	/** Hidden until the independent JS consumer has a valid measured article. */
	public function progress_bar( $settings, $total_seconds = 0, $total_label = '', $memory = false, $display = true ) {
		$defaults = ReadMarker_Settings::defaults();
		$color = isset( $settings['progress_color'] ) && is_string( $settings['progress_color'] ) ? sanitize_hex_color( $settings['progress_color'] ) : null;
		$height = isset( $settings['progress_height'] ) && is_int( $settings['progress_height'] ) && $settings['progress_height'] >= 1 && $settings['progress_height'] <= 20 ? $settings['progress_height'] : $defaults['progress_height'];
		$style = '--readmarker-progress-color:' . ( $color ? $color : $defaults['progress_color'] ) . ';--readmarker-progress-height:' . $height . 'px;';
		$modes = ReadMarker_Settings::progress_displays();
		$mode = isset( $settings['progress_display'] ) && is_string( $settings['progress_display'] ) && isset( $modes[ $settings['progress_display'] ] ) ? $settings['progress_display'] : $defaults['progress_display'];
		$bar = '<div class="readmarker-progress" data-readmarker-consumer="top_bar" hidden aria-hidden="true" style="' . esc_attr( $style ) . '"><div class="readmarker-progress-fill"></div></div>';
		$indicator = '';
		if ( in_array( $mode, array( 'remaining', 'time_remaining', 'percentage_remaining', 'countdown', 'floating_widget', 'estimated_finish_time' ), true ) ) {
			$labels = $this->duration_labels();
			$duration = $this->duration_fields();
			$floating = 'floating_widget' === $mode;
			if ( $floating ) {
				$duration = '<span class="readmarker-floating-widget__percentage" aria-hidden="true"></span><div class="readmarker-floating-widget__remaining">' . $duration . '</div><div class="readmarker-floating-widget__progress" role="progressbar" aria-label="' . esc_attr( __( 'Reading progress', 'readmarker' ) ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="readmarker-floating-widget__progress-fill" aria-hidden="true"></span></div>';
			}
			$indicator = '<div class="readmarker-progress-indicator readmarker-progress-indicator--remaining' . ( $floating ? ' readmarker-floating-widget' : '' ) . '" data-readmarker-consumer="' . esc_attr( $mode ) . '" data-readmarker-labels="' . esc_attr( wp_json_encode( $labels ) ) . '" data-readmarker-total-label="' . esc_attr( is_string( $total_label ) ? $total_label : '' ) . '" hidden role="group" aria-label="' . esc_attr( __( 'Reading information', 'readmarker' ) ) . '" style="' . esc_attr( $style ) . '">' . $duration . '</div>';
		} elseif ( 'inline_progress' === $mode ) {
			$indicator = $this->inline_progress( $settings );
		} elseif ( 'reading_milestones' === $mode ) {
			$labels = array( '25' => __( 'Getting started', 'readmarker' ), '50' => __( 'Halfway there', 'readmarker' ), '75' => __( 'Almost there', 'readmarker' ), '100' => __( 'Finished', 'readmarker' ) );
			$indicator = '<div class="readmarker-progress-indicator readmarker-progress-indicator--remaining readmarker-milestone" data-readmarker-consumer="reading_milestones" data-readmarker-milestone-labels="' . esc_attr( wp_json_encode( $labels ) ) . '" hidden style="' . esc_attr( $style ) . '"><span class="readmarker-milestone__label"></span><span aria-hidden="true">&middot;</span><span class="readmarker-milestone__progress"></span></div>';
		} elseif ( 'top_bar' !== $mode ) {
			$kind = 'circular' === $mode ? 'circular' : 'percentage';
			$svg = 'circular' === $kind ? '<svg class="readmarker-progress-ring" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><circle class="readmarker-progress-track" cx="32" cy="32" r="27"/><circle class="readmarker-progress-stroke" cx="32" cy="32" r="27" pathLength="100" stroke-dasharray="100" stroke-dashoffset="100"/></svg>' : '';
			$indicator = '<div class="readmarker-progress-indicator readmarker-progress-indicator--' . esc_attr( $kind ) . '" data-readmarker-consumer="' . esc_attr( $kind ) . '" hidden role="progressbar" aria-label="' . esc_attr( __( 'Reading progress', 'readmarker' ) ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="' . esc_attr( $style ) . '">' . $svg . '<span class="readmarker-progress-value" aria-hidden="true">0%</span></div>';
		}
		// One configuration root; consumers move to body when mounted to avoid theme containment.
		// JSON numeric serialization is locale-independent and preserves fractional seconds.
		$total_seconds = ( is_int( $total_seconds ) || is_float( $total_seconds ) ) && is_finite( (float) $total_seconds ) && $total_seconds >= 0 ? $total_seconds : 0;
		return '<div class="readmarker-progress-displays" data-readmarker-progress' . ( $display ? '' : ' data-readmarker-display-disabled="true"' ) . ' data-readmarker-mode="' . esc_attr( $mode ) . '" data-readmarker-total-seconds="' . esc_attr( wp_json_encode( $total_seconds ) ) . '">' . ( $display ? ( in_array( $mode, array( 'top_bar', 'top_bar_percentage' ), true ) ? $bar : '' ) . $indicator : '' ) . ( $memory ? $this->position_prompt() : '' ) . '</div>';
	}

	/** Automatic inline mode reuses the manual progress markup and consumer semantics. */
	public function inline_progress( $settings ) {
		$html = $this->shortcode( 'readmarker_progress', array(), $settings, array( 'show_percentage'=>'true' ) );
		$color = sanitize_hex_color( $settings['progress_color'] ?? '' ) ?: ReadMarker_Settings::defaults()['progress_color'];
		return '<div class="readmarker-inline-progress-auto" style="--readmarker-progress-color:' . esc_attr( $color ) . '">' . str_replace( 'data-readmarker-inline="progress"', 'data-readmarker-inline="progress" data-readmarker-auto-inline data-readmarker-consumer="inline_progress"', $html ) . '</div>';
	}

	/** Manual markup receives existing calculation data; no new duration/word-count policy. */
	public function shortcode( $tag, $data, $settings, $attributes ) {
		if ( 'readmarker' === $tag ) {
			$html = $this->render( $data, $settings );
			return str_replace( 'data-readmarker-post=', 'data-readmarker-shortcode=', $html );
		}
		if ( 'readmarker_time' === $tag ) {
			$text = 'long' === $attributes['format'] ? $data['formatted_time'] : $data['formatted_short_time'];
		} elseif ( 'readmarker_words' === $tag ) {
			$text = number_format_i18n( $data['word_count'] );
			/* translators: %s: Localized number of words. */
			if ( 'true' === $attributes['label'] ) { $text = sprintf( _n( '%s word', '%s words', $data['word_count'], 'readmarker' ), $text ); }
		} elseif ( 'readmarker_progress' === $tag ) {
			return '<span class="readmarker-inline-progress" data-readmarker-inline="progress" hidden role="progressbar" aria-label="' . esc_attr__( 'Reading progress', 'readmarker' ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="readmarker-progress-value" aria-hidden="true"' . ( 'false' === $attributes['show_percentage'] ? ' hidden' : '' ) . '></span><span class="readmarker-inline-track" aria-hidden="true"><span class="readmarker-progress-fill"></span></span></span>';
		} else {
			return '<span class="readmarker-inline-remaining" data-readmarker-inline="remaining" data-readmarker-format="' . esc_attr( $attributes['format'] ) . '" data-readmarker-labels="' . esc_attr( wp_json_encode( $this->duration_labels() ) ) . '" hidden>' . $this->duration_fields() . '</span>';
		}
		return '<span class="readmarker-inline-text" data-readmarker-shortcode="' . esc_attr( $tag ) . '">' . esc_html( $text ) . '</span>';
	}

	private function duration_labels() {
		return array(
				'hour' => __( 'hr', 'readmarker' ),
				'minute' => __( 'min', 'readmarker' ),
				'second' => __( 'sec', 'readmarker' ),
				/* translators: %s: Formatted remaining duration. */
				'remaining' => __( '%s remaining', 'readmarker' ),
				'finished' => __( 'Finished', 'readmarker' ),
				/* translators: %s: Reader-local estimated finish clock time. */
				'finish' => __( 'Finish around %s', 'readmarker' ),
			);
	}

	private function duration_fields() {
		return '<span class="readmarker-duration-prefix" hidden></span><span class="readmarker-duration-check" hidden aria-hidden="true">&#10003;</span><span class="readmarker-duration-value"></span>';
	}

	/** Static accessible prompt; storage and restoration remain browser-side. */
	private function position_prompt() {
		$site = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1;
		/* translators: %s: Saved reading progress percentage; keep the trailing percent sign. */
		return '<section class="readmarker-position-prompt" data-readmarker-memory data-readmarker-site="' . esc_attr( (string) $site ) . '" hidden aria-label="' . esc_attr__( 'Continue reading?', 'readmarker' ) . '"><strong>' . esc_html__( 'Continue reading?', 'readmarker' ) . '</strong><p data-readmarker-position-text data-readmarker-template="' . esc_attr__( 'You were at %s%', 'readmarker' ) . '"></p><div><button type="button" data-readmarker-continue>' . esc_html__( 'Continue', 'readmarker' ) . '</button> <button type="button" data-readmarker-dismiss>' . esc_html__( 'Dismiss', 'readmarker' ) . '</button></div></section>';
	}

	/**
	 * @param array|WP_Error $calculation Result from the shared calculator.
	 * @param array          $settings    Normalized ReadMarker_Settings values.
	 * @return string Escaped HTML, or empty string when there is nothing to show.
	 */
	public function render( $calculation, $settings ) {
		if ( ! is_array( $calculation ) || ! is_array( $settings ) || ! isset( $calculation['word_count'] ) || ! is_int( $calculation['word_count'] ) || $calculation['word_count'] < 1 ) {
			return '';
		}
		$styles = ReadMarker_Settings::styles();
		$style  = isset( $settings['style'] ) && is_string( $settings['style'] ) && isset( $styles[ $settings['style'] ] ) ? $settings['style'] : ReadMarker_Settings::defaults()['style'];
		$fields = array();
		if ( isset( $settings['show_time'] ) && true === $settings['show_time'] && isset( $calculation['formatted_time'] ) && is_string( $calculation['formatted_time'] ) && '' !== $calculation['formatted_time'] ) {
			$icon = '<svg class="readmarker-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
			$fields[] = '<span class="readmarker-field readmarker-time">' . $icon . '<span>' . esc_html( $calculation['formatted_time'] ) . '</span></span>';
		}
		if ( isset( $settings['show_word_count'] ) && true === $settings['show_word_count'] ) {
			/* translators: %s: Localized number of words. */
			$words = sprintf( _n( '%s word', '%s words', $calculation['word_count'], 'readmarker' ), number_format_i18n( $calculation['word_count'] ) );
			$fields[] = '<span class="readmarker-field readmarker-words">' . esc_html( $words ) . '</span>';
		}
		if ( empty( $fields ) ) {
			return '';
		}
		$post_id = isset( $calculation['post_id'] ) && is_int( $calculation['post_id'] ) && $calculation['post_id'] > 0 ? $calculation['post_id'] : 0;
		return '<div class="readmarker-display readmarker-display--' . esc_attr( $style ) . '" data-readmarker-post="' . esc_attr( (string) $post_id ) . '">' . implode( ' <span class="readmarker-separator" aria-hidden="true">&middot;</span> ', $fields ) . '</div>';
	}
}
