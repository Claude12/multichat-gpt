<?php
/**
 * REST Endpoints Class
 *
 * REST API endpoint registration and handling with rate limiting.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST Endpoints class for API management.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_REST_Endpoints {

	/**
	 * API Handler instance
	 *
	 * @var MultiChat_GPT_API_Handler
	 */
	private $api_handler;

	/**
	 * Knowledge Base instance
	 *
	 * @var MultiChat_GPT_Knowledge_Base
	 */
	private $knowledge_base;

	/**
	 * Rate limit: requests per minute
	 *
	 * @var int
	 */
	private $rate_limit = 10;

	/**
	 * Rate limit window in seconds
	 *
	 * @var int
	 */
	private $rate_limit_window = 60;

	/**
	 * Constructor
	 *
	 * @param MultiChat_GPT_API_Handler    $api_handler    API Handler instance.
	 * @param MultiChat_GPT_Knowledge_Base $knowledge_base Knowledge Base instance.
	 */
	public function __construct( $api_handler, $knowledge_base ) {
		$this->api_handler = $api_handler;
		$this->knowledge_base = $knowledge_base;

		// Allow custom rate limit via filter.
		$this->rate_limit = (int) apply_filters( 'multichat_gpt_rate_limit', $this->rate_limit );
		$this->rate_limit_window = (int) apply_filters( 'multichat_gpt_rate_limit_window', $this->rate_limit_window );
	}

	/**
	 * Register REST API endpoints
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'multichat/v1',
			'/ask',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'message' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_message' ),
					),
					'language' => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => 'en',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Validate message parameter
	 *
	 * @param mixed           $value   The parameter value.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_message( $value, $request, $param ) {
		if ( empty( $value ) ) {
			return new WP_Error(
				'empty_message',
				__( 'Message cannot be empty', 'multichat-gpt' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $value ) > 1000 ) {
			return new WP_Error(
				'message_too_long',
				__( 'Message is too long. Maximum 1000 characters allowed.', 'multichat-gpt' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Handle chat request from frontend
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function handle_chat_request( $request ) {
		// Check rate limit.
		$rate_limit_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			MultiChat_GPT_Logger::warning( 'Rate limit exceeded', array( 'ip' => $this->get_client_ip() ) );
			return $rate_limit_check;
		}

		// Get parameters.
		$user_message = $request->get_param( 'message' );
		$language     = $request->get_param( 'language' );

		// Get API key from settings.
		$api_key = get_option( 'multichat_gpt_api_key' );
		if ( empty( $api_key ) ) {
			MultiChat_GPT_Logger::error( 'API key not configured' );
			return new WP_Error(
				'missing_api_key',
				__( 'API key is not configured. Please contact the site administrator.', 'multichat-gpt' ),
				array( 'status' => 500 )
			);
		}

		// Find relevant KB chunks.
		$relevant_chunks = $this->knowledge_base->find_relevant_chunks( $user_message, $language );

		// Build system message.
		$system_message = $this->knowledge_base->build_system_message( $language, $relevant_chunks );

		// Call ChatGPT API.
		$response = $this->api_handler->call_api( $api_key, $system_message, $user_message );

		if ( is_wp_error( $response ) ) {
			MultiChat_GPT_Logger::error(
				'API call failed',
				array(
					'error' => $response->get_error_message(),
					'code' => $response->get_error_code(),
				)
			);

			return new WP_Error(
				$response->get_error_code(),
				/* translators: %s: Error message */
				sprintf( __( 'API error: %s', 'multichat-gpt' ), $response->get_error_message() ),
				array( 'status' => 500 )
			);
		}

		// Return successful response.
		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $response,
			),
			200
		);
	}

	/**
	 * Check rate limit for current IP
	 *
	 * @return true|WP_Error True if within limit, WP_Error otherwise.
	 */
	private function check_rate_limit() {
		$ip = $this->get_client_ip();
		$transient_key = 'multichat_gpt_rate_' . md5( $ip );

		// Get current request count.
		$request_count = (int) get_transient( $transient_key );

		// Check if limit exceeded.
		if ( $request_count >= $this->rate_limit ) {
			return new WP_Error(
				'rate_limit_exceeded',
				/* translators: %d: Rate limit per minute */
				sprintf( __( 'Rate limit exceeded. Maximum %d requests per minute allowed.', 'multichat-gpt' ), $this->rate_limit ),
				array( 'status' => 429 )
			);
		}

		// Increment counter.
		$new_count = $request_count + 1;
		set_transient( $transient_key, $new_count, $this->rate_limit_window );

		return true;
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip = '';

		// Check for various proxy headers.
		$headers_to_check = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $headers_to_check as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				break;
			}
		}

		// Handle comma-separated IPs (from proxies).
		if ( strpos( $ip, ',' ) !== false ) {
			$ip_array = explode( ',', $ip );
			$ip = trim( $ip_array[0] );
		}

		// Validate IP address.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '0.0.0.0';
		}

		return $ip;
	}

	/**
	 * Clear rate limit data for an IP
	 *
	 * @param string $ip IP address to clear (optional, clears all if not provided).
	 * @return void
	 */
	public function clear_rate_limit( $ip = '' ) {
		if ( ! empty( $ip ) ) {
			$transient_key = 'multichat_gpt_rate_' . md5( $ip );
			delete_transient( $transient_key );
			MultiChat_GPT_Logger::info( 'Rate limit cleared for IP', array( 'ip' => $ip ) );
		} else {
			global $wpdb;

			// Clear all rate limit transients.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( '_transient_multichat_gpt_rate_' ) . '%'
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( '_transient_timeout_multichat_gpt_rate_' ) . '%'
				)
			);

			MultiChat_GPT_Logger::info( 'All rate limits cleared' );
		}
	}
}
