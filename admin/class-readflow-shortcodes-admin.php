<?php
/** Read-only documentation; shortcode execution remains in the presentation service. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadFlow_Shortcodes_Admin {
 private $hook = '';
 public function register_hooks() {
  add_action('admin_menu',array($this,'add_page'),11);
  add_action('admin_enqueue_scripts',array($this,'assets'));
 }
 public function add_page() {
  $this->hook=add_submenu_page('readflow-overview',__('ReadFlow Shortcodes','readflow'),__('Shortcodes','readflow'),'manage_options','readflow-shortcodes',array($this,'render'));
 }
 /** Documentation metadata only; not a shortcode registry or parser. */
 public static function reference() {
  return array(
   'readflow'=>array(__('Reading Information','readflow'),__('Display the ReadFlow reading information using the current effective settings. Reading-time and word-count visibility and style follow those settings.','readflow'),array()),
   'readflow_time'=>array(__('Reading Time','readflow'),__('Display estimated reading time. Short is the default duration; long includes the reading label.','readflow'),array('format="short"','format="long"')),
   'readflow_words'=>array(__('Word Count','readflow'),__('Display the calculated word count. The label is shown by default; use false for the number alone.','readflow'),array('label="true"','label="false"')),
   'readflow_progress'=>array(__('Reading Progress','readflow'),__('Display a compact inline progress bar. Percentage text is shown by default.','readflow'),array('show_percentage="true"','show_percentage="false"')),
   'readflow_remaining'=>array(__('Remaining Time','readflow'),__('Display remaining reading time as progress changes. Natural is the default; short is compact, clock uses a time display, and detailed lists duration units. Completed articles show Finished.','readflow'),array('format="natural"','format="short"','format="clock"','format="detailed"')),
  );
 }
 public function assets($hook) {
  if(!$this->hook || $hook!==$this->hook || !current_user_can('manage_options'))return;
  foreach(array('css','js') as $type){
   $path='assets/'.$type.'/readflow-shortcodes.'.$type;
   if('css'===$type)wp_enqueue_style('readflow-shortcodes',READFLOW_PLUGIN_URL.$path,array(),(string)filemtime(READFLOW_PLUGIN_DIR.$path));
   else wp_enqueue_script('readflow-shortcodes',READFLOW_PLUGIN_URL.$path,array(),(string)filemtime(READFLOW_PLUGIN_DIR.$path),true);
  }
 }
 /** Static documentation samples, with no post lookup or frontend runtime hooks. */
 private function preview($tag,$attribute) {
  echo '<div class="readflow-shortcode-preview"><span class="readflow-shortcode-preview-label">'.esc_html__('Preview','readflow').'</span><div class="readflow-shortcode-preview-output">';
  if('readflow_progress'===$tag){
   echo '<span class="readflow-shortcode-preview-progress" role="progressbar" aria-label="'.esc_attr__('Reading progress','readflow').'" aria-valuemin="0" aria-valuemax="100" aria-valuenow="42">';
   if('show_percentage="false"'!==$attribute)echo '<span aria-hidden="true">42%</span>';
   echo '<span class="readflow-shortcode-preview-track" aria-hidden="true"><span></span></span></span>';
  }else{
   switch($tag){
    case 'readflow': $text=__('5 min read · 850 words','readflow');break;
    case 'readflow_time': $text='format="long"'===$attribute?__('5 min read','readflow'):__('5 min','readflow');break;
    case 'readflow_words': $text='label="false"'===$attribute?'850':__('850 words','readflow');break;
    case 'readflow_remaining':
     $samples=array('format="short"'=>__('4 min','readflow'),'format="clock"'=>'03:24','format="detailed"'=>__('3 min 24 sec','readflow'));
     $text=$samples[$attribute]??__('3 min 24 sec remaining','readflow');break;
   }
   echo esc_html($text);
  }
  echo '</div></div>';
 }
 public function render() {
  if(!current_user_can('manage_options'))return;
  echo '<div class="wrap readflow-shortcodes"><header class="readflow-shortcodes-header"><h1>'.esc_html__('ReadFlow Shortcodes','readflow').'</h1><p>'.esc_html__('Add reading information to posts, pages, and other supported WordPress content using these shortcodes.','readflow').'</p><p><a href="'.esc_url(admin_url('admin.php?page=readflow')).'">'.esc_html__('ReadFlow Settings','readflow').'</a></p></header><p class="readflow-shortcodes-help">'.esc_html__('Place these in a Shortcode block or classic editor content. Explicit shortcodes remain available when automatic display is disabled, subject to content eligibility. Progress and remaining time use the same reading progress. No tracking is performed.','readflow').'</p><div class="readflow-shortcodes-grid">';
  foreach(self::reference() as $tag=>$entry){
   echo '<section class="readflow-shortcodes-card"><h2>'.esc_html($entry[0]).'</h2><p>'.esc_html($entry[1]).'</p><div class="readflow-shortcode-variants">';
   foreach(array_merge(array(''),$entry[2]) as $attribute){
    $code='['.$tag.(''===$attribute?'':' '.$attribute).']';
    echo '<div class="readflow-shortcode-entry">';
    /* translators: %s: Complete shortcode syntax to copy. */
    echo '<div class="readflow-shortcode-example"><div class="readflow-shortcode-code" tabindex="0" role="region" aria-label="'.esc_attr__('Shortcode example','readflow').'"><code>'.esc_html($code).'</code></div> <button type="button" class="button" data-readflow-copy data-copy-label="'.esc_attr__('Copy','readflow').'" data-copied-label="'.esc_attr__('Copied','readflow').'" data-failed-label="'.esc_attr__('Select and copy the shortcode manually.','readflow').'" aria-label="'.esc_attr(sprintf(__('Copy %s','readflow'),$code)).'" hidden>'.esc_html__('Copy','readflow').'</button></div>';
    $this->preview($tag,$attribute);
    echo '</div>';
   }
   echo '</div></section>';
  }
  echo '</div></div>';
 }
}
