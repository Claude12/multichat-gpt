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
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Define plugin constants
 */
define('MULTICHAT_GPT_VERSION', '1.0.0');
define('MULTICHAT_GPT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MULTICHAT_GPT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MULTICHAT_GPT_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class MultiChat_GPT
{

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
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Load text domain for translations
		add_action('plugins_loaded', [$this, 'load_textdomain']);

		// Load helper classes
		$this->load_classes();

		// Register REST API endpoint
		add_action('rest_api_init', [$this, 'register_rest_endpoints']);

		// Enqueue frontend assets
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

		// Add admin settings page
		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_init', [$this, 'register_admin_settings']);

		// Handle AJAX requests
		add_action('wp_ajax_multichat_scan_sitemap', [$this, 'handle_scan_sitemap']);

		// Activation/Deactivation hooks
		register_activation_hook(__FILE__, [$this, 'activate_plugin']);
		register_deactivation_hook(__FILE__, [$this, 'deactivate_plugin']);
	}

	/**
	 * Load helper classes
	 */
	private function load_classes()
	{
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-sitemap-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-content-crawler.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';
	}

	/**
	 * Load plugin text domain for translations
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain(
			'multichat-gpt',
			false,
			dirname(MULTICHAT_GPT_BASENAME) . '/languages'
		);
	}

	/**
	 * Register REST API endpoints
	 */
	public function register_rest_endpoints()
	{
		register_rest_route(
			'multichat/v1',
			'/ask',
			[
				'methods'             => 'POST',
				'callback'            => [$this, 'handle_chat_request'],
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
	}

	/**
	 * Handle chat request from frontend
	 *
	 * @param WP_REST_Request $request REST request object
	 * @return WP_REST_Response
	 */
	public function handle_chat_request($request)
	{
		// Get parameters
		$user_message = sanitize_text_field($request->get_param('message'));
		$language     = sanitize_text_field($request->get_param('language'));

		// Validate message
		if (empty($user_message)) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __('Message cannot be empty', 'multichat-gpt'),
				],
				400
			);
		}

		// Get API key from settings
		$api_key = get_option('multichat_gpt_api_key');
		if (empty($api_key)) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __('API key not configured', 'multichat-gpt'),
				],
				500
			);
		}

		// Try to get knowledge base from cache first
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';
		$kb_chunks = MultiChat_KB_Builder::get_cached_knowledge_base();

		// If no cached KB, use static knowledge base
		if (! $kb_chunks) {
			$kb_chunks = $this->get_knowledge_base_chunks($language);
		}

		// Find relevant KB chunks based on user message
		$relevant_chunks = $this->find_relevant_chunks($user_message, $kb_chunks);

		// Build the ChatGPT prompt
		$system_message = $this->build_system_message($language, $relevant_chunks);

		// Call ChatGPT API
		$response = $this->call_chatgpt_api($api_key, $system_message, $user_message);

		// Handle API response
		if (is_wp_error($response)) {
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
	private function get_knowledge_base_chunks($language = 'en')
	{
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
		];

		return isset($kb_data[$language]) ? $kb_data[$language] : $kb_data['en'];
	}

	/**
	 * Find relevant KB chunks based on user message
	 *
	 * @param string $user_message User's message
	 * @param array  $kb_chunks Knowledge base chunks
	 * @return array Relevant chunks
	 */
	private function find_relevant_chunks($user_message, $kb_chunks)
	{
		if (! is_array($kb_chunks) || empty($kb_chunks)) {
			return [];
		}

		$relevant = [];
		$user_words = array_filter(array_map('strtolower', preg_split('/\s+/', $user_message)));

		foreach ($kb_chunks as $chunk) {
			$chunk_text = isset($chunk['content']) ? strtolower($chunk['content']) : '';
			$chunk_text .= ' ' . (isset($chunk['title']) ? strtolower($chunk['title']) : '');

			$match_count = 0;
			foreach ($user_words as $word) {
				if (strlen($word) > 3 && strpos($chunk_text, $word) !== false) {
					$match_count++;
				}
			}

			if ($match_count > 0) {
				$relevant[] = $chunk;
			}
		}

		// Return top 3 most relevant chunks
		return array_slice($relevant, 0, 3);
	}

	/**
	 * Build system message with KB context
	 *
	 * @param string $language Language code
	 * @param array  $relevant_chunks Relevant KB chunks
	 * @return string System message
	 */
	private function build_system_message($language, $relevant_chunks)
	{
		$lang_names = [
			'en' => 'English',
			'ar' => 'Arabic',
			'es' => 'Spanish',
			'fr' => 'French',
		];

		$lang_name = isset($lang_names[$language]) ? $lang_names[$language] : 'English';

		// Build KB content
		$kb_content = '';
		if (! empty($relevant_chunks)) {
			$kb_content = "RELEVANT INFORMATION:\n";
			foreach ($relevant_chunks as $chunk) {
				$title   = isset($chunk['title']) ? $chunk['title'] : 'Info';
				$content = isset($chunk['content']) ? substr($chunk['content'], 0, 500) : '';
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
	private function call_chatgpt_api($api_key, $system_message, $user_message)
	{
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
			'body'    => wp_json_encode($request_body),
			'timeout' => 30,
		];

		$response = wp_remote_post($this->api_endpoint, $args);

		// Check for HTTP errors
		if (is_wp_error($response)) {
			return $response;
		}

		// Get response body
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		// Check for API errors
		if (isset($data['error'])) {
			return new WP_Error('chatgpt_error', $data['error']['message'] ?? 'Unknown error');
		}

		// Extract assistant's message
		if (isset($data['choices'][0]['message']['content'])) {
			return $data['choices'][0]['message']['content'];
		}

		return new WP_Error('chatgpt_error', 'Unexpected response format from ChatGPT API');
	}

	/**
	 * Handle sitemap scan AJAX request
	 */
	public function handle_scan_sitemap()
	{
		// Check nonce
		if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'multichat_scan_nonce')) {
			wp_send_json_error('Invalid nonce');
		}

		// Check capabilities
		if (! current_user_can('manage_options')) {
			wp_send_json_error('Insufficient permissions');
		}

		// Get sitemap URL
		$sitemap_url = isset($_POST['sitemap_url']) ? esc_url_raw($_POST['sitemap_url']) : site_url('/sitemap.xml');

		if (empty($sitemap_url)) {
			wp_send_json_error('Invalid sitemap URL');
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-sitemap-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';

		// Check if external sitemap
		$external = strpos($sitemap_url, home_url()) === false;

		// Scan sitemap
		$urls = MultiChat_Sitemap_Scanner::get_urls_from_sitemap($sitemap_url, $external);

		if (empty($urls)) {
			error_log('MultiChat GPT: No URLs found in sitemap: ' . $sitemap_url);
			wp_send_json_error('No pages found in sitemap. Check your sitemap URL.');
		}

		// Build knowledge base (this clears old cache first)
		$kb = MultiChat_KB_Builder::build_kb_from_urls($urls);

		if (empty($kb)) {
			wp_send_json_error('Failed to extract content from pages. Check error logs.');
		}

		wp_send_json_success([
			'message'        => sprintf('Successfully scanned and indexed %d pages', count($kb)),
			'pages_scanned'  => count($kb),
			'cache_expires'  => gmdate('Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS),
		]);
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets()
	{
		// Only enqueue on frontend, not admin
		if (is_admin()) {
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

		wp_localize_script(
			'multichat-gpt-widget',
			'multiChatGPT',
			[
				'restUrl'  => esc_url(rest_url('multichat/v1/ask')),
				'language' => $current_language,
			]
		);
	}

	/**
	 * Get current language
	 *
	 * @return string Language code
	 */
	private function get_current_language()
	{
		// Check for WPML
		if (function_exists('wpml_get_default_language')) {
			return apply_filters('wpml_current_language', null);
		}

		return 'en';
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu()
	{
		add_options_page(
			__('MultiChat GPT', 'multichat-gpt'),
			__('MultiChat GPT', 'multichat-gpt'),
			'manage_options',
			'multichat-gpt',
			[$this, 'render_admin_page']
		);
	}

	/**
	 * Register admin settings
	 */
	public function register_admin_settings()
	{
		register_setting('multichat_gpt_group', 'multichat_gpt_api_key');
		register_setting('multichat_gpt_group', 'multichat_gpt_widget_position');
		register_setting('multichat_gpt_group', 'multichat_gpt_sitemap_url');

		add_settings_section(
			'multichat_gpt_section',
			__('Configuration', 'multichat-gpt'),
			[$this, 'render_settings_section'],
			'multichat-gpt-settings'
		);

		add_settings_field(
			'multichat_gpt_api_key',
			__('OpenAI API Key', 'multichat-gpt'),
			[$this, 'render_api_key_field'],
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		add_settings_field(
			'multichat_gpt_widget_position',
			__('Widget Position', 'multichat-gpt'),
			[$this, 'render_position_field'],
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		add_settings_field(
			'multichat_gpt_sitemap_url',
			__('Sitemap URL', 'multichat-gpt'),
			[$this, 'render_sitemap_url_field'],
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);
	}

	/**
	 * Render settings section
	 */
	public function render_settings_section()
	{
?>
		<p><?php esc_html_e('Configure your MultiChat GPT plugin settings below.', 'multichat-gpt'); ?></p>
	<?php
	}

	/**
	 * Render API key field
	 */
	public function render_api_key_field()
	{
		$api_key = get_option('multichat_gpt_api_key');
	?>
		<input
			type="password"
			name="multichat_gpt_api_key"
			value="<?php echo esc_attr($api_key); ?>"
			style="width: 400px;" />
	<?php
	}

	/**
	 * Render position field
	 */
	public function render_position_field()
	{
		$position = get_option('multichat_gpt_widget_position', 'bottom-right');
	?>
		<select name="multichat_gpt_widget_position">
			<option value="bottom-right" <?php selected($position, 'bottom-right'); ?>>
				<?php esc_html_e('Bottom Right', 'multichat-gpt'); ?>
			</option>
			<option value="bottom-left" <?php selected($position, 'bottom-left'); ?>>
				<?php esc_html_e('Bottom Left', 'multichat-gpt'); ?>
			</option>
		</select>
	<?php
	}

	/**
	 * Render sitemap URL field
	 */
	public function render_sitemap_url_field()
	{
		$sitemap_url = get_option('multichat_gpt_sitemap_url', site_url('/sitemap.xml'));
	?>
		<input
			type="url"
			name="multichat_gpt_sitemap_url"
			value="<?php echo esc_attr($sitemap_url); ?>"
			style="width: 400px;" />
		<p class="description">
			<?php esc_html_e('Leave blank to use default sitemap location', 'multichat-gpt'); ?>
		</p>
	<?php
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page()
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'multichat-gpt'));
		}

		$sitemap_url = get_option('multichat_gpt_sitemap_url', site_url('/sitemap.xml'));
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';
		$cache_expires = MultiChat_KB_Builder::get_cache_expiration();
	?>
		<div class="wrap">
			<h1><?php esc_html_e('MultiChat GPT Settings', 'multichat-gpt'); ?></h1>

			<div class="notice notice-info" style="margin-top: 20px;">
				<p>
					<strong><?php esc_html_e('Knowledge Base Status:', 'multichat-gpt'); ?></strong>
					<?php
					if ($cache_expires > 0) {
						echo sprintf(
							esc_html__('Cache will expire in %s', 'multichat-gpt'),
							gmdate('H:i:s', $cache_expires)
						);
					} else {
						esc_html_e('No knowledge base cached yet', 'multichat-gpt');
					}
					?>
				</p>
			</div>

			<div style="background: #f1f1f1; padding: 20px; margin: 20px 0; border-left: 4px solid #2563eb;">
				<h3><?php esc_html_e('Scan Website Sitemap', 'multichat-gpt'); ?></h3>
				<p><?php esc_html_e('Click the button below to scan your website sitemap and build the knowledge base.', 'multichat-gpt'); ?></p>
				<button id="multichat-scan-btn" class="button button-primary" style="padding: 10px 20px; font-size: 14px;">
					<?php esc_html_e('Scan Sitemap Now', 'multichat-gpt'); ?>
				</button>
				<span id="multichat-scan-status" style="margin-left: 10px; display: none;"></span>
			</div>

			<form method="POST" action="options.php">
				<?php
				settings_fields('multichat_gpt_group');
				do_settings_sections('multichat-gpt-settings');
				submit_button();
				?>
			</form>
		</div>

		<script>
			jQuery(document).ready(function($) {
				$('#multichat-scan-btn').on('click', function() {
					var $btn = $(this);
					var $status = $('#multichat-scan-status');

					$btn.prop('disabled', true);
					$status.show().text('<?php echo esc_js(__('Scanning...', 'multichat-gpt')); ?>');

					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'multichat_scan_sitemap',
							nonce: '<?php echo wp_create_nonce('multichat_scan_nonce'); ?>',
							sitemap_url: '<?php echo esc_js($sitemap_url); ?>'
						},
						success: function(response) {
							if (response.success) {
								$status.addClass('notice notice-success').text(response.data.message + ' - Cache expires: ' + response.data.cache_expires);
							} else {
								$status.addClass('notice notice-error').text('Error: ' + response.data);
							}
						},
						error: function() {
							$status.addClass('notice notice-error').text('<?php echo esc_js(__('Error scanning sitemap', 'multichat-gpt')); ?>');
						},
						complete: function() {
							$btn.prop('disabled', false);
						}
					});
				});
			});
		</script>
<?php
	}

	/**
	 * Activate plugin
	 */
	public static function activate_plugin()
	{
		// Create necessary options
		if (! get_option('multichat_gpt_api_key')) {
			add_option('multichat_gpt_api_key', '');
		}
		if (! get_option('multichat_gpt_widget_position')) {
			add_option('multichat_gpt_widget_position', 'bottom-right');
		}
		if (! get_option('multichat_gpt_sitemap_url')) {
			add_option('multichat_gpt_sitemap_url', site_url('/sitemap.xml'));
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivate plugin
	 */
	public static function deactivate_plugin()
	{
		// Clean up if needed
		flush_rewrite_rules();
	}
}

// Initialize the plugin
MultiChat_GPT::get_instance();
