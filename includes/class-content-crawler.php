<?php
/**
 * Content Crawler Class
 *
 * Fetches and extracts text content from web pages
 *
 * @package MultiChatGPT
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Crawler class
 */
class MultiChat_Content_Crawler {

	/**
	 * Timeout for HTTP requests (seconds)
	 *
	 * @var int
	 */
	private $timeout = 5;

	/**
	 * Crawl a URL and extract its content
	 *
	 * @param string $url URL to crawl
	 * @return array|WP_Error Array with extracted content or WP_Error
	 */
	public function crawl( $url ) {
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid_url', __( 'URL is required', 'multichat-gpt' ) );
		}

		// Fetch page content
		$response = wp_remote_get(
			$url,
			[
				'timeout' => $this->timeout,
				'headers' => [
					'User-Agent' => 'MultiChat-GPT-Bot/1.0',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error(
				'fetch_failed',
				sprintf( __( 'Failed to fetch URL. HTTP status: %d', 'multichat-gpt' ), $status_code )
			);
		}

		$html_content = wp_remote_retrieve_body( $response );
		if ( empty( $html_content ) ) {
			return new WP_Error( 'empty_content', __( 'Page content is empty', 'multichat-gpt' ) );
		}

		// Extract text content
		$text_content = $this->extract_text( $html_content );

		// Get title if available
		$title = $this->extract_title( $html_content );

		return [
			'url'     => $url,
			'title'   => $title,
			'content' => $text_content,
			'hash'    => md5( $text_content ),
		];
	}

	/**
	 * Extract text content from HTML
	 *
	 * @param string $html HTML content
	 * @return string Extracted text
	 */
	private function extract_text( $html ) {
		// Remove script tags
		$html = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $html );

		// Remove style tags
		$html = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $html );

		// Remove navigation elements
		$html = preg_replace( '/<nav\b[^>]*>(.*?)<\/nav>/is', '', $html );

		// Remove header and footer
		$html = preg_replace( '/<header\b[^>]*>(.*?)<\/header>/is', '', $html );
		$html = preg_replace( '/<footer\b[^>]*>(.*?)<\/footer>/is', '', $html );

		// Remove aside elements (sidebars)
		$html = preg_replace( '/<aside\b[^>]*>(.*?)<\/aside>/is', '', $html );

		// Try to extract main content
		if ( preg_match( '/<main\b[^>]*>(.*?)<\/main>/is', $html, $matches ) ) {
			$html = $matches[1];
		} elseif ( preg_match( '/<article\b[^>]*>(.*?)<\/article>/is', $html, $matches ) ) {
			$html = $matches[1];
		} elseif ( preg_match( '/<div\b[^>]*class=["\'][^"\']*(?:content|entry|post)[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $matches ) ) {
			$html = $matches[1];
		}

		// Strip remaining HTML tags
		$text = wp_strip_all_tags( $html, true );

		// Clean up whitespace
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		// Decode HTML entities
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return $text;
	}

	/**
	 * Extract page title from HTML
	 *
	 * @param string $html HTML content
	 * @return string Page title or empty string
	 */
	private function extract_title( $html ) {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			return trim( wp_strip_all_tags( $matches[1] ) );
		}

		// Try h1 as fallback
		if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches ) ) {
			return trim( wp_strip_all_tags( $matches[1] ) );
		}

		return '';
	}

	/**
	 * Crawl multiple URLs
	 *
	 * @param array $urls Array of URL data
	 * @param int   $max_urls Maximum number of URLs to crawl (default: 50)
	 * @return array Array of crawl results
	 */
	public function crawl_multiple( $urls, $max_urls = 50 ) {
		$results = [
			'success' => [],
			'failed'  => [],
		];

		$count = 0;
		foreach ( $urls as $url_data ) {
			if ( $count >= $max_urls ) {
				break;
			}

			$url = is_array( $url_data ) ? $url_data['url'] : $url_data;

			$result = $this->crawl( $url );

			if ( is_wp_error( $result ) ) {
				$results['failed'][] = [
					'url'   => $url,
					'error' => $result->get_error_message(),
				];
			} else {
				$results['success'][] = $result;
			}

			$count++;

			// Small delay to avoid overwhelming the server
			usleep( 100000 ); // 0.1 seconds
		}

		return $results;
	}

	/**
	 * Set timeout for HTTP requests
	 *
	 * @param int $timeout Timeout in seconds
	 */
	public function set_timeout( $timeout ) {
		$this->timeout = max( 1, min( 30, (int) $timeout ) );
	}
}
