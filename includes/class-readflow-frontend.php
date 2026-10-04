<?php
/**
 * WordPress placement adapter. No markup or calculation algorithms live here.
 *
 * Duplicate policy: one automatic insertion per post per integration instance
 * (normally one request). Already-filtered HTML carries the renderer's post
 * marker and is returned intact. Repeated raw input gets no second insertion.
 * No global flags or persistent state; the re-entry guard resets in finally.
 * Calls made for excerpts/secondary loops do not consume a post's insertion.
 *
 * @package ReadFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadFlow_Frontend {
	private $settings;
	private $calculator;
	private $renderer;
	private $rendered_posts = array();
	private $rendering = false;

	public function __construct( ReadFlow_Settings $settings, ReadFlow_Calculator $calculator, ReadFlow_Renderer $renderer ) {
		$this->settings   = $settings;
		$this->calculator = $calculator;
		$this->renderer   = $renderer;
	}

	public function register_hooks() {
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
	}

	/** @param string $content Filtered post HTML. @return string Unchanged or decorated content. */
	public function filter_content( $content ) {
		if ( $this->rendering || ! is_string( $content ) ) {
			return $content;
		}
		$this->rendering = true;
		try {
			$post = get_post();
			$settings = $this->settings->get();
			$context = ReadFlow_Display_Rules::build_context( $post );
			$base = ReadFlow_Display_Rules::base_decision( $context, $settings );
			if ( ! $base['allowed'] ) { return $content; }
			$effective = ReadFlow_Post_Overrides::for_post( (int) $post->ID, $settings );
			$manual_progress = false !== strpos( $content, 'data-readflow-inline=' );
			$manual_reading = false !== strpos( $content, 'data-readflow-shortcode=' );
			if ( $effective['disabled'] && ! $manual_progress ) { return $content; }
			$settings = $effective['settings'];
			$decision = ReadFlow_Display_Rules::evaluate( $context, $settings );
			if ( ( ! $decision['allowed'] && ! $manual_progress ) || isset( $this->rendered_posts[ $post->ID ] ) ) {
				return $content;
			}
			if ( false !== strpos( $content, 'data-readflow-post="' . $post->ID . '"' ) || false !== strpos( $content, 'data-readflow-article="' . $post->ID . '"' ) ) {
				$this->rendered_posts[ $post->ID ] = true;
				return $content;
			}
			if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
				return $content;
			}
			$calculation = $this->calculator->calculate_from_post( $post->ID, array( 'wpm' => $settings['wpm'] ) );
			if ( is_wp_error( $calculation ) || $calculation['word_count'] < 1 ) {
				return $content;
			}
			$html = $decision['reading_time'] && ! $manual_reading ? $this->renderer->render( $calculation, $settings ) : '';
			$this->rendered_posts[ $post->ID ] = true;
			$has_reading_info = '' !== $html;
			$assets = '';
			$inline_mode = $decision['progress'] && 'inline_progress' === $settings['progress_display'];
			$inline = $inline_mode ? $this->renderer->inline_progress( $settings ) : '';
			if ( $inline_mode ) {
				$assets .= ReadFlow_Assets::for_inline_progress();
				if ( 'selector' === $settings['position'] ) { $html .= $inline; }
				else { $content = 'after' === $settings['position'] ? $content . $inline : $inline . $content; }
			}
			if ( 'selector' === $settings['position'] && '' !== $html ) {
				if ( '' === $settings['target_selector'] ) { $html = ''; } else {
					ReadFlow_Assets::for_selector();
					if ( $has_reading_info ) { $assets .= ReadFlow_Assets::for_output(); }
					// Inert transport: no fallback before/after output, no duplicated renderer.
					$html = '<template data-readflow-placement data-readflow-selector="' . esc_attr( $settings['target_selector'] ) . '">' . $html . '</template>';
				}
			} elseif ( '' !== $html ) { $assets .= ReadFlow_Assets::for_output(); }
			if ( $decision['reader_controls'] && 'reading_info' === $settings['reader_controls_placement'] && '' !== $html && 'selector' !== $settings['position'] ) {
				$html = '<div data-readflow-reading-info="' . esc_attr( (string) $post->ID ) . '">' . $html . '</div>';
			}
			$controls = '';
			if ( $decision['reader_controls'] ) {
				$controls = ReadFlow_Reader_Controls::markup( $post->ID, $settings );
				$assets .= ReadFlow_Assets::for_reader_controls();
			}
			$bar = '';
			if ( $decision['reader_controls'] && ! $decision['progress'] && ! $decision['position_memory'] && ! $manual_progress ) {
				$content = $this->renderer->progress_article( $content, $post->ID );
			}
			if ( $decision['progress'] || $decision['position_memory'] || $manual_progress ) {
				$content = $this->renderer->progress_article( $content, $post->ID );
				$bar = $this->renderer->progress_bar( $settings, $calculation['reading_seconds'], $calculation['formatted_time'], $decision['position_memory'], $decision['progress'] && ! $inline_mode );
				$assets .= ReadFlow_Assets::for_progress( $decision['position_memory'] );
			}
			return $assets . $bar . $controls . ( 'after' === $settings['position'] ? $content . $html : $html . $content );
		} finally {
			$this->rendering = false;
		}
	}
}
