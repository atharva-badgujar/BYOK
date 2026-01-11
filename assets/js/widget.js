/**
 * SmartBot Widget - Frontend JavaScript
 * Handles chat widget interaction and API communication
 */

(function() {
    'use strict';
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        initWidget();
    }
    
    /**
     * Initialize widget
     */
    function initWidget() {
        const fab = document.getElementById('smartbot-fab');
        const chat = document.getElementById('smartbot-chat');
        const closeBtn = document.getElementById('smartbot-close');
        const input = document.getElementById('smartbot-input');
        const sendBtn = document.getElementById('smartbot-send');
        const messagesContainer = document.getElementById('smartbot-messages');
        
        if (!fab || !chat || !closeBtn || !input || !sendBtn || !messagesContainer) {
            console.error('SmartBot: Required elements not found');
            return;
        }
        
        // Event listeners
        fab.addEventListener('click', openChat);
        closeBtn.addEventListener('click', closeChat);
        sendBtn.addEventListener('click', sendMessage);
        
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Prevent form submission on Enter
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
            }
        });
        
        // Show welcome message
        addBotMessage('Hi! How can I help you today?');
    }
    
    /**
     * Open chat window
     */
    function openChat() {
        const chat = document.getElementById('smartbot-chat');
        const fab = document.getElementById('smartbot-fab');
        
        chat.classList.remove('smartbot-hidden');
        fab.classList.add('smartbot-hidden');
        
        // Focus input after animation
        setTimeout(() => {
            const input = document.getElementById('smartbot-input');
            if (input) {
                input.focus();
            }
        }, 100);
    }
    
    /**
     * Close chat window
     */
    function closeChat() {
        const chat = document.getElementById('smartbot-chat');
        const fab = document.getElementById('smartbot-fab');
        
        chat.classList.add('smartbot-hidden');
        fab.classList.remove('smartbot-hidden');
    }
    
    /**
     * Send user message
     */
    function sendMessage() {
        const input = document.getElementById('smartbot-input');
        const message = input.value.trim();
        
        if (!message) {
            return;
        }
        
        // Add user message to chat
        addUserMessage(message);
        
        // Clear input
        input.value = '';
        
        // Show typing indicator
        showTypingIndicator();
        
        // Send to API
        fetch(smartbotConfig.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': smartbotConfig.nonce
            },
            body: JSON.stringify({ message: message })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Network response was not ok');
                });
            }
            return response.json();
        })
        .then(data => {
            hideTypingIndicator();
            
            if (data.success && data.message) {
                addBotMessage(data.message);
            } else {
                addBotMessage('Sorry, I encountered an error. Please try again.');
            }
        })
        .catch(error => {
            hideTypingIndicator();
            console.error('SmartBot API error:', error);
            
            let errorMessage = 'Sorry, I\'m having trouble connecting. ';
            
            if (error.message.includes('API key')) {
                errorMessage += 'Please make sure the API key is configured correctly in the admin panel.';
            } else if (error.message.includes('rate limit')) {
                errorMessage += 'Too many requests. Please wait a moment and try again.';
            } else {
                errorMessage += 'Please try again later.';
            }
            
            addBotMessage(errorMessage);
        });
    }
    
    /**
     * Add user message to chat
     */
    function addUserMessage(message) {
        const messagesContainer = document.getElementById('smartbot-messages');
        const messageEl = document.createElement('div');
        messageEl.className = 'smartbot-message smartbot-message-user';
        
        const bubble = document.createElement('div');
        bubble.className = 'smartbot-bubble smartbot-bubble-user';
        bubble.style.backgroundColor = smartbotConfig.brandColor;
        bubble.textContent = message;
        
        messageEl.appendChild(bubble);
        messagesContainer.appendChild(messageEl);
        
        scrollToBottom();
    }
    
    /**
     * Add bot message to chat
     */
    function addBotMessage(message) {
        const messagesContainer = document.getElementById('smartbot-messages');
        const messageEl = document.createElement('div');
        messageEl.className = 'smartbot-message smartbot-message-bot';
        
        // Add avatar if configured
        if (smartbotConfig.avatar) {
            const avatar = document.createElement('img');
            avatar.className = 'smartbot-avatar';
            avatar.src = smartbotConfig.avatar;
            avatar.alt = smartbotConfig.botName;
            avatar.onerror = function() {
                this.style.display = 'none';
            };
            messageEl.appendChild(avatar);
        }
        
        const bubble = document.createElement('div');
        bubble.className = 'smartbot-bubble smartbot-bubble-bot';
        bubble.textContent = message;
        
        messageEl.appendChild(bubble);
        messagesContainer.appendChild(messageEl);
        
        scrollToBottom();
    }
    
    /**
     * Show typing indicator
     */
    function showTypingIndicator() {
        const messagesContainer = document.getElementById('smartbot-messages');
        
        // Remove any existing typing indicator
        const existingIndicator = document.getElementById('smartbot-typing');
        if (existingIndicator) {
            existingIndicator.remove();
        }
        
        const indicator = document.createElement('div');
        indicator.id = 'smartbot-typing';
        indicator.className = 'smartbot-message smartbot-message-bot';
        
        const bubble = document.createElement('div');
        bubble.className = 'smartbot-bubble smartbot-bubble-bot smartbot-typing-indicator';
        
        // Create three animated dots
        for (let i = 0; i < 3; i++) {
            const dot = document.createElement('span');
            dot.className = 'smartbot-typing-dot';
            bubble.appendChild(dot);
        }
        
        indicator.appendChild(bubble);
        messagesContainer.appendChild(indicator);
        
        scrollToBottom();
    }
    
    /**
     * Hide typing indicator
     */
    function hideTypingIndicator() {
        const indicator = document.getElementById('smartbot-typing');
        if (indicator) {
            indicator.remove();
        }
    }
    
    /**
     * Scroll messages to bottom
     */
    function scrollToBottom() {
        const messagesContainer = document.getElementById('smartbot-messages');
        if (messagesContainer) {
            setTimeout(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 100);
        }
    }
    
})();
