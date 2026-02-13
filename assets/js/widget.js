/**
 * MultiChat GPT Frontend Widget
 * Handles the floating chat interface
 * FIXED: Removed nonce requirement for proper REST API communication
 * FIXED: Widget position now comes from admin settings
 * FIXED: Handles rate-limit (429) responses gracefully
 * FIXED: Removed previous chat history feature
 * FIXED: Mobile responsiveness — chat window stays on screen
 */

(function () {
	'use strict';

	// Configuration
	const config = {
		restUrl: multiChatGPT?.restUrl || '/wp-json/multichat/v1/ask',
		language: multiChatGPT?.language || 'en',
		// ── CHANGED: use admin setting passed via wp_localize_script ──
		position: multiChatGPT?.position || 'bottom-right',
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
						maxlength="500"
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
	 * FIXED: Handles 429 rate-limit responses
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

			// ── NEW: Handle rate-limit (429) response ──
			if (response.status === 429) {
				addMessageToUI(
					data.message || getTranslation('rateLimitMessage'),
					'error'
				);
			} else if (data.success) {
				addMessageToUI(data.message, 'assistant');
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

		// Store in chat state (session only, not localStorage)
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
				rateLimitMessage:
					'Too many requests. Please wait a moment and try again.',
			},
			ar: {
				chatTitle: 'دعم الدردشة',
				inputPlaceholder: 'اسأل أي شيء...',
				sendButton: 'إرسال',
				loadingButton: 'جاري الإرسال...',
				errorMessage: 'عذرًا، حدث خطأ. يرجى المحاولة لاحقًا.',
				rateLimitMessage:
					'طلبات كثيرة جدًا. يرجى الانتظار لحظة والمحاولة مرة أخرى.',
			},
			es: {
				chatTitle: 'Soporte de Chat',
				inputPlaceholder: 'Pregúntame lo que sea...',
				sendButton: 'Enviar',
				loadingButton: 'Enviando...',
				errorMessage: 'Lo sentimos, ocurrió un error. Intente más tarde.',
				rateLimitMessage:
					'Demasiadas solicitudes. Espere un momento e intente de nuevo.',
			},
			fr: {
				chatTitle: 'Support de Chat',
				inputPlaceholder: 'Demandez-moi n\'importe quoi...',
				sendButton: 'Envoyer',
				loadingButton: 'Envoi en cours...',
				errorMessage: 'Désolé, une erreur s\'est produite. Veuillez réessayer plus tard.',
				rateLimitMessage:
					'Trop de requêtes. Veuillez patienter un moment et réessayer.',
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