<?php
/** Read-only documentation; shortcode execution remains in the presentation service. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadMarker_Shortcodes_Admin {
 private $hook = '';
 public function register_hooks() {
  add_action('admin_menu',array($this,'add_page'),11);
  add_action('admin_enqueue_scripts',array($this,'assets'));
 }
 public function add_page() {
  $this->hook=add_submenu_page('readmarker-overview',__('ReadMarker Shortcodes','readmarker'),__('Shortcodes','readmarker'),'manage_options','readmarker-shortcodes',array($this,'render'));
 }
 /** Documentation metadata only; not a shortcode registry or parser. */
 public static function reference() {
  return array(
   'readmarker'=>array(__('Reading Information','readmarker'),__('Display the ReadMarker reading information using the current effective settings. Reading-time and word-count visibility and style follow those settings.','readmarker'),array()),
   'readmarker_time'=>array(__('Reading Time','readmarker'),__('Display estimated reading time. Short is the default duration; long includes the reading label.','readmarker'),array('format="short"','format="long"')),
   'readmarker_words'=>array(__('Word Count','readmarker'),__('Display the calculated word count. The label is shown by default; use false for the number alone.','readmarker'),array('label="true"','label="false"')),
   'readmarker_progress'=>array(__('Reading Progress','readmarker'),__('Display a compact inline progress bar. Percentage text is shown by default.','readmarker'),array('show_percentage="true"','show_percentage="false"')),
   'readmarker_remaining'=>array(__('Remaining Time','readmarker'),__('Display remaining reading time as progress changes. Natural is the default; short is compact, clock uses a time display, and detailed lists duration units. Completed articles show Finished.','readmarker'),array('format="natural"','format="short"','format="clock"','format="detailed"')),
  );
 }
 public function assets($hook) {
  if(!$this->hook || $hook!==$this->hook || !current_user_can('manage_options'))return;
  foreach(array('css','js') as $type){
   $path='assets/'.$type.'/readmarker-shortcodes.'.$type;
   if('css'===$type)wp_enqueue_style('readmarker-shortcodes',READMARKER_PLUGIN_URL.$path,array(),(string)filemtime(READMARKER_PLUGIN_DIR.$path));
   else wp_enqueue_script('readmarker-shortcodes',READMARKER_PLUGIN_URL.$path,array(),(string)filemtime(READMARKER_PLUGIN_DIR.$path),true);
  }
 }
 /** Static documentation samples, with no post lookup or frontend runtime hooks. */
 private function preview($tag,$attribute) {
  echo '<div class="readmarker-shortcode-preview"><span class="readmarker-shortcode-preview-label">'.esc_html__('Preview','readmarker').'</span><div class="readmarker-shortcode-preview-output">';
  if('readmarker_progress'===$tag){
   echo '<span class="readmarker-shortcode-preview-progress" role="progressbar" aria-label="'.esc_attr__('Reading progress','readmarker').'" aria-valuemin="0" aria-valuemax="100" aria-valuenow="42">';
   if('show_percentage="false"'!==$attribute)echo '<span aria-hidden="true">42%</span>';
   echo '<span class="readmarker-shortcode-preview-track" aria-hidden="true"><span></span></span></span>';
  }else{
   switch($tag){
    case 'readmarker': $text=__('5 min read · 850 words','readmarker');break;
    case 'readmarker_time': $text='format="long"'===$attribute?__('5 min read','readmarker'):__('5 min','readmarker');break;
    case 'readmarker_words': $text='label="false"'===$attribute?'850':__('850 words','readmarker');break;
    case 'readmarker_remaining':
     $samples=array('format="short"'=>__('4 min','readmarker'),'format="clock"'=>'03:24','format="detailed"'=>__('3 min 24 sec','readmarker'));
     $text=$samples[$attribute]??__('3 min 24 sec remaining','readmarker');break;
   }
   echo esc_html($text);
  }
  echo '</div></div>';
 }
 public function render() {
  if(!current_user_can('manage_options'))return;
  echo '<div class="wrap readmarker-shortcodes"><header class="readmarker-shortcodes-header"><h1>'.esc_html__('ReadMarker Shortcodes','readmarker').'</h1><p>'.esc_html__('Add reading information to posts, pages, and other supported WordPress content using these shortcodes.','readmarker').'</p><p><a href="'.esc_url(admin_url('admin.php?page=readmarker')).'">'.esc_html__('ReadMarker Settings','readmarker').'</a></p></header><p class="readmarker-shortcodes-help">'.esc_html__('Place these in a Shortcode block or classic editor content. Explicit shortcodes remain available when automatic display is disabled, subject to content eligibility. Progress and remaining time use the same reading progress. No tracking is performed.','readmarker').'</p><div class="readmarker-shortcodes-grid">';
  foreach(self::reference() as $tag=>$entry){
   echo '<section class="readmarker-shortcodes-card"><h2>'.esc_html($entry[0]).'</h2><p>'.esc_html($entry[1]).'</p><div class="readmarker-shortcode-variants">';
   foreach(array_merge(array(''),$entry[2]) as $attribute){
    $code='['.$tag.(''===$attribute?'':' '.$attribute).']';
    echo '<div class="readmarker-shortcode-entry">';
    /* translators: %s: Complete shortcode syntax to copy. */
    echo '<div class="readmarker-shortcode-example"><div class="readmarker-shortcode-code" tabindex="0" role="region" aria-label="'.esc_attr__('Shortcode example','readmarker').'"><code>'.esc_html($code).'</code></div> <button type="button" class="button" data-readmarker-copy data-copy-label="'.esc_attr__('Copy','readmarker').'" data-copied-label="'.esc_attr__('Copied','readmarker').'" data-failed-label="'.esc_attr__('Select and copy the shortcode manually.','readmarker').'" aria-label="'.esc_attr(sprintf(__('Copy %s','readmarker'),$code)).'" hidden>'.esc_html__('Copy','readmarker').'</button></div>';
    $this->preview($tag,$attribute);
    echo '</div>';
   }
   echo '</div></section>';
  }
  echo '</div></div>';
 }
}
