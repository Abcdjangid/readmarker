<?php
/** Block rendering regression tests in the existing WordPress harness. */
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$block_start = $checks;
$metadata = json_decode( file_get_contents( __DIR__ . '/../blocks/readmarker/block.json' ), true );
readmarker_check( 'readmarker/readmarker' === $metadata['name'] && 3 === $metadata['apiVersion'], 'Namespaced iframe-compatible block metadata' );
readmarker_check( 'reading_time' === $metadata['attributes']['type']['default'] && 'short' === $metadata['attributes']['format']['default'], 'Block defaults' );
readmarker_check( 'readmarker-block-editor' === $metadata['editorScript'] && ! isset( $metadata['script'] ), 'Editor-only script metadata' );
$frontend = readmarker_progress_fixture( array( 'enabled' => false ) );
$block = new ReadMarker_Block( new ReadMarker_Presentation( new ReadMarker_Settings(), new ReadMarker_Calculator(), new ReadMarker_Renderer() ) );
foreach ( array( 'class-wp-block-type.php', 'class-wp-block-type-registry.php', 'class-wp-block-metadata-registry.php', 'blocks.php' ) as $core_file ) {
 if ( is_file( ABSPATH . WPINC . '/' . $core_file ) ) { require_once ABSPATH . WPINC . '/' . $core_file; }
}
$block->register();
$registry = WP_Block_Type_Registry::get_instance();
$canonical_block = $registry->get_registered( 'readmarker/readmarker' );
$legacy_block = $registry->get_registered( 'readflow/readflow' );
readmarker_check( $canonical_block instanceof WP_Block_Type && $legacy_block instanceof WP_Block_Type, 'Both block names register with WordPress' );
readmarker_check( $canonical_block->render_callback === $legacy_block->render_callback && $canonical_block->attributes === $legacy_block->attributes, 'Legacy block shares renderer and attribute schema' );
readmarker_check( 3 === $legacy_block->api_version && false === $legacy_block->supports['inserter'], 'Legacy block keeps API v3 without a second inserter entry' );
foreach ( array( 'reading_time' => '1 min', 'word_count' => '200 words', 'progress' => 'data-readmarker-inline="progress"', 'remaining' => 'data-readmarker-inline="remaining"', 'combined' => '200 words' ) as $type => $expected ) {
 $html = $block->render( array( 'type' => $type ) );
 readmarker_check( false !== strpos( $html, $expected ), $type . ' renders through existing service' );
 readmarker_check( false !== strpos( $html, 'readmarker-block--' . str_replace( '_', '-', $type ) ), 'Scoped type class' );
}
readmarker_check( false !== strpos( $block->render( array( 'type' => 'combined' ) ), '1 min read' ), 'Combined includes reading time' );
readmarker_check( ! wp_script_is( 'readmarker-progress', 'enqueued' ), 'Rendering alone does not enqueue dynamic runtime without article integration' );
foreach ( array( null, array( 'type' => '<script>', 'format' => array(), 'showLabel' => 'false' ), array( 'type' => array() ) ) as $invalid ) {
 $a = ReadMarker_Block::normalize( $invalid );
 readmarker_check( 'reading_time' === $a['type'] && 'short' === $a['format'] && true === $a['showLabel'], 'Malformed attributes fall back safely' );
 readmarker_check( false === strpos( $block->render( $invalid ), '<script>' ), 'No injected markup' );
}
readmarker_check( false === strpos( $block->render( array( 'type' => 'word_count', 'showLabel' => false ) ), '200 words' ), 'Boolean label option' );
readmarker_check( false !== strpos( $block->render( array( 'type' => 'remaining', 'format' => 'clock' ) ), 'data-readmarker-format="clock"' ), 'Remaining format preserved' );
readmarker_check( '' === $block->render( array(), '', (object) array( 'context' => array( 'postId' => 999 ) ) ), 'Different nested post context rejected' );
$readmarker_test_meta[456] = array( 'version' => 1, 'behavior' => 'disabled' );
$html = $frontend->filter_content( $post->post_content . $block->render( array( 'type' => 'progress' ) ) . $block->render( array( 'type' => 'remaining' ) ) . do_shortcode( '[readmarker_progress]' ) );
readmarker_check( 3 === substr_count( $html, 'data-readmarker-inline=' ) && 1 === substr_count( $html, 'data-readmarker-article=' ), 'Mixed blocks and shortcodes share article target' );
readmarker_check( wp_script_is( 'readmarker-progress', 'enqueued' ) && false === strpos( $html, 'data-readmarker-consumer=' ), 'Explicit blocks load shared runtime without automatic displays' );
$readmarker_test_meta = array();
foreach ( array( 'is_feed', 'is_preview', 'is_archive' ) as $flag ) { readmarker_progress_fixture(); $wp_query->$flag = true; readmarker_check( '' === $block->render(), 'Block rejects ' . $flag ); }
readmarker_progress_fixture(); $post = null;
readmarker_check( '' === $block->render(), 'Missing block post safe' );
readmarker_progress_fixture();
echo 'PASS: ', $checks - $block_start, ' block PHP assertions.', PHP_EOL;
