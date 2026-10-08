<?php
/** Dashboard coverage using the existing admin harness. */
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
require_once dirname( __DIR__ ) . '/admin/class-readmarker-dashboard.php';
$dashboard_start = $checks;
$dashboard_source=file_get_contents(dirname(__DIR__).'/admin/class-readmarker-dashboard.php');
readmarker_check(false===strpos($dashboard_source,"\xEF\xBF\xBD"),'No replacement characters in dashboard');
readmarker_check(false===strpos($dashboard_source,'Active automatic progress display'),'Overview does not promise active output');
$wp_post_types['book']->labels->name = 'Book';
$readmarker_test_admin = true;
$readmarker_test_option = ReadMarker_Settings::defaults();
$dashboard = new ReadMarker_Dashboard( new ReadMarker_Settings() );
$dashboard->add_page();
$entry = end( $submenu['readmarker-overview'] );
readmarker_check( 'readmarker-overview' === $entry[2] && 'manage_options' === $entry[1], 'Dashboard registration and capability' );
$summary = $dashboard->summary();
foreach ( array( 'ReadMarker Status', 'Reading Time', 'Progress', 'Reading Experience Features', 'Content Rules' ) as $section ) { readmarker_check( isset( $summary[$section] ), 'Dashboard section: ' . $section ); }
foreach ( array( '200 words/min', 'Visible', 'Hidden', 'Disabled', 'Top Bar', '#2563eb', '3 px', 'Before content', 'Simple' ) as $value ) { readmarker_check( false !== strpos( wp_json_encode( $summary, JSON_UNESCAPED_SLASHES ), $value ), 'Dashboard default: ' . $value ); }
ob_start(); $dashboard->render(); $html = ob_get_clean();
foreach ( array( 'Reading Experience', 'page=readmarker', '#readmarker-rules', 'readme.txt', 'no analytics', '<dl>', '<h2>' ) as $part ) { readmarker_check( false !== strpos( $html, $part ), 'Dashboard markup/link: ' . $part ); }
readmarker_check( false === strpos( $html, '<form' ) && false === strpos( $html, '<script' ), 'Read-only without scripts' );
$readmarker_test_option = array_merge( ReadMarker_Settings::defaults(), array( 'enabled'=>false, 'progress_enabled'=>true, 'position_memory_enabled'=>true, 'progress_display'=>'floating_widget', 'post_types'=>array('book'), 'wpm'=>250.5 ) );
$summary = $dashboard->summary();
readmarker_check( 'Disabled' === $summary['ReadMarker Status']['Automatic reading time'] && 'Enabled' === $summary['Progress']['Progress status'], 'Independent feature switches' );
readmarker_check( 'Floating Widget' === $summary['Progress']['Configured progress display'] && 'Enabled' === $summary['Reading Experience Features']['Position Memory'], 'Selected mode and memory' );
readmarker_check( 'Book' === $summary['Content Rules']['Eligible content types'] && '250.5 words/min' === $summary['Reading Time']['Reading speed'], 'CPT labels and fractional WPM' );
$readmarker_test_option = array('post_types'=>array(), 'progress_display'=>'<script>', 'style'=>array(), 'wpm'=>0);
$summary = $dashboard->summary();
readmarker_check( 'None selected' === $summary['Content Rules']['Eligible content types'] && 'Top Bar' === $summary['Progress']['Configured progress display'], 'Empty and invalid settings safe' );
$readmarker_test_option = array('post_types'=>array('book'));
$old_label = $wp_post_types['book']->labels->name;
$wp_post_types['book']->labels->name = '<script>unsafe</script>';
ob_start(); $dashboard->render(); $html=ob_get_clean();
readmarker_check( false === strpos($html,'<script>') && false !== strpos($html,'&lt;script&gt;'), 'Custom labels escaped' );
$wp_post_types['book']->labels->name=$old_label;
$wp_styles = new WP_Styles();
$dashboard->assets('index.php');
readmarker_check( !wp_style_is('readmarker-dashboard','enqueued'), 'No unrelated admin assets' );
$dashboard->assets('toplevel_page_readmarker-overview');
readmarker_check( wp_style_is('readmarker-dashboard','enqueued') && !wp_style_is('readmarker-progress','enqueued'), 'Only dashboard CSS' );
$readmarker_test_admin=false;
ob_start(); $dashboard->render(); $html=ob_get_clean();
readmarker_check( ''===$html && array('manage_options')===$readmarker_last_capability, 'Unauthorized rendering blocked' );
$readmarker_test_admin=true; $readmarker_test_option=ReadMarker_Settings::defaults();
echo 'PASS: ', $checks-$dashboard_start, ' dashboard assertions.', PHP_EOL;


