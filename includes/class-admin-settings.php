<?php
/**
 * Admin Settings Class
 *
 * Settings page management and configuration.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Settings class for managing plugin configuration.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_Admin_Settings {

	/**
	 * Settings page slug
	 *
	 * @var string
	 */
	private $page_slug = 'multichat-gpt-settings';

	/**
	 * Settings group
	 *
	 * @var string
	 */
	private $settings_group = 'multichat_gpt_group';

	/**
	 * Initialize admin hooks
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'MultiChat GPT Settings', 'multichat-gpt' ),
			__( 'MultiChat GPT', 'multichat-gpt' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function register_settings() {
		// API Key setting.
		register_setting(
			$this->settings_group,
			'multichat_gpt_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'show_in_rest'      => false,
			)
		);

		// Widget position setting.
		register_setting(
			$this->settings_group,
			'multichat_gpt_widget_position',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_position' ),
				'default'           => 'bottom-right',
				'show_in_rest'      => false,
			)
		);

		// Cache TTL setting.
		register_setting(
			$this->settings_group,
			'multichat_gpt_cache_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 3600,
				'show_in_rest'      => false,
			)
		);

		// Rate limit setting.
		register_setting(
			$this->settings_group,
			'multichat_gpt_rate_limit',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 10,
				'show_in_rest'      => false,
			)
		);

		// Add settings section.
		add_settings_section(
			'multichat_gpt_main_section',
			__( 'ChatGPT Configuration', 'multichat-gpt' ),
			array( $this, 'render_section_description' ),
			$this->page_slug
		);

		// API Key field.
		add_settings_field(
			'multichat_gpt_api_key',
			__( 'OpenAI API Key', 'multichat-gpt' ),
			array( $this, 'render_api_key_field' ),
			$this->page_slug,
			'multichat_gpt_main_section'
		);

		// Widget Position field.
		add_settings_field(
			'multichat_gpt_widget_position',
			__( 'Widget Position', 'multichat-gpt' ),
			array( $this, 'render_position_field' ),
			$this->page_slug,
			'multichat_gpt_main_section'
		);

		// Cache TTL field.
		add_settings_field(
			'multichat_gpt_cache_ttl',
			__( 'Cache Duration (seconds)', 'multichat-gpt' ),
			array( $this, 'render_cache_ttl_field' ),
			$this->page_slug,
			'multichat_gpt_main_section'
		);

		// Rate limit field.
		add_settings_field(
			'multichat_gpt_rate_limit',
			__( 'Rate Limit (requests/minute)', 'multichat-gpt' ),
			array( $this, 'render_rate_limit_field' ),
			$this->page_slug,
			'multichat_gpt_main_section'
		);
	}

	/**
	 * Sanitize API key
	 *
	 * @param string $value API key value.
	 * @return string Sanitized API key.
	 */
	public function sanitize_api_key( $value ) {
		$sanitized = sanitize_text_field( $value );

		// Validate API key format (should start with sk-).
		if ( ! empty( $sanitized ) && ! preg_match( '/^sk-[a-zA-Z0-9_-]+$/', $sanitized ) ) {
			add_settings_error(
				'multichat_gpt_api_key',
				'invalid_api_key',
				__( 'Invalid API key format. OpenAI API keys should start with "sk-".', 'multichat-gpt' ),
				'error'
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize widget position
	 *
	 * @param string $value Position value.
	 * @return string Sanitized position.
	 */
	public function sanitize_position( $value ) {
		$valid_positions = array( 'bottom-right', 'bottom-left' );

		if ( ! in_array( $value, $valid_positions, true ) ) {
			return 'bottom-right';
		}

		return $value;
	}

	/**
	 * Render section description
	 *
	 * @return void
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Configure your OpenAI ChatGPT API credentials and widget settings.', 'multichat-gpt' ) . '</p>';
	}

	/**
	 * Render API key field
	 *
	 * @return void
	 */
	public function render_api_key_field() {
		$api_key = get_option( 'multichat_gpt_api_key', '' );
		?>
		<input 
			type="password" 
			name="multichat_gpt_api_key" 
			id="multichat_gpt_api_key"
			value="<?php echo esc_attr( $api_key ); ?>" 
			class="regular-text"
			autocomplete="off"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: %s: URL to OpenAI API keys page */
				esc_html__( 'Get your API key from %s', 'multichat-gpt' ),
				'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">https://platform.openai.com/api-keys</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render position field
	 *
	 * @return void
	 */
	public function render_position_field() {
		$position = get_option( 'multichat_gpt_widget_position', 'bottom-right' );
		?>
		<select name="multichat_gpt_widget_position" id="multichat_gpt_widget_position">
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
	 * Render cache TTL field
	 *
	 * @return void
	 */
	public function render_cache_ttl_field() {
		$cache_ttl = get_option( 'multichat_gpt_cache_ttl', 3600 );
		?>
		<input 
			type="number" 
			name="multichat_gpt_cache_ttl" 
			id="multichat_gpt_cache_ttl"
			value="<?php echo esc_attr( $cache_ttl ); ?>" 
			class="small-text"
			min="0"
			step="1"
		/>
		<p class="description">
			<?php esc_html_e( 'How long to cache API responses (3600 = 1 hour). Set to 0 to disable caching.', 'multichat-gpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Render rate limit field
	 *
	 * @return void
	 */
	public function render_rate_limit_field() {
		$rate_limit = get_option( 'multichat_gpt_rate_limit', 10 );
		?>
		<input 
			type="number" 
			name="multichat_gpt_rate_limit" 
			id="multichat_gpt_rate_limit"
			value="<?php echo esc_attr( $rate_limit ); ?>" 
			class="small-text"
			min="1"
			max="100"
			step="1"
		/>
		<p class="description">
			<?php esc_html_e( 'Maximum number of chat requests allowed per IP address per minute.', 'multichat-gpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<form method="POST" action="options.php">
				<?php
				settings_fields( $this->settings_group );
				do_settings_sections( $this->page_slug );
				submit_button();
				?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Cache Management', 'multichat-gpt' ); ?></h2>
			<p><?php esc_html_e( 'Clear cached data to force fresh API calls and knowledge base retrieval.', 'multichat-gpt' ); ?></p>
			
			<form method="POST" action="">
				<?php wp_nonce_field( 'multichat_gpt_clear_cache', 'multichat_gpt_cache_nonce' ); ?>
				<button type="submit" name="multichat_gpt_clear_cache" class="button button-secondary">
					<?php esc_html_e( 'Clear All Caches', 'multichat-gpt' ); ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Show admin notices
	 *
	 * @return void
	 */
	public function show_admin_notices() {
		// Handle cache clearing.
		if ( isset( $_POST['multichat_gpt_clear_cache'] ) ) {
			// Verify nonce.
			if ( ! isset( $_POST['multichat_gpt_cache_nonce'] ) || 
				 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['multichat_gpt_cache_nonce'] ) ), 'multichat_gpt_clear_cache' ) ) {
				return;
			}

			// Check capabilities.
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Clear caches (this would be implemented in the main plugin class).
			do_action( 'multichat_gpt_clear_all_caches' );

			echo '<div class="notice notice-success is-dismissible"><p>' . 
				 esc_html__( 'All caches have been cleared successfully.', 'multichat-gpt' ) . 
				 '</p></div>';
		}
	}
}
