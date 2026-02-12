<?php
/**
 * Admin Page Handler Class
 * Handles all admin dashboard rendering and logic
 *
 * @package MultiChatGPT
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiChat_Admin_Page {

	/**
	 * Render Settings page
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';

		$is_wpml_active = MultiChat_WPML_Scanner::is_wpml_active();
		$sitemap_url = get_option( 'multichat_gpt_sitemap_url', site_url( '/sitemap.xml' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MultiChat GPT - Settings', 'multichat-gpt' ); ?></h1>

			<?php if ( $is_wpml_active ) : ?>
				<?php self::render_wpml_interface( $is_wpml_active ); ?>
			<?php else : ?>
				<?php self::render_single_language_interface( $sitemap_url ); ?>
			<?php endif; ?>

			<?php self::render_settings_form(); ?>
		</div>

		<script>
			<?php self::render_settings_scripts( $is_wpml_active, $sitemap_url ); ?>
		</script>
		<?php
	}

	/**
	 * Render Chat FAQs page
	 */
	public static function render_faqs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-faq-manager.php';

		$current_language = self::get_current_language();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MultiChat GPT - Chat FAQs', 'multichat-gpt' ); ?></h1>
			<?php self::render_faq_section( $current_language ); ?>
		</div>

		<script>
			<?php self::render_faq_scripts( $current_language ); ?>
		</script>
		<?php
	}

	/**
	 * Render About page
	 */
	public static function render_about() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MultiChat GPT - About', 'multichat-gpt' ); ?></h1>

			<div style="background: white; padding: 30px; margin: 20px 0; border-left: 4px solid #0066cc; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h2><?php esc_html_e( 'About MultiChat GPT', 'multichat-gpt' ); ?></h2>
				<p style="font-size: 16px; line-height: 1.6;">
					<?php esc_html_e( 'MultiChat GPT is a powerful ChatGPT-powered multilingual chat widget for WordPress Multisite + WPML. It provides intelligent customer support by leveraging your website content and custom FAQs.', 'multichat-gpt' ); ?>
				</p>

				<h3><?php esc_html_e( 'Features', 'multichat-gpt' ); ?></h3>
				<ul style="list-style: disc; margin-left: 30px; font-size: 15px; line-height: 1.8;">
					<li><?php esc_html_e( 'ChatGPT-powered intelligent responses', 'multichat-gpt' ); ?></li>
					<li><?php esc_html_e( 'Multilingual support with WPML integration', 'multichat-gpt' ); ?></li>
					<li><?php esc_html_e( 'Automatic knowledge base from sitemap', 'multichat-gpt' ); ?></li>
					<li><?php esc_html_e( 'Custom FAQ management', 'multichat-gpt' ); ?></li>
					<li><?php esc_html_e( 'Persistent knowledge base caching', 'multichat-gpt' ); ?></li>
					<li><?php esc_html_e( 'Customizable widget position', 'multichat-gpt' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Getting Started', 'multichat-gpt' ); ?></h3>
				<ol style="margin-left: 30px; font-size: 15px; line-height: 1.8;">
					<li><?php esc_html_e( 'Get your OpenAI API key from https://platform.openai.com/api-keys', 'multichat-gpt' ); ?></li>
					<li><?php esc_html_e( 'Enter your API key in the Settings page', 'multichat-gpt' ); ?></li>
					<li><?php esc_html_e( 'Scan your sitemap to build the knowledge base', 'multichat-gpt' ); ?></li>
					<li><?php esc_html_e( 'Add custom FAQs in the Chat FAQs page', 'multichat-gpt' ); ?></li>
					<li><?php esc_html_e( 'The chat widget will appear on your frontend automatically', 'multichat-gpt' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Documentation', 'multichat-gpt' ); ?></h3>
				<p style="font-size: 15px; line-height: 1.6;">
					<?php esc_html_e( 'For detailed documentation, please visit:', 'multichat-gpt' ); ?>
					<a href="https://example.com/multichat-gpt" target="_blank" style="color: #0066cc; text-decoration: none; font-weight: bold;">
						<?php esc_html_e( 'Plugin Documentation', 'multichat-gpt' ); ?>
					</a>
				</p>

				<h3><?php esc_html_e( 'Plugin Information', 'multichat-gpt' ); ?></h3>
				<table style="width: 100%; max-width: 600px; border-collapse: collapse; margin-top: 15px;">
					<tr style="border-bottom: 1px solid #e0e0e0;">
						<td style="padding: 10px; font-weight: bold; width: 150px;"><?php esc_html_e( 'Version:', 'multichat-gpt' ); ?></td>
						<td style="padding: 10px;"><?php echo esc_html( MULTICHAT_GPT_VERSION ); ?></td>
					</tr>
					<tr style="border-bottom: 1px solid #e0e0e0;">
						<td style="padding: 10px; font-weight: bold;"><?php esc_html_e( 'Author:', 'multichat-gpt' ); ?></td>
						<td style="padding: 10px;">
							<a href="https://example.com" target="_blank" style="color: #0066cc; text-decoration: none;">
								<?php esc_html_e( 'Claudius Sachinda', 'multichat-gpt' ); ?>
							</a>
						</td>
					</tr>
					<tr style="border-bottom: 1px solid #e0e0e0;">
						<td style="padding: 10px; font-weight: bold;"><?php esc_html_e( 'License:', 'multichat-gpt' ); ?></td>
						<td style="padding: 10px;">GPL v2 or later</td>
					</tr>
					<tr>
						<td style="padding: 10px; font-weight: bold;"><?php esc_html_e( 'Support:', 'multichat-gpt' ); ?></td>
						<td style="padding: 10px;">
							<a href="https://example.com/support" target="_blank" style="color: #0066cc; text-decoration: none;">
								<?php esc_html_e( 'Get Support', 'multichat-gpt' ); ?>
							</a>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render admin page (legacy method - kept for backwards compatibility)
	 */
	public static function render() {
		self::render_settings();
	}

	/**
	 * Render WPML multi-language interface
	 */
	private static function render_wpml_interface( $is_wpml_active ) {
		?>
		<div style="background: #e3f2fd; padding: 20px; margin: 20px 0; border-left: 4px solid #2196f3;">
			<h2><?php esc_html_e( 'Multi-Language Knowledge Base', 'multichat-gpt' ); ?></h2>
			<p><?php esc_html_e( 'WPML detected. Manage knowledge bases for each language separately.', 'multichat-gpt' ); ?></p>

			<?php
			$stats = MultiChat_WPML_Scanner::get_multilingual_stats();
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
		<?php
	}

	/**
	 * Render single language interface
	 */
	private static function render_single_language_interface( $sitemap_url ) {
		$kb_stats = MultiChat_KB_Builder::get_kb_stats();
		$is_cached = MultiChat_KB_Builder::is_kb_cached();
		?>
		<div class="notice notice-info" style="margin-top: 20px; padding: 20px; border-left: 4px solid #2563eb;">
			<h3><?php esc_html_e( 'Knowledge Base Status', 'multichat-gpt' ); ?></h3>
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
		<?php endif;
	}

	/**
	 * Render FAQ management section
	 */
	private static function render_faq_section( $current_language ) {
		?>
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
		<?php
	}

	/**
	 * Render settings form
	 */
	private static function render_settings_form() {
		?>
		<form method="POST" action="options.php" style="margin-top: 30px;">
			<?php
			settings_fields( 'multichat_gpt_group' );
			do_settings_sections( 'multichat-gpt-settings' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render settings scripts
	 */
	private static function render_settings_scripts( $is_wpml_active, $sitemap_url ) {
		?>
		jQuery(document).ready(function($) {
			<?php if ( $is_wpml_active ) : ?>
				// WPML handlers...
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
				// Single language handlers...
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
		<?php
	}

	/**
	 * Render FAQ scripts
	 */
	private static function render_faq_scripts( $current_language ) {
		?>
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
		});
		<?php
	}

	/**
	 * Render admin scripts (legacy method - kept for backwards compatibility)
	 */
	private static function render_admin_scripts( $is_wpml_active, $sitemap_url, $current_language ) {
		// Call the separated methods for better maintainability
		self::render_settings_scripts( $is_wpml_active, $sitemap_url );
		self::render_faq_scripts( $current_language );
	}

	/**
	 * Get current language
	 */
	private static function get_current_language() {
		if ( function_exists( 'wpml_current_language' ) ) {
			return wpml_current_language();
		}
		return 'en';
	}
}