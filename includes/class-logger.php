<?php
/**
 * Logger Class
 *
 * Handles error logging and debugging for the MultiChat GPT plugin
 *
 * @package MultiChatGPT
 * @since 1.1.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_Logger class
 *
 * Provides logging functionality with different log levels
 */
class MultiChat_GPT_Logger {

	/**
	 * Log levels
	 */
	const LEVEL_ERROR   = 'error';
	const LEVEL_WARNING = 'warning';
	const LEVEL_INFO    = 'info';
	const LEVEL_DEBUG   = 'debug';

	/**
	 * Plugin slug for option prefix
	 *
	 * @var string
	 */
	private $plugin_slug = 'multichat_gpt';

	/**
	 * Log an error message
	 *
	 * @param string $message Error message
	 * @param array  $context Additional context data
	 * @return void
	 */
	public function error( $message, $context = array() ) {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a warning message
	 *
	 * @param string $message Warning message
	 * @param array  $context Additional context data
	 * @return void
	 */
	public function warning( $message, $context = array() ) {
		$this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an info message
	 *
	 * @param string $message Info message
	 * @param array  $context Additional context data
	 * @return void
	 */
	public function info( $message, $context = array() ) {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a debug message
	 *
	 * @param string $message Debug message
	 * @param array  $context Additional context data
	 * @return void
	 */
	public function debug( $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		$this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Log a message with a specific level
	 *
	 * @param string $level   Log level
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @return void
	 */
	private function log( $level, $message, $context = array() ) {
		// Format the log entry
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
		);

		// Use WordPress error_log if available and WP_DEBUG_LOG is enabled
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// Format as string for error_log
			$log_string = sprintf(
				'[%s] [%s] %s',
				$log_entry['timestamp'],
				strtoupper( $level ),
				$message
			);

			if ( ! empty( $context ) ) {
				$log_string .= ' | Context: ' . wp_json_encode( $context );
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[MultiChat GPT] ' . $log_string );
		}

		/**
		 * Fire action for custom logging handlers
		 *
		 * @param string $level   Log level
		 * @param string $message Log message
		 * @param array  $context Additional context data
		 */
		do_action( 'multichat_gpt_log', $level, $message, $context );
	}

	/**
	 * Log an API error
	 *
	 * @param string $endpoint API endpoint
	 * @param string $error    Error message
	 * @param array  $details  Additional details
	 * @return void
	 */
	public function log_api_error( $endpoint, $error, $details = array() ) {
		$context = array_merge(
			array(
				'endpoint' => $endpoint,
				'error'    => $error,
			),
			$details
		);

		$this->error( 'API Error', $context );
	}

	/**
	 * Log a rate limit event
	 *
	 * @param string $identifier Request identifier (IP or user ID)
	 * @param int    $limit      Rate limit threshold
	 * @return void
	 */
	public function log_rate_limit( $identifier, $limit ) {
		$this->warning(
			'Rate limit exceeded',
			array(
				'identifier' => $identifier,
				'limit'      => $limit,
			)
		);
	}
}
