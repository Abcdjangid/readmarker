<?php
/** Run: php tests/progress-test.php (includes all preceding regression tests). @package ReadFlow */
if ( 'cli' !== PHP_SAPI ) {
	exit;
}
if ( ! isset( $checks ) ) {
	require __DIR__ . '/display-test.php';
	return;
}

$progress_checks_start = $checks;
$defaults = ReadFlow_Settings::defaults();
readflow_check( false === $defaults['progress_enabled'] && '#2563eb' === $defaults['progress_color'] && 3 === $defaults['progress_height'], 'Progress defaults' );
$old_settings = array_diff_key( $defaults, array_flip( array( 'progress_enabled', 'progress_color', 'progress_height' ) ) );
readflow_check( $defaults === $settings->normalize( $old_settings ), 'Old saved settings gain disabled progress defaults' );
foreach ( array( true, 1, '1', 'true', 'on' ) as $value ) {
	readflow_check( true === $settings->normalize( array( 'progress_enabled' => $value ) )['progress_enabled'], 'Progress true normalization' );
}
foreach ( array( false, 0, 'false', array(), new stdClass(), 'junk' ) as $value ) {
	readflow_check( false === $settings->normalize( array( 'progress_enabled' => $value ) )['progress_enabled'], 'Progress false normalization' );
}
foreach ( array( '#abc', '#ABCDEF', '#012345' ) as $color ) {
	readflow_check( $color === $settings->normalize( array( 'progress_color' => $color ) )['progress_color'], 'WordPress hex color accepted' );
}
foreach ( array( '', 'red', '#12', '#12345678', 'url(evil)', '#123;display:none', array(), null, 123 ) as $color ) {
	readflow_check( '#2563eb' === $settings->normalize( array( 'progress_color' => $color ) )['progress_color'], 'Invalid color falls back' );
}
foreach ( array( 1, 20, '5', 4.5 ) as $height ) {
	readflow_check( (int) round( $height ) === $settings->normalize( array( 'progress_height' => $height ) )['progress_height'], 'Height bounded and rounded' );
}
foreach ( array( 0, -1, 20.1, INF, NAN, '2px', array(), null, true ) as $height ) {
	readflow_check( 3 === $settings->normalize( array( 'progress_height' => $height ) )['progress_height'], 'Invalid height fallback' );
}
readflow_check( 4 === count( $wp_settings_fields['readflow']['readflow_progress'] ) && 1 === count( $wp_settings_fields['readflow']['readflow_position'] ), 'Four progress fields and separate position-memory field registered' );
$readflow_test_option = $defaults;
ob_start();
$admin->render_page();
$page = ob_get_clean();
foreach ( array( 'progress_enabled', 'progress_color', 'progress_height' ) as $field ) {
	readflow_check( false !== strpos( $page, 'readflow_settings[' . $field . ']' ), 'Progress control: ' . $field );
}
$unsafe_bar = $renderer->progress_bar( array( 'progress_color' => '"><script>alert(1)</script>', 'progress_height' => '1px;display:none' ) );
readflow_check( false === strpos( $unsafe_bar, '<script' ) && false !== strpos( $unsafe_bar, '#2563eb' ) && false !== strpos( $unsafe_bar, '3px' ), 'Progress markup escapes and validates CSS values' );

// Reset asset registries for each simulated request. No output or DB writes.
if ( ! function_exists( 'readflow_progress_fixture' ) ) {
function readflow_progress_fixture( $overrides = array(), $type = 'post', $text = null ) {
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	return readflow_frontend_fixture( $overrides, $type, $text );
}
}
$frontend = readflow_progress_fixture();
$content = $post->post_content;
$html = $frontend->filter_content( $content );
readflow_check( false !== strpos( $html, 'readflow-display' ), 'Reading-time display preserved' );
readflow_check( false === strpos( $html, 'data-readflow-article' ) && ! wp_script_is( 'readflow-progress', 'registered' ) && ! wp_style_is( 'readflow-progress', 'registered' ), 'Disabled progress has no marker or asset registration' );

$frontend = readflow_progress_fixture( array( 'progress_enabled' => true ) );
$html = $frontend->filter_content( $content );
readflow_check( 1 === substr_count( $html, 'class="readflow-article-content"' ) && 1 === substr_count( $html, 'data-readflow-progress ' ), 'One article and one top bar' );
readflow_check( false !== strpos( $html, 'data-readflow-article="456">' . $content . '</div>' ), 'Only article HTML inside target' );
readflow_check( strpos( $html, 'data-readflow-post="456"' ) < strpos( $html, 'data-readflow-article="456"' ), 'Time display outside measurement target' );
readflow_check( wp_script_is( 'readflow-progress', 'enqueued' ) && wp_style_is( 'readflow-progress', 'done' ), 'Conditional script and late CSS' );
readflow_check( array( 'readflow-remaining-time' ) === $wp_scripts->registered['readflow-progress']->deps && 1 === $wp_scripts->get_data( 'readflow-progress', 'group' ), 'Local duration dependency and footer script' );
readflow_check( $html === $frontend->filter_content( $html ) && $content === $frontend->filter_content( $content ), 'Progress shares duplicate protection' );

foreach ( array( array( 'enabled' => false ), array( 'position' => 'manual' ), array( 'show_time' => false, 'show_word_count' => false ) ) as $override ) {
	$frontend = readflow_progress_fixture( array_merge( $override, array( 'progress_enabled' => true ) ) );
	$html = $frontend->filter_content( $content );
	readflow_check( false !== strpos( $html, 'data-readflow-article' ) && false === strpos( $html, 'data-readflow-post=' ), 'Progress independent from reading-time controls' );
	readflow_check( ! wp_style_is( 'readflow-display', 'enqueued' ), 'Progress alone does not load badge CSS' );
}
$frontend = readflow_progress_fixture( array( 'progress_enabled' => true, 'position' => 'after' ) );
$html = $frontend->filter_content( $content );
readflow_check( strpos( $html, 'data-readflow-post="456"' ) > strpos( $html, 'data-readflow-article="456"' ), 'After-content display remains outside article' );
foreach ( array( 'page', 'book' ) as $type ) {
	$frontend = readflow_progress_fixture( array( 'progress_enabled' => true ), $type );
	readflow_check( $content === $frontend->filter_content( $content ) && ! wp_script_is( 'readflow-progress', 'enqueued' ), 'Disabled post type has no progress' );
	$frontend = readflow_progress_fixture( array( 'progress_enabled' => true, 'post_types' => array( $type ) ), $type );
	readflow_check( false !== strpos( $frontend->filter_content( $content ), 'data-readflow-article' ), 'Enabled public post type has progress' );
}
foreach ( array( '', '<img src="a.jpg">', '<p></p>' ) as $empty ) {
	$frontend = readflow_progress_fixture( array( 'progress_enabled' => true ), 'post', $empty );
	readflow_check( $empty === $frontend->filter_content( $empty ) && ! wp_script_is( 'readflow-progress', 'enqueued' ), 'Empty article has no progress assets' );
}
foreach ( array( 'is_feed', 'is_preview' ) as $flag ) {
	$frontend = readflow_progress_fixture( array( 'progress_enabled' => true ) );
	$wp_query->$flag = true;
	readflow_check( $content === $frontend->filter_content( $content ) && ! wp_script_is( 'readflow-progress', 'enqueued' ), 'Excluded context has no progress: ' . $flag );
}
readflow_check( 'top_bar' === $defaults['progress_display'], 'Default progress display' );
foreach ( array( true, false ) as $enabled ) {
	$old = $defaults;
	unset( $old['progress_display'] );
	$old['progress_enabled'] = $enabled;
	$normalized = $settings->normalize( $old );
	readflow_check( 'top_bar' === $normalized['progress_display'] && $enabled === $normalized['progress_enabled'], 'Existing enable state preserved' );
}
foreach ( array( 'top_bar', 'circular', 'percentage', 'top_bar_percentage' ) as $mode ) {
	$options = $settings->normalize( array( 'progress_enabled' => true, 'progress_display' => $mode ) );
	readflow_check( $mode === $options['progress_display'], 'Valid mode accepted' );
	$markup = $renderer->progress_bar( $options );
	readflow_check( false !== strpos( $markup, 'data-readflow-mode="' . $mode . '"' ), 'Selected mode in markup' );
	readflow_check( ( in_array( $mode, array( 'top_bar', 'top_bar_percentage' ), true ) ? 1 : 0 ) === substr_count( $markup, 'data-readflow-consumer="top_bar"' ), 'Only required top bar rendered' );
	readflow_check( ( 'circular' === $mode ? 1 : 0 ) === substr_count( $markup, 'data-readflow-consumer="circular"' ), 'Only required circle rendered' );
	readflow_check( ( in_array( $mode, array( 'percentage', 'top_bar_percentage' ), true ) ? 1 : 0 ) === substr_count( $markup, 'data-readflow-consumer="percentage"' ), 'Only required percentage rendered' );
	readflow_check( false === strpos( $markup, 'aria-live' ), 'No live announcements' );
}
foreach ( array( 'invalid', '', 'constructor', array(), null, '\"><script>' ) as $mode ) {
	readflow_check( 'top_bar' === $settings->normalize( array( 'progress_display' => $mode ) )['progress_display'], 'Invalid mode fallback' );
	readflow_check( false !== strpos( $renderer->progress_bar( array( 'progress_display' => $mode ) ), 'data-readflow-mode="top_bar"' ), 'Renderer defensive mode validation' );
}
readflow_check( false !== strpos( $page, 'readflow_settings[progress_display]' ), 'Display mode field rendered' );
foreach ( array( 0, 0.3, 367.42, 600 ) as $seconds ) {
	$markup = $renderer->progress_bar( $defaults, $seconds );
	preg_match( '/data-readflow-total-seconds="([^"]+)"/', $markup, $match );
	readflow_check( isset( $match[1] ) && (float) $match[1] === (float) $seconds, 'Duration transfer retains precision' );
}
foreach ( array( -1, INF, NAN, array(), null, '\"><script>' ) as $invalid ) {
	$markup = $renderer->progress_bar( $defaults, $invalid );
	readflow_check( false !== strpos( $markup, 'data-readflow-total-seconds="0"' ) && false === strpos( $markup, '<script>' ), 'Invalid duration transfer is safe' );
}
$frontend = readflow_progress_fixture( array( 'progress_enabled' => true, 'wpm' => 250 ) );
$markup = $frontend->filter_content( $post->post_content );
readflow_check( false !== strpos( $markup, 'data-readflow-total-seconds="48"' ), 'Duration comes from calculator and configured WPM' );
readflow_check( wp_script_is( 'readflow-remaining-time', 'registered' ) && array() === $wp_scripts->registered['readflow-remaining-time']->deps, 'Internal duration module registered without external dependencies' );
$frontend = readflow_progress_fixture();
$markup = $frontend->filter_content( $post->post_content );
readflow_check( ! wp_script_is( 'readflow-remaining-time', 'registered' ) && false === strpos( $markup, 'data-readflow-total-seconds' ), 'Disabled progress adds no remaining assets/data' );
foreach ( array( 'remaining', 'time_remaining', 'percentage_remaining', 'countdown' ) as $mode ) {
	$normalized = $settings->normalize( array( 'progress_display' => $mode ) );
	readflow_check( $mode === $normalized['progress_display'], 'New duration mode accepted' );
	$markup = $renderer->progress_bar( $normalized, 600, '10 min read' );
	readflow_check( 1 === substr_count( $markup, 'data-readflow-consumer=' ) && false !== strpos( $markup, 'data-readflow-consumer="' . $mode . '"' ), 'Correct standalone consumer' );
	readflow_check( false !== strpos( $markup, 'data-readflow-total-label="10 min read"' ) && false !== strpos( $markup, 'Finished' ), 'Server label and translated completion available' );
	readflow_check( false === strpos( $markup, 'aria-live' ) && false !== strpos( $markup, 'hidden aria-hidden="true"' ), 'No live announcements and decorative checkmark' );
	$frontend = readflow_progress_fixture( array( 'progress_enabled' => true, 'progress_display' => $mode ) );
	$markup = $frontend->filter_content( $post->post_content );
	readflow_check( false !== strpos( $markup, 'data-readflow-total-label="1 min read"' ), 'Total label comes from existing calculator output' );
	readflow_check( wp_script_is( 'readflow-progress', 'enqueued' ) && wp_script_is( 'readflow-remaining-time', 'registered' ), 'Reuse existing conditional assets' );
	readflow_check( false !== strpos( $page, 'value="' . $mode . '"' ), 'New mode available in admin selector' );
}
$markup = $renderer->progress_bar( array( 'progress_display' => 'time_remaining' ), 600, '<script>alert(1)</script>' );
readflow_check( false === strpos( $markup, '<script>' ) && false !== strpos( $markup, '&lt;script&gt;' ), 'Total label safely escaped' );

$widget_options = $settings->normalize( array( 'progress_enabled' => true, 'progress_display' => 'floating_widget' ) );
readflow_check( 'floating_widget' === $widget_options['progress_display'], 'Floating mode accepted' );
readflow_check( 12 === count( ReadFlow_Settings::progress_displays() ) && 'top_bar' === ReadFlow_Settings::defaults()['progress_display'], 'Twelve modes, unchanged default' );
readflow_check( false !== strpos( $page, 'value="floating_widget"' ) && false !== strpos( $page, 'Floating Widget' ), 'Widget admin option available' );
$markup = $renderer->progress_bar( $widget_options, 600, '<script>unsafe</script>' );
foreach ( array( 'readflow-floating-widget', 'readflow-floating-widget__percentage', 'readflow-floating-widget__remaining', 'readflow-floating-widget__progress', 'readflow-floating-widget__progress-fill', 'role="progressbar"', 'aria-valuenow="0"', 'aria-valuemin="0"', 'aria-valuemax="100"' ) as $part ) {
	readflow_check( false !== strpos( $markup, $part ), 'Widget markup: ' . $part );
}
readflow_check( 1 === substr_count( $markup, 'data-readflow-consumer=' ) && false === strpos( $markup, 'aria-live' ), 'Single widget and no live region' );
readflow_check( false === strpos( $markup, '<script>' ) && false !== strpos( $markup, '&lt;script&gt;' ), 'Widget attributes escaped' );
$frontend = readflow_progress_fixture( $widget_options );
$markup = $frontend->filter_content( $post->post_content );
readflow_check( 1 === substr_count( $markup, 'data-readflow-consumer="floating_widget"' ) && 1 === substr_count( $markup, 'data-readflow-article=' ), 'One widget and existing article wrapper' );
readflow_check( $markup === $frontend->filter_content( $markup ), 'Widget insertion idempotent' );
readflow_check( wp_script_is( 'readflow-progress', 'enqueued' ) && wp_script_is( 'readflow-remaining-time', 'registered' ), 'Widget reuses conditional assets' );
foreach ( array( array( 'progress_enabled' => false ), array( 'post_types' => array() ) ) as $override ) {
	$frontend = readflow_progress_fixture( array_merge( $widget_options, $override ) );
	$markup = $frontend->filter_content( $post->post_content );
	readflow_check( false === strpos( $markup, 'data-readflow-consumer=' ) && ! wp_script_is( 'readflow-progress', 'enqueued' ), 'Disabled/ineligible widget has no markup or assets' );
}
foreach ( array_keys( ReadFlow_Settings::progress_displays() ) as $mode ) {
	$options = array_merge( $defaults, array( 'progress_display' => $mode ) );
	readflow_check( $options === $settings->normalize( $options ), 'Existing option values preserved: ' . $mode );
	readflow_check( ( 'floating_widget' === $mode ? 1 : 0 ) === substr_count( $renderer->progress_bar( $options ), 'data-readflow-consumer="floating_widget"' ), 'Widget only in selected mode' );
}
$finish_options = $settings->normalize( array( 'progress_enabled' => true, 'progress_display' => 'estimated_finish_time' ) );
readflow_check( 'estimated_finish_time' === $finish_options['progress_display'], 'Finish mode accepted' );
readflow_check( false !== strpos( $page, 'value="estimated_finish_time"' ), 'Finish mode in settings' );
$markup = $renderer->progress_bar( $finish_options, 600, '<script>unsafe</script>' );
foreach ( array( 'data-readflow-consumer="estimated_finish_time"', 'readflow-duration-value', 'readflow-duration-check', 'Finish around %s', 'Finished' ) as $part ) {
 readflow_check( false !== strpos( $markup, $part ), 'Finish markup: ' . $part );
}
readflow_check( false === strpos( $markup, '<script>' ) && false !== strpos( $markup, '&lt;script&gt;' ) && false === strpos( $markup, 'aria-live' ), 'Finish escaping and no live announcements' );
$frontend = readflow_progress_fixture( $finish_options );
$markup = $frontend->filter_content( $post->post_content );
readflow_check( 1 === substr_count( $markup, 'data-readflow-consumer="estimated_finish_time"' ) && $markup === $frontend->filter_content( $markup ), 'Finish insertion is idempotent' );
readflow_check( wp_script_is( 'readflow-progress', 'enqueued' ), 'Finish uses conditional assets' );
foreach ( array_keys( ReadFlow_Settings::progress_displays() ) as $mode ) {
 readflow_check( ( 'estimated_finish_time' === $mode ? 1 : 0 ) === substr_count( $renderer->progress_bar( array( 'progress_display' => $mode ) ), 'data-readflow-consumer="estimated_finish_time"' ), 'Finish only when selected' );
}
$milestone_options = $settings->normalize( array( 'progress_enabled' => true, 'progress_display' => 'reading_milestones' ) );
readflow_check( 'reading_milestones' === $milestone_options['progress_display'], 'Milestone mode accepted' );
readflow_check( false !== strpos( $page, 'value="reading_milestones"' ) && false !== strpos( $page, 'Reading Milestones' ), 'Milestone admin option' );
$markup = $renderer->progress_bar( $milestone_options );
foreach ( array( 'readflow-milestone', 'readflow-milestone__label', 'readflow-milestone__progress', 'Getting started', 'Halfway there', 'Almost there', 'Finished', '&quot;' ) as $part ) {
 readflow_check( false !== strpos( $markup, $part ), 'Milestone markup and escaped labels: ' . $part );
}
readflow_check( false === strpos( $markup, 'aria-live' ) && 1 === substr_count( $markup, 'data-readflow-consumer=' ), 'Milestone single consumer without live region' );
$unsafe = $renderer->progress_bar( array_merge( $milestone_options, array( 'progress_color' => '"><script>alert(1)</script>' ) ) );
readflow_check( false === strpos( $unsafe, '<script>' ), 'Milestone unsafe color rejected' );
$frontend = readflow_progress_fixture( $milestone_options ); $markup = $frontend->filter_content( $post->post_content );
readflow_check( 1 === substr_count( $markup, 'data-readflow-consumer="reading_milestones"' ) && $markup === $frontend->filter_content( $markup ), 'Milestone duplicate prevention' );
readflow_check( wp_script_is( 'readflow-progress', 'enqueued' ), 'Milestone shares conditional assets' );
foreach ( array_keys( ReadFlow_Settings::progress_displays() ) as $mode ) {
 readflow_check( ( 'reading_milestones' === $mode ? 1 : 0 ) === substr_count( $renderer->progress_bar( array( 'progress_display' => $mode ) ), 'data-readflow-consumer="reading_milestones"' ), 'Milestone markup only for selected mode' );
}
readflow_check( false === $defaults['position_memory_enabled'], 'Memory disabled by default' );
foreach ( array( true, 1, '1', 'true', 'on' ) as $value ) {
 readflow_check( true === $settings->normalize( array( 'position_memory_enabled' => $value ) )['position_memory_enabled'], 'Memory boolean true' );
}
foreach ( array( false, 0, 'false', array(), new stdClass(), 'junk', null ) as $value ) {
 readflow_check( false === $settings->normalize( array( 'position_memory_enabled' => $value ) )['position_memory_enabled'], 'Memory boolean false' );
}
readflow_check( false !== strpos( $page, 'readflow_settings[position_memory_enabled]' ) && false !== strpos( $page, 'Enable Reading Position Memory' ), 'Memory Settings API field' );
foreach ( array( false, true ) as $progress_enabled ) {
 $frontend = readflow_progress_fixture( array( 'enabled' => false, 'progress_enabled' => $progress_enabled, 'position_memory_enabled' => true ) );
 $markup = $frontend->filter_content( $post->post_content );
 readflow_check( 1 === substr_count( $markup, 'data-readflow-memory ' ) && 1 === substr_count( $markup, 'data-readflow-article="' . $post->ID . '"' ), 'Memory uses one existing article ID target' );
 readflow_check( $markup === $frontend->filter_content( $markup ), 'Memory markup idempotent' );
 readflow_check( wp_script_is( 'readflow-position-memory', 'registered' ) && in_array( 'readflow-position-memory', $wp_scripts->registered['readflow-progress']->deps, true ), 'Memory module conditional dependency' );
 readflow_check( ( $progress_enabled ? 1 : 0 ) === substr_count( $markup, 'data-readflow-consumer=' ), 'Memory independent of visible progress' );
 readflow_check( 2 === substr_count( $markup, '<button type="button"' ) && false !== strpos( $markup, 'aria-label="Continue reading?' ) && false === strpos( $markup, 'aria-live' ), 'Accessible prompt buttons and name' );
}
$frontend = readflow_progress_fixture( array( 'progress_enabled' => true ) );
$markup = $frontend->filter_content( $post->post_content );
readflow_check( false === strpos( $markup, 'data-readflow-memory' ) && ! wp_script_is( 'readflow-position-memory', 'registered' ), 'Disabled memory no markup or module' );
foreach ( array( 'is_feed', 'is_preview', 'is_archive', 'is_search' ) as $flag ) {
 $frontend = readflow_progress_fixture( array( 'position_memory_enabled' => true ) );
 $wp_query->$flag = true;
 if ( in_array( $flag, array( 'is_archive', 'is_search' ), true ) ) { $wp_query->is_singular = false; }
 $markup = $frontend->filter_content( $post->post_content );
 readflow_check( false === strpos( $markup, 'data-readflow-memory' ) && ! wp_script_is( 'readflow-position-memory', 'registered' ), 'Memory excluded request: ' . $flag );
}
$frontend = readflow_progress_fixture( array( 'position_memory_enabled' => true ), 'page' );
readflow_check( false === strpos( $frontend->filter_content( $post->post_content ), 'data-readflow-memory' ), 'Memory excludes disabled post type' );
$old = $defaults; unset( $old['position_memory_enabled'] );
readflow_check( $defaults === $settings->normalize( $old ) && 12 === count( ReadFlow_Settings::progress_displays() ), 'Old settings gain only disabled memory; twelve modes' );
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/readflow-progress.css' );
readflow_check( false !== strpos( $css, '.readflow-position-prompt button:focus-visible' ) && false !== strpos( $css, 'pointer-events: auto' ), 'Prompt focus styles and interaction enabled' );
echo 'PASS: ', $checks - $progress_checks_start, ' PHP progress assertions including Floating Widget.', PHP_EOL;
