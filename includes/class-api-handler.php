<?php
/**
 * API Handler Class
 *
 * Centralized ChatGPT API communication with caching and error handling.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Handler class for ChatGPT communication.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_API_Handler {

	/**
	 * API endpoint URL
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Cache TTL in seconds (default: 1 hour)
	 *
	 * @var int
	 */
	private $cache_ttl = 3600;

	/**
	 * Request timeout in seconds
	 *
	 * @var int
	 */
	private $timeout = 30;

	/**
	 * Maximum retry attempts
	 *
	 * @var int
	 */
	private $max_retries = 2;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Allow custom API endpoint via filter.
		$this->api_endpoint = apply_filters( 'multichat_gpt_api_endpoint', $this->api_endpoint );

		// Allow custom cache TTL via filter.
		$this->cache_ttl = (int) apply_filters( 'multichat_gpt_cache_ttl', $this->cache_ttl );

		// Allow custom timeout via filter.
		$this->timeout = (int) apply_filters( 'multichat_gpt_timeout', $this->timeout );

		// Allow custom max retries via filter.
		$this->max_retries = (int) apply_filters( 'multichat_gpt_max_retries', $this->max_retries );
	}

	/**
	 * Call ChatGPT API with caching and retry logic
	 *
	 * @param string $api_key        OpenAI API key.
	 * @param string $system_message System message for context.
	 * @param string $user_message   User's message.
	 * @param array  $options        Additional API options.
	 * @return string|WP_Error Response text or error.
	 */
	public function call_api( $api_key, $system_message, $user_message, $options = array() ) {
		// Validate API key.
		if ( empty( $api_key ) ) {
			MultiChat_GPT_Logger::error( 'API key is missing' );
			return new WP_Error( 'missing_api_key', __( 'API key is not configured', 'multichat-gpt' ) );
		}

		// Generate cache key.
		$cache_key = $this->generate_cache_key( $system_message, $user_message, $options );

		// Check cache first.
		$cached_response = get_transient( $cache_key );
		if ( false !== $cached_response ) {
			MultiChat_GPT_Logger::debug( 'Cache hit for API request', array( 'cache_key' => $cache_key ) );
			return $cached_response;
		}

		// Make API call with retry logic.
		$response = $this->make_request_with_retry( $api_key, $system_message, $user_message, $options );

		// Cache successful response.
		if ( ! is_wp_error( $response ) ) {
			set_transient( $cache_key, $response, $this->cache_ttl );
			MultiChat_GPT_Logger::debug( 'Cached API response', array( 'cache_key' => $cache_key ) );
		}

		return $response;
	}

	/**
	 * Make API request with retry logic
	 *
	 * @param string $api_key        OpenAI API key.
	 * @param string $system_message System message for context.
	 * @param string $user_message   User's message.
	 * @param array  $options        Additional API options.
	 * @return string|WP_Error Response text or error.
	 */
	private function make_request_with_retry( $api_key, $system_message, $user_message, $options = array() ) {
		$attempt = 0;
		$last_error = null;

		while ( $attempt <= $this->max_retries ) {
			$attempt++;

			MultiChat_GPT_Logger::debug(
				'API request attempt',
				array(
					'attempt' => $attempt,
					'max_retries' => $this->max_retries,
				)
			);

			$response = $this->make_request( $api_key, $system_message, $user_message, $options );

			// Return on success.
			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			$last_error = $response;

			// Don't retry on certain errors.
			$error_code = $response->get_error_code();
			if ( in_array( $error_code, array( 'invalid_api_key', 'rate_limit' ), true ) ) {
				break;
			}

			// Wait before retry (exponential backoff).
			if ( $attempt <= $this->max_retries ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_usleep
				usleep( 1000000 * $attempt ); // 1s, 2s, etc.
			}
		}

		MultiChat_GPT_Logger::error( 'API request failed after retries', array( 'error' => $last_error->get_error_message() ) );
		return $last_error;
	}

	/**
	 * Make a single API request
	 *
	 * @param string $api_key        OpenAI API key.
	 * @param string $system_message System message for context.
	 * @param string $user_message   User's message.
	 * @param array  $options        Additional API options.
	 * @return string|WP_Error Response text or error.
	 */
	private function make_request( $api_key, $system_message, $user_message, $options = array() ) {
		// Build request body.
		$request_body = array_merge(
			array(
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
			),
			$options
		);

		// Prepare request arguments.
		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $request_body ),
			'timeout' => $this->timeout,
		);

		// Make the request.
		$response = wp_remote_post( $this->api_endpoint, $args );

		// Check for HTTP errors.
		if ( is_wp_error( $response ) ) {
			MultiChat_GPT_Logger::error( 'HTTP error', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		// Get response code.
		$response_code = wp_remote_retrieve_response_code( $response );

		// Get response body.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Handle API errors.
		if ( 200 !== $response_code ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown API error', 'multichat-gpt' );
			$error_type = isset( $data['error']['type'] ) ? $data['error']['type'] : 'api_error';

			MultiChat_GPT_Logger::error(
				'API error',
				array(
					'code' => $response_code,
					'type' => $error_type,
					'message' => $error_message,
				)
			);

			// Map specific error types.
			if ( 401 === $response_code ) {
				return new WP_Error( 'invalid_api_key', $error_message );
			} elseif ( 429 === $response_code ) {
				return new WP_Error( 'rate_limit', $error_message );
			}

			return new WP_Error( $error_type, $error_message );
		}

		// Validate response format.
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			MultiChat_GPT_Logger::error( 'Invalid API response format', array( 'response' => $body ) );
			return new WP_Error( 'invalid_response', __( 'Unexpected response format from ChatGPT API', 'multichat-gpt' ) );
		}

		// Extract and return the message content.
		$message_content = $data['choices'][0]['message']['content'];

		MultiChat_GPT_Logger::debug( 'API request successful' );

		return $message_content;
	}

	/**
	 * Generate cache key for API request
	 *
	 * @param string $system_message System message.
	 * @param string $user_message   User message.
	 * @param array  $options        API options.
	 * @return string Cache key.
	 */
	private function generate_cache_key( $system_message, $user_message, $options = array() ) {
		$key_data = array(
			'system' => $system_message,
			'user'   => $user_message,
			'options' => $options,
		);

		return 'multichat_gpt_api_' . md5( wp_json_encode( $key_data ) );
	}

	/**
	 * Clear API cache
	 *
	 * @return void
	 */
	public function clear_cache() {
		global $wpdb;

		// Delete all transients starting with our prefix.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_multichat_gpt_api_' ) . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_multichat_gpt_api_' ) . '%'
			)
		);

		MultiChat_GPT_Logger::info( 'API cache cleared' );
	}
}
