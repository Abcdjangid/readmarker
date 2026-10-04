<?php
/** Standard WordPress Settings API screen; no custom assets. @package ReadFlow */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-readflow-presets.php';
require_once __DIR__ . '/class-readflow-reset.php';

final class ReadFlow_Admin {
	private $settings;

	public function __construct( ReadFlow_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks() {
		add_filter( 'plugin_action_links_' . plugin_basename( READFLOW_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		$reset = new ReadFlow_Reset( $this->settings );
		$reset->register_hooks();
		$presets = new ReadFlow_Presets( $this->settings );
		add_action( 'load-readflow_page_readflow', array( $presets, 'handle' ) );
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'add_fields' ) );
		// Redirect before WordPress checks access against the removed Settings-menu hooks.
		add_action( 'admin_menu', array( $this, 'redirect_legacy_page' ), 12 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/** Preserve old Settings-menu bookmarks without registering duplicate pages. */
	public function redirect_legacy_page() {
		global $pagenow;
		if ( 'options-general.php' !== $pagenow || 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || ! current_user_can( 'manage_options' ) ) { return; }
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only legacy navigation; no settings are changed.
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : null;
		if ( ! is_string( $page ) || ! in_array( $page, array( 'readflow-overview', 'readflow', 'readflow-shortcodes' ), true ) ) { return; }
		$args = array( 'page' => $page );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Forward read-only notice parameters; destination verifies signed notices.
		foreach ( array( 'settings-updated', 'readflow_reset_done' ) as $key ) {
			if ( isset( $_GET[$key] ) && is_string( $_GET[$key] ) ) { $args[$key] = sanitize_text_field( wp_unslash( $_GET[$key] ) ); }
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function assets( $hook ) {
		if ( 'readflow_page_readflow' !== $hook || ! current_user_can( 'manage_options' ) ) { return; }
		wp_enqueue_style( 'readflow-presets', READFLOW_PLUGIN_URL . 'assets/css/readflow-presets.css', array(), (string) filemtime( READFLOW_PLUGIN_DIR . 'assets/css/readflow-presets.css' ) );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'readflow-settings-placement', READFLOW_PLUGIN_URL . 'assets/js/readflow-settings-placement.js', array( 'wp-color-picker' ), (string) filemtime( READFLOW_PLUGIN_DIR . 'assets/js/readflow-settings-placement.js' ), true );
	}

	public function add_page() {
		add_submenu_page( 'readflow-overview', __( 'ReadFlow', 'readflow' ), __( 'Settings', 'readflow' ), 'manage_options', 'readflow', array( $this, 'render_page' ) );
	}

	/** Navigation only; WordPress retains ownership of activation/deactivation links. */
	public function plugin_action_links( $links ) {
		if ( ! current_user_can( 'manage_options' ) ) { return $links; }
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=readflow' ) ) . '">' . esc_html__( 'Settings', 'readflow' ) . '</a>';
		return $links;
	}

	/** Presentation labels shared by headings and navigation. */
	private function sections() {
		return array(
			'readflow_general' => array( 'general', __( 'General', 'readflow' ), __( 'Enable automatic reading information and set the reading speed.', 'readflow' ) ),
			'readflow_rules' => array( 'rules', __( 'Display Rules', 'readflow' ), __( 'Choose the content types eligible for automatic display.', 'readflow' ) ),
			'readflow_display' => array( 'reading-time', __( 'Reading Time', 'readflow' ), __( 'Choose reading information, style, and automatic placement.', 'readflow' ) ),
			'readflow_progress' => array( 'progress', __( 'Progress', 'readflow' ), __( 'Choose how reading progress is displayed.', 'readflow' ) ),
			'readflow_position' => array( 'reading-position', __( 'Reading Position', 'readflow' ), __( 'Offer returning readers a position saved in their browser.', 'readflow' ) ),
			'readflow_controls' => array( 'reader-controls', __( 'Reader Controls', 'readflow' ), __( 'Offer local text-size and reading-width preferences.', 'readflow' ) ),
			'readflow_advanced' => array( 'advanced', __( 'Advanced', 'readflow' ), __( 'Configure the target for Custom Selector placement, chosen under Reading Time or in individual post settings.', 'readflow' ) ),
		);
	}

	public function section_description( $section ) {
		$sections = $this->sections();
		if ( 'readflow_rules' === $section['id'] ) { $this->rules_anchor(); }
		echo '<p>' . esc_html( $sections[ $section['id'] ][2] ) . '</p>';
	}

	public function add_fields() {
		foreach ( $this->sections() as $key => $section ) {
			$title = '<span class="readflow-settings-section" id="readflow-section-' . esc_attr( $section[0] ) . '" tabindex="-1">' . esc_html( $section[1] ) . '</span>';
			add_settings_section( $key, $title, array( $this, 'section_description' ), 'readflow' );
		}
		$fields = array(
			'enabled'         => array( __( 'Enable Reading Time', 'readflow' ), 'readflow_general' ),
			'wpm'             => array( __( 'Reading Speed', 'readflow' ), 'readflow_general' ),
			'post_types'      => array( __( 'Display on', 'readflow' ), 'readflow_rules' ),
			'style'           => array( __( 'Display Style', 'readflow' ), 'readflow_display' ),
			'show_time'       => array( __( 'Show Reading Time', 'readflow' ), 'readflow_display' ),
			'show_word_count' => array( __( 'Show Word Count', 'readflow' ), 'readflow_display' ),
			'position'        => array( __( 'Automatic Position', 'readflow' ), 'readflow_display' ),
			'target_selector' => array( __( 'Target CSS Selector', 'readflow' ), 'readflow_advanced' ),
			'reader_controls_enabled' => array( __( 'Reader Controls', 'readflow' ), 'readflow_controls' ),
			'reader_controls_placement' => array( __( 'Reader Controls Placement', 'readflow' ), 'readflow_controls' ),
			'reader_controls_visibility' => array( __( 'Show Reader Controls', 'readflow' ), 'readflow_controls' ),
			'reader_controls_panel' => array( __( 'Reader Controls Panel', 'readflow' ), 'readflow_controls' ),
			'position_memory_enabled' => array( __( 'Enable Reading Position Memory', 'readflow' ), 'readflow_position' ),
			'progress_enabled' => array( __( 'Enable Reading Progress', 'readflow' ), 'readflow_progress' ),
			'progress_color'   => array( __( 'Progress Color', 'readflow' ), 'readflow_progress' ),
			'progress_height'  => array( __( 'Progress Height', 'readflow' ), 'readflow_progress' ),
			'progress_display' => array( __( 'Progress Display', 'readflow' ), 'readflow_progress' ),
		);
		foreach ( $fields as $key => $field ) {
			$args = array( 'key' => $key );
			if ( 'target_selector' === $key ) { $args['class'] = 'readflow-selector-row'; }
			if ( 'post_types' !== $key ) {
				$args['label_for'] = 'readflow-' . $key;
			}
			add_settings_field( 'readflow-' . $key, $field[0], array( $this, 'render_field' ), 'readflow', $field[1], $args );
		}
	}

	/** Field values are normalized on read and escaped for their output context. */
	public function render_field( $args ) {
		$settings = $this->settings->get();
		$key      = $args['key'];
		$id       = 'readflow-' . $key;
		$name     = ReadFlow_Settings::OPTION . '[' . $key . ']';
		if ( in_array( $key, array( 'enabled', 'show_time', 'show_word_count', 'progress_enabled', 'position_memory_enabled', 'reader_controls_enabled' ), true ) ) {
			$labels = array(
				'enabled'         => __( 'Automatically display reading information on selected post types.', 'readflow' ),
				'show_time'       => __( 'Include the estimated reading time.', 'readflow' ),
				'show_word_count' => __( 'Include the word count in any display style.', 'readflow' ),
				'reader_controls_enabled' => __( 'Let readers adjust text size and reading width on supported articles. Preferences stay in their browser and are never sent to your site.', 'readflow' ),
				'position_memory_enabled' => __( 'Offer Continue reading using local browser storage. Positions expire after 30 days; nothing is sent to the server.', 'readflow' ),
				'progress_enabled' => __( 'Show reading progress on selected post types, independently of the reading-time display.', 'readflow' ),
			);
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
			echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( $settings[ $key ], true, false ) . '> ' . esc_html( $labels[ $key ] ) . '</label>';
		} elseif ( isset( ReadFlow_Settings::reader_control_choices()[ $key ] ) ) {
			$descriptions = array( 'reader_controls_placement'=>__( 'Choose where the Reading Options control appears around the article.', 'readflow' ), 'reader_controls_visibility'=>__( 'Choose which screen sizes show Reading Options. Desktop is 782px and above; mobile is below 782px.', 'readflow' ), 'reader_controls_panel'=>__( 'Choose whether the controls panel starts open or closed.', 'readflow' ) );
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" aria-describedby="' . esc_attr( $id . '-help' ) . '">';
			foreach ( ReadFlow_Settings::reader_control_choices()[ $key ] as $value=>$label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $settings[ $key ], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
			echo '</select><p class="description" id="' . esc_attr( $id . '-help' ) . '">' . esc_html( $descriptions[ $key ] ) . '</p>';
		} elseif ( 'target_selector' === $key ) {
			echo '<details data-readflow-selector-settings' . ( 'selector' === $settings['position'] || '' !== $settings[$key] ? ' open' : '' ) . '><summary>' . esc_html__( 'Shared target for global or per-post Custom Selector placement', 'readflow' ) . '</summary><input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $settings[ $key ] ) . '" aria-describedby="readflow-selector-help"><p class="description" id="readflow-selector-help">' . esc_html__( 'Enter a CSS selector where ReadFlow should place the reading information. Example: .article-header or #post-meta. Only the first matching element is used.', 'readflow' ) . '</p></details>';
		} elseif ( 'progress_display' === $key ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( ReadFlow_Settings::progress_displays() as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $settings[ $key ], $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select><p class="description">' . esc_html__( 'Non-bar displays sit at the bottom-right. Countdown follows scrolling; it is not a timer. Progress Height applies to the top bar only.', 'readflow' ) . '</p>';
		} elseif ( 'progress_color' === $key ) {
			echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $settings[ $key ] ) . '" pattern="#[a-fA-F0-9]{3}([a-fA-F0-9]{3})?" aria-describedby="readflow-color-help"><p class="description" id="readflow-color-help">' . esc_html__( 'Hex color, for example #2563eb or #369.', 'readflow' ) . '</p>';
		} elseif ( 'progress_height' === $key ) {
			echo '<input type="number" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $settings[ $key ] ) . '" min="1" max="20" step="1"> ' . esc_html__( 'pixels (1–20)', 'readflow' );
		} elseif ( 'wpm' === $key ) {
			echo '<input class="small-text" type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" min="' . esc_attr( (string) ReadFlow_Calculator::MIN_WPM ) . '" max="' . esc_attr( (string) ReadFlow_Calculator::MAX_WPM ) . '" step="any" value="' . esc_attr( (string) $settings['wpm'] ) . '" aria-describedby="readflow-wpm-help"> ' . esc_html__( 'WPM', 'readflow' );
			/* translators: 1: Minimum WPM, 2: Maximum WPM, 3: Default WPM. */
			$help = sprintf( __( 'Words per minute, from %1$s to %2$s. Invalid values use %3$s.', 'readflow' ), number_format_i18n( ReadFlow_Calculator::MIN_WPM ), number_format_i18n( ReadFlow_Calculator::MAX_WPM ), number_format_i18n( ReadFlow_Calculator::DEFAULT_WPM ) );
			echo '<p class="description" id="readflow-wpm-help">' . esc_html( $help ) . '</p>';
		} elseif ( 'post_types' === $key ) {
			echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Display on', 'readflow' ) . '</legend><input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
			foreach ( ReadFlow_Settings::display_post_types() as $type ) {
				echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $type->name ) . '" ' . checked( in_array( $type->name, $settings['post_types'], true ), true, false ) . '> ' . esc_html( $type->labels->name ) . '</label><br>';
			}
			echo '</fieldset>';
		} elseif ( 'style' === $key || 'position' === $key ) {
			$choices = 'style' === $key ? ReadFlow_Settings::styles() : ReadFlow_Settings::positions();
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" aria-describedby="' . esc_attr( $id . '-help' ) . '">';
			foreach ( $choices as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $settings[ $key ], $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select><p class="description" id="' . esc_attr( $id . '-help' ) . '">' . esc_html( 'style' === $key ? __( 'All styles use the visibility choices below. Meta shows details inline; Card stacks them.', 'readflow' ) : __( 'Manual only disables automatic reading-time output. Use a ReadFlow shortcode or block for manual placement.', 'readflow' ) ) . '</p>';
		}
	}

	public function rules_anchor() { echo '<span id="readflow-rules"></span>'; }

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap readflow-settings"><h1>' . esc_html__( 'ReadFlow', 'readflow' ) . '</h1><p>' . esc_html__( 'Lightweight reading information for your content. Free, with no account or tracking.', 'readflow' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=readflow-overview' ) ) . '">' . esc_html__( 'Configuration Overview', 'readflow' ) . '</a></p>';
		echo '<nav class="readflow-settings-nav" aria-label="' . esc_attr__( 'Settings sections', 'readflow' ) . '"><ul>';
		foreach ( $this->sections() as $section ) {
			echo '<li><a href="#readflow-section-' . esc_attr( $section[0] ) . '">' . esc_html( $section[1] ) . '</a></li>';
		}
		echo '</ul></nav>';
		// admin.php does not render Settings API notices through options-head.php.
		settings_errors();
		$presets = new ReadFlow_Presets( $this->settings );
		$presets->render();
		echo '<form action="options.php" method="post">';
		settings_fields( ReadFlow_Settings::GROUP );
		do_settings_sections( 'readflow' );
		submit_button();
		echo '</form>';
		$reset = new ReadFlow_Reset( $this->settings );
		$reset->render();
		echo '</div>';
	}
}
