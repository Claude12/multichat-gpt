<?php
/**
 * API Handler Class
 *
 * Handles ChatGPT API communication with caching and error handling
 *
 * @package MultiChatGPT
 * @since 1.1.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_API_Handler class
 *
 * Manages ChatGPT API requests with caching and rate limiting
 */
class MultiChat_GPT_API_Handler {

	/**
	 * ChatGPT API Endpoint
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Logger instance
	 *
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

	/**
	 * Cache expiration time in seconds (1 hour)
	 *
	 * @var int
	 */
	private $cache_expiration = 3600;

	/**
	 * Request timeout in seconds
	 *
	 * @var int
	 */
	private $request_timeout = 30;

	/**
	 * Constructor
	 *
	 * @param MultiChat_GPT_Logger $logger Logger instance
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;

		// Allow filtering of cache expiration
		$this->cache_expiration = apply_filters( 'multichat_gpt_cache_expiration', $this->cache_expiration );
		
		// Allow filtering of request timeout
		$this->request_timeout = apply_filters( 'multichat_gpt_request_timeout', $this->request_timeout );
	}

	/**
	 * Call ChatGPT API with caching support
	 *
	 * @param string $api_key        OpenAI API key
	 * @param string $system_message System message for context
	 * @param string $user_message   User's message
	 * @param array  $options        Additional options (model, temperature, etc.)
	 * @return string|WP_Error Response message or error
	 */
	public function call_api( $api_key, $system_message, $user_message, $options = array() ) {
		// Validate API key
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'API key is required', 'multichat-gpt' ) );
		}

		// Sanitize inputs
		$system_message = sanitize_textarea_field( $system_message );
		$user_message   = sanitize_textarea_field( $user_message );

		// Check cache first
		$cache_key = $this->generate_cache_key( $system_message, $user_message );
		$cached    = $this->get_cached_response( $cache_key );

		if ( false !== $cached ) {
			$this->logger->debug( 'Cache hit for API request', array( 'cache_key' => $cache_key ) );
			return $cached;
		}

		// Prepare request
		$defaults = array(
			'model'       => 'gpt-3.5-turbo',
			'temperature' => 0.7,
			'max_tokens'  => 1000,
		);

		$options = wp_parse_args( $options, $defaults );

		$request_body = array(
			'model'       => sanitize_text_field( $options['model'] ),
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
			'temperature' => floatval( $options['temperature'] ),
			'max_tokens'  => intval( $options['max_tokens'] ),
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $request_body ),
			'timeout' => $this->request_timeout,
		);

		// Make API request
		$this->logger->debug( 'Making API request', array( 'model' => $options['model'] ) );
		$response = wp_remote_post( $this->api_endpoint, $args );

		// Check for HTTP errors
		if ( is_wp_error( $response ) ) {
			$this->logger->log_api_error( $this->api_endpoint, $response->get_error_message() );
			return $response;
		}

		// Get response code
		$response_code = wp_remote_retrieve_response_code( $response );
		
		// Check for non-200 responses
		if ( 200 !== $response_code ) {
			$error_message = sprintf(
				/* translators: %d: HTTP response code */
				__( 'API returned error code: %d', 'multichat-gpt' ),
				$response_code
			);
			$this->logger->log_api_error( $this->api_endpoint, $error_message, array( 'code' => $response_code ) );
			return new WP_Error( 'api_error', $error_message );
		}

		// Get response body
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Check for JSON decode errors
		if ( null === $data ) {
			$this->logger->log_api_error( $this->api_endpoint, 'Invalid JSON response' );
			return new WP_Error( 'invalid_json', __( 'Invalid JSON response from API', 'multichat-gpt' ) );
		}

		// Check for API errors
		if ( isset( $data['error'] ) ) {
			$error_msg = $data['error']['message'] ?? __( 'Unknown API error', 'multichat-gpt' );
			$this->logger->log_api_error( $this->api_endpoint, $error_msg );
			return new WP_Error( 'chatgpt_error', $error_msg );
		}

		// Extract assistant's message
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			$this->logger->log_api_error( $this->api_endpoint, 'Unexpected response format' );
			return new WP_Error( 'invalid_response', __( 'Unexpected response format from ChatGPT API', 'multichat-gpt' ) );
		}

		$assistant_message = $data['choices'][0]['message']['content'];

		// Cache the response
		$this->cache_response( $cache_key, $assistant_message );

		return $assistant_message;
	}

	/**
	 * Generate cache key from request parameters
	 *
	 * @param string $system_message System message
	 * @param string $user_message   User message
	 * @return string Cache key
	 */
	private function generate_cache_key( $system_message, $user_message ) {
		$data = $system_message . '|' . $user_message;
		return 'multichat_gpt_response_' . md5( $data );
	}

	/**
	 * Get cached response
	 *
	 * @param string $cache_key Cache key
	 * @return string|false Cached response or false
	 */
	private function get_cached_response( $cache_key ) {
		return get_transient( $cache_key );
	}

	/**
	 * Cache API response
	 *
	 * @param string $cache_key Cache key
	 * @param string $response  Response to cache
	 * @return bool Whether the cache was set successfully
	 */
	private function cache_response( $cache_key, $response ) {
		$this->logger->debug( 'Caching API response', array( 'cache_key' => $cache_key ) );
		return set_transient( $cache_key, $response, $this->cache_expiration );
	}

	/**
	 * Clear all cached responses
	 *
	 * @return void
	 */
	public function clear_cache() {
		global $wpdb;

		$this->logger->info( 'Clearing all API response caches' );

		// Delete all transients with our prefix
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_multichat_gpt_response_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_multichat_gpt_response_' ) . '%'
			)
		);
	}
}
