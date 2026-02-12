<?php
/**
 * Widget Manager Class
 *
 * Manages frontend widget assets and localization
 *
 * @package MultiChatGPT
 * @since 1.1.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_Widget_Manager class
 *
 * Handles widget asset enqueuing and configuration
 */
class MultiChat_GPT_Widget_Manager {

	/**
	 * Logger instance
	 *
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param MultiChat_GPT_Logger $logger Logger instance
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Only enqueue on frontend, not admin
		if ( is_admin() ) {
			return;
		}

		// Enqueue widget CSS
		wp_enqueue_style(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/css/widget.css',
			array(),
			MULTICHAT_GPT_VERSION
		);

		// Enqueue widget JS with lazy loading (defer)
		wp_enqueue_script(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/js/widget.js',
			array(),
			MULTICHAT_GPT_VERSION,
			true // Load in footer
		);

		// Add defer attribute for lazy loading
		add_filter( 'script_loader_tag', array( $this, 'add_defer_attribute' ), 10, 2 );

		// Localize script with configuration
		$current_language = $this->get_current_language();

		wp_localize_script(
			'multichat-gpt-widget',
			'multiChatGPT',
			array(
				'restUrl'  => rest_url( 'multichat/v1/ask' ),
				'language' => $current_language,
				'position' => get_option( 'multichat_gpt_widget_position', 'bottom-right' ),
			)
		);

		$this->logger->debug( 'Widget assets enqueued', array( 'language' => $current_language ) );
	}

	/**
	 * Add defer attribute to widget script
	 *
	 * @param string $tag    Script tag
	 * @param string $handle Script handle
	 * @return string Modified script tag
	 */
	public function add_defer_attribute( $tag, $handle ) {
		if ( 'multichat-gpt-widget' !== $handle ) {
			return $tag;
		}

		// Add defer attribute if not already present
		if ( false === strpos( $tag, 'defer' ) ) {
			$tag = str_replace( ' src', ' defer src', $tag );
		}

		return $tag;
	}

	/**
	 * Get current language (WPML-aware)
	 *
	 * @return string Language code
	 */
	private function get_current_language() {
		// Check for WPML
		if ( function_exists( 'wpml_get_current_language' ) ) {
			return wpml_get_current_language();
		}

		// Fallback to apply_filters hook for WPML
		$lang = apply_filters( 'wpml_current_language', null );
		if ( ! empty( $lang ) ) {
			return $lang;
		}

		// Fallback to WordPress locale
		$locale = get_locale();
		$lang   = substr( $locale, 0, 2 );

		return $lang ?: 'en';
	}
}
