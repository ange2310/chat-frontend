// public/assets/js/chat-client.js - NGINX PROXY VERSION
class ChatClient {
    constructor() {
        // ✅ URLS CORREGIDAS PARA NGINX PROXY
        this.chatServiceUrl = 'http://187.33.158.246:8080/chats';     // ← A través de nginx
        this.websocketUrl = 'ws://187.33.158.246:8080';           // ← A través de nginx
        this.fileServiceUrl = 'http://187.33.158.246:8080/chats';   // ← A través de nginx
        
        this.socket = null;
        this.isConnected = false;
        this.isAuthenticated = false;
        this.currentRoom = null;
        this.currentSessionId = null;
        this.currentPToken = null;
        this.messageQueue = [];
        this.heartbeatInterval = null;
        
        console.log('💬 ChatClient inicializado - SOCKET.IO + NGINX');
        console.log('🌐 Chat Service:', this.chatServiceUrl);
        console.log('🔌 WebSocket:', this.websocketUrl);
        console.log('📁 File Service:', this.fileServiceUrl);
    }

    // ====== CONECTAR AL CHAT ======
    async connect(ptoken, roomId) {
        console.log('💬 Conectando al chat...', { roomId });
        
        this.currentPToken = ptoken;
        this.currentRoom = roomId;
        
        try {
            // 1. Crear sesión de chat
            const sessionData = await this.joinChatSession(ptoken, roomId);
            this.currentSessionId = sessionData.session_id;
            
            // 2. Conectar WebSocket
            await this.connectWebSocket(ptoken);
            
            // 3. Unirse a la sala
            await this.joinRoom(roomId, this.currentSessionId);
            
            console.log('✅ Chat conectado exitosamente');
            
        } catch (error) {
            console.error('❌ Error conectando chat:', error);
            this.onConnectionError(error);
        }
    }

    // ====== CREAR SESIÓN DE CHAT ======
    async joinChatSession(ptoken, roomId) {
        try {
            console.log('📡 Creando sesión de chat...');
            
            const response = await fetch(`${this.chatServiceUrl}/join`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    room_id: roomId,
                    ptoken: ptoken,
                    user_data: {
                        source: 'patient_portal',
                        browser: navigator.userAgent,
                        timestamp: new Date().toISOString()
                    }
                })
            });

            const result = await response.json();
            console.log('📋 Sesión response:', result);

            if (response.ok && result.success) {
                this.updateConnectionStatus('Sesión creada');
                return result.data;
            } else {
                throw new Error(result.message || 'Error creando sesión');
            }

        } catch (error) {
            console.error('❌ Error join session:', error);
            throw error;
        }
    }

    // ====== CONECTAR WEBSOCKET - SOCKET.IO ======
    async connectWebSocket(ptoken) {
        return new Promise((resolve, reject) => {
            try {
                console.log('🔌 Conectando Socket.IO via nginx...');
                
                // ✅ SOCKET.IO A TRAVÉS DE NGINX
                this.socket = io(this.websocketUrl, {
                    path: '/socket.io/',  // ← PATH CONFIGURADO EN NGINX
                    transports: ['websocket', 'polling'],
                    autoConnect: true,
                    auth: {
                        ptoken: ptoken
                    }
                });
                
                // ✅ EVENT HANDLERS DE SOCKET.IO
                this.socket.on('connect', () => {
                    console.log('✅ Socket.IO conectado via nginx');
                    this.isConnected = true;
                    this.updateConnectionStatus('Conectado');
                    
                    // Autenticar inmediatamente
                    this.authenticateSocket(ptoken);
                    resolve();
                });
                
                this.socket.on('disconnect', () => {
                    console.log('🔌 Socket.IO desconectado');
                    this.isConnected = false;
                    this.isAuthenticated = false;
                    this.updateConnectionStatus('Desconectado');
                    this.stopHeartbeat();
                });
                
                this.socket.on('connect_error', (error) => {
                    console.error('❌ Socket.IO error:', error);
                    this.onConnectionError(error);
                    reject(error);
                });

                // ✅ TODOS LOS EVENT HANDLERS ESPECÍFICOS
                this.setupSocketEventHandlers();
                
                // Timeout de conexión
                setTimeout(() => {
                    if (!this.isConnected) {
                        reject(new Error('Timeout conectando Socket.IO via nginx'));
                    }
                }, 10000);
                
            } catch (error) {
                console.error('❌ Error creating Socket.IO:', error);
                reject(error);
            }
        });
    }

    // ✅ CONFIGURAR TODOS LOS EVENT HANDLERS
    setupSocketEventHandlers() {
        this.socket.on('authenticated', (data) => this.handleAuthenticated(data));
        this.socket.on('auth_error', (data) => this.handleAuthError(data));
        this.socket.on('room_joined', (data) => this.handleRoomJoined(data));
        this.socket.on('room_error', (data) => this.handleRoomError(data));
        this.socket.on('message_received', (data) => this.handleMessageReceived(data));
        this.socket.on('message_sent', (data) => this.handleMessageSent(data));
        this.socket.on('message_error', (data) => this.handleMessageError(data));
        this.socket.on('user_typing', (data) => this.handleUserTyping(data));
        this.socket.on('user_stop_typing', (data) => this.handleUserStopTyping(data));
        this.socket.on('file_uploaded', (data) => this.handleFileUploaded(data));
        this.socket.on('queue_position', (data) => this.handleQueuePosition(data));
        this.socket.on('heartbeat', (data) => this.handleHeartbeat(data));
    }

    // ====== AUTENTICAR SOCKET ======
    authenticateSocket(ptoken) {
        if (!this.socket || !this.socket.connected) {
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

    // ====== HANDLERS ======
    handleAuthenticated(data) {
        console.log('✅ Socket autenticado:', data);
        this.isAuthenticated = true;
        this.stubUserId      = data.user_id;
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
        const ts = data.timestamp || data.created_at || data.createdAt || new Date().toISOString();

        this.addMessageToChat(
            data.content,
            data.sender_type,
            data.sender_id,
            ts               
        );

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
        const isMe =
            (data.sender_id  && data.sender_id  === this.stubUserId) ||
            (data.session_id && data.session_id === this.currentSessionId);

        this.addFileMessageToChat(data, isMe);
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

    // construimos el payload que se manda por socket
    const messageData = {
        content      : content.trim(),
        message_type : messageType,
        session_id   : this.currentSessionId,
        timestamp    : new Date().toISOString()      // hora local provisional
    };
        
        // Enviar por socket
         this.sendToSocket('send_message', messageData);
        
        console.log('📤 Mensaje enviado:', content);
    }

    // ====== SUBIR ARCHIVO ======
    async uploadFile(file, description = '') {
        if (!file) return;
        
        if (!this.currentSessionId) {
            this.showError('No hay sesión activa');
            return;
        }
        
        try {
            console.log('📎 Subiendo archivo:', file.name);
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('session_id', this.currentSessionId);
            formData.append('user_id', this.stubUserId); 
            if (description) formData.append('description', description);
            
            const response = await fetch(`${this.fileServiceUrl}/files/upload`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            console.log('📁 Upload result:', result);
            
            if (response.ok && result.success) {
                console.log('✅ Archivo subido exitosamente');
                // El WebSocket debería notificar automáticamente
                return result.data;
            } else {
                throw new Error(result.message || 'Error subiendo archivo');
            }
            
        } catch (error) {
            console.error('❌ Error upload:', error);
            this.showError('Error subiendo archivo: ' + error.message);
        }
    }

    // ====== CARGAR HISTORIAL ======
    async loadMessageHistory() {
        if (!this.currentSessionId) return;
        
        try {
            console.log('📚 Cargando historial...');
            
            // Usar endpoint de historial a través de nginx
            const response = await fetch(`http://187.33.158.246:8080/chats/messages/${this.currentSessionId}?limit=50`, {
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
    getFullFileUrl (partialUrl) {
        if (!partialUrl) return '#';
        if (partialUrl.startsWith('http')) return partialUrl;
        // garantiza una sola barra
        return `${this.fileServiceUrl}${partialUrl.startsWith('/') ? '' : '/'}${partialUrl}`;
    }

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

    addFileMessageToChat(fileData, isMe = false) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        const messageElement = document.createElement('div');
        messageElement.className =
            `flex ${isMe ? 'justify-end' : 'justify-start'} mb-4`;

        messageElement.innerHTML = `
            <div class="max-w-xs lg:max-w-md">
                <div class="${isMe
                        ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white'
                        : 'bg-white border border-gray-200 text-gray-900'} rounded-2xl">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">${fileData.file_name}</p>
                            <p class="text-xs text-gray-500">${this.formatFileSize(fileData.file_size)}</p>
                            <a href="${this.getFullFileUrl(fileData.download_url)}" target="_blank" download 
                               class="text-xs text-blue-600 hover:text-blue-800">Descargar</a>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">${new Date(fileData.timestamp).toLocaleTimeString()}</p>
                </div>
            </div>
        `;
        
        messagesContainer.appendChild(messageElement);
        this.scrollToBottom();
    }

    createMessageElement(messageData) {
        const messageDiv = document.createElement('div');
        const isUser = messageData.sender_type === 'patient' ||
                    messageData.sender_id   === 'user';

        // ─── normalizamos timestamp ───────────────────────────
        const tsString  = messageData.timestamp ||
                        messageData.created_at ||
                        messageData.createdAt  ||
                        Date.now();            // último recurso

        const tsDate    = new Date(tsString);
        const timeLabel = isNaN(tsDate)
            ? ''          // oculta la hora si llegara mal
            : tsDate.toLocaleTimeString('es-ES', { hour:'2-digit', minute:'2-digit' });

        // 🔑  *** AQUÍ va la línea que faltaba ***
        const bgClass = isUser
            ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white'
            : 'bg-white border border-gray-200 text-gray-900';

        // ─── estructura del mensaje ───────────────────────────
        messageDiv.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-4`;
        messageDiv.innerHTML = `
            <div class="max-w-xs lg:max-w-md">
                <div class="${bgClass} rounded-2xl px-4 py-3 shadow-sm">
                    ${!isUser ? `
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Dr. Asistente</span>
                        </div>` : ''}
                    <p class="text-sm ${isUser ? 'text-white' : 'text-gray-700'}">
                        ${this.formatMessage(messageData.content)}
                    </p>
                    <p class="text-xs ${isUser ? 'text-blue-100' : 'text-gray-500'} mt-2 opacity-75">
                        ${timeLabel}
                    </p>
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

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
    // ✅ MÉTODO CORREGIDO PARA SOCKET.IO
    sendToSocket(event, data) {
        if (this.socket && this.socket.connected) {
            this.socket.emit(event, data);  // ← EMIT en lugar de SEND
        } else {
            console.warn('Socket no disponible para:', event);
        }
    }

    startHeartbeat() {
        this.heartbeatInterval = setInterval(() => {
            if (this.socket && this.socket.connected) {
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
            this.socket.disconnect();  // ← DISCONNECT en lugar de CLOSE
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
            socketConnected: this.socket ? this.socket.connected : false,
            chatServiceUrl: this.chatServiceUrl,
            websocketUrl: this.websocketUrl,
            fileServiceUrl: this.fileServiceUrl
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

// Manejo de archivos
function handleFileUpload(files) {
    if (!files || files.length === 0) return;
    
    const file = files[0];
    
    // Validar tamaño (10MB máximo)
    if (file.size > 10 * 1024 * 1024) {
        window.authClient?.showError('Archivo muy grande (máximo 10MB)');
        return;
    }
    
    window.chatClient.uploadFile(file);
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

console.log('💬 ChatClient v2.7 cargado - NGINX PROXY CONFIGURADO');