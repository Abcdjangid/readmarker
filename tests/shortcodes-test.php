<?php
/** Shortcode integration using the existing lightweight WordPress harness. */
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$shortcode_start = $checks;
$frontend = readmarker_progress_fixture();
$GLOBALS['readmarker_test_meta'] = array();
$shortcodes = new ReadMarker_Shortcodes( new ReadMarker_Settings(), new ReadMarker_Calculator(), new ReadMarker_Renderer() );
$shortcodes->register();
foreach ( array( '', '_time', '_words', '_progress', '_remaining' ) as $suffix ) {
 readmarker_check( shortcode_exists( 'readflow' . $suffix ), 'Legacy shortcode remains registered' );
 foreach ( array( '', ' format="long"', ' format="clock"', ' label="false"', ' show_percentage="false"' ) as $attributes ) {
  readmarker_progress_fixture();
  $legacy_output = do_shortcode( '[readflow' . $suffix . $attributes . ']' );
  readmarker_progress_fixture();
  readmarker_check( $legacy_output === do_shortcode( '[readmarker' . $suffix . $attributes . ']' ), 'Legacy shortcode shares canonical output and attribute handling' );
 }
}
foreach ( array( 'readmarker', 'readmarker_time', 'readmarker_words', 'readmarker_progress', 'readmarker_remaining' ) as $tag ) {
 readmarker_check( shortcode_exists( $tag ), $tag . ' registered' );
 readmarker_check( '' !== do_shortcode( '[' . $tag . ']' ), $tag . ' renders current post' );
}
foreach ( array( '[readmarker_time]' => '1 min', '[readmarker_time format="long"]' => '1 min read', '[readmarker_time format="banana"]' => '1 min', '[readmarker_words]' => '200 words', '[readmarker_words label="false"]' => '200' ) as $input => $expected ) {
 readmarker_check( $expected === wp_strip_all_tags( do_shortcode( $input ) ), 'Existing formatter: ' . $input );
}
foreach ( array( array( 'format' => '<script>bad</script>' ), array( 'format' => array() ), array( 'label' => new stdClass() ) ) as $attrs ) {
 readmarker_check( false === strpos( $shortcodes->render( $attrs, null, 'readmarker_time' ), '<script>' ), 'Malformed attributes safely ignored' );
}
readmarker_check( false !== strpos( do_shortcode( '[readmarker_progress show_percentage="false"]' ), 'aria-hidden="true" hidden' ), 'Percentage can be hidden' );
foreach ( array( 'natural', 'short', 'clock', 'detailed' ) as $format ) {
 readmarker_check( false !== strpos( do_shortcode( '[readmarker_remaining format="' . $format . '"]' ), 'data-readmarker-format="' . $format . '"' ), 'Remaining format allowlist' );
}
$frontend = readmarker_progress_fixture( array( 'enabled' => false ) );
$html = $frontend->filter_content( do_shortcode( $post->post_content . '[readmarker_time]' ) );
readmarker_check( false !== strpos( $html, '1 min' ) && ! wp_script_is( 'readmarker-progress', 'enqueued' ), 'Time alone needs no progress JS when automatic output disabled' );
foreach ( array( 'readmarker_progress', 'readmarker_remaining' ) as $tag ) {
 $frontend = readmarker_progress_fixture( array( 'enabled' => false ) );
 $html = $frontend->filter_content( do_shortcode( $post->post_content . str_repeat( '[' . $tag . ']', 3 ) ) );
 readmarker_check( 3 === substr_count( $html, 'data-readmarker-inline=' ), 'Multiple inline consumers retained' );
 readmarker_check( 1 === substr_count( $html, 'data-readmarker-article=' ) && 1 === substr_count( $html, 'data-readmarker-progress ' ), 'One article and configuration root' );
 readmarker_check( wp_script_is( 'readmarker-progress', 'enqueued' ) && ! wp_script_is( 'readmarker-position-memory', 'enqueued' ), 'Only necessary shared assets' );
 readmarker_check( false !== strpos( $html, 'data-readmarker-display-disabled="true"' ), 'Manual consumers do not enable fixed displays' );
 readmarker_check( $html === $frontend->filter_content( $html ), 'No duplicate integration' );
}
$frontend = readmarker_progress_fixture();
$html = $frontend->filter_content( do_shortcode( $post->post_content . '[readmarker]' ) );
readmarker_check( 1 === substr_count( $html, 'class="readmarker-display ' ) && false === strpos( $html, 'data-readmarker-post=' ), 'Generic shortcode suppresses second automatic display' );
foreach ( array( 'is_feed', 'is_preview', 'is_archive' ) as $flag ) {
 readmarker_progress_fixture(); $wp_query->$flag = true;
 readmarker_check( '' === do_shortcode( '[readmarker_time][readmarker_progress]' ), 'No manual UI in ' . $flag );
}
readmarker_progress_fixture( array( 'post_types' => array() ) );
readmarker_check( '' === do_shortcode( '[readmarker_time]' ), 'Excluded types remain excluded' );
readmarker_progress_fixture();
$post->post_password = 'locked';
readmarker_check( '' === do_shortcode( '[readmarker_time]' ), 'Locked content protected' );
readmarker_progress_fixture();
$post = null;
readmarker_check( '' === do_shortcode( '[readmarker_time]' ), 'Missing post safely empty' );
readmarker_progress_fixture();

$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true, 'position_memory_enabled' => true ) );
$readmarker_test_meta[456] = array( 'version' => 1, 'behavior' => 'disabled' );
$html = $frontend->filter_content( do_shortcode( $post->post_content . '[readmarker_time][readmarker_progress][readmarker_remaining]' ) );
readmarker_check( false !== strpos( $html, '1 min' ) && 2 === substr_count( $html, 'data-readmarker-inline=' ), 'Disabled post permits explicit shortcodes' );
readmarker_check( false === strpos( $html, 'data-readmarker-consumer=' ) && false === strpos( $html, 'data-readmarker-memory' ), 'Disabled post does not restore automatic systems' );
$readmarker_test_meta[456] = array( 'version' => 1, 'behavior' => 'override', 'reading_time' => 'hide' );
readmarker_check( '' === do_shortcode( '[readmarker]' ) && false !== strpos( do_shortcode( '[readmarker_time]' ), '1 min' ), 'Generic honors visibility; explicit time is intentional' );
$readmarker_test_meta = array();
readmarker_progress_fixture();
echo 'PASS: ', $checks - $shortcode_start, ' shortcode assertions.', PHP_EOL;
