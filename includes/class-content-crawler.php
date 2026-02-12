<?php
/**
 * Content Crawler Class
 * Fetches and extracts content from web pages
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
		// Fetch the page
		$response = wp_remote_get( $url, [
			'timeout'   => 15,
			'sslverify' => false,
			'user-agent' => 'MultiChat-GPT-Crawler/1.0',
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

			// Remove script and style elements
			$remove_tags = [ 'script', 'style', 'nav', 'footer', 'noscript' ];
			foreach ( $remove_tags as $tag ) {
				while ( $element = $dom->getElementsByTagName( $tag )->item( 0 ) ) {
					$element->parentNode->removeChild( $element );
				}
			}

			// Extract main content
			$content = '';

			// Try to find main content area
			$main = $dom->getElementById( 'main' );
			if ( ! $main ) {
				$mains = $dom->getElementsByTagName( 'main' );
				if ( $mains->length > 0 ) {
					$main = $mains->item( 0 );
				}
			}

			if ( $main ) {
				$content = $main->textContent;
			} else {
				// Fallback to body content
				$body = $dom->getElementsByTagName( 'body' )->item( 0 );
				if ( $body ) {
					$content = $body->textContent;
				}
			}

			// Clean up whitespace
			$content = preg_replace( '/\s+/', ' ', $content );
			$content = trim( $content );

			// Limit content length to avoid token bloat
			if ( strlen( $content ) > 5000 ) {
				$content = substr( $content, 0, 5000 ) . '...';
			}

			return $content;

		} catch ( Exception $e ) {
			error_log( 'MultiChat GPT: Error extracting content from ' . $url . ' - ' . $e->getMessage() );
			return '';
		}
	}
}