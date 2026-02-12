<?php
/**
 * Widget Manager Class
 *
 * Manages frontend widget assets and initialization
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MultiChat_GPT_Widget_Manager
 *
 * Handles widget asset enqueuing and localization
 */
class MultiChat_GPT_Widget_Manager {

	/**
	 * Initialize widget manager
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		// Only enqueue on frontend, not admin
		if ( is_admin() ) {
			return;
		}

		// Check if API key is configured
		$api_key = get_option( 'multichat_gpt_api_key' );
		if ( empty( $api_key ) ) {
			MultiChat_GPT_Logger::debug( 'Widget not loaded - API key not configured' );
			return;
		}

		// Enqueue widget CSS
		wp_enqueue_style(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/css/widget.css',
			array(),
			MULTICHAT_GPT_VERSION,
			'all'
		);

		// Enqueue widget JS
		wp_enqueue_script(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/js/widget.js',
			array(),
			MULTICHAT_GPT_VERSION,
			true
		);

		// Localize script with configuration
		wp_localize_script(
			'multichat-gpt-widget',
			'multiChatGPT',
			array(
				'restUrl'  => rest_url( 'multichat/v1/ask' ),
				'language' => self::get_current_language(),
				'position' => get_option( 'multichat_gpt_widget_position', 'bottom-right' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);

		MultiChat_GPT_Logger::debug( 'Widget assets enqueued' );
	}

	/**
	 * Get current language (WPML-aware)
	 *
	 * @return string Current language code.
	 */
	private static function get_current_language(): string {
		// Check for WPML function
		if ( function_exists( 'wpml_get_current_language' ) ) {
			$language = wpml_get_current_language();
			if ( ! empty( $language ) ) {
				return $language;
			}
		}

		// Fallback to WPML filter
		$language = apply_filters( 'wpml_current_language', null );
		if ( ! empty( $language ) ) {
			return $language;
		}

		// Fallback to WordPress locale
		$locale   = get_locale();
		$language = substr( $locale, 0, 2 );

		return ! empty( $language ) ? $language : 'en';
	}

	/**
	 * Get supported languages
	 *
	 * @return array Array of supported language codes.
	 */
	public static function get_supported_languages(): array {
		return MultiChat_GPT_Utility::get_supported_languages();
	}

	/**
	 * Check if language is supported
	 *
	 * @param string $language Language code to check.
	 * @return bool True if supported, false otherwise.
	 */
	public static function is_language_supported( string $language ): bool {
		return MultiChat_GPT_Utility::is_language_supported( $language );
	}
}
