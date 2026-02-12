<?php
/**
 * FAQ Manager Class
 * Manages custom FAQ entries for knowledge base
 *
 * @package MultiChatGPT
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiChat_FAQ_Manager {

	const POST_TYPE = 'multichat_faq';
	const TAXONOMY = 'multichat_faq_category';

	/**
	 * Register FAQ post type and taxonomy
	 */
	public static function register_post_type() {
		// Register post type
		register_post_type(
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Chat FAQs', 'multichat-gpt' ),
					'singular_name' => __( 'Chat FAQ', 'multichat-gpt' ),
					'add_new_item'  => __( 'Add New FAQ', 'multichat-gpt' ),
					'edit_item'     => __( 'Edit FAQ', 'multichat-gpt' ),
				],
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'options-general.php',
				'supports'     => [ 'title', 'editor' ],
				'has_archive'  => false,
				'rewrite'      => false,
				'menu_icon'    => 'dashicons-editor-help',
			]
		);

		// Register taxonomy
		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'FAQ Categories', 'multichat-gpt' ),
					'singular_name' => __( 'FAQ Category', 'multichat-gpt' ),
				],
				'public'       => false,
				'show_ui'      => true,
				'hierarchical' => true,
			]
		);

		// Register with WPML if available
		self::register_wpml_support();
	}

	/**
	 * Register post type with WPML for translation
	 */
	private static function register_wpml_support() {
		// Check if WPML is active
		if ( ! function_exists( 'wpml_get_language_information' ) ) {
			return;
		}

		// Tell WPML to translate this post type
		do_action( 'wpml_register_custom_post_type_translatable', self::POST_TYPE );
	}

	/**
	 * Get all FAQs for a language
	 *
	 * @param string $language_code Language code (e.g., 'ar', 'en', 'fr')
	 * @return array Array of FAQ entries
	 */
	public static function get_language_faqs( $language_code = 'en' ) {
		$args = [
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		];

		// Filter by language if WPML is active
		if ( function_exists( 'wpml_get_language_information' ) ) {
			$args['language'] = $language_code;
		}

		$query = new WP_Query( $args );
		$faqs  = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$faqs[] = [
					'id'      => get_the_ID(),
					'title'   => get_the_title(),
					'content' => wp_strip_all_tags( get_the_content() ),
					'url'     => get_permalink(),
				];
			}
		}

		wp_reset_postdata();

		return $faqs;
	}

	/**
	 * Get all FAQs (all languages)
	 *
	 * @return array
	 */
	public static function get_all_faqs() {
		$args = [
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		];

		$query = new WP_Query( $args );
		$faqs  = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$faqs[] = [
					'id'      => get_the_ID(),
					'title'   => get_the_title(),
					'content' => wp_strip_all_tags( get_the_content() ),
					'url'     => get_permalink(),
					'type'    => 'faq',
				];
			}
		}

		wp_reset_postdata();

		return $faqs;
	}

	/**
	 * Create or update an FAQ
	 *
	 * @param string $title FAQ title (question)
	 * @param string $content FAQ content (answer)
	 * @param string $language_code Language code
	 * @return int|WP_Error FAQ post ID or error
	 */
	public static function create_faq( $title, $content, $language_code = 'en' ) {
		$post_id = wp_insert_post( [
			'post_type'    => self::POST_TYPE,
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => wp_kses_post( $content ),
			'post_status'  => 'publish',
		] );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set language for WPML if available
		if ( function_exists( 'wpml_get_language_information' ) ) {
			do_action( 'wpml_set_element_language_details', 
				[
					'element_id'    => $post_id,
					'element_type'  => self::POST_TYPE,
					'language_code' => $language_code,
				]
			);
		}

		return $post_id;
	}

	/**
	 * Delete an FAQ
	 *
	 * @param int $faq_id FAQ post ID
	 * @return bool
	 */
	public static function delete_faq( $faq_id ) {
		return wp_delete_post( $faq_id, true );
	}

	/**
	 * Get FAQ count
	 *
	 * @param string $language_code Language code
	 * @return int
	 */
	public static function count_faqs( $language_code = null ) {
		$args = [
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		];

		if ( $language_code && function_exists( 'wpml_get_language_information' ) ) {
			$args['language'] = $language_code;
		}

		$query = new WP_Query( $args );

		return $query->found_posts;
	}
}