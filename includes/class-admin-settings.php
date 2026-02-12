<?php
/**
 * Admin Settings Class
 *
 * Manages admin interface and settings
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MultiChat_GPT_Admin_Settings
 *
 * Handles admin menu, settings registration, and rendering
 */
class MultiChat_GPT_Admin_Settings {

	/**
	 * Register admin hooks
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public static function add_admin_menu(): void {
		add_options_page(
			__( 'MultiChat GPT Settings', 'multichat-gpt' ),
			__( 'MultiChat GPT', 'multichat-gpt' ),
			'manage_options',
			'multichat-gpt-settings',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		// Register settings
		register_setting(
			'multichat_gpt_group',
			'multichat_gpt_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		register_setting(
			'multichat_gpt_group',
			'multichat_gpt_widget_position',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_widget_position' ),
				'default'           => 'bottom-right',
				'show_in_rest'      => false,
			)
		);

		// Add settings section
		add_settings_section(
			'multichat_gpt_section',
			__( 'ChatGPT Configuration', 'multichat-gpt' ),
			array( __CLASS__, 'render_settings_section' ),
			'multichat-gpt-settings'
		);

		// Add settings fields
		add_settings_field(
			'multichat_gpt_api_key',
			__( 'OpenAI API Key', 'multichat-gpt' ),
			array( __CLASS__, 'render_api_key_field' ),
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		add_settings_field(
			'multichat_gpt_widget_position',
			__( 'Widget Position', 'multichat-gpt' ),
			array( __CLASS__, 'render_position_field' ),
			'multichat-gpt-settings',
			'multichat_gpt_section'
		);

		// Add cache management section
		add_settings_section(
			'multichat_gpt_cache_section',
			__( 'Cache Management', 'multichat-gpt' ),
			array( __CLASS__, 'render_cache_section' ),
			'multichat-gpt-settings'
		);

		add_settings_field(
			'multichat_gpt_clear_cache',
			__( 'Clear Cache', 'multichat-gpt' ),
			array( __CLASS__, 'render_clear_cache_field' ),
			'multichat-gpt-settings',
			'multichat_gpt_cache_section'
		);
	}

	/**
	 * Sanitize widget position value
	 *
	 * @param string $value Widget position value.
	 * @return string Sanitized value.
	 */
	public static function sanitize_widget_position( string $value ): string {
		$allowed_positions = array( 'bottom-right', 'bottom-left' );

		if ( ! in_array( $value, $allowed_positions, true ) ) {
			return 'bottom-right';
		}

		return $value;
	}

	/**
	 * Render settings section description
	 *
	 * @return void
	 */
	public static function render_settings_section(): void {
		echo '<p>' . esc_html__( 'Configure your OpenAI ChatGPT API credentials and widget settings.', 'multichat-gpt' ) . '</p>';
	}

	/**
	 * Render cache section description
	 *
	 * @return void
	 */
	public static function render_cache_section(): void {
		echo '<p>' . esc_html__( 'Manage cache for API responses and knowledge base data.', 'multichat-gpt' ) . '</p>';
	}

	/**
	 * Render API key field
	 *
	 * @return void
	 */
	public static function render_api_key_field(): void {
		$api_key = get_option( 'multichat_gpt_api_key', '' );
		?>
		<input
			type="password"
			name="multichat_gpt_api_key"
			id="multichat_gpt_api_key"
			value="<?php echo esc_attr( $api_key ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: %s: OpenAI API keys URL */
				esc_html__( 'Get your API key from %s', 'multichat-gpt' ),
				'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">https://platform.openai.com/api-keys</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render widget position field
	 *
	 * @return void
	 */
	public static function render_position_field(): void {
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
	 * Render clear cache field
	 *
	 * @return void
	 */
	public static function render_clear_cache_field(): void {
		// Handle cache clearing
		if ( isset( $_POST['multichat_clear_cache'] ) && check_admin_referer( 'multichat_clear_cache_action', 'multichat_clear_cache_nonce' ) ) {
			MultiChat_GPT_API_Handler::clear_cache();
			MultiChat_GPT_Knowledge_Base::clear_cache();
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Cache cleared successfully!', 'multichat-gpt' ) . '</p></div>';
		}
		?>
		<form method="post" style="display: inline;">
			<?php wp_nonce_field( 'multichat_clear_cache_action', 'multichat_clear_cache_nonce' ); ?>
			<button type="submit" name="multichat_clear_cache" class="button button-secondary">
				<?php esc_html_e( 'Clear All Cache', 'multichat-gpt' ); ?>
			</button>
		</form>
		<p class="description">
			<?php esc_html_e( 'Clear cached API responses and knowledge base data. This will force fresh API calls.', 'multichat-gpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Render admin settings page
	 *
	 * @return void
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors(); ?>

			<form method="POST" action="options.php">
				<?php
				settings_fields( 'multichat_gpt_group' );
				do_settings_sections( 'multichat-gpt-settings' );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Plugin Information', 'multichat-gpt' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Version', 'multichat-gpt' ); ?></th>
					<td><?php echo esc_html( MULTICHAT_GPT_VERSION ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Documentation', 'multichat-gpt' ); ?></th>
					<td>
						<a href="<?php echo esc_url( MULTICHAT_GPT_PLUGIN_URL . 'INSTALLATION.md' ); ?>" target="_blank">
							<?php esc_html_e( 'View Installation Guide', 'multichat-gpt' ); ?>
						</a>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
