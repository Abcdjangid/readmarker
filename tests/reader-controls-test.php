<?php
if(!isset($checks)){require __DIR__.'/display-test.php';return;}
$start=$checks;$s=new ReadFlow_Settings();
readflow_check(false===ReadFlow_Settings::defaults()['reader_controls_enabled'],'Controls off by default');
foreach(array(true,1,'1') as $value)readflow_check($s->normalize(array('reader_controls_enabled'=>$value))['reader_controls_enabled'],'Valid switch');
foreach(array('bad',array(),null) as $value)readflow_check(!$s->normalize(array('reader_controls_enabled'=>$value))['reader_controls_enabled'],'Invalid switch');
$f=readflow_progress_fixture();$html=$f->filter_content($post->post_content);
readflow_check(false===strpos($html,'data-readflow-reader-controls')&&!wp_script_is('readflow-reader-controls','enqueued'),'Disabled no UI/script');
$f=readflow_progress_fixture(array('enabled'=>false,'reader_controls_enabled'=>true));$html=$f->filter_content($post->post_content);
readflow_check(1===substr_count($html,'data-readflow-article=')&&1===substr_count($html,'data-readflow-reader-controls'),'Controls-only shared target');
readflow_check(wp_script_is('readflow-reader-controls','enqueued')&&!wp_script_is('readflow-progress','enqueued'),'No progress engine forced');
readflow_check(strpos($html,'data-readflow-reader-controls')<strpos($html,'data-readflow-article='),'UI outside article');
readflow_check(false!==strpos($html,'aria-expanded="false"')&&false!==strpos($html,'Reset Preferences'),'Accessible panel markup');
foreach(array('is_feed','is_preview','is_archive') as $flag){$f=readflow_progress_fixture(array('reader_controls_enabled'=>true));$wp_query->$flag=true;readflow_check($post->post_content===$f->filter_content($post->post_content),'Protected controls context');}
$readflow_test_meta[456]=array('version'=>1,'behavior'=>'disabled');$f=readflow_progress_fixture(array('reader_controls_enabled'=>true));readflow_check($post->post_content===$f->filter_content($post->post_content),'Post disable suppresses controls');$readflow_test_meta=array();
$f=readflow_progress_fixture(array('reader_controls_enabled'=>true,'progress_enabled'=>true,'position_memory_enabled'=>true,'position'=>'selector','target_selector'=>'#header'));$html=$f->filter_content($post->post_content);
readflow_check(1===substr_count($html,'data-readflow-article=')&&false!==strpos($html,'data-readflow-memory')&&false!==strpos($html,'data-readflow-placement'),'Existing features coexist with one target');
readflow_check($html===$f->filter_content($html),'No duplicate controls');

foreach(ReadFlow_Settings::reader_control_choices() as $key=>$choices){
 foreach(array_keys($choices) as $value)readflow_check($s->normalize(array($key=>$value))[$key]===$value,'Valid control configuration');
 foreach(array('invalid',array(),null) as $invalid)readflow_check($s->normalize(array($key=>$invalid))[$key]===ReadFlow_Settings::defaults()[$key],'Invalid control configuration defaults');
}
readflow_check('reading_info'===ReadFlow_Settings::defaults()['reader_controls_placement']&&'all'===ReadFlow_Settings::defaults()['reader_controls_visibility']&&'collapsed'===ReadFlow_Settings::defaults()['reader_controls_panel'],'New defaults');
$f=readflow_progress_fixture(array('reader_controls_enabled'=>true,'reader_controls_visibility'=>'desktop','reader_controls_panel'=>'expanded'));
$html=$f->filter_content($post->post_content);
readflow_check(1===substr_count($html,'data-readflow-reading-info='),'One shared metadata area');
foreach(array('data-reader-placement="reading_info"','data-reader-visibility="desktop"','data-reader-panel-state="expanded"') as $part)readflow_check(false!==strpos($html,$part),'Safe server configuration');
$f=readflow_progress_fixture(array('reader_controls_enabled'=>true,'reader_controls_placement'=>'below'));
readflow_check(false===strpos($f->filter_content($post->post_content),'data-readflow-reading-info='),'No unnecessary info wrapper for below');

$first=ReadFlow_Reader_Controls::markup(456);$second=ReadFlow_Reader_Controls::markup(456);
preg_match('/aria-controls="([^"]+)"/',$first,$first_id);preg_match('/aria-controls="([^"]+)"/',$second,$second_id);
readflow_check($first_id[1]!==$second_id[1]&&false!==strpos($first,'id="'.$first_id[1].'"'),'Unique panel IDs with correct association');
readflow_check(6===substr_count($first,'<button type="button"')&&false===strpos($first,'role="dialog"')&&false===strpos($first,'aria-live'),'Native button and disclosure semantics');

readflow_check(1===substr_count($first,'data-reader-summary ')&&false!==strpos($first,'aria-hidden="true"></span></button>'),'One noninteractive hidden-from-AT summary span');
readflow_check(false!==strpos($first,'readflow-reader-controls-label">Reading Options</span>')&&false===strpos($first,'tabindex='),'Stable native trigger name and no extra focus target');
readflow_progress_fixture();echo 'PASS: ',$checks-$start,' reader controls PHP assertions.',PHP_EOL;
