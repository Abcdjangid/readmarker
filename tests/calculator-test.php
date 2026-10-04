<?php
/**
 * Run: php tests/calculator-test.php
 *
 * CLI-only integration/unit harness using the surrounding WordPress core's
 * hooks, post API, in-memory object cache, formatting and shortcode registry.
 * Does not load wp-config.php, connect to a database, activate plugins or write
 * files. Translation/URL adapters are deterministic test doubles. The database
 * double only returns "missing post"; existing posts are in-memory fixtures.
 * This does not claim to test live activation or translated locales.
 *
 * @package ReadFlow
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

error_reporting( E_ALL );
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_DEBUG', false );
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'KB_IN_BYTES', 1024 );
define( 'WP_PLUGIN_DIR', dirname( __DIR__, 2 ) );
define( 'WPMU_PLUGIN_DIR', ABSPATH . 'wp-content/mu-plugins' );

$wp_plugin_paths = array();
foreach ( array( 'load.php', 'plugin.php', 'functions.php', 'formatting.php', 'class-wp-error.php', 'class-wp-post.php', 'post.php', 'cache.php', 'shortcodes.php' ) as $core_file ) {
	require_once ABSPATH . WPINC . '/' . $core_file;
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function __( $text, $domain = 'default' ) {
	return $text;
}

function plugins_url( $path = '', $plugin = '' ) {
	return 'https://example.test/wp-content/plugins/readflow/' . ltrim( $path, '/' );
}

$wpdb = new class() {
	public $posts = 'fixture_posts';
	public $postmeta = 'fixture_postmeta';
	public $lookups = 0;
	public function prepare( $query, $id ) {
		return sprintf( $query, $id );
	}
	public function get_row( $query ) {
		++$this->lookups;
		return null;
	}
};
wp_cache_init();

$checks = 0;
function readflow_check( $condition, $label ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$checks;
}

ob_start();
require dirname( __DIR__ ) . '/readflow.php';
readflow_check( ! class_exists( 'ReadFlow_Calculator', false ), 'Deferred service loading' );
do_action( 'plugins_loaded' );
ReadFlow_Plugin::init();
readflow_check( class_exists( 'ReadFlow_Calculator', false ), 'Calculator loaded by bootstrap' );
readflow_check( class_exists( 'ReadFlow_Time_Formatter', false ), 'Formatter loaded by bootstrap' );
readflow_check( 1 === did_action( 'readflow_loaded' ), 'Initialization occurs once' );
readflow_check( '' === ob_get_clean(), 'Silent bootstrap' );
$header = get_plugin_data( READFLOW_PLUGIN_FILE, false, false );
readflow_check( 'ReadFlow' === $header['Name'] && '0.1.0' === $header['Version'], 'WordPress recognizes plugin header' );

add_shortcode( 'caption', static function () { throw new RuntimeException( 'Shortcodes must not execute' ); } );
add_shortcode( 'gallery', static function () { throw new RuntimeException( 'Shortcodes must not execute' ); } );
add_filter( 'the_content', static function () { throw new RuntimeException( 'Content filters must not execute' ); } );
$calculator = new ReadFlow_Calculator();

// Each fixture asserts every result field, including optional badge labels.
$cases = array(
	array( 'Empty', '', 0, 0, 0, '0 sec' ),
	array( '100 words', str_repeat( 'word ', 100 ), 100, 0, 30, '30 sec' ),
	array( '200 words', str_repeat( 'word ', 200 ), 200, 0, 60, '1 min' ),
	array( '1000 words', str_repeat( 'word ', 1000 ), 1000, 0, 300, '5 min' ),
	array( 'HTML heavy', '<div><h2>Article title</h2><p>Hello <strong>bright</strong> world.</p><table><tr><th>Name</th><td>Alice</td></tr></table></div>', 7, 0, 2.1, '3 sec' ),
	array( 'Links', '<a href="https://example.test/path?q=hidden" title="not words">Read this link</a>', 3, 0, 0.9, '1 sec' ),
	array( 'Headings', '<h1>First title</h1><h2>Second heading</h2>', 4, 0, 1.2, '2 sec' ),
	array( 'Lists', '<ul><li>First item</li><li>Second item</li></ul><ol><li>Third</li></ol>', 5, 0, 1.5, '2 sec' ),
	array( 'Blockquotes', '<blockquote><p>Quoted words here</p></blockquote><p>After quote</p>', 5, 0, 1.5, '2 sec' ),
	array( 'Images', '<p>Two pictures</p><img src="a.jpg" alt="not counted"><IMG src="b.jpg" />', 2, 2, 0.6, '1 sec' ),
	array( 'Image only', '<img src="a.jpg">', 0, 1, 0, '0 sec' ),
	array( 'Very short', 'Hello', 1, 0, 0.3, '1 sec' ),
	array( '45 seconds', str_repeat( 'word ', 150 ), 150, 0, 45, '45 sec' ),
	array( 'Long', str_repeat( 'word ', 100000 ), 100000, 0, 30000, '8 hr 20 min' ),
	array( '72 minutes', str_repeat( 'word ', 14400 ), 14400, 0, 4320, '1 hr 12 min' ),
	array( 'Ceiling minutes', str_repeat( 'word ', 1240 ), 1240, 0, 372, '7 min' ),
	array( 'Gutenberg', '<!-- wp:heading --><h2>Block title</h2><!-- /wp:heading --><!-- wp:image {"id":9} --><figure class="wp-block-image"><img src="a.jpg"><figcaption>Visible caption</figcaption></figure><!-- /wp:image -->', 4, 1, 1.2, '2 sec' ),
	array( 'Excluded elements', 'Before<script>bad words <img src="x"></script><style>.foo { color:red }</style><noscript>hidden words<img src="y"></noscript>after<!-- invisible <img src="z"> -->', 2, 0, 0.6, '1 sec' ),
	array( 'Quoted angle brackets', '<p title="a > b">Hello</p><img title="a > b" src="a.jpg"><p>world</p>', 2, 1, 0.6, '1 sec' ),
	array( 'Entities', 'Tom&nbsp;&amp;&nbsp;Jerry &quot;hello&quot; caf&eacute; &#8212; &#128512;', 4, 0, 1.2, '2 sec' ),
	array( 'Literal escaped HTML', '&lt;img src=&quot;example&quot;&gt;', 3, 0, 0.9, '1 sec' ),
	array( 'Inline words', 'hel<em>lo</em> <span>world</span> inter<!-- comment -->national', 3, 0, 0.9, '1 sec' ),
	array( 'Punctuation', "Don't stop believing. It’s well-known: 1,000 3.14! 😀", 7, 0, 2.1, '3 sec' ),
	array( 'Whitespace', "one\ttwo\r\nthree\u{00A0}four", 4, 0, 1.2, '2 sec' ),
	array( 'Soft hyphens', 'read&shy;able words', 2, 0, 0.6, '1 sec' ),
	array( 'Registered shortcodes', '[caption id="a"]Visible caption[/caption] [gallery ids="1,2"]', 2, 0, 0.6, '1 sec' ),
	array( 'Nested shortcodes', '[caption]One [caption]two[/caption] three[/caption]', 3, 0, 0.9, '1 sec' ),
	array( 'Unknown shortcode preserved', '[unknown]Article text[/unknown]', 4, 0, 1.2, '2 sec' ),
	array( 'Escaped shortcode', '[[gallery]]', 1, 0, 0.3, '1 sec' ),
	array( 'Unclosed script', 'Visible<script>hidden <img src="bad">', 1, 0, 0.3, '1 sec' ),
	array( 'Unclosed comment', 'Visible<!-- hidden <img src="bad">', 1, 0, 0.3, '1 sec' ),
	array( 'Plain comparison', 'One < 3 and 4 > two', 5, 0, 1.5, '2 sec' ),
	array( 'Adjacent shortcodes', '[caption]One[/caption][caption]two[/caption]', 2, 0, 0.6, '1 sec' ),
	array( 'Custom HTML elements', '<script-example>Visible words</script-example>', 2, 0, 0.6, '1 sec' ),
	array( 'Document declaration', '<!DOCTYPE html><html><body><p>Visible words</p></body></html>', 2, 0, 0.6, '1 sec' ),
	array( 'Markup only', '<p></p><!-- invisible --><script>hidden</script>', 0, 0, 0, '0 sec' ),
	array( 'Large HTML article', str_repeat( '<p>Two words</p>', 10000 ), 20000, 0, 6000, '1 hr 40 min' ),
);
foreach ( $cases as $case ) {
	list( $label, $content, $words, $images, $seconds, $short ) = $case;
	$result = $calculator->calculate_from_content( $content );
	readflow_check( ! is_wp_error( $result ), $label . ': success' );
	readflow_check( null === $result['post_id'], $label . ': no post context' );
	readflow_check( $words === $result['word_count'], $label . ': words' );
	readflow_check( $images === $result['image_count'], $label . ': images' );
	readflow_check( 200.0 === $result['words_per_minute'], $label . ': WPM' );
	readflow_check( abs( $seconds - $result['reading_seconds'] ) < 0.000001, $label . ': seconds' );
	readflow_check( abs( $seconds / 60 - $result['reading_minutes'] ) < 0.000001, $label . ': minutes' );
	readflow_check( $short === $result['formatted_short_time'], $label . ': short label' );
	readflow_check( $short . ' read' === $result['formatted_time'], $label . ': full label' );
	echo 'PASS: ', $label, PHP_EOL;
}

foreach ( array( 0, -1, 'bad', '', null, true, false, array(), new stdClass(), INF, NAN, '1e999', 0.00001, 10001 ) as $invalid ) {
	$result = $calculator->calculate_from_content( str_repeat( 'word ', 200 ), array( 'wpm' => $invalid ) );
	readflow_check( 200.0 === $result['words_per_minute'] && 60.0 === $result['reading_seconds'] && '1 min read' === $result['formatted_time'], 'Invalid WPM defaults' );
}
foreach ( array( 1, 10000, 250, 250.5, '300', '2e2' ) as $wpm ) {
	$result = $calculator->calculate_from_content( str_repeat( 'word ', 1000 ), array( 'wpm' => $wpm ) );
	readflow_check( (float) $wpm === $result['words_per_minute'], 'Custom WPM retained' );
	readflow_check( abs( 60000 / $wpm - $result['reading_seconds'] ) < 0.000001, 'Custom WPM seconds' );
}
$result = $calculator->calculate_from_content( 'word', array( 'wpm' => 10000 ) );
readflow_check( 0.006 === $result['reading_seconds'] && '1 sec' === $result['formatted_short_time'], 'Fractional seconds preserved' );
readflow_check( 200.0 === $calculator->calculate_from_content( 'word', new stdClass() )['words_per_minute'], 'Invalid options use default' );

foreach ( array( 0, -1, 'bad', '12.5', 12.5, null, true, array(), new stdClass(), '999999999999999999999999999', '1e2' ) as $id ) {
	$result = $calculator->calculate_from_post( $id );
	readflow_check( is_wp_error( $result ) && 'readflow_invalid_post_id' === $result->get_error_code(), 'Invalid post ID controlled error' );
}
readflow_check( 0 === $wpdb->lookups, 'Invalid IDs/content calls never access database' );
$result = $calculator->calculate_from_post( 999 );
readflow_check( is_wp_error( $result ) && 'readflow_post_not_found' === $result->get_error_code(), 'Missing post controlled error' );
readflow_check( 1 === $wpdb->lookups, 'Missing post uses one lookup' );
wp_cache_set( 123, (object) array( 'ID' => 123, 'post_content' => '<p>' . str_repeat( 'word ', 200 ) . '</p><img src="a.jpg">', 'filter' => 'raw' ), 'posts' );
$result = $calculator->calculate_from_post( '123', array( 'wpm' => 100 ) );
readflow_check( 123 === $result['post_id'] && 200 === $result['word_count'] && 1 === $result['image_count'], 'Real WordPress post API fixture' );
readflow_check( 100.0 === $result['words_per_minute'] && 120.0 === $result['reading_seconds'] && 2.0 === $result['reading_minutes'], 'Post options and duration' );
readflow_check( '2 min read' === $result['formatted_time'] && '2 min' === $result['formatted_short_time'], 'Post labels' );
readflow_check( 1 === $wpdb->lookups, 'Cached fixture needs no database lookup' );

foreach ( array( null, array(), new stdClass(), 42, "\xC3\x28" ) as $content ) {
	$result = $calculator->calculate_from_content( $content );
	readflow_check( is_wp_error( $result ) && 'readflow_invalid_content' === $result->get_error_code(), 'Invalid content controlled error' );
}
foreach ( array( array( 0, '0 sec' ), array( 45, '45 sec' ), array( 59.9, '1 min' ), array( 60, '1 min' ), array( 61, '2 min' ), array( 372, '7 min' ), array( 3599, '1 hr' ), array( 3600, '1 hr' ), array( 4320, '1 hr 12 min' ), array( INF, '0 sec' ), array( -1, '0 sec' ) ) as $case ) {
	$formatted = ReadFlow_Time_Formatter::format( $case[0] );
	readflow_check( $case[1] === $formatted['formatted_short_time'] && $case[1] . ' read' === $formatted['formatted_time'], 'Independent formatter boundaries' );
}

// No parser failure should silently turn into a successful partial count.
$limit = ini_get( 'pcre.backtrack_limit' );
ini_set( 'pcre.backtrack_limit', '1' );
$result = $calculator->calculate_from_content( '<p title="one">Two words</p>' );
ini_set( 'pcre.backtrack_limit', $limit );
readflow_check( is_wp_error( $result ), 'Regex resource failure is controlled' );

echo 'PASS: ', $checks, ' assertions; no PHP warnings/notices; no live database or network access.', PHP_EOL;
