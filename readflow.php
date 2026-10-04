<?php
/**
 * Plugin Name: ReadFlow
 * Description: A free, lightweight reading time and reading experience plugin for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Chitvan
 * Author URI: https://profiles.wordpress.org/chitvan/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: readflow
 * Domain Path: /languages
 *
 * @package ReadFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'READFLOW_VERSION', '0.1.0' );
define( 'READFLOW_PLUGIN_FILE', __FILE__ );
define( 'READFLOW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'READFLOW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'READFLOW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once READFLOW_PLUGIN_DIR . 'includes/class-readflow-plugin.php';

add_action( 'plugins_loaded', array( 'ReadFlow_Plugin', 'init' ) );
