<?php
/** Central automatic eligibility. No rendering, assets, geometry or persistent state. @package ReadMarker */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReadMarker_Display_Rules {
	/** Build once per content evaluation. Do not cache across secondary loops/query changes. */
	public static function build_context( $post = false ) {
		$post = false === $post ? get_post() : $post;
		$valid = $post instanceof WP_Post && $post->ID > 0;
		$type = $valid ? get_post_type_object( $post->post_type ) : null;
		return array(
			'is_admin' => is_admin(),
			'is_feed' => is_feed(),
			'is_rest' => ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_is_json_request(),
			'is_ajax' => wp_doing_ajax(),
			'is_preview' => is_preview(),
			'is_archive' => is_archive(),
			'is_singular' => is_singular(),
			'is_main_query' => is_main_query(),
			'in_the_loop' => in_the_loop(),
			'is_excerpt' => doing_filter( 'get_the_excerpt' ) || doing_filter( 'the_excerpt' ),
			'post_id' => $valid ? (int) $post->ID : 0,
			'queried_post_id' => (int) get_queried_object_id(),
			'post_type' => $valid ? $post->post_type : '',
			'public_post_type' => $type && ReadMarker_Settings::is_display_post_type( $type ),
			'password_protected' => $valid && post_password_required( $post ),
		);
	}

	/** Pure evaluation of scalar context + normalized settings; stable reason and feature flags. */
	public static function base_decision( $context, $settings ) {
		$context = is_array( $context ) ? $context : array();
		$settings = is_array( $settings ) ? $settings : array();
		$deny = static function ( $reason ) { return array( 'allowed' => false, 'reason' => $reason, 'reading_time' => false, 'progress' => false, 'position_memory' => false, 'reader_controls' => false ); };
		foreach ( array( 'is_admin' => 'admin', 'is_feed' => 'feed', 'is_rest' => 'rest', 'is_ajax' => 'ajax', 'is_preview' => 'preview', 'is_archive' => 'archive', 'is_excerpt' => 'excerpt' ) as $flag => $reason ) {
			if ( ! empty( $context[ $flag ] ) ) { return $deny( $reason ); }
		}
		if ( empty( $context['is_singular'] ) ) { return $deny( 'not_singular' ); }
		if ( empty( $context['post_id'] ) || ! is_int( $context['post_id'] ) || $context['post_id'] < 1 ) { return $deny( 'missing_post' ); }
		if ( empty( $context['is_main_query'] ) || empty( $context['in_the_loop'] ) || $context['post_id'] !== ( $context['queried_post_id'] ?? null ) ) { return $deny( 'secondary_query' ); }
		if ( ! empty( $context['password_protected'] ) ) { return $deny( 'password_protected' ); }
		if ( empty( $context['public_post_type'] ) ) { return $deny( 'invalid_post_type' ); }
		if ( ! isset( $settings['post_types'] ) || ! is_array( $settings['post_types'] ) || ! in_array( $context['post_type'] ?? '', $settings['post_types'], true ) ) { return $deny( 'post_type_disabled' ); }
		return array( 'allowed' => true, 'reason' => null );
	}

	/** Manual placements may run outside the loop, but never bypass protected contexts or current-post identity. */
	public static function manual_decision( $context, $settings ) {
		$context['in_the_loop'] = true;
		return self::base_decision( $context, $settings );
	}

	/** Effective feature policy after protected context and post-type eligibility. */
	public static function can_display( $context, $settings ) {
		$base = self::base_decision( $context, $settings );
		if ( ! $base['allowed'] ) { return $base; }
		$deny = static function ( $reason ) { return array( 'allowed' => false, 'reason' => $reason, 'reading_time' => false, 'progress' => false, 'position_memory' => false, 'reader_controls' => false ); };
		// Existing Manual only controls the reading-time insertion, not independently enabled progress/memory.
		$manual = 'manual' === ( $settings['position'] ?? 'before' );
		$time = ! empty( $settings['enabled'] ) && ! $manual && ( ! empty( $settings['show_time'] ) || ! empty( $settings['show_word_count'] ) );
		$progress = ! empty( $settings['progress_enabled'] );
		if ( 'inline_progress' === ( $settings['progress_display'] ?? '' ) ) {
			$progress = $progress && ! empty( $settings['enabled'] ) && ! $manual && ( 'selector' !== ( $settings['position'] ?? '' ) || ! empty( $settings['target_selector'] ) );
		}
		$memory = ! empty( $settings['position_memory_enabled'] );
		$controls = ! empty( $settings['reader_controls_enabled'] );
		if ( ! $time && ! $progress && ! $memory && ! $controls ) { return $deny( $manual ? 'manual_only' : 'disabled' ); }
		return array( 'allowed' => true, 'reason' => null, 'reading_time' => $time, 'progress' => $progress, 'position_memory' => $memory, 'reader_controls' => $controls );
	}

	/**
	 * Extension veto: readmarker_can_display(bool $allow, array $context, array $result).
	 * Called only after base rules pass. Returning anything except true suppresses
	 * automatic output; filters cannot bypass protected contexts or enable features.
	 * Context contains IDs/flags only, never post content. Manual renderer APIs are separate.
	 */
	public static function evaluate( $context, $settings ) {
		$result = self::can_display( $context, $settings );
		if ( $result['allowed'] && ( true !== apply_filters( 'readflow_can_display', true, $context, $result ) || true !== apply_filters( 'readmarker_can_display', true, $context, $result ) ) ) {
			return array( 'allowed' => false, 'reason' => 'filtered', 'reading_time' => false, 'progress' => false, 'position_memory' => false, 'reader_controls' => false );
		}
		return $result;
	}
}
