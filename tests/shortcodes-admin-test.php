<?php
if(!isset($checks)){require __DIR__.'/display-test.php';return;}
require_once dirname(__DIR__).'/admin/class-readflow-shortcodes-admin.php';
$start=$checks;$reference=new ReadFlow_Shortcodes_Admin();$before=$readflow_test_option;$registered=$shortcode_tags;
$reference->register_hooks();$reference->add_page();$menu_entry=end($submenu['readflow-overview']);
readflow_check($menu_entry[1]==='manage_options'&&$menu_entry[2]==='readflow-shortcodes','Reference registration and capability');
$metadata=ReadFlow_Shortcodes_Admin::reference();
readflow_check(array_keys($metadata)===array('readflow','readflow_time','readflow_words','readflow_progress','readflow_remaining'),'Only existing five shortcodes documented');
$scripts_before=wp_scripts()->queue;$styles_before=wp_styles()->queue;
ob_start();$reference->render();$html=ob_get_clean();
readflow_check($scripts_before===wp_scripts()->queue && $styles_before===wp_styles()->queue,'Static previews enqueue no frontend assets');
preg_match_all('/<div class="readflow-shortcode-preview-output">(.*?)<\/div>/s',$html,$previews);
$expected_previews=array('5 min read · 850 words','5 min','5 min','5 min read','850 words','850 words','850','42%','42%','','3 min 24 sec remaining','3 min 24 sec remaining','4 min','03:24','3 min 24 sec');
readflow_check(count($previews[1])===15,'Every syntax example has a Preview');
foreach($expected_previews as $index=>$expected){
 readflow_check(html_entity_decode(strip_tags($previews[1][$index]),ENT_QUOTES,'UTF-8')===$expected,'Representative output for syntax example '.$index);
}
readflow_check(substr_count($html,'aria-valuenow="42"')===3 && substr_count($html,'class="readflow-shortcode-preview-track"')===3,'All progress examples have static accessible bars, including percentage hidden');
readflow_check(strpos($html,'data-readflow-inline=')===false && strpos($html,'aria-live=')===false,'Previews have no runtime consumer hooks or live announcements');
readflow_check(strpos($html,'<h1>ReadFlow Shortcodes</h1>')!==false,'Reference heading');
readflow_check(preg_match_all('/<section class="readflow-shortcodes-card"><h2>.*?<\/h2><p>.*?<\/p><div class="readflow-shortcode-variants">/s',$html)===5,'All section headings and descriptions precede their variant grids');
readflow_check(substr_count($html,'class="readflow-shortcode-entry"')===15 && preg_match_all('/<div class="readflow-shortcode-entry"><div class="readflow-shortcode-example">.*?<\/button><\/div><div class="readflow-shortcode-preview">/s',$html)===15,'Each variant card groups its code, unchanged Copy row, and preview');
foreach($metadata as $tag=>$entry){
 readflow_check(isset($shortcode_tags[$tag]),'Documented shortcode is registered '.$tag);
 foreach(array_merge(array(''),$entry[2]) as $attr){$code='['.$tag.($attr===''?'':' '.$attr).']';readflow_check(strpos($html,'<code>'.esc_html($code).'</code>')!==false,'Exact escaped syntax '.$code);}
}
readflow_check(substr_count($html,'class="readflow-shortcode-code" tabindex="0" role="region" aria-label="Shortcode example"')===15,'Every code example has a named keyboard-accessible scroll area');
readflow_check(substr_count($html,'</code></div> <button type="button"')===15,'Copy buttons remain outside code scroll areas');
readflow_check(substr_count($html,'data-readflow-copy ')===15,'Copy button for every base syntax and supported example');
readflow_check(strpos($html,'<form')===false && strpos($html,'<script')===false,'Read-only escaped output');
readflow_check($before===$readflow_test_option && $registered===$shortcode_tags,'No settings or shortcode registration changes');
wp_dequeue_script('readflow-shortcodes');wp_dequeue_style('readflow-shortcodes');
foreach(array('plugins.php','post.php','readflow_page_readflow') as $screen){$reference->assets($screen);readflow_check(!wp_script_is('readflow-shortcodes','enqueued')&&!wp_style_is('readflow-shortcodes','enqueued'),'No assets on '.$screen);}
$reference->assets('readflow_page_readflow-shortcodes');readflow_check(wp_script_is('readflow-shortcodes','enqueued')&&wp_style_is('readflow-shortcodes','enqueued'),'Page-only assets');
wp_dequeue_script('readflow-shortcodes');wp_dequeue_style('readflow-shortcodes');$readflow_test_admin=false;
ob_start();$reference->render();readflow_check(ob_get_clean()==='','Unauthorized reference hidden');$reference->assets('readflow_page_readflow-shortcodes');readflow_check(!wp_script_is('readflow-shortcodes','enqueued'),'Unauthorized assets blocked');$readflow_test_admin=true;
echo 'PASS: ',$checks-$start,' shortcode admin assertions.',PHP_EOL;
