<?php
/** Run php tests/post-overrides-test.php: pure, WP API adapters, frontend and save security. @package ReadMarker */
if ( 'cli' !== PHP_SAPI ) { exit; }
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$override_start = $checks;
require_once dirname( __DIR__ ) . '/admin/class-readmarker-post-controls.php';
$global = ReadMarker_Settings::defaults();
foreach ( array( null, '', array(), new stdClass(), array( 'version' => 2, 'behavior' => 'disabled' ), array( 'version' => 1, 'behavior' => array() ), array( 'version' => 1, 'behavior' => 'global', 'word_count' => 'show' ) ) as $raw ) {
 $resolved = ReadMarker_Post_Overrides::resolve( $global, $raw );
 readmarker_check( $global === $resolved['settings'] && ! $resolved['disabled'], 'Missing/malformed/global meta preserves settings' );
}
$disabled = array( 'version' => 1, 'behavior' => 'disabled' );
$resolved = ReadMarker_Post_Overrides::resolve( array_merge( $global, array( 'progress_enabled' => true, 'position_memory_enabled' => true ) ), $disabled );
readmarker_check( $resolved['disabled'] && ! $resolved['settings']['enabled'] && ! $resolved['settings']['progress_enabled'] && ! $resolved['settings']['position_memory_enabled'], 'Disable affects every automatic feature' );
readmarker_check( $disabled === ReadMarker_Post_Overrides::for_storage( array_merge( $disabled, array( 'word_count' => 'show', 'display_mode' => 'floating_widget' ) ) ), 'Disable storage minimal' );
readmarker_check( null === ReadMarker_Post_Overrides::for_storage( ReadMarker_Post_Overrides::defaults() ), 'Global storage deleted' );
$partial = array( 'version' => 1, 'behavior' => 'override', 'display_mode' => 'floating_widget', 'word_count' => 'show' );
$expected = array_merge( $global, array( 'progress_display' => 'floating_widget', 'show_word_count' => true ) );
readmarker_check( $expected === ReadMarker_Post_Overrides::resolve( $global, $partial )['settings'], 'Partial override preserves unrelated settings' );
readmarker_check( $partial === ReadMarker_Post_Overrides::for_storage( $partial ), 'Only explicit values stored' );
foreach ( array_keys( ReadMarker_Settings::progress_displays() ) as $mode ) {
 readmarker_check( $mode === ReadMarker_Post_Overrides::resolve( $global, array_merge( $partial, array( 'display_mode' => $mode ) ) )['settings']['progress_display'], 'All existing display modes override: ' . $mode );
}
foreach ( array( 'global', 'show', 'hide', 'bad', array(), null, '<script>' ) as $value ) {
 $raw = array( 'version' => 1, 'behavior' => 'override', 'reading_time' => $value, 'word_count' => $value );
 $resolved = ReadMarker_Post_Overrides::resolve( $global, $raw )['settings'];
 readmarker_check( ( 'hide' !== $value ) === $resolved['show_time'] && ( 'show' === $value ) === $resolved['show_word_count'], 'Visibility allowlist/global fallback' );
}
foreach ( array( 'before', 'after', 'manual', 'global', 'bad', array() ) as $value ) {
 $raw = array( 'version' => 1, 'behavior' => 'override', 'position' => $value );
 readmarker_check( ( in_array( $value, array( 'before', 'after', 'manual' ), true ) ? $value : 'before' ) === ReadMarker_Post_Overrides::resolve( $global, $raw )['settings']['position'], 'Position uses existing identifiers' );
}
readmarker_check( 'top_bar' === ReadMarker_Post_Overrides::resolve( $global, array_merge( $partial, array( 'display_mode' => '<script>' ) ) )['settings']['progress_display'], 'Invalid display mode inherits global' );
$readmarker_test_meta[456] = $disabled;
$readmarker_test_meta[789] = $partial;
readmarker_check( ReadMarker_Post_Overrides::for_post( 456, $global )['disabled'] && $expected === ReadMarker_Post_Overrides::for_post( 789, $global )['settings'], 'Independent posts resolve independent meta' );
foreach ( array( null, 0, -1, '456', array() ) as $id ) { readmarker_check( $global === ReadMarker_Post_Overrides::for_post( $id, $global )['settings'], 'Invalid ID reads global safely' ); }
$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true, 'position_memory_enabled' => true ) );
readmarker_check( $post->post_content === $frontend->filter_content( $post->post_content ) && ! wp_style_is( 'readmarker-display', 'enqueued' ) && ! wp_script_is( 'readmarker-progress', 'enqueued' ) && ! wp_script_is( 'readmarker-position-memory', 'registered' ), 'Disabled post no output or assets' );
$readmarker_test_meta[456] = array_merge( $partial, array( 'reading_time' => 'hide', 'position' => 'after' ) );
$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true, 'position_memory_enabled' => true ) );
$html = $frontend->filter_content( $post->post_content );
readmarker_check( false !== strpos( $html, 'data-readmarker-mode="floating_widget"' ) && false !== strpos( $html, 'readmarker-words' ) && false === strpos( $html, 'class="readmarker-field readmarker-time"' ), 'Frontend consumes effective mode/visibility' );
readmarker_check( strpos( $html, 'data-readmarker-post=' ) > strpos( $html, $post->post_content ) && wp_script_is( 'readmarker-position-memory', 'registered' ), 'Effective after placement preserves global memory' );
foreach ( array( 'is_feed', 'is_preview', 'is_archive' ) as $flag ) {
 $frontend = readmarker_progress_fixture( array( 'progress_enabled' => true, 'position_memory_enabled' => true ) ); $wp_query->$flag = true;
 readmarker_check( $post->post_content === $frontend->filter_content( $post->post_content ) && ! wp_script_is( 'readmarker-progress', 'enqueued' ), 'Override cannot bypass: ' . $flag );
}
$frontend = readmarker_progress_fixture( array(), 'page' );
readmarker_check( $post->post_content === $frontend->filter_content( $post->post_content ), 'Override cannot bypass disabled content type' );
$readmarker_test_meta[456] = array( 'version' => 1, 'behavior' => 'override', 'reading_time' => 'show', 'position' => 'before' );
$frontend = readmarker_progress_fixture( array( 'show_time' => false, 'position' => 'manual' ) );
readmarker_check( false !== strpos( $frontend->filter_content( $post->post_content ), 'readmarker-time' ), 'Eligible override can change hidden/manual presentation' );
$frontend = readmarker_progress_fixture( array( 'enabled' => false ) );
readmarker_check( $post->post_content === $frontend->filter_content( $post->post_content ), 'Visibility does not enable globally disabled reading time' );

require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
$controls = new ReadMarker_Post_Controls( $settings ); $controls->register_hooks();
$frontend = readmarker_progress_fixture( array( 'post_types' => array( 'post', 'page', 'book' ) ) );
foreach ( array( 'post', 'page', 'book' ) as $type ) {
 $test_post = clone $post; $test_post->post_type = $type;
 $controls->add_boxes( $type, $test_post );
 readmarker_check( isset( $wp_meta_boxes[ $type ]['normal']['default']['readmarker-post-controls'] ), 'Metabox supported: ' . $type );
}
foreach ( array( 'attachment', 'revision', 'nav_menu_item', 'private_note' ) as $type ) {
 $test_post = clone $post; $test_post->post_type = $type; $controls->add_boxes( $type, $test_post );
 readmarker_check( ! isset( $wp_meta_boxes[ $type ]['normal']['default']['readmarker-post-controls'] ), 'Metabox excluded: ' . $type );
}
$readmarker_test_meta[456] = $partial;
ob_start(); $controls->render( $post ); $panel = ob_get_clean();
foreach ( array( 'readmarker_post_nonce', 'readmarker_post[behavior]', 'readmarker_post[display_mode]', 'readmarker_post[reading_time]', 'readmarker_post[word_count]', 'readmarker_post[position]', 'data-readmarker-override-fields', 'aria-describedby=', 'value="floating_widget" selected=', 'value="override" checked=' ) as $part ) { readmarker_check( false !== strpos( preg_replace( '/\s+/', ' ', $panel ), $part ), 'Panel markup: ' . $part ); }
$readmarker_test_meta[456] = array_merge( $partial, array( 'display_mode' => '"><script>alert(1)</script>' ) );
ob_start(); $controls->render( $post ); $panel = ob_get_clean();
readmarker_check( false === strpos( $panel, '<script>' ), 'Malformed stored values do not become HTML' );
$readmarker_test_meta = array();
ob_start(); $controls->render( $post ); $panel = ob_get_clean();
readmarker_check( false !== strpos( preg_replace( '/\s+/', ' ', $panel ), 'value="global" checked=' ), 'No meta defaults to global behavior' );
$readmarker_test_admin = false;
ob_start(); $controls->render( $post ); $unauthorized_panel = ob_get_clean();
readmarker_check( '' === $unauthorized_panel, 'Unauthorized user receives no metabox controls' );
$readmarker_test_admin = true;
$current_screen = WP_Screen::get( 'post' );
$wp_scripts = new WP_Scripts();
$controls->enqueue( 'options-general.php' );
readmarker_check( ! wp_script_is( 'readmarker-post-controls', 'enqueued' ), 'No unrelated admin scripts' );
$controls->enqueue( 'post.php' );
readmarker_check( wp_script_is( 'readmarker-post-controls', 'enqueued' ), 'Eligible edit screen gets small disclosure script' );
$wp_scripts = new WP_Scripts(); $current_screen = WP_Screen::get( 'attachment' );
$controls->enqueue( 'post.php' );
readmarker_check( ! wp_script_is( 'readmarker-post-controls', 'enqueued' ), 'Unsupported edit screen gets no script' );
$current_screen = null;
$old_post = $_POST;
$valid_form = array( 'readmarker_post_nonce' => wp_create_nonce( 'readmarker_save_post_456' ), 'readmarker_post' => $partial );
foreach ( array( array(), array_merge( $valid_form, array( 'readmarker_post_nonce' => 'invalid' ) ), array_merge( $valid_form, array( 'readmarker_post_nonce' => array() ) ), array_merge( $valid_form, array( 'readmarker_post_nonce' => wp_create_nonce( 'readmarker_save_post_789' ) ) ), array_merge( $valid_form, array( 'readmarker_post' => 'bad' ) ), array_merge( $valid_form, array( 'readmarker_post' => array( 'version' => 1, 'behavior' => array() ) ) ), array_merge( $valid_form, array( 'readmarker_post' => array( 'version' => 2, 'behavior' => 'disabled' ) ) ) ) as $form ) {
 $_POST = $form; $writes = $readmarker_test_meta_writes; $controls->save( 456, $post );
 readmarker_check( $writes === $readmarker_test_meta_writes, 'Invalid/missing nonce or malformed payload does not write' );
}
$_POST = $valid_form; $readmarker_test_admin = false; $writes = $readmarker_test_meta_writes; $controls->save( 456, $post );
readmarker_check( $writes === $readmarker_test_meta_writes, 'Insufficient capability does not write' );
readmarker_check( array( 'edit_post', 456 ) === $readmarker_last_capability, 'Capability checked for hook-supplied post ID' ); $readmarker_test_admin = true;
foreach ( array( 0, -1, '456', array(), 789 ) as $id ) { $writes = $readmarker_test_meta_writes; $controls->save( $id, $post ); readmarker_check( $writes === $readmarker_test_meta_writes, 'Invalid/mismatched hook ID does not write' ); }
$revision = clone $post; $revision->post_type = 'revision'; $revision->post_parent = 20; $revision->post_name = '20-autosave-v1';
$writes = $readmarker_test_meta_writes; $controls->save( 456, $revision ); readmarker_check( $writes === $readmarker_test_meta_writes, 'Revision/autosave not saved' );
$controls->save( 456, $post ); readmarker_check( $partial === $readmarker_test_meta[456], 'Explicit save persists validated sparse overrides' );
$_POST['readmarker_post'] = $disabled; $controls->save( 456, $post ); readmarker_check( $disabled === $readmarker_test_meta[456], 'Disable save minimal' );
$_POST['readmarker_post'] = ReadMarker_Post_Overrides::defaults(); $controls->save( 456, $post ); readmarker_check( ! isset( $readmarker_test_meta[456] ), 'Global reset deletes redundant meta' );
$_POST['readmarker_post'] = array( 'version' => 1, 'behavior' => 'override', 'display_mode' => array(), 'word_count' => 'show', 'position' => '<script>', 'wpm' => 999 );
$controls->save( 456, $post );
readmarker_check( array( 'version' => 1, 'behavior' => 'override', 'word_count' => 'show' ) === $readmarker_test_meta[456], 'Malformed fields inherit global; unknown fields discarded' );
// Immutable constant tested last; subsequent frontend tests are REST exclusions only.
define( 'DOING_AUTOSAVE', true ); $_POST = $valid_form; $writes = $readmarker_test_meta_writes; $controls->save( 456, $post );
readmarker_check( $writes === $readmarker_test_meta_writes, 'DOING_AUTOSAVE never overwrites settings' );
$_POST = $old_post; $readmarker_test_meta = array();
readmarker_check( $global === ReadMarker_Settings::defaults() && 12 === count( ReadMarker_Settings::progress_displays() ), 'Global defaults and modes unchanged' );
echo 'PASS: ', $checks - $override_start, ' per-post override assertions.', PHP_EOL;
