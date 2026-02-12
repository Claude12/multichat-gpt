<?php
/**
 * Utility Class
 *
 * Shared utility functions
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MultiChat_GPT_Utility
 *
 * Provides shared utility functions across the plugin
 */
class MultiChat_GPT_Utility {

	/**
	 * Supported languages
	 *
	 * @var array
	 */
	private const SUPPORTED_LANGUAGES = array( 'en', 'ar', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja' );

	/**
	 * Get supported languages
	 *
	 * @return array Array of supported language codes.
	 */
	public static function get_supported_languages(): array {
		return self::SUPPORTED_LANGUAGES;
	}

	/**
	 * Check if language is supported
	 *
	 * @param string $language Language code to check.
	 * @return bool True if supported, false otherwise.
	 */
	public static function is_language_supported( string $language ): bool {
		return in_array( $language, self::SUPPORTED_LANGUAGES, true );
	}

	/**
	 * Get client IP address
	 *
	 * Attempts to get the real client IP from various headers
	 * that may be set by proxies or load balancers.
	 *
	 * @return string IP address.
	 */
	public static function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) as $ip ) {
					$ip = trim( $ip );

					if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return '0.0.0.0';
	}
}
