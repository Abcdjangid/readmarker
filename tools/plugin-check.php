<?php
/** Development-only: wp eval-file tools/plugin-check.php <plugin directory or slug>. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }

require_once WP_PLUGIN_DIR . '/plugin-check/vendor/autoload.php';

// Invoking the official checker after WordPress loads runs static checks only.
// This avoids installing its runtime object-cache drop-in into the user's site.
$readflow_checker = new WordPress\Plugin_Check\CLI\Plugin_Check_Command(
 new WordPress\Plugin_Check\Plugin_Context( WP_PLUGIN_DIR . '/plugin-check/plugin.php' )
);
$readflow_target = $args[0] ?? 'readflow';
$readflow_options = array( 'format' => 'json', 'slug' => 'readflow' );
if ( 'readflow' === $readflow_target || realpath( $readflow_target ) === dirname( __DIR__ ) ) {
 $readflow_options['exclude-directories'] = '.release';
}
$readflow_checker->check( array( $readflow_target ), $readflow_options );
