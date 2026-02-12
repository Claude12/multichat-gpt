/**
 * MultiChat GPT Frontend Widget
 * Handles the floating chat interface
 * Optimized with debouncing and event delegation
 */

(function () {
	'use strict';

	// Configuration
	const config = {
		restUrl: multiChatGPT?.restUrl || '/wp-json/multichat/v1/ask',
		language: multiChatGPT?.language || 'en',
		position: multiChatGPT?.position || localStorage.getItem('multichat_position') || 'bottom-right',
		debounceDelay: 300,
	};

	// Chat state
	const chatState = {
		isOpen: false,
		messages: [],
		isLoading: false,
	};

	// DOM element references
	let elements = {};

	/**
	 * Initialize the widget
	 */
	function init() {
		createWidgetHTML();
		cacheElements();
		attachEventListeners();
		loadChatHistory();
	}

	/**
	 * Cache DOM elements for better performance
	 */
	function cacheElements() {
		elements = {
			container: document.getElementById('multichat-gpt-widget'),
			chatWindow: document.getElementById('multichat-chat-window'),
			toggleBtn: document.getElementById('multichat-toggle-btn'),
			closeBtn: document.getElementById('multichat-close-btn'),
			sendBtn: document.getElementById('multichat-send-btn'),
			input: document.getElementById('multichat-input'),
			messages: document.getElementById('multichat-messages'),
		};
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
					<button id="multichat-close-btn" class="multichat-close-btn" aria-label="${getTranslation('closeAria')}">
						<span>×</span>
					</button>
				</div>
				<div class="multichat-messages" id="multichat-messages" role="log" aria-live="polite"></div>
				<div class="multichat-input-area">
					<input
						type="text"
						id="multichat-input"
						class="multichat-input"
						placeholder="${getTranslation('inputPlaceholder')}"
						aria-label="${getTranslation('inputAria')}"
						disabled
					/>
					<button
						id="multichat-send-btn"
						class="multichat-send-btn"
						aria-label="${getTranslation('sendAria')}"
						disabled
					>
						${getTranslation('sendButton')}
					</button>
				</div>
			</div>

			<button id="multichat-toggle-btn" class="multichat-toggle-btn" aria-label="${getTranslation('openAria')}">
				<span class="multichat-icon" aria-hidden="true">💬</span>
			</button>
		`;

		document.body.appendChild(container);
	}

	/**
	 * Attach event listeners with delegation
	 */
	function attachEventListeners() {
		// Use event delegation on container
		if (elements.container) {
			elements.container.addEventListener('click', handleClick);
			elements.container.addEventListener('keypress', handleKeyPress);
		}

		// Enable input after widget loads
		setTimeout(() => {
			if (elements.input && elements.sendBtn) {
				elements.input.disabled = false;
				elements.sendBtn.disabled = false;
			}
		}, 500);
	}

	/**
	 * Handle click events with delegation
	 */
	function handleClick(e) {
		const target = e.target.closest('button');
		if (!target) return;

		const id = target.id;
		if (id === 'multichat-toggle-btn') {
			toggleChat();
		} else if (id === 'multichat-close-btn') {
			closeChat();
		} else if (id === 'multichat-send-btn' && !chatState.isLoading) {
			sendMessage();
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
		if (elements.chatWindow && elements.toggleBtn) {
			elements.chatWindow.classList.add('multichat-open');
			elements.toggleBtn.classList.add('multichat-hidden');
			elements.toggleBtn.setAttribute('aria-label', getTranslation('closeAria'));
			chatState.isOpen = true;

			// Focus input for accessibility
			setTimeout(() => {
				elements.input?.focus();
			}, 200);
		}
	}

	/**
	 * Close chat window
	 */
	function closeChat() {
		if (elements.chatWindow && elements.toggleBtn) {
			elements.chatWindow.classList.remove('multichat-open');
			elements.toggleBtn.classList.remove('multichat-hidden');
			elements.toggleBtn.setAttribute('aria-label', getTranslation('openAria'));
			chatState.isOpen = false;
		}
	}

	/**
	 * Debounce function
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
	 * Send message to backend
	 */
	async function sendMessage() {
		const message = elements.input?.value?.trim();

		if (!message || chatState.isLoading) {
			return;
		}

		// Add user message to UI
		addMessageToUI(message, 'user');
		elements.input.value = '';

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
		if (!elements.messages) return;

		const messageEl = document.createElement('div');
		messageEl.className = `multichat-message multichat-${sender}`;
		messageEl.setAttribute('role', sender === 'user' ? 'note' : 'article');

		const messageContent = document.createElement('div');
		messageContent.className = 'multichat-message-content';
		messageContent.textContent = message;

		messageEl.appendChild(messageContent);
		elements.messages.appendChild(messageEl);

		// Auto-scroll to bottom with smooth behavior
		requestAnimationFrame(() => {
			if (elements.messages) {
				elements.messages.scrollTop = elements.messages.scrollHeight;
			}
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
		if (elements.input && elements.sendBtn) {
			elements.input.disabled = chatState.isLoading;
			elements.sendBtn.disabled = chatState.isLoading;
			elements.input.setAttribute('aria-busy', chatState.isLoading);

			if (chatState.isLoading) {
				elements.sendBtn.textContent = getTranslation('loadingButton');
			} else {
				elements.sendBtn.textContent = getTranslation('sendButton');
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

			if (history.length > 0 && elements.messages) {
				const welcomeMsg = document.createElement('div');
				welcomeMsg.className = 'multichat-message multichat-info';
				welcomeMsg.setAttribute('role', 'status');
				welcomeMsg.textContent = getTranslation('previousChats');

				elements.messages.appendChild(welcomeMsg);
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
				errorMessage: 'Sorry, an error occurred. Please try again later.',
				previousChats: 'Previous chat history loaded.',
				openAria: 'Open chat',
				closeAria: 'Close chat',
				inputAria: 'Type your message',
				sendAria: 'Send message',
			},
			ar: {
				chatTitle: 'دعم الدردشة',
				inputPlaceholder: 'اسأل أي شيء...',
				sendButton: 'إرسال',
				loadingButton: 'جاري الإرسال...',
				errorMessage: 'عذرًا، حدث خطأ. يرجى المحاولة لاحقًا.',
				previousChats: 'تم تحميل سجل الدردشة السابق.',
				openAria: 'فتح الدردشة',
				closeAria: 'إغلاق الدردشة',
				inputAria: 'اكتب رسالتك',
				sendAria: 'إرسال الرسالة',
			},
			es: {
				chatTitle: 'Soporte de Chat',
				inputPlaceholder: 'Pregúntame lo que sea...',
				sendButton: 'Enviar',
				loadingButton: 'Enviando...',
				errorMessage: 'Lo sentimos, ocurrió un error. Intente más tarde.',
				previousChats: 'Se cargó el historial de chat anterior.',
				openAria: 'Abrir chat',
				closeAria: 'Cerrar chat',
				inputAria: 'Escribe tu mensaje',
				sendAria: 'Enviar mensaje',
			},
			fr: {
				chatTitle: 'Support de Chat',
				inputPlaceholder: 'Demandez-moi n\'importe quoi...',
				sendButton: 'Envoyer',
				loadingButton: 'Envoi en cours...',
				errorMessage: 'Désolé, une erreur s\'est produite. Veuillez réessayer plus tard.',
				previousChats: 'L\'historique du chat précédent a été chargé.',
				openAria: 'Ouvrir le chat',
				closeAria: 'Fermer le chat',
				inputAria: 'Tapez votre message',
				sendAria: 'Envoyer le message',
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