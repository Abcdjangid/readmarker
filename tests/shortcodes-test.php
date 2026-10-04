<?php
/** Shortcode integration using the existing lightweight WordPress harness. */
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$shortcode_start = $checks;
$frontend = readflow_progress_fixture();
$GLOBALS['readflow_test_meta'] = array();
$shortcodes = new ReadFlow_Shortcodes( new ReadFlow_Settings(), new ReadFlow_Calculator(), new ReadFlow_Renderer() );
$shortcodes->register();
foreach ( array( 'readflow', 'readflow_time', 'readflow_words', 'readflow_progress', 'readflow_remaining' ) as $tag ) {
 readflow_check( shortcode_exists( $tag ), $tag . ' registered' );
 readflow_check( '' !== do_shortcode( '[' . $tag . ']' ), $tag . ' renders current post' );
}
foreach ( array( '[readflow_time]' => '1 min', '[readflow_time format="long"]' => '1 min read', '[readflow_time format="banana"]' => '1 min', '[readflow_words]' => '200 words', '[readflow_words label="false"]' => '200' ) as $input => $expected ) {
 readflow_check( $expected === wp_strip_all_tags( do_shortcode( $input ) ), 'Existing formatter: ' . $input );
}
foreach ( array( array( 'format' => '<script>bad</script>' ), array( 'format' => array() ), array( 'label' => new stdClass() ) ) as $attrs ) {
 readflow_check( false === strpos( $shortcodes->render( $attrs, null, 'readflow_time' ), '<script>' ), 'Malformed attributes safely ignored' );
}
readflow_check( false !== strpos( do_shortcode( '[readflow_progress show_percentage="false"]' ), 'aria-hidden="true" hidden' ), 'Percentage can be hidden' );
foreach ( array( 'natural', 'short', 'clock', 'detailed' ) as $format ) {
 readflow_check( false !== strpos( do_shortcode( '[readflow_remaining format="' . $format . '"]' ), 'data-readflow-format="' . $format . '"' ), 'Remaining format allowlist' );
}
$frontend = readflow_progress_fixture( array( 'enabled' => false ) );
$html = $frontend->filter_content( do_shortcode( $post->post_content . '[readflow_time]' ) );
readflow_check( false !== strpos( $html, '1 min' ) && ! wp_script_is( 'readflow-progress', 'enqueued' ), 'Time alone needs no progress JS when automatic output disabled' );
foreach ( array( 'readflow_progress', 'readflow_remaining' ) as $tag ) {
 $frontend = readflow_progress_fixture( array( 'enabled' => false ) );
 $html = $frontend->filter_content( do_shortcode( $post->post_content . str_repeat( '[' . $tag . ']', 3 ) ) );
 readflow_check( 3 === substr_count( $html, 'data-readflow-inline=' ), 'Multiple inline consumers retained' );
 readflow_check( 1 === substr_count( $html, 'data-readflow-article=' ) && 1 === substr_count( $html, 'data-readflow-progress ' ), 'One article and configuration root' );
 readflow_check( wp_script_is( 'readflow-progress', 'enqueued' ) && ! wp_script_is( 'readflow-position-memory', 'enqueued' ), 'Only necessary shared assets' );
 readflow_check( false !== strpos( $html, 'data-readflow-display-disabled="true"' ), 'Manual consumers do not enable fixed displays' );
 readflow_check( $html === $frontend->filter_content( $html ), 'No duplicate integration' );
}
$frontend = readflow_progress_fixture();
$html = $frontend->filter_content( do_shortcode( $post->post_content . '[readflow]' ) );
readflow_check( 1 === substr_count( $html, 'class="readflow-display ' ) && false === strpos( $html, 'data-readflow-post=' ), 'Generic shortcode suppresses second automatic display' );
foreach ( array( 'is_feed', 'is_preview', 'is_archive' ) as $flag ) {
 readflow_progress_fixture(); $wp_query->$flag = true;
 readflow_check( '' === do_shortcode( '[readflow_time][readflow_progress]' ), 'No manual UI in ' . $flag );
}
readflow_progress_fixture( array( 'post_types' => array() ) );
readflow_check( '' === do_shortcode( '[readflow_time]' ), 'Excluded types remain excluded' );
readflow_progress_fixture();
$post->post_password = 'locked';
readflow_check( '' === do_shortcode( '[readflow_time]' ), 'Locked content protected' );
readflow_progress_fixture();
$post = null;
readflow_check( '' === do_shortcode( '[readflow_time]' ), 'Missing post safely empty' );
readflow_progress_fixture();

$frontend = readflow_progress_fixture( array( 'progress_enabled' => true, 'position_memory_enabled' => true ) );
$readflow_test_meta[456] = array( 'version' => 1, 'behavior' => 'disabled' );
$html = $frontend->filter_content( do_shortcode( $post->post_content . '[readflow_time][readflow_progress][readflow_remaining]' ) );
readflow_check( false !== strpos( $html, '1 min' ) && 2 === substr_count( $html, 'data-readflow-inline=' ), 'Disabled post permits explicit shortcodes' );
readflow_check( false === strpos( $html, 'data-readflow-consumer=' ) && false === strpos( $html, 'data-readflow-memory' ), 'Disabled post does not restore automatic systems' );
$readflow_test_meta[456] = array( 'version' => 1, 'behavior' => 'override', 'reading_time' => 'hide' );
readflow_check( '' === do_shortcode( '[readflow]' ) && false !== strpos( do_shortcode( '[readflow_time]' ), '1 min' ), 'Generic honors visibility; explicit time is intentional' );
$readflow_test_meta = array();
readflow_progress_fixture();
echo 'PASS: ', $checks - $shortcode_start, ' shortcode assertions.', PHP_EOL;
