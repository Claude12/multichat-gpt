<?php
/**
 * Knowledge Base Builder Class
 *
 * Converts crawled content into knowledge base chunks and manages caching
 *
 * @package MultiChatGPT
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge Base Builder class
 */
class MultiChat_KB_Builder {

	/**
	 * Cache key for storing KB data
	 *
	 * @var string
	 */
	private $cache_key = 'multichat_gpt_kb_cache';

	/**
	 * Cache duration in seconds (default: 7 days)
	 *
	 * @var int
	 */
	private $cache_duration = 604800;

	/**
	 * Maximum chunk size (characters)
	 *
	 * @var int
	 */
	private $max_chunk_size = 500;

	/**
	 * Build knowledge base from crawled content
	 *
	 * @param array $crawled_content Array of crawled content results
	 * @return array Knowledge base data
	 */
	public function build( $crawled_content ) {
		$kb_data = [
			'chunks'      => [],
			'metadata'    => [
				'total_pages'    => 0,
				'total_chunks'   => 0,
				'last_updated'   => current_time( 'mysql' ),
				'scan_timestamp' => time(),
			],
			'source_urls' => [],
		];

		if ( empty( $crawled_content ) || ! is_array( $crawled_content ) ) {
			return $kb_data;
		}

		foreach ( $crawled_content as $page_data ) {
			if ( empty( $page_data['content'] ) ) {
				continue;
			}

			// Create chunks from the content
			$chunks = $this->create_chunks( $page_data['content'] );

			foreach ( $chunks as $chunk ) {
				$kb_data['chunks'][] = [
					'text'   => $chunk,
					'url'    => $page_data['url'],
					'title'  => $page_data['title'] ?? '',
					'hash'   => md5( $chunk ),
				];
			}

			$kb_data['source_urls'][] = [
				'url'   => $page_data['url'],
				'title' => $page_data['title'] ?? '',
				'hash'  => $page_data['hash'] ?? '',
			];

			$kb_data['metadata']['total_pages']++;
		}

		$kb_data['metadata']['total_chunks'] = count( $kb_data['chunks'] );

		return $kb_data;
	}

	/**
	 * Create semantic chunks from content
	 *
	 * @param string $content Text content
	 * @return array Array of text chunks
	 */
	private function create_chunks( $content ) {
		$chunks = [];

		// Split by sentences first
		$sentences = preg_split( '/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );

		$current_chunk = '';

		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );

			if ( empty( $sentence ) ) {
				continue;
			}

			// Check if adding this sentence would exceed max chunk size
			if ( strlen( $current_chunk ) + strlen( $sentence ) + 1 > $this->max_chunk_size ) {
				// Save current chunk if not empty
				if ( ! empty( $current_chunk ) ) {
					$chunks[] = trim( $current_chunk );
				}

				// Start new chunk
				$current_chunk = $sentence;
			} else {
				// Add sentence to current chunk
				$current_chunk .= ( empty( $current_chunk ) ? '' : ' ' ) . $sentence;
			}
		}

		// Add remaining chunk
		if ( ! empty( $current_chunk ) ) {
			$chunks[] = trim( $current_chunk );
		}

		// If no chunks were created but content exists, add it as a single chunk
		if ( empty( $chunks ) && ! empty( $content ) ) {
			$chunks[] = substr( $content, 0, $this->max_chunk_size );
		}

		return $chunks;
	}

	/**
	 * Save knowledge base to cache
	 *
	 * @param array $kb_data Knowledge base data
	 * @return bool True on success, false on failure
	 */
	public function save_to_cache( $kb_data ) {
		return set_transient( $this->cache_key, $kb_data, $this->cache_duration );
	}

	/**
	 * Get knowledge base from cache
	 *
	 * @return array|false Knowledge base data or false if not cached
	 */
	public function get_from_cache() {
		return get_transient( $this->cache_key );
	}

	/**
	 * Clear knowledge base cache
	 *
	 * @return bool True on success, false on failure
	 */
	public function clear_cache() {
		return delete_transient( $this->cache_key );
	}

	/**
	 * Check if cache exists and is valid
	 *
	 * @return bool True if cache is valid, false otherwise
	 */
	public function is_cache_valid() {
		$cached_data = $this->get_from_cache();
		return ! empty( $cached_data ) && is_array( $cached_data );
	}

	/**
	 * Get knowledge base chunks for a specific language
	 *
	 * @param string $language Language code (currently returns all chunks)
	 * @return array Array of knowledge base chunks
	 */
	public function get_chunks( $language = 'en' ) {
		$cached_data = $this->get_from_cache();

		if ( empty( $cached_data ) || ! isset( $cached_data['chunks'] ) ) {
			return [];
		}

		// Return all chunks (language filtering can be added later)
		return $cached_data['chunks'];
	}

	/**
	 * Get knowledge base metadata
	 *
	 * @return array Metadata or empty array
	 */
	public function get_metadata() {
		$cached_data = $this->get_from_cache();

		if ( empty( $cached_data ) || ! isset( $cached_data['metadata'] ) ) {
			return [];
		}

		return $cached_data['metadata'];
	}

	/**
	 * Set cache duration
	 *
	 * @param int $duration Duration in seconds
	 */
	public function set_cache_duration( $duration ) {
		$this->cache_duration = max( 3600, (int) $duration ); // Minimum 1 hour
	}

	/**
	 * Set maximum chunk size
	 *
	 * @param int $size Maximum chunk size in characters
	 */
	public function set_max_chunk_size( $size ) {
		$this->max_chunk_size = max( 100, min( 2000, (int) $size ) );
	}
}
