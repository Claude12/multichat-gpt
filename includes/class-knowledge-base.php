<?php
/**
 * Knowledge Base Class
 *
 * Manages knowledge base data with caching
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MultiChat_GPT_Knowledge_Base
 *
 * Handles knowledge base storage, retrieval, and relevance scoring
 */
class MultiChat_GPT_Knowledge_Base {

	/**
	 * Cache expiration time in seconds (24 hours)
	 *
	 * @var int
	 */
	private const CACHE_EXPIRATION = 86400;

	/**
	 * Get knowledge base chunks for a specific language
	 *
	 * @param string $language Language code (en, ar, es, fr, etc.).
	 * @return array Knowledge base chunks.
	 */
	public static function get_chunks( string $language = 'en' ): array {
		// Check cache first
		$cache_key = 'multichat_kb_' . $language;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			MultiChat_GPT_Logger::debug( 'KB cache hit', array( 'language' => $language ) );
			return $cached;
		}

		// Get knowledge base data
		$kb_data = self::get_knowledge_base_data();

		// Default to English if language not found
		if ( ! isset( $kb_data[ $language ] ) ) {
			MultiChat_GPT_Logger::warning(
				'Knowledge base not found for language, using English',
				array( 'requested_language' => $language )
			);
			$language = 'en';
		}

		$chunks = $kb_data[ $language ] ?? array();

		/**
		 * Filter to allow extending the knowledge base
		 *
		 * @param array  $chunks  Knowledge base array for the language.
		 * @param string $language Current language code.
		 */
		$chunks = apply_filters( 'multichat_gpt_knowledge_base', $chunks, $language );

		// Cache the result
		set_transient( $cache_key, $chunks, self::CACHE_EXPIRATION );

		return $chunks;
	}

	/**
	 * Find relevant knowledge base chunks using similarity matching
	 *
	 * @param string $user_message User's message.
	 * @param array  $kb_chunks    Knowledge base chunks.
	 * @param int    $num_results  Number of results to return.
	 * @return array Relevant chunks.
	 */
	public static function find_relevant_chunks( string $user_message, array $kb_chunks, int $num_results = 3 ): array {
		if ( empty( $kb_chunks ) ) {
			return array();
		}

		$similarities = array();

		// Calculate similarity between user message and each KB chunk
		foreach ( $kb_chunks as $chunk ) {
			$similarity = 0;
			similar_text( strtolower( $user_message ), strtolower( $chunk ), $similarity );
			$similarities[ $chunk ] = $similarity;
		}

		// Sort by similarity (descending)
		arsort( $similarities );

		// Return top results
		$relevant = array_slice( array_keys( $similarities ), 0, $num_results, true );

		MultiChat_GPT_Logger::debug(
			'Found relevant KB chunks',
			array(
				'num_chunks' => count( $relevant ),
				'top_score'  => ! empty( $similarities ) ? max( $similarities ) : 0,
			)
		);

		return $relevant;
	}

	/**
	 * Build system message for ChatGPT with knowledge base context
	 *
	 * @param string $language        Language code.
	 * @param array  $relevant_chunks Relevant knowledge base chunks.
	 * @return string System message.
	 */
	public static function build_system_message( string $language, array $relevant_chunks ): string {
		$language_names = array(
			'en' => 'English',
			'ar' => 'Arabic',
			'es' => 'Spanish',
			'fr' => 'French',
			'de' => 'German',
			'it' => 'Italian',
			'pt' => 'Portuguese',
			'ru' => 'Russian',
			'zh' => 'Chinese',
			'ja' => 'Japanese',
		);

		$lang_name = $language_names[ $language ] ?? $language;

		$kb_content = ! empty( $relevant_chunks )
			? implode( "\n\n", $relevant_chunks )
			: __( 'No relevant knowledge base available.', 'multichat-gpt' );

		/* translators: 1: language name, 2: knowledge base content */
		return sprintf(
			__(
				'You are a helpful customer support assistant. Answer only in %1$s. Use the provided knowledge base to answer questions accurately and helpfully.

KNOWLEDGE BASE:
%2$s

If the user\'s question is not covered in the knowledge base, politely let them know and offer to connect them with a human agent.',
				'multichat-gpt'
			),
			$lang_name,
			$kb_content
		);
	}

	/**
	 * Get hard-coded knowledge base data
	 *
	 * In a future version, this should be replaced with ACF fields or database storage
	 *
	 * @return array Knowledge base data by language.
	 */
	private static function get_knowledge_base_data(): array {
		return array(
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
	}

	/**
	 * Clear knowledge base cache
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		// Get all transient keys for knowledge base
		$languages = MultiChat_GPT_Utility::get_supported_languages();

		foreach ( $languages as $lang ) {
			delete_transient( 'multichat_kb_' . $lang );
		}

		MultiChat_GPT_Logger::info( 'Knowledge base cache cleared' );
	}
}
