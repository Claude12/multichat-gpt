<?php
/**
 * Knowledge Base Builder Class
 * Converts crawled content into KB chunks
 *
 * @package MultiChatGPT
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiChat_KB_Builder {

	const KB_CACHE_KEY = 'multichat_gpt_knowledge_base';
	const KB_META_KEY = '_multichat_gpt_kb_timestamp'; // Store timestamp as option

	/**
	 * Build knowledge base from URLs
	 *
	 * @param array $urls List of page URLs to crawl
	 * @return array Knowledge base chunks
	 */
	public static function build_kb_from_urls( $urls ) {
		// Clear old cache first
		self::clear_cache();

		$kb_chunks = [];

		if ( empty( $urls ) ) {
			return $kb_chunks;
		}

		// Load crawler
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-content-crawler.php';

		foreach ( $urls as $url ) {
			// Get content from URL
			$content = MultiChat_Content_Crawler::get_page_content( $url );

			if ( ! empty( $content ) ) {
				// Extract title if possible
				$title = self::extract_title_from_url( $url );

				// Create KB chunk
				$chunk = [
					'url'     => $url,
					'title'   => $title,
					'content' => $content,
				];

				$kb_chunks[] = $chunk;

				// Add small delay to be respectful to server
				usleep( 300000 ); // 0.3 second delay
			}
		}

		// Store the KB permanently (no expiration)
		if ( ! empty( $kb_chunks ) ) {
			self::cache_knowledge_base( $kb_chunks );
		}

		return $kb_chunks;
	}

	/**
	 * Extract title from URL
	 *
	 * @param string $url Page URL
	 * @return string Page title
	 */
	private static function extract_title_from_url( $url ) {
		// Get page ID from URL
		$post = url_to_postid( $url );

		if ( $post ) {
			return get_the_title( $post );
		}

		// Fallback: extract from URL path
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$title = basename( $path, '/' );
		$title = str_replace( [ '-', '_' ], ' ', $title );

		return ucwords( $title ) ?: 'Page';
	}

	/**
	 * Cache knowledge base permanently (no expiration)
	 * Uses option instead of transient to avoid auto-expiration
	 *
	 * @param array $kb Knowledge base chunks
	 * @return bool
	 */
	public static function cache_knowledge_base( $kb ) {
		// Store KB as option (permanent until manually cleared)
		$result = update_option( self::KB_CACHE_KEY, $kb );
		
		// Store timestamp of when it was cached
		update_option( self::KB_META_KEY, current_time( 'mysql' ) );
		
		return $result;
	}

	/**
	 * Get cached knowledge base (never expires)
	 *
	 * @return array|false
	 */
	public static function get_cached_knowledge_base() {
		return get_option( self::KB_CACHE_KEY, false );
	}

	/**
	 * Check if KB is cached
	 *
	 * @return bool
	 */
	public static function is_kb_cached() {
		return get_option( self::KB_CACHE_KEY, false ) !== false;
	}

	/**
	 * Clear cached knowledge base
	 *
	 * @return bool
	 */
	public static function clear_cache() {
		delete_option( self::KB_CACHE_KEY );
		delete_option( self::KB_META_KEY );
		return true;
	}

	/**
	 * Get KB cache timestamp
	 *
	 * @return string|false Timestamp when KB was last cached
	 */
	public static function get_cache_timestamp() {
		return get_option( self::KB_META_KEY, false );
	}

	/**
	 * Get KB stats
	 *
	 * @return array
	 */
	public static function get_kb_stats() {
		$kb = self::get_cached_knowledge_base();
		
		if ( ! $kb ) {
			return [
				'pages_indexed' => 0,
				'cache_status'  => 'No cache',
				'last_scanned'  => 'Never',
				'expires'       => 'Never (permanent)',
			];
		}

		$timestamp = self::get_cache_timestamp();
		
		return [
			'pages_indexed' => count( $kb ),
			'cache_status'  => 'Active',
			'last_scanned'  => $timestamp ? gmdate( 'Y-m-d H:i:s', strtotime( $timestamp ) ) : 'Unknown',
			'expires'       => 'Never (permanent)',
		];
	}
}