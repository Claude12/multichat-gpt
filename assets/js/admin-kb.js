/**
 * MultiChat GPT Admin KB Management
 * Handles the knowledge base scanning UI
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		const $scanButton = $('#multichat-scan-sitemap');
		const $statusDiv = $('#multichat-kb-status');
		const $progressDiv = $('#multichat-kb-progress');
		const $metadataDiv = $('#multichat-kb-metadata');

		// Scan sitemap button handler
		$scanButton.on('click', function (e) {
			e.preventDefault();

			if ($scanButton.prop('disabled')) {
				return;
			}

			// Get sitemap URL from settings
			const sitemapUrl = $('#multichat_gpt_sitemap_url').val();

			if (!sitemapUrl) {
				showStatus('error', 'Please enter a sitemap URL first and save settings.');
				return;
			}

			// Disable button and show progress
			$scanButton.prop('disabled', true).text('Scanning...');
			$progressDiv.html('<p>🔄 Scanning sitemap and extracting content...</p>').show();
			$statusDiv.hide();

			// Make AJAX request
			$.ajax({
				url: multiChatKB.ajaxUrl,
				method: 'POST',
				data: {
					action: 'multichat_scan_sitemap',
					nonce: multiChatKB.nonce,
					sitemap_url: sitemapUrl,
					force_refresh: true,
				},
				timeout: 120000, // 2 minutes timeout
			})
				.done(function (response) {
					if (response.success) {
						const data = response.data;
						showStatus(
							'success',
							`✅ Scan completed! Indexed ${data.total_pages} pages with ${data.total_chunks} chunks.`
						);
						updateMetadata(data);
					} else {
						showStatus('error', '❌ Scan failed: ' + (response.data?.message || 'Unknown error'));
					}
				})
				.fail(function (xhr, status, error) {
					let errorMsg = 'Request failed';
					if (status === 'timeout') {
						errorMsg = 'Request timed out. Please try again.';
					} else if (xhr.responseJSON?.message) {
						errorMsg = xhr.responseJSON.message;
					} else if (error) {
						errorMsg = error;
					}
					showStatus('error', '❌ Error: ' + errorMsg);
				})
				.always(function () {
					$scanButton.prop('disabled', false).text('Scan Sitemap Now');
					$progressDiv.hide();
				});
		});

		// Clear cache button handler
		$('#multichat-clear-cache').on('click', function (e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to clear the knowledge base cache?')) {
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true).text('Clearing...');

			$.ajax({
				url: multiChatKB.ajaxUrl,
				method: 'POST',
				data: {
					action: 'multichat_clear_kb_cache',
					nonce: multiChatKB.nonce,
				},
			})
				.done(function (response) {
					if (response.success) {
						showStatus('success', '✅ Cache cleared successfully.');
						$metadataDiv.html('<p><em>No knowledge base data cached.</em></p>');
					} else {
						showStatus('error', '❌ Failed to clear cache.');
					}
				})
				.fail(function () {
					showStatus('error', '❌ Request failed.');
				})
				.always(function () {
					$btn.prop('disabled', false).text('Clear Cache');
				});
		});

		// Helper: Show status message
		function showStatus(type, message) {
			const className = type === 'success' ? 'notice-success' : 'notice-error';
			$statusDiv
				.html('<p>' + message + '</p>')
				.removeClass('notice-success notice-error')
				.addClass('notice ' + className)
				.show();
		}

		// Helper: Update metadata display
		function updateMetadata(data) {
			const html = `
				<table class="widefat">
					<tbody>
						<tr>
							<th>Total Pages Indexed:</th>
							<td>${data.total_pages || 0}</td>
						</tr>
						<tr>
							<th>Total Knowledge Chunks:</th>
							<td>${data.total_chunks || 0}</td>
						</tr>
						<tr>
							<th>Last Updated:</th>
							<td>${data.last_updated || 'Never'}</td>
						</tr>
						<tr>
							<th>Cache Status:</th>
							<td><span style="color: green;">✓ Active</span></td>
						</tr>
					</tbody>
				</table>
			`;
			$metadataDiv.html(html);
		}
	});
})(jQuery);
