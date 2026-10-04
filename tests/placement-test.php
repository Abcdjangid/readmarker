<?php
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$placement_start=$checks;
$service=new ReadFlow_Settings();
foreach(array('before','after','manual','selector') as $position){readflow_check($position===$service->normalize(array('position'=>$position))['position'],'Placement value preserved');}
readflow_check('before'===$service->normalize(array('position'=>'invalid'))['position'],'Unknown position default');
foreach(array('article .entry-content','#main > article:first-child [data-kind="story"]','[data-text="<value>"]','.escaped\\:name','[invalid','') as $selector){readflow_check($selector===$service->normalize(array('target_selector'=>$selector))['target_selector'],'CSS punctuation preserved; browser parses');}
foreach(array(null,array(),new stdClass()) as $invalid){readflow_check(''===$service->normalize(array('target_selector'=>$invalid))['target_selector'],'Invalid selector type empty');}
$frontend=readflow_progress_fixture(array('position'=>'selector','target_selector'=>'#header[data-title="x"]'));
$html=$frontend->filter_content($post->post_content);
readflow_check(1===substr_count($html,'<template data-readflow-placement')&&1===substr_count($html,'data-readflow-post='),'One inert output transport');
readflow_check(false!==strpos($html,'&quot;x&quot;'),'Selector safely escaped as data');
readflow_check(false!==strpos($html,'</template>')&&substr($html,-strlen($post->post_content))===$post->post_content,'No visible before/after display');
readflow_check(wp_script_is('readflow-placement','enqueued')&&!wp_script_is('readflow-progress','enqueued'),'Placement only dependencies');
readflow_check($html===$frontend->filter_content($html),'Duplicate PHP guard retained');
foreach(array('before','after','manual') as $position){$frontend=readflow_progress_fixture(array('position'=>$position,'target_selector'=>'#header'));$html=$frontend->filter_content($post->post_content);readflow_check(!wp_script_is('readflow-placement','enqueued')&&false===strpos($html,'data-readflow-placement'),'Legacy positions no adapter');}
$frontend=readflow_progress_fixture(array('position'=>'selector','target_selector'=>''));
readflow_check($post->post_content===$frontend->filter_content($post->post_content)&&!wp_script_is('readflow-placement','enqueued'),'Empty selector no output/assets');
$frontend=readflow_progress_fixture(array('position'=>'selector','target_selector'=>'#header'));$wp_query->is_feed=true;
readflow_check($post->post_content===$frontend->filter_content($post->post_content)&&!wp_script_is('readflow-placement','enqueued'),'Rules first');
$frontend=readflow_progress_fixture(array('position'=>'selector','target_selector'=>'#header'));
$readflow_test_meta[456]=array('version'=>1,'behavior'=>'override','position'=>'after');
$html=$frontend->filter_content($post->post_content);
readflow_check(false===strpos($html,'<template')&&!wp_script_is('readflow-placement','enqueued'),'Per-post legacy position override');
$readflow_test_meta[456]=array('version'=>1,'behavior'=>'disabled');
$frontend=readflow_progress_fixture(array('position'=>'selector','target_selector'=>'#header'));
readflow_check($post->post_content===$frontend->filter_content($post->post_content),'Per-post disable honored');
$readflow_test_meta=array();
$readflow_test_option=array('position'=>'selector','target_selector'=>'"><script>alert(1)</script>');
ob_start();$admin->render_field(array('key'=>'target_selector'));$field=ob_get_clean();
readflow_check(false===strpos($field,'<script>')&&false!==strpos($field,'&lt;script&gt;'),'Settings selector escaped');
readflow_progress_fixture();
echo 'PASS: ',$checks-$placement_start,' placement PHP assertions.',PHP_EOL;
