<?php
/** Admin-only preset actions over the existing settings schema. @package ReadMarker */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadMarker_Presets {
 private $settings;
 public function __construct( ReadMarker_Settings $settings ) { $this->settings = $settings; }
 public static function registry() {
  $base = array( 'enabled'=>true, 'show_time'=>true, 'show_word_count'=>false, 'position'=>'before', 'progress_enabled'=>false, 'progress_display'=>'top_bar' );
  return array(
   'minimal'=>array('name'=>__('Minimal','readmarker'),'description'=>__('Simple reading time with minimal visual distraction.','readmarker'),'values'=>$base),
   'progress'=>array('name'=>__('Progress','readmarker'),'description'=>__('A simple visual bar that shows how far readers have progressed.','readmarker'),'values'=>array_merge($base,array('progress_enabled'=>true))),
   'focus'=>array('name'=>__('Focus','readmarker'),'description'=>__('A compact floating reading widget for a focused reading experience.','readmarker'),'values'=>array_merge($base,array('progress_enabled'=>true,'progress_display'=>'floating_widget'))),
   'detailed'=>array('name'=>__('Detailed','readmarker'),'description'=>__('Show reading time, word count, and remaining reading time together.','readmarker'),'values'=>array_merge($base,array('show_word_count'=>true,'progress_enabled'=>true,'progress_display'=>'time_remaining'))),
   'milestones'=>array('name'=>__('Milestones','readmarker'),'description'=>__('Show progress milestones as readers move through the article.','readmarker'),'values'=>array_merge($base,array('progress_enabled'=>true,'progress_display'=>'reading_milestones'))),
   'balanced'=>array('name'=>__('Balanced','readmarker'),'description'=>__('Reading time, word count, and a subtle progress indicator for a balanced reading experience.','readmarker'),'values'=>array_merge($base,array('show_word_count'=>true,'progress_enabled'=>true))),
  );
 }
 /** First matching configuration, comparing only registry-owned global values. */
 public function active_preset() {
  $current = $this->settings->get();
  foreach ( self::registry() as $id => $preset ) {
   foreach ( $preset['values'] as $key => $value ) {
    if ( ! array_key_exists($key,$current) || $current[$key] !== $value ) { continue 2; }
   }
   return $id;
  }
  return null;
 }
 /** Validates the action, then merges only registry-owned values. No preset state is stored. */
 public function apply( $request, $method ) {
  if ( 'POST' !== $method || ! current_user_can('manage_options') || ! is_array($request) ) { return false; }
  $nonce = $request['readmarker_preset_nonce'] ?? null;
  $id = $request['readmarker_preset'] ?? null;
  $registry = self::registry();
  if ( ! is_string($nonce) || ! wp_verify_nonce($nonce,'readmarker_apply_preset') || ! is_string($id) || ! isset($registry[$id]) || '1' !== ($request['readmarker_preset_confirm'] ?? null) ) { return false; }
  $settings = $this->settings->normalize(array_merge($this->settings->get(),$registry[$id]['values']));
  update_option(ReadMarker_Settings::OPTION,$settings);
  // update_option also returns false for an unchanged configuration; verify the result.
  if ( $this->settings->get() !== $settings ) { return false; }
  return true;
 }
 public function handle() {
  if ( ! current_user_can('manage_options') ) { return; }
  if ( 'POST' === (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '') && isset($_POST['readmarker_preset']) ) {
   // apply() verifies the dedicated nonce and strictly validates the registry ID and confirmation.
   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Structured input is validated by apply(); unknown fields are not stored.
   $request = wp_unslash($_POST);
   $applied = $this->apply($request,'POST');
   $result = $applied ? $request['readmarker_preset'] : 'failed';
   wp_safe_redirect(add_query_arg(array(
    'readmarker_preset_result'=>$result,
    'readmarker_preset_notice'=>wp_create_nonce('readmarker_preset_result_'.$result),
   ),admin_url('admin.php?page=readmarker')));
   exit;
  }
  if ( 'GET' !== (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '') ) { return; }
  $result = isset($_GET['readmarker_preset_result']) && is_string($_GET['readmarker_preset_result']) ? sanitize_text_field(wp_unslash($_GET['readmarker_preset_result'])) : null;
  $nonce = isset($_GET['readmarker_preset_notice']) && is_string($_GET['readmarker_preset_notice']) ? sanitize_text_field(wp_unslash($_GET['readmarker_preset_notice'])) : null;
  $registry = self::registry();
  if ( ! is_string($result) || ! is_string($nonce) || ! wp_verify_nonce($nonce,'readmarker_preset_result_'.$result) ) { return; }
  if ( 'failed' === $result ) {
   add_settings_error('readmarker_presets','readmarker_preset_failed',__('Preset not applied. Confirm the change and try again.','readmarker'),'error');
  } elseif ( isset($registry[$result]) ) {
   /* translators: %s: Name of the applied reading experience preset. */
   add_settings_error('readmarker_presets','readmarker_preset_applied',sprintf(__('%s preset applied.','readmarker'),$registry[$result]['name']),'success');
  }
 }
 public function render() {
  if ( ! current_user_can('manage_options') ) { return; }
  echo '<section class="readmarker-presets"><h2>'.esc_html__('Reading Experience Presets','readmarker').'</h2><p>'.esc_html__('Presets provide a starting configuration. After applying one, customize any available setting in the sections below and save your changes. Active indicates a match with the settings that preset controls.','readmarker').'</p><p>'.esc_html__('Presets replace the listed saved global values, including Custom Selector placement with Before content. WPM, content rules, per-post overrides, position memory, selector text, colors, height, and reading-time style are preserved. Save any other pending edits first.','readmarker').'</p><div class="readmarker-presets__grid">';
  $active = $this->active_preset();
  foreach ( self::registry() as $id=>$preset ) {
   $v=$preset['values'];
   echo '<form class="readmarker-presets__card'.($active===$id?' readmarker-presets__card--active':'').'" method="post" action="'.esc_url(admin_url('admin.php?page=readmarker')).'"><h3>'.esc_html($preset['name']).($active===$id?' <span class="readmarker-presets__active">'.esc_html__('Active','readmarker').'</span>':'').'</h3><p>'.esc_html($preset['description']).'</p><ul>';
   /* translators: %s: Name of the configured progress display. */
   foreach ( array( __('Automatic reading time: enabled and visible','readmarker'), $v['show_word_count'] ? __('Word count: visible','readmarker') : __('Word count: hidden','readmarker'), __('Position: Before content','readmarker'), $v['progress_enabled'] ? __('Progress: enabled','readmarker') : __('Progress: disabled','readmarker'), sprintf(__('Configured progress display: %s','readmarker'),ReadMarker_Settings::progress_displays()[$v['progress_display']]) ) as $line ) { echo '<li>'.esc_html($line).'</li>'; }
   echo '</ul>';
   wp_nonce_field('readmarker_apply_preset','readmarker_preset_nonce',false);
   /* translators: %s: Name of the preset the button applies. */
   echo '<input type="hidden" name="readmarker_preset" value="'.esc_attr($id).'"><p><label><input type="checkbox" name="readmarker_preset_confirm" value="1"'.checked($active,$id,false).' required> '.esc_html__('I confirm that applying this preset will replace the settings listed above.','readmarker').'</label></p><button type="submit" class="button button-secondary">'.esc_html(sprintf(__('Apply %s','readmarker'),$preset['name'])).'</button></form>';
  }
  echo '</div></section>';
 }
}
