<?php
/**
 * Logger Class
 *
 * Handles error and activity logging for the MultiChat GPT plugin.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_Logger class.
 *
 * Provides centralized logging functionality with different log levels
 * and WordPress integration.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_Logger {

	/**
	 * Log level constants
	 *
	 * @since 1.0.0
	 */
	const LEVEL_ERROR   = 'error';
	const LEVEL_WARNING = 'warning';
	const LEVEL_INFO    = 'info';
	const LEVEL_DEBUG   = 'debug';

	/**
	 * Maximum number of log entries to keep
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $max_log_entries = 1000;

	/**
	 * Option name for storing logs
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $log_option_name = 'multichat_gpt_logs';

	/**
	 * Whether logging is enabled
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $logging_enabled;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logging_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Log an error message
	 *
	 * @since 1.0.0
	 * @param string $message Error message to log.
	 * @param array  $context Optional. Additional context data.
	 * @return void
	 */
	public function error( $message, $context = [] ) {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a warning message
	 *
	 * @since 1.0.0
	 * @param string $message Warning message to log.
	 * @param array  $context Optional. Additional context data.
	 * @return void
	 */
	public function warning( $message, $context = [] ) {
		$this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an info message
	 *
	 * @since 1.0.0
	 * @param string $message Info message to log.
	 * @param array  $context Optional. Additional context data.
	 * @return void
	 */
	public function info( $message, $context = [] ) {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a debug message
	 *
	 * @since 1.0.0
	 * @param string $message Debug message to log.
	 * @param array  $context Optional. Additional context data.
	 * @return void
	 */
	public function debug( $message, $context = [] ) {
		$this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Core logging method
	 *
	 * @since 1.0.0
	 * @param string $level   Log level (error, warning, info, debug).
	 * @param string $message Message to log.
	 * @param array  $context Optional. Additional context data.
	 * @return void
	 */
	private function log( $level, $message, $context = [] ) {
		if ( ! $this->logging_enabled ) {
			return;
		}

		// Create log entry.
		$log_entry = [
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
		];

		// Also log to WordPress error log for errors.
		if ( self::LEVEL_ERROR === $level ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'MultiChat GPT Error: ' . $message . ( ! empty( $context ) ? ' - Context: ' . wp_json_encode( $context ) : '' ) );
		}

		/**
		 * Fires when a log entry is created.
		 *
		 * @since 1.0.0
		 * @param array $log_entry The log entry data.
		 */
		do_action( 'multichat_gpt_log', $log_entry );

		// Store in database for retrieval.
		$this->store_log_entry( $log_entry );
	}

	/**
	 * Store log entry in database
	 *
	 * @since 1.0.0
	 * @param array $log_entry Log entry to store.
	 * @return void
	 */
	private function store_log_entry( $log_entry ) {
		$logs = get_option( $this->log_option_name, [] );

		if ( ! is_array( $logs ) ) {
			$logs = [];
		}

		// Add new entry.
		array_unshift( $logs, $log_entry );

		// Limit size.
		if ( count( $logs ) > $this->max_log_entries ) {
			$logs = array_slice( $logs, 0, $this->max_log_entries );
		}

		update_option( $this->log_option_name, $logs, false );
	}

	/**
	 * Get recent log entries
	 *
	 * @since 1.0.0
	 * @param int    $limit Optional. Number of entries to retrieve. Default 100.
	 * @param string $level Optional. Filter by log level.
	 * @return array Array of log entries.
	 */
	public function get_logs( $limit = 100, $level = null ) {
		$logs = get_option( $this->log_option_name, [] );

		if ( ! is_array( $logs ) ) {
			return [];
		}

		// Filter by level if specified.
		if ( null !== $level ) {
			$logs = array_filter(
				$logs,
				function ( $entry ) use ( $level ) {
					return isset( $entry['level'] ) && $entry['level'] === $level;
				}
			);
		}

		// Limit results.
		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Clear all logs
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function clear_logs() {
		return delete_option( $this->log_option_name );
	}
}
