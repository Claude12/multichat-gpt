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
 * Autoload classes
 */
spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'MultiChat_GPT_' ) !== 0 ) {
			return;
		}

		$class_file = strtolower( str_replace( '_', '-', $class ) );
		$file_path  = MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-' . str_replace( 'multichat-gpt-', '', $class_file ) . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);

/**
 * Main plugin class
 */
class MultiChat_GPT {

	/**
	 * Instance of the class
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get instance of the class
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Load text domain for translations
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Register REST API endpoints
		add_action( 'rest_api_init', array( 'MultiChat_GPT_REST_Endpoints', 'register_endpoints' ) );

		// Initialize admin settings
		MultiChat_GPT_Admin_Settings::init();

		// Initialize widget manager
		MultiChat_GPT_Widget_Manager::init();

		// Activation/Deactivation hooks
		register_activation_hook( __FILE__, array( __CLASS__, 'activate_plugin' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate_plugin' ) );
	}

	/**
	 * Load plugin text domain for translations
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'multichat-gpt',
			false,
			dirname( MULTICHAT_GPT_BASENAME ) . '/languages'
		);
	}

	/**
	 * Activate plugin
	 *
	 * @return void
	 */
	public static function activate_plugin(): void {
		// Create necessary options
		if ( ! get_option( 'multichat_gpt_api_key' ) ) {
			add_option( 'multichat_gpt_api_key', '' );
		}
		if ( ! get_option( 'multichat_gpt_widget_position' ) ) {
			add_option( 'multichat_gpt_widget_position', 'bottom-right' );
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		MultiChat_GPT_Logger::info( 'Plugin activated' );
	}

	/**
	 * Deactivate plugin
	 *
	 * @return void
	 */
	public static function deactivate_plugin(): void {
		// Clean up transients
		MultiChat_GPT_API_Handler::clear_cache();
		MultiChat_GPT_Knowledge_Base::clear_cache();

		// Flush rewrite rules
		flush_rewrite_rules();

		MultiChat_GPT_Logger::info( 'Plugin deactivated' );
	}
}

// Initialize the plugin
MultiChat_GPT::get_instance();