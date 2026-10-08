<?php
if(!isset($checks)){require __DIR__.'/display-test.php';return;}
$start=$checks;$s=new ReadMarker_Settings();
readmarker_check(false===ReadMarker_Settings::defaults()['reader_controls_enabled'],'Controls off by default');
foreach(array(true,1,'1') as $value)readmarker_check($s->normalize(array('reader_controls_enabled'=>$value))['reader_controls_enabled'],'Valid switch');
foreach(array('bad',array(),null) as $value)readmarker_check(!$s->normalize(array('reader_controls_enabled'=>$value))['reader_controls_enabled'],'Invalid switch');
$f=readmarker_progress_fixture();$html=$f->filter_content($post->post_content);
readmarker_check(false===strpos($html,'data-readmarker-reader-controls')&&!wp_script_is('readmarker-reader-controls','enqueued'),'Disabled no UI/script');
$f=readmarker_progress_fixture(array('enabled'=>false,'reader_controls_enabled'=>true));$html=$f->filter_content($post->post_content);
readmarker_check(1===substr_count($html,'data-readmarker-article=')&&1===substr_count($html,'data-readmarker-reader-controls'),'Controls-only shared target');
readmarker_check(wp_script_is('readmarker-reader-controls','enqueued')&&!wp_script_is('readmarker-progress','enqueued'),'No progress engine forced');
readmarker_check(strpos($html,'data-readmarker-reader-controls')<strpos($html,'data-readmarker-article='),'UI outside article');
readmarker_check(false!==strpos($html,'aria-expanded="false"')&&false!==strpos($html,'Reset Preferences'),'Accessible panel markup');
foreach(array('is_feed','is_preview','is_archive') as $flag){$f=readmarker_progress_fixture(array('reader_controls_enabled'=>true));$wp_query->$flag=true;readmarker_check($post->post_content===$f->filter_content($post->post_content),'Protected controls context');}
$readmarker_test_meta[456]=array('version'=>1,'behavior'=>'disabled');$f=readmarker_progress_fixture(array('reader_controls_enabled'=>true));readmarker_check($post->post_content===$f->filter_content($post->post_content),'Post disable suppresses controls');$readmarker_test_meta=array();
$f=readmarker_progress_fixture(array('reader_controls_enabled'=>true,'progress_enabled'=>true,'position_memory_enabled'=>true,'position'=>'selector','target_selector'=>'#header'));$html=$f->filter_content($post->post_content);
readmarker_check(1===substr_count($html,'data-readmarker-article=')&&false!==strpos($html,'data-readmarker-memory')&&false!==strpos($html,'data-readmarker-placement'),'Existing features coexist with one target');
readmarker_check($html===$f->filter_content($html),'No duplicate controls');

foreach(ReadMarker_Settings::reader_control_choices() as $key=>$choices){
 foreach(array_keys($choices) as $value)readmarker_check($s->normalize(array($key=>$value))[$key]===$value,'Valid control configuration');
 foreach(array('invalid',array(),null) as $invalid)readmarker_check($s->normalize(array($key=>$invalid))[$key]===ReadMarker_Settings::defaults()[$key],'Invalid control configuration defaults');
}
readmarker_check('reading_info'===ReadMarker_Settings::defaults()['reader_controls_placement']&&'all'===ReadMarker_Settings::defaults()['reader_controls_visibility']&&'collapsed'===ReadMarker_Settings::defaults()['reader_controls_panel'],'New defaults');
$f=readmarker_progress_fixture(array('reader_controls_enabled'=>true,'reader_controls_visibility'=>'desktop','reader_controls_panel'=>'expanded'));
$html=$f->filter_content($post->post_content);
readmarker_check(1===substr_count($html,'data-readmarker-reading-info='),'One shared metadata area');
foreach(array('data-reader-placement="reading_info"','data-reader-visibility="desktop"','data-reader-panel-state="expanded"') as $part)readmarker_check(false!==strpos($html,$part),'Safe server configuration');
$f=readmarker_progress_fixture(array('reader_controls_enabled'=>true,'reader_controls_placement'=>'below'));
readmarker_check(false===strpos($f->filter_content($post->post_content),'data-readmarker-reading-info='),'No unnecessary info wrapper for below');

$first=ReadMarker_Reader_Controls::markup(456);$second=ReadMarker_Reader_Controls::markup(456);
preg_match('/aria-controls="([^"]+)"/',$first,$first_id);preg_match('/aria-controls="([^"]+)"/',$second,$second_id);
readmarker_check($first_id[1]!==$second_id[1]&&false!==strpos($first,'id="'.$first_id[1].'"'),'Unique panel IDs with correct association');
readmarker_check(6===substr_count($first,'<button type="button"')&&false===strpos($first,'role="dialog"')&&false===strpos($first,'aria-live'),'Native button and disclosure semantics');

readmarker_check(1===substr_count($first,'data-reader-summary ')&&false!==strpos($first,'aria-hidden="true"></span></button>'),'One noninteractive hidden-from-AT summary span');
readmarker_check(false!==strpos($first,'readmarker-reader-controls-label">Reading Options</span>')&&false===strpos($first,'tabindex='),'Stable native trigger name and no extra focus target');
readmarker_progress_fixture();echo 'PASS: ',$checks-$start,' reader controls PHP assertions.',PHP_EOL;
