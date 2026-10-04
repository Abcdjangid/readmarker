<?php
/** Block rendering regression tests in the existing WordPress harness. */
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$block_start = $checks;
$metadata = json_decode( file_get_contents( __DIR__ . '/../blocks/readflow/block.json' ), true );
readflow_check( 'readflow/readflow' === $metadata['name'] && 3 === $metadata['apiVersion'], 'Namespaced iframe-compatible block metadata' );
readflow_check( 'reading_time' === $metadata['attributes']['type']['default'] && 'short' === $metadata['attributes']['format']['default'], 'Block defaults' );
readflow_check( 'readflow-block-editor' === $metadata['editorScript'] && ! isset( $metadata['script'] ), 'Editor-only script metadata' );
$frontend = readflow_progress_fixture( array( 'enabled' => false ) );
$block = new ReadFlow_Block( new ReadFlow_Presentation( new ReadFlow_Settings(), new ReadFlow_Calculator(), new ReadFlow_Renderer() ) );
foreach ( array( 'reading_time' => '1 min', 'word_count' => '200 words', 'progress' => 'data-readflow-inline="progress"', 'remaining' => 'data-readflow-inline="remaining"', 'combined' => '200 words' ) as $type => $expected ) {
 $html = $block->render( array( 'type' => $type ) );
 readflow_check( false !== strpos( $html, $expected ), $type . ' renders through existing service' );
 readflow_check( false !== strpos( $html, 'readflow-block--' . str_replace( '_', '-', $type ) ), 'Scoped type class' );
}
readflow_check( false !== strpos( $block->render( array( 'type' => 'combined' ) ), '1 min read' ), 'Combined includes reading time' );
readflow_check( ! wp_script_is( 'readflow-progress', 'enqueued' ), 'Rendering alone does not enqueue dynamic runtime without article integration' );
foreach ( array( null, array( 'type' => '<script>', 'format' => array(), 'showLabel' => 'false' ), array( 'type' => array() ) ) as $invalid ) {
 $a = ReadFlow_Block::normalize( $invalid );
 readflow_check( 'reading_time' === $a['type'] && 'short' === $a['format'] && true === $a['showLabel'], 'Malformed attributes fall back safely' );
 readflow_check( false === strpos( $block->render( $invalid ), '<script>' ), 'No injected markup' );
}
readflow_check( false === strpos( $block->render( array( 'type' => 'word_count', 'showLabel' => false ) ), '200 words' ), 'Boolean label option' );
readflow_check( false !== strpos( $block->render( array( 'type' => 'remaining', 'format' => 'clock' ) ), 'data-readflow-format="clock"' ), 'Remaining format preserved' );
readflow_check( '' === $block->render( array(), '', (object) array( 'context' => array( 'postId' => 999 ) ) ), 'Different nested post context rejected' );
$readflow_test_meta[456] = array( 'version' => 1, 'behavior' => 'disabled' );
$html = $frontend->filter_content( $post->post_content . $block->render( array( 'type' => 'progress' ) ) . $block->render( array( 'type' => 'remaining' ) ) . do_shortcode( '[readflow_progress]' ) );
readflow_check( 3 === substr_count( $html, 'data-readflow-inline=' ) && 1 === substr_count( $html, 'data-readflow-article=' ), 'Mixed blocks and shortcodes share article target' );
readflow_check( wp_script_is( 'readflow-progress', 'enqueued' ) && false === strpos( $html, 'data-readflow-consumer=' ), 'Explicit blocks load shared runtime without automatic displays' );
$readflow_test_meta = array();
foreach ( array( 'is_feed', 'is_preview', 'is_archive' ) as $flag ) { readflow_progress_fixture(); $wp_query->$flag = true; readflow_check( '' === $block->render(), 'Block rejects ' . $flag ); }
readflow_progress_fixture(); $post = null;
readflow_check( '' === $block->render(), 'Missing block post safe' );
readflow_progress_fixture();
echo 'PASS: ', $checks - $block_start, ' block PHP assertions.', PHP_EOL;
