/**
 * MultiChat GPT Frontend Widget
 * Handles the floating chat interface with optimizations
 * Features: Event delegation, debouncing, lazy loading
 */

(function () {
	'use strict';

	// Configuration
	const config = {
		restUrl: multiChatGPT?.restUrl || '/wp-json/multichat/v1/ask',
		language: multiChatGPT?.language || 'en',
		position: multiChatGPT?.position || localStorage.getItem('multichat_position') || 'bottom-right',
		debounceDelay: 300, // ms
	};

	// Chat state
	const chatState = {
		isOpen: false,
		messages: [],
		isLoading: false,
	};

	// DOM references cache
	const domCache = {};

	/**
	 * Debounce utility function
	 *
	 * @param {Function} func Function to debounce
	 * @param {number} wait Wait time in milliseconds
	 * @return {Function} Debounced function
	 */
	function debounce(func, wait) {
		let timeout;
		return function executedFunction(...args) {
			const later = () => {
				clearTimeout(timeout);
				func(...args);
			};
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
		};
	}

	/**
	 * Initialize the widget
	 */
	function init() {
		createWidgetHTML();
		cacheDOM();
		attachEventListeners();
		loadChatHistory();
	}

	/**
	 * Cache DOM references
	 */
	function cacheDOM() {
		domCache.container = document.getElementById('multichat-gpt-widget');
		domCache.chatWindow = document.getElementById('multichat-chat-window');
		domCache.toggleBtn = document.getElementById('multichat-toggle-btn');
		domCache.closeBtn = document.getElementById('multichat-close-btn');
		domCache.sendBtn = document.getElementById('multichat-send-btn');
		domCache.input = document.getElementById('multichat-input');
		domCache.messages = document.getElementById('multichat-messages');
	}

	/**
	 * Initialize the widget
	 */
	function init() {
		createWidgetHTML();
		attachEventListeners();
		loadChatHistory();
	}

	/**
	 * Create widget DOM structure
	 */
	function createWidgetHTML() {
		// Create widget container
		const container = document.createElement('div');
		container.id = 'multichat-gpt-widget';
		container.className = `multichat-container ${config.position}`;

		container.innerHTML = `
			<div id="multichat-chat-window" class="multichat-chat-window">
				<div class="multichat-header">
					<h3>${getTranslation('chatTitle')}</h3>
					<button id="multichat-close-btn" class="multichat-close-btn" aria-label="Close chat">
						<span>×</span>
					</button>
				</div>
				<div class="multichat-messages" id="multichat-messages"></div>
				<div class="multichat-input-area">
					<input
						type="text"
						id="multichat-input"
						class="multichat-input"
						placeholder="${getTranslation('inputPlaceholder')}"
						disabled
					/>
					<button
						id="multichat-send-btn"
						class="multichat-send-btn"
						aria-label="Send message"
						disabled
					>
						${getTranslation('sendButton')}
					</button>
				</div>
			</div>

			<button id="multichat-toggle-btn" class="multichat-toggle-btn" aria-label="Open chat">
				<span class="multichat-icon">💬</span>
			</button>
		`;

		document.body.appendChild(container);
	}

	/**
	 * Attach event listeners with event delegation
	 */
	function attachEventListeners() {
		// Use event delegation on container
		if (domCache.container) {
			domCache.container.addEventListener('click', handleContainerClick);
		}

		// Keyboard event with debouncing
		if (domCache.input) {
			domCache.input.addEventListener('keypress', handleKeyPress);
			
			// Enable input after widget loads
			setTimeout(() => {
				domCache.input.disabled = false;
				if (domCache.sendBtn) {
					domCache.sendBtn.disabled = false;
				}
			}, 500);
		}

		// Handle window resize with debouncing
		window.addEventListener('resize', debounce(handleResize, config.debounceDelay));
	}

	/**
	 * Handle container click events (event delegation)
	 *
	 * @param {Event} e Click event
	 */
	function handleContainerClick(e) {
		const target = e.target;

		// Toggle button clicked
		if (target.closest('#multichat-toggle-btn')) {
			e.preventDefault();
			toggleChat();
			return;
		}

		// Close button clicked
		if (target.closest('#multichat-close-btn')) {
			e.preventDefault();
			closeChat();
			return;
		}

		// Send button clicked
		if (target.closest('#multichat-send-btn')) {
			e.preventDefault();
			sendMessage();
			return;
		}
	}

	/**
	 * Handle key press events
	 *
	 * @param {Event} e Keyboard event
	 */
	function handleKeyPress(e) {
		if (e.key === 'Enter' && !e.shiftKey && !chatState.isLoading) {
			e.preventDefault();
			sendMessage();
		}
	}

	/**
	 * Handle window resize (debounced)
	 */
	function handleResize() {
		// Adjust chat window height if needed
		if (chatState.isOpen && domCache.chatWindow) {
			const viewportHeight = window.innerHeight;
			const maxHeight = Math.min(500, viewportHeight * 0.8);
			domCache.chatWindow.style.maxHeight = maxHeight + 'px';
		}
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
		if (!domCache.chatWindow || !domCache.toggleBtn) return;

		domCache.chatWindow.classList.add('multichat-open');
		domCache.toggleBtn.classList.add('multichat-hidden');
		chatState.isOpen = true;

		// Focus input
		setTimeout(() => {
			domCache.input?.focus();
		}, 200);

		// Trigger resize handler
		handleResize();
	}

	/**
	 * Close chat window
	 */
	function closeChat() {
		if (!domCache.chatWindow || !domCache.toggleBtn) return;

		domCache.chatWindow.classList.remove('multichat-open');
		domCache.toggleBtn.classList.remove('multichat-hidden');
		chatState.isOpen = false;
	}

	/**
	 * Send message to backend
	 */
	async function sendMessage() {
		const message = domCache.input?.value?.trim();

		if (!message || chatState.isLoading) {
			return;
		}

		// Add user message to UI
		addMessageToUI(message, 'user');
		domCache.input.value = '';

		// Set loading state
		chatState.isLoading = true;
		updateInputState();

		// Show typing indicator
		const typingIndicator = addTypingIndicator();

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
			if (typingIndicator) {
				typingIndicator.remove();
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
			console.error('Chat error:', error);
			
			// Remove typing indicator
			if (typingIndicator) {
				typingIndicator.remove();
			}
			
			addMessageToUI(getTranslation('errorMessage'), 'error');
		} finally {
			chatState.isLoading = false;
			updateInputState();
		}
	}

	/**
	 * Add typing indicator
	 *
	 * @return {HTMLElement|null} Typing indicator element
	 */
	function addTypingIndicator() {
		if (!domCache.messages) return null;

		const indicator = document.createElement('div');
		indicator.className = 'multichat-message multichat-assistant multichat-typing';
		indicator.innerHTML = '<div class="multichat-message-content"><span class="multichat-typing-dots">...</span></div>';
		
		domCache.messages.appendChild(indicator);
		domCache.messages.scrollTop = domCache.messages.scrollHeight;
		
		return indicator;
	}

	/**
	 * Add message to chat UI (optimized DOM operations)
	 *
	 * @param {string} message Message text
	 * @param {string} sender Sender type (user, assistant, error, info)
	 */
	function addMessageToUI(message, sender = 'user') {
		if (!domCache.messages) return;

		// Create document fragment for better performance
		const fragment = document.createDocumentFragment();
		
		const messageEl = document.createElement('div');
		messageEl.className = `multichat-message multichat-${sender}`;

		const messageContent = document.createElement('div');
		messageContent.className = 'multichat-message-content';
		messageContent.textContent = message;

		messageEl.appendChild(messageContent);
		fragment.appendChild(messageEl);
		
		// Single DOM append
		domCache.messages.appendChild(fragment);

		// Auto-scroll to bottom with smooth behavior
		requestAnimationFrame(() => {
			domCache.messages.scrollTop = domCache.messages.scrollHeight;
		});

		// Store in chat state
		chatState.messages.push({
			sender,
			message,
			timestamp: new Date().toISOString(),
		});
	}

	/**
	 * Update input state based on loading
	 */
	function updateInputState() {
		if (!domCache.input || !domCache.sendBtn) return;

		domCache.input.disabled = chatState.isLoading;
		domCache.sendBtn.disabled = chatState.isLoading;

		if (chatState.isLoading) {
			domCache.sendBtn.textContent = getTranslation('loadingButton');
			domCache.sendBtn.classList.add('multichat-loading');
		} else {
			domCache.sendBtn.textContent = getTranslation('sendButton');
			domCache.sendBtn.classList.remove('multichat-loading');
		}
	}

	/**
	 * Save chat to local storage
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

			if (history.length > 0 && domCache.messages) {
				const welcomeMsg = document.createElement('div');
				welcomeMsg.className = 'multichat-message multichat-info';
				
				const welcomeContent = document.createElement('div');
				welcomeContent.className = 'multichat-message-content';
				welcomeContent.textContent = getTranslation('previousChats');
				
				welcomeMsg.appendChild(welcomeContent);
				domCache.messages.appendChild(welcomeMsg);
			}
		} catch (error) {
			console.error('Failed to load chat history:', error);
		}
	}

	/**
	 * Get translation by key
	 */
	function getTranslation(key) {
		const translations = {
			en: {
				chatTitle: 'Chat Support',
				inputPlaceholder: 'Ask me anything...',
				sendButton: 'Send',
				loadingButton: 'Sending...',
				errorMessage:
					'Sorry, an error occurred. Please try again later.',
				previousChats: 'Previous chat history loaded.',
			},
			ar: {
				chatTitle: 'دعم الدردشة',
				inputPlaceholder: 'اسأل أي شيء...',
				sendButton: 'إرسال',
				loadingButton: 'جاري الإرسال...',
				errorMessage: 'عذرًا، حدث خطأ. يرجى المحاولة لاحقًا.',
				previousChats: 'تم تحميل سجل الدردشة السابق.',
			},
			es: {
				chatTitle: 'Soporte de Chat',
				inputPlaceholder: 'Pregúntame lo que sea...',
				sendButton: 'Enviar',
				loadingButton: 'Enviando...',
				errorMessage: 'Lo sentimos, ocurrió un error. Intente más tarde.',
				previousChats: 'Se cargó el historial de chat anterior.',
			},
			fr: {
				chatTitle: 'Support de Chat',
				inputPlaceholder: 'Demandez-moi n\'importe quoi...',
				sendButton: 'Envoyer',
				loadingButton: 'Envoi en cours...',
				errorMessage: 'Désolé, une erreur s\'est produite. Veuillez réessayer plus tard.',
				previousChats: 'L\'historique du chat précédent a été chargé.',
			},
		};

		const lang = config.language || 'en';
		return translations[lang]?.[key] || translations.en[key] || key;
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();