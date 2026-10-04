<?php
/** Thin WordPress shortcode adapter. @package ReadFlow */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ReadFlow_Shortcodes {
 private $presentation;
 public function __construct( ReadFlow_Settings $settings, ReadFlow_Calculator $calculator, ReadFlow_Renderer $renderer, $presentation = null ) {
  $this->presentation = $presentation ?: new ReadFlow_Presentation( $settings, $calculator, $renderer );
 }
 public function register() {
  foreach ( array( 'readflow', 'readflow_time', 'readflow_words', 'readflow_progress', 'readflow_remaining' ) as $tag ) { add_shortcode( $tag, array( $this, 'render' ) ); }
 }
 public function render( $attributes = array(), $content = null, $tag = 'readflow' ) {
  return $this->presentation->render( $attributes, $content, $tag );
 }
}
