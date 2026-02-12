<?php
/**
 * Content Crawler Class
 * Fetches and extracts content from web pages
 * Enhanced for Elementor sites
 *
 * @package MultiChatGPT
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiChat_Content_Crawler {

	/**
	 * Fetch and extract content from URL
	 *
	 * @param string $url Page URL to crawl
	 * @return string Extracted content
	 */
	public static function get_page_content( $url ) {
		// Fetch the page with better headers
		$response = wp_remote_get( $url, [
			'timeout'   => 30,
			'sslverify' => false,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
			'headers'   => [
				'Accept-Language' => 'en-US,en;q=0.9',
				'Accept-Encoding' => 'gzip, deflate',
			],
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'MultiChat GPT: Failed to fetch URL ' . $url . ' - ' . $response->get_error_message() );
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return '';
		}

		// Extract content
		return self::extract_content( $body, $url );
	}

	/**
	 * Extract main content from HTML
	 * Enhanced for Elementor sites
	 *
	 * @param string $html HTML content
	 * @param string $url Page URL
	 * @return string Extracted text content
	 */
	private static function extract_content( $html, $url ) {
		// Suppress HTML warnings
		$old_error = libxml_use_internal_errors( true );

		try {
			$dom = new DOMDocument();
			@$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
			libxml_use_internal_errors( $old_error );

			// Remove script and style elements (but keep data attributes)
			$remove_tags = [ 'script', 'style', 'nav', 'noscript' ];
			foreach ( $remove_tags as $tag ) {
				while ( $element = $dom->getElementsByTagName( $tag )->item( 0 ) ) {
					$element->parentNode->removeChild( $element );
				}
			}

			// Extract main content
			$content = '';

			// Try to find main content area (works for Elementor)
			$main = $dom->getElementById( 'main' );
			if ( ! $main ) {
				$mains = $dom->getElementsByTagName( 'main' );
				if ( $mains->length > 0 ) {
					$main = $mains->item( 0 );
				}
			}

			// For Elementor, also check for common class names
			if ( ! $main ) {
				$xpath = new DOMXPath( $dom );
				$content_divs = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' elementor-container ')]" );
				if ( $content_divs->length > 0 ) {
					$main = $content_divs->item( 0 );
				}
			}

			if ( $main ) {
				$content = $main->textContent;
			} else {
				// Fallback to body content
				$body_element = $dom->getElementsByTagName( 'body' )->item( 0 );
				if ( $body_element ) {
					$content = $body_element->textContent;
				}
			}

			// Extract from page title
			$titles = $dom->getElementsByTagName( 'title' );
			$page_title = '';
			if ( $titles->length > 0 ) {
				$page_title = $titles->item( 0 )->textContent;
			}

			// Extract from meta description
			$xpath = new DOMXPath( $dom );
			$meta_desc = '';
			$metas = $xpath->query( "//meta[@name='description']" );
			if ( $metas->length > 0 ) {
				$meta_desc = $metas->item( 0 )->getAttribute( 'content' );
			}

			// Combine all extracted text
			$all_content = $page_title . ' ' . $meta_desc . ' ' . $content;

			// Clean up whitespace
			$all_content = preg_replace( '/\s+/', ' ', $all_content );
			$all_content = trim( $all_content );

			// Remove common footer/header noise
			$all_content = self::remove_common_noise( $all_content );

			// Limit content length to avoid token bloat
			if ( strlen( $all_content ) > 5000 ) {
				$all_content = substr( $all_content, 0, 5000 ) . '...';
			}

			return $all_content;

		} catch ( Exception $e ) {
			error_log( 'MultiChat GPT: Error extracting content from ' . $url . ' - ' . $e->getMessage() );
			return '';
		}
	}

	/**
	 * Remove common footer/header noise
	 *
	 * @param string $content Content to clean
	 * @return string Cleaned content
	 */
	private static function remove_common_noise( $content ) {
		// Remove common nav text
		$noise_patterns = [
			'/Home\s+About\s+Contact\s+Privacy/',
			'/Search\s+\.\.\./i',
			'/loading\s+\.\.\./i',
			'/Cookie (Policy|Notice)/i',
			'/Web Accessibility/i',
			'/Skip to main content/i',
		];

		foreach ( $noise_patterns as $pattern ) {
			$content = preg_replace( $pattern, '', $content );
		}

		return trim( $content );
	}

	/**
	 * Extract specific data from JSON-LD structured data
	 * Useful for Elementor sites with schema markup
	 *
	 * @param string $html HTML content
	 * @return array Extracted structured data
	 */
	public static function extract_structured_data( $html ) {
		$structured_data = [];

		// Look for JSON-LD
		preg_match_all( '/<script type=["\']application\/ld\+json["\']>(.*?)<\/script>/is', $html, $matches );

		foreach ( $matches[1] as $json_string ) {
			$data = json_decode( $json_string, true );
			if ( is_array( $data ) ) {
				$structured_data[] = $data;
			}
		}

		return $structured_data;
	}
}