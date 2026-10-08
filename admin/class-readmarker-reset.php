<?php
/** Explicit global-settings reset; no reader or post data is involved. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReadMarker_Reset {

	private $settings;

	public function __construct( ReadMarker_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks() {
		add_action( 'admin_post_readmarker_reset_settings', array( $this, 'handle' ) );
		add_action( 'load-readmarker_page_readmarker', array( $this, 'notice' ) );
	}

	/** Validate the explicit request before writing the single owned option. */
	public function reset() {
		if ( ! current_user_can( 'manage_options' ) || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' )
			|| ( ! isset( $_POST['action'] ) || ! is_string( $_POST['action'] ) || 'readmarker_reset_settings' !== sanitize_text_field( wp_unslash( $_POST['action'] ) ) )
			|| ( ! isset( $_POST['readmarker_reset_confirm'] ) || ! is_string( $_POST['readmarker_reset_confirm'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['readmarker_reset_confirm'] ) ) )
			|| ! isset( $_POST['readmarker_reset_nonce'] ) || ! is_string( $_POST['readmarker_reset_nonce'] ) ) {
			return false;
		}
		check_admin_referer( 'readmarker_reset_settings', 'readmarker_reset_nonce' );
		$defaults = $this->settings->normalize( ReadMarker_Settings::defaults() );
		update_option( ReadMarker_Settings::OPTION, $defaults );
		// update_option also returns false when the option already equals defaults.
		return get_option( ReadMarker_Settings::OPTION ) === $defaults;
	}

	public function handle() {
		if ( ! $this->reset() ) {
			wp_die( esc_html__( 'ReadMarker settings could not be reset. Check your confirmation and try again.', 'readmarker' ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect( add_query_arg( 'readmarker_reset_done', wp_create_nonce( 'readmarker_reset_done' ), admin_url( 'admin.php?page=readmarker' ) ) );
		exit;
	}

	public function notice() {
		$nonce = isset( $_GET['readmarker_reset_done'] ) && is_string( $_GET['readmarker_reset_done'] ) ? sanitize_text_field( wp_unslash( $_GET['readmarker_reset_done'] ) ) : null;
		if ( current_user_can( 'manage_options' ) && is_string( $nonce ) && wp_verify_nonce( $nonce, 'readmarker_reset_done' ) ) {
			add_settings_error( 'readmarker_reset', 'readmarker_reset_done', __( 'ReadMarker global settings restored to defaults.', 'readmarker' ), 'success' );
		}
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<hr><section aria-labelledby="readmarker-reset-heading"><h2 id="readmarker-reset-heading">' . esc_html__( 'Reset Settings', 'readmarker' ) . '</h2><p>' . esc_html__( 'Restore all global ReadMarker settings to their original defaults. Individual post settings and saved reader preferences will not be changed.', 'readmarker' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="readmarker_reset_settings">';
		wp_nonce_field( 'readmarker_reset_settings', 'readmarker_reset_nonce' );
		echo '<p><label><input type="checkbox" name="readmarker_reset_confirm" value="1" required> ' . esc_html__( 'I confirm that I want to reset all ReadMarker global settings to their defaults.', 'readmarker' ) . '</label></p>';
		submit_button( __( 'Reset to Defaults', 'readmarker' ), 'secondary' );
		echo '</form></section>';
	}
}
