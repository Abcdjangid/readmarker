<?php
/** Accessible presentation controls, separate from article/metadata rendering. @package ReadFlow */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadFlow_Reader_Controls {
 public static function markup( $post_id, $settings = array() ) {
  $config = array();
  foreach ( ReadFlow_Settings::reader_control_choices() as $key=>$choices ) { $config[$key] = isset($settings[$key]) && is_string($settings[$key]) && isset($choices[$settings[$key]]) ? $settings[$key] : ReadFlow_Settings::defaults()[$key]; }
  $id=wp_unique_id('readflow-reader-panel-');
  $html='<div class="readflow-reader-controls" data-readflow-reader-controls data-reader-placement="'.esc_attr($config['reader_controls_placement']).'" data-reader-visibility="'.esc_attr($config['reader_controls_visibility']).'" data-reader-panel-state="'.esc_attr($config['reader_controls_panel']).'" hidden><button type="button" data-reader-toggle aria-expanded="false" aria-controls="'.esc_attr($id).'"><span class="readflow-reader-controls-label">'.esc_html__('Reading Options','readflow').'</span><span class="readflow-reader-controls-summary" data-reader-summary data-reader-text-label="'.esc_attr__('Text','readflow').'" data-reader-width-label="'.esc_attr__('Width','readflow').'" aria-hidden="true"></span></button><section id="'.esc_attr($id).'" data-reader-panel hidden aria-label="'.esc_attr__('Reading Options','readflow').'">';
  foreach(array('textSize'=>__('Text Size','readflow'),'readingWidth'=>__('Reading Width','readflow')) as $key=>$label){
   $html.='<div><span>'.esc_html($label).'</span> <button type="button" data-reader-key="'.esc_attr($key).'" data-reader-step="-1" aria-label="'.esc_attr('textSize'===$key?__('Decrease text size','readflow'):__('Decrease reading width','readflow')).'">-</button> <span data-reader-value="'.esc_attr($key).'">100%</span> <button type="button" data-reader-key="'.esc_attr($key).'" data-reader-step="1" aria-label="'.esc_attr('textSize'===$key?__('Increase text size','readflow'):__('Increase reading width','readflow')).'">+</button></div>';
  }
  return $html.'<button type="button" data-reader-reset aria-label="'.esc_attr__('Reset reading preferences','readflow').'">'.esc_html__('Reset Preferences','readflow').'</button></section></div>';
 }
}
