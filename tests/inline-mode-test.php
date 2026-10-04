<?php
if(!isset($checks)){require __DIR__.'/display-test.php';return;}
$start=$checks;
readflow_check('Inline Reading Progress'===ReadFlow_Settings::progress_displays()['inline_progress']&&'top_bar'===ReadFlow_Settings::defaults()['progress_display'],'Registry and default');
foreach(array('before','after','selector','manual') as $position){
 $f=readflow_progress_fixture(array('progress_enabled'=>true,'progress_display'=>'inline_progress','position'=>$position,'target_selector'=>'#header'));
 $html=$f->filter_content($post->post_content);
 if('manual'===$position){readflow_check(false===strpos($html,'data-readflow-auto-inline')&&!wp_script_is('readflow-progress','enqueued'),'Manual no automatic inline');continue;}
 readflow_check(1===substr_count($html,'data-readflow-auto-inline')&&1===substr_count($html,'data-readflow-article='),'Single automatic consumer and article');
 readflow_check(false!==strpos($html,'role="progressbar"')&&false!==strpos($html,'aria-label="Reading progress"'),'Existing semantic markup');
 readflow_check(wp_style_is('readflow-inline-progress-mode','enqueued')&&wp_script_is('readflow-progress','enqueued'),'Conditional shared runtime and mode CSS');
 if('before'===$position)readflow_check(false!==strpos($html,'data-readflow-article="456"><div class="readflow-inline-progress-auto"'),'First child before');
 if('after'===$position)readflow_check(false!==strpos($html,$post->post_content.'<div class="readflow-inline-progress-auto"'),'Last child after');
 if('selector'===$position)readflow_check(1===substr_count($html,'<template data-readflow-placement')&&strpos($html,'data-readflow-auto-inline')<strpos($html,'</template>'),'Existing single selector transport');
 readflow_check($html===$f->filter_content($html),'Duplicate integration guarded');
}
foreach(array(array('enabled'=>false),array('progress_enabled'=>false),array('position'=>'selector','target_selector'=>'')) as $changes){$f=readflow_progress_fixture(array_merge(array('progress_enabled'=>true,'progress_display'=>'inline_progress'),$changes));$html=$f->filter_content($post->post_content);readflow_check(false===strpos($html,'data-readflow-auto-inline')&&!wp_style_is('readflow-inline-progress-mode','enqueued'),'Disabled/empty selector no mode assets');}
$f=readflow_progress_fixture(array('progress_enabled'=>true));$html=$f->filter_content($post->post_content);readflow_check(!wp_style_is('readflow-inline-progress-mode','enqueued'),'Other mode no inline mode CSS');
readflow_check(false===strpos(do_shortcode('[readflow_progress]'),'data-readflow-auto-inline'),'Shortcode markup unchanged');
foreach(array('before','after') as $position){
 $f=readflow_progress_fixture(array('progress_enabled'=>true,'progress_display'=>'inline_progress','position'=>$position));
 do_action('wp_head'); $html=$f->filter_content($post->post_content);
 readflow_check(false!==strpos($html,'readflow-inline-progress-mode-css') && false!==strpos($html,'readflow-display-css'), 'Late inline and metadata stylesheet links both returned: '.$position);
}
readflow_progress_fixture();echo 'PASS: ',$checks-$start,' automatic inline mode assertions.',PHP_EOL;
