<?php
/**
 * Widget Manager Class
 *
 * Frontend widget lifecycle management.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget Manager class for frontend widget management.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_Widget_Manager {

	/**
	 * Whether widget is enabled
	 *
	 * @var bool
	 */
	private $widget_enabled = true;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Allow disabling widget via filter.
		$this->widget_enabled = (bool) apply_filters( 'multichat_gpt_widget_enabled', $this->widget_enabled );
	}

	/**
	 * Initialize widget hooks
	 *
	 * @return void
	 */
	public function init() {
		if ( ! $this->widget_enabled ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Skip if in admin area.
		if ( is_admin() ) {
			return;
		}

		// Allow conditional loading via filter.
		$should_load = apply_filters( 'multichat_gpt_should_load_widget', true );
		if ( ! $should_load ) {
			return;
		}

		// Enqueue widget CSS.
		wp_enqueue_style(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/css/widget.css',
			array(),
			MULTICHAT_GPT_VERSION
		);

		// Enqueue widget JS.
		wp_enqueue_script(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/js/widget.js',
			array(),
			MULTICHAT_GPT_VERSION,
			true
		);

		// Get current language.
		$current_language = $this->get_current_language();

		// Localize script with configuration.
		wp_localize_script(
			'multichat-gpt-widget',
			'multiChatGPT',
			array(
				'restUrl'  => rest_url( 'multichat/v1/ask' ),
				'language' => $current_language,
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);

		MultiChat_GPT_Logger::debug( 'Widget assets enqueued', array( 'language' => $current_language ) );
	}

	/**
	 * Get current language (WPML-aware)
	 *
	 * @return string Language code.
	 */
	private function get_current_language() {
		// Check for WPML.
		if ( function_exists( 'wpml_get_current_language' ) ) {
			return wpml_get_current_language();
		}

		// Fallback to apply_filters hook for WPML.
		$lang = apply_filters( 'wpml_current_language', null );
		if ( ! empty( $lang ) ) {
			return $lang;
		}

		// Fallback to WordPress locale.
		$locale = get_locale();
		$lang   = substr( $locale, 0, 2 );

		return $lang ? $lang : 'en';
	}

	/**
	 * Get widget position from settings
	 *
	 * @return string Widget position.
	 */
	public function get_widget_position() {
		$position = get_option( 'multichat_gpt_widget_position', 'bottom-right' );

		// Validate position.
		$valid_positions = array( 'bottom-right', 'bottom-left' );
		if ( ! in_array( $position, $valid_positions, true ) ) {
			$position = 'bottom-right';
		}

		return $position;
	}
}
