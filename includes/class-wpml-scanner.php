<?php
/**
 * WPML Scanner Class
 * Handles multi-language sitemap scanning for WPML sites
 *
 * @package MultiChatGPT
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiChat_WPML_Scanner {

	/**
	 * Get all active WPML languages
	 *
	 * @return array Array of language codes and details
	 */
	public static function get_active_languages() {
		$languages = [];

		// Check if WPML is active
		if ( ! function_exists( 'wpml_get_active_languages' ) ) {
			return $languages;
		}

		$wpml_languages = wpml_get_active_languages( 'skip_missing' );

		if ( is_array( $wpml_languages ) ) {
			foreach ( $wpml_languages as $lang_code => $lang_data ) {
				$languages[ $lang_code ] = [
					'code'        => $lang_code,
					'name'        => $lang_data['native_name'] ?? $lang_data['display_name'] ?? $lang_code,
					'display_name' => $lang_data['display_name'] ?? $lang_code,
					'default'     => isset( $lang_data['default_locale'] ) ? true : false,
				];
			}
		}

		return $languages;
	}

	/**
	 * Check if WPML is active
	 *
	 * @return bool
	 */
	public static function is_wpml_active() {
		return function_exists( 'wpml_get_active_languages' );
	}

	/**
	 * Get sitemap URL for a specific language
	 *
	 * @param string $language_code Language code (e.g., 'ar', 'en', 'fr')
	 * @return string Sitemap URL
	 */
	public static function get_language_sitemap_url( $language_code ) {
		if ( ! self::is_wpml_active() ) {
			return site_url( '/sitemap.xml' );
		}

		$home_url = home_url();

		// Get WPML language details
		$languages = self::get_active_languages();
		
		if ( ! isset( $languages[ $language_code ] ) ) {
			return $home_url . '/sitemap.xml';
		}

		// Build language-specific sitemap URL
		$language_url = $home_url . '/' . $language_code . '/';
		$sitemap_url = $language_url . 'sitemap_index.xml';

		return $sitemap_url;
	}

	/**
	 * Get all language-specific sitemap URLs
	 *
	 * @return array Array of language codes => sitemap URLs
	 */
	public static function get_all_language_sitemaps() {
		$sitemaps = [];
		$languages = self::get_active_languages();

		foreach ( $languages as $lang_code => $lang_data ) {
			$sitemaps[ $lang_code ] = self::get_language_sitemap_url( $lang_code );
		}

		return $sitemaps;
	}

	/**
	 * Get language-specific cache key
	 *
	 * @param string $language_code Language code
	 * @return string Cache key
	 */
	public static function get_language_cache_key( $language_code ) {
		return 'multichat_gpt_kb_' . sanitize_key( $language_code );
	}

	/**
	 * Get language-specific timestamp key
	 *
	 * @param string $language_code Language code
	 * @return string Timestamp key
	 */
	public static function get_language_timestamp_key( $language_code ) {
		return '_multichat_gpt_kb_timestamp_' . sanitize_key( $language_code );
	}

	/**
	 * Get all cached knowledge bases
	 *
	 * @return array Array of language => KB data
	 */
	public static function get_all_cached_kbs() {
		$all_kbs = [];
		$languages = self::get_active_languages();

		foreach ( $languages as $lang_code => $lang_data ) {
			$cache_key = self::get_language_cache_key( $lang_code );
			$kb = get_option( $cache_key, false );
			
			if ( $kb ) {
				$timestamp_key = self::get_language_timestamp_key( $lang_code );
				$timestamp = get_option( $timestamp_key, false );
				
				$all_kbs[ $lang_code ] = [
					'kb'        => $kb,
					'timestamp' => $timestamp,
					'count'     => is_array( $kb ) ? count( $kb ) : 0,
				];
			}
		}

		return $all_kbs;
	}

	/**
	 * Get KB stats for all languages
	 *
	 * @return array Array of stats per language
	 */
	public static function get_multilingual_stats() {
		$stats = [];
		$languages = self::get_active_languages();

		foreach ( $languages as $lang_code => $lang_data ) {
			$cache_key = self::get_language_cache_key( $lang_code );
			$kb = get_option( $cache_key, false );
			$timestamp_key = self::get_language_timestamp_key( $lang_code );
			$timestamp = get_option( $timestamp_key, false );

			$stats[ $lang_code ] = [
				'language'      => $lang_data['name'],
				'language_code' => $lang_code,
				'pages_indexed' => $kb ? count( $kb ) : 0,
				'cached'        => $kb ? true : false,
				'last_scanned'  => $timestamp ? gmdate( 'Y-m-d H:i:s', strtotime( $timestamp ) ) : 'Never',
			];
		}

		return $stats;
	}

	/**
	 * Clear cache for a specific language
	 *
	 * @param string $language_code Language code
	 * @return bool
	 */
	public static function clear_language_cache( $language_code ) {
		$cache_key = self::get_language_cache_key( $language_code );
		$timestamp_key = self::get_language_timestamp_key( $language_code );
		
		delete_option( $cache_key );
		delete_option( $timestamp_key );
		
		return true;
	}

	/**
	 * Clear all language caches
	 *
	 * @return bool
	 */
	public static function clear_all_caches() {
		$languages = self::get_active_languages();

		foreach ( $languages as $lang_code => $lang_data ) {
			self::clear_language_cache( $lang_code );
		}

		return true;
	}

	/**
	 * Cache KB for a specific language
	 *
	 * @param string $language_code Language code
	 * @param array  $kb Knowledge base chunks
	 * @return bool
	 */
	public static function cache_language_kb( $language_code, $kb ) {
		$cache_key = self::get_language_cache_key( $language_code );
		$timestamp_key = self::get_language_timestamp_key( $language_code );

		update_option( $cache_key, $kb );
		update_option( $timestamp_key, current_time( 'mysql' ) );

		return true;
	}

	/**
	 * Get cached KB for a specific language
	 *
	 * @param string $language_code Language code
	 * @return array|false
	 */
	public static function get_language_kb( $language_code ) {
		$cache_key = self::get_language_cache_key( $language_code );
		return get_option( $cache_key, false );
	}
}