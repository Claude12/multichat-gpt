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
	 * ChatGPT API Key (CHANGE THIS)
	 */
	private $api_key = 'sk-YOUR_API_KEY_HERE';

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

		// Register REST API endpoint
		add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );

		// Enqueue frontend assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// Add admin settings page
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_admin_settings' ] );

		// Activation/Deactivation hooks
		register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate_plugin' ] );
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
	 * FIXED: Removed nonce requirement for public access
	 */
	public function register_rest_endpoints() {
		register_rest_route(
			'multichat/v1',
			'/ask',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_chat_request' ],
				'permission_callback' => '__return_true', // FIXED: Allow public access
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

		// Retrieve knowledge base for the language
		$kb_chunks = $this->get_knowledge_base_chunks( $language );

		// Find relevant KB chunks based on user message
		$relevant_chunks = $this->find_relevant_chunks( $user_message, $kb_chunks );

		// Build the ChatGPT prompt
		$system_message = $this->build_system_message( $language, $relevant_chunks );

		// Call ChatGPT API
		$response = $this->call_chatgpt_api( $api_key, $system_message, $user_message );

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'API error: ', 'multichat-gpt' ) . $response->get_error_message(),
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
	 * Get knowledge base for a specific language
	 *
	 * @param string $language Language code (en, ar, etc.)
	 * @return array
	 */
	private function get_knowledge_base_chunks( $language = 'en' ) {
		/**
		 * Hard-coded knowledge base
		 * LATER: Replace with ACF fields or database query
		 */

		$kb_data = [
			'en' => [
				'What are your business hours?',
				'Our business hours are Monday to Friday, 9 AM to 6 PM EST.',
				'How can I contact customer support?',
				'You can contact us via email at support@example.com or phone at 1-800-EXAMPLE.',
				'What is your return policy?',
				'We offer a 30-day money-back guarantee on all products.',
				'Do you ship internationally?',
				'Yes, we ship to over 150 countries worldwide.',
				'What payment methods do you accept?',
				'We accept all major credit cards, PayPal, and bank transfers.',
			],
			'ar' => [
				'ما هي ساعات العمل لديكم؟',
				'ساعات عملنا من الاثنين إلى الجمعة، من الساعة 9 صباحًا إلى الساعة 6 مساءً بتوقيت EST.',
				'كيف يمكنني التواصل مع خدمة العملاء؟',
				'يمكنك التواصل معنا عبر البريد الإلكتروني support@example.com أو الهاتف 1-800-EXAMPLE.',
				'ما هي سياسة الإرجاع؟',
				'نقدم ضمان استرجاع الأموال لمدة 30 يومًا على جميع المنتجات.',
				'هل تقومون بالشحن الدولي؟',
				'نعم، نشحن إلى أكثر من 150 دولة في جميع أنحاء العالم.',
				'ما هي طرق الدفع التي تقبلونها؟',
				'نقبل جميع بطاقات الائتمان الرئيسية و PayPal والتحويلات البنكية.',
			],
			'es' => [
				'¿Cuál es su horario de atención?',
				'Nuestro horario es de lunes a viernes, de 9 a.m. a 6 p.m. EST.',
				'¿Cómo puedo contactar al servicio al cliente?',
				'Puede contactarnos por correo electrónico a support@example.com o por teléfono al 1-800-EXAMPLE.',
				'¿Cuál es su política de devoluciones?',
				'Ofrecemos una garantía de devolución de dinero de 30 días en todos los productos.',
				'¿Envían a nivel internacional?',
				'Sí, enviamos a más de 150 países en todo el mundo.',
				'¿Qué métodos de pago aceptan?',
				'Aceptamos todas las tarjetas de crédito principales, PayPal y transferencias bancarias.',
			],
			'fr' => [
				'Quels sont vos horaires de travail?',
				'Nos horaires sont du lundi au vendredi, de 9h à 18h EST.',
				'Comment puis-je contacter le service clientèle?',
				'Vous pouvez nous contacter par email à support@example.com ou par téléphone au 1-800-EXAMPLE.',
				'Quelle est votre politique de retour?',
				'Nous offrons une garantie de remboursement de 30 jours sur tous les produits.',
				'Livrez-vous à l\'international?',
				'Oui, nous livrons dans plus de 150 pays dans le monde.',
				'Quels modes de paiement acceptez-vous?',
				'Nous acceptons toutes les principales cartes de crédit, PayPal et les virements bancaires.',
			],
		];

		// Default to English if language not found
		if ( ! isset( $kb_data[ $language ] ) ) {
			$language = 'en';
		}

		/**
		 * Filter to allow extending the knowledge base
		 *
		 * @param array $kb_data Knowledge base array for the language
		 * @param string $language Current language code
		 */
		return apply_filters( 'multichat_gpt_knowledge_base', $kb_data[ $language ], $language );
	}

	/**
	 * Find relevant knowledge base chunks using similarity matching
	 *
	 * @param string $user_message User's message
	 * @param array $kb_chunks Knowledge base chunks
	 * @param int $num_results Number of results to return
	 * @return array
	 */
	private function find_relevant_chunks( $user_message, $kb_chunks, $num_results = 3 ) {
		$similarities = [];

		// Calculate similarity between user message and each KB chunk
		foreach ( $kb_chunks as $chunk ) {
			$similarity = 0;
			similar_text( strtolower( $user_message ), strtolower( $chunk ), $similarity );
			$similarities[ $chunk ] = $similarity;
		}

		// Sort by similarity (descending)
		arsort( $similarities );

		// Return top results
		return array_slice( array_keys( $similarities ), 0, $num_results, true );
	}

	/**
	 * Build system message for ChatGPT
	 *
	 * @param string $language Language code
	 * @param array $relevant_chunks Relevant knowledge base chunks
	 * @return string
	 */
	private function build_system_message( $language, $relevant_chunks ) {
		$language_names = [
			'en' => 'English',
			'ar' => 'Arabic',
			'es' => 'Spanish',
			'fr' => 'French',
		];

		$lang_name = $language_names[ $language ] ?? $language;

		$kb_content = ! empty( $relevant_chunks ) ? implode( '\n\n', $relevant_chunks ) : 'No relevant knowledge base available.';

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

		// Localize script with WPML language
		$current_language = $this->get_current_language();

		wp_localize_script(
			'multichat-gpt-widget',
			'multiChatGPT',
			[
				'restUrl'  => rest_url( 'multichat/v1/ask' ),
				'language' => $current_language,
			]
		);
	}

	/**
	 * Get current language (WPML-aware)
	 *
	 * @return string
	 */
	private function get_current_language() {
		// Check for WPML
		if ( function_exists( 'wpml_get_current_language' ) ) {
			return wpml_get_current_language();
		}

		// Fallback to apply_filters hook for WPML
		$lang = apply_filters( 'wpml_current_language', null );
		if ( ! empty( $lang ) ) {
			return $lang;
		}

		// Fallback to WordPress locale
		$locale = get_locale();
		$lang    = substr( $locale, 0, 2 );

		return $lang ?: 'en';
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'MultiChat GPT Settings', 'multichat-gpt' ),
			__( 'MultiChat GPT', 'multichat-gpt' ),
			'manage_options',
			'multichat-gpt-settings',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Register admin settings
	 */
	public function register_admin_settings() {
		register_setting(
			'multichat_gpt_group',
			'multichat_gpt_api_key',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
			]
		);

		register_setting(
			'multichat_gpt_group',
			'multichat_gpt_widget_position',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'bottom-right',
				'show_in_rest'      => false,
			]
		);

		add_settings_section(
			'multichat_gpt_section',
			__( 'ChatGPT Configuration', 'multichat-gpt' ),
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
	}

	/**
	 * Render settings section
	 */
	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Configure your OpenAI ChatGPT API credentials and widget settings.', 'multichat-gpt' ) . '</p>';
	}

	/**
	 * Render API key field
	 */
	public function render_api_key_field() {
		$api_key = get_option( 'multichat_gpt_api_key' );
		echo '<input type="password" name="multichat_gpt_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Get your API key from https://platform.openai.com/api-keys', 'multichat-gpt' ) . '</p>';
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
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MultiChat GPT Settings', 'multichat-gpt' ); ?></h1>
			<form method="POST" action="options.php">
				<?php
				settings_fields( 'multichat_gpt_group' );
				do_settings_sections( 'multichat-gpt-settings' );
				submit_button();
				?>
			</form>
		</div>
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

		// Flush rewrite rules
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