<?php
/** Output-driven frontend assets shared by integrations. @package ReadFlow */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadFlow_Assets {
	/**
	 * Call only when nonempty ReadFlow markup will be returned.
	 * Before wp_head: WordPress prints the queued stylesheet normally.
	 * After wp_head: capture WordPress's stylesheet link beside the output, since
	 * classic themes render content too late for the head. Its done registry
	 * prevents duplicate links. No footer hook, global enqueue, or inline CSS.
	 *
	 * @return string Late stylesheet HTML, or empty string if queued/already printed.
	 */
	public static function for_output() {
		return self::stylesheet( 'readflow-display', 'assets/css/readflow.css' );
	}

	public static function for_inline_progress() { return self::stylesheet( 'readflow-inline-progress-mode', 'assets/css/readflow-inline-progress-mode.css' ); }

	public static function for_reader_controls() {
		wp_enqueue_script( 'readflow-reader-controls', READFLOW_PLUGIN_URL . 'assets/js/readflow-reader-controls.js', array(), self::version( 'assets/js/readflow-reader-controls.js' ), true );
		return self::stylesheet( 'readflow-reader-controls', 'assets/css/readflow-reader-controls.css' );
	}

	/** Placement has no progress dependencies; only called for nonempty automatic output. */
	public static function for_selector() {
		wp_enqueue_script( 'readflow-placement', READFLOW_PLUGIN_URL . 'assets/js/readflow-placement.js', array(), self::version( 'assets/js/readflow-placement.js' ), true );
	}

	/** Enqueue only alongside a marked article; the script boots in the footer. */
	public static function for_progress( $memory = false ) {
		wp_register_script( 'readflow-remaining-time', READFLOW_PLUGIN_URL . 'assets/js/readflow-remaining-time.js', array(), self::version( 'assets/js/readflow-remaining-time.js' ), true );
		$dependencies = array( 'readflow-remaining-time' );
		if ( $memory ) {
			wp_register_script( 'readflow-position-memory', READFLOW_PLUGIN_URL . 'assets/js/readflow-position-memory.js', array(), self::version( 'assets/js/readflow-position-memory.js' ), true );
			$dependencies[] = 'readflow-position-memory';
		}
		wp_enqueue_script( 'readflow-progress', READFLOW_PLUGIN_URL . 'assets/js/readflow-progress.js', $dependencies, self::version( 'assets/js/readflow-progress.js' ), true );
		return self::stylesheet( 'readflow-progress', 'assets/css/readflow-progress.css' );
	}

	/** Shared late/head stylesheet handling for both independent features. */
	private static function stylesheet( $handle, $path ) {
		wp_enqueue_style( $handle, READFLOW_PLUGIN_URL . $path, array(), self::version( $path ) );
		if ( did_action( 'wp_head' ) && ! wp_style_is( $handle, 'done' ) ) {
			ob_start();
			wp_print_styles( array( $handle ) );
			return ob_get_clean();
		}
		return '';
	}

	/** Keep upgraded markup and assets in sync during incremental development. */
	private static function version( $path ) {
		$file = READFLOW_PLUGIN_DIR . $path;
		return is_file( $file ) ? READFLOW_VERSION . '.' . filemtime( $file ) : READFLOW_VERSION;
	}
}
