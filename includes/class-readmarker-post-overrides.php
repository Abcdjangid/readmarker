<?php
/** Versioned per-article overrides and pure effective-settings resolution. @package ReadMarker */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReadMarker_Post_Overrides {
	const META_KEY = '_readflow_settings';
	const VERSION = 1;

	public static function defaults() {
		return array( 'version' => self::VERSION, 'behavior' => 'global', 'display_mode' => 'global', 'reading_time' => 'global', 'word_count' => 'global', 'position' => 'global' );
	}

	/** Allowlisted schema. Unsupported/malformed versions use global settings. */
	public static function normalize( $raw ) {
		$values = self::defaults();
		if ( ! is_array( $raw ) || ! isset( $raw['version'] ) || ! in_array( $raw['version'], array( 1, '1' ), true ) ) { return $values; }
		$choices = array(
			'behavior' => array( 'global', 'disabled', 'override' ),
			'display_mode' => array_merge( array( 'global' ), array_keys( ReadMarker_Settings::progress_displays() ) ),
			'reading_time' => array( 'global', 'show', 'hide' ),
			'word_count' => array( 'global', 'show', 'hide' ),
			'position' => array_merge( array( 'global' ), array_keys( ReadMarker_Settings::positions() ) ),
		);
		foreach ( $choices as $key => $allowed ) {
			if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && in_array( $raw[ $key ], $allowed, true ) ) { $values[ $key ] = $raw[ $key ]; }
		}
		return $values;
	}

	/** Minimal storage: null means delete. Never copy unrelated global settings. */
	public static function for_storage( $raw ) {
		$values = self::normalize( $raw );
		if ( 'global' === $values['behavior'] ) { return null; }
		$stored = array( 'version' => self::VERSION, 'behavior' => $values['behavior'] );
		if ( 'override' === $values['behavior'] ) {
			foreach ( array( 'display_mode', 'reading_time', 'word_count', 'position' ) as $key ) {
				if ( 'global' !== $values[ $key ] ) { $stored[ $key ] = $values[ $key ]; }
			}
		}
		return $stored;
	}

	/** Pure resolver. Master enables/WPM/content-type eligibility cannot be overridden. */
	public static function resolve( $global, $raw ) {
		$values = self::normalize( $raw );
		$effective = $global;
		$disabled = 'disabled' === $values['behavior'];
		if ( $disabled ) {
			$effective['enabled'] = false;
			$effective['progress_enabled'] = false;
			$effective['position_memory_enabled'] = false;
			$effective['reader_controls_enabled'] = false;
		} elseif ( 'override' === $values['behavior'] ) {
			foreach ( array( 'display_mode' => 'progress_display', 'position' => 'position' ) as $source => $target ) {
				if ( 'global' !== $values[ $source ] ) { $effective[ $target ] = $values[ $source ]; }
			}
			foreach ( array( 'reading_time' => 'show_time', 'word_count' => 'show_word_count' ) as $source => $target ) {
				if ( 'global' !== $values[ $source ] ) { $effective[ $target ] = 'show' === $values[ $source ]; }
			}
		}
		return array( 'settings' => $effective, 'disabled' => $disabled, 'behavior' => $values['behavior'] );
	}

	/** WordPress caches post meta; do not memoize across saves in the same request. */
	public static function for_post( $post_id, $global ) {
		$raw = is_int( $post_id ) && $post_id > 0 ? get_post_meta( $post_id, self::META_KEY, true ) : null;
		return self::resolve( $global, $raw );
	}
}
