<?php
/**
 * Plugin Name: MultiChat GPT
 * Description: A WordPress plugin for MultiChat integration with GPT.
 * Version: 1.0.0
 * Author: Claude12
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include main class file
require_once plugin_dir_path( __FILE__ ) . 'includes/class-multichat-gpt.php';

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    MultiChat_GPT::get_instance();
});
