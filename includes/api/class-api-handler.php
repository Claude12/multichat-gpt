<?php
/**
 * API Handler Class
 *
 * Handles communication with OpenAI API with caching, retry logic, and rate limiting.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_API_Handler class.
 *
 * Manages all OpenAI API interactions with performance optimizations
 * and error handling.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_API_Handler {

	/**
	 * OpenAI API endpoint
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Logger instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

	/**
	 * Cache TTL in seconds (1 hour)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $cache_ttl = 3600;

	/**
	 * Maximum retry attempts
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $max_retries = 2;

	/**
	 * Request timeout in seconds
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $timeout = 30;

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
	 * Call ChatGPT API with caching and retry logic
	 *
	 * @since 1.0.0
	 * @param string $api_key        OpenAI API key.
	 * @param string $system_message System message for context.
	 * @param string $user_message   User's message.
	 * @return string|WP_Error Response message or error.
	 */
	public function call_chatgpt_api( $api_key, $system_message, $user_message ) {
		// Validate API key.
		if ( empty( $api_key ) || ! $this->is_valid_api_key( $api_key ) ) {
			$this->logger->error( 'Invalid API key provided' );
			return new WP_Error( 'invalid_api_key', __( 'Invalid API key', 'multichat-gpt' ) );
		}

		// Check cache first.
		$cache_key = $this->generate_cache_key( $system_message, $user_message );
		$cached    = $this->get_cached_response( $cache_key );

		if ( false !== $cached ) {
			$this->logger->debug( 'API response retrieved from cache', [ 'cache_key' => $cache_key ] );
			return $cached;
		}

		// Make API request with retry logic.
		$response = $this->make_request_with_retry( $api_key, $system_message, $user_message );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Cache successful response.
		$this->cache_response( $cache_key, $response );

		return $response;
	}

	/**
	 * Make API request with retry logic
	 *
	 * Note: Uses sleep() for exponential backoff. This is intentional for API retry scenarios
	 * where we want to wait before retrying to avoid overwhelming the API endpoint.
	 * The blocking behavior is acceptable here as it only affects the current request.
	 *
	 * @since 1.0.0
	 * @param string $api_key        OpenAI API key.
	 * @param string $system_message System message.
	 * @param string $user_message   User message.
	 * @return string|WP_Error Response or error.
	 */
	private function make_request_with_retry( $api_key, $system_message, $user_message ) {
		$attempts = 0;
		$response = null;

		while ( $attempts < $this->max_retries ) {
			$response = $this->make_api_request( $api_key, $system_message, $user_message );

			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			$attempts++;

			// Log retry attempt.
			if ( $attempts < $this->max_retries ) {
				$this->logger->warning(
					'API request failed, retrying',
					[
						'attempt' => $attempts,
						'error'   => $response->get_error_message(),
					]
				);

				// Exponential backoff: wait 1s, 2s, 4s, etc.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_log, WordPress.VIP.RestrictedFunctions.sleep_sleep
				sleep( pow( 2, $attempts - 1 ) );
			}
		}

		// All retries failed.
		$this->logger->error( 'API request failed after all retries', [ 'error' => $response->get_error_message() ] );
		return $response;
	}

	/**
	 * Make a single API request
	 *
	 * @since 1.0.0
	 * @param string $api_key        OpenAI API key.
	 * @param string $system_message System message.
	 * @param string $user_message   User message.
	 * @return string|WP_Error Response or error.
	 */
	private function make_api_request( $api_key, $system_message, $user_message ) {
		// Build request body.
		$request_body = [
			'model'       => 'gpt-3.5-turbo',
			'messages'    => [
				[
					'role'    => 'system',
					'content' => $system_message,
				],
				[
					'role'    => 'user',
					'content' => $user_message,
				],
			],
			'temperature' => 0.7,
			'max_tokens'  => 1000,
		];

		/**
		 * Filter API request body before sending
		 *
		 * @since 1.0.0
		 * @param array $request_body The request body array.
		 */
		$request_body = apply_filters( 'multichat_gpt_api_request_body', $request_body );

		// Prepare request arguments.
		$args = [
			'method'  => 'POST',
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $request_body ),
			'timeout' => $this->timeout,
		];

		// Make request using WordPress HTTP API.
		$response = wp_remote_post( $this->api_endpoint, $args );

		// Check for HTTP errors.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Get response code.
		$response_code = wp_remote_retrieve_response_code( $response );

		// Get response body.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Check for API errors.
		if ( 200 !== $response_code ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown API error', 'multichat-gpt' );
			return new WP_Error( 'chatgpt_api_error', $error_message, [ 'status' => $response_code ] );
		}

		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'chatgpt_error', $data['error']['message'] ?? __( 'Unknown error', 'multichat-gpt' ) );
		}

		// Extract assistant's message.
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return $data['choices'][0]['message']['content'];
		}

		return new WP_Error( 'chatgpt_error', __( 'Unexpected response format from ChatGPT API', 'multichat-gpt' ) );
	}

	/**
	 * Validate API key format
	 *
	 * @since 1.0.0
	 * @param string $api_key API key to validate.
	 * @return bool True if valid format, false otherwise.
	 */
	private function is_valid_api_key( $api_key ) {
		// OpenAI API keys start with 'sk-' and are at least 20 characters.
		return preg_match( '/^sk-[a-zA-Z0-9]{20,}$/', $api_key ) === 1;
	}

	/**
	 * Generate cache key for API request
	 *
	 * @since 1.0.0
	 * @param string $system_message System message.
	 * @param string $user_message   User message.
	 * @return string Cache key.
	 */
	private function generate_cache_key( $system_message, $user_message ) {
		$key = 'multichat_gpt_' . md5( $system_message . '|' . $user_message );
		return $key;
	}

	/**
	 * Get cached API response
	 *
	 * @since 1.0.0
	 * @param string $cache_key Cache key.
	 * @return string|false Cached response or false if not found.
	 */
	private function get_cached_response( $cache_key ) {
		return get_transient( $cache_key );
	}

	/**
	 * Cache API response
	 *
	 * @since 1.0.0
	 * @param string $cache_key Cache key.
	 * @param string $response  Response to cache.
	 * @return bool True on success, false on failure.
	 */
	private function cache_response( $cache_key, $response ) {
		/**
		 * Filter cache TTL
		 *
		 * @since 1.0.0
		 * @param int $cache_ttl Cache time-to-live in seconds.
		 */
		$ttl = apply_filters( 'multichat_gpt_cache_ttl', $this->cache_ttl );

		return set_transient( $cache_key, $response, $ttl );
	}

	/**
	 * Clear all cached responses
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_cache() {
		global $wpdb;

		// Delete all transients and their timeout entries with our prefix.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_multichat_gpt_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_multichat_gpt_' ) . '%'
			)
		);

		$this->logger->info( 'API cache cleared' );
	}
}
