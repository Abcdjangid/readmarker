<?php
if(!isset($checks)){require __DIR__.'/display-test.php';return;}
$start=$checks;$service=new ReadMarker_Settings();$presets=new ReadMarker_Presets($service);$registry=ReadMarker_Presets::registry();
readmarker_check(array('minimal','progress','focus','detailed','milestones','balanced')===array_keys($registry),'Exactly six stable presets');
$base=array_merge(ReadMarker_Settings::defaults(),array('wpm'=>321.5,'progress_color'=>'#abcdef','progress_height'=>7,'position_memory_enabled'=>true,'post_types'=>array('post','book'),'position'=>'selector','target_selector'=>'#title','style'=>'card'));
$readmarker_test_option=ReadMarker_Settings::defaults();
readmarker_check('minimal'===$presets->active_preset(),'Canonical defaults match Minimal');
readmarker_check('Balanced'===$registry['balanced']['name'] && 'Reading time, word count, and a subtle progress indicator for a balanced reading experience.'===$registry['balanced']['description'],'Exact Balanced copy');
$writes=0;
$intercept=static function($new,$old)use(&$writes){$GLOBALS['readmarker_test_option']=$new;++$writes;return $old;};
add_filter('pre_update_option_readflow_settings',$intercept,10,2);
$expected=array('minimal'=>array(false,'top_bar',false),'progress'=>array(true,'top_bar',false),'focus'=>array(true,'floating_widget',false),'detailed'=>array(true,'time_remaining',true),'milestones'=>array(true,'reading_milestones',false),'balanced'=>array(true,'top_bar',true));
$meta_before=$readmarker_test_meta;
foreach($registry as $id=>$preset){
 $readmarker_test_option=$base;$request=array('readmarker_preset'=>$id,'readmarker_preset_nonce'=>wp_create_nonce('readmarker_apply_preset'),'readmarker_preset_confirm'=>'1');
 readmarker_check(is_string($preset['name'])&&''!==$preset['description'],'Registry presentation');
 readmarker_check($presets->apply($request,'POST'),'Preset applies '.$id);
 readmarker_check($presets->active_preset()===$id,'Applied preset is the single active match '.$id);
 ob_start();$presets->render();$card_html=ob_get_clean();
 readmarker_check(substr_count($card_html,trim(checked(true,true,false)))===1 && substr_count($card_html,'class="readmarker-presets__active"')===1,'One checked confirmation and active badge '.$id);
 readmarker_check(strpos($card_html,'value="'.$id.'"><p><label><input type="checkbox" name="readmarker_preset_confirm" value="1"'.checked(true,true,false))!==false,'Correct active card '.$id);
 $readmarker_test_option['enabled']=false;
 readmarker_check(null===$presets->active_preset(),'Manual owned change removes active match');
 $readmarker_test_option['enabled']=true;
 $actual=$readmarker_test_option;
 // The normal settings page must show the saved configuration, including unowned fields.
 ob_start();$admin->render_page();$settings_html=ob_get_clean();
 $field_errors=array();
 foreach($wp_settings_fields['readmarker'] as $fields){
  foreach($fields as $field){
   $key=$field['args']['key'];
   ob_start();$admin->render_field(array('key'=>$key));$field_html=ob_get_clean();
   if(strpos($settings_html,$field_html)===false)$field_errors[]=$key.' missing from Settings';
   // Field fragments contain only standard inputs/selects; inspect their submitted values.
   $document=new DOMDocument();$document->loadHTML('<html><body>'.preg_replace('/<\/?(?:details|summary)\b[^>]*>/','',$field_html).'</body></html>');
   $values=array();
   foreach($document->getElementsByTagName('input') as $input){
    if($input->getAttribute('type')==='hidden')continue;
    if($input->hasAttribute('disabled')||$input->hasAttribute('readonly'))$field_errors[]=$key.' locked input';
    if($input->getAttribute('type')!=='checkbox'||$input->hasAttribute('checked'))$values[]=$input->getAttribute('value');
   }
   foreach($document->getElementsByTagName('select') as $select){
    if($select->hasAttribute('disabled'))$field_errors[]=$key.' locked select';
    foreach($select->getElementsByTagName('option') as $option){if($option->hasAttribute('selected'))$values[]=$option->getAttribute('value');}
   }
   $expected_value=$actual[$key];
   $expected_values=is_array($expected_value)?$expected_value:(is_bool($expected_value)?($expected_value?array('1'):array()):array((string)$expected_value));
   if($values!==$expected_values)$field_errors[]=$key.' incorrect value';
  }
 }
 readmarker_check(empty($field_errors),'All Settings fields reflect saved values and remain editable after '.$id.': '.implode(', ',$field_errors));
 $custom=array_merge($actual,array('position'=>'after'));
 update_option(ReadMarker_Settings::OPTION,$custom);
 readmarker_check((new ReadMarker_Settings())->get()===$custom && null===$presets->active_preset(),'Normal save persists customization without an active preset: '.$id);
 ob_start();$admin->render_field(array('key'=>'position'));$custom_field=ob_get_clean();
 readmarker_check(strpos($custom_field,'value="after" '.selected('after','after',false))!==false,'Reloaded Settings reflects customization: '.$id);
 $frontend=readmarker_progress_fixture($service->get());
 $article=$post->post_content;$output=$frontend->filter_content($article);
 $info_position=strpos($output,'data-readmarker-post="456"');$article_position=strpos($output,$article);
 readmarker_check(false!==$info_position && false!==$article_position && $info_position>$article_position,'Frontend respects customized After placement: '.$id);
 if('detailed'===$id){
  update_option(ReadMarker_Settings::OPTION,array_merge($custom,array('progress_display'=>'floating_widget')));
  $frontend=readmarker_progress_fixture($service->get());$output=$frontend->filter_content($post->post_content);
  readmarker_check(strpos($output,'data-readmarker-consumer="floating_widget"')!==false && strpos($output,'data-readmarker-consumer="time_remaining"')===false && null===$presets->active_preset(),'Detailed customization uses Floating Widget without forcing original consumer');
 }
 update_option(ReadMarker_Settings::OPTION,$actual);
 readmarker_check($presets->active_preset()===$id,'Manual restoration restores derived active match: '.$id);
 if('detailed'===$id){
  $data=(new ReadMarker_Calculator())->calculate_from_content(str_repeat('word ',200));$renderer=new ReadMarker_Renderer();
  readmarker_check(strpos($renderer->progress_bar($actual,$data['reading_seconds'],$data['formatted_time']),'data-readmarker-consumer="time_remaining"')!==false,'Detailed renders remaining-time consumer');
  $metadata=$renderer->render($data,$actual);readmarker_check(strpos($metadata,'readmarker-time')!==false&&strpos($metadata,'readmarker-words')!==false,'Detailed shows time and word count');
 }

 readmarker_check(true===$actual['enabled']&&true===$actual['show_time']&&'before'===$actual['position'],'Common owned fields');
 readmarker_check(array($actual['progress_enabled'],$actual['progress_display'],$actual['show_word_count'])===$expected[$id],'Exact preset fields');
 foreach(array_diff(array_keys($base),array_keys($preset['values'])) as $key){readmarker_check($actual[$key]===$base[$key],'Unowned preserved '.$key);}
 readmarker_check(!isset($actual['preset'])&&$meta_before===$readmarker_test_meta,'No active preset or post meta mutations');
 readmarker_check($service->normalize($actual)===$actual,'Existing validation applied');
}
$request=array('readmarker_preset'=>'balanced','readmarker_preset_nonce'=>wp_create_nonce('readmarker_apply_preset'),'readmarker_preset_confirm'=>'1');
foreach(array(array(),array_merge($request,array('readmarker_preset'=>'unknown')),array_merge($request,array('readmarker_preset'=>array())),array_merge($request,array('readmarker_preset_nonce'=>'bad')),array_merge($request,array('readmarker_preset_nonce'=>array())),array_merge($request,array('readmarker_preset_confirm'=>'0'))) as $invalid){$count=$writes;readmarker_check(!$presets->apply($invalid,'POST')&&$writes===$count,'Malformed/nonce/confirmation denied');}
$count=$writes;readmarker_check(!$presets->apply($request,'GET')&&$writes===$count,'GET denied');
$readmarker_test_admin=false;readmarker_check(!$presets->apply($request,'POST')&&$writes===$count,'Capability denied');$readmarker_test_admin=true;
readmarker_check(true===$registry['detailed']['values']['progress_enabled'] && 'time_remaining'===$registry['detailed']['values']['progress_display'],'Detailed enables its promised remaining-time consumer');
ob_start();$presets->render();$html=ob_get_clean();
readmarker_check(6===substr_count($html,'class="readmarker-presets__card')&&6===substr_count($html,'name="readmarker_preset_nonce"'),'Six separate protected forms');
readmarker_check(6===substr_count($html,'name="readmarker_preset_confirm"')&&false===strpos($html,'selected but inactive'),'Confirmation remains; obsolete Detailed caveat removed');
readmarker_check(strpos($html,'Apply Balanced')!==false,'Balanced renders');
readmarker_check(strpos($html,'customize any available setting in the sections below')!==false,'Preset help points to normal editable settings');
$readmarker_test_option['wpm']=400.0;$readmarker_test_option['reader_controls_enabled']=true;
readmarker_check('balanced'===$presets->active_preset(),'Unowned changes retain active match');
$readmarker_test_option['position']='after';ob_start();$presets->render();$unmatched=ob_get_clean();
readmarker_check(null===$presets->active_preset() && strpos($unmatched,trim(checked(true,true,false)))===false && strpos($unmatched,'class="readmarker-presets__active"')===false,'No active UI for unmatched settings');

$legacy_detailed=array_merge($base,array('progress_enabled'=>false,'progress_display'=>'time_remaining','show_word_count'=>true));
$readmarker_test_option=$legacy_detailed;$presets->active_preset();ob_start();$presets->render();ob_end_clean();
readmarker_check($readmarker_test_option===$legacy_detailed,'Viewing presets does not upgrade old Detailed configuration');
// Use the existing redirect/nonce/option adapters to exercise POST -> GET.
$saved_post=$_POST;$saved_get=$_GET;$saved_server=$_SERVER;$saved_errors=$wp_settings_errors;
foreach(array('success','invalid_nonce','missing_confirmation','unknown') as $case){
 $_POST=$request;
 if('invalid_nonce'===$case)$_POST['readmarker_preset_nonce']='bad';
 if('missing_confirmation'===$case)unset($_POST['readmarker_preset_confirm']);
 if('unknown'===$case)$_POST['readmarker_preset']='unknown';
 $_SERVER['REQUEST_METHOD']='POST';$wp_settings_errors=array();$before=$readmarker_test_option;$readmarker_reset_redirect=null;
 try{$presets->handle();}catch(RuntimeException $e){readmarker_check('Reset redirect'===$e->getMessage(),'Existing redirect adapter captures preset redirect');}
 readmarker_check(0===strpos($readmarker_reset_redirect,admin_url('admin.php?page=readmarker')),'Preset returns to Settings');
 readmarker_check(empty(get_settings_errors('readmarker_presets')),'POST emits no duplicate notice');
 parse_str(parse_url($readmarker_reset_redirect,PHP_URL_QUERY),$_GET);$_POST=array();$_SERVER['REQUEST_METHOD']='GET';$count=$writes;
 $presets->handle();$notices=get_settings_errors('readmarker_presets');
 readmarker_check(count($notices)===1 && $notices[0]['type']===('success'===$case?'success':'error'),'One accurate notice on redirected GET');
 readmarker_check($count===$writes,'GET never reapplies preset');
 readmarker_check('success'===$case?$presets->active_preset()==='balanced':$readmarker_test_option===$before,'Only valid confirmed POST changes settings');
 ob_start();$admin->render_page();$result_html=ob_get_clean();readmarker_check(substr_count($result_html,esc_html($notices[0]['message']))===1,'Settings renders result once');
}
$wp_settings_errors=array();$_GET['readmarker_preset_notice']='bad';$presets->handle();readmarker_check(empty(get_settings_errors('readmarker_presets')),'Invalid result token produces no notice');
$readmarker_test_admin=false;$_POST=$request;$_SERVER['REQUEST_METHOD']='POST';$count=$writes;$readmarker_reset_redirect=null;$presets->handle();
readmarker_check($writes===$count && null===$readmarker_reset_redirect,'Unauthorized handle neither saves nor redirects');$readmarker_test_admin=true;
$_POST=$saved_post;$_GET=$saved_get;$_SERVER=$saved_server;$wp_settings_errors=$saved_errors;
foreach(array('before','selector') as $position){
 $readmarker_test_option=array_merge($base,array('position'=>$position,'target_selector'=>''));ob_start();$admin->render_field(array('key'=>'target_selector'));$field=ob_get_clean();
 readmarker_check((strpos($field,'data-readmarker-selector-settings open')!==false)===('selector'===$position),'Global selector opens disclosure; unused shared selector stays compact');
 readmarker_check(strpos($field,'<details data-readmarker-selector-settings')!==false&&strpos($field,'Shared target for global or per-post')!==false,'Shared selector disclosure available for '.$position);
}
remove_filter('pre_update_option_readflow_settings',$intercept,10);$readmarker_test_option=ReadMarker_Settings::defaults();
echo 'PASS: ',$checks-$start,' preset PHP assertions.',PHP_EOL;
