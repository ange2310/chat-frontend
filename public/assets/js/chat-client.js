class ChatClient {
  constructor() {
    // URLs LOCALES DIRECTAS
    this.chatServiceUrl = 'http://localhost:3011/chats';
    this.websocketUrl = 'http://localhost:3011';
    this.fileServiceUrl = 'http://localhost:3011/files';
    
    this.socket = null;
    this.isConnected = false;
    this.isAuthenticated = false;
    this.currentRoom = null;
    this.currentSessionId = null;
    this.currentUserId = null;
    this.userType = 'patient';
    
    console.log('💬 ChatClient inicializado');
    console.log('🔗 Chat Service:', this.chatServiceUrl);
    console.log('🔌 WebSocket:', this.websocketUrl);
  }

  // ====== MÉTODO PRINCIPAL PARA CONECTAR ======
  async connect(pToken, roomId = 'general', userName = 'Paciente') {
    try {
      console.log('🚀 Iniciando conexión del chat...', { pToken: pToken.substring(0, 15) + '...', roomId, userName });
      
      // 1. Crear sesión de chat
      const sessionData = await this.createSimpleSession(pToken, roomId, userName);
      this.currentSessionId = sessionData.session_id;
      this.currentUserId = pToken;  // Guardamos el pToken como identificador
      this.currentRoom = roomId;
      
      console.log('✅ Sesión creada:', sessionData);
      
      // 2. Conectar WebSocket
      await this.connectWebSocket();
      
      // 3. Unirse al chat
      this.joinChat();
      
      return sessionData;
      
    } catch (error) {
      console.error('❌ Error conectando chat:', error);
      this.showError('Error conectando al chat: ' + error.message);
      throw error;
    }
  }

  // ====== CREAR SESIÓN SIMPLE ======
  async createSimpleSession(pToken, roomId, userName) {
    try {
      console.log('📡 Creando sesión simple...');
      console.log('📝 Parámetros:', { pToken: pToken.substring(0, 15) + '...', roomId, userName });
      
      const response = await fetch(`${this.chatServiceUrl}/create-simple`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          user_id: pToken,      // ✅ pToken va como user_id
          user_name: userName,  // ✅ Correcto
          room_id: roomId       // ✅ Correcto
        })
      });

      console.log('📡 Response status:', response.status);
      console.log('📡 Response headers:', response.headers.get('content-type'));

      const result = await response.json();
      console.log('📨 Respuesta sesión:', result);

      if (response.ok && result.success) {
        this.updateConnectionStatus('Sesión creada');
        return result.data;
      } else {
        console.error('❌ Error en respuesta:', result);
        throw new Error(result.message || 'Error creando sesión');
      }

    } catch (error) {
      console.error('❌ Error creando sesión:', error);
      throw error;
    }
  }

  // ====== CONECTAR WEBSOCKET ======
  async connectWebSocket() {
    return new Promise((resolve, reject) => {
      try {
        // ✅ VALIDACIONES PREVIAS
        if (!this.currentUserId) {
          const error = new Error('currentUserId no está definido');
          console.error('❌ Error pre-conexión:', error.message);
          reject(error);
          return;
        }

        if (!this.websocketUrl) {
          const error = new Error('websocketUrl no está definido');
          console.error('❌ Error pre-conexión:', error.message);
          reject(error);
          return;
        }

        // ✅ LOGGING MEJORADO
        console.log('🔌 Conectando Socket.IO...');
        console.log('🔍 URL WebSocket:', this.websocketUrl);
        console.log('🔍 Usuario ID (pToken):', this.currentUserId.substring(0, 15) + '...');
        console.log('🔍 Tipo usuario:', 'patient');
        
        // ✅ DESCONECTAR SOCKET ANTERIOR SI EXISTE
        if (this.socket) {
          console.log('🔄 Desconectando socket anterior...');
          this.socket.disconnect();
          this.socket = null;
        }

        // ✅ RESETEAR ESTADO
        this.isConnected = false;
        this.updateConnectionStatus('Conectando...');
        
        // ✅ CREAR NUEVA CONEXIÓN CON CONFIGURACIÓN MEJORADA
        this.socket = io(this.websocketUrl, {
            transports: ['websocket', 'polling'],
            autoConnect: true,
            timeout: 10000,
            reconnection: true,
            reconnectionAttempts: 3,
            reconnectionDelay: 2000,
            auth: {
                // ✅ DATOS CORRECTOS PARA TU MIDDLEWARE
                user_type: 'patient',              // ← Tu middleware busca esto
                ptoken: this.currentUserId,        // ← Token del paciente
                session_id: this.currentSessionId, // ← ID de la sesión de chat
                user_id: this.currentUserId        // ← Compatibilidad con lógica existente
            }
            });

        // ✅ LOGGING DEL SOCKET CREADO
        console.log('🔧 Socket creado con ID:', this.socket.id || 'pending');
        
        // ✅ EVENT HANDLERS CON LOGGING MEJORADO
        this.socket.on('connect', () => {
          console.log('✅ Socket.IO conectado exitosamente');
          console.log('🔍 Socket ID:', this.socket.id);
          console.log('🔍 Transporte usado:', this.socket.io.engine.transport.name);
          
          this.isConnected = true;
          this.updateConnectionStatus('Conectado');
          resolve();
        });
        
        this.socket.on('disconnect', (reason) => {
          console.log('🔌 Socket.IO desconectado');
          console.log('🔍 Razón:', reason);
          
          this.isConnected = false;
          this.updateConnectionStatus('Desconectado');
          
          // ✅ SOLO MOSTRAR ERROR SI NO FUE DESCONEXIÓN MANUAL
          if (reason !== 'io client disconnect') {
            this.showError('Conexión perdida: ' + reason);
          }
        });
        
        this.socket.on('connect_error', (error) => {
          console.error('❌ Error de conexión Socket.IO:', error);
          console.error('🔍 Detalles del error:', {
            message: error.message,
            description: error.description,
            context: error.context,
            type: error.type
          });
          
          this.isConnected = false;
          this.updateConnectionStatus('Error de conexión');
          reject(error);
        });

        // ✅ EVENTOS DE RECONEXIÓN
        this.socket.on('reconnect', (attemptNumber) => {
          console.log('🔄 Reconectado después de', attemptNumber, 'intentos');
          this.updateConnectionStatus('Reconectado');
        });

        this.socket.on('reconnect_attempt', (attemptNumber) => {
          console.log('🔄 Intento de reconexión', attemptNumber);
          this.updateConnectionStatus(`Reconectando... (${attemptNumber})`);
        });

        this.socket.on('reconnect_error', (error) => {
          console.error('❌ Error en reconexión:', error);
        });

        this.socket.on('reconnect_failed', () => {
          console.error('❌ Falló la reconexión después de todos los intentos');
          this.updateConnectionStatus('Reconexión fallida');
          this.showError('No se pudo reconectar al servidor. Recarga la página.');
        });

        // ✅ CONFIGURAR EVENT HANDLERS ADICIONALES
        this.setupSocketEventHandlers();
        
        // ✅ TIMEOUT MEJORADO CON MEJOR MENSAJE
        const timeoutId = setTimeout(() => {
          if (!this.isConnected) {
            console.error('❌ Timeout conectando WebSocket después de 15 segundos');
            
            // ✅ LIMPIAR SOCKET EN CASO DE TIMEOUT
            if (this.socket) {
              this.socket.disconnect();
              this.socket = null;
            }
            
            this.updateConnectionStatus('Timeout');
            reject(new Error('Timeout conectando al servidor. Verifica que el servidor esté disponible en ' + this.websocketUrl));
          }
        }, 15000); // ✅ 15 segundos es más que suficiente

        // ✅ CANCELAR TIMEOUT SI SE CONECTA
        this.socket.on('connect', () => {
          clearTimeout(timeoutId);
        });

        // ✅ CANCELAR TIMEOUT SI HAY ERROR
        this.socket.on('connect_error', () => {
          clearTimeout(timeoutId);
        });
        
      } catch (error) {
        console.error('❌ Error creando Socket.IO:', error);
        console.error('🔍 Stack trace:', error.stack);
        
        this.updateConnectionStatus('Error');
        reject(error);
      }
    });
  }

  // ✅ MÉTODO ADICIONAL PARA VERIFICAR ESTADO DE CONEXIÓN
  isWebSocketHealthy() {
    return this.socket && 
           this.socket.connected && 
           this.isConnected &&
           this.socket.io.engine.readyState === 'open';
  }

  // ✅ MÉTODO PARA RECONECTAR MANUALMENTE
  async reconnectWebSocket() {
    console.log('🔄 Iniciando reconexión manual...');
    
    try {
      if (this.socket) {
        this.socket.disconnect();
        this.socket = null;
      }
      
      await this.connectWebSocket();
      console.log('✅ Reconexión manual exitosa');
      return true;
    } catch (error) {
      console.error('❌ Error en reconexión manual:', error);
      return false;
    }
  }

  // ✅ MÉTODO PARA DIAGNÓSTICO
  getSocketDiagnostics() {
    if (!this.socket) {
      return {
        status: 'NO_SOCKET',
        message: 'Socket no inicializado'
      };
    }

    return {
      status: this.socket.connected ? 'CONNECTED' : 'DISCONNECTED',
      socket_id: this.socket.id,
      transport: this.socket.io.engine.transport.name,
      readyState: this.socket.io.engine.readyState,
      url: this.socket.io.uri,
      isConnected: this.isConnected,
      reconnection: this.socket.io._reconnection,
      reconnectionAttempts: this.socket.io._reconnectionAttempts,
      timeout: this.socket.io._timeout
    };
  }

  // ====== CONFIGURAR EVENT HANDLERS ======
  setupSocketEventHandlers() {
    // Eventos de conexión
    this.socket.on('chat_joined', (data) => this.handleChatJoined(data));
    this.socket.on('error', (data) => this.handleError(data));

    // Eventos de mensajes
    this.socket.on('new_message', (data) => this.handleNewMessage(data));
    this.socket.on('message_sent', (data) => this.handleMessageSent(data));

    // Eventos de estado
    this.socket.on('session_status_changed', (data) => this.handleSessionStatusChanged(data));
    this.socket.on('user_joined', (data) => this.handleUserJoined(data));
    this.socket.on('user_left', (data) => this.handleUserLeft(data));

    // Eventos de typing
    this.socket.on('user_typing', (data) => this.handleUserTyping(data));
    this.socket.on('user_stop_typing', (data) => this.handleUserStopTyping(data));
  }

  // ====== UNIRSE AL CHAT ======
  joinChat() {
    if (!this.socket || !this.socket.connected) {
      console.warn('⚠️ Socket no está conectado');
      return;
    }

    console.log('🏠 Uniéndose al chat...', {
      session_id: this.currentSessionId,
      user_id: this.currentUserId.substring(0, 15) + '...',
      user_type: 'patient'
    });

    this.socket.emit('join_chat', {
      session_id: this.currentSessionId,
      user_id: this.currentUserId,
      user_type: 'patient'
    });
  }

  // ====== ENVIAR MENSAJE ======
  sendMessage(content, messageType = 'text') {
    if (!content || content.trim() === '') return;

    if (!this.isConnected) {
      this.showError('No conectado al chat');
      return;
    }

    const messageData = {
      content: content.trim(),
      message_type: messageType,
      session_id: this.currentSessionId,
      user_id: this.currentUserId,
      user_type: 'patient'
    };
    
    console.log('📤 Enviando mensaje:', {
      ...messageData,
      user_id: messageData.user_id.substring(0, 15) + '...'
    });
    this.socket.emit('send_message', messageData);
  }

  // ====== SUBIR ARCHIVO ======
  async uploadFile(file, description = '') {
    if (!file || !this.currentSessionId) return;
    
    try {
      console.log('📎 Subiendo archivo:', file.name);
      
      const formData = new FormData();
      formData.append('file', file);
      formData.append('session_id', this.currentSessionId);
      formData.append('user_id', this.currentUserId);
      if (description) formData.append('description', description);
      
      const response = await fetch(`${this.fileServiceUrl}/upload`, {
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

  // ====== INDICADORES DE TYPING ======
  startTyping() {
    if (this.socket && this.socket.connected) {
      this.socket.emit('typing_start', {
        session_id: this.currentSessionId,
        user_type: 'patient'
      });
    }
  }

  stopTyping() {
    if (this.socket && this.socket.connected) {
      this.socket.emit('typing_stop', {
        session_id: this.currentSessionId,
        user_type: 'patient'
      });
    }
  }

  // ====== HANDLERS ======
  handleChatJoined(data) {
    console.log('✅ Chat unido:', data);
    this.updateConnectionStatus('En chat');
    
    // Limpiar y cargar historial
    this.clearChatMessages();
    this.addInitialSystemMessages();
    
    // Cargar historial después de los mensajes automáticos
    setTimeout(() => {
      this.loadMessageHistory();
    }, 3000);
  }

  handleNewMessage(data) {
    console.log('📨 Nuevo mensaje recibido:', data);
    
    const isMyMessage = data.sender_id === this.currentUserId || data.user_type === 'patient';
    
    this.addMessageToChat(
      data.content,
      isMyMessage ? 'patient' : 'agent',
      data.sender_id,
      data.timestamp || new Date().toISOString()
    );

    // Sonido solo si no es mi mensaje
    if (!isMyMessage) {
      this.playNotificationSound();
    }
  }

  handleMessageSent(data) {
    console.log('✅ Mensaje enviado confirmado:', data);
  }

  handleSessionStatusChanged(data) {
    console.log('🔄 Estado de sesión cambiado:', data);
    if (data.status === 'active' && data.agent_id) {
      this.addSystemMessage('Un agente médico se ha unido a la conversación');
    }
  }

  handleUserJoined(data) {
    console.log('👤 Usuario se unió:', data);
    if (data.user_type === 'agent') {
      this.addSystemMessage('Un agente médico está ahora disponible');
    }
  }

  handleUserLeft(data) {
    console.log('👤 Usuario se fue:', data);
    if (data.user_type === 'agent') {
      this.addSystemMessage('El agente médico ha salido de la conversación');
    }
  }

  handleUserTyping(data) {
    if (data.user_type === 'agent') {
      this.showTypingIndicator();
    }
  }

  handleUserStopTyping(data) {
    if (data.user_type === 'agent') {
      this.hideTypingIndicator();
    }
  }

  handleError(data) {
    console.error('❌ Error del chat:', data);
    this.showError(data.message || 'Error en el chat');
  }

  // ====== CARGAR HISTORIAL ======
  async loadMessageHistory() {
    if (!this.currentSessionId) return;
    
    try {
      console.log('📚 Cargando historial...');
      
      const response = await fetch(`${this.chatServiceUrl}/messages/${this.currentSessionId}?limit=50`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });
      
      if (response.ok) {
        const result = await response.json();
        console.log('📖 Historial:', result);
        
        if (result.success && result.data && result.data.messages && result.data.messages.length > 0) {
          result.data.messages.forEach(msg => {
            if (msg && msg.content) {
              const isMyMessage = msg.sender_type === 'patient' || msg.sender_id === this.currentUserId;
              this.addMessageToChat(
                msg.content, 
                isMyMessage ? 'patient' : 'agent', 
                msg.sender_id, 
                msg.timestamp,
                false
              );
            }
          });
          this.scrollToBottom();
        }
      }
      
    } catch (error) {
      console.error('❌ Error cargando historial:', error);
    }
  }

  // ====== UI METHODS ======
  addInitialSystemMessages() {
    const messages = [
      'Bienvenido a Teleorientación CEM. Para urgencias o emergencias comunícate al #586.',
      'Al continuar en este chat estás aceptando nuestra política de tratamiento de datos.',
      'Cuéntanos ¿En qué podemos ayudarte hoy?'
    ];

    messages.forEach((content, index) => {
      setTimeout(() => {
        this.addSystemMessage(content);
      }, index * 1000);
    });
  }

  addSystemMessage(content) {
    this.addMessageToChat(content, 'system', 'system', new Date().toISOString(), true);
  }

  addMessageToChat(content, senderType, senderId, timestamp, scroll = true) {
    const messagesContainer = document.getElementById('chatMessages');
    if (!messagesContainer) return;
    
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
      console.error('❌ Error agregando mensaje:', error);
    }
  }

  createMessageElement(messageData) {
    const messageDiv = document.createElement('div');
    const isUser = messageData.sender_type === 'patient';
    const timeLabel = this.formatTime(messageData.timestamp);

    messageDiv.className = `message ${isUser ? 'message-user' : 'message-system'}`;
    
    if (isUser) {
      messageDiv.innerHTML = `
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #0372B9;">
          U
        </div>
        <div class="message-content">
          <p>${this.formatMessage(messageData.content)}</p>
          <div class="message-time">${timeLabel}</div>
        </div>
      `;
    } else {
      messageDiv.innerHTML = `
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #8CF79D; color: #065f46;">
          C
        </div>
        <div class="message-content">
          <div class="text-sm font-medium text-gray-700 mb-1">CEM:</div>
          <p>${this.formatMessage(messageData.content)}</p>
          <div class="message-time">${timeLabel}</div>
        </div>
      `;
    }
    
    return messageDiv;
  }

  formatMessage(message) {
    if (!message || typeof message !== 'string') return '';
    
    return message
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" class="underline">$1</a>');
  }

  formatTime(timestamp) {
    try {
      const date = new Date(timestamp);
      if (isNaN(date.getTime())) return '';
      return date.toLocaleTimeString('es-ES', { 
        hour: '2-digit', 
        minute: '2-digit' 
      });
    } catch (error) {
      return '';
    }
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

  showTypingIndicator() {
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

  updateConnectionStatus(status) {
    const chatStatus = document.getElementById('chatStatus');
    if (chatStatus) {
      chatStatus.textContent = status;
    }
    console.log('📡 Estado:', status);
  }

  showError(message) {
    console.error('Chat Error:', message);
    if (window.authClient) {
      window.authClient.showError(message);
    } else {
      alert(message);
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

  disconnect() {
    console.log('🔌 Desconectando chat...');
    
    this.isConnected = false;
    
    if (this.socket) {
      this.socket.disconnect();
      this.socket = null;
    }
    
    this.updateConnectionStatus('Desconectado');
  }

  getChatStats() {
    return {
      isConnected: this.isConnected,
      currentSessionId: this.currentSessionId,
      currentUserId: this.currentUserId ? this.currentUserId.substring(0, 15) + '...' : null,
      socketConnected: this.socket ? this.socket.connected : false,
      chatServiceUrl: this.chatServiceUrl,
      websocketUrl: this.websocketUrl
    };
  }
}

// Crear instancia global
window.chatClient = new ChatClient();

// Funciones globales para HTML
function sendMessage() {
  const messageInput = document.getElementById('messageInput');
  if (!messageInput) return;
  
  const message = messageInput.value.trim();
  if (!message) return;
  
  window.chatClient.sendMessage(message);
  
  // Limpiar input
  messageInput.value = '';
  messageInput.style.height = 'auto';
  
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
  
  if (file.size > 10 * 1024 * 1024) {
    window.authClient?.showError('Archivo muy grande (máximo 10MB)');
    return;
  }
  
  window.chatClient.uploadFile(file);
}

console.log('💬 ChatClient v4.1 corregido para pToken cargado');