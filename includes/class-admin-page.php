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
	 * Render main settings page
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}

		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-kb-builder.php';
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-faq-manager.php';

		$is_wpml_active = MultiChat_WPML_Scanner::is_wpml_active();
		$sitemap_url = get_option( 'multichat_gpt_sitemap_url', site_url( '/sitemap.xml' ) );
		$current_language = self::get_current_language();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MultiChat GPT Settings', 'multichat-gpt' ); ?></h1>

			<?php if ( $is_wpml_active ) : ?>
				<?php self::render_wpml_interface( $is_wpml_active ); ?>
			<?php else : ?>
				<?php self::render_single_language_interface( $sitemap_url ); ?>
			<?php endif; ?>

			<?php self::render_settings_form(); ?>
		</div>

		<script>
			<?php self::render_admin_scripts( $is_wpml_active, $sitemap_url, $current_language ); ?>
		</script>
		<?php
	}

	/**
	 * Render about page with full feature list
	 */
	public static function render_about() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'multichat-gpt' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'About MultiChat GPT', 'multichat-gpt' ); ?></h1>

			<div style="max-width: 900px; margin: 30px 0;">

				<!-- Overview -->
				<div style="background: #f0f7ff; padding: 25px; margin-bottom: 25px; border-left: 4px solid #0066cc; border-radius: 4px;">
					<h2 style="margin-top: 0;"><?php esc_html_e( 'MultiChat GPT v1.2.2', 'multichat-gpt' ); ?></h2>
					<p style="font-size: 16px; line-height: 1.6;">
						<?php esc_html_e( 'ChatGPT-powered multilingual chat widget for WordPress with WPML support. Automatically build a knowledge base from your website content and let AI answer customer questions in any language.', 'multichat-gpt' ); ?>
					</p>
				</div>

				<!-- ═══════════════════════════════════════════ -->
				<!-- CORE FEATURES -->
				<!-- ═══════════════════════════════════════════ -->
				<div style="background: #fff; padding: 25px; margin-bottom: 25px; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin-top: 0;">🤖 <?php esc_html_e( 'Core Features', 'multichat-gpt' ); ?></h3>
					<ul style="list-style: none; padding: 0; margin: 0;">
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'ChatGPT Integration', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Connects to OpenAI GPT-3.5-turbo API for intelligent, context-aware responses to user questions.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Floating Chat Widget', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Polished, animated floating chat bubble that opens a full chat window on your site frontend.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Configurable Widget Position', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Place the widget at bottom-right or bottom-left from admin settings, synced to the frontend.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Auto Knowledge Base from Sitemap', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Scans your sitemap.xml, crawls each page, and builds a permanent knowledge base for GPT context.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Elementor Compatibility', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Content crawler targets Elementor container classes for better content extraction.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'JSON-LD Structured Data Extraction', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Parses application/ld+json schema markup from pages for richer knowledge base data.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Custom FAQ Management', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Dedicated multichat_faq custom post type with WordPress editor for creating FAQ entries.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'FAQ Categories Taxonomy', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Hierarchical taxonomy for organizing FAQs into categories.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Combined Knowledge Base', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Chat responses draw from both sitemap-crawled content AND custom FAQs merged together.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Permanent KB Caching', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Knowledge base is stored permanently until manually cleared or re-scanned.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0;">
							<strong><?php esc_html_e( 'Manual Cache Management', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Admin buttons to scan, re-scan, and clear the knowledge base on demand.', 'multichat-gpt' ); ?>
						</li>
					</ul>
				</div>

				<!-- ═══════════════════════════════════════════ -->
				<!-- MULTI-LANGUAGE / WPML -->
				<!-- ═══════════════════════════════════════════ -->
				<div style="background: #fff; padding: 25px; margin-bottom: 25px; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin-top: 0;">🌍 <?php esc_html_e( 'Multi-Language & WPML', 'multichat-gpt' ); ?></h3>
					<ul style="list-style: none; padding: 0; margin: 0;">
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Full WPML Integration', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Auto-detects WPML and provides per-language knowledge base management.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Per-Language Sitemap Scanning', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Discovers and scans language-specific sitemaps for each WPML language.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Per-Language KB Caching', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Separate cache keys per language so each knowledge base is independent.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Per-Language Cache Controls', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Scan or clear individual language KBs or all at once from the admin dashboard.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'WPML-Translatable FAQ Post Type', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'FAQs are registered as translatable with WPML for multi-language FAQ content.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0;">
							<strong><?php esc_html_e( 'Language-Aware Chat Responses', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'GPT system prompt instructs the AI to respond in the user\'s language (EN, AR, ES, FR).', 'multichat-gpt' ); ?>
						</li>
					</ul>
				</div>

				<!-- ═══════════════════════════════════════════ -->
				<!-- FRONTEND WIDGET -->
				<!-- ═══════════════════════════════════════════ -->
				<div style="background: #fff; padding: 25px; margin-bottom: 25px; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin-top: 0;">💬 <?php esc_html_e( 'Frontend Widget', 'multichat-gpt' ); ?></h3>
					<ul style="list-style: none; padding: 0; margin: 0;">
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Multilingual Widget UI', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Built-in translations for English, Arabic, Spanish, and French for all widget strings.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'WPML String Translation Support', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Widget labels are passed through WPML string translation filters for custom translations.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Chat History Persistence', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Saves last 20 messages in localStorage and notifies returning users.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Loading States', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Disables input and button with "Sending..." indicator while waiting for API response.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Keyboard Support', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Press Enter to send messages instantly.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Dark Mode Support', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Automatic dark theme via CSS prefers-color-scheme media query.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Responsive Design', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Mobile-friendly with viewport-adapted sizing for screens 480px and below.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0;">
							<strong><?php esc_html_e( 'Smooth Animations', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Slide-in transitions for chat window open/close and message appearance.', 'multichat-gpt' ); ?>
						</li>
					</ul>
				</div>

				<!-- ═══════════════════════════════════════════ -->
				<!-- ADMIN DASHBOARD -->
				<!-- ═══════════════════════════════════════════ -->
				<div style="background: #fff; padding: 25px; margin-bottom: 25px; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin-top: 0;">⚙️ <?php esc_html_e( 'Admin Dashboard', 'multichat-gpt' ); ?></h3>
					<ul style="list-style: none; padding: 0; margin: 0;">
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Dedicated Plugin Menu', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Top-level "MultiChat GPT" menu in WP admin with custom SVG icon.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Settings Page', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'API key, widget position, and sitemap URL configuration via WP Settings API.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Chat FAQs Submenu', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Direct link to the FAQ post type list under the plugin menu.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'About Page', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Version info, complete feature list, quick-start guide, and support links.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'WPML Multi-Language Dashboard', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Table showing each language\'s cache status, pages indexed, last scan time, with scan/clear buttons.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0;">
							<strong><?php esc_html_e( 'Single-Language Dashboard', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'For non-WPML sites: KB status, pages indexed, cache state, scan/clear controls.', 'multichat-gpt' ); ?>
						</li>
					</ul>
				</div>

				<!-- ═══════════════════════════════════════════ -->
				<!-- REST API & AJAX -->
				<!-- ═══════════════════════════════════════════ -->
				<div style="background: #fff; padding: 25px; margin-bottom: 25px; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin-top: 0;">🔌 <?php esc_html_e( 'REST API & AJAX', 'multichat-gpt' ); ?></h3>
					<ul style="list-style: none; padding: 0; margin: 0;">
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong>POST /multichat/v1/ask</strong> —
							<?php esc_html_e( 'Public endpoint for chat requests (message + language).', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong>GET /multichat/v1/faqs</strong> —
							<?php esc_html_e( 'Public endpoint to retrieve FAQs by language.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0;">
							<strong><?php esc_html_e( 'AJAX FAQ Management', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Admin AJAX handlers for creating, deleting, and listing FAQs.', 'multichat-gpt' ); ?>
						</li>
					</ul>
				</div>

				<!-- ═══════════════════════════════════════════ -->
				<!-- SECURITY & PERFORMANCE -->
				<!-- ═══════════════════════════════════════════ -->
				<div style="background: #fff; padding: 25px; margin-bottom: 25px; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin-top: 0;">🔒 <?php esc_html_e( 'Security & Performance', 'multichat-gpt' ); ?></h3>
					<ul style="list-style: none; padding: 0; margin: 0;">
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'API Rate Limiting', 'multichat-gpt' ); ?></strong> —
							<span style="background: #e8f5e9; padding: 2px 6px; border-radius: 3px; font-size: 11px; color: #2e7d32; font-weight: bold;">NEW in v1.2.2</span>
							<?php esc_html_e( 'IP-based rate limiting (10 requests/minute) on the /ask endpoint to protect your OpenAI billing from abuse.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Message Length Limit', 'multichat-gpt' ); ?></strong> —
							<span style="background: #e8f5e9; padding: 2px 6px; border-radius: 3px; font-size: 11px; color: #2e7d32; font-weight: bold;">NEW in v1.2.2</span>
							<?php esc_html_e( 'User messages are capped at 500 characters (server-side + client-side) to prevent token abuse.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Optimised KB Storage', 'multichat-gpt' ); ?></strong> —
							<span style="background: #e8f5e9; padding: 2px 6px; border-radius: 3px; font-size: 11px; color: #2e7d32; font-weight: bold;">NEW in v1.2.2</span>
							<?php esc_html_e( 'Large knowledge base data stored with autoload disabled so it is not loaded into memory on every page request.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Graceful Rate Limit UX', 'multichat-gpt' ); ?></strong> —
							<span style="background: #e8f5e9; padding: 2px 6px; border-radius: 3px; font-size: 11px; color: #2e7d32; font-weight: bold;">NEW in v1.2.2</span>
							<?php esc_html_e( 'Frontend widget shows a friendly "Too many requests" message in all 4 languages when rate limited.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Singleton Pattern', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Main plugin class uses get_instance() singleton to prevent multiple instantiations.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Keyword-Based Relevance Matching', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Finds the top 3 most relevant KB chunks per user question via intelligent word matching.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Content Noise Removal', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Strips common navigation and footer text patterns from crawled content.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Rate-Limited Crawling', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( '300ms delay between page crawls to be respectful to the server.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<strong><?php esc_html_e( 'Nonce Verification', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'All admin AJAX handlers protected with WordPress nonce verification and capability checks.', 'multichat-gpt' ); ?>
						</li>
						<li style="padding: 8px 0;">
							<strong><?php esc_html_e( 'Activation / Deactivation Hooks', 'multichat-gpt' ); ?></strong> —
							<?php esc_html_e( 'Sets up default options on activation and flushes rewrite rules on deactivation.', 'multichat-gpt' ); ?>
						</li>
					</ul>
				</div>

				<!-- ═══════════════════════════════════════════ -->
				<!-- WHAT'S NEW IN v1.2.2 -->
				<!-- ═══════════════════════════════════════════ -->
				<div style="background: #e8f5e9; padding: 25px; margin-bottom: 25px; border-left: 4px solid #4caf50; border-radius: 4px;">
					<h3 style="margin-top: 0;">🆕 <?php esc_html_e( 'What\'s New in v1.2.2', 'multichat-gpt' ); ?></h3>
					<ul style="line-height: 1.8; margin: 0; padding-left: 20px;">
						<li><strong><?php esc_html_e( 'API Rate Limiting', 'multichat-gpt' ); ?></strong> — <?php esc_html_e( 'Protects your OpenAI billing with 10 requests per IP per minute throttling via WordPress transients.', 'multichat-gpt' ); ?></li>
						<li><strong><?php esc_html_e( 'Message Length Cap', 'multichat-gpt' ); ?></strong> — <?php esc_html_e( '500 character limit on chat input (both server-side truncation and client-side maxlength).', 'multichat-gpt' ); ?></li>
						<li><strong><?php esc_html_e( 'Disabled Autoload on KB Data', 'multichat-gpt' ); ?></strong> — <?php esc_html_e( 'Large knowledge base options no longer autoloaded on every WordPress page load, improving site performance.', 'multichat-gpt' ); ?></li>
						<li><strong><?php esc_html_e( 'Widget Position from Admin Settings', 'multichat-gpt' ); ?></strong> — <?php esc_html_e( 'The widget position setting now properly flows from PHP admin settings to the frontend JavaScript.', 'multichat-gpt' ); ?></li>
						<li><strong><?php esc_html_e( 'Rate Limit Error Messages', 'multichat-gpt' ); ?></strong> — <?php esc_html_e( 'Friendly "Too many requests" messages displayed in English, Arabic, Spanish, and French.', 'multichat-gpt' ); ?></li>
						<li><strong><?php esc_html_e( 'Expanded About Page', 'multichat-gpt' ); ?></strong> — <?php esc_html_e( 'Complete feature catalogue with all 40+ features organized by category.', 'multichat-gpt' ); ?></li>
					</ul>
				</div>

				<!-- Quick Start -->
				<div style="background: #f9f9f9; padding: 25px; margin-bottom: 25px; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin-top: 0;">🚀 <?php esc_html_e( 'Quick Start Guide', 'multichat-gpt' ); ?></h3>
					<ol style="line-height: 1.8;">
						<li><?php esc_html_e( 'Get your OpenAI API key from ', 'multichat-gpt' ); ?><a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></li>
						<li><?php esc_html_e( 'Go to MultiChat GPT → Settings', 'multichat-gpt' ); ?></li>
						<li><?php esc_html_e( 'Paste your API key in the OpenAI API Key field', 'multichat-gpt' ); ?></li>
						<li><?php esc_html_e( 'Choose your widget position (Bottom Right or Bottom Left)', 'multichat-gpt' ); ?></li>
						<li><?php esc_html_e( 'Click "Scan Sitemap Now" to index your website content', 'multichat-gpt' ); ?></li>
						<li><?php esc_html_e( '(Optional) Add custom FAQs via MultiChat GPT → Chat FAQs', 'multichat-gpt' ); ?></li>
						<li><?php esc_html_e( 'The chat widget will appear on your website automatically!', 'multichat-gpt' ); ?></li>
					</ol>
				</div>

				<!-- Support — UPDATED: Added LinkedIn link -->
				<div style="background: #e8f5e9; padding: 25px; margin-bottom: 25px; border-left: 4px solid #4caf50; border-radius: 4px;">
					<h3 style="margin-top: 0;">🆘 <?php esc_html_e( 'Support & Documentation', 'multichat-gpt' ); ?></h3>
					<p>
						<strong><?php esc_html_e( 'Documentation:', 'multichat-gpt' ); ?></strong><br>
						<a href="https://github.com/Claude12/multichat-gpt" target="_blank">GitHub Repository</a> | 
						<a href="https://github.com/Claude12/multichat-gpt/issues" target="_blank"><?php esc_html_e( 'Report Issues', 'multichat-gpt' ); ?></a> |
						<a href="https://www.linkedin.com/in/claudius-sachinda-45670317a/" target="_blank"><?php esc_html_e( 'Connect on LinkedIn', 'multichat-gpt' ); ?></a>
					</p>
				</div>

				<!-- Author Info — UPDATED: Added LinkedIn link -->
				<div style="background: #f5f5f5; padding: 25px; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin-top: 0;">👨‍💻 <?php esc_html_e( 'About the Author', 'multichat-gpt' ); ?></h3>
					<p>
						<?php esc_html_e( 'MultiChat GPT is developed and maintained by', 'multichat-gpt' ); ?>
						<a href="https://www.linkedin.com/in/claudius-sachinda-45670317a/" target="_blank"><strong>Claudius Sachinda</strong></a>
						<?php esc_html_e( 'as an open-source WordPress plugin.', 'multichat-gpt' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Version:', 'multichat-gpt' ); ?></strong> <?php echo esc_html( MULTICHAT_GPT_VERSION ); ?><br>
						<strong><?php esc_html_e( 'Requirements:', 'multichat-gpt' ); ?></strong> <?php esc_html_e( 'WordPress 5.6+, PHP 7.4+', 'multichat-gpt' ); ?><br>
						<strong><?php esc_html_e( 'License:', 'multichat-gpt' ); ?></strong> <?php esc_html_e( 'GPL v2 or later', 'multichat-gpt' ); ?>
					</p>
				</div>

			</div>
		</div>
		<?php
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
	 * Render admin scripts
	 */
	private static function render_admin_scripts( $is_wpml_active, $sitemap_url, $current_language ) {
		?>
		jQuery(document).ready(function($) {
			var currentLanguage = '<?php echo esc_js( $current_language ); ?>';

			<?php if ( $is_wpml_active ) : ?>
				// WPML handlers
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
				// Single language handlers
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
	 * Get current language
	 */
	private static function get_current_language() {
		if ( function_exists( 'wpml_current_language' ) ) {
			return wpml_current_language();
		}
		return 'en';
	}
}