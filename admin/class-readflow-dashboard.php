<?php
/** Read-only configuration presentation. No article calculation or visitor data. @package ReadFlow */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadFlow_Dashboard {
 private $settings;
 private $hook = '';
 public function __construct( ReadFlow_Settings $settings ) { $this->settings = $settings; }
 public function register_hooks() {
  add_action( 'admin_menu', array( $this, 'add_page' ), 9 );
  add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
 }
 public function add_page() {
  $this->hook = add_menu_page( __( 'ReadFlow Overview', 'readflow' ), 'ReadFlow', 'manage_options', 'readflow-overview', array( $this, 'render' ), 'dashicons-book-alt' );
  add_submenu_page( 'readflow-overview', __( 'ReadFlow Overview', 'readflow' ), __( 'Overview', 'readflow' ), 'manage_options', 'readflow-overview', array( $this, 'render' ) );
 }
 public function assets( $hook ) {
  if ( ! $this->hook || $hook !== $this->hook || ! current_user_can( 'manage_options' ) ) { return; }
  wp_enqueue_style( 'readflow-dashboard', plugins_url( 'assets/css/readflow-dashboard.css', READFLOW_PLUGIN_FILE ), array(), (string) filemtime( READFLOW_PLUGIN_DIR . 'assets/css/readflow-dashboard.css' ) );
 }
 /** Labels come from the settings registries; this is configuration, not request eligibility. */
 public function summary() {
  $s = $this->settings->get();
  $types = ReadFlow_Settings::display_post_types();
  $selected = array();
  foreach ( $s['post_types'] as $name ) { if ( isset( $types[ $name ] ) ) { $selected[] = $types[ $name ]->labels->name; } }
  $on = __( 'Enabled', 'readflow' ); $off = __( 'Disabled', 'readflow' );
  $visible = __( 'Visible', 'readflow' ); $hidden = __( 'Hidden', 'readflow' );
  $modes = ReadFlow_Settings::progress_displays();
  $post_types = $selected ? implode( ', ', $selected ) : __( 'None selected', 'readflow' );
  return array(
   __( 'ReadFlow Status', 'readflow' ) => array(
    __( 'Automatic reading time', 'readflow' ) => $s['enabled'] ? $on : $off,
    __( 'Reading-time style', 'readflow' ) => ReadFlow_Settings::styles()[ $s['style'] ],
    __( 'Automatic position', 'readflow' ) => ReadFlow_Settings::positions()[ $s['position'] ],
    __( 'Selected post types', 'readflow' ) => $post_types,
   ),
   __( 'Reading Time', 'readflow' ) => array(
    /* translators: %s: Configured reading speed in words per minute. */
    __( 'Reading speed', 'readflow' ) => sprintf( __( '%s words/min', 'readflow' ), rtrim( rtrim( number_format_i18n( $s['wpm'], 2 ), '0' ), '.,' ) ),
    __( 'Reading time', 'readflow' ) => $s['show_time'] ? $visible : $hidden,
    __( 'Word count', 'readflow' ) => $s['show_word_count'] ? $visible : $hidden,
   ),
   __( 'Progress', 'readflow' ) => array(
    __( 'Progress status', 'readflow' ) => $s['progress_enabled'] ? $on : $off,
    __( 'Configured progress display', 'readflow' ) => $modes[ $s['progress_display'] ],
    __( 'Progress color', 'readflow' ) => $s['progress_color'],
    /* translators: %s: Top progress bar height in pixels. */
    __( 'Top bar height', 'readflow' ) => sprintf( __( '%s px', 'readflow' ), number_format_i18n( $s['progress_height'] ) ),
   ),
   __( 'Reading Experience Features', 'readflow' ) => array(
    __( 'Position Memory', 'readflow' ) => $s['position_memory_enabled'] ? $on : $off,
   ),
   __( 'Content Rules', 'readflow' ) => array(
    __( 'Eligible content types', 'readflow' ) => $post_types,
    __( 'Automatic display scope', 'readflow' ) => __( 'Eligible singular frontend content only. Archives, feeds, REST responses, previews, and other protected contexts are excluded.', 'readflow' ),
    __( 'Per-post controls', 'readflow' ) => __( 'Available on selected eligible edit screens. Individual content may disable or override global display settings.', 'readflow' ),
   ),
  );
 }
 public function render() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  echo '<div class="wrap readflow-dashboard"><p class="readflow-dashboard__eyebrow">ReadFlow</p><h1>' . esc_html__( 'Reading Experience', 'readflow' ) . '</h1><p>' . esc_html__( 'Your current global configuration. This overview collects no analytics or visitor activity. Per-post controls and manual placements may differ.', 'readflow' ) . '</p><nav class="readflow-dashboard__actions" aria-label="' . esc_attr__( 'ReadFlow quick actions', 'readflow' ) . '">';
  foreach ( array( admin_url( 'admin.php?page=readflow' ) => __( 'Configure Settings', 'readflow' ), admin_url( 'admin.php?page=readflow#readflow-rules' ) => __( 'Display Rules', 'readflow' ), plugins_url( 'readme.txt', READFLOW_PLUGIN_FILE ) => __( 'Read documentation', 'readflow' ) ) as $url => $label ) {
   echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
  }
  echo '</nav><div class="readflow-dashboard__grid">';
  foreach ( $this->summary() as $heading => $rows ) {
   echo '<section class="readflow-dashboard__card"><h2>' . esc_html( $heading ) . '</h2><dl>';
   foreach ( $rows as $label => $value ) { echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>'; }
   echo '</dl>';
   if ( __( 'Reading Experience Features', 'readflow' ) === $heading ) {
    echo '<p>' . esc_html__( 'Remaining Time, Countdown, Floating Widget, Estimated Finish Time, and Reading Milestones are display-mode choices, not separate switches. The configured mode is shown in the Progress card. Display Rules and placement settings determine where output can appear.', 'readflow' ) . '</p>';
   }
   echo '</section>';
  }
  echo '</div><p>' . esc_html__( 'ReadFlow is completely free. No accounts, telemetry, remote reporting, or visitor analytics. Optional position memory stays in the reader\'s browser.', 'readflow' ) . '</p></div>';
 }
}
