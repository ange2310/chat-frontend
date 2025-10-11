class ChatClient {
  constructor() {
    this.chatServiceUrl = 'http://187.33.158.246/chat';
    this.wsUrl = 'http://187.33.158.246';
    this.fileServiceUrl = 'http://187.33.158.246/chat/files';
    
    this.socket = null;
    this.isConnected = false;
    this.isAuthenticated = false;
    this.debug = true;
    this.currentRoom = null;
    this.currentSessionId = null;
    this.currentUserId = null;
    this.userType = 'patient';
    this.sessionJoined = false;
    this.lastSentMessage = null;
    this.myUploadedFiles = new Set();
    this.uploadingFiles = new Set();
    this.lastFileUploadTime = 0;
  }

  
  async connect(pToken, roomId = 'general', userName = 'Paciente') {
    try {
      const sessionData = await this.createSimpleSession(pToken, roomId, userName);
      this.currentSessionId = sessionData.session_id;
      this.currentUserId = pToken;
      this.currentRoom = roomId;
      
      await this.connectWebSocket();
      this.joinChat();
      
      return sessionData;
      
    } catch (error) {
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
          ptoken: pToken,
          user_name: userName,
          room_id: roomId,
          force_new: true
        })
      });

      const result = await response.json();

      if (response.ok && result.success) {
        this.updateConnectionStatus('Sesión creada');
        
        // Configurar websocketUrl desde la respuesta del backend
        if (result.data.websocket_url) {
          this.websocketUrl = 'ws://187.33.158.246';
        }
        
        return result.data;
      } else {
        throw new Error(result.message || 'Error creando sesión');
      }

      } catch (error) {
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
          path: '/chat/socket.io/',  
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
          this.isConnected = true;
          this.updateConnectionStatus('Conectado');
          resolve();
        });
        
        this.socket.on('disconnect', (reason) => {
          this.isConnected = false;
          this.sessionJoined = false;
          this.updateConnectionStatus('Desconectado');
          
          if (reason !== 'io client disconnect') {
            this.showError('Conexión perdida: ' + reason);
          }
        });
        
        this.socket.on('connect_error', (error) => {
          this.isConnected = false;
          this.updateConnectionStatus('Error de conexión');
          reject(error);
        });

        this.socket.on('reconnect', (attemptNumber) => {
          this.updateConnectionStatus('Reconectado');
          
          if (this.currentSessionId) {
            setTimeout(() => this.joinChat(), 500);
          }
        });

        this.setupSocketEventHandlers();
        
        const timeoutId = setTimeout(() => {
          if (!this.isConnected) {
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
    this.socket.on('user_typing', (data) => this.handleUserTyping(data));
    this.socket.on('user_stop_typing', (data) => this.handleUserStopTyping(data));
    this.socket.on('file_uploaded', (data) => this.handleFileUploaded(data));
    this.socket.on('session_terminated', (data) => this.handleSessionTerminated(data));
    this.socket.on('terminate_session', (data) => this.handleSessionTerminated(data));

    setTimeout(() => {
        this.setupDebugging();
    }, 1000);
  }

  async handleFileUploaded(data) {
    try {
      if (data.session_id !== this.currentSessionId) {
        return;
      }

      console.log('📁 Paciente - Archivo recibido por WebSocket:', {
        fileName: data.file_name,
        fileId: data.file_id,
        uploaderId: data.uploader_id || data.uploaded_by || data.user_id,
        uploaderType: data.uploader_type || data.user_type,
        currentUserId: this.currentUserId,
        isMyUpload: this.isMyUploadedFile(data)
      });

      const isMyFile = this.isMyUploadedFile(data);
      
      if (isMyFile) {
        console.log('✅ Ignorando mi propio archivo');
        return;
      }
      
      console.log('📥 Procesando archivo del agente');
      
      const fileData = {
        id: data.file_id,
        original_name: data.file_name,
        file_size: data.file_size,
        file_type: data.file_type,
        mime_type: data.mime_type,
        download_url: data.download_url || `${this.fileServiceUrl}/download/${data.file_id}`,
        preview_url: `${this.fileServiceUrl}/preview/${data.file_id}`
      };
      
      await this.addFileMessageToChat(data.file_name, fileData, false);
      
      if (window.authClient && window.authClient.showNotification) {
        window.authClient.showNotification(`${data.uploader_name || 'Agente'} envió un archivo: ${data.file_name}`, 'info');
      }
      
      this.playNotificationSound();
      
    } catch (error) {
      console.error('Error procesando archivo recibido:', error);
    }
  }

  isMyUploadedFile(data) {
    if (data.file_id && this.myUploadedFiles.has(data.file_id)) {
      return true;
    }
    
    if (data.file_name && this.myUploadedFiles.has(data.file_name)) {
      return true;
    }
    
    if (data.uploader_id && data.uploader_id === this.currentUserId) {
      return true;
    }
    
    if (data.user_id && data.user_id === this.currentUserId) {
      return true;
    }
    
    const timeSinceLastUpload = Date.now() - this.lastFileUploadTime;
    if (timeSinceLastUpload < 10000) {
      return true;
    }
    
    const fileKey = data.file_name + '_' + data.file_size;
    if (this.uploadingFiles.has(fileKey)) {
      return true;
    }
    
    return false;
  }

  joinChat() {
    if (!this.socket || !this.socket.connected) {
      return;
    }

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

    this.lastSentMessage = {
      content: content.trim(),
      timestamp: Date.now()
    };

    this.socket.emit('send_message', payload, (response) => {
      if (response && response.success) {
        // Mensaje enviado exitosamente
      } else {
        this.showError('Error enviando mensaje: ' + (response?.message || 'Error desconocido'));
        this.lastSentMessage = null;
      }
    });
  }

  async uploadFile(file, description = '') {
    if (!file || !this.currentSessionId) {
      throw new Error('Faltan datos básicos para upload');
    }
    
    try {
      let userIdToSend = this.currentUserId;
      const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
      
      if (!uuidRegex.test(this.currentUserId)) {
        userIdToSend = this.generateSimpleUUID(this.currentUserId);
      }

      const uploadId = `upload_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
      
      this.uploadingFiles.add(file.name + '_' + file.size);
      this.lastFileUploadTime = Date.now();

      const formData = new FormData();
      formData.append('file', file);
      formData.append('session_id', this.currentSessionId);
      formData.append('user_id', userIdToSend);
      formData.append('upload_id', uploadId);
      if (description) formData.append('description', description);
      
      const response = await fetch(`${this.fileServiceUrl}/upload`, {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (response.ok && result.success) {
        const fileData = result.data.file || result.data;
        
        if (fileData && (fileData.id || fileData.file_id)) {
          const fileId = fileData.id || fileData.file_id;
          this.myUploadedFiles.add(fileId);
          this.myUploadedFiles.add(fileData.original_name || fileData.file_name || file.name);
        }

        const completeFileData = {
          id: fileData?.id || fileData?.file_id,
          original_name: fileData?.original_name || fileData?.file_name || file.name,
          file_size: fileData?.file_size || fileData?.size || file.size,
          file_type: fileData?.file_type || fileData?.type || file.type,
          download_url: fileData?.download_url,
          ...fileData
        };

        await this.addFileMessageToChat(
          completeFileData.original_name, 
          completeFileData, 
          true,
          uploadId
        );
        
        return fileData;
        
      } else {
        let errorMsg = 'Error subiendo archivo';
        if (result.errors && Array.isArray(result.errors)) {
          errorMsg = result.errors.join(', ');
        } else if (result.message) {
          errorMsg = result.message;
        }
        
        throw new Error(errorMsg);
      }
      
    } catch (error) {
      throw error;
    } finally {
      this.uploadingFiles.delete(file.name + '_' + file.size);
    }
  }

  generateSimpleUUID(input) {
    let hash = 0;
    if (input.length === 0) return '00000000-0000-4000-8000-000000000000';
    
    for (let i = 0; i < input.length; i++) {
      const char = input.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash;
    }
    
    hash = Math.abs(hash);
    const hex = hash.toString(16).padStart(8, '0');
    const uuid = `${hex.slice(0,8)}-${hex.slice(0,4)}-4${hex.slice(1,4)}-8${hex.slice(1,4)}-${hex}${hex}`.slice(0,36);
    
    return uuid.toLowerCase();
  }

  isValidForUpload(file) {
    if (!file) return false;
    if (file.size > 10 * 1024 * 1024) return false;
    if (!this.currentSessionId) return false;
    if (!this.currentUserId) return false;
    return true;
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

  handleChatJoined(data) {
    this.sessionJoined = true;
    this.updateConnectionStatus('En chat');
    
    this.clearChatMessages();
    this.addInitialSystemMessages();
    
    this.myUploadedFiles.clear();
    this.uploadingFiles.clear();
    this.lastFileUploadTime = 0;
    
    setTimeout(() => {
      this.loadMessageHistory();
    }, 3000);
  }

  async handleNewMessage(data) {
    const messageData = {
      user_id: data.user_id || data.sender_id || data.author_id || data.from || data.userId,
      user_type: data.user_type || data.sender_type || data.type,
      user_name: data.user_name || data.sender_name || data.author_name || data.name || 'Usuario',
      content: data.content || data.message || '',
      message_type: data.message_type || data.type || 'text',
      timestamp: data.timestamp || data.created_at || data.time || new Date().toISOString(),
      session_id: data.session_id
    };

    if (messageData.message_type === 'file_upload' || 
        messageData.content.includes('📎') ||
        (data.file_data && data.file_data.id)) {
      
      const isMyFile = this.isMyUploadedFile(data) || 
                       this.isMyUploadedFile(messageData) ||
                       (data.file_data && this.myUploadedFiles.has(data.file_data.id));
      
      if (isMyFile) {
        return;
      }
      
      if (data.file_data) {
        const fileData = {
          id: data.file_data.id,
          original_name: data.file_data.original_name || data.file_data.name,
          file_size: data.file_data.file_size || data.file_data.size,
          mime_type: data.file_data.mime_type || data.file_data.file_type,
          download_url: data.file_data.download_url || `${this.fileServiceUrl}/download/${data.file_data.id}`
        };
        
        await this.addFileMessageToChat(fileData.original_name, fileData, false);
        return;
      }
    }

    let isMine = false;
    
    if (messageData.user_id && messageData.user_id === this.currentUserId) {
      isMine = true;
    }
    else if (messageData.user_type === 'patient' && 
             messageData.session_id === this.currentSessionId &&
             this.userType === 'patient') {
      isMine = true;
    }
    else if (this.lastSentMessage && 
             this.lastSentMessage.content === messageData.content &&
             Date.now() - this.lastSentMessage.timestamp < 3000) {
      isMine = true;
      this.lastSentMessage = null;
    }

    this.addMessageToUI(messageData, isMine);
  }

  handleMessageSent(data) {
    // Mensaje enviado confirmado
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
    if (data.message && data.message.includes('Error enviando mensaje')) {
      return;
    }
    
    this.showError(data.message || 'Error en el chat');
  }

  async loadMessageHistory() {
    if (!this.currentSessionId) return;

    try {
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
      console.error('Error cargando historial:', error);
    }
  }

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

  addSystemMessage(content) {
    const container = document.getElementById('chatMessages');
    if (!container) return;

    const messageDiv = document.createElement('div');
    messageDiv.className = 'mb-4';
    messageDiv.innerHTML = `
      <div class="flex justify-start">
        <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
          <div class="text-xs font-medium text-gray-600 mb-1">Sistema</div>
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

  // ✅ FUNCIÓN MODIFICADA PARA USAR SOLO PREVIEW
  addFileMessageToChat(fileName, fileData, isMine = true, uploadId = null) {
    const container = document.getElementById('chatMessages');
    if (!container) return;

    // Evitar duplicados
    const existingMessages = container.querySelectorAll('.mb-4');
    for (let msg of existingMessages) {
      const existingFileName = msg.querySelector('.font-medium')?.textContent?.trim();
      const isExistingMine = msg.querySelector('.bg-blue-600') !== null;
      
      if (existingFileName === fileName && isExistingMine === isMine) {
        return;
      }
    }

    const time = new Date().toLocaleTimeString('es-ES', { 
      hour: '2-digit', 
      minute: '2-digit' 
    });
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'mb-4';
    
    if (uploadId) {
      messageDiv.setAttribute('data-upload-id', uploadId);
    }
    
    if (fileData && (fileData.id || fileData.file_id)) {
      messageDiv.setAttribute('data-file-id', fileData.id || fileData.file_id);
    }

    // ✅ CONSTRUIR URL DE PREVIEW ÚNICAMENTE
    let previewUrl = '#';
    let fileSize = null;
    let fileId = null;

    if (fileData) {
      fileId = fileData.id || fileData.file_id || fileData.fileId;
      fileSize = fileData.file_size || fileData.size || fileData.fileSize;
      
      if (fileId) {
        previewUrl = `${this.fileServiceUrl}/preview/${fileId}`;
        console.log('📎 URL de preview construida:', previewUrl);
      } else if (fileData.preview_url) {
        previewUrl = fileData.preview_url;
        console.log('📎 URL de preview desde preview_url:', previewUrl);
      } else if (fileData.download_url) {
        // Convertir download URL a preview URL si existe
        previewUrl = fileData.download_url.replace('/download/', '/preview/');
        console.log('📎 URL convertida a preview:', previewUrl);
      }
    }

    // ✅ VERIFICAR SI SE PUEDE PREVISUALIZAR
    const canPreview = canPreviewFile(fileName, fileData?.file_type || fileData?.mime_type);
    const hasValidPreviewUrl = previewUrl !== '#' && !previewUrl.includes('undefined') && !previewUrl.includes('null');
    const showPreviewButton = canPreview && hasValidPreviewUrl;

    console.log('🔍 Verificación de preview:', {
      fileName: fileName,
      canPreview: canPreview,
      hasValidPreviewUrl: hasValidPreviewUrl,
      showPreviewButton: showPreviewButton,
      previewUrl: previewUrl
    });

    if (isMine) {
      // ✅ ARCHIVO DEL PACIENTE - LADO DERECHO (AZUL) CON BOTÓN "VER"
      const previewButton = showPreviewButton ? 
        `<button onclick="openFileInNewTab('${previewUrl}', '${fileName.replace(/'/g, "\\'")}')" 
                class="inline-flex items-center text-xs bg-blue-500 hover:bg-blue-400 text-white px-3 py-1.5 rounded mt-2 transition-colors"
                title="Ver ${fileName}">
          <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
          </svg>
          Ver
        </button>` : 
        `<span class="inline-flex items-center text-xs bg-gray-600 text-white px-3 py-1.5 rounded mt-2"
              title="Vista previa no disponible para este tipo de archivo">
          <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          No disponible
        </span>`;
      
      messageDiv.innerHTML = `
        <div class="flex justify-end">
          <div class="max-w-xs lg:max-w-md bg-blue-600 text-white rounded-lg px-4 py-2">
            <div class="text-xs opacity-75 mb-1">Yo</div>
            <div class="flex items-center space-x-2">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
              </svg>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-sm truncate">${fileName}</p>
                ${fileSize ? 
                  `<p class="text-xs opacity-75">${this.formatFileSize(fileSize)}</p>` : 
                  ''
                }
              </div>
            </div>
            <div class="mt-2">
              ${previewButton}
            </div>
            <div class="text-xs opacity-75 mt-1 text-right">${time}</div>
          </div>
        </div>
      `;
    } else {
      // ✅ ARCHIVO DEL AGENTE - LADO IZQUIERDO (GRIS) CON BOTÓN "VER"
      const previewButton = showPreviewButton ? 
        `<button onclick="openFileInNewTab('${previewUrl}', '${fileName.replace(/'/g, "\\'")}')" 
                class="inline-flex items-center text-xs bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded mt-2 transition-colors"
                title="Ver ${fileName}">
          <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
          </svg>
          Ver
        </button>` : 
        `<span class="inline-flex items-center text-xs bg-gray-600 text-white px-3 py-1.5 rounded mt-2"
              title="Vista previa no disponible para este tipo de archivo">
          <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          No disponible
        </span>`;

      messageDiv.innerHTML = `
        <div class="flex justify-start">
          <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
            <div class="text-xs font-medium text-gray-600 mb-1">Agente</div>
            <div class="flex items-center space-x-2">
              <svg class="w-4 h-4 flex-shrink-0 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
              </svg>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-sm truncate">${fileName}</p>
                ${fileSize ? 
                  `<p class="text-xs text-gray-500">${this.formatFileSize(fileSize)}</p>` : 
                  ''
                }
              </div>
            </div>
            <div class="mt-2">
              ${previewButton}
            </div>
            <div class="text-xs text-gray-500 mt-1">${time}</div>
          </div>
        </div>
      `;
    }

    container.appendChild(messageDiv);
    
    setTimeout(() => {
      container.scrollTop = container.scrollHeight;
    }, 100);
  }

  getFileIcon(fileType, mimeType) {
    const type = fileType?.toLowerCase() || '';
    const mime = mimeType?.toLowerCase() || '';
    
    if (mime.startsWith('image/')) {
      return '🖼️';
    } else if (mime === 'application/pdf') {
      return '📄';
    } else if (mime.includes('word') || mime.includes('document')) {
      return '📝';
    } else if (mime.includes('excel') || mime.includes('spreadsheet')) {
      return '📊';
    } else if (mime.includes('powerpoint') || mime.includes('presentation')) {
      return '📽️';
    } else if (mime.startsWith('audio/')) {
      return '🎵';
    } else if (mime.startsWith('video/')) {
      return '🎬';
    } else if (mime === 'text/plain') {
      return '📃';
    } else {
      return '📎';
    }
  }

  async getImagePreview(fileId) {
    try {
      const response = await fetch(`${this.fileServiceUrl}/to-base64/${fileId}`);
      const result = await response.json();
      
      if (response.ok && result.success && result.data.base64_content) {
        return `data:${result.data.mime_type};base64,${result.data.base64_content}`;
      }
    } catch (error) {
      console.error('Error obteniendo preview:', error);
    }
    return null;
  }

  formatFileSize(bytes) {
    if (!bytes) return '';
    
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
  }

  formatRelativeTime(timestamp) {
    try {
      const now = new Date();
      const date = new Date(timestamp);
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / (1000 * 60));
      
      if (diffMins < 1) return 'ahora';
      if (diffMins < 60) return `hace ${diffMins} min`;
      
      const diffHours = Math.floor(diffMins / 60);
      if (diffHours < 24) return `hace ${diffHours}h`;
      
      const diffDays = Math.floor(diffHours / 24);
      if (diffDays < 7) return `hace ${diffDays}d`;
      
      return date.toLocaleDateString('es-ES', { 
        day: '2-digit', 
        month: '2-digit' 
      });
    } catch (error) {
      return '';
    }
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
  }

  showError(message) {
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

  handleSessionTerminated(data) {
    console.log('🔚 Sesión terminada recibida:', data);
    
    if (data.session_id === this.currentSessionId) {
        const terminatedBy = data.terminated_by || 'unknown';
        
        if (terminatedBy === 'agent') {
            // Mostrar modal específico cuando el agente termina la sesión
            this.showAgentTerminatedModal(data);
        } else if (terminatedBy === 'system') {
            this.showSystemTerminatedModal(data);
        } else {
            // Terminación general
            this.showSessionEndedScreen(terminatedBy);
        }
        
        // Deshabilitar controles del chat
        this.disableChat();
        this.updateConnectionStatus('Sesión finalizada');
        
        console.log('🔒 Sesión finalizada completamente');
    }
}

showAgentTerminatedModal(data) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md mx-4 text-center">
            <div class="mb-4">
                <svg class="w-16 h-16 text-blue-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-4">Conversación Finalizada</h2>
            <p class="text-gray-600 mb-6">
                El agente ha finalizado esta conversación. 
                ${data.reason ? `Motivo: ${data.reason}` : ''}
            </p>
            <div class="space-y-3">
                <button onclick="window.location.reload()" 
                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Iniciar Nueva Conversación
                </button>
                <button onclick="this.closest('.fixed').remove()" 
                        class="w-full px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Cerrar
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Auto-cerrar después de 10 segundos
    setTimeout(() => {
        if (modal.parentNode) {
            modal.remove();
        }
    }, 10000);
}


disableChat() {
    console.log('🔒 Deshabilitando controles del chat...');
    
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    const uploadButton = document.querySelector('button[onclick*="fileInput"]');
    const fileInput = document.getElementById('fileInput');
    
    // Deshabilitar input de mensaje
    if (messageInput) {
        messageInput.disabled = true;
        messageInput.placeholder = 'La conversación ha finalizado';
        messageInput.style.backgroundColor = '#f3f4f6';
        messageInput.style.color = '#6b7280';
    }
    
    // Deshabilitar botón de envío
    if (sendButton) {
        sendButton.disabled = true;
        sendButton.innerHTML = '❌ Finalizada';
        sendButton.className = sendButton.className.replace(/bg-blue-\d+/, 'bg-gray-400');
        sendButton.style.cursor = 'not-allowed';
    }
    
    // Deshabilitar subida de archivos
    if (uploadButton) {
        uploadButton.disabled = true;
        uploadButton.style.opacity = '0.5';
        uploadButton.style.cursor = 'not-allowed';
        uploadButton.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636"></path>
            </svg>
            <span>Chat finalizado</span>
        `;
    }
    
    if (fileInput) {
        fileInput.disabled = true;
    }
    
    // Ocultar indicador de escritura si está visible
    this.hideTypingIndicator();
    
    console.log('✅ Controles del chat deshabilitados');
}
showSessionEndedScreen(terminatedBy) {
    const container = document.getElementById('chatMessages');
    if (!container) return;
    
    // Crear elemento de finalización
    const endedDiv = document.createElement('div');
    endedDiv.className = 'mb-4 mt-8';
    endedDiv.innerHTML = `
        <div class="flex justify-center">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-6 py-4 max-w-md text-center">
                <div class="flex items-center justify-center mb-3">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-yellow-800 mb-2">
                    Conversación Finalizada
                </h3>
                <p class="text-sm text-yellow-700 mb-4">
                    ${terminatedBy === 'agent' ? 
                        'El agente ha finalizado esta conversación. Gracias por contactarnos.' : 
                        'Esta conversación ha sido finalizada.'}
                </p>
                <div class="text-xs text-yellow-600">
                    ${new Date().toLocaleString('es-ES')}
                </div>
                <div class="mt-4 pt-3 border-t border-yellow-200">
                    <p class="text-xs text-yellow-600">
                        Para una nueva consulta, inicia una nueva conversación
                    </p>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(endedDiv);
    this.scrollToBottom();
}

disconnect() {
    if (this.debug) console.log('🚪 PACIENTE: Iniciando desconexión...', {
        connected: this.isConnected,
        sessionId: this.currentSessionId,
        socketConnected: this.socket?.connected
    });
    
    if (this.socket && this.socket.connected && this.currentSessionId) {
        if (this.debug) console.log('📤 PACIENTE: Enviando eventos de terminación...');
        
        // Evento 1: terminate_session (tu evento existente)
        this.socket.emit('terminate_session', {
            session_id: this.currentSessionId,
            terminated_by: 'patient',
            reason: 'patient_ended_chat',
            message: 'El paciente ha finalizado la conversación',
            timestamp: new Date().toISOString()
        });
        
        if (this.debug) console.log('✅ PACIENTE: terminate_session emitido');
        
        // Evento 2: Evento simple para debugging
        this.socket.emit('patient_leaving', {
            session_id: this.currentSessionId,
            message: 'Paciente se está desconectando'
        });
        
        if (this.debug) console.log('✅ PACIENTE: patient_leaving emitido');
        
        // Dar tiempo para que se envíen
        setTimeout(() => {
            this.finalizeDisconnect();
        }, 2000); // Aumentado a 2 segundos
    } else {
        if (this.debug) console.log('⚠️ PACIENTE: No hay conexión activa');
        this.finalizeDisconnect();
    }
}

finalizeDisconnect() {
    console.log('🔚 Finalizando desconexión del paciente...');
    
    this.isConnected = false;
    this.sessionJoined = false;
    
    if (this.socket) {
        this.socket.disconnect();
        this.socket = null;
    }
    
    // Limpiar datos de sesión
    this.currentSessionId = null;
    this.currentUserId = null;
    this.currentRoom = null;
    this.lastSentMessage = null;
    
    // Limpiar archivos
    this.myUploadedFiles.clear();
    this.uploadingFiles.clear();
    
    this.updateConnectionStatus('Desconectado');
    
    console.log('✅ Desconexión del paciente completada');
}
finishChat() {
    if (!this.currentSessionId) {
        showNotification('No hay sesión activa para finalizar', 'warning');
        return;
    }
    
    // Confirmar con el usuario
    if (!confirm('¿Estás seguro de que deseas finalizar esta conversación?')) {
        return;
    }
    
    console.log('✋ Paciente finalizando chat voluntariamente');
    
    // Mostrar mensaje de despedida en el chat
    this.addSystemMessage('Finalizando conversación...');
    
    // Proceder con la desconexión
    this.disconnect();
    
    // Mostrar notificación de confirmación
    showNotification('👋 Conversación finalizada. ¡Gracias por contactarnos!', 'success', 6000);
}

setupDisconnectionHandlers() {
    // Detectar cierre de ventana/pestaña
    window.addEventListener('beforeunload', (event) => {
        if (this.isConnected && this.currentSessionId && this.socket && this.socket.connected) {
            console.log('🚪 Ventana cerrándose, notificando al agente...');
            
            // Notificación síncrona para beforeunload
            this.socket.emit('terminate_session', {
                session_id: this.currentSessionId,
                terminated_by: 'patient',
                reason: 'page_closed',
                message: 'El paciente cerró la ventana',
                patient_initiated: true,
                timestamp: new Date().toISOString()
            });
            
            // También notificar para actualización de listas
            this.socket.emit('session_ended_by_patient', {
                session_id: this.currentSessionId,
                user_id: this.currentUserId,
                action: 'page_closed',
                timestamp: new Date().toISOString()
            });
        }
    });
    
    console.log('👂 Handlers de desconexión mejorados configurados');
}

  getChatStats() {
    return {
      isConnected: this.isConnected,
      currentSessionId: this.currentSessionId,
      currentUserId: this.currentUserId ? this.currentUserId.substring(0, 15) + '...' : null,
      socketConnected: this.socket ? this.socket.connected : false,
      chatServiceUrl: this.chatServiceUrl,
      websocketUrl: this.websocketUrl,
      fileServiceUrl: this.fileServiceUrl,
      lastFileUploadTime: this.lastFileUploadTime
    };
  }

  cleanDuplicateFiles() {
    const container = document.getElementById('chatMessages');
    if (!container) return;
    
    const allMessages = container.querySelectorAll('.mb-4');
    const seen = new Map();
    let removedCount = 0;
    
    allMessages.forEach(msg => {
      const fileName = msg.querySelector('.file-name')?.textContent?.trim();
      const isOwn = msg.querySelector('.bg-blue-600') !== null;
      
      if (fileName) {
        const key = `${fileName}_${isOwn}`;
        if (seen.has(key)) {
          msg.remove();
          removedCount++;
        } else {
          seen.set(key, true);
        }
      }
    });
    
    if (removedCount > 0) {
      console.log(`Limpieza completada. ${removedCount} duplicados removidos`);
    }
  }

setupDebugging() {
    if (!this.debug) return;
    
    console.log('🔧 PACIENTE: Activando debugging...');
    
    if (this.socket) {
        // Listener para todos los eventos recibidos
        this.socket.onAny = this.socket.onAny || function(fn) {
            const originalEmit = this.emit;
            this.emit = function(event, ...args) {
                console.log('📤 PACIENTE ENVIANDO:', event, args);
                return originalEmit.call(this, event, ...args);
            };
        };
        
        // Override del emit para debugging
        const originalEmit = this.socket.emit.bind(this.socket);
        this.socket.emit = (...args) => {
            console.log('📤 PACIENTE EMIT:', args);
            return originalEmit(...args);
        };
        
        // Agregar listener para eventos del agente
        this.socket.on('terminate_session', (data) => {
            console.log('🔚 PACIENTE: terminate_session recibido del agente:', data);
            
            if (data.terminated_by === 'agent') {
                console.log('👨‍💼 PACIENTE: Agente terminó la sesión');
                
                // Alert simple para debugging
                alert(`DEBUGGING: Agente terminó la sesión. Motivo: ${data.reason || 'No especificado'}`);
                
                // Llamar al handler existente
                this.handleSessionTerminated(data);
            }
        });
        
        console.log('PACIENTE: Debugging configurado');
    }
 }
}
window.chatClient = new ChatClient();

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

function showNotification(message, type = 'info', duration = 4000) {
    if (window.authClient && window.authClient.showNotification) {
        window.authClient.showNotification(message, type, duration);
    } else {
        console.log(`[${type.toUpperCase()}] ${message}`);
        // Crear notificación visual simple si no hay authClient
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm text-white ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 
            type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
        }`;
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-xl">×</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    }
}
function enableSocketDebugging() {
    if (!chatSocket) {
        console.log('❌ No hay chatSocket para debugging');
        return;
    }
    
    // Listener para TODOS los eventos (catch-all)
    chatSocket.onAny((eventName, ...args) => {
        console.log('📨 AGENTE RECIBIÓ EVENTO:', eventName, args);
    });
    
    // Listeners específicos para debugging
    chatSocket.on('terminate_session', (data) => {
        console.log('🔚 AGENTE: terminate_session recibido:', data);
        
        if (data.terminated_by === 'patient') {
            console.log('👤 AGENTE: Paciente terminó la sesión');
            
            // Mostrar alerta simple para debugging
            alert(`DEBUGGING: Paciente terminó sesión ${data.session_id}`);
            
            // Mostrar notificación
            showNotification('El paciente ha finalizado la conversación', 'info', 5000);
            
            // Si es mi sesión actual
            if (data.session_id === currentSession?.id) {
                console.log('🎯 AGENTE: Es mi sesión actual, desconectando...');
                setTimeout(() => {
                    disconnectFromCurrentSession();
                }, 2000);
            }
        }
    });
    
    chatSocket.on('patient_leaving', (data) => {
        console.log('👋 AGENTE: patient_leaving recibido:', data);
        alert(`DEBUGGING: Evento patient_leaving recibido para ${data.session_id}`);
    });
    
    console.log('🔧 AGENTE: Debugging de socket activado');
}

function openFileInNewTab(url, fileName) {
    console.log('🔍 Paciente abriendo vista previa:', { url, fileName });
    
    if (!url || url === '#' || url.includes('undefined')) {
        showNotification('No se puede mostrar la vista previa de este archivo', 'error');
        return;
    }
    
    // Verificar que la URL sea válida
    try {
        new URL(url, window.location.origin);
    } catch (error) {
        console.error('URL inválida:', url, error);
        showNotification('URL de archivo inválida: ' + url, 'error');
        return;
    }
    
    try {
        console.log('🚀 Abriendo ventana para:', url);
        
        // Abrir en nueva pestaña para previsualización
        const newWindow = window.open(url, '_blank', 'noopener,noreferrer');
        
        if (newWindow) {
            newWindow.focus();
            showNotification(`Abriendo vista previa de ${fileName}`, 'info', 2000);
        } else {
            // Fallback si el popup fue bloqueado
            console.log('📎 Popup bloqueado, usando fallback');
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showNotification(`Vista previa de ${fileName} abierta`, 'info', 2000);
        }
        
    } catch (error) {
        console.error('Error abriendo archivo:', error);
        showNotification('Error abriendo archivo: ' + error.message, 'error');
    }
}

// FUNCIÓN GLOBAL PARA VERIFICAR SI SE PUEDE PREVISUALIZAR
function canPreviewFile(fileName, fileType) {
    if (!fileName) return false;
    
    const fileName_lower = fileName.toLowerCase();
    
    // Verificar por extensión de archivo
    if (fileName_lower.match(/\.(pdf|jpg|jpeg|png|gif|bmp|webp|txt|csv|json|xml|log|html|md)$/)) {
        return true;
    }
    
    // Verificar por tipo MIME
    if (fileType) {
        const previewableTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
            'application/pdf',
            'text/plain', 'text/csv', 'text/html', 'text/markdown',
            'application/json', 'application/xml'
        ];
        
        return previewableTypes.includes(fileType.toLowerCase());
    }
    
    return false;
}

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
  
  if (!window.chatClient) {
    alert('Error: Chat no está listo');
    return;
  }
  
  const file = files[0];
  
  if (file.size > 10 * 1024 * 1024) {
    const sizeMB = Math.round(file.size / 1024 / 1024 * 100) / 100;
    alert(`Archivo muy grande: ${sizeMB}MB (máximo 10MB)`);
    return;
  }
  
  if (!window.chatClient.currentSessionId) {
    alert('Error: No hay sesión activa');
    return;
  }
  
  if (!window.chatClient.isConnected || !window.chatClient.sessionJoined) {
    alert('Error: Chat no está conectado. Inténtalo de nuevo.');
    return;
  }
  
  const fileInput = document.getElementById('fileInput');
  const uploadButton = document.querySelector('button[onclick*="fileInput"]') || 
                       document.getElementById('uploadButton');
  
  if (uploadButton) {
    uploadButton.disabled = true;
    uploadButton.innerHTML = `
      <svg class="w-4 h-4 animate-spin mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
      </svg>
      <span>Subiendo...</span>
    `;
  }
  
  if (fileInput) {
    fileInput.disabled = true;
  }
  
  window.addEventListener('beforeunload', function(event) {
    if (window.chatClient && window.chatClient.isConnected && window.chatClient.currentSessionId) {
        // Notificar al agente de manera síncrona
        if (window.chatClient.socket && window.chatClient.socket.connected) {
            window.chatClient.socket.emit('terminate_session', {
                session_id: window.chatClient.currentSessionId,
                terminated_by: 'patient',
                reason: 'page_closed',
                message: 'El paciente cerró la ventana'
            });
        }
    }
});

// Detectar cuando el usuario navega fuera de la página
window.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'hidden' && window.chatClient && window.chatClient.isConnected) {
        // El usuario cambió de pestaña o minimizó
        if (window.chatClient.socket && window.chatClient.socket.connected) {
            window.chatClient.socket.emit('patient_inactive', {
                session_id: window.chatClient.currentSessionId,
                message: 'Paciente inactivo'
            });
        }
    }
});

  window.chatClient.uploadFile(file)
    .then((result) => {
      if (fileInput) {
        fileInput.value = '';
      }
      
      const fileName = result?.original_name || result?.file_name || file.name;
      
      if (window.authClient && window.authClient.showNotification) {
        window.authClient.showNotification(`Archivo subido: ${fileName}`, 'success');
      }
      
    })
    .catch((error) => {
      if (window.authClient && window.authClient.showNotification) {
        window.authClient.showNotification('Error subiendo archivo: ' + error.message, 'error');
      } else {
        alert('Error subiendo archivo: ' + error.message);
      }
    })
    .finally(() => {
      if (uploadButton) {
        uploadButton.disabled = false;
        uploadButton.innerHTML = `
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
          </svg>
          <span>Adjuntar archivo</span>
        `;
      }
      
      if (fileInput) {
        fileInput.disabled = false;
      }
    });
}

// ✅ EXPORTAR FUNCIONES GLOBALMENTE
window.openFileInNewTab = openFileInNewTab;
window.canPreviewFile = canPreviewFile;
window.showNotification = showNotification;
window.sendMessage = sendMessage;
window.handleFileUpload = handleFileUpload;
window.cleanDuplicateFiles = () => {
  if (window.chatClient) {
    window.chatClient.cleanDuplicateFiles();
  }
};