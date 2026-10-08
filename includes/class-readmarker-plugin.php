<?php
/**
 * Coordinates plugin initialization.
 *
 * @package ReadMarker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadMarker_Plugin {

	/**
	 * Whether the plugin has initialized in this request.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initializes the foundation once, after plugins have loaded.
	 *
	 * Future modules should be registered here as they are implemented.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-time-formatter.php';
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-calculator.php';
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-settings.php';
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-renderer.php';
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-assets.php';
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-display-rules.php';
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-post-overrides.php';
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-frontend.php';
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-reader-controls.php';

		$settings = new ReadMarker_Settings();
		add_action( 'admin_init', array( $settings, 'register' ) );
		$frontend = new ReadMarker_Frontend( $settings, new ReadMarker_Calculator(), new ReadMarker_Renderer() );
		$frontend->register_hooks();
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-presentation.php';
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-block.php';
		$presentation = new ReadMarker_Presentation( $settings, new ReadMarker_Calculator(), new ReadMarker_Renderer() );
		$block = new ReadMarker_Block( $presentation );
		add_action( 'init', array( $block, 'register' ) );
		require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-shortcodes.php';
		$shortcodes = new ReadMarker_Shortcodes( $settings, new ReadMarker_Calculator(), new ReadMarker_Renderer(), $presentation );
		$shortcodes->register();

		if ( is_admin() ) {
			require_once READMARKER_PLUGIN_DIR . 'admin/class-readmarker-shortcodes-admin.php';
			$shortcodes_admin = new ReadMarker_Shortcodes_Admin();
			$shortcodes_admin->register_hooks();
			require_once READMARKER_PLUGIN_DIR . 'admin/class-readmarker-admin.php';
			$admin = new ReadMarker_Admin( $settings );
			$admin->register_hooks();
			require_once READMARKER_PLUGIN_DIR . 'admin/class-readmarker-dashboard.php';
			$dashboard = new ReadMarker_Dashboard( $settings );
			$dashboard->register_hooks();
			require_once READMARKER_PLUGIN_DIR . 'admin/class-readmarker-post-controls.php';
			$post_controls = new ReadMarker_Post_Controls( $settings );
			$post_controls->register_hooks();
		}

		/**
		 * Fires once ReadMarker has initialized.
		 *
		 * @since 0.1.0
		 */
		do_action( 'readmarker_loaded' );
		// Compatibility for integrations registered before the ReadMarker rename.
		do_action( 'readflow_loaded' );
	}
}
