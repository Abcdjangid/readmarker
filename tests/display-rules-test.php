<?php
/** Run php tests/display-rules-test.php; includes the complete preceding PHP suite. @package ReadMarker */
if ( 'cli' !== PHP_SAPI ) { exit; }
if ( ! isset( $checks ) ) { require __DIR__ . '/display-test.php'; return; }
$rules_start = $checks;
$frontend = readmarker_progress_fixture();
$context = ReadMarker_Display_Rules::build_context();
$defaults = ReadMarker_Settings::defaults();
readmarker_check( ReadMarker_Display_Rules::can_display( $context, $defaults )['allowed'], 'Singular post eligible' );
readmarker_check( 456 === $context['post_id'] && 'post' === $context['post_type'] && ! isset( $context['post_content'] ), 'Scalar safe context from WP' );
foreach ( array( 'is_admin' => 'admin', 'is_feed' => 'feed', 'is_rest' => 'rest', 'is_ajax' => 'ajax', 'is_preview' => 'preview', 'is_archive' => 'archive', 'is_excerpt' => 'excerpt', 'password_protected' => 'password_protected' ) as $key => $reason ) {
 $result = ReadMarker_Display_Rules::can_display( array_merge( $context, array( $key => true ) ), $defaults );
 readmarker_check( ! $result['allowed'] && $reason === $result['reason'], 'Rule reason: ' . $reason );
 readmarker_check( ! $result['reading_time'] && ! $result['progress'] && ! $result['position_memory'], 'Denied rules disable all automatic systems' );
}
foreach ( array( array( 'is_singular', false, 'not_singular' ), array( 'is_main_query', false, 'secondary_query' ), array( 'in_the_loop', false, 'secondary_query' ), array( 'queried_post_id', 789, 'secondary_query' ), array( 'post_id', 0, 'missing_post' ), array( 'post_id', -1, 'missing_post' ), array( 'public_post_type', false, 'invalid_post_type' ) ) as $case ) {
 readmarker_check( $case[2] === ReadMarker_Display_Rules::can_display( array_merge( $context, array( $case[0] => $case[1] ) ), $defaults )['reason'], 'Rule reason: ' . $case[2] );
}
readmarker_check( ! ReadMarker_Display_Rules::can_display( null, null )['allowed'], 'Malformed context fails closed' );
foreach ( array( 'post', 'page', 'book' ) as $type ) {
 $typed = array_merge( $context, array( 'post_type' => $type ) );
 readmarker_check( ReadMarker_Display_Rules::can_display( $typed, $settings->normalize( array( 'post_types' => array( $type ) ) ) )['allowed'], 'Selected public type: ' . $type );
 readmarker_check( 'post_type_disabled' === ReadMarker_Display_Rules::can_display( $typed, array_merge( $defaults, array( 'post_types' => array() ) ) )['reason'], 'Unchecked type: ' . $type );
}
foreach ( array( 'attachment', 'revision', 'nav_menu_item' ) as $type ) {
 $wp_post_types[ $type ] = (object) array( 'name' => $type, 'public' => true, 'labels' => (object) array( 'name' => $type ) );
 readmarker_check( ! isset( ReadMarker_Settings::display_post_types()[ $type ] ), 'Unsuitable type hidden even when public: ' . $type );
 readmarker_check( array() === $settings->normalize( array( 'post_types' => array( $type, 'private_note' ) ) )['post_types'], 'Unsuitable type rejected in settings' );
 $frontend = readmarker_progress_fixture( array( 'post_types' => array( $type ), 'progress_enabled' => true, 'position_memory_enabled' => true ), $type );
 $ctx = ReadMarker_Display_Rules::build_context();
 readmarker_check( 'invalid_post_type' === ReadMarker_Display_Rules::can_display( $ctx, array_merge( $defaults, array( 'post_types' => array( $type ) ) ) )['reason'], 'Invalid public/internal type cannot pass pure rules' );
 readmarker_check( $post->post_content === $frontend->filter_content( $post->post_content ) && ! wp_script_is( 'readmarker-progress', 'enqueued' ), 'Internal type no output/assets' );
}
foreach ( array( 'posts' => array( 'post' ), 'pages' => array( 'page' ), 'both' => array( 'post', 'page' ), 'book' => array( 'book' ) ) as $legacy => $expected ) {
 readmarker_check( $expected === $settings->normalize( array( 'post_type' => $legacy ) )['post_types'], 'Legacy read-only adapter: ' . $legacy );
}
readmarker_check( array( 'page' ) === $settings->normalize( array( 'post_type' => 'posts', 'post_types' => array( 'page' ) ) )['post_types'], 'Modern selection takes precedence' );
$manual = array_merge( $defaults, array( 'position' => 'manual' ) );
readmarker_check( 'manual_only' === ReadMarker_Display_Rules::can_display( $context, $manual )['reason'], 'Manual time never automatically inserted' );
$result = ReadMarker_Display_Rules::can_display( $context, array_merge( $manual, array( 'progress_enabled' => true ) ) );
readmarker_check( $result['allowed'] && ! $result['reading_time'] && $result['progress'], 'Manual preserves independent progress feature' );
readmarker_check( '' !== $renderer->render( $calculator->calculate_from_content( 'Article text' ), $manual ), 'Internal manual renderer remains unrestricted' );
$veto = static function ( $allowed, $ctx, $result ) { return false; };
add_filter( 'readmarker_can_display', $veto, 10, 3 );
readmarker_check( 'filtered' === ReadMarker_Display_Rules::evaluate( $context, $defaults )['reason'], 'Extension can veto eligible output' );
$frontend = readmarker_progress_fixture( array( 'progress_enabled' => true, 'position_memory_enabled' => true ) );
readmarker_check( $post->post_content === $frontend->filter_content( $post->post_content ) && ! wp_script_is( 'readmarker-progress', 'enqueued' ) && ! wp_script_is( 'readmarker-position-memory', 'registered' ) && ! wp_style_is( 'readmarker-display', 'enqueued' ), 'Veto gates every asset and feature' );
remove_filter( 'readmarker_can_display', $veto, 10 );
add_filter( 'readflow_can_display', $veto, 10, 3 );
readmarker_check( 'filtered' === ReadMarker_Display_Rules::evaluate( $context, $defaults )['reason'], 'Legacy extension can still veto output' );
add_filter( 'readmarker_can_display', '__return_true' );
readmarker_check( ! ReadMarker_Display_Rules::evaluate( $context, $defaults )['allowed'], 'New hook cannot override legacy veto' );
remove_filter( 'readmarker_can_display', '__return_true' );
remove_filter( 'readflow_can_display', $veto, 10 );
$calls = 0;
$allow = static function () use ( &$calls ) { ++$calls; return true; };
add_filter( 'readmarker_can_display', $allow );
readmarker_check( 'admin' === ReadMarker_Display_Rules::evaluate( array_merge( $context, array( 'is_admin' => true ) ), $defaults )['reason'] && 0 === $calls, 'Extension cannot bypass protected context' );
remove_filter( 'readmarker_can_display', $allow );
$frontend = readmarker_progress_fixture();
ob_start(); $admin->render_page(); $rules_page = ob_get_clean();
readmarker_check( false !== strpos( $rules_page, 'Display Rules' ) && false !== strpos( $rules_page, 'Display on' ) && false !== strpos( $rules_page, '<fieldset>' ), 'Accessible Display Rules section' );
foreach ( array( 'attachment', 'revision', 'nav_menu_item', 'private_note' ) as $type ) {
 readmarker_check( false === strpos( $rules_page, 'value="' . $type . '"' ), 'Internal type absent from controls' );
}
readmarker_check( array( 'post' ) === $defaults['post_types'] && 'top_bar' === $defaults['progress_display'] && false === $defaults['position_memory_enabled'] && 12 === count( ReadMarker_Settings::progress_displays() ), 'Defaults and modes unchanged' );
echo 'PASS: ', $checks - $rules_start, ' centralized display-rule assertions.', PHP_EOL;
