<?php
/**
 * Uninstall intentionally retains global ReadMarker settings and per-post overrides.
 *
 * @package ReadMarker
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Retain configuration for reinstallations; browser preferences are unaffected.
