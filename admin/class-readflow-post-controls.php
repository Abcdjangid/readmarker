<?php
/** Standard classic/block-editor compatible metabox. No public REST meta exposure. @package ReadFlow */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReadFlow_Post_Controls {
	private $settings;
	public function __construct( ReadFlow_Settings $settings ) { $this->settings = $settings; }
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}
	public function supports( $type ) {
		return isset( ReadFlow_Settings::display_post_types()[ $type ] ) && in_array( $type, $this->settings->get()['post_types'], true );
	}
	public function add_boxes( $type, $post ) {
		if ( ! $post instanceof WP_Post || ! $this->supports( $type ) || ! current_user_can( 'edit_post', $post->ID ) ) { return; }
		add_meta_box( 'readflow-post-controls', __( 'ReadFlow', 'readflow' ), array( $this, 'render' ), $type, 'normal', 'default', array( '__block_editor_compatible_meta_box' => true ) );
	}
	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { return; }
		$screen = get_current_screen();
		$post = get_post();
		if ( ! $screen || ! $this->supports( $screen->post_type ) || ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) { return; }
		wp_enqueue_script( 'readflow-post-controls', READFLOW_PLUGIN_URL . 'assets/js/readflow-post-controls.js', array(), READFLOW_VERSION . '.' . filemtime( READFLOW_PLUGIN_DIR . 'assets/js/readflow-post-controls.js' ), true );
	}
	public function render( $post ) {
		if ( ! $post instanceof WP_Post || ! $this->supports( $post->post_type ) || ! current_user_can( 'edit_post', $post->ID ) ) { return; }
		$values = ReadFlow_Post_Overrides::normalize( get_post_meta( $post->ID, ReadFlow_Post_Overrides::META_KEY, true ) );
		wp_nonce_field( 'readflow_save_post_' . $post->ID, 'readflow_post_nonce' );
		echo '<div class="readflow-post-controls"><input type="hidden" name="readflow_post[version]" value="1"><fieldset><legend>' . esc_html__( 'ReadFlow behavior', 'readflow' ) . '</legend>';
		foreach ( array( 'global' => __( 'Use global settings', 'readflow' ), 'disabled' => __( 'Disable ReadFlow', 'readflow' ), 'override' => __( 'Override settings', 'readflow' ) ) as $value => $label ) {
			echo '<p><label><input type="radio" name="readflow_post[behavior]" value="' . esc_attr( $value ) . '" ' . checked( $values['behavior'], $value, false ) . '> ' . esc_html( $label ) . '</label></p>';
		}
		echo '</fieldset><p id="readflow-post-help">' . esc_html__( 'Overrides apply only on content allowed by Display Rules. Display mode follows Enable Reading Progress; visibility follows Enable Reading Time. WPM and position memory retain global settings.', 'readflow' ) . '</p><fieldset data-readflow-override-fields aria-describedby="readflow-post-help"><legend>' . esc_html__( 'Article overrides', 'readflow' ) . '</legend>';
		$global = array( 'global' => __( 'Use global setting', 'readflow' ) );
		$visibility = array( 'show' => __( 'Show', 'readflow' ), 'hide' => __( 'Hide', 'readflow' ) );
		$fields = array(
			'display_mode' => array( __( 'Display Mode', 'readflow' ), ReadFlow_Settings::progress_displays() ),
			'reading_time' => array( __( 'Reading Time', 'readflow' ), $visibility ),
			'word_count' => array( __( 'Word Count', 'readflow' ), $visibility ),
			'position' => array( __( 'Position', 'readflow' ), ReadFlow_Settings::positions() ),
		);
		foreach ( $fields as $key => $field ) {
			echo '<p><label for="readflow-post-' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label><br><select id="readflow-post-' . esc_attr( $key ) . '" name="readflow_post[' . esc_attr( $key ) . ']">';
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
		if ( ! isset( $_POST['readflow_post_nonce'] ) || ! is_string( $_POST['readflow_post_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['readflow_post_nonce'] ) ), 'readflow_save_post_' . $post_id ) ) { return; }
		if ( ! isset( $_POST['readflow_post'] ) || ! is_array( $_POST['readflow_post'] ) ) { return; }
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- for_storage() validates each field against the existing strict allowlists below.
		$raw = wp_unslash( $_POST['readflow_post'] );
		if ( ! isset( $raw['version'], $raw['behavior'] ) || ! in_array( $raw['version'], array( 1, '1' ), true ) || ! in_array( $raw['behavior'], array( 'global', 'disabled', 'override' ), true ) ) { return; }
		$stored = ReadFlow_Post_Overrides::for_storage( $raw );
		if ( null === $stored ) { delete_post_meta( $post_id, ReadFlow_Post_Overrides::META_KEY ); }
		else { update_post_meta( $post_id, ReadFlow_Post_Overrides::META_KEY, $stored ); }
	}
}
