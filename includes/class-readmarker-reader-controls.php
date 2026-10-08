<?php
/** Accessible presentation controls, separate from article/metadata rendering. @package ReadMarker */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadMarker_Reader_Controls {
 public static function markup( $post_id, $settings = array() ) {
  $config = array();
  foreach ( ReadMarker_Settings::reader_control_choices() as $key=>$choices ) { $config[$key] = isset($settings[$key]) && is_string($settings[$key]) && isset($choices[$settings[$key]]) ? $settings[$key] : ReadMarker_Settings::defaults()[$key]; }
  $id=wp_unique_id('readmarker-reader-panel-');
  $html='<div class="readmarker-reader-controls" data-readmarker-reader-controls data-reader-placement="'.esc_attr($config['reader_controls_placement']).'" data-reader-visibility="'.esc_attr($config['reader_controls_visibility']).'" data-reader-panel-state="'.esc_attr($config['reader_controls_panel']).'" hidden><button type="button" data-reader-toggle aria-expanded="false" aria-controls="'.esc_attr($id).'"><span class="readmarker-reader-controls-label">'.esc_html__('Reading Options','readmarker').'</span><span class="readmarker-reader-controls-summary" data-reader-summary data-reader-text-label="'.esc_attr__('Text','readmarker').'" data-reader-width-label="'.esc_attr__('Width','readmarker').'" aria-hidden="true"></span></button><section id="'.esc_attr($id).'" data-reader-panel hidden aria-label="'.esc_attr__('Reading Options','readmarker').'">';
  foreach(array('textSize'=>__('Text Size','readmarker'),'readingWidth'=>__('Reading Width','readmarker')) as $key=>$label){
   $html.='<div><span>'.esc_html($label).'</span> <button type="button" data-reader-key="'.esc_attr($key).'" data-reader-step="-1" aria-label="'.esc_attr('textSize'===$key?__('Decrease text size','readmarker'):__('Decrease reading width','readmarker')).'">-</button> <span data-reader-value="'.esc_attr($key).'">100%</span> <button type="button" data-reader-key="'.esc_attr($key).'" data-reader-step="1" aria-label="'.esc_attr('textSize'===$key?__('Increase text size','readmarker'):__('Increase reading width','readmarker')).'">+</button></div>';
  }
  return $html.'<button type="button" data-reader-reset aria-label="'.esc_attr__('Reset reading preferences','readmarker').'">'.esc_html__('Reset Preferences','readmarker').'</button></section></div>';
 }
}
