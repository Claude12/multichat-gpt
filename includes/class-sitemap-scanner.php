<?php
/**
 * Sitemap Scanner Class
 * Handles parsing XML sitemaps and discovering URLs
 *
 * @package MultiChatGPT
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiChat_Sitemap_Scanner {

	/**
	 * Parse sitemap index and get all sitemap URLs
	 *
	 * @param string $sitemap_url URL to sitemap or sitemap index
	 * @param bool   $external_sitemap Whether this is an external sitemap
	 * @return array Array of page URLs
	 */
	public static function get_urls_from_sitemap( $sitemap_url, $external_sitemap = false ) {
		$all_urls = [];

		// Validate URL
		if ( empty( $sitemap_url ) ) {
			return $all_urls;
		}

		// Fetch the sitemap
		$response = wp_remote_get( $sitemap_url, [
			'timeout'   => 30,
			'sslverify' => false,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'MultiChat GPT: Failed to fetch sitemap - ' . $response->get_error_message() );
			return $all_urls;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return $all_urls;
		}

		// Suppress XML warnings
		$old_error = libxml_use_internal_errors( true );

		try {
			$xml = simplexml_load_string( $body );

			if ( ! $xml ) {
				libxml_use_internal_errors( $old_error );
				return $all_urls;
			}

			// Register namespace
			$xml->registerXPathNamespace( 'sm', 'http://www.sitemaps.org/schemas/sitemap/0.9' );

			// Check if this is a sitemap index
			$sitemaps = $xml->xpath( '//sm:sitemap/sm:loc' );
			if ( ! empty( $sitemaps ) ) {
				// This is a sitemap index - recursively fetch each sitemap
				foreach ( $sitemaps as $sitemap ) {
					$sitemap_url = (string) $sitemap;
					// Log the sitemap being fetched
					error_log( 'MultiChat GPT: Fetching sitemap: ' . $sitemap_url );
					$urls = self::get_urls_from_sitemap( $sitemap_url, $external_sitemap );
					$all_urls = array_merge( $all_urls, $urls );
				}
			} else {
				// This is a regular sitemap - extract URLs
				$urls = $xml->xpath( '//sm:url/sm:loc' );
				foreach ( $urls as $url ) {
					$url_string = (string) $url;
					// Filter to pages only
					if ( self::is_valid_page_url( $url_string, $external_sitemap ) ) {
						$all_urls[] = $url_string;
					}
				}
			}
		} catch ( Exception $e ) {
			error_log( 'MultiChat GPT: Error parsing sitemap - ' . $e->getMessage() );
		}

		libxml_use_internal_errors( $old_error );

		return array_unique( $all_urls );
	}

	/**
	 * Check if URL is a valid page (not post, category, tag, etc.)
	 *
	 * @param string $url URL to check
	 * @param bool   $external_sitemap Whether this is an external sitemap
	 * @return bool
	 */
	private static function is_valid_page_url( $url, $external_sitemap = false ) {
		// Exclude common non-page URLs
		$excluded_patterns = [
			'/category/',
			'/tag/',
			'/author/',
			'/search',
			'/wp-',
			'/feed/',
			'/archive/',
			'/date/',
			'?',
			'#',
			'.pdf',
			'.jpg',
			'.png',
			'.gif',
			'.zip',
		];

		foreach ( $excluded_patterns as $pattern ) {
			if ( strpos( $url, $pattern ) !== false ) {
				return false;
			}
		}

		// If external sitemap, accept any URL
		if ( $external_sitemap ) {
			return true;
		}

		// For internal sitemaps, check domain
		$home_url = home_url( '/' );
		$site_url = site_url( '/' );

		// Must be from this site
		if ( strpos( $url, $home_url ) === false && strpos( $url, $site_url ) === false ) {
			return false;
		}

		return true;
	}
}