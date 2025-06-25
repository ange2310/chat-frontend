// chat-client.js - Cliente completo de chat para demo
class ChatClient {
    constructor() {
        this.websocketUrl = 'ws://187.33.158.246/ws';
        this.socket = null;
        this.isConnected = false;
        this.currentRoom = null;
        this.currentPToken = null;
        this.messageQueue = [];
        this.heartbeatInterval = null;
        this.connectionStartTime = null;
        this.messageCount = 0;
    }

    // Conectar al WebSocket con pToken
    connect(ptoken, roomId) {
        console.log('💬 Conectando al chat...', { roomId });
        
        this.currentPToken = ptoken;
        this.currentRoom = roomId;
        this.connectionStartTime = Date.now();
        
        try {
            // En la demo, simularemos la conexión
            this.simulateConnection();
            
            // TODO: Implementar conexión real WebSocket
            // this.socket = new WebSocket(this.websocketUrl);
            // this.setupSocketEvents();
            
        } catch (error) {
            console.error('❌ Error conectando chat:', error);
            this.onConnectionError(error);
        }
    }

    // Simular conexión para demo
    simulateConnection() {
        console.log('🎭 Modo demo: Simulando conexión de chat');
        
        setTimeout(() => {
            this.isConnected = true;
            this.updateConnectionStatus('Conectado');
            
            // Simular mensaje de bienvenida del agente
            setTimeout(() => {
                this.receiveMessage({
                    id: Date.now(),
                    message: '¡Hola! Soy el Dr. García. He recibido tu solicitud de consulta. ¿En qué puedo ayudarte hoy? 👨‍⚕️',
                    sender: 'agent',
                    timestamp: new Date().toISOString(),
                    sender_name: 'Dr. García'
                });
            }, 2000);
            
            // Simular indicador de escritura ocasional
            this.startAgentTypingSimulation();
            
        }, 1000);
    }

    // Configurar eventos del WebSocket (para implementación real)
    setupSocketEvents() {
        if (!this.socket) return;
        
        this.socket.onopen = () => {
            console.log('✅ WebSocket conectado');
            this.isConnected = true;
            this.updateConnectionStatus('Conectado');
            
            // Autenticarse con pToken
            this.authenticate();
            
            // Iniciar heartbeat
            this.startHeartbeat();
        };
        
        this.socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleSocketMessage(data);
            } catch (error) {
                console.error('Error procesando mensaje:', error);
            }
        };
        
        this.socket.onclose = () => {
            console.log('🔌 WebSocket desconectado');
            this.isConnected = false;
            this.updateConnectionStatus('Desconectado');
            this.stopHeartbeat();
        };
        
        this.socket.onerror = (error) => {
            console.error('❌ Error WebSocket:', error);
            this.onConnectionError(error);
        };
    }

    // Autenticarse con el servidor
    authenticate() {
        if (!this.socket || !this.currentPToken) return;
        
        const authMessage = {
            type: 'auth',
            ptoken: this.currentPToken,
            room_id: this.currentRoom
        };
        
        this.sendToSocket(authMessage);
    }

    // Manejar mensajes del WebSocket
    handleSocketMessage(data) {
        console.log('📨 Mensaje recibido:', data);
        
        switch (data.type) {
            case 'message':
                this.receiveMessage(data);
                break;
            case 'typing':
                this.handleTypingIndicator(data);
                break;
            case 'agent_joined':
                this.handleAgentJoined(data);
                break;
            case 'error':
                this.handleError(data);
                break;
            default:
                console.log('Tipo de mensaje no reconocido:', data.type);
        }
    }

    // Enviar mensaje
    sendMessage(message) {
        if (!message || message.trim() === '') return;
        
        const messageData = {
            id: Date.now(),
            message: message.trim(),
            sender: 'user',
            timestamp: new Date().toISOString(),
            room_id: this.currentRoom
        };
        
        // Añadir a la UI inmediatamente
        this.addMessageToUI(messageData);
        this.messageCount++;
        
        // Enviar al servidor
        if (this.isConnected && this.socket) {
            this.sendToSocket({
                type: 'message',
                ...messageData,
                ptoken: this.currentPToken
            });
        } else {
            // En modo demo, simular respuesta
            this.simulateAgentResponse(message);
        }
        
        console.log('📤 Mensaje enviado:', message);
    }

    // Recibir mensaje
    receiveMessage(messageData) {
        this.addMessageToUI(messageData);
        this.messageCount++;
        
        // Reproducir sonido de notificación (opcional)
        this.playNotificationSound();
    }

    // Añadir mensaje a la UI
    addMessageToUI(messageData) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        
        const messageElement = this.createMessageElement(messageData);
        messagesContainer.appendChild(messageElement);
        
        // Scroll hacia abajo
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        // Animación de entrada
        setTimeout(() => {
            messageElement.classList.add('medical-fade-in');
        }, 10);
    }

    // Crear elemento de mensaje
    createMessageElement(messageData) {
        const messageDiv = document.createElement('div');
        const isUser = messageData.sender === 'user';
        const isSystem = messageData.sender === 'system';
        
        messageDiv.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-4`;
        
        const bgClass = isUser ? 'bg-gradient-to-r from-medical-blue to-medical-blue-dark text-white' : 
                       isSystem ? 'bg-gray-100 border border-gray-200 text-gray-700' :
                       'bg-white border border-gray-200 text-gray-900';
        
        const messageTime = new Date(messageData.timestamp).toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        messageDiv.innerHTML = `
            <div class="max-w-xs lg:max-w-md">
                <div class="${bgClass} rounded-2xl px-4 py-3 shadow-sm">
                    ${!isUser && messageData.sender_name ? `
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-900">${messageData.sender_name}</span>
                        </div>
                    ` : ''}
                    <p class="text-sm ${isUser ? 'text-white' : 'text-gray-700'}">${this.formatMessage(messageData.message)}</p>
                    <p class="text-xs ${isUser ? 'text-blue-100' : 'text-gray-500'} mt-2">
                        ${messageTime}
                    </p>
                </div>
            </div>
        `;
        
        return messageDiv;
    }

    // Formatear mensaje (detectar emojis, links, etc.)
    formatMessage(message) {
        // Escapar HTML básico
        let formatted = message
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        
        // Detectar URLs simples
        formatted = formatted.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" class="underline">$1</a>'
        );
        
        // Detectar menciones médicas comunes
        formatted = formatted.replace(
            /\b(dolor|fiebre|tos|gripe|covid|medicina|doctor|enfermera)\b/gi,
            '<span class="font-medium">$1</span>'
        );
        
        return formatted;
    }

    // Simular respuesta del agente para demo
    simulateAgentResponse(userMessage) {
        const responses = [
            "Entiendo tu consulta. Déjame revisar la información que me proporcionas. 🩺",
            "Gracias por esa información. ¿Podrías contarme desde cuándo presentas estos síntomas?",
            "Basado en lo que me describes, te haré algunas preguntas adicionales para poder ayudarte mejor.",
            "Es importante que me proporciones todos los detalles. ¿Has tenido algún síntoma adicional?",
            "Perfecto. Con esta información puedo darte una mejor orientación. ¿Tienes alguna alergia conocida a medicamentos?",
            "Te recomiendo algunas medidas que puedes tomar. ¿Tienes alguna pregunta específica sobre tu situación? 💊",
            "Voy a revisar tu historial médico. Mientras tanto, ¿podrías describir la intensidad del dolor del 1 al 10?",
            "Entiendo tu preocupación. Es normal sentirse así. Te voy a explicar los pasos a seguir. 🩹",
            "Basándome en tus síntomas, esto podría ser común, pero es importante descartar otras causas.",
            "¿Has tomado algún medicamento para esto? Es importante conocer qué has probado antes. 🏥"
        ];
        
        // Respuestas específicas basadas en palabras clave
        const lowerMessage = userMessage.toLowerCase();
        let specificResponse = null;
        
        if (lowerMessage.includes('dolor')) {
            specificResponse = "Entiendo que tienes dolor. ¿Puedes describir la intensidad del 1 al 10 y si es constante o intermitente? 😟";
        } else if (lowerMessage.includes('fiebre')) {
            specificResponse = "La fiebre puede indicar varias cosas. ¿Has medido tu temperatura? ¿Qué otros síntomas tienes? 🤒";
        } else if (lowerMessage.includes('tos')) {
            specificResponse = "La tos puede tener muchas causas. ¿Es seca o con flemas? ¿Desde cuándo la tienes? 😷";
        } else if (lowerMessage.includes('gracias')) {
            specificResponse = "De nada, estoy aquí para ayudarte. ¿Hay algo más que te preocupe? 😊";
        }
        
        // Mostrar indicador de escritura
        this.showTypingIndicator();
        
        // Simular tiempo de respuesta realista
        setTimeout(() => {
            this.hideTypingIndicator();
            
            const responseMessage = specificResponse || responses[Math.floor(Math.random() * responses.length)];
            this.receiveMessage({
                id: Date.now(),
                message: responseMessage,
                sender: 'agent',
                timestamp: new Date().toISOString(),
                sender_name: 'Dr. García'
            });
        }, 2000 + Math.random() * 3000); // 2-5 segundos
    }

    // Mostrar indicador de escritura
    showTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) {
            indicator.classList.remove('hidden');
            
            // Scroll hacia abajo
            const messagesContainer = document.getElementById('chatMessages');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
    }

    // Ocultar indicador de escritura
    hideTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) {
            indicator.classList.add('hidden');
        }
    }

    // Simular actividad del agente
    startAgentTypingSimulation() {
        // Cada 30-60 segundos, mostrar que el agente está escribiendo
        this.agentTypingInterval = setInterval(() => {
            if (Math.random() > 0.7 && this.isConnected) { // 30% de probabilidad
                this.showTypingIndicator();
                setTimeout(() => {
                    this.hideTypingIndicator();
                }, 2000 + Math.random() * 2000);
            }
        }, 30000 + Math.random() * 30000);
    }

    // Actualizar estado de conexión
    updateConnectionStatus(status) {
        const statusElement = document.getElementById('connectionStatus');
        const chatStatus = document.getElementById('chatStatus');
        
        if (statusElement) {
            statusElement.innerHTML = `
                <div class="w-2 h-2 ${this.isConnected ? 'bg-green-400' : 'bg-red-400'} rounded-full mr-1"></div>
                ${status}
            `;
        }
        
        if (chatStatus) {
            chatStatus.textContent = status;
        }
    }

    // Enviar al WebSocket
    sendToSocket(data) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify(data));
        } else {
            console.warn('WebSocket no está conectado');
        }
    }

    // Heartbeat para mantener conexión
    startHeartbeat() {
        this.heartbeatInterval = setInterval(() => {
            if (this.socket && this.socket.readyState === WebSocket.OPEN) {
                this.sendToSocket({ type: 'ping' });
            }
        }, 30000); // Cada 30 segundos
    }

    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
        if (this.agentTypingInterval) {
            clearInterval(this.agentTypingInterval);
            this.agentTypingInterval = null;
        }
    }

    // Manejar errores
    onConnectionError(error) {
        console.error('Error de conexión chat:', error);
        this.updateConnectionStatus('Error de conexión');
        
        // Mostrar mensaje en el chat
        this.receiveMessage({
            id: Date.now(),
            message: 'Hubo un problema con la conexión. Intentando reconectar...',
            sender: 'system',
            timestamp: new Date().toISOString()
        });
    }

    // Desconectar chat
    disconnect() {
        console.log('🔌 Desconectando chat...');
        
        this.isConnected = false;
        this.stopHeartbeat();
        
        if (this.socket) {
            this.socket.close();
            this.socket = null;
        }
        
        this.updateConnectionStatus('Desconectado');
    }

    // Reproducir sonido de notificación
    playNotificationSound() {
        // Solo reproducir si el usuario ha interactuado con la página
        try {
            if (window.audioEnabled) {
                const audio = new Audio();
                audio.volume = 0.1;
                // Sonido simple de notificación usando AudioContext
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.3);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.3);
            }
        } catch (error) {
            // Fallar silenciosamente si no se puede reproducir sonido
        }
    }

    // Habilitar audio después de interacción del usuario
    enableAudio() {
        window.audioEnabled = true;
    }

    // Obtener estadísticas del chat
    getChatStats() {
        return {
            isConnected: this.isConnected,
            currentRoom: this.currentRoom,
            messageCount: this.messageCount,
            connectionTime: this.connectionStartTime ? Date.now() - this.connectionStartTime : 0,
            uptime: this.connectionStartTime ? Math.floor((Date.now() - this.connectionStartTime) / 1000) : 0
        };
    }

    // Manejar agente que se une
    handleAgentJoined(data) {
        this.receiveMessage({
            id: Date.now(),
            message: `${data.agent_name || 'Un profesional'} se ha unido a la conversación. 👨‍⚕️`,
            sender: 'system',
            timestamp: new Date().toISOString()
        });
    }

    // Manejar indicador de escritura
    handleTypingIndicator(data) {
        if (data.is_typing) {
            this.showTypingIndicator();
        } else {
            this.hideTypingIndicator();
        }
    }

    // Manejar errores del servidor
    handleError(data) {
        console.error('Error del servidor:', data);
        this.receiveMessage({
            id: Date.now(),
            message: `Error: ${data.message || 'Algo salió mal'}`,
            sender: 'system',
            timestamp: new Date().toISOString()
        });
    }
}

// Crear instancia global del cliente de chat
window.chatClient = new ChatClient();

// Event listeners para el chat
document.addEventListener('DOMContentLoaded', () => {
    
    // Habilitar audio en primera interacción
    document.addEventListener('click', () => {
        window.chatClient.enableAudio();
    }, { once: true });

    // Auto-resize del textarea
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            
            // Actualizar contador de caracteres
            const charCount = this.value.length;
            const charCountElement = document.getElementById('charCount');
            if (charCountElement) {
                charCountElement.textContent = charCount;
            }
            
            // Habilitar/deshabilitar botón de envío
            const sendButton = document.getElementById('sendButton');
            if (sendButton) {
                sendButton.disabled = charCount === 0;
            }
        });

        // Enter para enviar (Shift+Enter para nueva línea)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const sendButton = document.getElementById('sendButton');
                if (sendButton && !sendButton.disabled) {
                    sendMessage();
                }
            }
        });
    }

    // Botón de emoji
    const emojiButton = document.getElementById('emojiButton');
    if (emojiButton) {
        emojiButton.addEventListener('click', function() {
            const emojiPicker = document.getElementById('emojiPicker');
            if (emojiPicker) {
                emojiPicker.classList.toggle('hidden');
            }
        });
    }

    // Selección de emojis
    document.querySelectorAll('.emoji-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const emoji = this.dataset.emoji;
            const messageInput = document.getElementById('messageInput');
            
            if (messageInput && emoji) {
                const cursorPos = messageInput.selectionStart;
                const textBefore = messageInput.value.substring(0, cursorPos);
                const textAfter = messageInput.value.substring(cursorPos);
                
                messageInput.value = textBefore + emoji + textAfter;
                messageInput.focus();
                messageInput.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
                
                // Disparar evento de input para actualizar contadores
                messageInput.dispatchEvent(new Event('input'));
                
                // Ocultar selector de emojis
                const emojiPicker = document.getElementById('emojiPicker');
                if (emojiPicker) {
                    emojiPicker.classList.add('hidden');
                }
            }
        });
    });

    // Botones de acción rápida
    document.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.value = this.textContent.trim();
                messageInput.focus();
                messageInput.dispatchEvent(new Event('input'));
            }
        });
    });

    // Botón de archivo
    const fileButton = document.getElementById('fileButton');
    const fileInput = document.getElementById('fileInput');
    
    if (fileButton && fileInput) {
        fileButton.addEventListener('click', () => fileInput.click());
        
        fileInput.addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                handleFileUpload(files);
            }
        });
    }

    // Minimizar/maximizar chat
    const minimizeBtn = document.getElementById('minimizeChat');
    if (minimizeBtn) {
        minimizeBtn.addEventListener('click', function() {
            const chatWidget = document.getElementById('chatWidget');
            if (chatWidget) {
                chatWidget.classList.toggle('minimized');
                this.innerHTML = chatWidget.classList.contains('minimized') ?
                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"></path></svg>' :
                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>';
            }
        });
    }

    // Ocultar emoji picker al hacer clic fuera
    document.addEventListener('click', function(e) {
        const emojiPicker = document.getElementById('emojiPicker');
        const emojiButton = document.getElementById('emojiButton');
        
        if (emojiPicker && emojiButton && 
            !emojiPicker.contains(e.target) && 
            !emojiButton.contains(e.target)) {
            emojiPicker.classList.add('hidden');
        }
    });
});

// Función global para enviar mensaje
function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    const message = messageInput.value.trim();
    if (!message) return;
    
    // Enviar mensaje a través del cliente de chat
    window.chatClient.sendMessage(message);
    
    // Limpiar input
    messageInput.value = '';
    messageInput.style.height = 'auto';
    
    // Actualizar UI
    const charCountElement = document.getElementById('charCount');
    if (charCountElement) {
        charCountElement.textContent = '0';
    }
    
    const sendButton = document.getElementById('sendButton');
    if (sendButton) {
        sendButton.disabled = true;
    }
    
    // Focus de vuelta en el input
    messageInput.focus();
}

// Manejar subida de archivos
function handleFileUpload(files) {
    console.log('📎 Subiendo archivos:', files.length);
    
    Array.from(files).forEach(file => {
        // Validar tamaño (5MB máximo)
        if (file.size > 5 * 1024 * 1024) {
            window.authClient?.showError(`El archivo "${file.name}" es muy grande (máximo 5MB)`);
            return;
        }
        
        // Validar tipo
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
        if (!allowedTypes.includes(file.type)) {
            window.authClient?.showError(`Tipo de archivo "${file.type}" no permitido`);
            return;
        }
        
        // Simular subida para demo
        simulateFileUpload(file);
    });
}

// Simular subida de archivo para demo
function simulateFileUpload(file) {
    // Mostrar progreso en el chat
    const progressMessage = {
        id: Date.now(),
        message: `📎 Subiendo archivo: ${file.name} (${formatFileSize(file.size)})`,
        sender: 'system',
        timestamp: new Date().toISOString()
    };
    
    window.chatClient.addMessageToUI(progressMessage);
    
    // Simular tiempo de subida
    setTimeout(() => {
        const successMessage = {
            id: Date.now(),
            message: `✅ Archivo subido exitosamente: ${file.name}`,
            sender: 'system',
            timestamp: new Date().toISOString()
        };
        
        window.chatClient.addMessageToUI(successMessage);
        
        // Simular respuesta del agente
        setTimeout(() => {
            window.chatClient.receiveMessage({
                id: Date.now(),
                message: 'He recibido tu archivo. Lo voy a revisar y te responderé en un momento. 📋',
                sender: 'agent',
                timestamp: new Date().toISOString(),
                sender_name: 'Dr. García'
            });
        }, 1000);
        
    }, 2000 + Math.random() * 3000);
}

// Formatear tamaño de archivo
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Función para finalizar chat
function endChat() {
    if (confirm('¿Estás seguro de que quieres terminar la consulta?')) {
        window.chatClient.disconnect();
        
        // Ocultar widget de chat
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            chatContainer.classList.add('hidden');
        }
        
        // Mostrar mensaje de despedida
        window.authClient?.showSuccess('Consulta finalizada. ¡Que tengas un buen día! 👋');
        
        // Resetear UI
        setTimeout(() => {
            const roomsContainer = document.getElementById('roomsContainer');
            if (roomsContainer) {
                roomsContainer.classList.add('hidden');
            }
        }, 2000);
        
        console.log('👋 Chat finalizado por el usuario');
    }
}

// Funciones de utilidad para el chat
window.chatUtils = {
    // Obtener estadísticas del chat
    getStats: () => window.chatClient.getChatStats(),
    
    // Simular conexión/desconexión para demo
    toggleConnection: () => {
        if (window.chatClient.isConnected) {
            window.chatClient.disconnect();
        } else {
            window.chatClient.simulateConnection();
        }
    },
    
    // Limpiar historial de chat
    clearHistory: () => {
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer && confirm('¿Limpiar historial de chat?')) {
            // Mantener solo mensaje de bienvenida
            const welcomeMsg = messagesContainer.querySelector('.flex');
            messagesContainer.innerHTML = '';
            if (welcomeMsg) {
                messagesContainer.appendChild(welcomeMsg);
            }
        }
    },
    
    // Simular mensaje del agente para demo
    simulateAgentMessage: (message) => {
        window.chatClient.receiveMessage({
            id: Date.now(),
            message: message || '¡Hola! Este es un mensaje de prueba del agente. 👨‍⚕️',
            sender: 'agent',
            timestamp: new Date().toISOString(),
            sender_name: 'Dr. García'
        });
    },
    
    // Enviar mensaje predefinido
    sendQuickMessage: (type) => {
        const messages = {
            help: '¿Podrían ayudarme con información sobre mi consulta?',
            symptoms: 'Tengo algunos síntomas que me gustaría consultar 🤒',
            medication: '¿Podrían revisar mi medicación actual? 💊',
            followup: 'Me gustaría hacer seguimiento de mi consulta anterior',
            emergency: '🚨 Necesito atención urgente por favor',
            thanks: 'Muchas gracias por su atención y profesionalismo 🙏'
        };
        
        const message = messages[type] || messages.help;
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.value = message;
            messageInput.dispatchEvent(new Event('input'));
            messageInput.focus();
        }
    },
    
    // Mostrar estadísticas en consola
    showStats: () => {
        const stats = window.chatClient.getChatStats();
        console.table(stats);
        return stats;
    }
};

// Función para inicializar timer del chat
function startChatTimer() {
    const timerElement = document.getElementById('chatTimer');
    if (!timerElement) return;
    
    let seconds = 0;
    const timer = setInterval(() => {
        seconds++;
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
    }, 1000);
    
    // Guardar referencia del timer para poder limpiarlo
    window.chatTimer = timer;
}

// Función para detener timer del chat
function stopChatTimer() {
    if (window.chatTimer) {
        clearInterval(window.chatTimer);
        window.chatTimer = null;
    }
}

// Funciones adicionales para mejorar la experiencia
window.chatActions = {
    // Cambiar tema del chat
    toggleTheme: () => {
        const chatWidget = document.getElementById('chatWidget');
        if (chatWidget) {
            chatWidget.classList.toggle('dark-theme');
        }
    },
    
    // Aumentar/disminuir tamaño de fuente
    changeFontSize: (size) => {
        const messages = document.querySelectorAll('#chatMessages .text-sm');
        messages.forEach(msg => {
            msg.style.fontSize = size + 'rem';
        });
    },
    
    // Exportar historial de chat
    exportChat: () => {
        const messages = Array.from(document.querySelectorAll('#chatMessages > div')).map(msgDiv => {
            const content = msgDiv.querySelector('p');
            const time = msgDiv.querySelector('.text-xs');
            const sender = msgDiv.classList.contains('justify-end') ? 'Usuario' : 'Agente';
            
            return {
                sender,
                message: content?.textContent || '',
                time: time?.textContent || ''
            };
        });
        
        const chatData = {
            session_id: window.chatClient.currentRoom,
            date: new Date().toISOString(),
            messages: messages,
            stats: window.chatClient.getChatStats()
        };
        
        const blob = new Blob([JSON.stringify(chatData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `chat-${new Date().toISOString().split('T')[0]}.json`;
        a.click();
        URL.revokeObjectURL(url);
        
        console.log('📄 Chat exportado:', chatData);
    },
    
    // Activar/desactivar sonidos
    toggleSound: () => {
        window.audioEnabled = !window.audioEnabled;
        const status = window.audioEnabled ? 'activados' : 'desactivados';
        window.authClient?.showSuccess(`Sonidos ${status}`);
        return window.audioEnabled;
    }
};

// Debugging y testing para desarrollo
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    window.chatDebug = {
        // Simular diferentes tipos de mensajes
        testMessages: () => {
            const testMsgs = [
                { msg: 'Mensaje de prueba del usuario', sender: 'user' },
                { msg: '¡Hola! Soy el Dr. García, ¿en qué puedo ayudarte? 👨‍⚕️', sender: 'agent', name: 'Dr. García' },
                { msg: 'Sistema: Conexión establecida', sender: 'system' },
                { msg: 'Tengo dolor de cabeza desde ayer 🤕', sender: 'user' },
                { msg: 'Entiendo tu molestia. ¿Has tomado algún medicamento? 💊', sender: 'agent', name: 'Dr. García' }
            ];
            
            testMsgs.forEach((test, index) => {
                setTimeout(() => {
                    window.chatClient.receiveMessage({
                        id: Date.now() + index,
                        message: test.msg,
                        sender: test.sender,
                        timestamp: new Date().toISOString(),
                        sender_name: test.name
                    });
                }, index * 1000);
            });
        },
        
        // Simular indicador de escritura
        testTyping: () => {
            window.chatClient.showTypingIndicator();
            setTimeout(() => {
                window.chatClient.hideTypingIndicator();
            }, 3000);
        },
        
        // Simular error de conexión
        testError: () => {
            window.chatClient.onConnectionError(new Error('Error de prueba'));
        },
        
        // Limpiar todo el chat
        clearAll: () => {
            const messagesContainer = document.getElementById('chatMessages');
            if (messagesContainer) {
                messagesContainer.innerHTML = '';
                window.chatClient.messageCount = 0;
            }
        },
        
        // Mostrar todas las utilidades disponibles
        help: () => {
            console.log(`
🛠️ CHAT DEBUG UTILITIES:
═══════════════════════

• window.chatDebug.testMessages() - Simular conversación
• window.chatDebug.testTyping() - Probar indicador de escritura
• window.chatDebug.testError() - Simular error de conexión
• window.chatDebug.clearAll() - Limpiar todo el chat

• window.chatUtils.getStats() - Ver estadísticas
• window.chatUtils.simulateAgentMessage(msg) - Mensaje del agente
• window.chatUtils.sendQuickMessage(type) - Mensaje rápido

• window.chatActions.exportChat() - Exportar historial
• window.chatActions.toggleSound() - Activar/desactivar sonidos
• window.chatActions.changeFontSize(1.2) - Cambiar tamaño texto

Tipos de mensajes rápidos: help, symptoms, medication, followup, emergency, thanks
            `);
        }
    };
    
    console.log('🛠️ Chat debug mode activado. Usa window.chatDebug.help() para ver opciones');
}

console.log('💬 Chat client completo cargado');
console.log('📋 Controles disponibles:');
console.log('   • window.chatUtils - Utilidades del chat');
console.log('   • window.chatActions - Acciones adicionales');
if (window.chatDebug) {
    console.log('   • window.chatDebug - Herramientas de desarrollo');
}