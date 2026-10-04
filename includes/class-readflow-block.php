<?php
/** Dynamic block adapter; manual eligibility and data are shared with shortcodes. @package ReadFlow */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadFlow_Block {
 private $presentation;
 public function __construct( ReadFlow_Presentation $presentation ) { $this->presentation = $presentation; }
 public function register() {
  // Registration is needed in REST/editor contexts; rendering still enforces manual rules.
  wp_register_script( 'readflow-block-editor', plugins_url( 'blocks/readflow/index.js', READFLOW_PLUGIN_FILE ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n' ), (string) filemtime( READFLOW_PLUGIN_DIR . 'blocks/readflow/index.js' ), true );
  register_block_type( READFLOW_PLUGIN_DIR . 'blocks/readflow', array( 'render_callback' => array( $this, 'render' ) ) );
 }
 public static function normalize( $attributes ) {
  $a = is_array( $attributes ) ? $attributes : array();
  $type = isset( $a['type'] ) && is_string( $a['type'] ) && in_array( $a['type'], array( 'reading_time', 'word_count', 'progress', 'remaining', 'combined' ), true ) ? $a['type'] : 'reading_time';
  $formats = 'remaining' === $type ? array( 'natural', 'short', 'clock', 'detailed' ) : array( 'short', 'long' );
  return array( 'type' => $type, 'format' => isset( $a['format'] ) && is_string( $a['format'] ) && in_array( $a['format'], $formats, true ) ? $a['format'] : $formats[0], 'showLabel' => isset( $a['showLabel'] ) && is_bool( $a['showLabel'] ) ? $a['showLabel'] : true, 'showPercentage' => isset( $a['showPercentage'] ) && is_bool( $a['showPercentage'] ) ? $a['showPercentage'] : true );
 }
 public function render( $attributes = array(), $content = '', $block = null ) {
  // Nested query blocks cannot borrow the outer article's calculation.
  if ( isset( $block->context['postId'] ) && (int) $block->context['postId'] !== (int) get_the_ID() ) { return ''; }
  $a = self::normalize( $attributes );
  $tags = array( 'reading_time' => 'readflow_time', 'word_count' => 'readflow_words', 'progress' => 'readflow_progress', 'remaining' => 'readflow_remaining', 'combined' => 'readflow' );
  $html = $this->presentation->render( array( 'format' => $a['format'], 'label' => $a['showLabel'] ? 'true' : 'false', 'show_percentage' => $a['showPercentage'] ? 'true' : 'false' ), null, $tags[ $a['type'] ], 'combined' === $a['type'] );
  return '' === $html ? '' : '<div class="readflow-block readflow-block--' . esc_attr( str_replace( '_', '-', $a['type'] ) ) . '">' . $html . '</div>';
 }
}
