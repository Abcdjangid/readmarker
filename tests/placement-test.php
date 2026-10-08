<?php
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$placement_start=$checks;
$service=new ReadMarker_Settings();
foreach(array('before','after','manual','selector') as $position){readmarker_check($position===$service->normalize(array('position'=>$position))['position'],'Placement value preserved');}
readmarker_check('before'===$service->normalize(array('position'=>'invalid'))['position'],'Unknown position default');
foreach(array('article .entry-content','#main > article:first-child [data-kind="story"]','[data-text="<value>"]','.escaped\\:name','[invalid','') as $selector){readmarker_check($selector===$service->normalize(array('target_selector'=>$selector))['target_selector'],'CSS punctuation preserved; browser parses');}
foreach(array(null,array(),new stdClass()) as $invalid){readmarker_check(''===$service->normalize(array('target_selector'=>$invalid))['target_selector'],'Invalid selector type empty');}
$frontend=readmarker_progress_fixture(array('position'=>'selector','target_selector'=>'#header[data-title="x"]'));
$html=$frontend->filter_content($post->post_content);
readmarker_check(1===substr_count($html,'<template data-readmarker-placement')&&1===substr_count($html,'data-readmarker-post='),'One inert output transport');
readmarker_check(false!==strpos($html,'&quot;x&quot;'),'Selector safely escaped as data');
readmarker_check(false!==strpos($html,'</template>')&&substr($html,-strlen($post->post_content))===$post->post_content,'No visible before/after display');
readmarker_check(wp_script_is('readmarker-placement','enqueued')&&!wp_script_is('readmarker-progress','enqueued'),'Placement only dependencies');
readmarker_check($html===$frontend->filter_content($html),'Duplicate PHP guard retained');
foreach(array('before','after','manual') as $position){$frontend=readmarker_progress_fixture(array('position'=>$position,'target_selector'=>'#header'));$html=$frontend->filter_content($post->post_content);readmarker_check(!wp_script_is('readmarker-placement','enqueued')&&false===strpos($html,'data-readmarker-placement'),'Legacy positions no adapter');}
$frontend=readmarker_progress_fixture(array('position'=>'selector','target_selector'=>''));
readmarker_check($post->post_content===$frontend->filter_content($post->post_content)&&!wp_script_is('readmarker-placement','enqueued'),'Empty selector no output/assets');
$frontend=readmarker_progress_fixture(array('position'=>'selector','target_selector'=>'#header'));$wp_query->is_feed=true;
readmarker_check($post->post_content===$frontend->filter_content($post->post_content)&&!wp_script_is('readmarker-placement','enqueued'),'Rules first');
$frontend=readmarker_progress_fixture(array('position'=>'selector','target_selector'=>'#header'));
$readmarker_test_meta[456]=array('version'=>1,'behavior'=>'override','position'=>'after');
$html=$frontend->filter_content($post->post_content);
readmarker_check(false===strpos($html,'<template')&&!wp_script_is('readmarker-placement','enqueued'),'Per-post legacy position override');
$readmarker_test_meta[456]=array('version'=>1,'behavior'=>'disabled');
$frontend=readmarker_progress_fixture(array('position'=>'selector','target_selector'=>'#header'));
readmarker_check($post->post_content===$frontend->filter_content($post->post_content),'Per-post disable honored');
$readmarker_test_meta=array();
$readmarker_test_option=array('position'=>'selector','target_selector'=>'"><script>alert(1)</script>');
ob_start();$admin->render_field(array('key'=>'target_selector'));$field=ob_get_clean();
readmarker_check(false===strpos($field,'<script>')&&false!==strpos($field,'&lt;script&gt;'),'Settings selector escaped');
readmarker_progress_fixture();
echo 'PASS: ',$checks-$placement_start,' placement PHP assertions.',PHP_EOL;
