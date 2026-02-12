<?php
/**
 * REST Endpoints Class
 *
 * Handles REST API endpoints with security and rate limiting
 *
 * @package MultiChatGPT
 * @since 1.1.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_REST_Endpoints class
 *
 * Manages REST API endpoints with rate limiting and validation
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
	 * Logger instance
	 *
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

	/**
	 * Rate limit - requests per minute
	 *
	 * @var int
	 */
	private $rate_limit = 10;

	/**
	 * Constructor
	 *
	 * @param MultiChat_GPT_API_Handler    $api_handler    API handler instance
	 * @param MultiChat_GPT_Knowledge_Base $knowledge_base Knowledge base instance
	 * @param MultiChat_GPT_Logger         $logger         Logger instance
	 */
	public function __construct( $api_handler, $knowledge_base, $logger ) {
		$this->api_handler    = $api_handler;
		$this->knowledge_base = $knowledge_base;
		$this->logger         = $logger;

		// Allow filtering of rate limit
		$this->rate_limit = apply_filters( 'multichat_gpt_rate_limit', $this->rate_limit );
	}

	/**
	 * Register REST API endpoints
	 *
	 * @return void
	 */
	public function register_endpoints() {
		register_rest_route(
			'multichat/v1',
			'/ask',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'message'  => array(
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
						'validate_callback' => array( $this, 'validate_language' ),
					),
				),
			)
		);
	}

	/**
	 * Validate message parameter
	 *
	 * @param string          $param   Message parameter
	 * @param WP_REST_Request $request Request object
	 * @param string          $key     Parameter key
	 * @return bool|WP_Error True if valid, WP_Error otherwise
	 */
	public function validate_message( $param, $request, $key ) {
		if ( empty( $param ) ) {
			return new WP_Error(
				'invalid_message',
				__( 'Message cannot be empty', 'multichat-gpt' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $param ) > 1000 ) {
			return new WP_Error(
				'message_too_long',
				__( 'Message is too long (max 1000 characters)', 'multichat-gpt' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate language parameter
	 *
	 * @param string          $param   Language parameter
	 * @param WP_REST_Request $request Request object
	 * @param string          $key     Parameter key
	 * @return bool|WP_Error True if valid, WP_Error otherwise
	 */
	public function validate_language( $param, $request, $key ) {
		$supported_languages = array( 'en', 'ar', 'es', 'fr' );

		if ( ! in_array( $param, $supported_languages, true ) ) {
			return new WP_Error(
				'invalid_language',
				sprintf(
					/* translators: %s: Comma-separated list of supported languages */
					__( 'Invalid language. Supported languages: %s', 'multichat-gpt' ),
					implode( ', ', $supported_languages )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Handle chat request from frontend
	 *
	 * @param WP_REST_Request $request REST request object
	 * @return WP_REST_Response|WP_Error Response object
	 */
	public function handle_chat_request( $request ) {
		// Check rate limit
		if ( ! $this->check_rate_limit() ) {
			$this->logger->log_rate_limit( $this->get_request_identifier(), $this->rate_limit );
			
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Rate limit exceeded. Please try again later.', 'multichat-gpt' ),
				),
				429
			);
		}

		// Get and validate parameters (already sanitized and validated by REST API)
		$user_message = $request->get_param( 'message' );
		$language     = $request->get_param( 'language' );

		// Get API key from settings
		$api_key = get_option( 'multichat_gpt_api_key' );
		if ( empty( $api_key ) ) {
			$this->logger->error( 'API key not configured' );
			
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'API key not configured', 'multichat-gpt' ),
				),
				500
			);
		}

		// Find relevant knowledge base chunks
		$relevant_chunks = $this->knowledge_base->find_relevant_chunks( $user_message, $language );

		// Build the ChatGPT prompt
		$system_message = $this->knowledge_base->build_system_message( $language, $relevant_chunks );

		// Call ChatGPT API
		$response = $this->api_handler->call_api( $api_key, $system_message, $user_message );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'API error: %s', 'multichat-gpt' ),
						$response->get_error_message()
					),
				),
				500
			);
		}

		// Increment rate limit counter
		$this->increment_rate_limit();

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $response,
			)
		);
	}

	/**
	 * Get request identifier for rate limiting (IP or user ID)
	 *
	 * @return string Request identifier
	 */
	private function get_request_identifier() {
		// Use user ID if logged in
		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}

		// Otherwise use IP address
		$ip = $this->get_client_ip();
		return 'ip_' . md5( $ip );
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Check if rate limit is exceeded
	 *
	 * @return bool True if request is allowed, false if rate limit exceeded
	 */
	private function check_rate_limit() {
		$identifier  = $this->get_request_identifier();
		$transient_key = 'multichat_gpt_rate_' . $identifier;
		$request_count = get_transient( $transient_key );

		if ( false === $request_count ) {
			return true;
		}

		return intval( $request_count ) < $this->rate_limit;
	}

	/**
	 * Increment rate limit counter
	 *
	 * @return void
	 */
	private function increment_rate_limit() {
		$identifier    = $this->get_request_identifier();
		$transient_key = 'multichat_gpt_rate_' . $identifier;
		$request_count = get_transient( $transient_key );

		if ( false === $request_count ) {
			set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
		} else {
			set_transient( $transient_key, intval( $request_count ) + 1, MINUTE_IN_SECONDS );
		}
	}
}
