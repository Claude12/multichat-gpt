<?php
/**
 * Knowledge Base Builder Class
 * Converts crawled content into KB chunks
 * Updated for WPML multi-language support
 *
 * @package MultiChatGPT
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiChat_KB_Builder {

	const KB_CACHE_KEY = 'multichat_gpt_knowledge_base';
	const KB_META_KEY = '_multichat_gpt_kb_timestamp';

	/**
	 * Build knowledge base from URLs for a specific language
	 *
	 * @param array  $urls List of page URLs to crawl
	 * @param string $language_code Language code (for WPML)
	 * @return array Knowledge base chunks
	 */
	public static function build_kb_from_urls( $urls, $language_code = 'en' ) {
		// Clear old cache for this language first
		self::clear_cache( $language_code );

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
					'url'      => $url,
					'title'    => $title,
					'content'  => $content,
					'language' => $language_code,
				];

				$kb_chunks[] = $chunk;

				// Add small delay to be respectful to server
				usleep( 300000 ); // 0.3 second delay
			}
		}

		// Store the KB for this language
		if ( ! empty( $kb_chunks ) ) {
			self::cache_knowledge_base( $kb_chunks, $language_code );
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
	 * Cache knowledge base for a language
	 *
	 * @param array  $kb Knowledge base chunks
	 * @param string $language_code Language code
	 * @return bool
	 */
	public static function cache_knowledge_base( $kb, $language_code = 'en' ) {
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';

		// Use WPML-aware caching if available
		if ( MultiChat_WPML_Scanner::is_wpml_active() ) {
			return MultiChat_WPML_Scanner::cache_language_kb( $language_code, $kb );
		}

		// Fallback to single-language caching
		update_option( self::KB_CACHE_KEY, $kb );
		update_option( self::KB_META_KEY, current_time( 'mysql' ) );

		return true;
	}

	/**
	 * Get cached knowledge base
	 *
	 * @param string $language_code Language code
	 * @return array|false
	 */
	public static function get_cached_knowledge_base( $language_code = 'en' ) {
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';

		// Try WPML-aware retrieval first
		if ( MultiChat_WPML_Scanner::is_wpml_active() ) {
			$kb = MultiChat_WPML_Scanner::get_language_kb( $language_code );
			if ( $kb ) {
				return $kb;
			}
		}

		// Fallback to single-language cache
		return get_option( self::KB_CACHE_KEY, false );
	}

	/**
	 * Check if KB is cached for a language
	 *
	 * @param string $language_code Language code
	 * @return bool
	 */
	public static function is_kb_cached( $language_code = 'en' ) {
		return self::get_cached_knowledge_base( $language_code ) !== false;
	}

	/**
	 * Clear cached knowledge base for a language
	 *
	 * @param string $language_code Language code
	 * @return bool
	 */
	public static function clear_cache( $language_code = 'en' ) {
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';

		if ( MultiChat_WPML_Scanner::is_wpml_active() ) {
			return MultiChat_WPML_Scanner::clear_language_cache( $language_code );
		}

		// Fallback
		delete_option( self::KB_CACHE_KEY );
		delete_option( self::KB_META_KEY );

		return true;
	}

	/**
	 * Get KB cache timestamp
	 *
	 * @param string $language_code Language code
	 * @return string|false Timestamp when KB was last cached
	 */
	public static function get_cache_timestamp( $language_code = 'en' ) {
		require_once MULTICHAT_GPT_PLUGIN_DIR . 'includes/class-wpml-scanner.php';

		if ( MultiChat_WPML_Scanner::is_wpml_active() ) {
			$timestamp_key = MultiChat_WPML_Scanner::get_language_timestamp_key( $language_code );
			return get_option( $timestamp_key, false );
		}

		return get_option( self::KB_META_KEY, false );
	}

	/**
	 * Get KB stats for single language
	 *
	 * @param string $language_code Language code
	 * @return array
	 */
	public static function get_kb_stats( $language_code = 'en' ) {
		$kb = self::get_cached_knowledge_base( $language_code );

		if ( ! $kb ) {
			return [
				'pages_indexed' => 0,
				'cache_status'  => 'No cache',
				'last_scanned'  => 'Never',
				'expires'       => 'Never (permanent)',
			];
		}

		$timestamp = self::get_cache_timestamp( $language_code );

		return [
			'pages_indexed' => count( $kb ),
			'cache_status'  => 'Active',
			'last_scanned'  => $timestamp ? gmdate( 'Y-m-d H:i:s', strtotime( $timestamp ) ) : 'Unknown',
			'expires'       => 'Never (permanent)',
		];
	}
}