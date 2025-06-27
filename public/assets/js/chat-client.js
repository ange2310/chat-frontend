// public/assets/js/chat-client.js - VERSIÓN MINIMALISTA CON AVATARES
class ChatClient {
    constructor() {
        // ✅ URLS CORREGIDAS PARA NGINX PROXY
        this.chatServiceUrl = 'http://187.33.158.246:8080/chats';
        this.websocketUrl = 'ws://187.33.158.246:8080';
        this.fileServiceUrl = 'http://187.33.158.246:8080/chats';
        
        this.socket = null;
        this.isConnected = false;
        this.isAuthenticated = false;
        this.currentRoom = null;
        this.currentSessionId = null;
        this.currentPToken = null;
        this.messageQueue = [];
        this.heartbeatInterval = null;
        
        console.log('💬 ChatClient minimalista iniciado');
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
                
                this.socket = io(this.websocketUrl, {
                    path: '/socket.io/',
                    transports: ['websocket', 'polling'],
                    autoConnect: true,
                    auth: {
                        ptoken: ptoken
                    }
                });
                
                this.socket.on('connect', () => {
                    console.log('✅ Socket.IO conectado via nginx');
                    this.isConnected = true;
                    this.updateConnectionStatus('Conectado');
                    
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

                this.setupSocketEventHandlers();
                
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
        this.stubUserId = data.user_id;
        this.updateConnectionStatus('Autenticado');
    }

    handleAuthError(data) {
        console.error('❌ Error autenticación socket:', data);
        this.updateConnectionStatus('Error de autenticación');
    }

    handleRoomJoined(data) {
        console.log('✅ Sala unida:', data);
        this.updateConnectionStatus('En sala');
        
        if (data.queue_info) {
            this.showQueueInfo(data.queue_info);
        }
        
        // Secuencia controlada de inicialización
        setTimeout(() => {
            try {
                // 1. Limpiar chat
                this.clearChatMessages();
                
                // 2. Agregar mensajes automáticos
                this.addInitialSystemMessages();
                
                // 3. Cargar historial después de los mensajes automáticos
                setTimeout(() => {
                    this.loadMessageHistory();
                }, 4000); // Esperar a que terminen los 3 mensajes automáticos
                
            } catch (error) {
                console.error('❌ Error en inicialización del chat:', error);
            }
        }, 100);
        
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
        this.sendToSocket('heartbeat', { client_time: new Date().toISOString() });
    }

    // ====== MENSAJES AUTOMÁTICOS INICIALES ======
    addInitialSystemMessages() {
        console.log('📝 Agregando mensajes automáticos del sistema...');
        
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) {
            console.warn('⚠️ No se encontró el contenedor de mensajes');
            return;
        }
        
        const messages = [
            'Bienvenido a Teleorientación CEM. Para urgencias o emergencias comunícate al #586.',
            'Al continuar en este chat estás aceptando nuestra política de tratamiento de datos.',
            'Cuéntanos ¿En qué podemos ayudarte hoy?'
        ];

        // Agregar mensajes con delay progresivo
        messages.forEach((content, index) => {
            setTimeout(() => {
                try {
                    console.log(`📝 Agregando mensaje ${index + 1}:`, content);
                    this.addMessageToChat(content, 'system', 'system', new Date().toISOString(), true);
                } catch (error) {
                    console.error(`❌ Error agregando mensaje ${index + 1}:`, error);
                }
            }, index * 1000); // 1 segundo entre cada mensaje
        });
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
        if (!this.currentSessionId) {
            console.log('📚 No hay sessionId, saltando historial');
            return;
        }
        
        try {
            console.log('📚 Cargando historial...');
            
            const response = await fetch(`http://187.33.158.246:8080/chats/messages/${this.currentSessionId}?limit=50`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const result = await response.json();
                console.log('📖 Historial response:', result);
                
                if (result.success && result.data && result.data.messages && Array.isArray(result.data.messages) && result.data.messages.length > 0) {
                    console.log(`📖 Cargando ${result.data.messages.length} mensajes del historial`);
                    
                    result.data.messages.forEach(msg => {
                        if (msg && msg.content) {
                            this.addMessageToChat(
                                msg.content, 
                                msg.sender_type || 'system', 
                                msg.sender_id || 'system', 
                                msg.timestamp,
                                false
                            );
                        }
                    });
                    
                    this.scrollToBottom();
                } else {
                    console.log('📖 No hay mensajes en el historial o es una sesión nueva');
                }
            } else {
                console.log('📖 No se pudo cargar historial, respuesta:', response.status);
            }
            
        } catch (error) {
            console.error('❌ Error cargando historial:', error);
        }
    }

    // ====== UI HELPERS - VERSIÓN MINIMALISTA ======
    getFullFileUrl(partialUrl) {
        if (!partialUrl) return '#';
        if (partialUrl.startsWith('http')) return partialUrl;
        return `${this.fileServiceUrl}${partialUrl.startsWith('/') ? '' : '/'}${partialUrl}`;
    }

    addMessageToChat(content, senderType, senderId, timestamp, scroll = true) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) {
            console.warn('⚠️ No se encontró el contenedor de mensajes');
            return;
        }
        
        try {
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
        } catch (error) {
            console.error('❌ Error agregando mensaje al chat:', error);
        }
    }

    addFileMessageToChat(fileData, isMe = false) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        
        const messageElement = document.createElement('div');
        messageElement.className = `message ${isMe ? 'message-user' : 'message-agent'}`;

        const avatarClass = isMe ? 'avatar-user' : 'avatar-agent';
        const avatarIcon = isMe ? 'U' : 'D';

        messageElement.innerHTML = `
            <div class="avatar avatar-md ${avatarClass}">
                ${avatarIcon}
            </div>
            <div class="message-content">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <svg class="icon text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium">${fileData.file_name}</p>
                        <p class="text-sm opacity-75">${this.formatFileSize(fileData.file_size)}</p>
                        <a href="${this.getFullFileUrl(fileData.download_url)}" target="_blank" download 
                           class="text-sm underline hover:no-underline">Descargar</a>
                    </div>
                </div>
                <div class="message-time">${this.formatTime(fileData.timestamp)}</div>
            </div>
        `;
        
        messagesContainer.appendChild(messageElement);
        this.scrollToBottom();
    }

    createMessageElement(messageData) {
        const messageDiv = document.createElement('div');
        const isUser = messageData.sender_type === 'patient' || messageData.sender_id === 'user';

        try {
            const tsString = messageData.timestamp || messageData.created_at || messageData.createdAt || Date.now();
            const timeLabel = this.formatTime(tsString);

            messageDiv.className = `message ${isUser ? 'message-user' : 'message-system'}`;
            
            if (isUser) {
                // Mensaje del usuario - alineado a la derecha, azul, con avatar azul
                messageDiv.innerHTML = `
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #0372B9;">
                        U
                    </div>
                    <div class="message-content">
                        <p>${this.formatMessage(messageData.content || '')}</p>
                        <div class="message-time">${timeLabel}</div>
                    </div>
                `;
            } else {
                // Mensaje del sistema/doctor - alineado a la izquierda, gris con label CEM, avatar verde
                messageDiv.innerHTML = `
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #8CF79D; color: #065f46;">
                        C
                    </div>
                    <div class="message-content">
                        <div class="text-sm font-medium text-gray-700 mb-1">CEM:</div>
                        <p>${this.formatMessage(messageData.content || '')}</p>
                        <div class="message-time">${timeLabel}</div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('❌ Error creando elemento de mensaje:', error);
            // Fallback simple con avatar
            messageDiv.className = 'message message-system';
            messageDiv.innerHTML = `
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #8CF79D; color: #065f46;">
                    C
                </div>
                <div class="message-content">
                    <div class="text-sm font-medium text-gray-700 mb-1">CEM:</div>
                    <p>Error mostrando mensaje</p>
                </div>
            `;
        }
        
        return messageDiv;
    }

    formatMessage(message) {
        if (!message || typeof message !== 'string') {
            return '';
        }
        
        try {
            return message
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" class="underline">$1</a>');
        } catch (error) {
            console.error('❌ Error formateando mensaje:', error);
            return message;
        }
    }

    formatTime(timestamp) {
        try {
            const date = new Date(timestamp);
            if (isNaN(date.getTime())) {
                return '';
            }
            return date.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        } catch (error) {
            console.error('❌ Error formateando tiempo:', error);
            return '';
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    clearChatMessages() {
        try {
            const messagesContainer = document.getElementById('chatMessages');
            if (messagesContainer) {
                messagesContainer.innerHTML = '';
                console.log('🧹 Chat limpiado correctamente');
            }
        } catch (error) {
            console.error('❌ Error limpiando chat:', error);
        }
    }

    scrollToBottom() {
        try {
            const messagesContainer = document.getElementById('chatMessages');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        } catch (error) {
            console.error('❌ Error haciendo scroll:', error);
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
    }

    updateQueueInfo(queueData) {
        console.log('🔢 Actualización cola:', queueData);
    }

    updateConnectionStatus(status) {
        const chatStatus = document.getElementById('chatStatus');
        
        if (chatStatus) {
            chatStatus.textContent = status;
        }
        
        console.log('📡 Estado:', status);
    }

    showError(message) {
        console.error('💬 Chat Error:', message);
        if (window.authClient) {
            window.authClient.showError(message);
        } else {
            alert(message);
        }
    }

    // ====== UTILIDADES ======
    sendToSocket(event, data) {
        if (this.socket && this.socket.connected) {
            this.socket.emit(event, data);
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
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmYfBSuPze/R');
            audio.volume = 0.1;
            audio.play().catch(() => {});
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
            this.socket.disconnect();
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

// ====== FUNCIONES GLOBALES MINIMALISTAS ======
window.chatClient = new ChatClient();

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

function endChat() {
    window.chatClient.disconnect();
    
    const chatContainer = document.getElementById('chatSection');
    if (chatContainer) {
        chatContainer.classList.add('hidden');
    }
    
    window.authClient?.showSuccess('Consulta finalizada');
    
    setTimeout(() => {
        window.location.href = 'https://www.tpsalud.com';
    }, 2000);
}

console.log('💬 ChatClient v3.0 - DISEÑO MINIMALISTA CON AVATARES');