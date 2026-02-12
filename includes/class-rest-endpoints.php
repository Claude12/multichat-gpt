<?php
/**
 * REST Endpoints Class
 *
 * Manages REST API endpoints with rate limiting and validation
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MultiChat_GPT_REST_Endpoints
 *
 * Handles REST API endpoint registration and request handling
 */
class MultiChat_GPT_REST_Endpoints {

	/**
	 * Rate limit: Maximum requests per time window
	 *
	 * @var int
	 */
	private const RATE_LIMIT_REQUESTS = 10;

	/**
	 * Rate limit: Time window in seconds (60 seconds = 1 minute)
	 *
	 * @var int
	 */
	private const RATE_LIMIT_WINDOW = 60;

	/**
	 * Register REST API endpoints
	 *
	 * @return void
	 */
	public static function register_endpoints(): void {
		register_rest_route(
			'multichat/v1',
			'/ask',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_chat_request' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => array(
					'message'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_message' ),
					),
					'language' => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => 'en',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_language' ),
					),
				),
			)
		);
	}

	/**
	 * Validate message parameter
	 *
	 * @param string          $value   Message value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_message( $value, $request, $param ) {
		if ( empty( $value ) ) {
			return new WP_Error(
				'invalid_message',
				__( 'Message cannot be empty', 'multichat-gpt' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $value ) > 1000 ) {
			return new WP_Error(
				'message_too_long',
				__( 'Message cannot exceed 1000 characters', 'multichat-gpt' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate language parameter
	 *
	 * @param string          $value   Language value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_language( $value, $request, $param ) {
		if ( ! MultiChat_GPT_Utility::is_language_supported( $value ) ) {
			return new WP_Error(
				'invalid_language',
				/* translators: %s: allowed languages */
				sprintf(
					__( 'Language must be one of: %s', 'multichat-gpt' ),
					implode( ', ', MultiChat_GPT_Utility::get_supported_languages() )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Handle chat request
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public static function handle_chat_request( WP_REST_Request $request ) {
		// Check rate limit
		$rate_limit_check = self::check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			MultiChat_GPT_Logger::warning(
				'Rate limit exceeded',
				array( 'ip' => MultiChat_GPT_Utility::get_client_ip() )
			);
			return $rate_limit_check;
		}

		// Get parameters (already sanitized by REST API)
		$user_message = $request->get_param( 'message' );
		$language     = $request->get_param( 'language' );

		// Get API key from settings
		$api_key = get_option( 'multichat_gpt_api_key' );
		if ( empty( $api_key ) ) {
			MultiChat_GPT_Logger::error( 'API key not configured' );
			return new WP_Error(
				'api_key_missing',
				__( 'API key not configured. Please contact the site administrator.', 'multichat-gpt' ),
				array( 'status' => 500 )
			);
		}

		// Retrieve knowledge base for the language
		$kb_chunks = MultiChat_GPT_Knowledge_Base::get_chunks( $language );

		// Find relevant KB chunks based on user message
		$relevant_chunks = MultiChat_GPT_Knowledge_Base::find_relevant_chunks( $user_message, $kb_chunks );

		// Build the ChatGPT prompt
		$system_message = MultiChat_GPT_Knowledge_Base::build_system_message( $language, $relevant_chunks );

		// Call ChatGPT API
		$response = MultiChat_GPT_API_Handler::call_api( $api_key, $system_message, $user_message );

		if ( is_wp_error( $response ) ) {
			// Log the error
			MultiChat_GPT_Logger::error(
				'Chat request failed',
				array(
					'error_code'    => $response->get_error_code(),
					'error_message' => $response->get_error_message(),
					'language'      => $language,
				)
			);

			// Return user-friendly error
			return new WP_Error(
				$response->get_error_code(),
				/* translators: %s: error message */
				sprintf( __( 'Chat error: %s', 'multichat-gpt' ), $response->get_error_message() ),
				array( 'status' => 500 )
			);
		}

		// Log successful request
		MultiChat_GPT_Logger::info(
			'Chat request successful',
			array(
				'language'      => $language,
				'message_chars' => strlen( $user_message ),
			)
		);

		// Return success response
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
	 * @return bool|WP_Error True if within limit, WP_Error if exceeded.
	 */
	private static function check_rate_limit() {
		$client_ip = MultiChat_GPT_Utility::get_client_ip();
		$cache_key = 'multichat_rate_limit_' . md5( $client_ip );

		$request_log = get_transient( $cache_key );

		if ( false === $request_log ) {
			// First request in this window
			$request_log = array( time() );
		} else {
			// Filter out requests outside the time window
			$current_time = time();
			$request_log  = array_filter(
				$request_log,
				function ( $timestamp ) use ( $current_time ) {
					return ( $current_time - $timestamp ) < self::RATE_LIMIT_WINDOW;
				}
			);

			// Check if limit exceeded
			if ( count( $request_log ) >= self::RATE_LIMIT_REQUESTS ) {
				return new WP_Error(
					'rate_limit_exceeded',
					/* translators: 1: number of requests, 2: time window in seconds */
					sprintf(
						__( 'Rate limit exceeded. Maximum %1$d requests per %2$d seconds allowed.', 'multichat-gpt' ),
						self::RATE_LIMIT_REQUESTS,
						self::RATE_LIMIT_WINDOW
					),
					array( 'status' => 429 )
				);
			}

			// Add current request
			$request_log[] = $current_time;
		}

		// Update transient
		set_transient( $cache_key, $request_log, self::RATE_LIMIT_WINDOW );

		return true;
	}
}
