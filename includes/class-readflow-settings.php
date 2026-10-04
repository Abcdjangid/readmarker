<?php
/**
 * Central settings schema. Legacy options are upgraded lazily when used.
 * Missing keys receive defaults for upgrades; unknown keys are discarded.
 *
 * @package ReadFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadFlow_Settings {
	const OPTION = 'readflow_settings';
	const GROUP  = 'readflow_settings_group';
	const SCHEMA_VERSION = 1;
	private $migrating = false;

	/** @return array Defaults shared by storage, admin and integration. */
	public static function defaults() {
		return array(
			'schema_version'  => self::SCHEMA_VERSION,
			'enabled'         => true,
			'wpm'             => (float) ReadFlow_Calculator::DEFAULT_WPM,
			'post_types'      => array( 'post' ),
			'style'           => 'simple',
			'show_time'       => true,
			'show_word_count' => false,
			'position'        => 'before',
			'target_selector' => '',
			'progress_enabled' => false,
			'position_memory_enabled' => false,
			'reader_controls_enabled' => false,
			'reader_controls_placement' => 'reading_info',
			'reader_controls_visibility' => 'all',
			'reader_controls_panel' => 'collapsed',
			'progress_color'   => '#2563eb',
			'progress_height'  => 3,
			'progress_display' => 'top_bar',
		);
	}

	/** @return array Style registry; adding a style needs only a key and its CSS. */
	public static function styles() {
		return array(
			'simple' => __( 'Simple', 'readflow' ),
			'badge'  => __( 'Badge', 'readflow' ),
			'meta'   => __( 'Meta', 'readflow' ),
			'card'   => __( 'Card', 'readflow' ),
		);
	}

	/** @return array Supported progress consumers; existing installations retain Top Bar. */
	public static function progress_displays() {
		return array(
			'top_bar' => __( 'Top Bar', 'readflow' ),
			'inline_progress' => __( 'Inline Reading Progress', 'readflow' ),
			'circular' => __( 'Circular', 'readflow' ),
			'percentage' => __( 'Percentage', 'readflow' ),
			'top_bar_percentage' => __( 'Top Bar + Percentage', 'readflow' ),
			'remaining' => __( 'Remaining Time', 'readflow' ),
			'time_remaining' => __( 'Reading Time + Remaining', 'readflow' ),
			'percentage_remaining' => __( 'Percentage + Remaining', 'readflow' ),
			'countdown' => __( 'Countdown', 'readflow' ),
			'floating_widget' => __( 'Floating Widget', 'readflow' ),
			'estimated_finish_time' => __( 'Estimated Finish Time', 'readflow' ),
			'reading_milestones' => __( 'Reading Milestones', 'readflow' ),
		);
	}

	/** @return array Automatic placement choices. */
	public static function positions() {
		return array(
			'before' => __( 'Before content', 'readflow' ),
			'after'  => __( 'After content', 'readflow' ),
			'selector' => __( 'Custom Selector', 'readflow' ),
			'manual' => __( 'Manual only', 'readflow' ),
		);
	}

	/** Reader Controls site configuration; never stored in browser preferences. */
	public static function reader_control_choices() {
		return array(
			'reader_controls_placement' => array( 'above'=>__( 'Above Article', 'readflow' ), 'below'=>__( 'Below Article', 'readflow' ), 'reading_info'=>__( 'With Reading Info', 'readflow' ) ),
			'reader_controls_visibility' => array( 'all'=>__( 'Desktop & Mobile', 'readflow' ), 'desktop'=>__( 'Desktop Only', 'readflow' ), 'mobile'=>__( 'Mobile Only', 'readflow' ) ),
			'reader_controls_panel' => array( 'collapsed'=>__( 'Collapsed', 'readflow' ), 'expanded'=>__( 'Expanded', 'readflow' ) ),
		);
	}

	/** Shared discovery policy for settings and request context. Attachments are media, not articles. */
	public static function is_display_post_type( $type ) {
		return is_object( $type ) && ! empty( $type->public ) && isset( $type->name ) && ! in_array( $type->name, array( 'attachment', 'revision', 'nav_menu_item' ), true );
	}

	public static function display_post_types() {
		return array_filter( get_post_types( array( 'public' => true ), 'objects' ), array( __CLASS__, 'is_display_post_type' ) );
	}

	/** Register through Settings API; options.php supplies capability and nonce checks. */
	public function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'normalize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/** @return array Current settings, validated even if stored outside the admin form. */
	public function get() {
		$stored = get_option( self::OPTION, self::defaults() );
		$version = is_array( $stored ) && isset( $stored['schema_version'] ) && is_int( $stored['schema_version'] ) && $stored['schema_version'] >= 0 ? $stored['schema_version'] : 0;
		$settings = $version < self::SCHEMA_VERSION ? $this->migrate( $stored, $version ) : $stored;
		$normalized = $this->normalize( $settings );
		// Future schemas are read compatibly, never automatically downgraded or written.
		// Their unknown fields remain intact in storage. An explicit save/reset still
		// uses this release's canonical schema through normalize().
		if ( $version < self::SCHEMA_VERSION && ! $this->migrating && $this->can_persist_migration() ) {
			$this->migrating = true;
			try {
				update_option( self::OPTION, $normalized );
			} finally {
				$this->migrating = false;
			}
		}
		return $normalized;
	}

	/** Version 0 -> 1 introduces the schema marker; existing validation owns aliases. */
	private function migrate( $settings, $from_version ) {
		$settings = is_array( $settings ) ? $settings : array();
		if ( 0 === $from_version ) {
			$settings['schema_version'] = 1;
		}
		return $settings;
	}

	/** No incidental writes on unrelated admin screens or API requests. */
	private function can_persist_migration() {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) { return false; }
		if ( ! is_admin() ) { return true; }
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'readflow_page_readflow' === $screen->id && current_user_can( 'manage_options' );
	}

	/**
	 * Normalize untrusted settings. Form checkboxes submit hidden zero/empty values.
	 * Reuses the calculator's public API on empty content for WPM validation, so
	 * its private validation policy remains authoritative and unchanged.
	 *
	 * @param mixed $input Stored option or submitted form values.
	 * @return array Known keys with safe, predictable types.
	 */
	public function normalize( $input ) {
		$defaults = self::defaults();
		$input = is_array( $input ) ? $input : array();
		// Compatibility adapter: never overwrite modern selections.
		if ( ! array_key_exists( 'post_types', $input ) && isset( $input['post_type'] ) && is_string( $input['post_type'] ) ) {
			$legacy = array( 'posts' => array( 'post' ), 'pages' => array( 'page' ), 'both' => array( 'post', 'page' ) );
			$input['post_types'] = $legacy[ $input['post_type'] ] ?? array( $input['post_type'] );
		}
		$input = array_intersect_key( $input, $defaults );
		$output   = array_merge( $defaults, $input );
		$output['schema_version'] = self::SCHEMA_VERSION;
		foreach ( array( 'enabled', 'show_time', 'show_word_count', 'progress_enabled', 'position_memory_enabled', 'reader_controls_enabled' ) as $key ) {
			$output[ $key ] = is_scalar( $output[ $key ] ) && filter_var( $output[ $key ], FILTER_VALIDATE_BOOLEAN );
		}
		$calculator   = new ReadFlow_Calculator();
		$validated    = $calculator->calculate_from_content( '', array( 'wpm' => $output['wpm'] ) );
		$output['wpm'] = is_wp_error( $validated ) ? $defaults['wpm'] : $validated['words_per_minute'];
		foreach ( array_merge( array( 'style' => self::styles(), 'position' => self::positions(), 'progress_display' => self::progress_displays() ), self::reader_control_choices() ) as $key => $choices ) {
			if ( ! is_string( $output[ $key ] ) || ! isset( $choices[ $output[ $key ] ] ) ) {
				$output[ $key ] = $defaults[ $key ];
			}
		}
		// Preserve CSS punctuation/escapes. Reject invalid UTF-8/control bytes; browser parses CSS.
		$output['target_selector'] = is_string( $output['target_selector'] ) ? trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', wp_check_invalid_utf8( $output['target_selector'] ) ) ) : '';
		$allowed = array_keys( self::display_post_types() );
		$types   = array();
		foreach ( is_array( $output['post_types'] ) ? $output['post_types'] : array() as $type ) {
			if ( is_string( $type ) && in_array( $type, $allowed, true ) ) {
				$types[] = $type;
			}
		}
		$output['post_types'] = array_values( array_unique( $types ) );
		$color = is_string( $output['progress_color'] ) ? sanitize_hex_color( $output['progress_color'] ) : null;
		$output['progress_color'] = $color ? $color : $defaults['progress_color'];
		$height = is_numeric( $output['progress_height'] ) ? (float) $output['progress_height'] : 0;
		$output['progress_height'] = is_finite( $height ) && $height >= 1 && $height <= 20 ? (int) round( $height ) : $defaults['progress_height'];
		return $output;
	}
}
