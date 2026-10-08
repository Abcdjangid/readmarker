<?php
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$start = $checks;
$service = new ReadMarker_Settings();
readmarker_check( 'readflow_settings' === ReadMarker_Settings::OPTION && '_readflow_settings' === ReadMarker_Post_Overrides::META_KEY, 'Rename retains existing option and post-meta storage keys' );
$canonical = ReadMarker_Settings::defaults();
readmarker_check( ReadMarker_Settings::SCHEMA_VERSION === $canonical['schema_version'], 'Canonical schema version' );
readmarker_check( ReadMarker_Settings::SCHEMA_VERSION === sanitize_option( ReadMarker_Settings::OPTION, array( 'schema_version' => 999 ) )['schema_version'], 'Normal save stamps current schema' );
$legacy = array_merge( $canonical, array( 'enabled'=>false, 'wpm'=>321.5, 'post_types'=>array('page','book'), 'style'=>'card', 'show_time'=>false, 'show_word_count'=>true, 'position'=>'selector', 'target_selector'=>'article > .entry[data-kind="story"]', 'progress_enabled'=>true, 'progress_display'=>'inline_progress', 'progress_color'=>'#abcdef', 'progress_height'=>9, 'position_memory_enabled'=>true, 'reader_controls_enabled'=>true, 'reader_controls_placement'=>'below', 'reader_controls_visibility'=>'mobile', 'reader_controls_panel'=>'expanded' ) );
unset( $legacy['schema_version'] );
$writes = array(); $meta = $readmarker_test_meta;
$observe = static function( $new, $old, $option ) use ( &$writes ) { $writes[] = $option; return $new; };
add_filter( 'pre_update_option_readflow_settings', $observe, 10, 3 );
$readmarker_test_option = $legacy;
$result = $service->get();
foreach ( $legacy as $key=>$value ) { readmarker_check( $result[$key] === $value, 'Legacy value preserved: '.$key ); }
readmarker_check( $readmarker_test_option === $service->normalize($legacy), 'Migration persisted normalized values' );
readmarker_check( array(ReadMarker_Settings::OPTION) === $writes, 'Migration writes only owned option once' );
$service->get(); (new ReadMarker_Settings())->get();
readmarker_check( 1 === count($writes), 'Repeated reads and new service do not rewrite migrated schema' );
$future = array_merge($legacy,array('schema_version'=>ReadMarker_Settings::SCHEMA_VERSION+1,'future_field'=>'preserve'));
$readmarker_test_option=$future; $result=$service->get();
readmarker_check( $readmarker_test_option===$future && 1===count($writes), 'Future version and unknown fields untouched in storage' );
readmarker_check( 321.5===$result['wpm'] && 'inline_progress'===$result['progress_display'], 'Future compatible values usable' );
foreach ( array(null, false, '1', 'bad', -1, 1.5, array(), new stdClass()) as $version ) {
	$readmarker_test_option=array_merge($legacy,array('schema_version'=>$version)); $before=count($writes);
	$result=$service->get();
	readmarker_check( ReadMarker_Settings::SCHEMA_VERSION===$result['schema_version'] && 321.5===$result['wpm'] && count($writes)===$before+1, 'Malformed version safely migrates as legacy' );
}
$readmarker_test_option=array('post_type'=>'pages','wpm'=>-1,'enabled'=>array(),'position'=>'bad','progress_display'=>'bad','progress_color'=>'bad','progress_height'=>900,'reader_controls_panel'=>'bad','unknown'=>'discard');
$result=$service->get();
readmarker_check( $result===$service->normalize(array('post_types'=>array('page'),'enabled'=>false)), 'Legacy aliases and invalid values use existing validation' );
readmarker_check( $meta===$readmarker_test_meta, 'Migration never touches post overrides' );
foreach ( ReadMarker_Settings::progress_displays() as $mode=>$label ) { readmarker_check( $mode===$service->normalize(array('progress_display'=>$mode))['progress_display'], 'Mode validation preserved: '.$mode ); }
readmarker_check( 12===count(ReadMarker_Settings::progress_displays()) && isset(ReadMarker_Settings::progress_displays()['inline_progress']) && 'top_bar'===$canonical['progress_display'], 'Twelve modes and Top Bar default' );
remove_filter('pre_update_option_readflow_settings',$observe,10);
$readmarker_test_option=$canonical;
echo 'PASS: ', $checks-$start, ' settings schema assertions.', PHP_EOL;

