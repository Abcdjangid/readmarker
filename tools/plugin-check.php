<?php
/** Development-only: wp eval-file tools/plugin-check.php <plugin directory or slug>. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }

require_once WP_PLUGIN_DIR . '/plugin-check/vendor/autoload.php';

// Invoking the official checker after WordPress loads runs static checks only.
// This avoids installing its runtime object-cache drop-in into the user's site.
$readmarker_checker = new WordPress\Plugin_Check\CLI\Plugin_Check_Command(
 new WordPress\Plugin_Check\Plugin_Context( WP_PLUGIN_DIR . '/plugin-check/plugin.php' )
);
$readmarker_target = $args[0] ?? 'readmarker';
$readmarker_options = array( 'format' => 'json', 'slug' => 'readmarker' );
if ( 'readmarker' === $readmarker_target || realpath( $readmarker_target ) === dirname( __DIR__ ) ) {
 $readmarker_options['exclude-directories'] = '.release';
}
$readmarker_checker->check( array( $readmarker_target ), $readmarker_options );
