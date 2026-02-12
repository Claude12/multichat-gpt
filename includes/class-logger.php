<?php
/**
 * Logger Class
 *
 * Centralized error logging for MultiChat GPT
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MultiChat_GPT_Logger
 *
 * Handles centralized logging for the plugin
 */
class MultiChat_GPT_Logger {

	/**
	 * Log levels
	 */
	private const LEVEL_ERROR   = 'error';
	private const LEVEL_WARNING = 'warning';
	private const LEVEL_INFO    = 'info';
	private const LEVEL_DEBUG   = 'debug';

	/**
	 * Maximum number of log entries to store
	 *
	 * @var int
	 */
	private const MAX_LOG_ENTRIES = 100;

	/**
	 * Log option name
	 *
	 * @var string
	 */
	private const LOG_OPTION = 'multichat_gpt_logs';

	/**
	 * Log an error message
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a warning message
	 *
	 * @param string $message Warning message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an info message
	 *
	 * @param string $message Info message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a debug message (only in WP_DEBUG mode)
	 *
	 * @param string $message Debug message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function debug( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::log( self::LEVEL_DEBUG, $message, $context );
		}
	}

	/**
	 * Log a message to the database
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private static function log( string $level, string $message, array $context = array() ): void {
		$logs = get_option( self::LOG_OPTION, array() );

		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
			'ip'        => MultiChat_GPT_Utility::get_client_ip(),
		);

		// Add to beginning of array
		array_unshift( $logs, $log_entry );

		// Limit log size
		if ( count( $logs ) > self::MAX_LOG_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_LOG_ENTRIES );
		}

		update_option( self::LOG_OPTION, $logs, false );

		// Also log to PHP error log in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[MultiChat GPT] [%s] %s %s',
					strtoupper( $level ),
					$message,
					! empty( $context ) ? wp_json_encode( $context ) : ''
				)
			);
		}
	}

	/**
	 * Get all log entries
	 *
	 * @param int    $limit Maximum number of entries to retrieve.
	 * @param string $level Filter by log level (optional).
	 * @return array Log entries.
	 */
	public static function get_logs( int $limit = 50, string $level = '' ): array {
		$logs = get_option( self::LOG_OPTION, array() );

		if ( ! empty( $level ) ) {
			$logs = array_filter(
				$logs,
				function ( $log ) use ( $level ) {
					return $log['level'] === $level;
				}
			);
		}

		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Clear all logs
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_logs(): bool {
		return delete_option( self::LOG_OPTION );
	}
}
