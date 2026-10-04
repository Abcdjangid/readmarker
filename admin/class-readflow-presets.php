<?php
/** Admin-only preset actions over the existing settings schema. @package ReadFlow */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadFlow_Presets {
 private $settings;
 public function __construct( ReadFlow_Settings $settings ) { $this->settings = $settings; }
 public static function registry() {
  $base = array( 'enabled'=>true, 'show_time'=>true, 'show_word_count'=>false, 'position'=>'before', 'progress_enabled'=>false, 'progress_display'=>'top_bar' );
  return array(
   'minimal'=>array('name'=>__('Minimal','readflow'),'description'=>__('Simple reading time with minimal visual distraction.','readflow'),'values'=>$base),
   'progress'=>array('name'=>__('Progress','readflow'),'description'=>__('A simple visual bar that shows how far readers have progressed.','readflow'),'values'=>array_merge($base,array('progress_enabled'=>true))),
   'focus'=>array('name'=>__('Focus','readflow'),'description'=>__('A compact floating reading widget for a focused reading experience.','readflow'),'values'=>array_merge($base,array('progress_enabled'=>true,'progress_display'=>'floating_widget'))),
   'detailed'=>array('name'=>__('Detailed','readflow'),'description'=>__('Show reading time, word count, and remaining reading time together.','readflow'),'values'=>array_merge($base,array('show_word_count'=>true,'progress_enabled'=>true,'progress_display'=>'time_remaining'))),
   'milestones'=>array('name'=>__('Milestones','readflow'),'description'=>__('Show progress milestones as readers move through the article.','readflow'),'values'=>array_merge($base,array('progress_enabled'=>true,'progress_display'=>'reading_milestones'))),
   'balanced'=>array('name'=>__('Balanced','readflow'),'description'=>__('Reading time, word count, and a subtle progress indicator for a balanced reading experience.','readflow'),'values'=>array_merge($base,array('show_word_count'=>true,'progress_enabled'=>true))),
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
  $nonce = $request['readflow_preset_nonce'] ?? null;
  $id = $request['readflow_preset'] ?? null;
  $registry = self::registry();
  if ( ! is_string($nonce) || ! wp_verify_nonce($nonce,'readflow_apply_preset') || ! is_string($id) || ! isset($registry[$id]) || '1' !== ($request['readflow_preset_confirm'] ?? null) ) { return false; }
  $settings = $this->settings->normalize(array_merge($this->settings->get(),$registry[$id]['values']));
  update_option(ReadFlow_Settings::OPTION,$settings);
  // update_option also returns false for an unchanged configuration; verify the result.
  if ( $this->settings->get() !== $settings ) { return false; }
  return true;
 }
 public function handle() {
  if ( ! current_user_can('manage_options') ) { return; }
  if ( 'POST' === (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '') && isset($_POST['readflow_preset']) ) {
   // apply() verifies the dedicated nonce and strictly validates the registry ID and confirmation.
   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Structured input is validated by apply(); unknown fields are not stored.
   $request = wp_unslash($_POST);
   $applied = $this->apply($request,'POST');
   $result = $applied ? $request['readflow_preset'] : 'failed';
   wp_safe_redirect(add_query_arg(array(
    'readflow_preset_result'=>$result,
    'readflow_preset_notice'=>wp_create_nonce('readflow_preset_result_'.$result),
   ),admin_url('admin.php?page=readflow')));
   exit;
  }
  if ( 'GET' !== (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '') ) { return; }
  $result = isset($_GET['readflow_preset_result']) && is_string($_GET['readflow_preset_result']) ? sanitize_text_field(wp_unslash($_GET['readflow_preset_result'])) : null;
  $nonce = isset($_GET['readflow_preset_notice']) && is_string($_GET['readflow_preset_notice']) ? sanitize_text_field(wp_unslash($_GET['readflow_preset_notice'])) : null;
  $registry = self::registry();
  if ( ! is_string($result) || ! is_string($nonce) || ! wp_verify_nonce($nonce,'readflow_preset_result_'.$result) ) { return; }
  if ( 'failed' === $result ) {
   add_settings_error('readflow_presets','readflow_preset_failed',__('Preset not applied. Confirm the change and try again.','readflow'),'error');
  } elseif ( isset($registry[$result]) ) {
   /* translators: %s: Name of the applied reading experience preset. */
   add_settings_error('readflow_presets','readflow_preset_applied',sprintf(__('%s preset applied.','readflow'),$registry[$result]['name']),'success');
  }
 }
 public function render() {
  if ( ! current_user_can('manage_options') ) { return; }
  echo '<section class="readflow-presets"><h2>'.esc_html__('Reading Experience Presets','readflow').'</h2><p>'.esc_html__('Presets provide a starting configuration. After applying one, customize any available setting in the sections below and save your changes. Active indicates a match with the settings that preset controls.','readflow').'</p><p>'.esc_html__('Presets replace the listed saved global values, including Custom Selector placement with Before content. WPM, content rules, per-post overrides, position memory, selector text, colors, height, and reading-time style are preserved. Save any other pending edits first.','readflow').'</p><div class="readflow-presets__grid">';
  $active = $this->active_preset();
  foreach ( self::registry() as $id=>$preset ) {
   $v=$preset['values'];
   echo '<form class="readflow-presets__card'.($active===$id?' readflow-presets__card--active':'').'" method="post" action="'.esc_url(admin_url('admin.php?page=readflow')).'"><h3>'.esc_html($preset['name']).($active===$id?' <span class="readflow-presets__active">'.esc_html__('Active','readflow').'</span>':'').'</h3><p>'.esc_html($preset['description']).'</p><ul>';
   /* translators: %s: Name of the configured progress display. */
   foreach ( array( __('Automatic reading time: enabled and visible','readflow'), $v['show_word_count'] ? __('Word count: visible','readflow') : __('Word count: hidden','readflow'), __('Position: Before content','readflow'), $v['progress_enabled'] ? __('Progress: enabled','readflow') : __('Progress: disabled','readflow'), sprintf(__('Configured progress display: %s','readflow'),ReadFlow_Settings::progress_displays()[$v['progress_display']]) ) as $line ) { echo '<li>'.esc_html($line).'</li>'; }
   echo '</ul>';
   wp_nonce_field('readflow_apply_preset','readflow_preset_nonce',false);
   /* translators: %s: Name of the preset the button applies. */
   echo '<input type="hidden" name="readflow_preset" value="'.esc_attr($id).'"><p><label><input type="checkbox" name="readflow_preset_confirm" value="1"'.checked($active,$id,false).' required> '.esc_html__('I confirm that applying this preset will replace the settings listed above.','readflow').'</label></p><button type="submit" class="button button-secondary">'.esc_html(sprintf(__('Apply %s','readflow'),$preset['name'])).'</button></form>';
  }
  echo '</div></section>';
 }
}
