<?php
/**
 * Sitemap Scanner Class
 *
 * Handles XML sitemap parsing and URL discovery
 *
 * @package MultiChatGPT
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sitemap Scanner class
 */
class MultiChat_Sitemap_Scanner {

	/**
	 * Timeout for HTTP requests (seconds)
	 *
	 * @var int
	 */
	private $timeout = 10;

	/**
	 * Scan sitemap and discover URLs
	 *
	 * @param string $sitemap_url URL of the sitemap or sitemap index
	 * @param array  $post_types Post types to include (default: ['page'])
	 * @return array|WP_Error Array of discovered URLs or WP_Error on failure
	 */
	public function scan( $sitemap_url, $post_types = [ 'page' ] ) {
		if ( empty( $sitemap_url ) ) {
			return new WP_Error( 'invalid_sitemap_url', __( 'Sitemap URL is required', 'multichat-gpt' ) );
		}

		// Fetch sitemap content
		$response = wp_remote_get(
			$sitemap_url,
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
				'sitemap_fetch_failed',
				sprintf( __( 'Failed to fetch sitemap. HTTP status: %d', 'multichat-gpt' ), $status_code )
			);
		}

		$xml_content = wp_remote_retrieve_body( $response );
		if ( empty( $xml_content ) ) {
			return new WP_Error( 'empty_sitemap', __( 'Sitemap content is empty', 'multichat-gpt' ) );
		}

		// Parse XML
		$urls = $this->parse_xml( $xml_content, $sitemap_url );

		if ( is_wp_error( $urls ) ) {
			return $urls;
		}

		// Filter URLs by post type if needed
		if ( ! empty( $post_types ) ) {
			$urls = $this->filter_urls_by_post_type( $urls, $post_types );
		}

		return $urls;
	}

	/**
	 * Parse XML content and extract URLs
	 *
	 * @param string $xml_content XML content
	 * @param string $base_url Base URL for resolving relative URLs
	 * @return array|WP_Error Array of URLs or WP_Error
	 */
	private function parse_xml( $xml_content, $base_url ) {
		// Suppress XML errors
		libxml_use_internal_errors( true );

		$xml = simplexml_load_string( $xml_content );

		if ( false === $xml ) {
			$errors = libxml_get_errors();
			libxml_clear_errors();
			return new WP_Error(
				'xml_parse_error',
				__( 'Failed to parse sitemap XML', 'multichat-gpt' ),
				[ 'errors' => $errors ]
			);
		}

		$urls = [];

		// Check if this is a sitemap index (contains multiple sitemaps)
		if ( isset( $xml->sitemap ) ) {
			// Process sitemap index
			foreach ( $xml->sitemap as $sitemap ) {
				$sitemap_loc = (string) $sitemap->loc;
				if ( ! empty( $sitemap_loc ) ) {
					// Recursively scan each sitemap
					$sub_urls = $this->scan( $sitemap_loc );
					if ( ! is_wp_error( $sub_urls ) && is_array( $sub_urls ) ) {
						$urls = array_merge( $urls, $sub_urls );
					}
				}
			}
		} elseif ( isset( $xml->url ) ) {
			// Process regular sitemap
			foreach ( $xml->url as $url ) {
				$loc = (string) $url->loc;
				if ( ! empty( $loc ) ) {
					$urls[] = [
						'url'     => $loc,
						'lastmod' => isset( $url->lastmod ) ? (string) $url->lastmod : '',
					];
				}
			}
		}

		return $urls;
	}

	/**
	 * Filter URLs by post type
	 *
	 * @param array $urls Array of URL data
	 * @param array $post_types Post types to include
	 * @return array Filtered URLs
	 */
	private function filter_urls_by_post_type( $urls, $post_types ) {
		$filtered_urls = [];

		foreach ( $urls as $url_data ) {
			$url = $url_data['url'];

			// Try to determine post type from URL
			$post_type = $this->detect_post_type( $url );

			if ( in_array( $post_type, $post_types, true ) ) {
				$filtered_urls[] = $url_data;
			}
		}

		return $filtered_urls;
	}

	/**
	 * Detect post type from URL
	 *
	 * @param string $url URL to analyze
	 * @return string Detected post type or 'unknown'
	 */
	private function detect_post_type( $url ) {
		// Remove trailing slash
		$url = untrailingslashit( $url );

		// Common patterns for different post types
		$patterns = [
			'page'     => [ '/(?!blog|news|post|article)/' ], // Pages typically don't have these keywords
			'post'     => [ '/blog/', '/news/', '/post/', '/article/' ],
			'product'  => [ '/product/', '/shop/', '/store/' ],
			'category' => [ '/category/' ],
			'tag'      => [ '/tag/' ],
		];

		// Check URL patterns
		foreach ( $patterns as $type => $type_patterns ) {
			foreach ( $type_patterns as $pattern ) {
				if ( preg_match( $pattern, $url ) ) {
					return $type;
				}
			}
		}

		// Default to page for root-level URLs
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! empty( $path ) && substr_count( $path, '/' ) <= 2 ) {
			return 'page';
		}

		return 'unknown';
	}

	/**
	 * Get unique URLs from array
	 *
	 * @param array $urls Array of URL data
	 * @return array Unique URLs
	 */
	public function get_unique_urls( $urls ) {
		$unique = [];
		$seen   = [];

		foreach ( $urls as $url_data ) {
			$url = $url_data['url'];
			if ( ! in_array( $url, $seen, true ) ) {
				$seen[]   = $url;
				$unique[] = $url_data;
			}
		}

		return $unique;
	}
}
