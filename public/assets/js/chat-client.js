// public/assets/js/chat-client.js - CORREGIDO PARA BACKEND REAL
class ChatClient {
    constructor() {
        this.chatServiceUrl = 'http://187.33.158.246:8080/chat';
        this.websocketUrl = 'ws://187.33.158.246:8080';
        this.fileServiceUrl = 'http://187.33.158.246:8080/files';
        
        this.socket = null;
        this.isConnected = false;
        this.isAuthenticated = false;
        this.currentRoom = null;
        this.currentSessionId = null;
        this.currentChatId = null;
        this.currentUserId = null;
        this.currentPToken = null;
        this.messageQueue = [];
        this.heartbeatInterval = null;
        
        console.log('💬 ChatClient inicializado');
        console.log('🌐 Chat Service:', this.chatServiceUrl);
        console.log('🔌 WebSocket:', this.websocketUrl);
    }

    // ====== CONECTAR AL CHAT - FLUJO COMPLETO ======
    async connect(ptoken, roomId) {
        console.log('💬 === INICIANDO CONEXIÓN AL CHAT ===', { roomId });
        
        this.currentPToken = ptoken;
        this.currentRoom = roomId;
        
        try {
            // Paso 1: Verificar salud del servicio
            console.log('🔍 Paso 1: Verificando salud del servicio...');
            await this.checkChatServiceHealth();
            
            // Paso 2: Crear sesión de chat
            console.log('🔍 Paso 2: Creando sesión de chat...');
            const sessionData = await this.joinChatSession(ptoken, roomId);
            
            // Guardar datos de la sesión
            this.currentSessionId = sessionData.session_id;
            this.currentChatId = sessionData.chat_id;
            this.currentUserId = sessionData.user_id;
            
            console.log('✅ Sesión creada exitosamente:', {
                session_id: this.currentSessionId,
                chat_id: this.currentChatId,
                user_id: this.currentUserId,
                status: sessionData.status,
                queue_position: sessionData.queue_position
            });
            
            // Paso 3: Conectar WebSocket
            console.log('🔍 Paso 3: Conectando WebSocket...');
            await this.connectWebSocket(ptoken);
            
            // Paso 4: Unirse a la sala
            console.log('🔍 Paso 4: Uniéndose a la sala...');
            await this.joinRoom(roomId, this.currentSessionId);
            
            console.log('✅ Chat conectado exitosamente');
            
            // Retornar datos completos para el frontend
            return {
                session_id: this.currentSessionId,
                chat_id: this.currentChatId,
                user_id: this.currentUserId,
                status: sessionData.status,
                queue_position: sessionData.queue_position,
                websocket_url: sessionData.websocket_url || this.websocketUrl
            };
            
        } catch (error) {
            console.error('❌ Error conectando chat:', error);
            this.onConnectionError(error);
            throw error; // Re-throw para que el frontend lo maneje
        }
    }

    // ====== VERIFICAR SALUD DEL SERVICIO ======
    async checkChatServiceHealth() {
        try {
            // El backend expone /health en la raíz
            const healthUrl = this.chatServiceUrl.replace('/chat', '') + '/health';
            console.log('🏥 Verificando salud del chat service:', healthUrl);
            
            const response = await fetch(healthUrl, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                signal: AbortSignal.timeout(5000) // 5 segundos timeout
            });
            
            if (response.ok) {
                const health = await response.json();
                console.log('✅ Chat service saludable:', health);
                
                // El backend retorna { status: 'OK', service: 'chat-service', ... }
                if (health.status === 'OK') {
                    return true;
                } else {
                    throw new Error(`Servicio reporta estado: ${health.status}`);
                }
            } else {
                throw new Error(`Health check falló (${response.status})`);
            }
        } catch (error) {
            console.error('❌ Chat service no disponible:', error);
            
            if (error.name === 'TimeoutError') {
                throw new Error('El servicio de chat no responde (timeout)');
            } else if (error.message.includes('fetch')) {
                throw new Error('No se puede conectar al servicio de chat');
            } else {
                throw new Error('El servicio de chat no está disponible: ' + error.message);
            }
        }
    }

    // ====== CREAR SESIÓN DE CHAT - FORMATO BACKEND ======
    async joinChatSession(ptoken, roomId) {
        try {
            console.log('📡 Creando sesión de chat...', { 
                roomId, 
                ptoken: ptoken.substring(0, 10) + '...',
                chatServiceUrl: this.chatServiceUrl 
            });
            
            // Payload EXACTO que espera el backend
            const payload = {
                room_id: roomId,        // ✅ Backend espera 'room_id'
                ptoken: ptoken,         // ✅ Backend espera 'ptoken'
                user_data: {            // ✅ Backend espera 'user_data' como object
                    source: 'patient_portal',
                    browser: navigator.userAgent.substring(0, 100),
                    timestamp: new Date().toISOString(),
                    room_selected: roomId,
                    frontend_version: '2.0'
                }
            };
            
            console.log('📤 Enviando payload EXACTO para backend:', payload);
            
            const response = await fetch(`${this.chatServiceUrl}/join`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'User-Agent': 'PatientPortal/2.0'
                },
                body: JSON.stringify(payload)
            });

            console.log('📡 Response status:', response.status, response.statusText);
            
            // Manejar respuesta según el formato del backend
            if (!response.ok) {
                let errorDetails = { status: response.status, statusText: response.statusText };
                
                try {
                    const errorData = await response.json();
                    errorDetails.data = errorData;
                    
                    // El backend retorna { success: false, message: "..." } para errores
                    const errorMessage = errorData.message || errorData.error || `Error HTTP ${response.status}`;
                    console.log('📋 Backend error response:', errorData);
                    
                    throw new Error(errorMessage);
                    
                } catch (parseError) {
                    console.log('📋 Error parsing JSON, usando response text');
                    const errorText = await response.text();
                    errorDetails.text = errorText;
                    
                    throw new Error(`Error del servidor (${response.status}): ${errorText || response.statusText}`);
                }
            }
            
            const result = await response.json();
            console.log('📋 Backend response completa:', result);

            // El backend SIEMPRE retorna { success: boolean, data: {...} }
            if (result.success && result.data) {
                this.updateConnectionStatus('Sesión creada exitosamente');
                
                console.log('✅ Sesión creada con datos:', {
                    session_id: result.data.session_id,
                    chat_id: result.data.chat_id,
                    status: result.data.status,
                    queue_position: result.data.queue_position
                });
                
                return result.data;
            } else {
                throw new Error(result.message || 'Backend no retornó datos de sesión válidos');
            }

        } catch (error) {
            console.error('❌ Error en joinChatSession:', error);
            
            // Mejorar el mensaje de error según el tipo
            let userFriendlyMessage = 'Error conectando al chat';
            
            if (error.message.includes('fetch')) {
                userFriendlyMessage = 'Error de conexión con el servidor de chat';
            } else if (error.message.includes('HTTP 500')) {
                userFriendlyMessage = 'Error interno del servidor. El servicio puede estar iniciándose.';
            } else if (error.message.includes('pToken')) {
                userFriendlyMessage = 'Token de acceso inválido o expirado';
            } else if (error.message.includes('room')) {
                userFriendlyMessage = 'Sala de chat no disponible';
            }
            
            throw new Error(userFriendlyMessage);
        }
    }

    // ====== CONECTAR WEBSOCKET ======
    async connectWebSocket(ptoken) {
        return new Promise((resolve, reject) => {
            try {
                console.log('🔌 Conectando WebSocket...', this.websocketUrl);
                
                this.socket = new WebSocket(this.websocketUrl);
                
                this.socket.onopen = () => {
                    console.log('✅ WebSocket conectado');
                    this.isConnected = true;
                    this.updateConnectionStatus('WebSocket conectado');
                    
                    // Autenticar inmediatamente
                    this.authenticateSocket(ptoken);
                    resolve();
                };
                
                this.socket.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        this.handleSocketMessage(data);
                    } catch (error) {
                        console.error('Error parsing message:', error, event.data);
                    }
                };
                
                this.socket.onclose = (event) => {
                    console.log('🔌 WebSocket cerrado:', event.code, event.reason);
                    this.isConnected = false;
                    this.isAuthenticated = false;
                    this.updateConnectionStatus('Desconectado');
                    this.stopHeartbeat();
                    
                    // Intentar reconexión automática si no fue intencional
                    if (event.code !== 1000 && this.currentPToken) {
                        console.log('🔄 Intentando reconectar en 5 segundos...');
                        setTimeout(() => {
                            if (!this.isConnected) {
                                this.connectWebSocket(this.currentPToken).catch(console.error);
                            }
                        }, 5000);
                    }
                };
                
                this.socket.onerror = (error) => {
                    console.error('❌ WebSocket error:', error);
                    this.updateConnectionStatus('Error de conexión WebSocket');
                    reject(new Error('Error conectando WebSocket'));
                };
                
                // Timeout de conexión más largo
                setTimeout(() => {
                    if (!this.isConnected) {
                        console.error('⏰ Timeout conectando WebSocket');
                        reject(new Error('Timeout conectando WebSocket'));
                    }
                }, 15000);
                
            } catch (error) {
                console.error('❌ Error creating WebSocket:', error);
                reject(error);
            }
        });
    }

    // ====== AUTENTICAR SOCKET ======
    authenticateSocket(ptoken) {
        if (!this.socket || this.socket.readyState !== WebSocket.OPEN) {
            console.warn('⚠️ Socket no está listo para autenticación');
            return;
        }
        
        console.log('🔐 Autenticando socket...');
        
        this.sendToSocket('authenticate', {
            ptoken: ptoken
        });
    }

    // ====== UNIRSE A SALA ======
    async joinRoom(roomId, sessionId) {
        if (!this.isAuthenticated) {
            console.warn('⚠️ No autenticado, esperando...');
            // Esperar autenticación
            setTimeout(() => this.joinRoom(roomId, sessionId), 1000);
            return;
        }
        
        console.log('🏠 Uniéndose a sala:', roomId);
        
        this.sendToSocket('join_room', {
            room_id: roomId,
            session_id: sessionId
        });
    }

    // ====== MANEJAR MENSAJES DEL SOCKET ======
    handleSocketMessage(data) {
        console.log('📨 Socket message:', data);
        
        switch (data.type || data.event) {
            case 'authenticated':
                this.handleAuthenticated(data);
                break;
            case 'auth_error':
                this.handleAuthError(data);
                break;
            case 'room_joined':
                this.handleRoomJoined(data);
                break;
            case 'room_error':
                this.handleRoomError(data);
                break;
            case 'message_received':
                this.handleMessageReceived(data);
                break;
            case 'message_sent':
                this.handleMessageSent(data);
                break;
            case 'message_error':
                this.handleMessageError(data);
                break;
            case 'user_typing':
                this.handleUserTyping(data);
                break;
            case 'user_stop_typing':
                this.handleUserStopTyping(data);
                break;
            case 'file_uploaded':
                this.handleFileUploaded(data);
                break;
            case 'queue_position':
                this.handleQueuePosition(data);
                break;
            case 'heartbeat':
                this.handleHeartbeat(data);
                break;
            default:
                console.log('Mensaje no manejado:', data.type, data);
        }
    }

    // ====== HANDLERS DE EVENTOS ======
    handleAuthenticated(data) {
        console.log('✅ Socket autenticado:', data);
        this.isAuthenticated = true;
        this.updateConnectionStatus('Autenticado');
    }

    handleAuthError(data) {
        console.error('❌ Error autenticación socket:', data);
        this.updateConnectionStatus('Error de autenticación');
    }

    handleRoomJoined(data) {
        console.log('✅ Sala unida:', data);
        this.updateConnectionStatus('En sala');
        
        // Mostrar info de cola si está esperando
        if (data.queue_info) {
            this.showQueueInfo(data.queue_info);
        }
        
        // Cargar historial de mensajes
        this.loadMessageHistory();
        
        // Iniciar heartbeat
        this.startHeartbeat();
    }

    handleRoomError(data) {
        console.error('❌ Error sala:', data);
        this.updateConnectionStatus('Error en sala');
    }

    handleMessageReceived(data) {
        console.log('📨 Mensaje recibido:', data);
        this.addMessageToChat(data.content, data.sender_type, data.sender_id, data.timestamp);
        this.playNotificationSound();
    }

    handleMessageSent(data) {
        console.log('✅ Mensaje enviado:', data);
        // El mensaje ya está en la UI, solo confirmamos
    }

    handleMessageError(data) {
        console.error('❌ Error mensaje:', data);
        this.showError('Error enviando mensaje: ' + data.error);
    }

    handleUserTyping(data) {
        this.showTypingIndicator(data.user_id);
    }

    handleUserStopTyping(data) {
        this.hideTypingIndicator();
    }

    handleFileUploaded(data) {
        console.log('📎 Archivo subido:', data);
        this.addFileMessageToChat(data);
    }

    handleQueuePosition(data) {
        console.log('🔢 Posición en cola:', data);
        this.updateQueueInfo(data);
    }

    handleHeartbeat(data) {
        // Responder al heartbeat
        this.sendToSocket('heartbeat', { client_time: new Date().toISOString() });
    }

    // ====== ENVIAR MENSAJE ======
    sendMessage(content, messageType = 'text') {
        if (!content || content.trim() === '') return;
        
        if (!this.isConnected || !this.isAuthenticated) {
            this.showError('No conectado al chat');
            return;
        }
        
        const messageData = {
            content: content.trim(),
            message_type: messageType,
            session_id: this.currentSessionId,
            timestamp: new Date().toISOString()
        };
        
        // Agregar a UI inmediatamente
        this.addMessageToChat(messageData.content, 'patient', 'user', messageData.timestamp);
        
        // Enviar por socket
        this.sendToSocket('send_message', messageData);
        
        console.log('📤 Mensaje enviado:', content);
    }

    // ====== CARGAR HISTORIAL ======
    async loadMessageHistory() {
        if (!this.currentSessionId) return;
        
        try {
            console.log('📚 Cargando historial...');
            
            const response = await fetch(`${this.chatServiceUrl.replace('/chat', '')}/messages/${this.currentSessionId}?limit=50`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const result = await response.json();
                console.log('📖 Historial cargado:', result);
                
                if (result.success && result.data.messages) {
                    // Limpiar chat y agregar mensajes del historial
                    this.clearChatMessages();
                    
                    result.data.messages.forEach(msg => {
                        this.addMessageToChat(
                            msg.content, 
                            msg.sender_type, 
                            msg.sender_id, 
                            msg.timestamp,
                            false // No hacer scroll por cada mensaje
                        );
                    });
                    
                    // Scroll al final una vez
                    this.scrollToBottom();
                }
            }
            
        } catch (error) {
            console.error('❌ Error cargando historial:', error);
        }
    }

    // ====== UI HELPERS ======
    addMessageToChat(content, senderType, senderId, timestamp, scroll = true) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        
        const messageElement = this.createMessageElement({
            content,
            sender_type: senderType,
            sender_id: senderId,
            timestamp
        });
        
        messagesContainer.appendChild(messageElement);
        
        if (scroll) {
            this.scrollToBottom();
        }
    }

    createMessageElement(messageData) {
        const messageDiv = document.createElement('div');
        const isUser = messageData.sender_type === 'patient' || messageData.sender_id === 'user';
        
        messageDiv.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-4`;
        
        const bgClass = isUser ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white' : 
                       'bg-white border border-gray-200 text-gray-900';
        
        const time = new Date(messageData.timestamp).toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        messageDiv.innerHTML = `
            <div class="max-w-xs lg:max-w-md">
                <div class="${bgClass} rounded-2xl px-4 py-3 shadow-sm">
                    ${!isUser ? `
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Especialista</span>
                        </div>
                    ` : ''}
                    <p class="text-sm ${isUser ? 'text-white' : 'text-gray-700'}">${this.formatMessage(messageData.content)}</p>
                    <p class="text-xs ${isUser ? 'text-blue-100' : 'text-gray-500'} mt-2 opacity-75">${time}</p>
                </div>
            </div>
        `;
        
        return messageDiv;
    }

    formatMessage(message) {
        return message
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" class="underline">$1</a>');
    }

    clearChatMessages() {
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            messagesContainer.innerHTML = '';
        }
    }

    scrollToBottom() {
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    showTypingIndicator(userId = null) {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) {
            indicator.classList.remove('hidden');
            this.scrollToBottom();
        }
    }

    hideTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) {
            indicator.classList.add('hidden');
        }
    }

    showQueueInfo(queueInfo) {
        console.log('🔢 Info de cola:', queueInfo);
        // Implementar UI de cola si es necesario
    }

    updateQueueInfo(queueData) {
        console.log('🔢 Actualización cola:', queueData);
        // Implementar actualización de UI de cola
    }

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

    showError(message) {
        console.error('💬 Chat Error:', message);
        // Usar el sistema de notificaciones del authClient si está disponible
        if (window.authClient) {
            window.authClient.showError(message);
        } else {
            alert(message);
        }
    }

    // ====== UTILIDADES ======
    sendToSocket(event, data) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({ type: event, ...data }));
        } else {
            console.warn('Socket no disponible para:', event);
        }
    }

    startHeartbeat() {
        this.heartbeatInterval = setInterval(() => {
            if (this.socket && this.socket.readyState === WebSocket.OPEN) {
                this.sendToSocket('heartbeat', { client_time: new Date().toISOString() });
            }
        }, 30000);
    }

    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }

    playNotificationSound() {
        // Sonido simple de notificación
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmYfBSuPze/R');
            audio.volume = 0.1;
            audio.play().catch(() => {}); // Fallar silenciosamente
        } catch (error) {
            // Ignorar errores de audio
        }
    }

    onConnectionError(error) {
        console.error('💬 Error conexión:', error);
        this.updateConnectionStatus('Error de conexión');
        this.showError('Error de conexión con el chat');
    }

    disconnect() {
        console.log('🔌 Desconectando chat...');
        
        this.isConnected = false;
        this.isAuthenticated = false;
        this.stopHeartbeat();
        
        if (this.socket) {
            this.socket.close();
            this.socket = null;
        }
        
        this.updateConnectionStatus('Desconectado');
    }

    // ====== ESTADO ======
    getChatStats() {
        return {
            isConnected: this.isConnected,
            isAuthenticated: this.isAuthenticated,
            currentRoom: this.currentRoom,
            currentSessionId: this.currentSessionId,
            currentChatId: this.currentChatId,
            currentUserId: this.currentUserId,
            socketReadyState: this.socket ? this.socket.readyState : null
        };
    }
}

// ====== FUNCIONES GLOBALES ======
window.chatClient = new ChatClient();

// Función para enviar mensaje (llamada desde UI)
function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    const message = messageInput.value.trim();
    if (!message) return;
    
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
    
    messageInput.focus();
}

// Función para finalizar chat
function endChat() {
    if (confirm('¿Finalizar consulta?')) {
        window.chatClient.disconnect();
        
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            chatContainer.classList.add('hidden');
        }
        
        window.authClient?.showSuccess('Consulta finalizada');
        
        setTimeout(() => {
            window.location.href = 'https://www.tpsalud.com';
        }, 2000);
    }
}

// ====== DEBUG HELPERS COMPLETOS ======
window.debugChat = {
    // Estado del chat
    getState: () => {
        if (!window.chatClient) return 'Chat client no inicializado';
        
        return {
            isConnected: window.chatClient.isConnected,
            isAuthenticated: window.chatClient.isAuthenticated,
            currentRoom: window.chatClient.currentRoom,
            currentSessionId: window.chatClient.currentSessionId,
            currentChatId: window.chatClient.currentChatId,
            currentUserId: window.chatClient.currentUserId,
            currentPToken: window.chatClient.currentPToken ? 
                window.chatClient.currentPToken.substring(0, 15) + '...' : null,
            socketReadyState: window.chatClient.socket ? 
                window.chatClient.socket.readyState : null,
            chatServiceUrl: window.chatClient.chatServiceUrl,
            websocketUrl: window.chatClient.websocketUrl
        };
    },
    
    // Test de conectividad del chat service
    testHealth: async () => {
        if (!window.chatClient) {
            console.error('❌ Chat client no inicializado');
            return false;
        }
        try {
            await window.chatClient.checkChatServiceHealth();
            console.log('✅ Chat service saludable');
            return true;
        } catch (error) {
            console.error('❌ Chat service no disponible:', error.message);
            return false;
        }
    },
    
    // Test de join session directo
    testJoinSession: async (ptoken = null, roomId = 'general') => {
        if (!window.chatClient) {
            console.error('❌ Chat client no inicializado');
            return;
        }
        
        const token = ptoken || (window.currentSession ? window.currentSession.ptoken : null);
        if (!token) {
            console.error('❌ Necesitas un pToken válido');
            console.log('💡 Usa: window.debugChat.testJoinSession("CC678AVEZVKADBT", "general")');
            return;
        }
        
        try {
            const result = await window.chatClient.joinChatSession(token, roomId);
            console.log('✅ Join session exitoso:', result);
            return result;
        } catch (error) {
            console.error('❌ Error en join session:', error.message);
            return null;
        }
    },
    
    // Reconectar manualmente
    reconnect: async (ptoken = null, roomId = null) => {
        if (!window.chatClient) {
            console.error('❌ Chat client no inicializado');
            return;
        }
        
        const token = ptoken || window.chatClient.currentPToken;
        const room = roomId || window.chatClient.currentRoom;
        
        if (!token || !room) {
            console.error('❌ Faltan ptoken o roomId para reconectar');
            console.log('💡 Usa: window.debugChat.reconnect("tu-ptoken", "room-id")');
            return;
        }
        
        try {
            const result = await window.chatClient.connect(token, room);
            console.log('✅ Reconectado exitosamente:', result);
            return result;
        } catch (error) {
            console.error('❌ Error reconectando:', error.message);
            return null;
        }
    },
    
    // Información detallada de la sesión actual
    getSessionInfo: () => {
        if (!window.currentSession) {
            return 'No hay sesión activa';
        }
        
        return {
            ...window.currentSession,
            chatClientState: window.debugChat.getState(),
            connectionActive: window.chatClient ? window.chatClient.isConnected : false,
            lastActivity: new Date().toISOString()
        };
    },
    
    // Enviar mensaje de prueba
    testMessage: (message = 'Mensaje de prueba desde debug') => {
        if (!window.chatClient || !window.chatClient.isConnected) {
            console.error('❌ Chat no conectado');
            return;
        }
        
        window.chatClient.sendMessage(message);
        console.log('✅ Mensaje de prueba enviado:', message);
    },
    
    // Verificar logs del backend
    checkBackendLogs: async () => {
        try {
            const healthUrl = window.chatClient.chatServiceUrl.replace('/chat', '') + '/health';
            const response = await fetch(healthUrl);
            const health = await response.json();
            
            console.log('📊 Estado del backend:', health);
            return health;
        } catch (error) {
            console.error('❌ Error verificando backend:', error);
            return null;
        }
    },
    
    // Limpiar estado para testing
    reset: () => {
        if (window.chatClient) {
            window.chatClient.disconnect();
        }
        window.chatClient = null;
        window.currentSession = null;
        console.log('🧹 Estado del chat limpiado');
    },
    
    // Ayuda de comandos disponibles
    help: () => {
        console.log(`
🛠️ COMANDOS DEBUG DISPONIBLES:

📊 Estado:
- window.debugChat.getState()          // Estado actual del chat
- window.debugChat.getSessionInfo()    // Info de la sesión
- window.debugChat.checkBackendLogs()  // Estado del backend

🔧 Testing:
- window.debugChat.testHealth()        // Test de salud del servicio
- window.debugChat.testJoinSession()   // Test de crear sesión
- window.debugChat.testMessage()       // Enviar mensaje de prueba

🔄 Reconexión:
- window.debugChat.reconnect()         // Reconectar con datos actuales
- window.debugChat.reset()             // Limpiar todo el estado

💡 Ejemplo:
window.debugChat.testJoinSession('CC678AVEZVKADBT', 'general')
        `);
    }
};

console.log('💬 ChatClient v2.0 CORREGIDO cargado');
console.log('🛠️ Debug disponible en: window.debugChat');
console.log('💡 Usa window.debugChat.help() para ver todos los comandos');