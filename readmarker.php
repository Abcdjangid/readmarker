<?php
/**
 * Plugin Name: ReadMarker
 * Description: A free, lightweight reading time and reading experience plugin for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Chitvan
 * Author URI: https://profiles.wordpress.org/chitvan/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: readmarker
 * Domain Path: /languages
 *
 * @package ReadMarker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'READMARKER_VERSION', '0.1.0' );
define( 'READMARKER_PLUGIN_FILE', __FILE__ );
define( 'READMARKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'READMARKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'READMARKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once READMARKER_PLUGIN_DIR . 'includes/class-readmarker-plugin.php';

add_action( 'plugins_loaded', array( 'ReadMarker_Plugin', 'init' ) );
