<?php
if(!isset($checks)){require __DIR__.'/display-test.php';return;}
require_once dirname(__DIR__).'/admin/class-readmarker-shortcodes-admin.php';
$start=$checks;$reference=new ReadMarker_Shortcodes_Admin();$before=$readmarker_test_option;$registered=$shortcode_tags;
$reference->register_hooks();$reference->add_page();$menu_entry=end($submenu['readmarker-overview']);
readmarker_check($menu_entry[1]==='manage_options'&&$menu_entry[2]==='readmarker-shortcodes','Reference registration and capability');
$metadata=ReadMarker_Shortcodes_Admin::reference();
readmarker_check(array_keys($metadata)===array('readmarker','readmarker_time','readmarker_words','readmarker_progress','readmarker_remaining'),'Only existing five shortcodes documented');
$scripts_before=wp_scripts()->queue;$styles_before=wp_styles()->queue;
ob_start();$reference->render();$html=ob_get_clean();
readmarker_check($scripts_before===wp_scripts()->queue && $styles_before===wp_styles()->queue,'Static previews enqueue no frontend assets');
preg_match_all('/<div class="readmarker-shortcode-preview-output">(.*?)<\/div>/s',$html,$previews);
$expected_previews=array('5 min read · 850 words','5 min','5 min','5 min read','850 words','850 words','850','42%','42%','','3 min 24 sec remaining','3 min 24 sec remaining','4 min','03:24','3 min 24 sec');
readmarker_check(count($previews[1])===15,'Every syntax example has a Preview');
foreach($expected_previews as $index=>$expected){
 readmarker_check(html_entity_decode(strip_tags($previews[1][$index]),ENT_QUOTES,'UTF-8')===$expected,'Representative output for syntax example '.$index);
}
readmarker_check(substr_count($html,'aria-valuenow="42"')===3 && substr_count($html,'class="readmarker-shortcode-preview-track"')===3,'All progress examples have static accessible bars, including percentage hidden');
readmarker_check(strpos($html,'data-readmarker-inline=')===false && strpos($html,'aria-live=')===false,'Previews have no runtime consumer hooks or live announcements');
readmarker_check(strpos($html,'<h1>ReadMarker Shortcodes</h1>')!==false,'Reference heading');
readmarker_check(preg_match_all('/<section class="readmarker-shortcodes-card"><h2>.*?<\/h2><p>.*?<\/p><div class="readmarker-shortcode-variants">/s',$html)===5,'All section headings and descriptions precede their variant grids');
readmarker_check(substr_count($html,'class="readmarker-shortcode-entry"')===15 && preg_match_all('/<div class="readmarker-shortcode-entry"><div class="readmarker-shortcode-example">.*?<\/button><\/div><div class="readmarker-shortcode-preview">/s',$html)===15,'Each variant card groups its code, unchanged Copy row, and preview');
foreach($metadata as $tag=>$entry){
 readmarker_check(isset($shortcode_tags[$tag]),'Documented shortcode is registered '.$tag);
 foreach(array_merge(array(''),$entry[2]) as $attr){$code='['.$tag.($attr===''?'':' '.$attr).']';readmarker_check(strpos($html,'<code>'.esc_html($code).'</code>')!==false,'Exact escaped syntax '.$code);}
}
readmarker_check(substr_count($html,'class="readmarker-shortcode-code" tabindex="0" role="region" aria-label="Shortcode example"')===15,'Every code example has a named keyboard-accessible scroll area');
readmarker_check(substr_count($html,'</code></div> <button type="button"')===15,'Copy buttons remain outside code scroll areas');
readmarker_check(substr_count($html,'data-readmarker-copy ')===15,'Copy button for every base syntax and supported example');
readmarker_check(strpos($html,'<form')===false && strpos($html,'<script')===false,'Read-only escaped output');
readmarker_check($before===$readmarker_test_option && $registered===$shortcode_tags,'No settings or shortcode registration changes');
wp_dequeue_script('readmarker-shortcodes');wp_dequeue_style('readmarker-shortcodes');
foreach(array('plugins.php','post.php','readmarker_page_readmarker') as $screen){$reference->assets($screen);readmarker_check(!wp_script_is('readmarker-shortcodes','enqueued')&&!wp_style_is('readmarker-shortcodes','enqueued'),'No assets on '.$screen);}
$reference->assets('readmarker_page_readmarker-shortcodes');readmarker_check(wp_script_is('readmarker-shortcodes','enqueued')&&wp_style_is('readmarker-shortcodes','enqueued'),'Page-only assets');
wp_dequeue_script('readmarker-shortcodes');wp_dequeue_style('readmarker-shortcodes');$readmarker_test_admin=false;
ob_start();$reference->render();readmarker_check(ob_get_clean()==='','Unauthorized reference hidden');$reference->assets('readmarker_page_readmarker-shortcodes');readmarker_check(!wp_script_is('readmarker-shortcodes','enqueued'),'Unauthorized assets blocked');$readmarker_test_admin=true;
echo 'PASS: ',$checks-$start,' shortcode admin assertions.',PHP_EOL;
