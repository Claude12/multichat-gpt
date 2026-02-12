/**
 * MultiChat GPT Frontend Widget
 * Optimized with event delegation, debouncing, and lazy loading
 *
 * @package MultiChatGPT
 * @since 1.0.0
 */

(function () {
	'use strict';

	// Configuration
	const config = {
		restUrl: multiChatGPT?.restUrl || '/wp-json/multichat/v1/ask',
		language: multiChatGPT?.language || 'en',
		position: localStorage.getItem('multichat_position') || 'bottom-right',
		debounceDelay: 300,
		initDelay: 100,
	};

	// Chat state
	const chatState = {
		isOpen: false,
		messages: [],
		isLoading: false,
		isInitialized: false,
	};

	// Debounce timer
	let debounceTimer = null;

	/**
	 * Lazy initialize the widget
	 */
	function lazyInit() {
		// Only initialize once
		if (chatState.isInitialized) {
			return;
		}

		chatState.isInitialized = true;

		// Delay to avoid blocking page load
		setTimeout(() => {
			createWidgetHTML();
			attachEventListeners();
			loadChatHistory();
		}, config.initDelay);
	}

	/**
	 * Create widget DOM structure
	 */
	function createWidgetHTML() {
		// Use DocumentFragment for better performance
		const fragment = document.createDocumentFragment();
		const container = document.createElement('div');
		container.id = 'multichat-gpt-widget';
		container.className = `multichat-container ${config.position}`;

		container.innerHTML = `
			<div id="multichat-chat-window" class="multichat-chat-window">
				<div class="multichat-header">
					<h3>${getTranslation('chatTitle')}</h3>
					<button id="multichat-close-btn" class="multichat-close-btn" aria-label="${getTranslation('closeButton')}">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="multichat-messages" id="multichat-messages" role="log" aria-live="polite" aria-atomic="false"></div>
				<div class="multichat-input-area">
					<input
						type="text"
						id="multichat-input"
						class="multichat-input"
						placeholder="${getTranslation('inputPlaceholder')}"
						disabled
						aria-label="${getTranslation('inputPlaceholder')}"
					/>
					<button
						id="multichat-send-btn"
						class="multichat-send-btn"
						aria-label="${getTranslation('sendButton')}"
						disabled
					>
						${getTranslation('sendButton')}
					</button>
				</div>
			</div>

			<button id="multichat-toggle-btn" class="multichat-toggle-btn" aria-label="${getTranslation('openChat')}">
				<span class="multichat-icon" aria-hidden="true">💬</span>
			</button>
		`;

		fragment.appendChild(container);
		document.body.appendChild(fragment);

		// Enable input after widget loads
		requestAnimationFrame(() => {
			const input = document.getElementById('multichat-input');
			const sendBtn = document.getElementById('multichat-send-btn');
			if (input && sendBtn) {
				input.disabled = false;
				sendBtn.disabled = false;
			}
		});
	}

	/**
	 * Attach event listeners using event delegation
	 */
	function attachEventListeners() {
		// Use event delegation on the widget container
		const widget = document.getElementById('multichat-gpt-widget');
		if (!widget) return;

		// Single click handler for all buttons
		widget.addEventListener('click', handleClick);

		// Input handler with debouncing
		const input = document.getElementById('multichat-input');
		if (input) {
			input.addEventListener('keypress', handleKeyPress);
			input.addEventListener('input', handleInputChange);
		}

		// Window resize handler (debounced)
		window.addEventListener('resize', debounce(handleResize, config.debounceDelay));
	}

	/**
	 * Handle click events using event delegation
	 *
	 * @param {Event} e Click event.
	 */
	function handleClick(e) {
		const target = e.target.closest('button');
		if (!target) return;

		const id = target.id;
		switch (id) {
			case 'multichat-toggle-btn':
				toggleChat();
				break;
			case 'multichat-close-btn':
				closeChat();
				break;
			case 'multichat-send-btn':
				if (!chatState.isLoading) {
					sendMessage();
				}
				break;
		}
	}

	/**
	 * Handle key press events
	 *
	 * @param {KeyboardEvent} e Keyboard event.
	 */
	function handleKeyPress(e) {
		if (e.key === 'Enter' && !e.shiftKey && !chatState.isLoading) {
			e.preventDefault();
			sendMessage();
		}
	}

	/**
	 * Handle input change (can be used for character count, etc.)
	 *
	 * @param {Event} e Input event.
	 */
	function handleInputChange(e) {
		// Future: Add character count, validation, etc.
	}

	/**
	 * Handle window resize
	 */
	function handleResize() {
		// Future: Adjust chat window size if needed
	}

	/**
	 * Debounce utility function
	 *
	 * @param {Function} func Function to debounce.
	 * @param {number} wait Wait time in milliseconds.
	 * @return {Function} Debounced function.
	 */
	function debounce(func, wait) {
		return function executedFunction(...args) {
			const later = () => {
				clearTimeout(debounceTimer);
				func(...args);
			};
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(later, wait);
		};
	}

	/**
	 * Toggle chat window open/close
	 */
	function toggleChat() {
		if (chatState.isOpen) {
			closeChat();
		} else {
			openChat();
		}
	}

	/**
	 * Open chat window
	 */
	function openChat() {
		const chatWindow = document.getElementById('multichat-chat-window');
		const toggleBtn = document.getElementById('multichat-toggle-btn');

		if (chatWindow) {
			// Use requestAnimationFrame for smooth animation
			requestAnimationFrame(() => {
				chatWindow.classList.add('multichat-open');
				toggleBtn?.classList.add('multichat-hidden');
				chatState.isOpen = true;
				toggleBtn?.setAttribute('aria-label', getTranslation('closeChat'));

				// Focus input after animation
				setTimeout(() => {
					const input = document.getElementById('multichat-input');
					input?.focus();
				}, 200);
			});
		}
	}

	/**
	 * Close chat window
	 */
	function closeChat() {
		const chatWindow = document.getElementById('multichat-chat-window');
		const toggleBtn = document.getElementById('multichat-toggle-btn');

		if (chatWindow) {
			requestAnimationFrame(() => {
				chatWindow.classList.remove('multichat-open');
				toggleBtn?.classList.remove('multichat-hidden');
				chatState.isOpen = false;
				toggleBtn?.setAttribute('aria-label', getTranslation('openChat'));
			});
		}
	}

	/**
	 * Send message to backend with debouncing to prevent spam
	 */
	async function sendMessage() {
		const input = document.getElementById('multichat-input');
		const message = input?.value?.trim();

		if (!message || chatState.isLoading) {
			return;
		}

		// Prevent duplicate submissions
		if (chatState.isLoading) {
			return;
		}

		// Add user message to UI
		addMessageToUI(message, 'user');
		input.value = '';

		// Set loading state
		chatState.isLoading = true;
		updateInputState();
		showTypingIndicator();

		try {
			const response = await fetch(config.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					message: message,
					language: config.language,
				}),
			});

			// Remove typing indicator
			hideTypingIndicator();

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const data = await response.json();

			if (data.success) {
				addMessageToUI(data.message, 'assistant');
				saveChatToHistory(message, data.message);
			} else {
				addMessageToUI(
					data.message || getTranslation('errorMessage'),
					'error'
				);
			}
		} catch (error) {
			hideTypingIndicator();
			console.error('Chat error:', error);
			addMessageToUI(getTranslation('errorMessage'), 'error');
		} finally {
			chatState.isLoading = false;
			updateInputState();
		}
	}

	/**
	 * Show typing indicator
	 */
	function showTypingIndicator() {
		const messagesContainer = document.getElementById('multichat-messages');
		if (!messagesContainer) return;

		const indicator = document.createElement('div');
		indicator.id = 'multichat-typing-indicator';
		indicator.className = 'multichat-message multichat-assistant';
		indicator.innerHTML = '<div class="multichat-message-content multichat-typing"><span>•</span><span>•</span><span>•</span></div>';

		messagesContainer.appendChild(indicator);
		scrollToBottom();
	}

	/**
	 * Hide typing indicator
	 */
	function hideTypingIndicator() {
		const indicator = document.getElementById('multichat-typing-indicator');
		if (indicator) {
			indicator.remove();
		}
	}

	/**
	 * Add message to chat UI (optimized DOM manipulation)
	 *
	 * @param {string} message Message text.
	 * @param {string} sender Message sender type.
	 */
	function addMessageToUI(message, sender = 'user') {
		const messagesContainer = document.getElementById('multichat-messages');
		if (!messagesContainer) return;

		// Create message element efficiently
		const messageEl = document.createElement('div');
		messageEl.className = `multichat-message multichat-${sender}`;

		const messageContent = document.createElement('div');
		messageContent.className = 'multichat-message-content';
		messageContent.textContent = message;

		messageEl.appendChild(messageContent);

		// Use DocumentFragment for better performance if adding multiple
		messagesContainer.appendChild(messageEl);

		// Auto-scroll to bottom
		scrollToBottom();

		// Store in chat state
		chatState.messages.push({
			sender,
			message,
			timestamp: new Date().toISOString(),
		});
	}

	/**
	 * Scroll messages to bottom (optimized)
	 */
	function scrollToBottom() {
		const messagesContainer = document.getElementById('multichat-messages');
		if (!messagesContainer) return;

		requestAnimationFrame(() => {
			messagesContainer.scrollTop = messagesContainer.scrollHeight;
		});
	}

	/**
	 * Update input state based on loading
	 */
	function updateInputState() {
		const input = document.getElementById('multichat-input');
		const sendBtn = document.getElementById('multichat-send-btn');

		if (input && sendBtn) {
			input.disabled = chatState.isLoading;
			sendBtn.disabled = chatState.isLoading;

			if (chatState.isLoading) {
				sendBtn.textContent = getTranslation('loadingButton');
				sendBtn.setAttribute('aria-busy', 'true');
			} else {
				sendBtn.textContent = getTranslation('sendButton');
				sendBtn.setAttribute('aria-busy', 'false');
			}
		}
	}

	/**
	 * Save chat to local storage with error handling
	 *
	 * @param {string} userMsg User message.
	 * @param {string} assistantMsg Assistant message.
	 */
	function saveChatToHistory(userMsg, assistantMsg) {
		try {
			const history = JSON.parse(
				localStorage.getItem('multichat_history') || '[]'
			);
			history.push({
				user: userMsg,
				assistant: assistantMsg,
				timestamp: new Date().toISOString(),
			});

			// Keep last 20 messages
			if (history.length > 20) {
				history.shift();
			}

			localStorage.setItem('multichat_history', JSON.stringify(history));
		} catch (error) {
			console.error('Failed to save chat history:', error);
		}
	}

	/**
	 * Load chat history from local storage
	 */
	function loadChatHistory() {
		try {
			const history = JSON.parse(
				localStorage.getItem('multichat_history') || '[]'
			);

			if (history.length > 0) {
				const welcomeMsg = document.createElement('div');
				welcomeMsg.className = 'multichat-message multichat-info';
				welcomeMsg.innerHTML = '<div class="multichat-message-content">' + 
					getTranslation('previousChats') + '</div>';

				const messagesContainer = document.getElementById('multichat-messages');
				messagesContainer?.appendChild(welcomeMsg);
			}
		} catch (error) {
			console.error('Failed to load chat history:', error);
		}
	}

	/**
	 * Get translation by key with caching
	 *
	 * @param {string} key Translation key.
	 * @return {string} Translated string.
	 */
	function getTranslation(key) {
		// Translation cache for performance
		if (!getTranslation.cache) {
			getTranslation.cache = {
				en: {
					chatTitle: 'Chat Support',
					inputPlaceholder: 'Ask me anything...',
					sendButton: 'Send',
					loadingButton: 'Sending...',
					errorMessage: 'Sorry, an error occurred. Please try again later.',
					previousChats: 'Previous chat history loaded.',
					openChat: 'Open chat',
					closeChat: 'Close chat',
				},
				ar: {
					chatTitle: 'دعم الدردشة',
					inputPlaceholder: 'اسأل أي شيء...',
					sendButton: 'إرسال',
					loadingButton: 'جاري الإرسال...',
					errorMessage: 'عذرًا، حدث خطأ. يرجى المحاولة لاحقًا.',
					previousChats: 'تم تحميل سجل الدردشة السابق.',
					openChat: 'فتح الدردشة',
					closeChat: 'إغلاق الدردشة',
				},
				es: {
					chatTitle: 'Soporte de Chat',
					inputPlaceholder: 'Pregúntame lo que sea...',
					sendButton: 'Enviar',
					loadingButton: 'Enviando...',
					errorMessage: 'Lo sentimos, ocurrió un error. Intente más tarde.',
					previousChats: 'Se cargó el historial de chat anterior.',
					openChat: 'Abrir chat',
					closeChat: 'Cerrar chat',
				},
				fr: {
					chatTitle: 'Support de Chat',
					inputPlaceholder: 'Demandez-moi n\'importe quoi...',
					sendButton: 'Envoyer',
					loadingButton: 'Envoi en cours...',
					errorMessage: 'Désolé, une erreur s\'est produite. Veuillez réessayer plus tard.',
					previousChats: 'L\'historique du chat précédent a été chargé.',
					openChat: 'Ouvrir le chat',
					closeChat: 'Fermer le chat',
				},
			};
		}

		const lang = config.language || 'en';
		const translations = getTranslation.cache[lang] || getTranslation.cache.en;
		return translations[key] || getTranslation.cache.en[key] || key;
	}

	// Initialize when DOM is ready (with lazy loading)
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', lazyInit);
	} else {
		lazyInit();
	}
})();