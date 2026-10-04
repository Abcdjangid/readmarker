<?php
/**
 * Run: php tests/display-test.php
 * Runs the unchanged 402 calculator assertions first, then display coverage.
 * Uses real WordPress Settings API, escaping, query predicates, styles and post
 * APIs. Options, permissions, nonces and translations are in-memory adapters.
 * No database, network, activation or filesystem mutations are performed.
 *
 * @package ReadFlow
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

require __DIR__ . '/calculator-test.php';
$previous_checks = $checks;
remove_all_filters( 'the_content' );
// Newer core versions split UTF-8 helpers into their own files.
foreach ( array( 'compat-utf8.php', 'utf8.php' ) as $optional_file ) {
	if ( file_exists( ABSPATH . WPINC . '/' . $optional_file ) ) {
		require_once ABSPATH . WPINC . '/' . $optional_file;
	}
}
foreach ( array( 'meta.php', 'revision.php', 'compat.php', 'class-wp-list-util.php', 'class-wp-query.php', 'class-wp-walker.php', 'query.php', 'post-template.php', 'general-template.php', 'kses.php', 'script-loader.php' ) as $core_file ) {
	require_once ABSPATH . WPINC . '/' . $core_file;
}
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once dirname( __DIR__ ) . '/admin/class-readflow-admin.php';
define( 'COOKIEHASH', 'readflow-test' );

function _n( $single, $plural, $number, $domain = 'default' ) { return 1 === $number ? $single : $plural; }
function esc_html__( $text, $domain = 'default' ) { return esc_html( $text ); }
function esc_attr__( $text, $domain = 'default' ) { return esc_attr( $text ); }
function current_user_can( $capability ) { $GLOBALS['readflow_last_capability'] = func_get_args(); return $GLOBALS['readflow_test_admin']; }
function wp_create_nonce( $action ) { return 'test-only-nonce:' . $action; }
function wp_verify_nonce( $nonce, $action ) { return wp_create_nonce( $action ) === $nonce ? 1 : false; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function is_rtl() { return false; }
function set_url_scheme( $url, $scheme = null ) { return $url; }

$readflow_test_meta = array();
$readflow_test_meta_writes = 0;
add_filter( 'get_post_metadata', static function ( $value, $id, $key ) {
	if ( ReadFlow_Post_Overrides::META_KEY === $key ) { return isset( $GLOBALS['readflow_test_meta'][ $id ] ) ? array( $GLOBALS['readflow_test_meta'][ $id ] ) : ''; }
	return '';
}, 10, 3 );
add_filter( 'update_post_metadata', static function ( $value, $id, $key, $data ) {
	if ( ReadFlow_Post_Overrides::META_KEY !== $key ) { return false; }
	$GLOBALS['readflow_test_meta'][ $id ] = $data;
	++$GLOBALS['readflow_test_meta_writes'];
	return true;
}, 10, 4 );
add_filter( 'delete_post_metadata', static function ( $value, $id, $key ) {
	if ( ReadFlow_Post_Overrides::META_KEY !== $key ) { return false; }
	unset( $GLOBALS['readflow_test_meta'][ $id ] );
	++$GLOBALS['readflow_test_meta_writes'];
	return true;
}, 10, 3 );
$readflow_test_admin = true;
$readflow_test_option = ReadFlow_Settings::defaults();
add_filter( 'pre_option_readflow_settings', static function () { return $GLOBALS['readflow_test_option']; } );
// Lazy migrations use the same in-memory option storage as explicit admin saves.
add_filter( 'pre_update_option_readflow_settings', static function ( $new, $old ) {
	if ( $new !== $old ) { $GLOBALS['readflow_test_option'] = $new; }
	return $old;
}, 99, 2 );
add_filter( 'pre_option_blog_charset', static function () { return 'UTF-8'; } );
add_filter( 'pre_option_html_type', static function () { return 'text/html'; } );
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=readflow';
$wp_post_types = array();
foreach ( array( 'post' => true, 'page' => true, 'book' => true, 'private_note' => false ) as $name => $public ) {
	$wp_post_types[ $name ] = (object) array( 'name' => $name, 'public' => $public, 'labels' => (object) array( 'name' => ucfirst( $name ) ) );
}

$settings = new ReadFlow_Settings();
$defaults = ReadFlow_Settings::defaults();
readflow_check( $defaults === $settings->normalize( array() ), 'Central defaults and missing-key upgrades' );
readflow_check( true === $defaults['enabled'] && 200.0 === $defaults['wpm'] && array( 'post' ) === $defaults['post_types'], 'General defaults' );
readflow_check( 'simple' === $defaults['style'] && 'before' === $defaults['position'] && true === $defaults['show_time'] && false === $defaults['show_word_count'], 'Display defaults' );
foreach ( array( 0, -1, 10001, INF, NAN, 'oops', array(), new stdClass() ) as $wpm ) {
	readflow_check( 200.0 === $settings->normalize( array( 'wpm' => $wpm ) )['wpm'], 'Settings invalid WPM default' );
}
foreach ( array( 1, 10000, '250.5' ) as $wpm ) {
	readflow_check( (float) $wpm === $settings->normalize( array( 'wpm' => $wpm ) )['wpm'], 'Settings WPM matches calculator' );
}
foreach ( array( 'unknown', array(), new stdClass(), '<script>' ) as $value ) {
	$result = $settings->normalize( array( 'style' => $value, 'position' => $value ) );
	readflow_check( 'simple' === $result['style'] && 'before' === $result['position'], 'Invalid enum defaults' );
}
$result = $settings->normalize( array( 'post_types' => array( 'post', 'book', 'private_note', 'missing', array(), 1, 'book', '<script>' ), 'unknown' => 'discard' ) );
readflow_check( array( 'post', 'book' ) === $result['post_types'] && ! isset( $result['unknown'] ), 'Public types allowlisted and deduplicated' );
readflow_check( array() === $settings->normalize( array( 'post_types' => 'post' ) )['post_types'], 'Invalid post type structure' );
readflow_check( array() === $settings->normalize( array( 'post_types' => array( '' ) ) )['post_types'], 'All types can be unchecked' );
foreach ( array( '1', 1, true, 'true', 'on', 'yes' ) as $value ) {
	readflow_check( true === $settings->normalize( array( 'enabled' => $value ) )['enabled'], 'Truthy form boolean' );
}
foreach ( array( '0', 0, false, 'false', 'off', 'no', 'invalid', null, array() ) as $value ) {
	$result = $settings->normalize( array( 'enabled' => $value, 'show_time' => $value, 'show_word_count' => $value ) );
	readflow_check( ! $result['enabled'] && ! $result['show_time'] && ! $result['show_word_count'], 'False/malformed booleans' );
}
readflow_check( $defaults === $settings->normalize( new stdClass() ), 'Malformed option default' );
$settings->register();
readflow_check( isset( $wp_registered_settings['readflow_settings'] ), 'Settings API registration' );
readflow_check( array( $settings, 'normalize' ) === $wp_registered_settings['readflow_settings']['sanitize_callback'], 'Settings API sanitizer registered' );
readflow_check( false === $wp_registered_settings['readflow_settings']['show_in_rest'], 'Settings not exposed through REST' );
readflow_check( 200.0 === sanitize_option( 'readflow_settings', array( 'wpm' => 0 ) )['wpm'], 'Actual WordPress sanitation pipeline' );

$renderer = new ReadFlow_Renderer();
$data = $calculator->calculate_from_content( str_repeat( 'word ', 1240 ) );
$data['post_id'] = 123;
foreach ( array( 'simple', 'badge', 'meta', 'card' ) as $style ) {
	$options = array_merge( $defaults, array( 'style' => $style, 'show_word_count' => true ) );
	$html = $renderer->render( $data, $options );
	readflow_check( false !== strpos( $html, 'readflow-display--' . $style ), $style . ': CSS class' );
	readflow_check( false !== strpos( $html, '7 min read' ) && false !== strpos( $html, '1,240 words' ), $style . ': metadata' );
	readflow_check( false !== strpos( $html, 'aria-hidden="true" focusable="false"' ), $style . ': decorative icon' );
	$options['show_word_count'] = false;
	readflow_check( false === strpos( $renderer->render( $data, $options ), 'readflow-words' ), $style . ': word count toggle' );
	$options['show_word_count'] = true;
	$options['show_time'] = false;
	$html = $renderer->render( $data, $options );
	readflow_check( false === strpos( $html, 'readflow-time' ) && false === strpos( $html, '<svg' ) && false !== strpos( $html, '1,240 words' ), $style . ': word-only output' );
	$options['show_word_count'] = false;
	readflow_check( '' === $renderer->render( $data, $options ), $style . ': both fields hidden' );
}
$unsafe = $data;
$unsafe['formatted_time'] = '<img src=x onerror="alert(1)">';
$unsafe['post_id'] = '"><script>alert(1)</script>';
$html = $renderer->render( $unsafe, array_merge( $defaults, array( 'style' => '"><script>' ) ) );
readflow_check( false === strpos( $html, '<img' ) && false === strpos( $html, '<script' ) && false !== strpos( $html, '&lt;img' ), 'Renderer escapes supplied text' );
readflow_check( false !== strpos( $html, 'readflow-display--simple' ) && false !== strpos( $html, 'data-readflow-post="0"' ), 'Renderer safe attribute values' );
foreach ( array( array(), new WP_Error( 'test' ), null, array( 'word_count' => '<script>' ), array( 'word_count' => -1 ) ) as $invalid ) {
	readflow_check( '' === $renderer->render( $invalid, $defaults ), 'Invalid calculation safe' );
}
readflow_check( '' === $renderer->render( $calculator->calculate_from_content( '' ), $defaults ), 'Empty result hidden' );
readflow_check( false !== strpos( $renderer->render( $calculator->calculate_from_content( 'One' ), array_merge( $defaults, array( 'show_word_count' => true ) ) ), '1 word<' ), 'Singular word translation' );

$admin = new ReadFlow_Admin( $settings );
$admin->register_hooks();
$action_hook = 'plugin_action_links_' . plugin_basename( READFLOW_PLUGIN_FILE );
readflow_check( false !== has_filter( $action_hook, array( $admin, 'plugin_action_links' ) ), 'Native plugin action hook registered' );
$native_links = array( 'deactivate' => '<a href="native-deactivate">Deactivate</a>', 'edit' => '<a href="native-edit">Edit</a>' );
$scripts_before = wp_scripts()->queue; $styles_before = wp_styles()->queue;
$action_links = apply_filters( $action_hook, $native_links );
readflow_check( '<a href="' . esc_url( admin_url( 'admin.php?page=readflow' ) ) . '">Settings</a>' === end( $action_links ), 'Escaped native admin URL and exact Settings label appended' );
readflow_check( array_slice( $action_links, 0, count( $native_links ), true ) === $native_links, 'Native action links retain values keys and original order' );
$deactivate_only = array( 'deactivate' => $native_links['deactivate'] );
$ordered_links = apply_filters( $action_hook, $deactivate_only );
readflow_check( array_values( $ordered_links ) === array( $native_links['deactivate'], end( $action_links ) ), 'Deactivate first and Settings second' );
readflow_check( array( 'manage_options' ) === $GLOBALS['readflow_last_capability'], 'Link uses settings-page capability' );
readflow_check( wp_scripts()->queue === $scripts_before && wp_styles()->queue === $styles_before, 'Action link enqueues no assets' );
$readflow_test_admin = false;
readflow_check( apply_filters( $action_hook, $native_links ) === $native_links, 'Unauthorized users retain only native links' );
$readflow_test_admin = true;
readflow_check( count( apply_filters( $action_hook, array() ) ) === 1, 'Empty native actions safely accept navigation link' );

add_filter( 'sanitize_title', 'sanitize_title_with_dashes', 10, 3 );
$admin_page_hooks = array( 'options-general.php' => 'settings' );
$menu = array();
$submenu = array();
do_action( 'admin_init' );
require_once dirname(__DIR__).'/admin/class-readflow-dashboard.php';
require_once dirname(__DIR__).'/admin/class-readflow-shortcodes-admin.php';
$menu_dashboard = new ReadFlow_Dashboard($settings); $menu_dashboard->register_hooks();
$menu_shortcodes = new ReadFlow_Shortcodes_Admin(); $menu_shortcodes->register_hooks();
do_action( 'admin_menu' );
readflow_check( !isset( $submenu['options-general.php'] ), 'No ReadFlow pages under WordPress Settings' );
$readflow_menu = $submenu['readflow-overview'][1];
readflow_check(array_column($submenu['readflow-overview'],0)===array('Overview','Settings','Shortcodes'), 'Exactly three ordered submenus without duplicate ReadFlow');
readflow_check(array_column($submenu['readflow-overview'],2)===array('readflow-overview','readflow','readflow-shortcodes'), 'Original page slugs preserved');
$top = array_values(array_filter($menu,static function($item){return 'readflow-overview'===$item[2];}));
readflow_check(count($top)===1 && 'manage_options'===$top[0][1], 'One capability-protected top-level menu');
readflow_check( 'manage_options' === $readflow_menu[1] && 'readflow' === $readflow_menu[2], 'Admin menu capability' );
readflow_check( 2 === count( $wp_settings_fields['readflow']['readflow_general'] ) && 1 === count( $wp_settings_fields['readflow']['readflow_rules'] ) && 4 === count( $wp_settings_fields['readflow']['readflow_display'] ), 'General, rules and reading-time fields grouped' );
wp_dequeue_script( 'readflow-settings-placement' );
$admin->assets( 'dashboard' );
readflow_check( ! wp_script_is( 'readflow-settings-placement', 'enqueued' ), 'Settings protection absent on unrelated admin pages' );
$readflow_test_admin = false;
$admin->assets( 'readflow_page_readflow' );
readflow_check( ! wp_script_is( 'readflow-settings-placement', 'enqueued' ), 'Settings script requires capability' );
$readflow_test_admin = true;
$admin->assets( 'readflow_page_readflow' );
readflow_check( wp_script_is( 'readflow-settings-placement', 'enqueued' ), 'Existing settings-only script provides unsaved protection' );
wp_dequeue_script( 'readflow-settings-placement' );

readflow_check(false!==has_action('load-readflow_page_readflow'), 'Preset and reset notices follow relocated settings load hook');
foreach(array('toplevel_page_readflow-overview','readflow_page_readflow','readflow_page_readflow-shortcodes') as $screen){
 foreach(array('readflow-dashboard','readflow-presets','readflow-shortcodes') as $handle)wp_dequeue_style($handle);
 foreach(array('readflow-settings-placement','readflow-shortcodes') as $handle)wp_dequeue_script($handle);
 $menu_dashboard->assets($screen);$admin->assets($screen);$menu_shortcodes->assets($screen);
 readflow_check(wp_style_is('readflow-dashboard','enqueued')===('toplevel_page_readflow-overview'===$screen),'Dashboard assets isolated on '.$screen);
 readflow_check(wp_script_is('readflow-settings-placement','enqueued')===('readflow_page_readflow'===$screen),'Settings assets isolated on '.$screen);
 readflow_check(wp_script_is('readflow-shortcodes','enqueued')===('readflow_page_readflow-shortcodes'===$screen),'Shortcode assets isolated on '.$screen);
}
// The isolated suite omits WordPress's default admin asset registration.
wp_register_style('wp-color-picker', admin_url('css/color-picker.css'));
wp_register_script('wp-color-picker', admin_url('js/color-picker.js'));
wp_dequeue_style('wp-color-picker');wp_dequeue_script('readflow-settings-placement');
foreach(array('plugins.php','post.php','dashboard') as $screen){$admin->assets($screen);readflow_check(!wp_style_is('wp-color-picker','enqueued')&&!wp_script_is('readflow-settings-placement','enqueued'),'Color picker absent on '.$screen);}
$admin->assets('readflow_page_readflow');
readflow_check(wp_style_is('wp-color-picker','enqueued') && in_array('wp-color-picker',wp_scripts()->registered['readflow-settings-placement']->deps,true),'Native picker stylesheet and script dependency on settings only');
$color_before=$readflow_test_option;$readflow_test_option=array_merge(ReadFlow_Settings::defaults(),array('progress_color'=>'#ff0000'));
ob_start();$admin->render_field(array('key'=>'progress_color'));$color_html=ob_get_clean();
readflow_check(false!==strpos($color_html,'id="readflow-progress_color"')&&false!==strpos($color_html,'name="readflow_settings[progress_color]"')&&false!==strpos($color_html,'value="#ff0000"')&&false!==strpos($color_html,'type="text"'),'Existing editable HEX field and saved value unchanged');
readflow_check('#2563eb'===ReadFlow_Settings::defaults()['progress_color'] && 1===ReadFlow_Settings::SCHEMA_VERSION && '#2563eb'===$settings->normalize(array('progress_color'=>'bad'))['progress_color'],'Color validation default and schema unchanged');
$readflow_test_option=$color_before;
// Exercise the real options-header renderer followed by the plugin page callback.
$notice_get = $_GET;
unset($_GET['updated'], $_GET['settings-updated']);
foreach (array('saved','unchanged','error','reset') as $flow) {
 $wp_settings_errors = array();
 if ('reset' === $flow) {
  $_GET['readflow_reset_done'] = wp_create_nonce('readflow_reset_done');
  (new ReadFlow_Reset($settings))->notice();
  $message = 'ReadFlow global settings restored to defaults.';
 } else {
  $message = 'error' === $flow ? 'Validation error retained.' : 'Settings saved.';
  add_settings_error('general','settings_updated',$message,'error' === $flow ? 'error' : 'success');
 }
 readflow_check(1 === count(get_settings_errors()), 'One generated notice: '.$flow);
 ob_start();$admin->render_page();$notice_html=ob_get_clean();
 readflow_check(1 === substr_count($notice_html,$message), 'Header and settings page render notice exactly once: '.$flow);
 readflow_check(false !== strpos($notice_html,'notice-'.('error' === $flow ? 'error' : 'success')), 'Original notice type retained: '.$flow);
}
$_GET=$notice_get;
$wp_settings_errors = array();
ob_start();
$admin->render_page();
$html = ob_get_clean();
readflow_check( 1 === substr_count($html, 'class="readflow-selector-row"'), 'Selector alone has scoped disclosure row styling' );
readflow_check( false !== strpos( $html, 'action="options.php"' ) && false !== strpos( $html, 'name="_wpnonce"' ), 'Settings form uses WordPress options endpoint and nonce' );
readflow_check( false !== strpos( $html, 'value="book"' ) && false === strpos( $html, 'value="private_note"' ), 'Dynamic public type controls' );
readflow_check( false !== strpos( $html, 'value="manual"' ) && false !== strpos( $html, 'value="card"' ), 'All display choices available' );
// Organization changes only section membership, never the field or form contract.
foreach ( array('general','rules','reading-time','progress','reading-position','reader-controls','advanced') as $section ) {
 readflow_check( 1 === substr_count($html, 'id="readflow-section-'.$section.'"'), 'Unique section target: '.$section );
 readflow_check( 1 === substr_count($html, '<a href="#readflow-section-'.$section.'">'), 'Native navigation anchor: '.$section );
}
$registered_keys = array();
foreach ( $wp_settings_fields['readflow'] as $fields ) {
 foreach ( $fields as $field_id => $field ) {
  $key=$field['args']['key']; $registered_keys[]=$key;
  readflow_check( 'readflow-'.$key === $field_id, 'Original field ID: '.$key );
  readflow_check( false !== strpos($html, 'name="readflow_settings['.$key.']'.('post_types'===$key?'[]':'').'"'), 'Original submitted field name: '.$key );
  if ('post_types'!==$key) { readflow_check( 1===substr_count($html,'id="readflow-'.$key.'"'), 'Unique original control ID: '.$key ); }
 }
}
readflow_check(17===count($registered_keys) && count($registered_keys)===count(array_unique($registered_keys)), 'All 17 editable settings rendered once');
readflow_check(false===strpos($html,'name="readflow_settings[schema_version]"'), 'Internal schema not exposed');
readflow_check(1===ReadFlow_Settings::SCHEMA_VERSION && $defaults===ReadFlow_Settings::defaults(), 'Schema and canonical defaults unchanged by rendering');
readflow_check(false!==strpos($html,'id="readflow-rules"'), 'Existing Display Rules quick link preserved');
preg_match('/<form action="options.php" method="post">(.*?)<\/form>/s',$html,$settings_form);
readflow_check(isset($settings_form[1]) && false===strpos($settings_form[1],'readflow_reset_confirm') && false!==strpos($html,'Reset to Defaults'), 'Reset remains outside normal settings form');
readflow_check(false!==strpos($settings_form[1],'readflow_settings_group') && false!==strpos($settings_form[1],'name="_wpnonce"'), 'Settings API group and nonce preserved');

$wp_post_types['book']->labels->name = '<script>alert(1)</script>';
ob_start();
$admin->render_field( array( 'key' => 'post_types' ) );
$html = ob_get_clean();
readflow_check( false === strpos( $html, '<script>' ) && false !== strpos( $html, '&lt;script&gt;' ), 'Post type labels escaped' );
$readflow_test_admin = false;
ob_start();
$admin->render_page();
readflow_check( '' === ob_get_clean(), 'Unauthorized settings page is empty' );
$readflow_test_admin = true;

function readflow_frontend_fixture( $overrides = array(), $type = 'post', $content = null ) {
	global $readflow_test_option, $wp_query, $wp_the_query, $post, $wp_styles, $current_screen;
	$readflow_test_option = array_merge( ReadFlow_Settings::defaults(), $overrides );
	$content = null === $content ? '<p>' . str_repeat( 'word ', 200 ) . '</p>' : $content;
	$post = new WP_Post( (object) array( 'ID' => 456, 'post_type' => $type, 'post_content' => $content, 'post_password' => '', 'filter' => 'raw' ) );
	wp_cache_set( 456, $post, 'posts' );
	$wp_query = new WP_Query();
	$wp_query->is_singular = true;
	$wp_query->in_the_loop = true;
	$wp_query->queried_object = $post;
	$wp_query->queried_object_id = 456;
	$wp_the_query = $wp_query;
	$wp_styles = new WP_Styles();
	$current_screen = null;
	return new ReadFlow_Frontend( new ReadFlow_Settings(), new ReadFlow_Calculator(), new ReadFlow_Renderer() );
}

$frontend = readflow_frontend_fixture();
readflow_check( ! wp_style_is( 'readflow-display', 'enqueued' ), 'No global frontend CSS' );
$content = $post->post_content;
$html = $frontend->filter_content( $content );
readflow_check( 0 === strpos( $html, '<div class="readflow-display' ) && substr( $html, -strlen( $content ) ) === $content, 'Default before placement' );
readflow_check( wp_style_is( 'readflow-display', 'enqueued' ), 'CSS queued only after output' );
readflow_check( false !== strpos( $html, '1 min read' ), 'Frontend consumes shared calculation' );
readflow_check( $html === $frontend->filter_content( $html ), 'Repeated filtered content remains unchanged' );
readflow_check( $content === $frontend->filter_content( $content ), 'Repeated raw content not decorated twice' );
$other_instance = new ReadFlow_Frontend( $settings, $calculator, $renderer );
readflow_check( $html === $other_instance->filter_content( $html ), 'HTML marker protects across integration instances' );
readflow_check( $content === $other_instance->filter_content( $content ), 'Recognized marker consumes automatic insertion' );

foreach ( array( array( 'enabled' => false ), array( 'position' => 'manual' ), array( 'post_types' => array() ), array( 'show_time' => false, 'show_word_count' => false ) ) as $options ) {
	$frontend = readflow_frontend_fixture( $options );
	readflow_check( $content === $frontend->filter_content( $content ), 'Settings disable automatic output' );
	readflow_check( ! wp_style_is( 'readflow-display', 'enqueued' ), 'No CSS for disabled output' );
}
$frontend = readflow_frontend_fixture( array( 'position' => 'after', 'wpm' => 100 ) );
$html = $frontend->filter_content( $content );
readflow_check( 0 === strpos( $html, $content ) && false !== strpos( $html, '2 min read' ), 'After placement and custom WPM' );
$frontend = readflow_frontend_fixture( array(), 'page' );
readflow_check( $content === $frontend->filter_content( $content ), 'Page disabled by default' );
$frontend = readflow_frontend_fixture( array(), 'book' );
readflow_check( $content === $frontend->filter_content( $content ), 'CPT disabled by default' );
$frontend = readflow_frontend_fixture( array( 'post_types' => array( 'book' ) ), 'book' );
readflow_check( false !== strpos( $frontend->filter_content( $content ), 'readflow-display' ), 'Opted-in public CPT works' );
foreach ( array( '', '   ', '<p></p>', '<img src="a.jpg">', '<script>not readable</script>' ) as $empty ) {
	$frontend = readflow_frontend_fixture( array(), 'post', $empty );
	readflow_check( $empty === $frontend->filter_content( $empty ), 'Empty/non-readable content unchanged' );
	readflow_check( ! wp_style_is( 'readflow-display', 'enqueued' ), 'Empty content has no CSS' );
}
foreach ( array( 'is_feed', 'is_preview' ) as $flag ) {
	$frontend = readflow_frontend_fixture();
	$wp_query->$flag = true;
	readflow_check( $content === $frontend->filter_content( $content ), 'Excluded query: ' . $flag );
}
foreach ( array( 'is_singular', 'in_the_loop' ) as $flag ) {
	$frontend = readflow_frontend_fixture();
	$wp_query->$flag = false;
	readflow_check( $content === $frontend->filter_content( $content ), 'Required query: ' . $flag );
}
$frontend = readflow_frontend_fixture();
$wp_the_query = new WP_Query();
readflow_check( $content === $frontend->filter_content( $content ), 'Secondary query excluded' );
$frontend = readflow_frontend_fixture();
$wp_query->queried_object_id = 999;
readflow_check( $content === $frontend->filter_content( $content ), 'Other post excluded' );
$frontend = readflow_frontend_fixture();
$post->post_password = 'protected';
readflow_check( $content === $frontend->filter_content( $content ), 'Password-protected post excluded' );
$frontend = readflow_frontend_fixture();
$current_screen = new class() { public function in_admin() { return true; } };
readflow_check( $content === $frontend->filter_content( $content ), 'Admin context excluded' );
$frontend = readflow_frontend_fixture();
$_SERVER['HTTP_ACCEPT'] = 'application/json';
readflow_check( $content === $frontend->filter_content( $content ), 'JSON/editor response excluded' );
unset( $_SERVER['HTTP_ACCEPT'] );
$frontend = readflow_frontend_fixture();
add_filter( 'wp_doing_ajax', '__return_true' );
readflow_check( $content === $frontend->filter_content( $content ), 'AJAX excluded' );
remove_filter( 'wp_doing_ajax', '__return_true' );

$frontend = readflow_frontend_fixture();
$frontend->register_hooks();
$excerpt_callback = static function ( $text ) { return apply_filters( 'the_content', $text ); };
add_filter( 'get_the_excerpt', $excerpt_callback );
readflow_check( $content === apply_filters( 'get_the_excerpt', $content ), 'Excerpt content filter excluded' );
remove_filter( 'get_the_excerpt', $excerpt_callback );
readflow_check( false !== strpos( apply_filters( 'the_content', $content ), 'readflow-display' ), 'Excerpt call does not consume insertion' );
remove_all_filters( 'the_content' );

$frontend = readflow_frontend_fixture();
$recursion_result = null;
$reentrant = static function ( $value ) use ( $frontend, $content, &$recursion_result ) {
	$recursion_result = $frontend->filter_content( $content );
	return $value;
};
add_filter( 'pre_option_readflow_settings', $reentrant, 20 );
$html = $frontend->filter_content( $content );
remove_filter( 'pre_option_readflow_settings', $reentrant, 20 );
readflow_check( $content === $recursion_result && 1 === substr_count( $html, 'data-readflow-post=' ), 'Reentrant option filter cannot recurse' );

$frontend = readflow_frontend_fixture();
$password_recursion = static function ( $required ) use ( $frontend, $content, &$recursion_result ) {
	$recursion_result = $frontend->filter_content( $content );
	return $required;
};
add_filter( 'post_password_required', $password_recursion );
$html = $frontend->filter_content( $content );
remove_filter( 'post_password_required', $password_recursion );
readflow_check( $content === $recursion_result && 1 === substr_count( $html, 'data-readflow-post=' ), 'Eligibility callbacks cannot recurse' );

$frontend = readflow_frontend_fixture();
do_action( 'wp_head' );
$html = $frontend->filter_content( $content );
readflow_check( false !== strpos( $html, 'readflow-display-css' ) && wp_style_is( 'readflow-display', 'done' ), 'Classic-theme late stylesheet printed' );
readflow_check( '' === ReadFlow_Assets::for_output(), 'Stylesheet printed only once' );

require __DIR__ . '/progress-test.php';
require __DIR__ . '/display-rules-test.php';
require __DIR__ . '/shortcodes-test.php';
require __DIR__ . '/block-test.php';
require __DIR__ . '/dashboard-test.php';
require __DIR__ . '/shortcodes-admin-test.php';
require __DIR__ . '/placement-test.php';
require __DIR__ . '/reset-test.php';
require __DIR__ . '/presets-test.php';
require __DIR__ . '/settings-schema-test.php';
require __DIR__ . '/reader-controls-test.php';
require __DIR__ . '/inline-mode-test.php';
require __DIR__ . '/post-overrides-test.php';

// REST constant is immutable, so test it last.
define( 'REST_REQUEST', true );
$frontend = readflow_frontend_fixture();
readflow_check( $content === $frontend->filter_content( $content ) && ! wp_style_is( 'readflow-display', 'enqueued' ), 'REST request has no output/assets' );

$frontend = readflow_frontend_fixture( array( 'progress_enabled' => true ) );
readflow_check( $content === $frontend->filter_content( $content ) && ! wp_style_is( 'readflow-progress', 'enqueued' ), 'REST excludes progress too' );

echo 'PASS: ', $checks - $previous_checks, ' settings/display assertions; ', $checks, ' total including calculator regressions.', PHP_EOL;






