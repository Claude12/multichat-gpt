<?php
/**
 * Knowledge Base Manager Class
 *
 * Manages knowledge base content with caching
 *
 * @package MultiChatGPT
 * @since 1.1.0
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MultiChat_GPT_Knowledge_Base class
 *
 * Handles knowledge base retrieval and chunk matching
 */
class MultiChat_GPT_Knowledge_Base {

	/**
	 * Logger instance
	 *
	 * @var MultiChat_GPT_Logger
	 */
	private $logger;

	/**
	 * Cache expiration time in seconds (24 hours)
	 *
	 * @var int
	 */
	private $cache_expiration = 86400;

	/**
	 * Constructor
	 *
	 * @param MultiChat_GPT_Logger $logger Logger instance
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get knowledge base chunks for a specific language
	 *
	 * @param string $language Language code (en, ar, es, fr)
	 * @return array Knowledge base chunks
	 */
	public function get_chunks( $language = 'en' ) {
		// Sanitize language code
		$language = sanitize_text_field( $language );

		// Check cache first
		$cache_key = 'multichat_gpt_kb_' . $language;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			$this->logger->debug( 'Knowledge base cache hit', array( 'language' => $language ) );
			return $cached;
		}

		// Get knowledge base data
		$kb_chunks = $this->get_hardcoded_kb( $language );

		/**
		 * Filter to allow extending the knowledge base
		 *
		 * @param array  $kb_chunks Knowledge base array for the language
		 * @param string $language  Current language code
		 */
		$kb_chunks = apply_filters( 'multichat_gpt_knowledge_base', $kb_chunks, $language );

		// Cache the knowledge base
		set_transient( $cache_key, $kb_chunks, $this->cache_expiration );

		return $kb_chunks;
	}

	/**
	 * Get hardcoded knowledge base
	 *
	 * @param string $language Language code
	 * @return array Knowledge base chunks
	 */
	private function get_hardcoded_kb( $language ) {
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

		// Default to English if language not found
		if ( ! isset( $kb_data[ $language ] ) ) {
			$language = 'en';
		}

		return $kb_data[ $language ];
	}

	/**
	 * Find relevant knowledge base chunks using similarity matching
	 *
	 * @param string $user_message User's message
	 * @param string $language     Language code
	 * @param int    $num_results  Number of results to return
	 * @return array Relevant knowledge base chunks
	 */
	public function find_relevant_chunks( $user_message, $language = 'en', $num_results = 3 ) {
		// Sanitize inputs
		$user_message = sanitize_text_field( $user_message );
		$num_results  = absint( $num_results );

		// Get knowledge base chunks
		$kb_chunks = $this->get_chunks( $language );

		if ( empty( $kb_chunks ) ) {
			$this->logger->warning( 'No knowledge base chunks found', array( 'language' => $language ) );
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
		return array_slice( array_keys( $similarities ), 0, $num_results, true );
	}

	/**
	 * Build system message for ChatGPT
	 *
	 * @param string $language        Language code
	 * @param array  $relevant_chunks Relevant knowledge base chunks
	 * @return string System message
	 */
	public function build_system_message( $language, $relevant_chunks ) {
		$language_names = array(
			'en' => 'English',
			'ar' => 'Arabic',
			'es' => 'Spanish',
			'fr' => 'French',
		);

		$lang_name = isset( $language_names[ $language ] ) ? $language_names[ $language ] : $language;

		$kb_content = ! empty( $relevant_chunks )
			? implode( "\n\n", $relevant_chunks )
			: __( 'No relevant knowledge base available.', 'multichat-gpt' );

		return sprintf(
			/* translators: 1: Language name, 2: Knowledge base content */
			__( 'You are a helpful customer support assistant. Answer only in %1$s. Use the provided knowledge base to answer questions accurately and helpfully.\n\nKNOWLEDGE BASE:\n%2$s\n\nIf the user\'s question is not covered in the knowledge base, politely let them know and offer to connect them with a human agent.', 'multichat-gpt' ),
			$lang_name,
			$kb_content
		);
	}

	/**
	 * Clear knowledge base cache
	 *
	 * @return void
	 */
	public function clear_cache() {
		$languages = array( 'en', 'ar', 'es', 'fr' );

		foreach ( $languages as $language ) {
			delete_transient( 'multichat_gpt_kb_' . $language );
		}

		$this->logger->info( 'Knowledge base cache cleared' );
	}
}
