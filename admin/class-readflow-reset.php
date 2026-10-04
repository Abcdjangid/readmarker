<?php
/** Explicit global-settings reset; no reader or post data is involved. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReadFlow_Reset {

	private $settings;

	public function __construct( ReadFlow_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks() {
		add_action( 'admin_post_readflow_reset_settings', array( $this, 'handle' ) );
		add_action( 'load-readflow_page_readflow', array( $this, 'notice' ) );
	}

	/** Validate the explicit request before writing the single owned option. */
	public function reset() {
		if ( ! current_user_can( 'manage_options' ) || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' )
			|| ( ! isset( $_POST['action'] ) || ! is_string( $_POST['action'] ) || 'readflow_reset_settings' !== sanitize_text_field( wp_unslash( $_POST['action'] ) ) )
			|| ( ! isset( $_POST['readflow_reset_confirm'] ) || ! is_string( $_POST['readflow_reset_confirm'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['readflow_reset_confirm'] ) ) )
			|| ! isset( $_POST['readflow_reset_nonce'] ) || ! is_string( $_POST['readflow_reset_nonce'] ) ) {
			return false;
		}
		check_admin_referer( 'readflow_reset_settings', 'readflow_reset_nonce' );
		$defaults = $this->settings->normalize( ReadFlow_Settings::defaults() );
		update_option( ReadFlow_Settings::OPTION, $defaults );
		// update_option also returns false when the option already equals defaults.
		return get_option( ReadFlow_Settings::OPTION ) === $defaults;
	}

	public function handle() {
		if ( ! $this->reset() ) {
			wp_die( esc_html__( 'ReadFlow settings could not be reset. Check your confirmation and try again.', 'readflow' ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect( add_query_arg( 'readflow_reset_done', wp_create_nonce( 'readflow_reset_done' ), admin_url( 'admin.php?page=readflow' ) ) );
		exit;
	}

	public function notice() {
		$nonce = isset( $_GET['readflow_reset_done'] ) && is_string( $_GET['readflow_reset_done'] ) ? sanitize_text_field( wp_unslash( $_GET['readflow_reset_done'] ) ) : null;
		if ( current_user_can( 'manage_options' ) && is_string( $nonce ) && wp_verify_nonce( $nonce, 'readflow_reset_done' ) ) {
			add_settings_error( 'readflow_reset', 'readflow_reset_done', __( 'ReadFlow global settings restored to defaults.', 'readflow' ), 'success' );
		}
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<hr><section aria-labelledby="readflow-reset-heading"><h2 id="readflow-reset-heading">' . esc_html__( 'Reset Settings', 'readflow' ) . '</h2><p>' . esc_html__( 'Restore all global ReadFlow settings to their original defaults. Individual post settings and saved reader preferences will not be changed.', 'readflow' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="readflow_reset_settings">';
		wp_nonce_field( 'readflow_reset_settings', 'readflow_reset_nonce' );
		echo '<p><label><input type="checkbox" name="readflow_reset_confirm" value="1" required> ' . esc_html__( 'I confirm that I want to reset all ReadFlow global settings to their defaults.', 'readflow' ) . '</label></p>';
		submit_button( __( 'Reset to Defaults', 'readflow' ), 'secondary' );
		echo '</form></section>';
	}
}
