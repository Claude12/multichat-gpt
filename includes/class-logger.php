<?php
/**
 * Logger Class
 *
 * Centralized error logging system for MultiChat GPT.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class for centralized error logging.
 *
 * @since 1.0.0
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
	 * Whether debug mode is enabled
	 *
	 * @var bool
	 */
	private static $debug_mode = false;

	/**
	 * Initialize the logger
	 *
	 * @return void
	 */
	public static function init() {
		self::$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Log an error message
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function error( $message, $context = array() ) {
		self::log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a warning message
	 *
	 * @param string $message Warning message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function warning( $message, $context = array() ) {
		self::log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an info message
	 *
	 * @param string $message Info message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function info( $message, $context = array() ) {
		self::log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a debug message
	 *
	 * @param string $message Debug message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function debug( $message, $context = array() ) {
		if ( ! self::$debug_mode ) {
			return;
		}
		self::log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Core logging function
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private static function log( $level, $message, $context = array() ) {
		// Skip debug logs if debug mode is disabled.
		if ( self::LEVEL_DEBUG === $level && ! self::$debug_mode ) {
			return;
		}

		// Format the log entry.
		$log_entry = sprintf(
			'[%s] [%s] %s',
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( $level ),
			$message
		);

		// Add context if provided.
		if ( ! empty( $context ) ) {
			$log_entry .= ' | Context: ' . wp_json_encode( $context );
		}

		// Use error_log for persistent logging.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'MultiChat GPT: ' . $log_entry );

		/**
		 * Action hook for custom log handlers
		 *
		 * @param string $level   Log level.
		 * @param string $message Log message.
		 * @param array  $context Additional context data.
		 */
		do_action( 'multichat_gpt_log', $level, $message, $context );
	}

	/**
	 * Clear old log entries
	 *
	 * @return void
	 */
	public static function clear_old_logs() {
		// This is a placeholder for future implementation
		// Could be extended to clean up database-stored logs.
		do_action( 'multichat_gpt_clear_logs' );
	}
}

// Initialize logger.
MultiChat_GPT_Logger::init();
