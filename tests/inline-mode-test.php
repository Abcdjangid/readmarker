<?php
if(!isset($checks)){require __DIR__.'/display-test.php';return;}
$start=$checks;
readmarker_check('Inline Reading Progress'===ReadMarker_Settings::progress_displays()['inline_progress']&&'top_bar'===ReadMarker_Settings::defaults()['progress_display'],'Registry and default');
foreach(array('before','after','selector','manual') as $position){
 $f=readmarker_progress_fixture(array('progress_enabled'=>true,'progress_display'=>'inline_progress','position'=>$position,'target_selector'=>'#header'));
 $html=$f->filter_content($post->post_content);
 if('manual'===$position){readmarker_check(false===strpos($html,'data-readmarker-auto-inline')&&!wp_script_is('readmarker-progress','enqueued'),'Manual no automatic inline');continue;}
 readmarker_check(1===substr_count($html,'data-readmarker-auto-inline')&&1===substr_count($html,'data-readmarker-article='),'Single automatic consumer and article');
 readmarker_check(false!==strpos($html,'role="progressbar"')&&false!==strpos($html,'aria-label="Reading progress"'),'Existing semantic markup');
 readmarker_check(wp_style_is('readmarker-inline-progress-mode','enqueued')&&wp_script_is('readmarker-progress','enqueued'),'Conditional shared runtime and mode CSS');
 if('before'===$position)readmarker_check(false!==strpos($html,'data-readmarker-article="456"><div class="readmarker-inline-progress-auto"'),'First child before');
 if('after'===$position)readmarker_check(false!==strpos($html,$post->post_content.'<div class="readmarker-inline-progress-auto"'),'Last child after');
 if('selector'===$position)readmarker_check(1===substr_count($html,'<template data-readmarker-placement')&&strpos($html,'data-readmarker-auto-inline')<strpos($html,'</template>'),'Existing single selector transport');
 readmarker_check($html===$f->filter_content($html),'Duplicate integration guarded');
}
foreach(array(array('enabled'=>false),array('progress_enabled'=>false),array('position'=>'selector','target_selector'=>'')) as $changes){$f=readmarker_progress_fixture(array_merge(array('progress_enabled'=>true,'progress_display'=>'inline_progress'),$changes));$html=$f->filter_content($post->post_content);readmarker_check(false===strpos($html,'data-readmarker-auto-inline')&&!wp_style_is('readmarker-inline-progress-mode','enqueued'),'Disabled/empty selector no mode assets');}
$f=readmarker_progress_fixture(array('progress_enabled'=>true));$html=$f->filter_content($post->post_content);readmarker_check(!wp_style_is('readmarker-inline-progress-mode','enqueued'),'Other mode no inline mode CSS');
readmarker_check(false===strpos(do_shortcode('[readmarker_progress]'),'data-readmarker-auto-inline'),'Shortcode markup unchanged');
foreach(array('before','after') as $position){
 $f=readmarker_progress_fixture(array('progress_enabled'=>true,'progress_display'=>'inline_progress','position'=>$position));
 do_action('wp_head'); $html=$f->filter_content($post->post_content);
 readmarker_check(false!==strpos($html,'readmarker-inline-progress-mode-css') && false!==strpos($html,'readmarker-display-css'), 'Late inline and metadata stylesheet links both returned: '.$position);
}
readmarker_progress_fixture();echo 'PASS: ',$checks-$start,' automatic inline mode assertions.',PHP_EOL;
