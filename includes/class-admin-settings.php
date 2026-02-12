<?php
/**
 * Admin Settings Class
 *
 * Manages admin settings page and options
 *
 * @package MultiChatGPT
 * @since 1.1.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_Admin_Settings class
 *
 * Handles admin settings page and option registration
 */
class MultiChat_GPT_Admin_Settings {

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
	 * Constructor
	 *
	 * @param MultiChat_GPT_Logger         $logger         Logger instance
	 * @param MultiChat_GPT_API_Handler    $api_handler    API handler instance
	 * @param MultiChat_GPT_Knowledge_Base $knowledge_base Knowledge base instance
	 */
	public function __construct( $logger, $api_handler, $knowledge_base ) {
		$this->logger         = $logger;
		$this->api_handler    = $api_handler;
		$this->knowledge_base = $knowledge_base;
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'MultiChat GPT Settings', 'multichat-gpt' ),
			__( 'MultiChat GPT', 'multichat-gpt' ),
			'manage_options',
			'multichat-gpt-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register admin settings
	 *
	 * @return void
	 */
	public function register_settings() {
		// Register settings
		register_setting(
			'multichat_gpt_group',
			'multichat_gpt_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'multichat_gpt_group',
			'multichat_gpt_widget_position',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_position' ),
				'default'           => 'bottom-right',
				'show_in_rest'      => false,
			)
		);

		// Add settings section
		add_settings_section(
			'multichat_gpt_section',
			__( 'ChatGPT Configuration', 'multichat-gpt' ),
			array( $this, 'render_section' ),
			'multichat-gpt-settings'
		);

		// Add settings fields
		add_settings_field(
			'multichat_gpt_api_key',
			__( 'OpenAI API Key', 'multichat-gpt' ),
			array( $this, 'render_api_key_field' ),
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		add_settings_field(
			'multichat_gpt_widget_position',
			__( 'Widget Position', 'multichat-gpt' ),
			array( $this, 'render_position_field' ),
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		// Add cache management section
		add_settings_section(
			'multichat_gpt_cache_section',
			__( 'Cache Management', 'multichat-gpt' ),
			array( $this, 'render_cache_section' ),
			'multichat-gpt-settings'
		);
	}

	/**
	 * Sanitize API key
	 *
	 * @param string $value API key value
	 * @return string Sanitized API key
	 */
	public function sanitize_api_key( $value ) {
		$value = sanitize_text_field( $value );

		// Validate API key format (should start with 'sk-')
		if ( ! empty( $value ) && ! preg_match( '/^sk-[a-zA-Z0-9]+$/', $value ) ) {
			add_settings_error(
				'multichat_gpt_api_key',
				'invalid_api_key',
				__( 'Invalid API key format. API key should start with "sk-"', 'multichat-gpt' ),
				'error'
			);
			
			// Return the previous value
			return get_option( 'multichat_gpt_api_key', '' );
		}

		$this->logger->info( 'API key updated' );

		return $value;
	}

	/**
	 * Sanitize widget position
	 *
	 * @param string $value Widget position value
	 * @return string Sanitized position
	 */
	public function sanitize_position( $value ) {
		$valid_positions = array( 'bottom-right', 'bottom-left' );

		if ( ! in_array( $value, $valid_positions, true ) ) {
			add_settings_error(
				'multichat_gpt_widget_position',
				'invalid_position',
				__( 'Invalid widget position', 'multichat-gpt' ),
				'error'
			);
			
			return 'bottom-right';
		}

		return $value;
	}

	/**
	 * Render settings section
	 *
	 * @return void
	 */
	public function render_section() {
		echo '<p>' . esc_html__( 'Configure your OpenAI ChatGPT API credentials and widget settings.', 'multichat-gpt' ) . '</p>';
	}

	/**
	 * Render cache section
	 *
	 * @return void
	 */
	public function render_cache_section() {
		echo '<p>' . esc_html__( 'Clear cached API responses and knowledge base data.', 'multichat-gpt' ) . '</p>';
		
		// Handle cache clearing
		if ( isset( $_POST['multichat_gpt_clear_cache'] ) && check_admin_referer( 'multichat_gpt_clear_cache' ) ) {
			$this->clear_all_caches();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'All caches cleared successfully.', 'multichat-gpt' ) . '</p></div>';
		}
		
		?>
		<form method="post">
			<?php wp_nonce_field( 'multichat_gpt_clear_cache' ); ?>
			<button type="submit" name="multichat_gpt_clear_cache" class="button button-secondary">
				<?php esc_html_e( 'Clear All Caches', 'multichat-gpt' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Render API key field
	 *
	 * @return void
	 */
	public function render_api_key_field() {
		$api_key = get_option( 'multichat_gpt_api_key' );
		?>
		<input 
			type="password" 
			name="multichat_gpt_api_key" 
			value="<?php echo esc_attr( $api_key ); ?>" 
			class="regular-text" 
			autocomplete="off"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: %s: URL to OpenAI API keys page */
				esc_html__( 'Get your API key from %s', 'multichat-gpt' ),
				'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">https://platform.openai.com/api-keys</a>'
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
	 *
	 * @return void
	 */
	public function render_page() {
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
	 * Clear all caches
	 *
	 * @return void
	 */
	private function clear_all_caches() {
		$this->api_handler->clear_cache();
		$this->knowledge_base->clear_cache();
		$this->logger->info( 'All caches cleared from admin panel' );
	}
}
