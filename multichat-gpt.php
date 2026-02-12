<?php
/**
 * Plugin Name: MultiChat GPT
 * Plugin URI: https://example.com/multichat-gpt
 * Description: ChatGPT-powered multilingual chat widget for WordPress Multisite + WPML
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multichat-gpt
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 *
 * @package MultiChatGPT
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin constants
 */
define( 'MULTICHAT_GPT_VERSION', '1.0.0' );
define( 'MULTICHAT_GPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MULTICHAT_GPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MULTICHAT_GPT_BASENAME', plugin_basename( __FILE__ ) );

// Load required class files.
require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/core/class-logger.php';
require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/api/class-api-handler.php';
require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/core/class-knowledge-base.php';
require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/api/class-rest-endpoints.php';
require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/core/class-widget-manager.php';
require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/admin/class-admin-settings.php';

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class MultiChat_GPT {

	/**
	 * Instance of the class
	 *
	 * @since 1.0.0
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Logger instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

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
	 * REST Endpoints instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_REST_Endpoints
	 */
	private $rest_endpoints;

	/**
	 * Widget Manager instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_Widget_Manager
	 */
	private $widget_manager;

	/**
	 * Admin Settings instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_Admin_Settings
	 */
	private $admin_settings;

	/**
	 * Get instance of the class
	 *
	 * @since 1.0.0
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Initialize core components.
		$this->init_components();

		// Load text domain for translations.
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Register REST API endpoints.
		add_action( 'rest_api_init', [ $this->rest_endpoints, 'register_routes' ] );

		// Enqueue frontend assets.
		add_action( 'wp_enqueue_scripts', [ $this->widget_manager, 'enqueue_assets' ] );

		// Add admin settings page.
		add_action( 'admin_menu', [ $this->admin_settings, 'add_menu' ] );
		add_action( 'admin_init', [ $this->admin_settings, 'register_settings' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_multichat_gpt_clear_cache', [ $this->admin_settings, 'handle_clear_cache' ] );

		// Activation/Deactivation hooks.
		register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate_plugin' ] );
	}

	/**
	 * Initialize plugin components
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_components() {
		// Initialize logger first as other components depend on it.
		$this->logger = new MultiChat_GPT_Logger();

		// Initialize API handler with caching and retry logic.
		$this->api_handler = new MultiChat_GPT_API_Handler( $this->logger );

		// Initialize knowledge base with caching.
		$this->knowledge_base = new MultiChat_GPT_Knowledge_Base( $this->logger );

		// Initialize REST endpoints with rate limiting.
		$this->rest_endpoints = new MultiChat_GPT_REST_Endpoints(
			$this->api_handler,
			$this->knowledge_base,
			$this->logger
		);

		// Initialize widget manager.
		$this->widget_manager = new MultiChat_GPT_Widget_Manager( $this->logger );

		// Initialize admin settings.
		$this->admin_settings = new MultiChat_GPT_Admin_Settings(
			$this->api_handler,
			$this->knowledge_base,
			$this->logger
		);
	}

	/**
	 * Load plugin text domain for translations
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'multichat-gpt',
			false,
			dirname( MULTICHAT_GPT_BASENAME ) . '/languages'
		);
	}

	/**
	 * Activate plugin
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate_plugin() {
		// Create necessary options.
		if ( ! get_option( 'multichat_gpt_api_key' ) ) {
			add_option( 'multichat_gpt_api_key', '' );
		}
		if ( ! get_option( 'multichat_gpt_widget_position' ) ) {
			add_option( 'multichat_gpt_widget_position', 'bottom-right' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Deactivate plugin
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate_plugin() {
		// Clean up if needed.
		flush_rewrite_rules();
	}
}

// Initialize the plugin.
MultiChat_GPT::get_instance();