<?php
/**
 * Widget Manager Class
 *
 * Manages frontend widget initialization and asset loading.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_Widget_Manager class.
 *
 * Handles frontend widget scripts, styles, and localization.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_Widget_Manager {

	/**
	 * Logger instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param MultiChat_GPT_Logger $logger Logger instance.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_assets() {
		// Only enqueue on frontend, not admin.
		if ( is_admin() ) {
			return;
		}

		// Enqueue widget CSS.
		wp_enqueue_style(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/css/widget.css',
			[],
			MULTICHAT_GPT_VERSION,
			'all'
		);

		// Enqueue widget JS with defer loading for better performance.
		wp_enqueue_script(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/js/widget.js',
			[],
			MULTICHAT_GPT_VERSION,
			true
		);

		// Add defer attribute for performance.
		add_filter( 'script_loader_tag', [ $this, 'add_defer_attribute' ], 10, 2 );

		// Localize script with data.
		$this->localize_script();

		$this->logger->debug( 'Widget assets enqueued' );
	}

	/**
	 * Add defer attribute to widget script
	 *
	 * @since 1.0.0
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @return string Modified script tag.
	 */
	public function add_defer_attribute( $tag, $handle ) {
		if ( 'multichat-gpt-widget' !== $handle ) {
			return $tag;
		}

		// Add defer attribute if not already present.
		if ( strpos( $tag, 'defer' ) === false ) {
			$tag = str_replace( ' src', ' defer src', $tag );
		}

		return $tag;
	}

	/**
	 * Localize script with necessary data
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function localize_script() {
		$current_language = $this->get_current_language();

		wp_localize_script(
			'multichat-gpt-widget',
			'multiChatGPT',
			[
				'restUrl'  => rest_url( 'multichat/v1/ask' ),
				'language' => $current_language,
				'position' => get_option( 'multichat_gpt_widget_position', 'bottom-right' ),
			]
		);
	}

	/**
	 * Get current language (WPML-aware)
	 *
	 * @since 1.0.0
	 * @return string Language code.
	 */
	private function get_current_language() {
		// Check for WPML.
		if ( function_exists( 'wpml_get_current_language' ) ) {
			return wpml_get_current_language();
		}

		// Fallback to WPML filter hook.
		$lang = apply_filters( 'wpml_current_language', null );
		if ( ! empty( $lang ) ) {
			return $lang;
		}

		// Fallback to WordPress locale.
		$locale = get_locale();
		$lang   = substr( $locale, 0, 2 );

		return ! empty( $lang ) ? $lang : 'en';
	}
}
