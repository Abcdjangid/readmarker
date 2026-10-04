<?php
/** Run php tests/post-overrides-test.php: pure, WP API adapters, frontend and save security. @package ReadFlow */
if ( 'cli' !== PHP_SAPI ) { exit; }
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$override_start = $checks;
require_once dirname( __DIR__ ) . '/admin/class-readflow-post-controls.php';
$global = ReadFlow_Settings::defaults();
foreach ( array( null, '', array(), new stdClass(), array( 'version' => 2, 'behavior' => 'disabled' ), array( 'version' => 1, 'behavior' => array() ), array( 'version' => 1, 'behavior' => 'global', 'word_count' => 'show' ) ) as $raw ) {
 $resolved = ReadFlow_Post_Overrides::resolve( $global, $raw );
 readflow_check( $global === $resolved['settings'] && ! $resolved['disabled'], 'Missing/malformed/global meta preserves settings' );
}
$disabled = array( 'version' => 1, 'behavior' => 'disabled' );
$resolved = ReadFlow_Post_Overrides::resolve( array_merge( $global, array( 'progress_enabled' => true, 'position_memory_enabled' => true ) ), $disabled );
readflow_check( $resolved['disabled'] && ! $resolved['settings']['enabled'] && ! $resolved['settings']['progress_enabled'] && ! $resolved['settings']['position_memory_enabled'], 'Disable affects every automatic feature' );
readflow_check( $disabled === ReadFlow_Post_Overrides::for_storage( array_merge( $disabled, array( 'word_count' => 'show', 'display_mode' => 'floating_widget' ) ) ), 'Disable storage minimal' );
readflow_check( null === ReadFlow_Post_Overrides::for_storage( ReadFlow_Post_Overrides::defaults() ), 'Global storage deleted' );
$partial = array( 'version' => 1, 'behavior' => 'override', 'display_mode' => 'floating_widget', 'word_count' => 'show' );
$expected = array_merge( $global, array( 'progress_display' => 'floating_widget', 'show_word_count' => true ) );
readflow_check( $expected === ReadFlow_Post_Overrides::resolve( $global, $partial )['settings'], 'Partial override preserves unrelated settings' );
readflow_check( $partial === ReadFlow_Post_Overrides::for_storage( $partial ), 'Only explicit values stored' );
foreach ( array_keys( ReadFlow_Settings::progress_displays() ) as $mode ) {
 readflow_check( $mode === ReadFlow_Post_Overrides::resolve( $global, array_merge( $partial, array( 'display_mode' => $mode ) ) )['settings']['progress_display'], 'All existing display modes override: ' . $mode );
}
foreach ( array( 'global', 'show', 'hide', 'bad', array(), null, '<script>' ) as $value ) {
 $raw = array( 'version' => 1, 'behavior' => 'override', 'reading_time' => $value, 'word_count' => $value );
 $resolved = ReadFlow_Post_Overrides::resolve( $global, $raw )['settings'];
 readflow_check( ( 'hide' !== $value ) === $resolved['show_time'] && ( 'show' === $value ) === $resolved['show_word_count'], 'Visibility allowlist/global fallback' );
}
foreach ( array( 'before', 'after', 'manual', 'global', 'bad', array() ) as $value ) {
 $raw = array( 'version' => 1, 'behavior' => 'override', 'position' => $value );
 readflow_check( ( in_array( $value, array( 'before', 'after', 'manual' ), true ) ? $value : 'before' ) === ReadFlow_Post_Overrides::resolve( $global, $raw )['settings']['position'], 'Position uses existing identifiers' );
}
readflow_check( 'top_bar' === ReadFlow_Post_Overrides::resolve( $global, array_merge( $partial, array( 'display_mode' => '<script>' ) ) )['settings']['progress_display'], 'Invalid display mode inherits global' );
$readflow_test_meta[456] = $disabled;
$readflow_test_meta[789] = $partial;
readflow_check( ReadFlow_Post_Overrides::for_post( 456, $global )['disabled'] && $expected === ReadFlow_Post_Overrides::for_post( 789, $global )['settings'], 'Independent posts resolve independent meta' );
foreach ( array( null, 0, -1, '456', array() ) as $id ) { readflow_check( $global === ReadFlow_Post_Overrides::for_post( $id, $global )['settings'], 'Invalid ID reads global safely' ); }
$frontend = readflow_progress_fixture( array( 'progress_enabled' => true, 'position_memory_enabled' => true ) );
readflow_check( $post->post_content === $frontend->filter_content( $post->post_content ) && ! wp_style_is( 'readflow-display', 'enqueued' ) && ! wp_script_is( 'readflow-progress', 'enqueued' ) && ! wp_script_is( 'readflow-position-memory', 'registered' ), 'Disabled post no output or assets' );
$readflow_test_meta[456] = array_merge( $partial, array( 'reading_time' => 'hide', 'position' => 'after' ) );
$frontend = readflow_progress_fixture( array( 'progress_enabled' => true, 'position_memory_enabled' => true ) );
$html = $frontend->filter_content( $post->post_content );
readflow_check( false !== strpos( $html, 'data-readflow-mode="floating_widget"' ) && false !== strpos( $html, 'readflow-words' ) && false === strpos( $html, 'class="readflow-field readflow-time"' ), 'Frontend consumes effective mode/visibility' );
readflow_check( strpos( $html, 'data-readflow-post=' ) > strpos( $html, $post->post_content ) && wp_script_is( 'readflow-position-memory', 'registered' ), 'Effective after placement preserves global memory' );
foreach ( array( 'is_feed', 'is_preview', 'is_archive' ) as $flag ) {
 $frontend = readflow_progress_fixture( array( 'progress_enabled' => true, 'position_memory_enabled' => true ) ); $wp_query->$flag = true;
 readflow_check( $post->post_content === $frontend->filter_content( $post->post_content ) && ! wp_script_is( 'readflow-progress', 'enqueued' ), 'Override cannot bypass: ' . $flag );
}
$frontend = readflow_progress_fixture( array(), 'page' );
readflow_check( $post->post_content === $frontend->filter_content( $post->post_content ), 'Override cannot bypass disabled content type' );
$readflow_test_meta[456] = array( 'version' => 1, 'behavior' => 'override', 'reading_time' => 'show', 'position' => 'before' );
$frontend = readflow_progress_fixture( array( 'show_time' => false, 'position' => 'manual' ) );
readflow_check( false !== strpos( $frontend->filter_content( $post->post_content ), 'readflow-time' ), 'Eligible override can change hidden/manual presentation' );
$frontend = readflow_progress_fixture( array( 'enabled' => false ) );
readflow_check( $post->post_content === $frontend->filter_content( $post->post_content ), 'Visibility does not enable globally disabled reading time' );

require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
$controls = new ReadFlow_Post_Controls( $settings ); $controls->register_hooks();
$frontend = readflow_progress_fixture( array( 'post_types' => array( 'post', 'page', 'book' ) ) );
foreach ( array( 'post', 'page', 'book' ) as $type ) {
 $test_post = clone $post; $test_post->post_type = $type;
 $controls->add_boxes( $type, $test_post );
 readflow_check( isset( $wp_meta_boxes[ $type ]['normal']['default']['readflow-post-controls'] ), 'Metabox supported: ' . $type );
}
foreach ( array( 'attachment', 'revision', 'nav_menu_item', 'private_note' ) as $type ) {
 $test_post = clone $post; $test_post->post_type = $type; $controls->add_boxes( $type, $test_post );
 readflow_check( ! isset( $wp_meta_boxes[ $type ]['normal']['default']['readflow-post-controls'] ), 'Metabox excluded: ' . $type );
}
$readflow_test_meta[456] = $partial;
ob_start(); $controls->render( $post ); $panel = ob_get_clean();
foreach ( array( 'readflow_post_nonce', 'readflow_post[behavior]', 'readflow_post[display_mode]', 'readflow_post[reading_time]', 'readflow_post[word_count]', 'readflow_post[position]', 'data-readflow-override-fields', 'aria-describedby=', 'value="floating_widget" selected=', 'value="override" checked=' ) as $part ) { readflow_check( false !== strpos( preg_replace( '/\s+/', ' ', $panel ), $part ), 'Panel markup: ' . $part ); }
$readflow_test_meta[456] = array_merge( $partial, array( 'display_mode' => '"><script>alert(1)</script>' ) );
ob_start(); $controls->render( $post ); $panel = ob_get_clean();
readflow_check( false === strpos( $panel, '<script>' ), 'Malformed stored values do not become HTML' );
$readflow_test_meta = array();
ob_start(); $controls->render( $post ); $panel = ob_get_clean();
readflow_check( false !== strpos( preg_replace( '/\s+/', ' ', $panel ), 'value="global" checked=' ), 'No meta defaults to global behavior' );
$readflow_test_admin = false;
ob_start(); $controls->render( $post ); $unauthorized_panel = ob_get_clean();
readflow_check( '' === $unauthorized_panel, 'Unauthorized user receives no metabox controls' );
$readflow_test_admin = true;
$current_screen = WP_Screen::get( 'post' );
$wp_scripts = new WP_Scripts();
$controls->enqueue( 'options-general.php' );
readflow_check( ! wp_script_is( 'readflow-post-controls', 'enqueued' ), 'No unrelated admin scripts' );
$controls->enqueue( 'post.php' );
readflow_check( wp_script_is( 'readflow-post-controls', 'enqueued' ), 'Eligible edit screen gets small disclosure script' );
$wp_scripts = new WP_Scripts(); $current_screen = WP_Screen::get( 'attachment' );
$controls->enqueue( 'post.php' );
readflow_check( ! wp_script_is( 'readflow-post-controls', 'enqueued' ), 'Unsupported edit screen gets no script' );
$current_screen = null;
$old_post = $_POST;
$valid_form = array( 'readflow_post_nonce' => wp_create_nonce( 'readflow_save_post_456' ), 'readflow_post' => $partial );
foreach ( array( array(), array_merge( $valid_form, array( 'readflow_post_nonce' => 'invalid' ) ), array_merge( $valid_form, array( 'readflow_post_nonce' => array() ) ), array_merge( $valid_form, array( 'readflow_post_nonce' => wp_create_nonce( 'readflow_save_post_789' ) ) ), array_merge( $valid_form, array( 'readflow_post' => 'bad' ) ), array_merge( $valid_form, array( 'readflow_post' => array( 'version' => 1, 'behavior' => array() ) ) ), array_merge( $valid_form, array( 'readflow_post' => array( 'version' => 2, 'behavior' => 'disabled' ) ) ) ) as $form ) {
 $_POST = $form; $writes = $readflow_test_meta_writes; $controls->save( 456, $post );
 readflow_check( $writes === $readflow_test_meta_writes, 'Invalid/missing nonce or malformed payload does not write' );
}
$_POST = $valid_form; $readflow_test_admin = false; $writes = $readflow_test_meta_writes; $controls->save( 456, $post );
readflow_check( $writes === $readflow_test_meta_writes, 'Insufficient capability does not write' );
readflow_check( array( 'edit_post', 456 ) === $readflow_last_capability, 'Capability checked for hook-supplied post ID' ); $readflow_test_admin = true;
foreach ( array( 0, -1, '456', array(), 789 ) as $id ) { $writes = $readflow_test_meta_writes; $controls->save( $id, $post ); readflow_check( $writes === $readflow_test_meta_writes, 'Invalid/mismatched hook ID does not write' ); }
$revision = clone $post; $revision->post_type = 'revision'; $revision->post_parent = 20; $revision->post_name = '20-autosave-v1';
$writes = $readflow_test_meta_writes; $controls->save( 456, $revision ); readflow_check( $writes === $readflow_test_meta_writes, 'Revision/autosave not saved' );
$controls->save( 456, $post ); readflow_check( $partial === $readflow_test_meta[456], 'Explicit save persists validated sparse overrides' );
$_POST['readflow_post'] = $disabled; $controls->save( 456, $post ); readflow_check( $disabled === $readflow_test_meta[456], 'Disable save minimal' );
$_POST['readflow_post'] = ReadFlow_Post_Overrides::defaults(); $controls->save( 456, $post ); readflow_check( ! isset( $readflow_test_meta[456] ), 'Global reset deletes redundant meta' );
$_POST['readflow_post'] = array( 'version' => 1, 'behavior' => 'override', 'display_mode' => array(), 'word_count' => 'show', 'position' => '<script>', 'wpm' => 999 );
$controls->save( 456, $post );
readflow_check( array( 'version' => 1, 'behavior' => 'override', 'word_count' => 'show' ) === $readflow_test_meta[456], 'Malformed fields inherit global; unknown fields discarded' );
// Immutable constant tested last; subsequent frontend tests are REST exclusions only.
define( 'DOING_AUTOSAVE', true ); $_POST = $valid_form; $writes = $readflow_test_meta_writes; $controls->save( 456, $post );
readflow_check( $writes === $readflow_test_meta_writes, 'DOING_AUTOSAVE never overwrites settings' );
$_POST = $old_post; $readflow_test_meta = array();
readflow_check( $global === ReadFlow_Settings::defaults() && 12 === count( ReadFlow_Settings::progress_displays() ), 'Global defaults and modes unchanged' );
echo 'PASS: ', $checks - $override_start, ' per-post override assertions.', PHP_EOL;
