/**
 * MultiChat GPT Frontend Widget
 * Handles the floating chat interface
 * FIXED: Removed nonce requirement for proper REST API communication
 */

(function () {
	'use strict';

	// Configuration
	const config = {
		restUrl: multiChatGPT?.restUrl || '/wp-json/multichat/v1/ask',
		language: multiChatGPT?.language || 'en',
		position: localStorage.getItem('multichat_position') || 'bottom-right',
	};

	// Chat state
	const chatState = {
		isOpen: false,
		messages: [],
		isLoading: false,
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
	 * Attach event listeners
	 */
	function attachEventListeners() {
		const toggleBtn = document.getElementById('multichat-toggle-btn');
		const closeBtn = document.getElementById('multichat-close-btn');
		const sendBtn = document.getElementById('multichat-send-btn');
		const input = document.getElementById('multichat-input');

		toggleBtn?.addEventListener('click', toggleChat);
		closeBtn?.addEventListener('click', closeChat);
		sendBtn?.addEventListener('click', sendMessage);

		input?.addEventListener('keypress', (e) => {
			if (e.key === 'Enter' && !e.shiftKey && !chatState.isLoading) {
				sendMessage();
			}
		});

		// Enable input after widget loads
		setTimeout(() => {
			if (input) {
				input.disabled = false;
				sendBtn.disabled = false;
			}
		}, 500);
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
	 * Add message to chat UI
	 */
	function addMessageToUI(message, sender = 'user') {
		const messagesContainer = document.getElementById('multichat-messages');
		if (!messagesContainer) return;

		const messageEl = document.createElement('div');
		messageEl.className = `multichat-message multichat-${sender}`;

		const messageContent = document.createElement('div');
		messageContent.className = 'multichat-message-content';
		messageContent.textContent = message;

		messageEl.appendChild(messageContent);
		messagesContainer.appendChild(messageEl);

		// Auto-scroll to bottom
		messagesContainer.scrollTop = messagesContainer.scrollHeight;

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
		const input = document.getElementById('multichat-input');
		const sendBtn = document.getElementById('multichat-send-btn');

		if (input && sendBtn) {
			input.disabled = chatState.isLoading;
			sendBtn.disabled = chatState.isLoading;

			if (chatState.isLoading) {
				sendBtn.textContent = getTranslation('loadingButton');
			} else {
				sendBtn.textContent = getTranslation('sendButton');
			}
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