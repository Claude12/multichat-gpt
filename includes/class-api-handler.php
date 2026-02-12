<?php
/**
 * API Handler Class
 *
 * Handles communication with ChatGPT API
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MultiChat_GPT_API_Handler
 *
 * Manages ChatGPT API communication with caching and error handling
 */
class MultiChat_GPT_API_Handler {

	/**
	 * ChatGPT API Endpoint
	 *
	 * @var string
	 */
	private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Cache expiration time in seconds (1 hour)
	 *
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Maximum retry attempts
	 *
	 * @var int
	 */
	private const MAX_RETRIES = 2;

	/**
	 * Call ChatGPT API with caching and retry logic
	 *
	 * @param string $api_key        OpenAI API key.
	 * @param string $system_message System message for context.
	 * @param string $user_message   User's message.
	 * @return string|WP_Error Response content or error.
	 */
	public static function call_api( string $api_key, string $system_message, string $user_message ) {
		// Validate inputs
		if ( empty( $api_key ) ) {
			MultiChat_GPT_Logger::error( 'API key is empty' );
			return new WP_Error( 'invalid_api_key', __( 'API key is required', 'multichat-gpt' ) );
		}

		if ( empty( $user_message ) ) {
			MultiChat_GPT_Logger::error( 'User message is empty' );
			return new WP_Error( 'invalid_message', __( 'Message is required', 'multichat-gpt' ) );
		}

		// Check cache first
		$cache_key      = self::get_cache_key( $system_message, $user_message );
		$cached_response = get_transient( $cache_key );

		if ( false !== $cached_response ) {
			MultiChat_GPT_Logger::debug( 'Cache hit for API request', array( 'cache_key' => $cache_key ) );
			return $cached_response;
		}

		// Make API request with retries
		$response = self::make_request_with_retry( $api_key, $system_message, $user_message );

		if ( is_wp_error( $response ) ) {
			MultiChat_GPT_Logger::error(
				'API request failed',
				array(
					'error_message' => $response->get_error_message(),
					'error_code'    => $response->get_error_code(),
				)
			);
			return $response;
		}

		// Cache the successful response
		set_transient( $cache_key, $response, self::CACHE_EXPIRATION );
		MultiChat_GPT_Logger::debug( 'API response cached', array( 'cache_key' => $cache_key ) );

		return $response;
	}

	/**
	 * Make API request with retry logic
	 *
	 * @param string $api_key        OpenAI API key.
	 * @param string $system_message System message.
	 * @param string $user_message   User message.
	 * @return string|WP_Error Response or error.
	 */
	private static function make_request_with_retry( string $api_key, string $system_message, string $user_message ) {
		$last_error = null;

		for ( $attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			if ( $attempt > 0 ) {
				// Wait before retry with exponential backoff
				// Note: sleep() is intentional here for retry logic
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_sleep
				sleep( pow( 2, $attempt - 1 ) );
				MultiChat_GPT_Logger::debug( "Retry attempt {$attempt}" );
			}

			$response = self::make_api_request( $api_key, $system_message, $user_message );

			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			$last_error = $response;

			// Don't retry on authentication or validation errors
			$error_code = $response->get_error_code();
			if ( in_array( $error_code, array( 'invalid_api_key', 'authentication_error', 'invalid_request' ), true ) ) {
				break;
			}
		}

		return $last_error;
	}

	/**
	 * Make a single API request
	 *
	 * @param string $api_key        OpenAI API key.
	 * @param string $system_message System message.
	 * @param string $user_message   User message.
	 * @return string|WP_Error Response content or error.
	 */
	private static function make_api_request( string $api_key, string $system_message, string $user_message ) {
		$request_body = array(
			'model'       => 'gpt-3.5-turbo',
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_message,
				),
				array(
					'role'    => 'user',
					'content' => $user_message,
				),
			),
			'temperature' => 0.7,
			'max_tokens'  => 1000,
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $request_body ),
			'timeout' => 30,
		);

		$response = wp_remote_post( self::API_ENDPOINT, $args );

		// Check for HTTP errors
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'http_error',
				/* translators: %s: error message */
				sprintf( __( 'HTTP error: %s', 'multichat-gpt' ), $response->get_error_message() )
			);
		}

		// Get response code
		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );

		// Handle API errors
		if ( $response_code !== 200 ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown API error', 'multichat-gpt' );

			// Determine error code based on HTTP status
			$error_code = 'api_error';
			if ( $response_code === 401 ) {
				$error_code = 'authentication_error';
			} elseif ( $response_code === 429 ) {
				$error_code = 'rate_limit_error';
			} elseif ( $response_code === 400 ) {
				$error_code = 'invalid_request';
			}

			return new WP_Error( $error_code, $error_message );
		}

		// Validate response format
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response format from ChatGPT API', 'multichat-gpt' )
			);
		}

		return $data['choices'][0]['message']['content'];
	}

	/**
	 * Generate cache key for request
	 *
	 * @param string $system_message System message.
	 * @param string $user_message   User message.
	 * @return string Cache key.
	 */
	private static function get_cache_key( string $system_message, string $user_message ): string {
		return 'multichat_api_' . md5( $system_message . '|' . $user_message );
	}

	/**
	 * Clear API cache
	 *
	 * Clears all cached API responses by deleting transients with the multichat_api_ prefix
	 * Note: Uses direct SQL query as WordPress doesn't provide a method to delete by prefix.
	 * This operation can be slow on large sites with many transients.
	 * Consider running during low-traffic periods or via WP-CLI for large installations.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		global $wpdb;

		// Delete all transients with our prefix using direct SQL
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_multichat_api_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_multichat_api_' ) . '%'
			)
		);

		MultiChat_GPT_Logger::info( 'API cache cleared' );
	}
}
