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

/**
 * Autoload plugin classes
 *
 * @param string $class_name Class name to load.
 * @return void
 */
function multichat_gpt_autoload( $class_name ) {
	// Check if it's our class.
	if ( 0 !== strpos( $class_name, 'MultiChat_GPT_' ) ) {
		return;
	}

	// Convert class name to file name.
	$class_file = strtolower( str_replace( '_', '-', $class_name ) );
	$class_file = str_replace( 'multichat-gpt-', 'class-', $class_file );
	$file_path  = MULTICHAT_GPT_PLUGIN_DIR . 'includes/' . $class_file . '.php';

	// Load the file if it exists.
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

spl_autoload_register( 'multichat_gpt_autoload' );

/**
 * Main plugin class - Lightweight bootstrap
 *
 * @since 1.0.0
 */
class MultiChat_GPT {

	/**
	 * Instance of the class
	 *
	 * @var self
	 */
	private static $instance = null;

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
	 * Get instance of the class
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
	public function __construct() {
		// Load dependencies.
		$this->load_dependencies();

		// Initialize components.
		$this->init_components();

		// Setup hooks.
		$this->setup_hooks();

		// Activation/Deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );
	}

	/**
	 * Load plugin dependencies
	 *
	 * @return void
	 */
	private function load_dependencies() {
		// Core classes are autoloaded via spl_autoload_register.
	}

	/**
	 * Initialize plugin components
	 *
	 * @return void
	 */
	private function init_components() {
		// Initialize API Handler.
		$this->api_handler = new MultiChat_GPT_API_Handler();

		// Initialize Knowledge Base.
		$this->knowledge_base = new MultiChat_GPT_Knowledge_Base();

		// Initialize REST Endpoints.
		$this->rest_endpoints = new MultiChat_GPT_REST_Endpoints( $this->api_handler, $this->knowledge_base );

		// Initialize Widget Manager.
		$this->widget_manager = new MultiChat_GPT_Widget_Manager();

		// Initialize Admin Settings.
		$this->admin_settings = new MultiChat_GPT_Admin_Settings();
	}

	/**
	 * Setup WordPress hooks
	 *
	 * @return void
	 */
	private function setup_hooks() {
		// Load text domain for translations.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Register REST API endpoints.
		add_action( 'rest_api_init', array( $this->rest_endpoints, 'register_routes' ) );

		// Initialize widget manager.
		add_action( 'init', array( $this->widget_manager, 'init' ) );

		// Initialize admin settings.
		if ( is_admin() ) {
			add_action( 'init', array( $this->admin_settings, 'init' ) );
		}

		// Clear cache action.
		add_action( 'multichat_gpt_clear_all_caches', array( $this, 'clear_all_caches' ) );
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
	}

	/**
	 * Clear all plugin caches
	 *
	 * @return void
	 */
	public function clear_all_caches() {
		if ( $this->api_handler ) {
			$this->api_handler->clear_cache();
		}

		if ( $this->knowledge_base ) {
			$this->knowledge_base->clear_cache();
		}

		if ( $this->rest_endpoints ) {
			$this->rest_endpoints->clear_rate_limit();
		}

		MultiChat_GPT_Logger::info( 'All caches cleared via admin action' );
	}

	/**
	 * Activate plugin
	 *
	 * @return void
	 */
	public static function activate_plugin() {
		// Create necessary options with defaults.
		if ( ! get_option( 'multichat_gpt_api_key' ) ) {
			add_option( 'multichat_gpt_api_key', '' );
		}
		if ( ! get_option( 'multichat_gpt_widget_position' ) ) {
			add_option( 'multichat_gpt_widget_position', 'bottom-right' );
		}
		if ( ! get_option( 'multichat_gpt_cache_ttl' ) ) {
			add_option( 'multichat_gpt_cache_ttl', 3600 );
		}
		if ( ! get_option( 'multichat_gpt_rate_limit' ) ) {
			add_option( 'multichat_gpt_rate_limit', 10 );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();

		MultiChat_GPT_Logger::info( 'Plugin activated' );
	}

	/**
	 * Deactivate plugin
	 *
	 * @return void
	 */
	public static function deactivate_plugin() {
		// Flush rewrite rules.
		flush_rewrite_rules();

		MultiChat_GPT_Logger::info( 'Plugin deactivated' );
	}
}

// Initialize the plugin.
MultiChat_GPT::get_instance();