<?php
/**
 * REST Endpoints Class
 *
 * Handles REST API route registration and request handling with rate limiting.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_REST_Endpoints class.
 *
 * Manages REST API endpoints with security and rate limiting.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_REST_Endpoints {

	/**
	 * API Handler instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_API_Handler
	 */
	private $api_handler;

	/**
	 * Knowledge Base instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_Knowledge_Base
	 */
	private $knowledge_base;

	/**
	 * Logger instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

	/**
	 * Rate limit: requests per minute per IP
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $rate_limit = 10;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param MultiChat_GPT_API_Handler    $api_handler    API handler instance.
	 * @param MultiChat_GPT_Knowledge_Base $knowledge_base Knowledge base instance.
	 * @param MultiChat_GPT_Logger         $logger         Logger instance.
	 */
	public function __construct( $api_handler, $knowledge_base, $logger ) {
		$this->api_handler    = $api_handler;
		$this->knowledge_base = $knowledge_base;
		$this->logger         = $logger;
	}

	/**
	 * Register REST API endpoints
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'multichat/v1',
			'/ask',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_chat_request' ],
				'permission_callback' => '__return_true',
				'args'                => $this->get_endpoint_args(),
			]
		);
	}

	/**
	 * Get endpoint arguments with validation
	 *
	 * @since 1.0.0
	 * @return array Endpoint arguments.
	 */
	private function get_endpoint_args() {
		return [
			'message'  => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_message' ],
				'description'       => __( 'User message to send to the chatbot', 'multichat-gpt' ),
			],
			'language' => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'en',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_language' ],
				'description'       => __( 'Language code (en, ar, es, fr)', 'multichat-gpt' ),
			],
		];
	}

	/**
	 * Validate message parameter
	 *
	 * @since 1.0.0
	 * @param string          $value   The parameter value.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_message( $value, $request, $param ) {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return new WP_Error(
				'invalid_message',
				__( 'Message must be a non-empty string', 'multichat-gpt' ),
				[ 'status' => 400 ]
			);
		}

		// Check message length (max 2000 characters).
		if ( strlen( $value ) > 2000 ) {
			return new WP_Error(
				'message_too_long',
				__( 'Message must not exceed 2000 characters', 'multichat-gpt' ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Validate language parameter
	 *
	 * @since 1.0.0
	 * @param string          $value   The parameter value.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_language( $value, $request, $param ) {
		$allowed_languages = [ 'en', 'ar', 'es', 'fr' ];

		if ( ! in_array( $value, $allowed_languages, true ) ) {
			return new WP_Error(
				'invalid_language',
				sprintf(
					/* translators: %s: comma-separated list of allowed languages */
					__( 'Language must be one of: %s', 'multichat-gpt' ),
					implode( ', ', $allowed_languages )
				),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Handle chat request from frontend
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function handle_chat_request( $request ) {
		// Check rate limit.
		$rate_limit_check = $this->check_rate_limit( $request );
		if ( is_wp_error( $rate_limit_check ) ) {
			return rest_ensure_response( $rate_limit_check );
		}

		// Get parameters (already validated and sanitized).
		$user_message = $request->get_param( 'message' );
		$language     = $request->get_param( 'language' );

		// Get API key from settings.
		$api_key = get_option( 'multichat_gpt_api_key' );
		if ( empty( $api_key ) ) {
			$this->logger->error( 'API key not configured in settings' );
			return new WP_Error(
				'api_key_missing',
				__( 'ChatGPT API key is not configured. Please contact the site administrator.', 'multichat-gpt' ),
				[ 'status' => 500 ]
			);
		}

		// Retrieve knowledge base for the language.
		$kb_chunks = $this->knowledge_base->get_chunks( $language );

		// Find relevant KB chunks based on user message.
		$relevant_chunks = $this->knowledge_base->find_relevant_chunks( $user_message, $kb_chunks );

		// Build the ChatGPT prompt.
		$system_message = $this->knowledge_base->build_system_message( $language, $relevant_chunks );

		// Call ChatGPT API.
		$response = $this->api_handler->call_chatgpt_api( $api_key, $system_message, $user_message );

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'ChatGPT API error',
				[
					'error'   => $response->get_error_message(),
					'message' => $user_message,
					'lang'    => $language,
				]
			);

			return new WP_Error(
				'api_error',
				__( 'Sorry, there was an error processing your request. Please try again later.', 'multichat-gpt' ),
				[ 'status' => 500 ]
			);
		}

		$this->logger->info(
			'Chat request processed successfully',
			[
				'language' => $language,
				'cached'   => false !== get_transient( 'multichat_gpt_' . md5( $system_message . '|' . $user_message ) ),
			]
		);

		return rest_ensure_response(
			[
				'success' => true,
				'message' => $response,
			]
		);
	}

	/**
	 * Check rate limit for request
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, WP_Error if rate limited.
	 */
	private function check_rate_limit( $request ) {
		$ip_address = $this->get_client_ip( $request );

		if ( empty( $ip_address ) ) {
			return true; // Can't rate limit without IP.
		}

		$transient_key = 'multichat_gpt_rl_' . md5( $ip_address );
		$requests      = get_transient( $transient_key );

		if ( false === $requests ) {
			$requests = 0;
		}

		/**
		 * Filter rate limit threshold.
		 *
		 * @since 1.0.0
		 * @param int    $rate_limit Rate limit (requests per minute).
		 * @param string $ip_address Client IP address.
		 */
		$rate_limit = apply_filters( 'multichat_gpt_rate_limit', $this->rate_limit, $ip_address );

		if ( $requests >= $rate_limit ) {
			$this->logger->warning(
				'Rate limit exceeded',
				[
					'ip'       => $ip_address,
					'requests' => $requests,
				]
			);

			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Too many requests. Please wait a moment before trying again.', 'multichat-gpt' ),
				[ 'status' => 429 ]
			);
		}

		// Increment counter.
		set_transient( $transient_key, $requests + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Get client IP address
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return string|null Client IP address or null if not available.
	 */
	private function get_client_ip( $request ) {
		$ip_address = null;

		// Check for various headers in priority order.
		$headers = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',  // Proxy.
			'HTTP_X_REAL_IP',        // Nginx proxy.
			'REMOTE_ADDR',           // Standard.
		];

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$ip_address = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// If X-Forwarded-For, get first IP.
				if ( 'HTTP_X_FORWARDED_FOR' === $header && strpos( $ip_address, ',' ) !== false ) {
					$ip_parts   = explode( ',', $ip_address );
					$ip_address = trim( $ip_parts[0] );
				}

				break;
			}
		}

		// Validate IP address.
		if ( $ip_address && filter_var( $ip_address, FILTER_VALIDATE_IP ) ) {
			return $ip_address;
		}

		return null;
	}
}
