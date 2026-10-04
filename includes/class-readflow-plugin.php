<?php
/**
 * Coordinates plugin initialization.
 *
 * @package ReadFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadFlow_Plugin {

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

		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-time-formatter.php';
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-calculator.php';
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-settings.php';
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-renderer.php';
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-assets.php';
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-display-rules.php';
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-post-overrides.php';
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-frontend.php';
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-reader-controls.php';

		$settings = new ReadFlow_Settings();
		add_action( 'admin_init', array( $settings, 'register' ) );
		$frontend = new ReadFlow_Frontend( $settings, new ReadFlow_Calculator(), new ReadFlow_Renderer() );
		$frontend->register_hooks();
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-presentation.php';
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-block.php';
		$presentation = new ReadFlow_Presentation( $settings, new ReadFlow_Calculator(), new ReadFlow_Renderer() );
		$block = new ReadFlow_Block( $presentation );
		add_action( 'init', array( $block, 'register' ) );
		require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-shortcodes.php';
		$shortcodes = new ReadFlow_Shortcodes( $settings, new ReadFlow_Calculator(), new ReadFlow_Renderer(), $presentation );
		$shortcodes->register();

		if ( is_admin() ) {
			require_once READFLOW_PLUGIN_DIR . 'admin/class-readflow-shortcodes-admin.php';
			$shortcodes_admin = new ReadFlow_Shortcodes_Admin();
			$shortcodes_admin->register_hooks();
			require_once READFLOW_PLUGIN_DIR . 'admin/class-readflow-admin.php';
			$admin = new ReadFlow_Admin( $settings );
			$admin->register_hooks();
			require_once READFLOW_PLUGIN_DIR . 'admin/class-readflow-dashboard.php';
			$dashboard = new ReadFlow_Dashboard( $settings );
			$dashboard->register_hooks();
			require_once READFLOW_PLUGIN_DIR . 'admin/class-readflow-post-controls.php';
			$post_controls = new ReadFlow_Post_Controls( $settings );
			$post_controls->register_hooks();
		}

		/**
		 * Fires once ReadFlow has initialized.
		 *
		 * @since 0.1.0
		 */
		do_action( 'readflow_loaded' );
	}
}
