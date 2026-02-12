/**
 * MultiChat GPT Frontend Widget
 * Optimized with debouncing, event delegation, and performance improvements
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
		position: multiChatGPT?.position || 'bottom-right',
		debounceDelay: 300,
	};

	// Chat state
	const chatState = {
		isOpen: false,
		messages: [],
		isLoading: false,
	};

	// Debounce utility function
	const debounce = (func, wait) => {
		let timeout;
		return function executedFunction(...args) {
			const later = () => {
				clearTimeout(timeout);
				func(...args);
			};
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
		};
	};

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
	 * Attach event listeners using event delegation
	 */
	function attachEventListeners() {
		const container = document.getElementById('multichat-gpt-widget');
		if (!container) return;

		// Use event delegation on container for better performance
		container.addEventListener('click', handleClick);
		container.addEventListener('keypress', handleKeyPress);

		// Enable input after widget loads
		setTimeout(() => {
			const input = document.getElementById('multichat-input');
			const sendBtn = document.getElementById('multichat-send-btn');
			if (input && sendBtn) {
				input.disabled = false;
				sendBtn.disabled = false;
			}
		}, 100);
	}

	/**
	 * Handle click events with event delegation
	 */
	function handleClick(e) {
		const target = e.target;

		// Toggle button click
		if (target.id === 'multichat-toggle-btn' || target.closest('#multichat-toggle-btn')) {
			e.preventDefault();
			toggleChat();
			return;
		}

		// Close button click
		if (target.id === 'multichat-close-btn' || target.closest('#multichat-close-btn')) {
			e.preventDefault();
			closeChat();
			return;
		}

		// Send button click
		if (target.id === 'multichat-send-btn' || target.closest('#multichat-send-btn')) {
			e.preventDefault();
			if (!chatState.isLoading) {
				sendMessage();
			}
			return;
		}
	}

	/**
	 * Handle keypress events
	 */
	function handleKeyPress(e) {
		if (e.target.id === 'multichat-input' && e.key === 'Enter' && !e.shiftKey && !chatState.isLoading) {
			e.preventDefault();
			sendMessage();
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
		const chatWindow = document.getElementById('multichat-chat-window');
		const toggleBtn = document.getElementById('multichat-toggle-btn');

		if (chatWindow) {
			chatWindow.classList.add('multichat-open');
			toggleBtn?.classList.add('multichat-hidden');
			chatState.isOpen = true;

			// Focus input
			setTimeout(() => {
				document.getElementById('multichat-input')?.focus();
			}, 200);
		}
	}

	/**
	 * Close chat window
	 */
	function closeChat() {
		const chatWindow = document.getElementById('multichat-chat-window');
		const toggleBtn = document.getElementById('multichat-toggle-btn');

		if (chatWindow) {
			chatWindow.classList.remove('multichat-open');
			toggleBtn?.classList.remove('multichat-hidden');
			chatState.isOpen = false;
		}
	}

	/**
	 * Send message to backend
	 * FIXED: Removed nonce requirement, using simple fetch POST
	 */
	async function sendMessage() {
		const input = document.getElementById('multichat-input');
		const message = input?.value?.trim();

		if (!message || chatState.isLoading) {
			return;
		}

		// Add user message to UI
		addMessageToUI(message, 'user');
		input.value = '';

		// Set loading state
		chatState.isLoading = true;
		updateInputState();

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
			addMessageToUI(getTranslation('errorMessage'), 'error');
		} finally {
			chatState.isLoading = false;
			updateInputState();
		}
	}

	/**
	 * Add message to chat UI with optimized DOM operations
	 */
	function addMessageToUI(message, sender = 'user') {
		const messagesContainer = document.getElementById('multichat-messages');
		if (!messagesContainer) return;

		// Use template literals for efficient DOM creation
		const messageHTML = `
			<div class="multichat-message multichat-${sender}">
				<div class="multichat-message-content">${escapeHtml(message)}</div>
			</div>
		`;

		// Insert HTML in one operation
		messagesContainer.insertAdjacentHTML('beforeend', messageHTML);

		// Auto-scroll to bottom with debounce
		requestAnimationFrame(() => {
			messagesContainer.scrollTop = messagesContainer.scrollHeight;
		});

		// Store in chat state
		chatState.messages.push({
			sender,
			message,
			timestamp: new Date().toISOString(),
		});
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Update input state based on loading - optimized
	 */
	function updateInputState() {
		const input = document.getElementById('multichat-input');
		const sendBtn = document.getElementById('multichat-send-btn');

		if (!input || !sendBtn) return;

		// Batch DOM updates
		const isLoading = chatState.isLoading;
		input.disabled = isLoading;
		sendBtn.disabled = isLoading;
		sendBtn.textContent = isLoading ? getTranslation('loadingButton') : getTranslation('sendButton');

		// Add/remove loading class for CSS animations
		sendBtn.classList.toggle('multichat-loading', isLoading);
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

			if (history.length > 0) {
				const welcomeMsg = document.createElement('div');
				welcomeMsg.className = 'multichat-message multichat-info';
				welcomeMsg.textContent = getTranslation('previousChats');

				const messagesContainer = document.getElementById('multichat-messages');
				messagesContainer?.appendChild(welcomeMsg);
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