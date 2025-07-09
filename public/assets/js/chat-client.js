class ChatClient {
  constructor() {
    this.chatServiceUrl = 'http://localhost:3011';
    this.websocketUrl = 'http://localhost:3011';
    this.fileServiceUrl = 'http://localhost:3011/files';
    
    this.socket = null;
    this.isConnected = false;
    this.isAuthenticated = false;
    this.currentRoom = null;
    this.currentSessionId = null;
    this.currentUserId = null;
    this.userType = 'patient';
    this.sessionJoined = false;
    this.lastSentMessage = null; // ✅ Para trackear mensajes enviados
    
    console.log('💬 ChatClient inicializado');
  }

  async connect(pToken, roomId = 'general', userName = 'Paciente') {
    try {
      console.log('🚀 Iniciando conexión del chat...', { pToken: pToken.substring(0, 15) + '...', roomId, userName });
      
      const sessionData = await this.createSimpleSession(pToken, roomId, userName);
      this.currentSessionId = sessionData.session_id;
      this.currentUserId = pToken; // ✅ GUARDAR EL PTOKEN COMPLETO
      this.currentRoom = roomId;
      
      console.log('✅ Sesión creada:', sessionData);
      console.log('🔍 GUARDADO currentUserId:', this.currentUserId?.substring(0, 20) + '...');
      
      await this.connectWebSocket();
      this.joinChat();
      
      return sessionData;
      
    } catch (error) {
      console.error('❌ Error conectando chat:', error);
      this.showError('Error conectando al chat: ' + error.message);
      throw error;
    }
  }

  async createSimpleSession(pToken, roomId, userName) {
    try {
      const response = await fetch(`${this.chatServiceUrl}/chats/create-simple`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          user_id: pToken,
          user_name: userName,
          room_id: roomId
        })
      });

      const result = await response.json();

      if (response.ok && result.success) {
        this.updateConnectionStatus('Sesión creada');
        return result.data;
      } else {
        throw new Error(result.message || 'Error creando sesión');
      }

    } catch (error) {
      console.error('❌ Error creando sesión:', error);
      throw error;
    }
  }

  async connectWebSocket() {
    return new Promise((resolve, reject) => {
      try {
        if (!this.currentUserId || !this.websocketUrl) {
          reject(new Error('Datos de conexión faltantes'));
          return;
        }

        if (this.socket) {
          this.socket.disconnect();
          this.socket = null;
        }

        this.isConnected = false;
        this.sessionJoined = false;
        this.updateConnectionStatus('Conectando...');
        
        this.socket = io(this.websocketUrl, {
          transports: ['websocket', 'polling'],
          autoConnect: true,
          timeout: 10000,
          reconnection: true,
          reconnectionAttempts: 3,
          reconnectionDelay: 2000,
          auth: {
            user_type: 'patient',
            ptoken: this.currentUserId,
            session_id: this.currentSessionId,
            user_id: this.currentUserId
          }
        });

        this.socket.on('connect', () => {
          console.log('✅ Socket.IO conectado exitosamente');
          this.isConnected = true;
          this.updateConnectionStatus('Conectado');
          resolve();
        });
        
        this.socket.on('disconnect', (reason) => {
          console.log('🔌 Socket.IO desconectado:', reason);
          this.isConnected = false;
          this.sessionJoined = false;
          this.updateConnectionStatus('Desconectado');
          
          if (reason !== 'io client disconnect') {
            this.showError('Conexión perdida: ' + reason);
          }
        });
        
        this.socket.on('connect_error', (error) => {
          console.error('❌ Error de conexión Socket.IO:', error);
          this.isConnected = false;
          this.updateConnectionStatus('Error de conexión');
          reject(error);
        });

        this.socket.on('reconnect', (attemptNumber) => {
          console.log('🔄 Reconectado después de', attemptNumber, 'intentos');
          this.updateConnectionStatus('Reconectado');
          
          if (this.currentSessionId) {
            setTimeout(() => this.joinChat(), 500);
          }
        });

        this.setupSocketEventHandlers();
        
        const timeoutId = setTimeout(() => {
          if (!this.isConnected) {
            console.error('❌ Timeout conectando WebSocket');
            if (this.socket) {
              this.socket.disconnect();
              this.socket = null;
            }
            this.updateConnectionStatus('Timeout');
            reject(new Error('Timeout conectando al servidor'));
          }
        }, 15000);

        this.socket.on('connect', () => clearTimeout(timeoutId));
        this.socket.on('connect_error', () => clearTimeout(timeoutId));
        
      } catch (error) {
        console.error('❌ Error creando Socket.IO:', error);
        this.updateConnectionStatus('Error');
        reject(error);
      }
    });
  }

  setupSocketEventHandlers() {
    this.socket.on('chat_joined', (data) => this.handleChatJoined(data));
    this.socket.on('error', (data) => this.handleError(data));
    this.socket.on('new_message', (data) => this.handleNewMessage(data));
    this.socket.on('message_sent', (data) => this.handleMessageSent(data));
    this.socket.on('session_status_changed', (data) => this.handleSessionStatusChanged(data));
    this.socket.on('user_joined', (data) => this.handleUserJoined(data));
    this.socket.on('user_left', (data) => this.handleUserLeft(data));
    this.socket.on('user_typing', (data) => this.handleUserTyping(data));
    this.socket.on('user_stop_typing', (data) => this.handleUserStopTyping(data));
  }

  joinChat() {
    if (!this.socket || !this.socket.connected) {
      console.warn('⚠️ Socket no está conectado');
      return;
    }

    console.log('🏠 Paciente uniéndose al chat...');

    this.socket.emit('join_chat', {
      session_id: this.currentSessionId,
      user_id: this.currentUserId,
      user_type: 'patient',
      user_name: 'Paciente'
    });
  }

  sendMessage(content, messageType = 'text') {
    if (!content?.trim()) return;

    if (!this.isConnected || !this.sessionJoined) {
      this.showError('No conectado al chat');
      return;
    }

    const payload = {
      session_id: this.currentSessionId,
      user_id: this.currentUserId,
      user_type: 'patient',
      user_name: 'Paciente',
      message_type: messageType,
      content: content.trim()
    };

    // ✅ TRACKEAR MENSAJE ENVIADO PARA DETECTARLO CUANDO REGRESE
    this.lastSentMessage = {
      content: content.trim(),
      timestamp: Date.now()
    };

    console.log('📤 [Paciente] Enviando mensaje:', { ...payload, user_id: payload.user_id.slice(0, 15) + '…' });

    this.socket.emit('send_message', payload, (response) => {
      console.log('📨 [Paciente] Respuesta del servidor:', response);
      
      if (response && response.success) {
        console.log('✅ Mensaje del paciente enviado exitosamente');
      } else {
        console.error('❌ Error enviando mensaje del paciente:', response?.message || 'Error desconocido');
        this.showError('Error enviando mensaje: ' + (response?.message || 'Error desconocido'));
        // Limpiar tracking si hay error
        this.lastSentMessage = null;
      }
    });
  }

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

  startTyping() {
    if (this.socket?.connected && this.sessionJoined) {
      this.socket.emit('typing', {
        session_id: this.currentSessionId,
        user_id: this.currentUserId,
        user_type: 'patient'
      });
    }
  }

  stopTyping() {
    if (this.socket?.connected && this.sessionJoined) {
      this.socket.emit('stop_typing', {
        session_id: this.currentSessionId,
        user_id: this.currentUserId,
        user_type: 'patient'
      });
    }
  }

  // ====== HANDLERS ======
  handleChatJoined(data) {
    console.log('✅ [Paciente] Chat unido:', data);
    this.sessionJoined = true;
    this.updateConnectionStatus('En chat');
    
    this.clearChatMessages();
    this.addInitialSystemMessages();
    
    setTimeout(() => {
      this.loadMessageHistory();
    }, 3000);
  }

  // ✅ SOLUCIÓN PARA BACKEND ROTO QUE ENVÍA sender_id: null
  handleNewMessage(data) {
    console.log('📨 Nuevo mensaje recibido:', data);
    
    // Normalizar datos del mensaje
    const messageData = {
      user_id: data.user_id || data.sender_id || data.author_id || data.from || data.userId,
      user_type: data.user_type || data.sender_type || data.type,
      user_name: data.user_name || data.sender_name || data.author_name || data.name || 'Usuario',
      content: data.content || data.message || '',
      timestamp: data.timestamp || data.created_at || data.time || new Date().toISOString(),
      session_id: data.session_id
    };

    // ✅ LÓGICA PARA DETECTAR MENSAJES PROPIOS AUNQUE EL BACKEND ESTÉ ROTO
    let isMine = false;
    
    // Método 1: Comparación directa si tenemos el ID
    if (messageData.user_id && messageData.user_id === this.currentUserId) {
      isMine = true;
      console.log('✅ Es mío por ID exacto');
    }
    // Método 2: Si es paciente en MI sesión y yo soy el único paciente
    else if (messageData.user_type === 'patient' && 
             messageData.session_id === this.currentSessionId &&
             this.userType === 'patient') {
      isMine = true;
      console.log('✅ Es mío porque soy el único paciente en esta sesión');
    }
    // Método 3: Detectar por timing (mensaje reciente que acabo de enviar)
    else if (this.lastSentMessage && 
             this.lastSentMessage.content === messageData.content &&
             Date.now() - this.lastSentMessage.timestamp < 3000) {
      isMine = true;
      console.log('✅ Es mío por timing y contenido');
      this.lastSentMessage = null; // Limpiar para evitar false positives
    }

    console.log('📨 ¿Es mío?:', isMine);
    this.addMessageToUI(messageData, isMine);
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
    if (data.user_type === 'agent' && data.user_id !== this.currentUserId) {
      this.showTypingIndicator();
    }
  }

  handleUserStopTyping(data) {
    if (data.user_type === 'agent' && data.user_id !== this.currentUserId) {
      this.hideTypingIndicator();
    }
  }

  handleError(data) {
    console.error('❌ Error del chat:', data);
    this.showError(data.message || 'Error en el chat');
  }

  // ✅ CARGAR HISTORIAL CON LÓGICA CORREGIDA
  async loadMessageHistory() {
    if (!this.currentSessionId) return;

    try {
      console.log('📚 Cargando historial...');
      
      const response = await fetch(
        `${this.chatServiceUrl}/messages/${this.currentSessionId}?limit=50`,
        { headers: { Accept: 'application/json' } }
      );

      const result = await response.json();
      if (!result.success) return;

      const messages = result.data.messages || [];
      
      messages.reverse().forEach((msg) => {
        const messageData = {
          user_id: msg.user_id || msg.sender_id || msg.author_id || msg.from,
          user_type: msg.user_type || msg.sender_type || msg.type,
          user_name: msg.user_name || msg.sender_name || msg.author_name || 'Usuario',
          content: msg.content || msg.message,
          timestamp: msg.timestamp || msg.created_at,
          session_id: msg.session_id
        };

        // ✅ MISMA LÓGICA DE DETECCIÓN QUE handleNewMessage
        let isMine = false;
        
        if (messageData.user_id && messageData.user_id === this.currentUserId) {
          isMine = true;
        } else if (messageData.user_type === 'patient' && 
                   messageData.session_id === this.currentSessionId &&
                   this.userType === 'patient') {
          isMine = true;
        }
        
        this.addMessageToUI(messageData, isMine, false);
      });

      this.scrollToBottom();
    } catch (error) {
      console.error('❌ Error cargando historial:', error);
    }
  }

  // ✅ MOSTRAR MENSAJES EN UI
  addMessageToUI(messageData, isMine, scroll = true) {
    const container = document.getElementById('chatMessages');
    if (!container) return;

    const time = this.formatTime(messageData.timestamp);
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'mb-4';

    if (isMine) {
      messageDiv.innerHTML = `
        <div class="flex justify-end">
          <div class="max-w-xs lg:max-w-md bg-blue-600 text-white rounded-lg px-4 py-2">
            <div class="text-xs opacity-75 mb-1">Yo</div>
            <p>${this.formatMessage(messageData.content)}</p>
            <div class="text-xs opacity-75 mt-1">${time}</div>
          </div>
        </div>
      `;
    } else {
      const senderName = messageData.user_type === 'agent' ? 'Agente' : 
                        messageData.user_type === 'system' ? 'Sistema' : 
                        messageData.user_name || 'Usuario';
      messageDiv.innerHTML = `
        <div class="flex justify-start">
          <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
            <div class="text-xs font-medium text-gray-600 mb-1">${senderName}</div>
            <p>${this.formatMessage(messageData.content)}</p>
            <div class="text-xs text-gray-500 mt-1">${time}</div>
          </div>
        </div>
      `;
    }

    container.appendChild(messageDiv);

    if (scroll) {
      this.scrollToBottom();
    }
  }

  // ✅ MENSAJES DEL SISTEMA COMO AGENTE
  addSystemMessage(content) {
    const container = document.getElementById('chatMessages');
    if (!container) return;

    const messageDiv = document.createElement('div');
    messageDiv.className = 'mb-4';
    messageDiv.innerHTML = `
      <div class="flex justify-start">
        <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
          <div class="text-xs font-medium text-gray-600 mb-1">Agente</div>
          <p>${this.formatMessage(content)}</p>
          <div class="text-xs text-gray-500 mt-1">${this.formatTime(new Date().toISOString())}</div>
        </div>
      </div>
    `;

    container.appendChild(messageDiv);
    this.scrollToBottom();
  }

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

  // ✅ UTILITY METHODS
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
      if (!timestamp) return '';
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
      indicator.innerHTML = `
        <div class="flex items-center space-x-2 text-gray-500 text-sm p-3">
          <div class="flex space-x-1">
            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s;"></div>
            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
          </div>
          <span>El agente está escribiendo...</span>
        </div>
      `;
      this.scrollToBottom();
    }
  }

  hideTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
      indicator.classList.add('hidden');
      indicator.innerHTML = '';
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
    this.sessionJoined = false;
    
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

// ✅ CREAR INSTANCIA GLOBAL
window.chatClient = new ChatClient();

// ✅ EVENT LISTENERS
document.addEventListener('DOMContentLoaded', function() {
  const messageInput = document.getElementById('messageInput');
  
  if (messageInput) {
    let typingTimer;
    messageInput.addEventListener('input', function() {
      if (window.chatClient && window.chatClient.socket && window.chatClient.sessionJoined) {
        window.chatClient.startTyping();
        
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
          window.chatClient.stopTyping();
        }, 1000);
      }
    });
    
    messageInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        clearTimeout(typingTimer);
        if (window.chatClient) {
          window.chatClient.stopTyping();
        }
      }
    });
  }
});

// ✅ FUNCIONES GLOBALES
function sendMessage() {
  if (!window.chatClient) {
    alert('Error: ChatClient no está cargado');
    return;
  }
  
  const messageInput = document.getElementById('messageInput');
  if (!messageInput) return;
  
  const message = messageInput.value.trim();
  if (!message) return;
  
  window.chatClient.stopTyping();
  window.chatClient.sendMessage(message);
  
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
    if (window.authClient) {
      window.authClient.showError('Archivo muy grande (máximo 10MB)');
    } else {
      alert('Archivo muy grande (máximo 10MB)');
    }
    return;
  }
  
  if (window.chatClient) {
    window.chatClient.uploadFile(file);
  }
}

window.sendMessage = sendMessage;
window.handleFileUpload = handleFileUpload;

console.log('💬 ChatClient ARREGLADO - Backend roto solucionado ✅');