<?php
/**
 * Plugin Name: MultiChat GPT
 * Plugin URI: https://example.com/multichat-gpt
 * Description: ChatGPT-powered multilingual chat widget for WordPress Multisite + WPML (Optimized)
 * Version: 1.1.0
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
define( 'MULTICHAT_GPT_VERSION', '1.1.0' );
define( 'MULTICHAT_GPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MULTICHAT_GPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MULTICHAT_GPT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load plugin class files and initialize
 */
require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-multichat-gpt.php';

// Initialize the plugin
MultiChat_GPT_Plugin::get_instance();