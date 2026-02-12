<?php
/**
 * Admin Settings Class
 *
 * Manages WordPress admin settings interface.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_Admin_Settings class.
 *
 * Handles admin menu, settings registration, and rendering.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_Admin_Settings {

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
	 * Logger instance
	 *
	 * @since 1.0.0
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param MultiChat_GPT_API_Handler    $api_handler    API handler instance.
	 * @param MultiChat_GPT_Knowledge_Base $knowledge_base Knowledge base instance.
	 * @param MultiChat_GPT_Logger         $logger         Logger instance.
	 */
	public function __construct( $api_handler, $knowledge_base, $logger ) {
		$this->api_handler    = $api_handler;
		$this->knowledge_base = $knowledge_base;
		$this->logger         = $logger;
	}

	/**
	 * Add admin menu
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'MultiChat GPT Settings', 'multichat-gpt' ),
			__( 'MultiChat GPT', 'multichat-gpt' ),
			'manage_options',
			'multichat-gpt-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings() {
		// Register API key setting.
		register_setting(
			'multichat_gpt_group',
			'multichat_gpt_api_key',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_api_key' ],
				'show_in_rest'      => false,
				'default'           => '',
			]
		);

		// Register widget position setting.
		register_setting(
			'multichat_gpt_group',
			'multichat_gpt_widget_position',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_position' ],
				'default'           => 'bottom-right',
				'show_in_rest'      => false,
			]
		);

		// Add settings section.
		add_settings_section(
			'multichat_gpt_section',
			__( 'ChatGPT Configuration', 'multichat-gpt' ),
			[ $this, 'render_settings_section' ],
			'multichat-gpt-settings'
		);

		// Add API key field.
		add_settings_field(
			'multichat_gpt_api_key',
			__( 'OpenAI API Key', 'multichat-gpt' ),
			[ $this, 'render_api_key_field' ],
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		// Add position field.
		add_settings_field(
			'multichat_gpt_widget_position',
			__( 'Widget Position', 'multichat-gpt' ),
			[ $this, 'render_position_field' ],
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		// Add cache management section.
		add_settings_section(
			'multichat_gpt_cache_section',
			__( 'Cache Management', 'multichat-gpt' ),
			[ $this, 'render_cache_section' ],
			'multichat-gpt-settings'
		);
	}

	/**
	 * Sanitize API key
	 *
	 * @since 1.0.0
	 * @param string $value API key value.
	 * @return string Sanitized API key.
	 */
	public function sanitize_api_key( $value ) {
		$value = sanitize_text_field( $value );

		// Log API key update (without exposing the key).
		if ( ! empty( $value ) ) {
			$this->logger->info( 'API key updated' );
		}

		return $value;
	}

	/**
	 * Sanitize widget position
	 *
	 * @since 1.0.0
	 * @param string $value Position value.
	 * @return string Sanitized position.
	 */
	public function sanitize_position( $value ) {
		$allowed = [ 'bottom-right', 'bottom-left' ];

		if ( ! in_array( $value, $allowed, true ) ) {
			return 'bottom-right';
		}

		return $value;
	}

	/**
	 * Render settings section description
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Configure your OpenAI ChatGPT API credentials and widget settings.', 'multichat-gpt' ) . '</p>';
	}

	/**
	 * Render cache section description
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_cache_section() {
		echo '<p>' . esc_html__( 'Clear cached API responses and knowledge base data to force fresh requests.', 'multichat-gpt' ) . '</p>';
		?>
		<p>
			<button type="button" class="button" id="multichat-clear-cache">
				<?php esc_html_e( 'Clear All Caches', 'multichat-gpt' ); ?>
			</button>
			<span id="multichat-cache-status" style="margin-left: 10px;"></span>
		</p>
		<script type="text/javascript">
			document.getElementById('multichat-clear-cache').addEventListener('click', function() {
				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to clear all caches?', 'multichat-gpt' ) ); ?>')) {
					return;
				}

				var button = this;
				var status = document.getElementById('multichat-cache-status');
				button.disabled = true;
				status.textContent = '<?php echo esc_js( __( 'Clearing...', 'multichat-gpt' ) ); ?>';

				fetch(ajaxurl, {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: 'action=multichat_gpt_clear_cache&nonce=<?php echo esc_js( wp_create_nonce( 'multichat_clear_cache' ) ); ?>'
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						status.style.color = 'green';
						status.textContent = '<?php echo esc_js( __( 'Cache cleared successfully!', 'multichat-gpt' ) ); ?>';
					} else {
						status.style.color = 'red';
						status.textContent = '<?php echo esc_js( __( 'Failed to clear cache.', 'multichat-gpt' ) ); ?>';
					}
					button.disabled = false;
					setTimeout(() => { status.textContent = ''; }, 3000);
				})
				.catch(error => {
					status.style.color = 'red';
					status.textContent = '<?php echo esc_js( __( 'Error clearing cache.', 'multichat-gpt' ) ); ?>';
					button.disabled = false;
				});
			});
		</script>
		<?php
	}

	/**
	 * Render API key field
	 *
	 * @since 1.0.0
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
				'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">OpenAI Platform</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render position field
	 *
	 * @since 1.0.0
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
	 * Render settings page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
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
	 * Handle AJAX cache clear request
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_clear_cache() {
		check_ajax_referer( 'multichat_clear_cache', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'multichat-gpt' ) ] );
		}

		// Clear API cache.
		$this->api_handler->clear_cache();

		// Clear knowledge base cache.
		$this->knowledge_base->clear_cache();

		$this->logger->info( 'All caches cleared via admin panel' );

		wp_send_json_success( [ 'message' => __( 'Cache cleared successfully', 'multichat-gpt' ) ] );
	}
}
