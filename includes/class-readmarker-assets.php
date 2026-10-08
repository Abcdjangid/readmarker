<?php
/** Output-driven frontend assets shared by integrations. @package ReadMarker */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadMarker_Assets {
	/**
	 * Call only when nonempty ReadMarker markup will be returned.
	 * Before wp_head: WordPress prints the queued stylesheet normally.
	 * After wp_head: capture WordPress's stylesheet link beside the output, since
	 * classic themes render content too late for the head. Its done registry
	 * prevents duplicate links. No footer hook, global enqueue, or inline CSS.
	 *
	 * @return string Late stylesheet HTML, or empty string if queued/already printed.
	 */
	public static function for_output() {
		return self::stylesheet( 'readmarker-display', 'assets/css/readmarker.css' );
	}

	public static function for_inline_progress() { return self::stylesheet( 'readmarker-inline-progress-mode', 'assets/css/readmarker-inline-progress-mode.css' ); }

	public static function for_reader_controls() {
		wp_enqueue_script( 'readmarker-reader-controls', READMARKER_PLUGIN_URL . 'assets/js/readmarker-reader-controls.js', array(), self::version( 'assets/js/readmarker-reader-controls.js' ), true );
		return self::stylesheet( 'readmarker-reader-controls', 'assets/css/readmarker-reader-controls.css' );
	}

	/** Placement has no progress dependencies; only called for nonempty automatic output. */
	public static function for_selector() {
		wp_enqueue_script( 'readmarker-placement', READMARKER_PLUGIN_URL . 'assets/js/readmarker-placement.js', array(), self::version( 'assets/js/readmarker-placement.js' ), true );
	}

	/** Enqueue only alongside a marked article; the script boots in the footer. */
	public static function for_progress( $memory = false ) {
		wp_register_script( 'readmarker-remaining-time', READMARKER_PLUGIN_URL . 'assets/js/readmarker-remaining-time.js', array(), self::version( 'assets/js/readmarker-remaining-time.js' ), true );
		$dependencies = array( 'readmarker-remaining-time' );
		if ( $memory ) {
			wp_register_script( 'readmarker-position-memory', READMARKER_PLUGIN_URL . 'assets/js/readmarker-position-memory.js', array(), self::version( 'assets/js/readmarker-position-memory.js' ), true );
			$dependencies[] = 'readmarker-position-memory';
		}
		wp_enqueue_script( 'readmarker-progress', READMARKER_PLUGIN_URL . 'assets/js/readmarker-progress.js', $dependencies, self::version( 'assets/js/readmarker-progress.js' ), true );
		return self::stylesheet( 'readmarker-progress', 'assets/css/readmarker-progress.css' );
	}

	/** Shared late/head stylesheet handling for both independent features. */
	private static function stylesheet( $handle, $path ) {
		wp_enqueue_style( $handle, READMARKER_PLUGIN_URL . $path, array(), self::version( $path ) );
		if ( did_action( 'wp_head' ) && ! wp_style_is( $handle, 'done' ) ) {
			ob_start();
			wp_print_styles( array( $handle ) );
			return ob_get_clean();
		}
		return '';
	}

	/** Keep upgraded markup and assets in sync during incremental development. */
	private static function version( $path ) {
		$file = READMARKER_PLUGIN_DIR . $path;
		return is_file( $file ) ? READMARKER_VERSION . '.' . filemtime( $file ) : READMARKER_VERSION;
	}
}
