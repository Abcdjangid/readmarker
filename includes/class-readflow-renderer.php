<?php
/**
 * Pure HTML renderer: structured calculator data + normalized settings -> HTML.
 * No calculation, option reads, asset loading, or placement decisions occur here.
 * Future integrations may call render() then ReadFlow_Assets::for_output().
 * All styles honor the metadata toggles; styles change layout, not data policy.
 *
 * @package ReadFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadFlow_Renderer {
	/**
	 * Wrap only filtered article HTML, excluding the reading-time display.
	 * Content is trusted WordPress-rendered HTML, not a new user-input sink.
	 * The wrapper can affect theme child selectors; no theme-specific selector is used.
	 */
	public function progress_article( $content, $post_id ) {
		return '<div class="readflow-article-content" data-readflow-article="' . esc_attr( (string) $post_id ) . '">' . $content . '</div>';
	}

	/** Hidden until the independent JS consumer has a valid measured article. */
	public function progress_bar( $settings, $total_seconds = 0, $total_label = '', $memory = false, $display = true ) {
		$defaults = ReadFlow_Settings::defaults();
		$color = isset( $settings['progress_color'] ) && is_string( $settings['progress_color'] ) ? sanitize_hex_color( $settings['progress_color'] ) : null;
		$height = isset( $settings['progress_height'] ) && is_int( $settings['progress_height'] ) && $settings['progress_height'] >= 1 && $settings['progress_height'] <= 20 ? $settings['progress_height'] : $defaults['progress_height'];
		$style = '--readflow-progress-color:' . ( $color ? $color : $defaults['progress_color'] ) . ';--readflow-progress-height:' . $height . 'px;';
		$modes = ReadFlow_Settings::progress_displays();
		$mode = isset( $settings['progress_display'] ) && is_string( $settings['progress_display'] ) && isset( $modes[ $settings['progress_display'] ] ) ? $settings['progress_display'] : $defaults['progress_display'];
		$bar = '<div class="readflow-progress" data-readflow-consumer="top_bar" hidden aria-hidden="true" style="' . esc_attr( $style ) . '"><div class="readflow-progress-fill"></div></div>';
		$indicator = '';
		if ( in_array( $mode, array( 'remaining', 'time_remaining', 'percentage_remaining', 'countdown', 'floating_widget', 'estimated_finish_time' ), true ) ) {
			$labels = $this->duration_labels();
			$duration = $this->duration_fields();
			$floating = 'floating_widget' === $mode;
			if ( $floating ) {
				$duration = '<span class="readflow-floating-widget__percentage" aria-hidden="true"></span><div class="readflow-floating-widget__remaining">' . $duration . '</div><div class="readflow-floating-widget__progress" role="progressbar" aria-label="' . esc_attr( __( 'Reading progress', 'readflow' ) ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="readflow-floating-widget__progress-fill" aria-hidden="true"></span></div>';
			}
			$indicator = '<div class="readflow-progress-indicator readflow-progress-indicator--remaining' . ( $floating ? ' readflow-floating-widget' : '' ) . '" data-readflow-consumer="' . esc_attr( $mode ) . '" data-readflow-labels="' . esc_attr( wp_json_encode( $labels ) ) . '" data-readflow-total-label="' . esc_attr( is_string( $total_label ) ? $total_label : '' ) . '" hidden role="group" aria-label="' . esc_attr( __( 'Reading information', 'readflow' ) ) . '" style="' . esc_attr( $style ) . '">' . $duration . '</div>';
		} elseif ( 'inline_progress' === $mode ) {
			$indicator = $this->inline_progress( $settings );
		} elseif ( 'reading_milestones' === $mode ) {
			$labels = array( '25' => __( 'Getting started', 'readflow' ), '50' => __( 'Halfway there', 'readflow' ), '75' => __( 'Almost there', 'readflow' ), '100' => __( 'Finished', 'readflow' ) );
			$indicator = '<div class="readflow-progress-indicator readflow-progress-indicator--remaining readflow-milestone" data-readflow-consumer="reading_milestones" data-readflow-milestone-labels="' . esc_attr( wp_json_encode( $labels ) ) . '" hidden style="' . esc_attr( $style ) . '"><span class="readflow-milestone__label"></span><span aria-hidden="true">&middot;</span><span class="readflow-milestone__progress"></span></div>';
		} elseif ( 'top_bar' !== $mode ) {
			$kind = 'circular' === $mode ? 'circular' : 'percentage';
			$svg = 'circular' === $kind ? '<svg class="readflow-progress-ring" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><circle class="readflow-progress-track" cx="32" cy="32" r="27"/><circle class="readflow-progress-stroke" cx="32" cy="32" r="27" pathLength="100" stroke-dasharray="100" stroke-dashoffset="100"/></svg>' : '';
			$indicator = '<div class="readflow-progress-indicator readflow-progress-indicator--' . esc_attr( $kind ) . '" data-readflow-consumer="' . esc_attr( $kind ) . '" hidden role="progressbar" aria-label="' . esc_attr( __( 'Reading progress', 'readflow' ) ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="' . esc_attr( $style ) . '">' . $svg . '<span class="readflow-progress-value" aria-hidden="true">0%</span></div>';
		}
		// One configuration root; consumers move to body when mounted to avoid theme containment.
		// JSON numeric serialization is locale-independent and preserves fractional seconds.
		$total_seconds = ( is_int( $total_seconds ) || is_float( $total_seconds ) ) && is_finite( (float) $total_seconds ) && $total_seconds >= 0 ? $total_seconds : 0;
		return '<div class="readflow-progress-displays" data-readflow-progress' . ( $display ? '' : ' data-readflow-display-disabled="true"' ) . ' data-readflow-mode="' . esc_attr( $mode ) . '" data-readflow-total-seconds="' . esc_attr( wp_json_encode( $total_seconds ) ) . '">' . ( $display ? ( in_array( $mode, array( 'top_bar', 'top_bar_percentage' ), true ) ? $bar : '' ) . $indicator : '' ) . ( $memory ? $this->position_prompt() : '' ) . '</div>';
	}

	/** Automatic inline mode reuses the manual progress markup and consumer semantics. */
	public function inline_progress( $settings ) {
		$html = $this->shortcode( 'readflow_progress', array(), $settings, array( 'show_percentage'=>'true' ) );
		$color = sanitize_hex_color( $settings['progress_color'] ?? '' ) ?: ReadFlow_Settings::defaults()['progress_color'];
		return '<div class="readflow-inline-progress-auto" style="--readflow-progress-color:' . esc_attr( $color ) . '">' . str_replace( 'data-readflow-inline="progress"', 'data-readflow-inline="progress" data-readflow-auto-inline data-readflow-consumer="inline_progress"', $html ) . '</div>';
	}

	/** Manual markup receives existing calculation data; no new duration/word-count policy. */
	public function shortcode( $tag, $data, $settings, $attributes ) {
		if ( 'readflow' === $tag ) {
			$html = $this->render( $data, $settings );
			return str_replace( 'data-readflow-post=', 'data-readflow-shortcode=', $html );
		}
		if ( 'readflow_time' === $tag ) {
			$text = 'long' === $attributes['format'] ? $data['formatted_time'] : $data['formatted_short_time'];
		} elseif ( 'readflow_words' === $tag ) {
			$text = number_format_i18n( $data['word_count'] );
			/* translators: %s: Localized number of words. */
			if ( 'true' === $attributes['label'] ) { $text = sprintf( _n( '%s word', '%s words', $data['word_count'], 'readflow' ), $text ); }
		} elseif ( 'readflow_progress' === $tag ) {
			return '<span class="readflow-inline-progress" data-readflow-inline="progress" hidden role="progressbar" aria-label="' . esc_attr__( 'Reading progress', 'readflow' ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="readflow-progress-value" aria-hidden="true"' . ( 'false' === $attributes['show_percentage'] ? ' hidden' : '' ) . '></span><span class="readflow-inline-track" aria-hidden="true"><span class="readflow-progress-fill"></span></span></span>';
		} else {
			return '<span class="readflow-inline-remaining" data-readflow-inline="remaining" data-readflow-format="' . esc_attr( $attributes['format'] ) . '" data-readflow-labels="' . esc_attr( wp_json_encode( $this->duration_labels() ) ) . '" hidden>' . $this->duration_fields() . '</span>';
		}
		return '<span class="readflow-inline-text" data-readflow-shortcode="' . esc_attr( $tag ) . '">' . esc_html( $text ) . '</span>';
	}

	private function duration_labels() {
		return array(
				'hour' => __( 'hr', 'readflow' ),
				'minute' => __( 'min', 'readflow' ),
				'second' => __( 'sec', 'readflow' ),
				/* translators: %s: Formatted remaining duration. */
				'remaining' => __( '%s remaining', 'readflow' ),
				'finished' => __( 'Finished', 'readflow' ),
				/* translators: %s: Reader-local estimated finish clock time. */
				'finish' => __( 'Finish around %s', 'readflow' ),
			);
	}

	private function duration_fields() {
		return '<span class="readflow-duration-prefix" hidden></span><span class="readflow-duration-check" hidden aria-hidden="true">&#10003;</span><span class="readflow-duration-value"></span>';
	}

	/** Static accessible prompt; storage and restoration remain browser-side. */
	private function position_prompt() {
		$site = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1;
		/* translators: %s: Saved reading progress percentage; keep the trailing percent sign. */
		return '<section class="readflow-position-prompt" data-readflow-memory data-readflow-site="' . esc_attr( (string) $site ) . '" hidden aria-label="' . esc_attr__( 'Continue reading?', 'readflow' ) . '"><strong>' . esc_html__( 'Continue reading?', 'readflow' ) . '</strong><p data-readflow-position-text data-readflow-template="' . esc_attr__( 'You were at %s%', 'readflow' ) . '"></p><div><button type="button" data-readflow-continue>' . esc_html__( 'Continue', 'readflow' ) . '</button> <button type="button" data-readflow-dismiss>' . esc_html__( 'Dismiss', 'readflow' ) . '</button></div></section>';
	}

	/**
	 * @param array|WP_Error $calculation Result from the shared calculator.
	 * @param array          $settings    Normalized ReadFlow_Settings values.
	 * @return string Escaped HTML, or empty string when there is nothing to show.
	 */
	public function render( $calculation, $settings ) {
		if ( ! is_array( $calculation ) || ! is_array( $settings ) || ! isset( $calculation['word_count'] ) || ! is_int( $calculation['word_count'] ) || $calculation['word_count'] < 1 ) {
			return '';
		}
		$styles = ReadFlow_Settings::styles();
		$style  = isset( $settings['style'] ) && is_string( $settings['style'] ) && isset( $styles[ $settings['style'] ] ) ? $settings['style'] : ReadFlow_Settings::defaults()['style'];
		$fields = array();
		if ( isset( $settings['show_time'] ) && true === $settings['show_time'] && isset( $calculation['formatted_time'] ) && is_string( $calculation['formatted_time'] ) && '' !== $calculation['formatted_time'] ) {
			$icon = '<svg class="readflow-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
			$fields[] = '<span class="readflow-field readflow-time">' . $icon . '<span>' . esc_html( $calculation['formatted_time'] ) . '</span></span>';
		}
		if ( isset( $settings['show_word_count'] ) && true === $settings['show_word_count'] ) {
			/* translators: %s: Localized number of words. */
			$words = sprintf( _n( '%s word', '%s words', $calculation['word_count'], 'readflow' ), number_format_i18n( $calculation['word_count'] ) );
			$fields[] = '<span class="readflow-field readflow-words">' . esc_html( $words ) . '</span>';
		}
		if ( empty( $fields ) ) {
			return '';
		}
		$post_id = isset( $calculation['post_id'] ) && is_int( $calculation['post_id'] ) && $calculation['post_id'] > 0 ? $calculation['post_id'] : 0;
		return '<div class="readflow-display readflow-display--' . esc_attr( $style ) . '" data-readflow-post="' . esc_attr( (string) $post_id ) . '">' . implode( ' <span class="readflow-separator" aria-hidden="true">&middot;</span> ', $fields ) . '</div>';
	}
}
