<?php
/**
 * Uninstall intentionally retains global ReadFlow settings and per-post overrides.
 *
 * @package ReadFlow
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Retain configuration for reinstallations; browser preferences are unaffected.
