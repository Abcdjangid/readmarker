<?php
/** Thin WordPress shortcode adapter. @package ReadMarker */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadMarker_Shortcodes {
 private $presentation;
 public function __construct( ReadMarker_Settings $settings, ReadMarker_Calculator $calculator, ReadMarker_Renderer $renderer, $presentation = null ) {
  $this->presentation = $presentation ?: new ReadMarker_Presentation( $settings, $calculator, $renderer );
 }
 public function register() {
  foreach ( array( '', '_time', '_words', '_progress', '_remaining' ) as $suffix ) {
   add_shortcode( 'readmarker' . $suffix, array( $this, 'render' ) );
   // Keep saved pre-rename content working through the same presentation path.
   add_shortcode( 'readflow' . $suffix, array( $this, 'render' ) );
  }
 }
 public function render( $attributes = array(), $content = null, $tag = 'readmarker' ) {
  if ( in_array( $tag, array( 'readflow', 'readflow_time', 'readflow_words', 'readflow_progress', 'readflow_remaining' ), true ) ) {
   $tag = 'readmarker' . substr( $tag, strlen( 'readflow' ) );
  }
  return $this->presentation->render( $attributes, $content, $tag );
 }
}
