<?php
/**
 * Knowledge Base Class
 *
 * Knowledge base management and retrieval with caching.
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge Base class for managing chat knowledge.
 *
 * @since 1.0.0
 */
class MultiChat_GPT_Knowledge_Base {

	/**
	 * Cache TTL in seconds (default: 24 hours)
	 *
	 * @var int
	 */
	private $cache_ttl = 86400;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Allow custom cache TTL via filter.
		$this->cache_ttl = (int) apply_filters( 'multichat_gpt_kb_cache_ttl', $this->cache_ttl );
	}

	/**
	 * Get knowledge base chunks for a specific language
	 *
	 * @param string $language Language code (en, ar, es, fr, etc.).
	 * @return array Knowledge base chunks.
	 */
	public function get_chunks( $language = 'en' ) {
		// Try to get from cache.
		$cache_key = $this->get_cache_key( $language );
		$cached_chunks = get_transient( $cache_key );

		if ( false !== $cached_chunks ) {
			MultiChat_GPT_Logger::debug( 'KB cache hit', array( 'language' => $language ) );
			return $cached_chunks;
		}

		// Load knowledge base data.
		$kb_data = $this->load_knowledge_base_data();

		// Get chunks for the language (fallback to English).
		$chunks = isset( $kb_data[ $language ] ) ? $kb_data[ $language ] : $kb_data['en'];

		/**
		 * Filter to allow extending the knowledge base
		 *
		 * @param array  $chunks  Knowledge base array for the language.
		 * @param string $language Current language code.
		 */
		$chunks = apply_filters( 'multichat_gpt_knowledge_base', $chunks, $language );

		// Cache the chunks.
		set_transient( $cache_key, $chunks, $this->cache_ttl );
		MultiChat_GPT_Logger::debug( 'KB cached', array( 'language' => $language ) );

		return $chunks;
	}

	/**
	 * Find relevant knowledge base chunks using similarity matching
	 *
	 * @param string $user_message User's message.
	 * @param string $language     Language code.
	 * @param int    $num_results  Number of results to return.
	 * @return array Relevant chunks.
	 */
	public function find_relevant_chunks( $user_message, $language = 'en', $num_results = 3 ) {
		// Get all chunks for the language.
		$kb_chunks = $this->get_chunks( $language );

		if ( empty( $kb_chunks ) ) {
			return array();
		}

		$similarities = array();

		// Calculate similarity between user message and each KB chunk.
		foreach ( $kb_chunks as $chunk ) {
			$similarity = 0;
			similar_text( strtolower( $user_message ), strtolower( $chunk ), $similarity );
			$similarities[ $chunk ] = $similarity;
		}

		// Sort by similarity (descending).
		arsort( $similarities );

		// Return top results.
		$relevant_chunks = array_slice( array_keys( $similarities ), 0, $num_results, true );

		MultiChat_GPT_Logger::debug(
			'Found relevant KB chunks',
			array(
				'language' => $language,
				'num_chunks' => count( $relevant_chunks ),
			)
		);

		return $relevant_chunks;
	}

	/**
	 * Build system message for ChatGPT using knowledge base
	 *
	 * @param string $language        Language code.
	 * @param array  $relevant_chunks Relevant knowledge base chunks.
	 * @return string System message.
	 */
	public function build_system_message( $language, $relevant_chunks = array() ) {
		$language_names = $this->get_language_names();
		$lang_name = isset( $language_names[ $language ] ) ? $language_names[ $language ] : $language;

		$kb_content = ! empty( $relevant_chunks )
			? implode( "\n\n", $relevant_chunks )
			: __( 'No relevant knowledge base available.', 'multichat-gpt' );

		$system_message = sprintf(
			/* translators: %1$s: Language name, %2$s: Knowledge base content */
			__( 'You are a helpful customer support assistant. Answer only in %1$s. Use the provided knowledge base to answer questions accurately and helpfully.\n\nKNOWLEDGE BASE:\n%2$s\n\nIf the user\'s question is not covered in the knowledge base, politely let them know and offer to connect them with a human agent.', 'multichat-gpt' ),
			$lang_name,
			$kb_content
		);

		return $system_message;
	}

	/**
	 * Load knowledge base data
	 *
	 * This can be extended to load from database, ACF fields, etc.
	 *
	 * @return array Knowledge base data by language.
	 */
	private function load_knowledge_base_data() {
		// Check for cached parsed data.
		$cache_key = 'multichat_gpt_kb_data_all';
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Hard-coded knowledge base (can be replaced with database query or ACF).
		$kb_data = array(
			'en' => array(
				'What are your business hours?',
				'Our business hours are Monday to Friday, 9 AM to 6 PM EST.',
				'How can I contact customer support?',
				'You can contact us via email at support@example.com or phone at 1-800-EXAMPLE.',
				'What is your return policy?',
				'We offer a 30-day money-back guarantee on all products.',
				'Do you ship internationally?',
				'Yes, we ship to over 150 countries worldwide.',
				'What payment methods do you accept?',
				'We accept all major credit cards, PayPal, and bank transfers.',
			),
			'ar' => array(
				'ما هي ساعات العمل لديكم؟',
				'ساعات عملنا من الاثنين إلى الجمعة، من الساعة 9 صباحًا إلى الساعة 6 مساءً بتوقيت EST.',
				'كيف يمكنني التواصل مع خدمة العملاء؟',
				'يمكنك التواصل معنا عبر البريد الإلكتروني support@example.com أو الهاتف 1-800-EXAMPLE.',
				'ما هي سياسة الإرجاع؟',
				'نقدم ضمان استرجاع الأموال لمدة 30 يومًا على جميع المنتجات.',
				'هل تقومون بالشحن الدولي؟',
				'نعم، نشحن إلى أكثر من 150 دولة في جميع أنحاء العالم.',
				'ما هي طرق الدفع التي تقبلونها؟',
				'نقبل جميع بطاقات الائتمان الرئيسية و PayPal والتحويلات البنكية.',
			),
			'es' => array(
				'¿Cuál es su horario de atención?',
				'Nuestro horario es de lunes a viernes, de 9 a.m. a 6 p.m. EST.',
				'¿Cómo puedo contactar al servicio al cliente?',
				'Puede contactarnos por correo electrónico a support@example.com o por teléfono al 1-800-EXAMPLE.',
				'¿Cuál es su política de devoluciones?',
				'Ofrecemos una garantía de devolución de dinero de 30 días en todos los productos.',
				'¿Envían a nivel internacional?',
				'Sí, enviamos a más de 150 países en todo el mundo.',
				'¿Qué métodos de pago aceptan?',
				'Aceptamos todas las tarjetas de crédito principales, PayPal y transferencias bancarias.',
			),
			'fr' => array(
				'Quels sont vos horaires de travail?',
				'Nos horaires sont du lundi au vendredi, de 9h à 18h EST.',
				'Comment puis-je contacter le service clientèle?',
				'Vous pouvez nous contacter par email à support@example.com ou par téléphone au 1-800-EXAMPLE.',
				'Quelle est votre politique de retour?',
				'Nous offrons une garantie de remboursement de 30 jours sur tous les produits.',
				'Livrez-vous à l\'international?',
				'Oui, nous livrons dans plus de 150 pays dans le monde.',
				'Quels modes de paiement acceptez-vous?',
				'Nous acceptons toutes les principales cartes de crédit, PayPal et les virements bancaires.',
			),
		);

		/**
		 * Filter to allow complete replacement of knowledge base data
		 *
		 * @param array $kb_data Complete knowledge base data structure.
		 */
		$kb_data = apply_filters( 'multichat_gpt_knowledge_base_data', $kb_data );

		// Cache the data.
		set_transient( $cache_key, $kb_data, $this->cache_ttl );

		return $kb_data;
	}

	/**
	 * Get language names mapping
	 *
	 * @return array Language code to name mapping.
	 */
	private function get_language_names() {
		// Check cache.
		$cache_key = 'multichat_gpt_lang_names';
		$cached_names = get_transient( $cache_key );

		if ( false !== $cached_names ) {
			return $cached_names;
		}

		$language_names = array(
			'en' => __( 'English', 'multichat-gpt' ),
			'ar' => __( 'Arabic', 'multichat-gpt' ),
			'es' => __( 'Spanish', 'multichat-gpt' ),
			'fr' => __( 'French', 'multichat-gpt' ),
			'de' => __( 'German', 'multichat-gpt' ),
			'it' => __( 'Italian', 'multichat-gpt' ),
			'pt' => __( 'Portuguese', 'multichat-gpt' ),
			'ru' => __( 'Russian', 'multichat-gpt' ),
			'zh' => __( 'Chinese', 'multichat-gpt' ),
			'ja' => __( 'Japanese', 'multichat-gpt' ),
		);

		/**
		 * Filter to allow extending language names
		 *
		 * @param array $language_names Language code to name mapping.
		 */
		$language_names = apply_filters( 'multichat_gpt_language_names', $language_names );

		// Cache the names.
		set_transient( $cache_key, $language_names, $this->cache_ttl );

		return $language_names;
	}

	/**
	 * Get cache key for language chunks
	 *
	 * @param string $language Language code.
	 * @return string Cache key.
	 */
	private function get_cache_key( $language ) {
		return 'multichat_gpt_kb_' . sanitize_key( $language );
	}

	/**
	 * Clear knowledge base cache
	 *
	 * @return void
	 */
	public function clear_cache() {
		global $wpdb;

		// Delete all KB transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_multichat_gpt_kb_' ) . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_multichat_gpt_kb_' ) . '%'
			)
		);

		// Also delete the language names cache.
		delete_transient( 'multichat_gpt_lang_names' );

		MultiChat_GPT_Logger::info( 'Knowledge base cache cleared' );
	}
}
