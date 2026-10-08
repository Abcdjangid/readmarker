<?php
/** Run: php tests/progress-test.php (includes all preceding regression tests). @package ReadMarker */
if ( 'cli' !== PHP_SAPI ) {
	exit;
}
if ( ! isset( $checks ) ) {
	require __DIR__ . '/display-test.php';
	return;
}

$progress_checks_start = $checks;
$defaults = ReadMarker_Settings::defaults();
readmarker_check( false === $defaults['progress_enabled'] && '#2563eb' === $defaults['progress_color'] && 3 === $defaults['progress_height'], 'Progress defaults' );
$old_settings = array_diff_key( $defaults, array_flip( array( 'progress_enabled', 'progress_color', 'progress_height' ) ) );
readmarker_check( $defaults === $settings->normalize( $old_settings ), 'Old saved settings gain disabled progress defaults' );
foreach ( array( true, 1, '1', 'true', 'on' ) as $value ) {
	readmarker_check( true === $settings->normalize( array( 'progress_enabled' => $value ) )['progress_enabled'], 'Progress true normalization' );
}
foreach ( array( false, 0, 'false', array(), new stdClass(), 'junk' ) as $value ) {
	readmarker_check( false === $settings->normalize( array( 'progress_enabled' => $value ) )['progress_enabled'], 'Progress false normalization' );
}
foreach ( array( '#abc', '#ABCDEF', '#012345' ) as $color ) {
	readmarker_check( $color === $settings->normalize( array( 'progress_color' => $color ) )['progress_color'], 'WordPress hex color accepted' );
}
foreach ( array( '', 'red', '#12', '#12345678', 'url(evil)', '#123;display:none', array(), null, 123 ) as $color ) {
	readmarker_check( '#2563eb' === $settings->normalize( array( 'progress_color' => $color ) )['progress_color'], 'Invalid color falls back' );
}
foreach ( array( 1, 20, '5', 4.5 ) as $height ) {
	readmarker_check( (int) round( $height ) === $settings->normalize( array( 'progress_height' => $height ) )['progress_height'], 'Height bounded and rounded' );
}
foreach ( array( 0, -1, 20.1, INF, NAN, '2px', array(), null, true ) as $height ) {
	readmarker_check( 3 === $settings->normalize( array( 'progress_height' => $height ) )['progress_height'], 'Invalid height fallback' );
}
readmarker_check( 4 === count( $wp_settings_fields['readmarker']['readmarker_progress'] ) && 1 === count( $wp_settings_fields['readmarker']['readmarker_position'] ), 'Four progress fields and separate position-memory field registered' );
$readmarker_test_option = $defaults;
ob_start();
$admin->render_page();
$page = ob_get_clean();
foreach ( array( 'progress_enabled', 'progress_color', 'progress_height' ) as $field ) {
	readmarker_check( false !== strpos( $page, 'readflow_settings[' . $field . ']' ), 'Progress control: ' . $field );
}
$unsafe_bar = $renderer->progress_bar( array( 'progress_color' => '"><script>alert(1)</script>', 'progress_height' => '1px;display:none' ) );
readmarker_check( false === strpos( $unsafe_bar, '<script' ) && false !== strpos( $unsafe_bar, '#2563eb' ) && false !== strpos( $unsafe_bar, '3px' ), 'Progress markup escapes and validates CSS values' );

// Reset asset registries for each simulated request. No output or DB writes.
if ( ! function_exists( 'readmarker_progress_fixture' ) ) {
function readmarker_progress_fixture( $overrides = array(), $type = 'post', $text = null ) {
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	return readmarker_frontend_fixture( $overrides, $type, $text );
}
}
$frontend = readmarker_progress_fixture();
$content = $post->post_content;
$html = $frontend->filter_content( $content );
readmarker_check( false !== strpos( $html, 'readmarker-display' ), 'Reading-time display preserved' );
readmarker_check( false === strpos( $html, 'data-readmarker-article' ) && ! wp_script_is( 'readmarker-progress', 'registered' ) && ! wp_style_is( 'readmarker-progress', 'registered' ), 'Disabled progress has no marker or asset registration' );

$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true ) );
$html = $frontend->filter_content( $content );
readmarker_check( 1 === substr_count( $html, 'class="readmarker-article-content"' ) && 1 === substr_count( $html, 'data-readmarker-progress ' ), 'One article and one top bar' );
readmarker_check( false !== strpos( $html, 'data-readmarker-article="456">' . $content . '</div>' ), 'Only article HTML inside target' );
readmarker_check( strpos( $html, 'data-readmarker-post="456"' ) < strpos( $html, 'data-readmarker-article="456"' ), 'Time display outside measurement target' );
readmarker_check( wp_script_is( 'readmarker-progress', 'enqueued' ) && wp_style_is( 'readmarker-progress', 'done' ), 'Conditional script and late CSS' );
readmarker_check( array( 'readmarker-remaining-time' ) === $wp_scripts->registered['readmarker-progress']->deps && 1 === $wp_scripts->get_data( 'readmarker-progress', 'group' ), 'Local duration dependency and footer script' );
readmarker_check( $html === $frontend->filter_content( $html ) && $content === $frontend->filter_content( $content ), 'Progress shares duplicate protection' );

foreach ( array( array( 'enabled' => false ), array( 'position' => 'manual' ), array( 'show_time' => false, 'show_word_count' => false ) ) as $override ) {
	$frontend = readmarker_progress_fixture( array_merge( $override, array( 'progress_enabled' => true ) ) );
	$html = $frontend->filter_content( $content );
	readmarker_check( false !== strpos( $html, 'data-readmarker-article' ) && false === strpos( $html, 'data-readmarker-post=' ), 'Progress independent from reading-time controls' );
	readmarker_check( ! wp_style_is( 'readmarker-display', 'enqueued' ), 'Progress alone does not load badge CSS' );
}
$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true, 'position' => 'after' ) );
$html = $frontend->filter_content( $content );
readmarker_check( strpos( $html, 'data-readmarker-post="456"' ) > strpos( $html, 'data-readmarker-article="456"' ), 'After-content display remains outside article' );
foreach ( array( 'page', 'book' ) as $type ) {
	$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true ), $type );
	readmarker_check( $content === $frontend->filter_content( $content ) && ! wp_script_is( 'readmarker-progress', 'enqueued' ), 'Disabled post type has no progress' );
	$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true, 'post_types' => array( $type ) ), $type );
	readmarker_check( false !== strpos( $frontend->filter_content( $content ), 'data-readmarker-article' ), 'Enabled public post type has progress' );
}
foreach ( array( '', '<img src="a.jpg">', '<p></p>' ) as $empty ) {
	$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true ), 'post', $empty );
	readmarker_check( $empty === $frontend->filter_content( $empty ) && ! wp_script_is( 'readmarker-progress', 'enqueued' ), 'Empty article has no progress assets' );
}
foreach ( array( 'is_feed', 'is_preview' ) as $flag ) {
	$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true ) );
	$wp_query->$flag = true;
	readmarker_check( $content === $frontend->filter_content( $content ) && ! wp_script_is( 'readmarker-progress', 'enqueued' ), 'Excluded context has no progress: ' . $flag );
}
readmarker_check( 'top_bar' === $defaults['progress_display'], 'Default progress display' );
foreach ( array( true, false ) as $enabled ) {
	$old = $defaults;
	unset( $old['progress_display'] );
	$old['progress_enabled'] = $enabled;
	$normalized = $settings->normalize( $old );
	readmarker_check( 'top_bar' === $normalized['progress_display'] && $enabled === $normalized['progress_enabled'], 'Existing enable state preserved' );
}
foreach ( array( 'top_bar', 'circular', 'percentage', 'top_bar_percentage' ) as $mode ) {
	$options = $settings->normalize( array( 'progress_enabled' => true, 'progress_display' => $mode ) );
	readmarker_check( $mode === $options['progress_display'], 'Valid mode accepted' );
	$markup = $renderer->progress_bar( $options );
	readmarker_check( false !== strpos( $markup, 'data-readmarker-mode="' . $mode . '"' ), 'Selected mode in markup' );
	readmarker_check( ( in_array( $mode, array( 'top_bar', 'top_bar_percentage' ), true ) ? 1 : 0 ) === substr_count( $markup, 'data-readmarker-consumer="top_bar"' ), 'Only required top bar rendered' );
	readmarker_check( ( 'circular' === $mode ? 1 : 0 ) === substr_count( $markup, 'data-readmarker-consumer="circular"' ), 'Only required circle rendered' );
	readmarker_check( ( in_array( $mode, array( 'percentage', 'top_bar_percentage' ), true ) ? 1 : 0 ) === substr_count( $markup, 'data-readmarker-consumer="percentage"' ), 'Only required percentage rendered' );
	readmarker_check( false === strpos( $markup, 'aria-live' ), 'No live announcements' );
}
foreach ( array( 'invalid', '', 'constructor', array(), null, '\"><script>' ) as $mode ) {
	readmarker_check( 'top_bar' === $settings->normalize( array( 'progress_display' => $mode ) )['progress_display'], 'Invalid mode fallback' );
	readmarker_check( false !== strpos( $renderer->progress_bar( array( 'progress_display' => $mode ) ), 'data-readmarker-mode="top_bar"' ), 'Renderer defensive mode validation' );
}
readmarker_check( false !== strpos( $page, 'readflow_settings[progress_display]' ), 'Display mode field rendered' );
foreach ( array( 0, 0.3, 367.42, 600 ) as $seconds ) {
	$markup = $renderer->progress_bar( $defaults, $seconds );
	preg_match( '/data-readmarker-total-seconds="([^"]+)"/', $markup, $match );
	readmarker_check( isset( $match[1] ) && (float) $match[1] === (float) $seconds, 'Duration transfer retains precision' );
}
foreach ( array( -1, INF, NAN, array(), null, '\"><script>' ) as $invalid ) {
	$markup = $renderer->progress_bar( $defaults, $invalid );
	readmarker_check( false !== strpos( $markup, 'data-readmarker-total-seconds="0"' ) && false === strpos( $markup, '<script>' ), 'Invalid duration transfer is safe' );
}
$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true, 'wpm' => 250 ) );
$markup = $frontend->filter_content( $post->post_content );
readmarker_check( false !== strpos( $markup, 'data-readmarker-total-seconds="48"' ), 'Duration comes from calculator and configured WPM' );
readmarker_check( wp_script_is( 'readmarker-remaining-time', 'registered' ) && array() === $wp_scripts->registered['readmarker-remaining-time']->deps, 'Internal duration module registered without external dependencies' );
$frontend = readmarker_progress_fixture();
$markup = $frontend->filter_content( $post->post_content );
readmarker_check( ! wp_script_is( 'readmarker-remaining-time', 'registered' ) && false === strpos( $markup, 'data-readmarker-total-seconds' ), 'Disabled progress adds no remaining assets/data' );
foreach ( array( 'remaining', 'time_remaining', 'percentage_remaining', 'countdown' ) as $mode ) {
	$normalized = $settings->normalize( array( 'progress_display' => $mode ) );
	readmarker_check( $mode === $normalized['progress_display'], 'New duration mode accepted' );
	$markup = $renderer->progress_bar( $normalized, 600, '10 min read' );
	readmarker_check( 1 === substr_count( $markup, 'data-readmarker-consumer=' ) && false !== strpos( $markup, 'data-readmarker-consumer="' . $mode . '"' ), 'Correct standalone consumer' );
	readmarker_check( false !== strpos( $markup, 'data-readmarker-total-label="10 min read"' ) && false !== strpos( $markup, 'Finished' ), 'Server label and translated completion available' );
	readmarker_check( false === strpos( $markup, 'aria-live' ) && false !== strpos( $markup, 'hidden aria-hidden="true"' ), 'No live announcements and decorative checkmark' );
	$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true, 'progress_display' => $mode ) );
	$markup = $frontend->filter_content( $post->post_content );
	readmarker_check( false !== strpos( $markup, 'data-readmarker-total-label="1 min read"' ), 'Total label comes from existing calculator output' );
	readmarker_check( wp_script_is( 'readmarker-progress', 'enqueued' ) && wp_script_is( 'readmarker-remaining-time', 'registered' ), 'Reuse existing conditional assets' );
	readmarker_check( false !== strpos( $page, 'value="' . $mode . '"' ), 'New mode available in admin selector' );
}
$markup = $renderer->progress_bar( array( 'progress_display' => 'time_remaining' ), 600, '<script>alert(1)</script>' );
readmarker_check( false === strpos( $markup, '<script>' ) && false !== strpos( $markup, '&lt;script&gt;' ), 'Total label safely escaped' );

$widget_options = $settings->normalize( array( 'progress_enabled' => true, 'progress_display' => 'floating_widget' ) );
readmarker_check( 'floating_widget' === $widget_options['progress_display'], 'Floating mode accepted' );
readmarker_check( 12 === count( ReadMarker_Settings::progress_displays() ) && 'top_bar' === ReadMarker_Settings::defaults()['progress_display'], 'Twelve modes, unchanged default' );
readmarker_check( false !== strpos( $page, 'value="floating_widget"' ) && false !== strpos( $page, 'Floating Widget' ), 'Widget admin option available' );
$markup = $renderer->progress_bar( $widget_options, 600, '<script>unsafe</script>' );
foreach ( array( 'readmarker-floating-widget', 'readmarker-floating-widget__percentage', 'readmarker-floating-widget__remaining', 'readmarker-floating-widget__progress', 'readmarker-floating-widget__progress-fill', 'role="progressbar"', 'aria-valuenow="0"', 'aria-valuemin="0"', 'aria-valuemax="100"' ) as $part ) {
	readmarker_check( false !== strpos( $markup, $part ), 'Widget markup: ' . $part );
}
readmarker_check( 1 === substr_count( $markup, 'data-readmarker-consumer=' ) && false === strpos( $markup, 'aria-live' ), 'Single widget and no live region' );
readmarker_check( false === strpos( $markup, '<script>' ) && false !== strpos( $markup, '&lt;script&gt;' ), 'Widget attributes escaped' );
$frontend = readmarker_progress_fixture( $widget_options );
$markup = $frontend->filter_content( $post->post_content );
readmarker_check( 1 === substr_count( $markup, 'data-readmarker-consumer="floating_widget"' ) && 1 === substr_count( $markup, 'data-readmarker-article=' ), 'One widget and existing article wrapper' );
readmarker_check( $markup === $frontend->filter_content( $markup ), 'Widget insertion idempotent' );
readmarker_check( wp_script_is( 'readmarker-progress', 'enqueued' ) && wp_script_is( 'readmarker-remaining-time', 'registered' ), 'Widget reuses conditional assets' );
foreach ( array( array( 'progress_enabled' => false ), array( 'post_types' => array() ) ) as $override ) {
	$frontend = readmarker_progress_fixture( array_merge( $widget_options, $override ) );
	$markup = $frontend->filter_content( $post->post_content );
	readmarker_check( false === strpos( $markup, 'data-readmarker-consumer=' ) && ! wp_script_is( 'readmarker-progress', 'enqueued' ), 'Disabled/ineligible widget has no markup or assets' );
}
foreach ( array_keys( ReadMarker_Settings::progress_displays() ) as $mode ) {
	$options = array_merge( $defaults, array( 'progress_display' => $mode ) );
	readmarker_check( $options === $settings->normalize( $options ), 'Existing option values preserved: ' . $mode );
	readmarker_check( ( 'floating_widget' === $mode ? 1 : 0 ) === substr_count( $renderer->progress_bar( $options ), 'data-readmarker-consumer="floating_widget"' ), 'Widget only in selected mode' );
}
$finish_options = $settings->normalize( array( 'progress_enabled' => true, 'progress_display' => 'estimated_finish_time' ) );
readmarker_check( 'estimated_finish_time' === $finish_options['progress_display'], 'Finish mode accepted' );
readmarker_check( false !== strpos( $page, 'value="estimated_finish_time"' ), 'Finish mode in settings' );
$markup = $renderer->progress_bar( $finish_options, 600, '<script>unsafe</script>' );
foreach ( array( 'data-readmarker-consumer="estimated_finish_time"', 'readmarker-duration-value', 'readmarker-duration-check', 'Finish around %s', 'Finished' ) as $part ) {
 readmarker_check( false !== strpos( $markup, $part ), 'Finish markup: ' . $part );
}
readmarker_check( false === strpos( $markup, '<script>' ) && false !== strpos( $markup, '&lt;script&gt;' ) && false === strpos( $markup, 'aria-live' ), 'Finish escaping and no live announcements' );
$frontend = readmarker_progress_fixture( $finish_options );
$markup = $frontend->filter_content( $post->post_content );
readmarker_check( 1 === substr_count( $markup, 'data-readmarker-consumer="estimated_finish_time"' ) && $markup === $frontend->filter_content( $markup ), 'Finish insertion is idempotent' );
readmarker_check( wp_script_is( 'readmarker-progress', 'enqueued' ), 'Finish uses conditional assets' );
foreach ( array_keys( ReadMarker_Settings::progress_displays() ) as $mode ) {
 readmarker_check( ( 'estimated_finish_time' === $mode ? 1 : 0 ) === substr_count( $renderer->progress_bar( array( 'progress_display' => $mode ) ), 'data-readmarker-consumer="estimated_finish_time"' ), 'Finish only when selected' );
}
$milestone_options = $settings->normalize( array( 'progress_enabled' => true, 'progress_display' => 'reading_milestones' ) );
readmarker_check( 'reading_milestones' === $milestone_options['progress_display'], 'Milestone mode accepted' );
readmarker_check( false !== strpos( $page, 'value="reading_milestones"' ) && false !== strpos( $page, 'Reading Milestones' ), 'Milestone admin option' );
$markup = $renderer->progress_bar( $milestone_options );
foreach ( array( 'readmarker-milestone', 'readmarker-milestone__label', 'readmarker-milestone__progress', 'Getting started', 'Halfway there', 'Almost there', 'Finished', '&quot;' ) as $part ) {
 readmarker_check( false !== strpos( $markup, $part ), 'Milestone markup and escaped labels: ' . $part );
}
readmarker_check( false === strpos( $markup, 'aria-live' ) && 1 === substr_count( $markup, 'data-readmarker-consumer=' ), 'Milestone single consumer without live region' );
$unsafe = $renderer->progress_bar( array_merge( $milestone_options, array( 'progress_color' => '"><script>alert(1)</script>' ) ) );
readmarker_check( false === strpos( $unsafe, '<script>' ), 'Milestone unsafe color rejected' );
$frontend = readmarker_progress_fixture( $milestone_options ); $markup = $frontend->filter_content( $post->post_content );
readmarker_check( 1 === substr_count( $markup, 'data-readmarker-consumer="reading_milestones"' ) && $markup === $frontend->filter_content( $markup ), 'Milestone duplicate prevention' );
readmarker_check( wp_script_is( 'readmarker-progress', 'enqueued' ), 'Milestone shares conditional assets' );
foreach ( array_keys( ReadMarker_Settings::progress_displays() ) as $mode ) {
 readmarker_check( ( 'reading_milestones' === $mode ? 1 : 0 ) === substr_count( $renderer->progress_bar( array( 'progress_display' => $mode ) ), 'data-readmarker-consumer="reading_milestones"' ), 'Milestone markup only for selected mode' );
}
readmarker_check( false === $defaults['position_memory_enabled'], 'Memory disabled by default' );
foreach ( array( true, 1, '1', 'true', 'on' ) as $value ) {
 readmarker_check( true === $settings->normalize( array( 'position_memory_enabled' => $value ) )['position_memory_enabled'], 'Memory boolean true' );
}
foreach ( array( false, 0, 'false', array(), new stdClass(), 'junk', null ) as $value ) {
 readmarker_check( false === $settings->normalize( array( 'position_memory_enabled' => $value ) )['position_memory_enabled'], 'Memory boolean false' );
}
readmarker_check( false !== strpos( $page, 'readflow_settings[position_memory_enabled]' ) && false !== strpos( $page, 'Enable Reading Position Memory' ), 'Memory Settings API field' );
foreach ( array( false, true ) as $progress_enabled ) {
 $frontend = readmarker_progress_fixture( array( 'enabled' => false, 'progress_enabled' => $progress_enabled, 'position_memory_enabled' => true ) );
 $markup = $frontend->filter_content( $post->post_content );
 readmarker_check( 1 === substr_count( $markup, 'data-readmarker-memory ' ) && 1 === substr_count( $markup, 'data-readmarker-article="' . $post->ID . '"' ), 'Memory uses one existing article ID target' );
 readmarker_check( $markup === $frontend->filter_content( $markup ), 'Memory markup idempotent' );
 readmarker_check( wp_script_is( 'readmarker-position-memory', 'registered' ) && in_array( 'readmarker-position-memory', $wp_scripts->registered['readmarker-progress']->deps, true ), 'Memory module conditional dependency' );
 readmarker_check( ( $progress_enabled ? 1 : 0 ) === substr_count( $markup, 'data-readmarker-consumer=' ), 'Memory independent of visible progress' );
 readmarker_check( 2 === substr_count( $markup, '<button type="button"' ) && false !== strpos( $markup, 'aria-label="Continue reading?' ) && false === strpos( $markup, 'aria-live' ), 'Accessible prompt buttons and name' );
}
$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true ) );
$markup = $frontend->filter_content( $post->post_content );
readmarker_check( false === strpos( $markup, 'data-readmarker-memory' ) && ! wp_script_is( 'readmarker-position-memory', 'registered' ), 'Disabled memory no markup or module' );
foreach ( array( 'is_feed', 'is_preview', 'is_archive', 'is_search' ) as $flag ) {
 $frontend = readmarker_progress_fixture( array( 'position_memory_enabled' => true ) );
 $wp_query->$flag = true;
 if ( in_array( $flag, array( 'is_archive', 'is_search' ), true ) ) { $wp_query->is_singular = false; }
 $markup = $frontend->filter_content( $post->post_content );
 readmarker_check( false === strpos( $markup, 'data-readmarker-memory' ) && ! wp_script_is( 'readmarker-position-memory', 'registered' ), 'Memory excluded request: ' . $flag );
}
$frontend = readmarker_progress_fixture( array( 'position_memory_enabled' => true ), 'page' );
readmarker_check( false === strpos( $frontend->filter_content( $post->post_content ), 'data-readmarker-memory' ), 'Memory excludes disabled post type' );
$old = $defaults; unset( $old['position_memory_enabled'] );
readmarker_check( $defaults === $settings->normalize( $old ) && 12 === count( ReadMarker_Settings::progress_displays() ), 'Old settings gain only disabled memory; twelve modes' );
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/readmarker-progress.css' );
readmarker_check( false !== strpos( $css, '.readmarker-position-prompt button:focus-visible' ) && false !== strpos( $css, 'pointer-events: auto' ), 'Prompt focus styles and interaction enabled' );
echo 'PASS: ', $checks - $progress_checks_start, ' PHP progress assertions including Floating Widget.', PHP_EOL;
