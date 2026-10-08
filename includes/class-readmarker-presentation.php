<?php
/** Shared manual presentation service. No calculations, geometry, or arbitrary HTML attributes. @package ReadMarker */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadMarker_Presentation {
	private $settings;
	private $calculator;
	private $renderer;
	private $cache = array();
	public function __construct( ReadMarker_Settings $settings, ReadMarker_Calculator $calculator, ReadMarker_Renderer $renderer ) {
		$this->settings = $settings; $this->calculator = $calculator; $this->renderer = $renderer;
	}
	/** Unknown attributes, including post_id, are ignored. Only current eligible content is supported. */
	public function render( $attributes = array(), $content = null, $tag = 'readmarker', $combined = false ) {
		if ( ! in_array( $tag, array( 'readmarker', 'readmarker_time', 'readmarker_words', 'readmarker_progress', 'readmarker_remaining' ), true ) ) { return ''; }
		$post = get_post(); $global = $this->settings->get();
		if ( ! ReadMarker_Display_Rules::manual_decision( ReadMarker_Display_Rules::build_context( $post ), $global )['allowed'] ) { return ''; }
		$effective = ReadMarker_Post_Overrides::for_post( (int) $post->ID, $global )['settings'];
		$key = $post->ID . ':' . $effective['wpm'];
		if ( ! isset( $this->cache[ $key ] ) ) { $this->cache[ $key ] = $this->calculator->calculate_from_post( (int) $post->ID, array( 'wpm' => $effective['wpm'] ) ); }
		$data = $this->cache[ $key ];
		if ( is_wp_error( $data ) || $data['word_count'] < 1 ) { return ''; }
		$attributes = is_array( $attributes ) ? $attributes : array();
		$allowed = array( 'format' => 'short', 'label' => 'true', 'show_percentage' => 'true' );
		if ( 'readmarker_remaining' === $tag ) { $allowed['format'] = 'natural'; }
		foreach ( $allowed as $key => $default ) {
			$choices = 'format' === $key ? ( 'readmarker_remaining' === $tag ? array( 'natural', 'short', 'clock', 'detailed' ) : array( 'short', 'long' ) ) : array( 'true', 'false' );
			$allowed[ $key ] = isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) && in_array( $attributes[ $key ], $choices, true ) ? $attributes[ $key ] : $default;
		}
		if ( $combined ) { $effective['show_time'] = true; $effective['show_word_count'] = true; $effective['style'] = 'meta'; }
		$html = $this->renderer->shortcode( $tag, $data, $effective, $allowed );
		// Dynamic assets are requested by frontend integration after it establishes the article target.
		return ( 'readmarker' === $tag && '' !== $html ? ReadMarker_Assets::for_output() : '' ) . $html;
	}
}
