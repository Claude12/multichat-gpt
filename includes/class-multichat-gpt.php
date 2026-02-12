<?php
/**
 * Main Plugin Class
 *
 * Core plugin class that initializes all components
 *
 * @package MultiChatGPT
 * @since 1.1.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_Plugin class
 *
 * Main plugin orchestrator
 */
class MultiChat_GPT_Plugin {

	/**
	 * Instance of the class
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Logger instance
	 *
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

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
	 * REST Endpoints instance
	 *
	 * @var MultiChat_GPT_REST_Endpoints
	 */
	private $rest_endpoints;

	/**
	 * Widget Manager instance
	 *
	 * @var MultiChat_GPT_Widget_Manager
	 */
	private $widget_manager;

	/**
	 * Admin Settings instance
	 *
	 * @var MultiChat_GPT_Admin_Settings
	 */
	private $admin_settings;

	/**
	 * Get instance of the class (Singleton pattern)
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize plugin components
	 */
	private function __construct() {
		// Load dependencies
		$this->load_dependencies();

		// Initialize components
		$this->init_components();

		// Hook into WordPress
		$this->setup_hooks();
	}

	/**
	 * Load required class files
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-logger.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-api-handler.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-knowledge-base.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-rest-endpoints.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-widget-manager.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-admin-settings.php';
	}

	/**
	 * Initialize plugin components
	 *
	 * @return void
	 */
	private function init_components() {
		// Initialize logger first
		$this->logger = new MultiChat_GPT_Logger();

		// Initialize API handler with logger
		$this->api_handler = new MultiChat_GPT_API_Handler( $this->logger );

		// Initialize knowledge base with logger
		$this->knowledge_base = new MultiChat_GPT_Knowledge_Base( $this->logger );

		// Initialize REST endpoints with dependencies
		$this->rest_endpoints = new MultiChat_GPT_REST_Endpoints(
			$this->api_handler,
			$this->knowledge_base,
			$this->logger
		);

		// Initialize widget manager with logger
		$this->widget_manager = new MultiChat_GPT_Widget_Manager( $this->logger );

		// Initialize admin settings with dependencies
		$this->admin_settings = new MultiChat_GPT_Admin_Settings(
			$this->logger,
			$this->api_handler,
			$this->knowledge_base
		);

		$this->logger->info( 'Plugin components initialized' );
	}

	/**
	 * Setup WordPress hooks
	 *
	 * @return void
	 */
	private function setup_hooks() {
		// Load text domain for translations
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Register REST API endpoints
		add_action( 'rest_api_init', array( $this->rest_endpoints, 'register_endpoints' ) );

		// Enqueue frontend assets
		add_action( 'wp_enqueue_scripts', array( $this->widget_manager, 'enqueue_assets' ) );

		// Add admin settings page
		add_action( 'admin_menu', array( $this->admin_settings, 'add_menu' ) );
		add_action( 'admin_init', array( $this->admin_settings, 'register_settings' ) );

		// Activation/Deactivation hooks
		register_activation_hook( MULTICHAT_GPT_PLUGIN_DIR . 'multichat-gpt.php', array( $this, 'activate' ) );
		register_deactivation_hook( MULTICHAT_GPT_PLUGIN_DIR . 'multichat-gpt.php', array( $this, 'deactivate' ) );
	}

	/**
	 * Load plugin text domain for translations
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'multichat-gpt',
			false,
			dirname( MULTICHAT_GPT_BASENAME ) . '/languages'
		);

		$this->logger->debug( 'Text domain loaded' );
	}

	/**
	 * Activate plugin
	 *
	 * @return void
	 */
	public function activate() {
		// Create necessary options
		if ( ! get_option( 'multichat_gpt_api_key' ) ) {
			add_option( 'multichat_gpt_api_key', '' );
		}
		
		if ( ! get_option( 'multichat_gpt_widget_position' ) ) {
			add_option( 'multichat_gpt_widget_position', 'bottom-right' );
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		$this->logger->info( 'Plugin activated' );
	}

	/**
	 * Deactivate plugin
	 *
	 * @return void
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();

		$this->logger->info( 'Plugin deactivated' );
	}

	/**
	 * Get logger instance
	 *
	 * @return MultiChat_GPT_Logger
	 */
	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Get API handler instance
	 *
	 * @return MultiChat_GPT_API_Handler
	 */
	public function get_api_handler() {
		return $this->api_handler;
	}

	/**
	 * Get knowledge base instance
	 *
	 * @return MultiChat_GPT_Knowledge_Base
	 */
	public function get_knowledge_base() {
		return $this->knowledge_base;
	}
}
