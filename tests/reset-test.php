<?php
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }

// Reuse the suite's nonce/capability and option doubles.
if ( ! function_exists( 'check_admin_referer' ) ) {
function check_admin_referer( $action, $name ) {
	$GLOBALS['readmarker_reset_nonce_check'] = array( $action, $name );
	if ( ! wp_verify_nonce( $_REQUEST[ $name ] ?? '', $action ) ) { throw new RuntimeException( 'Invalid reset nonce' ); }
}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
function wp_safe_redirect( $url, $status = 302, $by = 'WordPress' ) {
	$GLOBALS['readmarker_reset_redirect'] = $url;
	throw new RuntimeException( 'Reset redirect' );
}

}
$start = $checks;
$reset = new ReadMarker_Reset( new ReadMarker_Settings() );
$saved_post = $_POST; $saved_get = $_GET; $saved_request = $_REQUEST; $saved_server = $_SERVER;
$meta = $readmarker_test_meta;
$writes = array();
$intercept = static function ( $new, $old, $option ) use ( &$writes ) {
	$writes[] = $option;
	$GLOBALS['readmarker_test_option'] = $new;
	return $old;
};
add_filter( 'pre_update_option_readflow_settings', $intercept, 10, 3 );
$valid = array( 'action' => 'readmarker_reset_settings', 'readmarker_reset_confirm' => '1', 'readmarker_reset_nonce' => wp_create_nonce( 'readmarker_reset_settings' ) );
readmarker_check( false !== has_action( 'admin_post_readmarker_reset_settings' ), 'Authenticated reset action registered' );
readmarker_check( false === has_action( 'admin_post_nopriv_readmarker_reset_settings' ), 'No unauthenticated reset action' );
foreach ( array( 'GET', '', 'PUT', 'DELETE' ) as $method ) {
	$_SERVER['REQUEST_METHOD'] = $method; $_POST = $_REQUEST = $valid;
	readmarker_check( ! $reset->reset() && ! $writes, 'Only POST resets: ' . $method );
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = $_REQUEST = array_diff_key( $valid, array( 'readmarker_reset_nonce' => true ) );
readmarker_check( ! $reset->reset() && ! $writes, 'Missing nonce rejected with otherwise valid request' );
foreach ( array( array(), array_merge( $valid, array( 'action' => 'other' ) ), array_merge( $valid, array( 'action' => array() ) ), array_merge( $valid, array( 'readmarker_reset_confirm' => '0' ) ), array_merge( $valid, array( 'readmarker_reset_nonce' => array() ) ) ) as $invalid ) {
	$_POST = $_REQUEST = $invalid;
	readmarker_check( ! $reset->reset() && ! $writes, 'Malformed/unconfirmed reset rejected' );
}
$_POST = $_REQUEST = array_merge( $valid, array( 'readmarker_reset_nonce' => 'invalid' ) );
$rejected = false;
try { $reset->reset(); } catch ( RuntimeException $e ) { $rejected = true; }
readmarker_check( $rejected && ! $writes, 'Invalid dedicated nonce rejected' );
$_POST = $_REQUEST = $valid; $readmarker_test_admin = false;
readmarker_check( ! $reset->reset() && ! $writes, 'Unauthorized user rejected' );
$readmarker_test_admin = true;
$readmarker_test_option = array_fill_keys( array_keys( ReadMarker_Settings::defaults() ), 'invalid' );
$service = new ReadMarker_Settings();
readmarker_check( $reset->reset(), 'Modified global settings reset successfully' );
$expected = $service->normalize( ReadMarker_Settings::defaults() );
readmarker_check( $readmarker_test_option === $expected, 'Exact normalized canonical defaults persisted' );
foreach ( $expected as $key => $value ) { readmarker_check( $readmarker_test_option[ $key ] === $value, 'Canonical reset field: ' . $key ); }
readmarker_check( $service->get() === $expected, 'Normal settings service reads reset values' );
readmarker_check( $meta === $readmarker_test_meta, 'Per-post metadata untouched' );
readmarker_check( array( ReadMarker_Settings::OPTION ) === $writes, 'Only global option written' );
readmarker_check( 12 === count( ReadMarker_Settings::progress_displays() ) && isset( ReadMarker_Settings::progress_displays()['inline_progress'] ) && 'top_bar' === $expected['progress_display'], 'All twelve modes and default preserved' );
readmarker_check( array( 'readmarker_reset_settings', 'readmarker_reset_nonce' ) === $GLOBALS['readmarker_reset_nonce_check'], 'Dedicated check_admin_referer used' );
try { $reset->handle(); } catch ( RuntimeException $e ) { readmarker_check( 'Reset redirect' === $e->getMessage(), 'Successful handler redirects even when already default' ); }
$url = $GLOBALS['readmarker_reset_redirect'];
readmarker_check( 0 === strpos( $url, admin_url( 'admin.php?page=readmarker' ) ), 'Safe fixed settings redirect URL' );
parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
$_GET = $query; $reset->notice();
$notices = get_settings_errors( 'readmarker_reset' );
readmarker_check( 1 === count( $notices ) && 'success' === $notices[0]['type'], 'Standard success notice after redirect' );
$_GET = array( 'readmarker_reset_done' => 'invalid' ); $reset->notice();
readmarker_check( 1 === count( get_settings_errors( 'readmarker_reset' ) ), 'Invalid result token does not add notice or reset' );
ob_start(); $reset->render(); $html = ob_get_clean();
readmarker_check( false !== strpos( $html, 'method="post"' ) && false !== strpos( $html, 'admin-post.php' ), 'Separate POST form' );
readmarker_check( false !== strpos( $html, 'name="readmarker_reset_confirm" value="1" required' ) && false !== strpos( $html, 'name="readmarker_reset_nonce"' ), 'Accessible server-required confirmation and nonce' );
readmarker_check( false === strpos( $html, '<script' ) && false === strpos( $html, 'localStorage' ), 'No browser storage or JavaScript reset behavior' );
$readmarker_test_admin = false; ob_start(); $reset->render(); $html = ob_get_clean();
readmarker_check( '' === $html, 'Unauthorized reset UI hidden' );
$readmarker_test_admin = true;
remove_filter( 'pre_update_option_readflow_settings', $intercept, 10 );
$_POST = $saved_post; $_GET = $saved_get; $_REQUEST = $saved_request; $_SERVER = $saved_server;
echo 'PASS: ', $checks - $start, ' reset PHP assertions.', PHP_EOL;


// Legacy bookmarks navigate only; reuse the redirect adapter without writing settings.
$legacy_get=$_GET; $legacy_server=$_SERVER; $legacy_page=$pagenow ?? null;
$pagenow='options-general.php'; $_SERVER['REQUEST_METHOD']='GET';
foreach(array('readmarker','readmarker-overview','readmarker-shortcodes') as $slug){
 $_GET=array('page'=>$slug); $readmarker_reset_redirect=null;
 try{$admin->redirect_legacy_page();}catch(RuntimeException $e){}
 readmarker_check($readmarker_reset_redirect===admin_url('admin.php?page='.$slug),'Legacy bookmark redirects: '.$slug);
}
$_GET=array('page'=>'unrelated');$readmarker_reset_redirect=null;$admin->redirect_legacy_page();
readmarker_check(null===$readmarker_reset_redirect,'Other WordPress Settings pages untouched');
$_GET=array('page'=>'readmarker');$readmarker_test_admin=false;$admin->redirect_legacy_page();
readmarker_check(null===$readmarker_reset_redirect,'Unauthorized bookmark does not redirect');$readmarker_test_admin=true;
$_SERVER['REQUEST_METHOD']='POST';$admin->redirect_legacy_page();
readmarker_check(null===$readmarker_reset_redirect,'Legacy redirect does not intercept POST');
$_GET=$legacy_get;$_SERVER=$legacy_server;$pagenow=$legacy_page;
