<?php
/** Standard classic/block-editor compatible metabox. No public REST meta exposure. @package ReadMarker */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReadMarker_Post_Controls {
	private $settings;
	public function __construct( ReadMarker_Settings $settings ) { $this->settings = $settings; }
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}
	public function supports( $type ) {
		return isset( ReadMarker_Settings::display_post_types()[ $type ] ) && in_array( $type, $this->settings->get()['post_types'], true );
	}
	public function add_boxes( $type, $post ) {
		if ( ! $post instanceof WP_Post || ! $this->supports( $type ) || ! current_user_can( 'edit_post', $post->ID ) ) { return; }
		add_meta_box( 'readmarker-post-controls', __( 'ReadMarker', 'readmarker' ), array( $this, 'render' ), $type, 'normal', 'default', array( '__block_editor_compatible_meta_box' => true ) );
	}
	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { return; }
		$screen = get_current_screen();
		$post = get_post();
		if ( ! $screen || ! $this->supports( $screen->post_type ) || ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) { return; }
		wp_enqueue_script( 'readmarker-post-controls', READMARKER_PLUGIN_URL . 'assets/js/readmarker-post-controls.js', array(), READMARKER_VERSION . '.' . filemtime( READMARKER_PLUGIN_DIR . 'assets/js/readmarker-post-controls.js' ), true );
	}
	public function render( $post ) {
		if ( ! $post instanceof WP_Post || ! $this->supports( $post->post_type ) || ! current_user_can( 'edit_post', $post->ID ) ) { return; }
		$values = ReadMarker_Post_Overrides::normalize( get_post_meta( $post->ID, ReadMarker_Post_Overrides::META_KEY, true ) );
		wp_nonce_field( 'readmarker_save_post_' . $post->ID, 'readmarker_post_nonce' );
		echo '<div class="readmarker-post-controls"><input type="hidden" name="readmarker_post[version]" value="1"><fieldset><legend>' . esc_html__( 'ReadMarker behavior', 'readmarker' ) . '</legend>';
		foreach ( array( 'global' => __( 'Use global settings', 'readmarker' ), 'disabled' => __( 'Disable ReadMarker', 'readmarker' ), 'override' => __( 'Override settings', 'readmarker' ) ) as $value => $label ) {
			echo '<p><label><input type="radio" name="readmarker_post[behavior]" value="' . esc_attr( $value ) . '" ' . checked( $values['behavior'], $value, false ) . '> ' . esc_html( $label ) . '</label></p>';
		}
		echo '</fieldset><p id="readmarker-post-help">' . esc_html__( 'Overrides apply only on content allowed by Display Rules. Display mode follows Enable Reading Progress; visibility follows Enable Reading Time. WPM and position memory retain global settings.', 'readmarker' ) . '</p><fieldset data-readmarker-override-fields aria-describedby="readmarker-post-help"><legend>' . esc_html__( 'Article overrides', 'readmarker' ) . '</legend>';
		$global = array( 'global' => __( 'Use global setting', 'readmarker' ) );
		$visibility = array( 'show' => __( 'Show', 'readmarker' ), 'hide' => __( 'Hide', 'readmarker' ) );
		$fields = array(
			'display_mode' => array( __( 'Display Mode', 'readmarker' ), ReadMarker_Settings::progress_displays() ),
			'reading_time' => array( __( 'Reading Time', 'readmarker' ), $visibility ),
			'word_count' => array( __( 'Word Count', 'readmarker' ), $visibility ),
			'position' => array( __( 'Position', 'readmarker' ), ReadMarker_Settings::positions() ),
		);
		foreach ( $fields as $key => $field ) {
			echo '<p><label for="readmarker-post-' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label><br><select id="readmarker-post-' . esc_attr( $key ) . '" name="readmarker_post[' . esc_attr( $key ) . ']">';
			foreach ( $global + $field[1] as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $values[ $key ], $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></p>';
		}
		echo '</fieldset></div>';
	}
	public function save( $post_id, $post ) {
		if ( ! is_int( $post_id ) || $post_id < 1 || ! $post instanceof WP_Post || (int) $post->ID !== $post_id ) { return; }
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || ! $this->supports( $post->post_type ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		if ( ! isset( $_POST['readmarker_post_nonce'] ) || ! is_string( $_POST['readmarker_post_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['readmarker_post_nonce'] ) ), 'readmarker_save_post_' . $post_id ) ) { return; }
		if ( ! isset( $_POST['readmarker_post'] ) || ! is_array( $_POST['readmarker_post'] ) ) { return; }
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- for_storage() validates each field against the existing strict allowlists below.
		$raw = wp_unslash( $_POST['readmarker_post'] );
		if ( ! isset( $raw['version'], $raw['behavior'] ) || ! in_array( $raw['version'], array( 1, '1' ), true ) || ! in_array( $raw['behavior'], array( 'global', 'disabled', 'override' ), true ) ) { return; }
		$stored = ReadMarker_Post_Overrides::for_storage( $raw );
		if ( null === $stored ) { delete_post_meta( $post_id, ReadMarker_Post_Overrides::META_KEY ); }
		else { update_post_meta( $post_id, ReadMarker_Post_Overrides::META_KEY, $stored ); }
	}
}
