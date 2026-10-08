<?php
/** Read-only configuration presentation. No article calculation or visitor data. @package ReadMarker */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadMarker_Dashboard {
 private $settings;
 private $hook = '';
 public function __construct( ReadMarker_Settings $settings ) { $this->settings = $settings; }
 public function register_hooks() {
  add_action( 'admin_menu', array( $this, 'add_page' ), 9 );
  add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
 }
 public function add_page() {
  $this->hook = add_menu_page( __( 'ReadMarker Overview', 'readmarker' ), 'ReadMarker', 'manage_options', 'readmarker-overview', array( $this, 'render' ), 'dashicons-book-alt' );
  add_submenu_page( 'readmarker-overview', __( 'ReadMarker Overview', 'readmarker' ), __( 'Overview', 'readmarker' ), 'manage_options', 'readmarker-overview', array( $this, 'render' ) );
 }
 public function assets( $hook ) {
  if ( ! $this->hook || $hook !== $this->hook || ! current_user_can( 'manage_options' ) ) { return; }
  wp_enqueue_style( 'readmarker-dashboard', plugins_url( 'assets/css/readmarker-dashboard.css', READMARKER_PLUGIN_FILE ), array(), (string) filemtime( READMARKER_PLUGIN_DIR . 'assets/css/readmarker-dashboard.css' ) );
 }
 /** Labels come from the settings registries; this is configuration, not request eligibility. */
 public function summary() {
  $s = $this->settings->get();
  $types = ReadMarker_Settings::display_post_types();
  $selected = array();
  foreach ( $s['post_types'] as $name ) { if ( isset( $types[ $name ] ) ) { $selected[] = $types[ $name ]->labels->name; } }
  $on = __( 'Enabled', 'readmarker' ); $off = __( 'Disabled', 'readmarker' );
  $visible = __( 'Visible', 'readmarker' ); $hidden = __( 'Hidden', 'readmarker' );
  $modes = ReadMarker_Settings::progress_displays();
  $post_types = $selected ? implode( ', ', $selected ) : __( 'None selected', 'readmarker' );
  return array(
   __( 'ReadMarker Status', 'readmarker' ) => array(
    __( 'Automatic reading time', 'readmarker' ) => $s['enabled'] ? $on : $off,
    __( 'Reading-time style', 'readmarker' ) => ReadMarker_Settings::styles()[ $s['style'] ],
    __( 'Automatic position', 'readmarker' ) => ReadMarker_Settings::positions()[ $s['position'] ],
    __( 'Selected post types', 'readmarker' ) => $post_types,
   ),
   __( 'Reading Time', 'readmarker' ) => array(
    /* translators: %s: Configured reading speed in words per minute. */
    __( 'Reading speed', 'readmarker' ) => sprintf( __( '%s words/min', 'readmarker' ), rtrim( rtrim( number_format_i18n( $s['wpm'], 2 ), '0' ), '.,' ) ),
    __( 'Reading time', 'readmarker' ) => $s['show_time'] ? $visible : $hidden,
    __( 'Word count', 'readmarker' ) => $s['show_word_count'] ? $visible : $hidden,
   ),
   __( 'Progress', 'readmarker' ) => array(
    __( 'Progress status', 'readmarker' ) => $s['progress_enabled'] ? $on : $off,
    __( 'Configured progress display', 'readmarker' ) => $modes[ $s['progress_display'] ],
    __( 'Progress color', 'readmarker' ) => $s['progress_color'],
    /* translators: %s: Top progress bar height in pixels. */
    __( 'Top bar height', 'readmarker' ) => sprintf( __( '%s px', 'readmarker' ), number_format_i18n( $s['progress_height'] ) ),
   ),
   __( 'Reading Experience Features', 'readmarker' ) => array(
    __( 'Position Memory', 'readmarker' ) => $s['position_memory_enabled'] ? $on : $off,
   ),
   __( 'Content Rules', 'readmarker' ) => array(
    __( 'Eligible content types', 'readmarker' ) => $post_types,
    __( 'Automatic display scope', 'readmarker' ) => __( 'Eligible singular frontend content only. Archives, feeds, REST responses, previews, and other protected contexts are excluded.', 'readmarker' ),
    __( 'Per-post controls', 'readmarker' ) => __( 'Available on selected eligible edit screens. Individual content may disable or override global display settings.', 'readmarker' ),
   ),
  );
 }
 public function render() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  echo '<div class="wrap readmarker-dashboard"><p class="readmarker-dashboard__eyebrow">ReadMarker</p><h1>' . esc_html__( 'Reading Experience', 'readmarker' ) . '</h1><p>' . esc_html__( 'Your current global configuration. This overview collects no analytics or visitor activity. Per-post controls and manual placements may differ.', 'readmarker' ) . '</p><nav class="readmarker-dashboard__actions" aria-label="' . esc_attr__( 'ReadMarker quick actions', 'readmarker' ) . '">';
  foreach ( array( admin_url( 'admin.php?page=readmarker' ) => __( 'Configure Settings', 'readmarker' ), admin_url( 'admin.php?page=readmarker#readmarker-rules' ) => __( 'Display Rules', 'readmarker' ), plugins_url( 'readme.txt', READMARKER_PLUGIN_FILE ) => __( 'Read documentation', 'readmarker' ) ) as $url => $label ) {
   echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
  }
  echo '</nav><div class="readmarker-dashboard__grid">';
  foreach ( $this->summary() as $heading => $rows ) {
   echo '<section class="readmarker-dashboard__card"><h2>' . esc_html( $heading ) . '</h2><dl>';
   foreach ( $rows as $label => $value ) { echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>'; }
   echo '</dl>';
   if ( __( 'Reading Experience Features', 'readmarker' ) === $heading ) {
    echo '<p>' . esc_html__( 'Remaining Time, Countdown, Floating Widget, Estimated Finish Time, and Reading Milestones are display-mode choices, not separate switches. The configured mode is shown in the Progress card. Display Rules and placement settings determine where output can appear.', 'readmarker' ) . '</p>';
   }
   echo '</section>';
  }
  echo '</div><p>' . esc_html__( 'ReadMarker is completely free. No accounts, telemetry, remote reporting, or visitor analytics. Optional position memory stays in the reader\'s browser.', 'readmarker' ) . '</p></div>';
 }
}
