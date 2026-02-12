<?php
/**
 * Plugin Name: MultiChat GPT
 * Plugin URI: https://example.com/multichat-gpt
 * Description: ChatGPT-powered multilingual chat widget for WordPress Multisite + WPML
 * Version: 1.2.1
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
define( 'MULTICHAT_GPT_VERSION', '1.2.1' );
define( 'MULTICHAT_GPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MULTICHAT_GPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MULTICHAT_GPT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class MultiChat_GPT {

	/**
	 * Instance of the class
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * ChatGPT API Endpoint
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

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
	 * Constructor
	 */
	public function __construct() {
		// Load text domain for translations
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Load helper classes
		$this->load_classes();

		// Register FAQ post type
		add_action( 'init', [ 'MultiChat_FAQ_Manager', 'register_post_type' ] );

		// Register REST API endpoint
		add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );

		// Enqueue frontend assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// Add admin settings page
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_admin_settings' ] );

		// Handle AJAX requests
		add_action( 'wp_ajax_multichat_scan_sitemap', [ $this, 'handle_scan_sitemap' ] );
		add_action( 'wp_ajax_multichat_scan_language', [ $this, 'handle_scan_language' ] );
		add_action( 'wp_ajax_multichat_clear_cache', [ $this, 'handle_clear_cache' ] );
		add_action( 'wp_ajax_multichat_clear_language_cache', [ $this, 'handle_clear_language_cache' ] );
		add_action( 'wp_ajax_multichat_create_faq', [ $this, 'handle_create_faq' ] );
		add_action( 'wp_ajax_multichat_delete_faq', [ $this, 'handle_delete_faq' ] );
		add_action( 'wp_ajax_multichat_get_faqs', [ $this, 'handle_get_faqs' ] );
		add_action( 'wp_ajax_nopriv_multichat_get_faqs', [ $this, 'handle_get_faqs' ] );

		// Activation/Deactivation hooks
		register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate_plugin' ] );
	}

	/**
	 * Load helper classes
	 */
	private function load_classes() {
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-sitemap-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-content-crawler.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-faq-manager.php';
	}

	/**
	 * Load plugin text domain for translations
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'multichat-gpt',
			false,
			dirname( MULTICHAT_GPT_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register REST API endpoints
	 */
	public function register_rest_endpoints() {
		// Chat endpoint
		register_rest_route(
			'multichat/v1',
			'/ask',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_chat_request' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'message' => [
						'type'     => 'string',
						'required' => true,
					],
					'language' => [
						'type'     => 'string',
						'required' => false,
						'default'  => 'en',
					],
				],
			]
		);

		// Public FAQ endpoint for frontend chat
		register_rest_route(
			'multichat/v1',
			'/faqs',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_rest_get_faqs' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'language' => [
						'type'     => 'string',
						'required' => false,
						'default'  => 'en',
					],
				],
			]
		);
	}

	/**
	 * Get combined knowledge base (Sitemap KB + FAQs)
	 */
	private function get_combined_knowledge_base( $language = 'en' ) {
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-faq-manager.php';

		$kb = [];

		// Get sitemap-based KB
		$sitemap_kb = MultiChat_KB_Builder::get_cached_knowledge_base( $language );
		if ( $sitemap_kb ) {
			$kb = array_merge( $kb, $sitemap_kb );
		}

		// Get custom FAQs
		$faqs = MultiChat_FAQ_Manager::get_language_faqs( $language );
		$kb   = array_merge( $kb, $faqs );

		return $kb;
	}

	/**
	 * Handle chat request from frontend
	 *
	 * @param WP_REST_Request $request REST request object
	 * @return WP_REST_Response
	 */
	public function handle_chat_request( $request ) {
		// Get parameters
		$user_message = sanitize_text_field( $request->get_param( 'message' ) );
		$language     = sanitize_text_field( $request->get_param( 'language' ) );

		// Validate message
		if ( empty( $user_message ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Message cannot be empty', 'multichat-gpt' ),
				],
				400
			);
		}

		// Get API key from settings
		$api_key = get_option( 'multichat_gpt_api_key' );
		if ( empty( $api_key ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'API key not configured', 'multichat-gpt' ),
				],
				500
			);
		}

		// Get COMBINED knowledge base (Sitemap + FAQs)
		$kb_chunks = $this->get_combined_knowledge_base( $language );

		// If no KB available, use fallback
		if ( empty( $kb_chunks ) ) {
			$kb_chunks = $this->get_knowledge_base_chunks( $language );
		}

		// Find relevant KB chunks based on user message
		$relevant_chunks = $this->find_relevant_chunks( $user_message, $kb_chunks );

		// Build the ChatGPT prompt
		$system_message = $this->build_system_message( $language, $relevant_chunks );

		// Call ChatGPT API
		$response = $this->call_chatgpt_api( $api_key, $system_message, $user_message );

		// Handle API response
		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => $response->get_error_message(),
				],
				500
			);
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => $response,
			]
		);
	}

	/**
	 * Get static knowledge base chunks (fallback)
	 *
	 * @param string $language Language code
	 * @return array
	 */
	private function get_knowledge_base_chunks( $language = 'en' ) {
		$kb_data = [
			'en' => [
				[
					'title'   => 'Welcome',
					'content' => 'Welcome to our customer support. How can we help you today?',
				],
				[
					'title'   => 'Getting Started',
					'content' => 'To get started with our services, please visit our documentation.',
				],
			],
			'ar' => [
				[
					'title'   => 'أهلا وسهلا',
					'content' => 'أهلا وسهلا بك في دعم العملاء. كيف يمكننا مساعدتك اليوم؟',
				],
			],
			'fr' => [
				[
					'title'   => 'Bienvenue',
					'content' => 'Bienvenue dans notre support client. Comment pouvons-nous vous aider?',
				],
			],
		];

		return isset( $kb_data[ $language ] ) ? $kb_data[ $language ] : $kb_data['en'];
	}

	/**
	 * Find relevant KB chunks based on user message
	 *
	 * @param string $user_message User's message
	 * @param array  $kb_chunks Knowledge base chunks
	 * @return array Relevant chunks
	 */
	private function find_relevant_chunks( $user_message, $kb_chunks ) {
		if ( ! is_array( $kb_chunks ) || empty( $kb_chunks ) ) {
			return [];
		}

		$relevant = [];
		$user_words = array_filter( array_map( 'strtolower', preg_split( '/\s+/', $user_message ) ) );

		foreach ( $kb_chunks as $chunk ) {
			$chunk_text = isset( $chunk['content'] ) ? strtolower( $chunk['content'] ) : '';
			$chunk_text .= ' ' . ( isset( $chunk['title'] ) ? strtolower( $chunk['title'] ) : '' );

			$match_count = 0;
			foreach ( $user_words as $word ) {
				if ( strlen( $word ) > 3 && strpos( $chunk_text, $word ) !== false ) {
					$match_count++;
				}
			}

			if ( $match_count > 0 ) {
				$relevant[] = $chunk;
			}
		}

		// Return top 3 most relevant chunks
		return array_slice( $relevant, 0, 3 );
	}

	/**
	 * Build system message with KB context
	 *
	 * @param string $language Language code
	 * @param array  $relevant_chunks Relevant KB chunks
	 * @return string System message
	 */
	private function build_system_message( $language, $relevant_chunks ) {
		$lang_names = [
			'en' => 'English',
			'ar' => 'Arabic',
			'es' => 'Spanish',
			'fr' => 'French',
		];

		$lang_name = isset( $lang_names[ $language ] ) ? $lang_names[ $language ] : 'English';

		// Build KB content
		$kb_content = '';
		if ( ! empty( $relevant_chunks ) ) {
			$kb_content = "RELEVANT INFORMATION:\n";
			foreach ( $relevant_chunks as $chunk ) {
				$title   = isset( $chunk['title'] ) ? $chunk['title'] : 'Info';
				$content = isset( $chunk['content'] ) ? substr( $chunk['content'], 0, 500 ) : '';
				$kb_content .= "\n- $title: $content";
			}
		}

		return "You are a helpful customer support assistant. Answer only in {$lang_name}. Use the provided knowledge base to answer questions accurately and helpfully.\n\nKNOWLEDGE BASE:\n{$kb_content}\n\nIf the user's question is not covered in the knowledge base, politely let them know and offer to connect them with a human agent.";
	}

	/**
	 * Call ChatGPT API
	 *
	 * @param string $api_key OpenAI API key
	 * @param string $system_message System message for context
	 * @param string $user_message User's message
	 * @return string|WP_Error
	 */
	private function call_chatgpt_api( $api_key, $system_message, $user_message ) {
		$request_body = [
			'model'    => 'gpt-3.5-turbo',
			'messages' => [
				[
					'role'    => 'system',
					'content' => $system_message,
				],
				[
					'role'    => 'user',
					'content' => $user_message,
				],
			],
			'temperature' => 0.7,
			'max_tokens'  => 1000,
		];

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $request_body ),
			'timeout' => 30,
		];

		$response = wp_remote_post( $this->api_endpoint, $args );

		// Check for HTTP errors
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Get response body
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Check for API errors
		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'chatgpt_error', $data['error']['message'] ?? 'Unknown error' );
		}

		// Extract assistant's message
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return $data['choices'][0]['message']['content'];
		}

		return new WP_Error( 'chatgpt_error', 'Unexpected response format from ChatGPT API' );
	}

	/**
	 * Handle sitemap scan for all languages
	 */
	public function handle_scan_sitemap() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'multichat_scan_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-sitemap-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';

		// Get all language sitemaps
		if ( MultiChat_WPML_Scanner::is_wpml_active() ) {
			// Scan all languages
			$all_sitemaps = MultiChat_WPML_Scanner::get_all_language_sitemaps();
			$total_pages = 0;
			$languages_scanned = [];

			foreach ( $all_sitemaps as $lang_code => $sitemap_url ) {
				$external = strpos( $sitemap_url, home_url() ) === false;
				$urls = MultiChat_Sitemap_Scanner::get_urls_from_sitemap( $sitemap_url, $external );

				if ( ! empty( $urls ) ) {
					$kb = MultiChat_KB_Builder::build_kb_from_urls( $urls, $lang_code );
					$total_pages += count( $kb );
					$languages_scanned[] = [
						'language' => $lang_code,
						'pages'    => count( $kb ),
					];
				}
			}

			if ( empty( $languages_scanned ) ) {
				wp_send_json_error( 'No pages found in any sitemap' );
			}

			wp_send_json_success( [
				'message'           => sprintf( 'Successfully scanned %d pages across %d languages', $total_pages, count( $languages_scanned ) ),
				'total_pages'       => $total_pages,
				'languages_scanned' => $languages_scanned,
			] );
		} else {
			// Single language fallback
			$sitemap_url = isset( $_POST['sitemap_url'] ) ? esc_url_raw( $_POST['sitemap_url'] ) : site_url( '/sitemap.xml' );

			if ( empty( $sitemap_url ) ) {
				wp_send_json_error( 'Invalid sitemap URL' );
			}

			$external = strpos( $sitemap_url, home_url() ) === false;
			$urls = MultiChat_Sitemap_Scanner::get_urls_from_sitemap( $sitemap_url, $external );

			if ( empty( $urls ) ) {
				wp_send_json_error( 'No pages found in sitemap' );
			}

			$kb = MultiChat_KB_Builder::build_kb_from_urls( $urls, 'en' );

			if ( empty( $kb ) ) {
				wp_send_json_error( 'Failed to extract content from pages' );
			}

			wp_send_json_success( [
				'message'       => sprintf( 'Successfully scanned %d pages', count( $kb ) ),
				'pages_scanned' => count( $kb ),
			] );
		}
	}

	/**
	 * Handle language-specific scan
	 */
	public function handle_scan_language() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'multichat_scan_language_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$language = isset( $_POST['language'] ) ? sanitize_key( $_POST['language'] ) : 'en';

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-sitemap-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';

		// Get sitemap for the language
		$sitemap_url = MultiChat_WPML_Scanner::get_language_sitemap_url( $language );

		if ( empty( $sitemap_url ) ) {
			wp_send_json_error( 'Invalid language code' );
		}

		$external = strpos( $sitemap_url, home_url() ) === false;
		$urls = MultiChat_Sitemap_Scanner::get_urls_from_sitemap( $sitemap_url, $external );

		if ( empty( $urls ) ) {
			wp_send_json_error( 'No pages found in sitemap for this language' );
		}

		// Build KB for this language
		$kb = MultiChat_KB_Builder::build_kb_from_urls( $urls, $language );

		if ( empty( $kb ) ) {
			wp_send_json_error( 'Failed to extract content from pages' );
		}

		wp_send_json_success( [
			'message'       => sprintf( 'Successfully scanned %d pages for %s', count( $kb ), $language ),
			'pages_scanned' => count( $kb ),
			'language'      => $language,
		] );
	}

	/**
	 * Handle clear all caches
	 */
	public function handle_clear_cache() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'multichat_clear_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';

		if ( MultiChat_WPML_Scanner::is_wpml_active() ) {
			MultiChat_WPML_Scanner::clear_all_caches();
		} else {
			require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';
			MultiChat_KB_Builder::clear_cache();
		}

		wp_send_json_success( [
			'message' => __( 'All knowledge bases cleared successfully.', 'multichat-gpt' ),
		] );
	}

	/**
	 * Handle language-specific cache clear
	 */
	public function handle_clear_language_cache() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'multichat_clear_language_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$language = isset( $_POST['language'] ) ? sanitize_key( $_POST['language'] ) : 'en';

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';
		MultiChat_WPML_Scanner::clear_language_cache( $language );

		wp_send_json_success( [
			'message' => sprintf( __( '%s knowledge base cleared successfully.', 'multichat-gpt' ), $language ),
		] );
	}

	/**
	 * Handle create FAQ AJAX
	 */
	public function handle_create_faq() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'multichat_faq_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-faq-manager.php';

		$title    = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$content  = isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '';
		$language = isset( $_POST['language'] ) ? sanitize_key( $_POST['language'] ) : 'en';

		if ( empty( $title ) || empty( $content ) ) {
			wp_send_json_error( 'Title and content are required' );
		}

		$faq_id = MultiChat_FAQ_Manager::create_faq( $title, $content, $language );

		if ( is_wp_error( $faq_id ) ) {
			wp_send_json_error( $faq_id->get_error_message() );
		}

		wp_send_json_success( [
			'message' => __( 'FAQ created successfully', 'multichat-gpt' ),
			'faq_id'  => $faq_id,
		] );
	}

	/**
	 * Handle delete FAQ AJAX
	 */
	public function handle_delete_faq() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'multichat_faq_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-faq-manager.php';

		$faq_id = isset( $_POST['faq_id'] ) ? intval( $_POST['faq_id'] ) : 0;

		if ( ! $faq_id ) {
			wp_send_json_error( 'Invalid FAQ ID' );
		}

		MultiChat_FAQ_Manager::delete_faq( $faq_id );

		wp_send_json_success( [
			'message' => __( 'FAQ deleted successfully', 'multichat-gpt' ),
		] );
	}

	/**
	 * Handle get FAQs via REST API (PUBLIC - for frontend)
	 */
	public function handle_rest_get_faqs( $request ) {
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-faq-manager.php';

		$language = sanitize_key( $request->get_param( 'language' ) ?: 'en' );
		$faqs     = MultiChat_FAQ_Manager::get_language_faqs( $language );

		return new WP_REST_Response( $faqs );
	}

	/**
	 * Handle get FAQs AJAX (ADMIN ONLY - for admin panel)
	 */
	public function handle_get_faqs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-faq-manager.php';

		$language = isset( $_GET['language'] ) ? sanitize_key( $_GET['language'] ) : 'en';
		$faqs     = MultiChat_FAQ_Manager::get_language_faqs( $language );

		wp_send_json_success( $faqs );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Only enqueue on frontend, not admin
		if ( is_admin() ) {
			return;
		}

		// Enqueue widget CSS
		wp_enqueue_style(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/css/widget.css',
			[],
			MULTICHAT_GPT_VERSION
		);

		// Enqueue widget JS
		wp_enqueue_script(
			'multichat-gpt-widget',
			MULTICHAT_GPT_PLUGIN_URL . 'assets/js/widget.js',
			[],
			MULTICHAT_GPT_VERSION,
			true
		);

		// Localize script
		$current_language = $this->get_current_language();

		// Get translations from WPML
		$translations = [
			'chatTitle'        => apply_filters( 'wpml_translate_single_string', 'Chat Support', 'multichat-gpt', 'chat_title' ),
			'inputPlaceholder' => apply_filters( 'wpml_translate_single_string', 'Ask me anything...', 'multichat-gpt', 'input_placeholder' ),
			'sendButton'       => apply_filters( 'wpml_translate_single_string', 'Send', 'multichat-gpt', 'send_button' ),
			'loadingMessage'   => apply_filters( 'wpml_translate_single_string', 'Thinking...', 'multichat-gpt', 'loading_message' ),
			'errorMessage'     => apply_filters( 'wpml_translate_single_string', 'Sorry, I encountered an error. Please try again.', 'multichat-gpt', 'error_message' ),
		];

		wp_localize_script(
			'multichat-gpt-widget',
			'multiChatGPT',
			[
				'restUrl'      => esc_url( rest_url( 'multichat/v1/ask' ) ),
				'language'     => $current_language,
				'translations' => $translations,
			]
		);
	}

	/**
	 * Get current language
	 *
	 * @return string Language code
	 */
	private function get_current_language() {
		// Check for WPML
		if ( function_exists( 'wpml_current_language' ) ) {
			return wpml_current_language();
		}

		return 'en';
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'MultiChat GPT', 'multichat-gpt' ),
			__( 'MultiChat GPT', 'multichat-gpt' ),
			'manage_options',
			'multichat-gpt',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Register admin settings
	 */
	public function register_admin_settings() {
		register_setting( 'multichat_gpt_group', 'multichat_gpt_api_key' );
		register_setting( 'multichat_gpt_group', 'multichat_gpt_widget_position' );
		register_setting( 'multichat_gpt_group', 'multichat_gpt_sitemap_url' );

		add_settings_section(
			'multichat_gpt_section',
			__( 'Configuration', 'multichat-gpt' ),
			[ $this, 'render_settings_section' ],
			'multichat-gpt-settings'
		);

		add_settings_field(
			'multichat_gpt_api_key',
			__( 'OpenAI API Key', 'multichat-gpt' ),
			[ $this, 'render_api_key_field' ],
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		add_settings_field(
			'multichat_gpt_widget_position',
			__( 'Widget Position', 'multichat-gpt' ),
			[ $this, 'render_position_field' ],
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		add_settings_field(
			'multichat_gpt_sitemap_url',
			__( 'Sitemap URL', 'multichat-gpt' ),
			[ $this, 'render_sitemap_url_field' ],
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);
	}

	/**
	 * Render settings section
	 */
	public function render_settings_section() {
		?>
		<p><?php esc_html_e( 'Configure your MultiChat GPT plugin settings below.', 'multichat-gpt' ); ?></p>
		<?php
	}

	/**
	 * Render API key field
	 */
	public function render_api_key_field() {
		$api_key = get_option( 'multichat_gpt_api_key' );
		?>
		<input 
			type="password" 
			name="multichat_gpt_api_key" 
			value="<?php echo esc_attr( $api_key ); ?>" 
			style="width: 400px;"
		/>
		<?php
	}

	/**
	 * Render position field
	 */
	public function render_position_field() {
		$position = get_option( 'multichat_gpt_widget_position', 'bottom-right' );
		?>
		<select name="multichat_gpt_widget_position">
			<option value="bottom-right" <?php selected( $position, 'bottom-right' ); ?>>
				<?php esc_html_e( 'Bottom Right', 'multichat-gpt' ); ?>
			</option>
			<option value="bottom-left" <?php selected( $position, 'bottom-left' ); ?>>
				<?php esc_html_e( 'Bottom Left', 'multichat-gpt' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render sitemap URL field
	 */
	public function render_sitemap_url_field() {
		$sitemap_url = get_option( 'multichat_gpt_sitemap_url', site_url( '/sitemap.xml' ) );
		?>
		<input 
			type="url" 
			name="multichat_gpt_sitemap_url" 
			value="<?php echo esc_attr( $sitemap_url ); ?>" 
			style="width: 400px;"
		/>
		<p class="description">
			<?php esc_html_e( 'Leave blank to use default sitemap location', 'multichat-gpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-faq-manager.php';

		$is_wpml_active = MultiChat_WPML_Scanner::is_wpml_active();
		$sitemap_url = get_option( 'multichat_gpt_sitemap_url', site_url( '/sitemap.xml' ) );
		$current_language = $this->get_current_language();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MultiChat GPT Settings', 'multichat-gpt' ); ?></h1>

			<?php if ( $is_wpml_active ) : ?>
				<!-- WPML Multi-Language Interface -->
				<div style="background: #e3f2fd; padding: 20px; margin: 20px 0; border-left: 4px solid #2196f3;">
					<h2><?php esc_html_e( 'Multi-Language Knowledge Base', 'multichat-gpt' ); ?></h2>
					<p><?php esc_html_e( 'WPML detected. Manage knowledge bases for each language separately.', 'multichat-gpt' ); ?></p>

					<?php
					$stats = MultiChat_WPML_Scanner::get_multilingual_stats();
					$languages = MultiChat_WPML_Scanner::get_active_languages();
					?>

					<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
						<thead>
							<tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
								<th style="padding: 10px; text-align: left;"><?php esc_html_e( 'Language', 'multichat-gpt' ); ?></th>
								<th style="padding: 10px; text-align: center;"><?php esc_html_e( 'Status', 'multichat-gpt' ); ?></th>
								<th style="padding: 10px; text-align: center;"><?php esc_html_e( 'Pages Indexed', 'multichat-gpt' ); ?></th>
								<th style="padding: 10px; text-align: left;"><?php esc_html_e( 'Last Scanned', 'multichat-gpt' ); ?></th>
								<th style="padding: 10px; text-align: center;"><?php esc_html_e( 'Actions', 'multichat-gpt' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stats as $lang_code => $stat ) : ?>
								<tr style="border-bottom: 1px solid #eee;">
									<td style="padding: 10px;"><strong><?php echo esc_html( $stat['language'] ); ?></strong> (<?php echo esc_html( $stat['language_code'] ); ?>)</td>
									<td style="padding: 10px; text-align: center;">
										<?php
										if ( $stat['cached'] ) {
											echo '<span style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 3px;">✓ Cached</span>';
										} else {
											echo '<span style="background: #f44336; color: white; padding: 4px 8px; border-radius: 3px;">✗ Not Cached</span>';
										}
										?>
									</td>
									<td style="padding: 10px; text-align: center;"><strong><?php echo intval( $stat['pages_indexed'] ); ?></strong></td>
									<td style="padding: 10px;"><?php echo esc_html( $stat['last_scanned'] ); ?></td>
									<td style="padding: 10px; text-align: center;">
										<button class="multichat-scan-lang-btn button button-primary" data-language="<?php echo esc_attr( $lang_code ); ?>" style="padding: 4px 8px; font-size: 12px;">
											<?php esc_html_e( 'Scan', 'multichat-gpt' ); ?>
										</button>
										<?php if ( $stat['cached'] ) : ?>
											<button class="multichat-clear-lang-btn button button-secondary" data-language="<?php echo esc_attr( $lang_code ); ?>" style="padding: 4px 8px; font-size: 12px; margin-left: 5px;">
												<?php esc_html_e( 'Clear', 'multichat-gpt' ); ?>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p style="margin-top: 15px;">
						<button id="multichat-scan-all-btn" class="button button-primary" style="padding: 10px 20px; font-size: 14px; margin-right: 10px;">
							<?php esc_html_e( 'Scan All Languages', 'multichat-gpt' ); ?>
						</button>
						<button id="multichat-clear-all-btn" class="button button-secondary" style="padding: 10px 20px; font-size: 14px;">
							<?php esc_html_e( 'Clear All Caches', 'multichat-gpt' ); ?>
						</button>
					</p>
					<div id="multichat-status" style="margin-top: 15px; display: none;"></div>
				</div>
			<?php else : ?>
				<!-- Single Language Interface -->
				<div class="notice notice-info" style="margin-top: 20px; padding: 20px; border-left: 4px solid #2563eb;">
					<h3><?php esc_html_e( 'Knowledge Base Status', 'multichat-gpt' ); ?></h3>
					<?php
					$kb_stats = MultiChat_KB_Builder::get_kb_stats();
					$is_cached = MultiChat_KB_Builder::is_kb_cached();
					?>
					<table style="width: 100%; margin-top: 10px;">
						<tr>
							<td style="padding: 8px;"><strong><?php esc_html_e( 'Pages Indexed:', 'multichat-gpt' ); ?></strong></td>
							<td style="padding: 8px;"><span style="font-size: 18px; color: #2563eb; font-weight: bold;"><?php echo intval( $kb_stats['pages_indexed'] ); ?></span></td>
						</tr>
						<tr>
							<td style="padding: 8px;"><strong><?php esc_html_e( 'Cache Status:', 'multichat-gpt' ); ?></strong></td>
							<td style="padding: 8px;">
								<?php 
								if ( $is_cached ) {
									echo '<span style="color: #22c55e; font-weight: bold;">✓ ' . esc_html__( 'Active (Permanent)', 'multichat-gpt' ) . '</span>';
								} else {
									echo '<span style="color: #f59e0b; font-weight: bold;">⚠ ' . esc_html__( 'No cache', 'multichat-gpt' ) . '</span>';
								}
								?>
							</td>
						</tr>
						<tr>
							<td style="padding: 8px;"><strong><?php esc_html_e( 'Last Scanned:', 'multichat-gpt' ); ?></strong></td>
							<td style="padding: 8px;"><?php echo esc_html( $kb_stats['last_scanned'] ); ?></td>
						</tr>
					</table>
				</div>

				<div style="background: #f1f1f1; padding: 20px; margin: 20px 0; border-left: 4px solid #2563eb;">
					<h3><?php esc_html_e( 'Scan Website Sitemap', 'multichat-gpt' ); ?></h3>
					<button id="multichat-scan-btn" class="button button-primary" style="padding: 10px 20px; font-size: 14px;">
						<?php esc_html_e( 'Scan Sitemap Now', 'multichat-gpt' ); ?>
					</button>
					<span id="multichat-scan-status" style="margin-left: 10px; display: none;"></span>
				</div>

				<?php if ( $is_cached ) : ?>
					<div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;">
						<h3><?php esc_html_e( 'Clear Knowledge Base', 'multichat-gpt' ); ?></h3>
						<button id="multichat-clear-btn" class="button button-secondary" style="padding: 10px 20px; font-size: 14px; color: #d32f2f; border-color: #d32f2f;">
							<?php esc_html_e( 'Clear Cache', 'multichat-gpt' ); ?>
						</button>
						<span id="multichat-clear-status" style="margin-left: 10px; display: none;"></span>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<!-- FAQ Management Section -->
			<div style="background: #f0f7ff; padding: 20px; margin: 20px 0; border-left: 4px solid #0066cc;">
				<h2><?php esc_html_e( 'Custom FAQ Management', 'multichat-gpt' ); ?></h2>
				<p><?php esc_html_e( 'Add custom Q&A pairs that don\'t rely on sitemap content.', 'multichat-gpt' ); ?></p>

				<div style="margin: 20px 0;">
					<h3><?php esc_html_e( 'Add New FAQ', 'multichat-gpt' ); ?></h3>
					<table style="width: 100%;">
						<tr>
							<td style="padding: 10px;"><label><?php esc_html_e( 'Question:', 'multichat-gpt' ); ?></label></td>
							<td style="padding: 10px;"><input type="text" id="faq-title" style="width: 100%;" placeholder="Enter FAQ question"></td>
						</tr>
						<tr>
							<td style="padding: 10px; vertical-align: top;"><label><?php esc_html_e( 'Answer:', 'multichat-gpt' ); ?></label></td>
							<td style="padding: 10px;"><textarea id="faq-content" style="width: 100%; height: 120px;" placeholder="Enter FAQ answer"></textarea></td>
						</tr>
						<tr>
							<td colspan="2" style="padding: 10px;">
								<button id="faq-add-btn" class="button button-primary" style="padding: 10px 20px;">
									<?php esc_html_e( 'Add FAQ', 'multichat-gpt' ); ?>
								</button>
							</td>
						</tr>
					</table>
				</div>

				<div id="faq-list" style="margin-top: 30px;">
					<h3><?php esc_html_e( 'Existing FAQs', 'multichat-gpt' ); ?></h3>
					<div id="faq-items"></div>
				</div>
			</div>

			<!-- Settings Form -->
			<form method="POST" action="options.php" style="margin-top: 30px;">
				<?php
				settings_fields( 'multichat_gpt_group' );
				do_settings_sections( 'multichat-gpt-settings' );
				submit_button();
				?>
			</form>
		</div>

		<script>
			jQuery(document).ready(function($) {
				var currentLanguage = '<?php echo esc_js( $current_language ); ?>';

				// Load FAQs
				function loadFAQs() {
					$.ajax({
						url: ajaxurl,
						method: 'GET',
						data: {
							action: 'multichat_get_faqs',
							language: currentLanguage
						},
						success: function(response) {
							if (response.success) {
								var html = '<ul style="list-style: none; padding: 0;">';
								if (response.data.length === 0) {
									html += '<li style="padding: 15px; background: white; margin-bottom: 10px; border-left: 3px solid #999; color: #999;">No FAQs yet</li>';
								} else {
									$.each(response.data, function(i, faq) {
										html += '<li style="padding: 15px; background: white; margin-bottom: 10px; border-left: 3px solid #0066cc;">';
										html += '<strong>' + faq.title + '</strong><br>';
										html += '<small style="color: #666;">' + faq.content.substring(0, 100) + '...</small><br>';
										html += '<button class="faq-delete-btn button button-small button-secondary" data-faq-id="' + faq.id + '" style="margin-top: 10px;">Delete</button>';
										html += '</li>';
									});
								}
								html += '</ul>';
								$('#faq-items').html(html);

								// Bind delete buttons
								$('.faq-delete-btn').on('click', function() {
									if (!confirm('<?php echo esc_js( __( 'Delete this FAQ?', 'multichat-gpt' ) ); ?>')) return;
									deleteFAQ($(this).data('faq-id'));
								});
							}
						}
					});
				}

				// Add FAQ
				$('#faq-add-btn').on('click', function() {
					var title = $('#faq-title').val();
					var content = $('#faq-content').val();

					if (!title || !content) {
						alert('<?php echo esc_js( __( 'Please fill in all fields', 'multichat-gpt' ) ); ?>');
						return;
					}

					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'multichat_create_faq',
							nonce: '<?php echo wp_create_nonce( 'multichat_faq_nonce' ); ?>',
							title: title,
							content: content,
							language: currentLanguage
						},
						success: function(response) {
							if (response.success) {
								alert('<?php echo esc_js( __( 'FAQ added successfully!', 'multichat-gpt' ) ); ?>');
								$('#faq-title').val('');
								$('#faq-content').val('');
								loadFAQs();
							} else {
								alert('Error: ' + response.data);
							}
						}
					});
				});

				// Delete FAQ
				function deleteFAQ(faqId) {
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'multichat_delete_faq',
							nonce: '<?php echo wp_create_nonce( 'multichat_faq_nonce' ); ?>',
							faq_id: faqId
						},
						success: function(response) {
							if (response.success) {
								loadFAQs();
							}
						}
					});
				}

				// Initial load
				loadFAQs();

				<?php if ( $is_wpml_active ) : ?>
					// WPML - Scan all languages
					$('#multichat-scan-all-btn').on('click', function() {
						var $btn = $(this);
						var $status = $('#multichat-status');
						$btn.prop('disabled', true);
						$status.show().html('<p style="color: #1976d2;"><strong>⏳ Scanning all languages...</strong></p>');

						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'multichat_scan_sitemap',
								nonce: '<?php echo wp_create_nonce( 'multichat_scan_nonce' ); ?>'
							},
							success: function(response) {
								if (response.success) {
									var html = '<div style="background: #c8e6c9; padding: 10px; border-radius: 3px;"><strong>✓ ' + response.data.message + '</strong>';
									if (response.data.languages_scanned) {
										html += '<ul>';
										$.each(response.data.languages_scanned, function(i, lang) {
											html += '<li>' + lang.language + ': ' + lang.pages + ' pages</li>';
										});
										html += '</ul>';
									}
									html += '</div>';
									$status.html(html);
									setTimeout(function() { location.reload(); }, 2000);
								} else {
									$status.html('<div style="background: #ffcdd2; padding: 10px; border-radius: 3px;"><strong>✗ Error: ' + response.data + '</strong></div>');
								}
							},
							complete: function() {
								$btn.prop('disabled', false);
							}
						});
					});

					// WPML - Scan individual language
					$(document).on('click', '.multichat-scan-lang-btn', function(e) {
						e.preventDefault();
						var $btn = $(this);
						var language = $btn.data('language');
						var $status = $('#multichat-status');
						$btn.prop('disabled', true);
						$status.show().text('⏳ Scanning...');

						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'multichat_scan_language',
								nonce: '<?php echo wp_create_nonce( 'multichat_scan_language_nonce' ); ?>',
								language: language
							},
							success: function(response) {
								if (response.success) {
									$status.html('<div style="background: #c8e6c9; padding: 10px; border-radius: 3px;"><strong>✓ ' + response.data.message + '</strong></div>');
									setTimeout(function() { location.reload(); }, 2000);
								} else {
									$status.html('<div style="background: #ffcdd2; padding: 10px; border-radius: 3px;"><strong>✗ Error: ' + response.data + '</strong></div>');
								}
							},
							complete: function() {
								$btn.prop('disabled', false);
							}
						});
					});

					// WPML - Clear all caches
					$('#multichat-clear-all-btn').on('click', function(e) {
						e.preventDefault();
						if (!confirm('<?php echo esc_js( __( 'Clear all language caches? This cannot be undone.', 'multichat-gpt' ) ); ?>')) {
							return;
						}
						var $btn = $(this);
						var $status = $('#multichat-status');
						$btn.prop('disabled', true);
						$status.show().text('⏳ Clearing...');

						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'multichat_clear_cache',
								nonce: '<?php echo wp_create_nonce( 'multichat_clear_nonce' ); ?>'
							},
							success: function(response) {
								if (response.success) {
									$status.html('<div style="background: #c8e6c9; padding: 10px; border-radius: 3px;"><strong>✓ ' + response.data.message + '</strong></div>');
									setTimeout(function() { location.reload(); }, 2000);
								} else {
									$status.html('<div style="background: #ffcdd2; padding: 10px; border-radius: 3px;"><strong>✗ Error: ' + response.data + '</strong></div>');
								}
							},
							complete: function() {
								$btn.prop('disabled', false);
							}
						});
					});

					// WPML - Clear individual language cache
					$(document).on('click', '.multichat-clear-lang-btn', function(e) {
						e.preventDefault();
						if (!confirm('<?php echo esc_js( __( 'Clear cache for this language?', 'multichat-gpt' ) ); ?>')) {
							return;
						}
						var $btn = $(this);
						var language = $btn.data('language');
						var $status = $('#multichat-status');
						$btn.prop('disabled', true);
						$status.show().text('⏳ Clearing...');

						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'multichat_clear_language_cache',
								nonce: '<?php echo wp_create_nonce( 'multichat_clear_language_nonce' ); ?>',
								language: language
							},
							success: function(response) {
								if (response.success) {
									$status.html('<div style="background: #c8e6c9; padding: 10px; border-radius: 3px;"><strong>✓ ' + response.data.message + '</strong></div>');
									setTimeout(function() { location.reload(); }, 2000);
								} else {
									$status.html('<div style="background: #ffcdd2; padding: 10px; border-radius: 3px;"><strong>✗ Error: ' + response.data + '</strong></div>');
								}
							},
							complete: function() {
								$btn.prop('disabled', false);
							}
						});
					});
				<?php else : ?>
					// Single language - Scan
					$('#multichat-scan-btn').on('click', function() {
						var $btn = $(this);
						var $status = $('#multichat-scan-status');
						$btn.prop('disabled', true);
						$status.show().text('⏳ Scanning...');

						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'multichat_scan_sitemap',
								nonce: '<?php echo wp_create_nonce( 'multichat_scan_nonce' ); ?>',
								sitemap_url: '<?php echo esc_js( $sitemap_url ); ?>'
							},
							success: function(response) {
								if (response.success) {
									$status.addClass('notice notice-success').html('✓ ' + response.data.message + '<br>Knowledge base is permanent until next manual scan.');
									setTimeout(function() { location.reload(); }, 2000);
								} else {
									$status.addClass('notice notice-error').text('✗ Error: ' + response.data);
								}
							},
							error: function() {
								$status.addClass('notice notice-error').text('✗ Error scanning sitemap');
							},
							complete: function() {
								$btn.prop('disabled', false);
							}
						});
					});

					// Single language - Clear
					$('#multichat-clear-btn').on('click', function() {
						if (!confirm('<?php echo esc_js( __( 'Clear knowledge base? This cannot be undone.', 'multichat-gpt' ) ); ?>')) {
							return;
						}
						var $btn = $(this);
						var $status = $('#multichat-clear-status');
						$btn.prop('disabled', true);
						$status.show().text('⏳ Clearing...');

						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'multichat_clear_cache',
								nonce: '<?php echo wp_create_nonce( 'multichat_clear_nonce' ); ?>'
							},
							success: function(response) {
								if (response.success) {
									$status.addClass('notice notice-success').text('✓ ' + response.data.message);
									setTimeout(function() { location.reload(); }, 2000);
								} else {
									$status.addClass('notice notice-error').text('✗ Error: ' + response.data);
								}
							},
							error: function() {
								$status.addClass('notice notice-error').text('✗ Error clearing cache');
							},
							complete: function() {
								$btn.prop('disabled', false);
							}
						});
					});
				<?php endif; ?>
			});
		</script>
		<?php
	}

	/**
	 * Activate plugin
	 */
	public static function activate_plugin() {
		// Create necessary options
		if ( ! get_option( 'multichat_gpt_api_key' ) ) {
			add_option( 'multichat_gpt_api_key', '' );
		}
		if ( ! get_option( 'multichat_gpt_widget_position' ) ) {
			add_option( 'multichat_gpt_widget_position', 'bottom-right' );
		}
		if ( ! get_option( 'multichat_gpt_sitemap_url' ) ) {
			add_option( 'multichat_gpt_sitemap_url', site_url( '/sitemap.xml' ) );
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivate plugin
	 */
	public static function deactivate_plugin() {
		// Clean up if needed
		flush_rewrite_rules();
	}
}

// Initialize the plugin
MultiChat_GPT::get_instance();