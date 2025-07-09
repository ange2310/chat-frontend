class StaffClient {
    constructor() {
        // URLs LOCALES DIRECTAS
        this.authServiceUrl = 'http://localhost:3010';
        this.chatServiceUrl = 'http://localhost:3011';
        this.wsUrl = 'http://localhost:3011';
        
        this.currentRoom = null;
        this.currentSessionId = null;
        this.currentSession = null;
        this.rooms = [];
        this.sessionsByRoom = {};
        this.refreshInterval = null;
        
        // WebSocket para chat
        this.chatSocket = null;
        this.isConnectedToChat = false;
        this.sessionJoined = false;
        
        // TOKENS
        this.agentBearerToken = null;
        
        console.log('✅ StaffClient para desarrollo local inicializado');
        console.log('🔗 Auth Service:', this.authServiceUrl);
        console.log('💬 Chat Service:', this.chatServiceUrl);
        console.log('🔌 WebSocket:', this.wsUrl);
    }

    // ====== GESTIÓN DE TOKENS ======
    getAgentBearerToken() {
        const phpTokenMeta = document.querySelector('meta[name="staff-token"]');
        
        if (phpTokenMeta && phpTokenMeta.content && phpTokenMeta.content.trim() !== '') {
            const token = phpTokenMeta.content.trim();
            
            try {
                const parts = token.split('.');
                if (parts.length === 3) {
                    this.agentBearerToken = token;
                    return token;
                }
            } catch (e) {
                console.error('❌ Error decodificando token:', e);
            }
        }
        
        console.error('❌ NO HAY BEARER TOKEN DISPONIBLE');
        return null;
    }

    getAuthHeaders() {
        const token = this.getAgentBearerToken();
        if (!token) {
            throw new Error('Bearer token no disponible');
        }
        
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`
        };
    }

    getCurrentUser() {
        const userMeta = document.querySelector('meta[name="staff-user"]');
        if (userMeta && userMeta.content) {
            try {
                const user = JSON.parse(userMeta.content);
                console.log('🔍 [getCurrentUser] Usuario actual:', {
                    id: user.id,
                    name: user.name,
                    email: user.email,
                    role: user.role
                });
                return user;
            } catch (e) {
                console.warn('Error parsing user meta:', e);
            }
        }
        
        console.warn('⚠️ [getCurrentUser] No se pudo obtener usuario, usando fallback');
        return { 
            id: 'unknown', 
            name: 'Usuario', 
            email: 'unknown@example.com' 
        };
    }

    // ====== CARGAR SALAS ======
    async loadRoomsFromAuthService() {
        try {
            const token = this.getAgentBearerToken();
            if (!token) {
                throw new Error('No hay token disponible');
            }
            
            const url = `${this.authServiceUrl}/rooms/available`;
            const headers = this.getAuthHeaders();
            
            const response = await fetch(url, {
                method: 'GET',
                headers: headers
            });

            if (response.ok) {
                const data = await response.json();
                const rooms = data.data?.rooms || data.rooms || [];
                
                if (Array.isArray(rooms) && rooms.length > 0) {
                    this.rooms = rooms;
                    this.displayRooms();
                    console.log(`✅ ${rooms.length} salas cargadas desde servidor`);
                    return rooms;
                } else {
                    return this.loadRoomsFallback();
                }
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('❌ Error cargando salas:', error);
            this.showNotification('Error conectando con el servidor. Usando salas de prueba.', 'warning');
            return this.loadRoomsFallback();
        }
    }

    loadRoomsFallback() {
        this.rooms = [
            {
                id: 'general',
                name: 'Consultas Generales',
                description: 'Consultas generales y información básica',
                type: 'general',
                available: true,
                estimated_wait: '5-10 min',
                current_queue: 2
            },
            {
                id: 'medical',
                name: 'Consultas Médicas',
                description: 'Consultas médicas especializadas',
                type: 'medical',
                available: true,
                estimated_wait: '10-15 min',
                current_queue: 1
            },
            {
                id: 'support',
                name: 'Soporte Técnico',
                description: 'Soporte técnico y ayuda',
                type: 'support',
                available: true,
                estimated_wait: '2-5 min',
                current_queue: 0
            },
            {
                id: 'emergency',
                name: 'Emergencias',
                description: 'Atención de emergencias médicas',
                type: 'emergency',
                available: true,
                estimated_wait: '1-2 min',
                current_queue: 0
            }
        ];
        
        this.displayRooms();
        return this.rooms;
    }

    // ====== MOSTRAR SALAS ======
    displayRooms() {
        const container = document.getElementById('roomsContainer');
        if (!container) return;

        if (this.rooms.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <p class="text-gray-500 mb-4">No hay salas disponibles</p>
                    <button onclick="staffClient.loadRoomsFromAuthService()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Reintentar
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                ${this.rooms.map(room => this.createRoomCard(room)).join('')}
            </div>
        `;
    }

    createRoomCard(room) {
        const sessionsCount = this.sessionsByRoom[room.id]?.length || 0;
        const waitingCount = this.sessionsByRoom[room.id]?.filter(s => s.status === 'waiting').length || 0;
        
        return `
            <div class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-all cursor-pointer" 
                 onclick="staffClient.selectRoom('${room.id}')">
                
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 ${this.getRoomColorClass(room.type)} rounded-lg flex items-center justify-center">
                                ${this.getRoomIcon(room.type)}
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">${room.name}</h3>
                                <p class="text-sm text-gray-500">${room.type || 'General'}</p>
                            </div>
                        </div>
                        
                        ${room.available ? 
                            '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">Disponible</span>' :
                            '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">No disponible</span>'
                        }
                    </div>
                </div>

                <div class="p-6">
                    <p class="text-gray-600 text-sm mb-4">${room.description || 'Sala de atención médica'}</p>
                    
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-blue-600">${sessionsCount}</div>
                            <div class="text-xs text-gray-500">Total Sesiones</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-orange-600">${waitingCount}</div>
                            <div class="text-xs text-gray-500">En Cola</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-gray-500">
                        <div class="flex justify-between">
                            <span>Tiempo estimado:</span>
                            <span>${room.estimated_wait || '5-10 min'}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ====== SELECCIONAR SALA ======
    async selectRoom(roomId) {
        try {
            this.currentRoom = roomId;
            const room = this.rooms.find(r => r.id === roomId);
            
            if (!room) {
                this.showNotification('Sala no encontrada', 'error');
                return;
            }
            
            // Actualizar UI
            document.getElementById('currentRoomName').textContent = room.name;
            
            // Mostrar sección de sesiones
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            document.getElementById('room-sessions-section').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = `Sesiones en: ${room.name}`;
            
            // Cargar sesiones de la sala
            await this.loadSessionsByRoom(roomId);
            
        } catch (error) {
            console.error('❌ Error seleccionando sala:', error);
            this.showNotification('Error seleccionando sala: ' + error.message, 'error');
        }
    }

    // ====== CARGAR SESIONES DE UNA SALA ======
    async loadSessionsByRoom(roomId) {
        try {
            // ✅ CORRECCIÓN: Usar URL absoluta para garantizar que sea correcta
            const baseUrl = 'http://localhost:3011/chats';
            const url = `${baseUrl}/sessions?room_id=${roomId}&include_expired=false`;
            
            console.log('🔍 [loadSessionsByRoom] URL construida:', url);
            console.log('🔍 [loadSessionsByRoom] chatServiceUrl actual:', this.chatServiceUrl);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            console.log('📡 [loadSessionsByRoom] Respuesta HTTP:', response.status, response.statusText);

            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data && result.data.sessions) {
                    const processedSessions = result.data.sessions.map(session => this.processSessionData(session));
                    this.sessionsByRoom[roomId] = processedSessions;
                    this.displayRoomSessions(processedSessions, roomId);
                    console.log(`✅ ${processedSessions.length} sesiones cargadas para ${roomId}`);
                    return processedSessions;
                } else {
                    this.sessionsByRoom[roomId] = [];
                    this.displayRoomSessions([], roomId);
                    return [];
                }
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error(`❌ Error cargando sesiones para ${roomId}:`, error);
            this.sessionsByRoom[roomId] = [];
            this.displayRoomSessions([], roomId);
            this.showNotification('Error cargando sesiones: ' + error.message, 'error');
            return [];
        }
    }

    processSessionData(session) {
        return {
            id: session.id,
            room_id: session.room_id,
            status: session.status || 'waiting',
            created_at: session.created_at,
            user_data: session.user_data,
            user_id: session.user_id,
            agent_id: session.agent_id || null
        };
    }

    // ====== MOSTRAR SESIONES ======
    displayRoomSessions(sessions, roomId) {
        const container = document.getElementById('sessionsContainer');
        if (!container) return;

        if (sessions.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay sesiones activas</h3>
                    <p class="text-gray-500">Esta sala no tiene pacientes esperando atención</p>
                </div>
            `;
            return;
        }

        const html = sessions.map(session => this.createSessionCard(session)).join('');
        container.innerHTML = `<div class="space-y-4">${html}</div>`;
    }

    createSessionCard(session) {
        const patientName = this.getPatientNameFromSession(session);
        const statusColor = this.getStatusColor(session.status);
        const timeAgo = this.getTimeAgo(session.created_at);
        const currentUser = this.getCurrentUser();
        
        // Verificar si esta sesión pertenece al agente actual
        const isMySession = session.agent_id === currentUser.id;
        const canTakeSession = session.status === 'waiting';
        const canContinueSession = session.status === 'active' && isMySession;
        
        return `
            <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-lg font-semibold text-blue-700">
                                ${patientName.charAt(0).toUpperCase()}
                            </span>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900">${patientName}</h4>
                            <p class="text-sm text-gray-600">ID: ${session.id}</p>
                            <p class="text-xs text-gray-500">Creado hace ${timeAgo}</p>
                            ${isMySession ? '<p class="text-xs text-blue-600 font-medium">📋 Tu sesión</p>' : ''}
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <span class="px-3 py-1 rounded-full text-sm font-medium ${statusColor}">
                            ${this.getStatusText(session.status)}
                        </span>
                        <div class="mt-2">
                            ${canTakeSession ? 
                                `<button onclick="staffClient.takeSession('${session.id}')" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                    Tomar
                                </button>` :
                                canContinueSession ?
                                `<button onclick="staffClient.continueSession('${session.id}')" 
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                    Continuar
                                </button>` :
                                `<button class="px-4 py-2 bg-gray-300 text-gray-500 rounded-lg text-sm cursor-not-allowed" disabled>
                                    ${session.status === 'active' ? 'Ocupado' : 'No disponible'}
                                </button>`
                            }
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ====== TOMAR SESIÓN ======
    async takeSession(sessionId) {
        try {
            console.log('👤 Tomando sesión:', sessionId);
            
            const response = await fetch(`${this.chatServiceUrl}/chats/sessions/${sessionId}/assign/me`, {
                method: 'PUT',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    agent_id: this.getCurrentUser().id,
                    agent_data: {
                        name: this.getCurrentUser().name,
                        email: this.getCurrentUser().email
                    }
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                
                if (result.success) {
                    this.showNotification('Sesión asignada exitosamente', 'success');
                    
                    // Buscar la sesión y actualizar su estado
                    const session = this.findSessionById(sessionId);
                    if (session) {
                        session.status = 'active';
                        session.agent_id = this.getCurrentUser().id;
                        // Abrir el chat
                        this.openPatientChat(session);
                    }
                    
                    // Recargar sesiones de la sala actual
                    if (this.currentRoom) {
                        await this.loadSessionsByRoom(this.currentRoom);
                    }
                } else {
                    throw new Error(result.message || 'Error asignando sesión');
                }
            } else {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('❌ Error tomando sesión:', error);
            this.showNotification('Error al tomar la sesión: ' + error.message, 'error');
        }
    }

    // ====== CONTINUAR SESIÓN EXISTENTE ======
    async continueSession(sessionId) {
        try {
            const session = this.findSessionById(sessionId);
            if (!session) {
                this.showNotification('Sesión no encontrada', 'error');
                return;
            }
            
            console.log('🔄 Continuando sesión:', sessionId);
            this.openPatientChat(session);
            
        } catch (error) {
            console.error('❌ Error continuando sesión:', error);
            this.showNotification('Error al continuar la sesión: ' + error.message, 'error');
        }
    }

    // ====== ABRIR CHAT CON PACIENTE ======
    async openPatientChat(session) {
        try {
            console.log('💬 Abriendo chat para sesión:', session.id);
            
            this.currentSessionId = session.id;
            this.currentSession = session;
            
            // Mostrar panel de chat
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            document.getElementById('patient-chat-panel').classList.remove('hidden');
            
            // Actualizar UI del chat
            const patientName = this.getPatientNameFromSession(session);
            document.getElementById('chatPatientName').textContent = patientName;
            document.getElementById('chatPatientInitials').textContent = patientName.charAt(0).toUpperCase();
            document.getElementById('chatPatientId').textContent = session.id;
            
            // Limpiar mensajes anteriores
            const messagesContainer = document.getElementById('patientChatMessages');
            if (messagesContainer) {
                messagesContainer.innerHTML = '';
            }
            
            // Conectar al WebSocket como agente
            await this.connectToChatWebSocket();
            
            // Cargar historial de mensajes
            await this.loadChatHistory();
            
        } catch (error) {
            console.error('❌ Error abriendo chat:', error);
            this.showNotification('Error al abrir chat: ' + error.message, 'error');
        }
    }

    // ====== CONECTAR WEBSOCKET PARA CHAT ======
    async connectToChatWebSocket() {
        try {
            console.log('🔌 Conectando al WebSocket como agente...');
            
            // Desconectar socket anterior si existe
            if (this.chatSocket) {
                this.chatSocket.disconnect();
                this.isConnectedToChat = false;
                this.sessionJoined = false;
            }
            
            // Usar el token de autorización en la conexión WebSocket
            const token = this.getAgentBearerToken();
            const currentUser = this.getCurrentUser();
            
            this.chatSocket = io(this.wsUrl, {
                transports: ['websocket', 'polling'],
                auth: {
                    token: token,
                    user_id: currentUser.id,
                    user_type: 'agent',
                    user_name: currentUser.name,
                    session_id: this.currentSessionId
                }
            });
            
            this.chatSocket.on('connect', () => {
                console.log('✅ Socket de agente conectado');
                this.isConnectedToChat = true;
                this.updateChatStatus('Conectado');
                
                // Unirse al chat de la sesión
                setTimeout(() => {
                    this.joinChatSession();
                }, 500);
            });
            
            this.chatSocket.on('disconnect', () => {
                console.log('🔌 Socket de agente desconectado');
                this.isConnectedToChat = false;
                this.sessionJoined = false;
                this.updateChatStatus('Desconectado');
            });
            
            this.chatSocket.on('chat_joined', (data) => {
                console.log('✅ Agente unido al chat:', data);
                this.sessionJoined = true;
                this.updateChatStatus('En chat');
            });
            
            this.chatSocket.on('new_message', (data) => {
                console.log('📨 Nuevo mensaje recibido:', data);
                this.handleNewChatMessage(data);
            });
            
            this.chatSocket.on('message_sent', (data) => {
                console.log('✅ Mensaje enviado confirmado:', data);
                // Opcional: mostrar confirmación visual
            });
            
            this.chatSocket.on('user_typing', (data) => {
                if (data.user_type === 'patient' && data.user_id !== this.getCurrentUser().id) {
                    this.showPatientTyping();
                }
            });
            
            this.chatSocket.on('user_stop_typing', (data) => {
                if (data.user_type === 'patient' && data.user_id !== this.getCurrentUser().id) {
                    this.hidePatientTyping();
                }
            });
            
            this.chatSocket.on('error', (error) => {
                console.error('❌ Error en socket de chat:', error);
                this.showNotification('Error en chat: ' + (error.message || error), 'error');
            });
            
        } catch (error) {
            console.error('❌ Error conectando WebSocket de chat:', error);
            throw error;
        }
    }

    // ====== UNIRSE A LA SESIÓN DE CHAT ======
    joinChatSession() {
        if (!this.chatSocket || !this.currentSessionId || !this.isConnectedToChat) {
            console.warn('⚠️ No se puede unir al chat: socket no conectado o sesión no válida');
            return;
        }
        
        const currentUser = this.getCurrentUser();
        console.log('🏠 Agente uniéndose al chat de sesión:', this.currentSessionId);
        
        this.chatSocket.emit('join_chat', {
            session_id: this.currentSessionId,
            user_id: currentUser.id,
            user_type: 'agent',
            user_name: currentUser.name
        });
    }

    // ====== ENVIAR MENSAJE COMO AGENTE ======
    sendMessage() {
        const input = document.getElementById('agentMessageInput');
        if (!input) return;

        const message = input.value.trim();
        if (!message) return;

        if (!this.isConnectedToChat || !this.sessionJoined) {
            this.showNotification('No conectado al chat. Intentando reconectar...', 'warning');
            this.connectToChatWebSocket();
            return;
        }

        const currentUser = this.getCurrentUser();

        // ✅ CORREGIDO: Usar los nombres de campo que espera el backend
        const payload = {
            session_id: this.currentSessionId,
            user_id: currentUser.id,          // ← Cambio: usar user_id en lugar de sender_id
            user_type: 'agent',               // ← Cambio: usar user_type en lugar de sender_type
            user_name: currentUser.name,
            message_type: 'text',
            content: message
        };

        console.log('📤 [sendMessage] Enviando payload:', payload);

        // Enviar con callback para manejar confirmación
        this.chatSocket.emit('send_message', payload, (response) => {
            console.log('📨 [sendMessage] Respuesta del servidor:', response);
            
            if (response && response.success) {
                console.log('✅ Mensaje enviado exitosamente');
                // El mensaje aparecerá en la UI cuando llegue por 'new_message'
            } else {
                console.error('❌ Error enviando mensaje:', response?.message || 'Error desconocido');
                this.showNotification('Error enviando mensaje: ' + (response?.message || 'Error desconocido'), 'error');
            }
        });

        // Limpiar input inmediatamente
        input.value = '';
        const sendButton = document.getElementById('agentSendButton');
        if (sendButton) sendButton.disabled = true;
    }

    // ====== MANEJAR NUEVOS MENSAJES ======
    handleNewChatMessage(data) {
        const messagesContainer = document.getElementById('patientChatMessages');
        if (!messagesContainer) return;

        console.log('📨 [handleNewChatMessage] Procesando mensaje:', data);

        // Normalizar campos del mensaje
        const normalizedMessage = {
            user_id: data.user_id || data.sender_id,
            user_type: data.user_type || data.sender_type,
            user_name: data.user_name || data.sender_name || 'Usuario',
            content: data.content || '',
            timestamp: data.timestamp || data.created_at || Date.now(),
            message_type: data.message_type || 'text'
        };

        const currentUser = this.getCurrentUser();
        const isMyMessage = normalizedMessage.user_type === 'agent' && normalizedMessage.user_id === currentUser.id;

        // Formatear tiempo
        const time = new Date(normalizedMessage.timestamp).toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });

        const wrapper = document.createElement('div');
        wrapper.className = 'mb-4';

        if (isMyMessage) {
            // Mensaje del agente (derecha)
            wrapper.innerHTML = `
                <div class="flex justify-end">
                    <div class="max-w-xs lg:max-w-md bg-blue-600 text-white rounded-lg px-4 py-2">
                        <div class="text-xs opacity-75 mb-1">Yo (${normalizedMessage.user_name})</div>
                        <p>${this.escapeHtml(normalizedMessage.content)}</p>
                        <div class="text-xs opacity-75 mt-1">${time}</div>
                    </div>
                </div>`;
        } else {
            // Mensaje del paciente (izquierda)
            wrapper.innerHTML = `
                <div class="flex justify-start">
                    <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
                        <div class="text-xs font-medium text-gray-600 mb-1">${normalizedMessage.user_name}</div>
                        <p>${this.escapeHtml(normalizedMessage.content)}</p>
                        <div class="text-xs text-gray-500 mt-1">${time}</div>
                    </div>
                </div>`;
        }

        messagesContainer.appendChild(wrapper);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Notificación solo para mensajes de otros usuarios
        if (!isMyMessage) {
            console.log('🔔 Nuevo mensaje de paciente recibido');
            // Opcional: sonido, notificación browser, etc.
        }
    }

    // ====== CARGAR HISTORIAL DE CHAT ======
    async loadChatHistory() {
        if (!this.currentSessionId) return;
        
        try {
            console.log('📋 Cargando historial de chat para sesión:', this.currentSessionId);
            
            const response = await fetch(`${this.chatServiceUrl}/messages/${this.currentSessionId}?limit=50`, {
                headers: this.getAuthHeaders()
            });
            
            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data && result.data.messages) {
                    const messagesContainer = document.getElementById('patientChatMessages');
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '';
                        
                        console.log(`📋 Cargando ${result.data.messages.length} mensajes del historial`);
                        
                        result.data.messages.forEach(msg => {
                            this.handleNewChatMessage({
                                content: msg.content,
                                user_type: msg.sender_type || msg.user_type,
                                user_id: msg.sender_id || msg.user_id,
                                user_name: msg.sender_name || msg.user_name || 'Usuario',
                                timestamp: msg.timestamp || msg.created_at,
                                message_type: msg.message_type || 'text'
                            });
                        });
                    }
                } else {
                    console.log('📋 No hay mensajes en el historial');
                }
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
        } catch (error) {
            console.error('❌ Error cargando historial:', error);
            this.showNotification('Error cargando historial de chat', 'warning');
        }
    }

    // ====== UTILIDADES ======
    findSessionById(sessionId) {
        for (const roomSessions of Object.values(this.sessionsByRoom)) {
            const session = roomSessions.find(s => s.id === sessionId);
            if (session) return session;
        }
        return null;
    }

    getPatientNameFromSession(session) {
        if (session.user_data) {
            try {
                const userData = typeof session.user_data === 'string' 
                    ? JSON.parse(session.user_data) 
                    : session.user_data;
                
                if (userData.user_name) return userData.user_name;
                if (userData.name) return userData.name;
                if (userData.patient_data?.name) return userData.patient_data.name;
            } catch (e) {
                console.warn('Error parseando user_data:', e);
            }
        }
        return 'Paciente';
    }

    getStatusColor(status) {
        const colors = {
            'waiting': 'bg-yellow-100 text-yellow-800',
            'active': 'bg-green-100 text-green-800',
            'ended': 'bg-gray-100 text-gray-800',
            'transferred': 'bg-blue-100 text-blue-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    }

    getStatusText(status) {
        const texts = {
            'waiting': 'Esperando',
            'active': 'Activo',
            'ended': 'Finalizado',
            'transferred': 'Transferido'
        };
        return texts[status] || 'Desconocido';
    }

    getTimeAgo(timestamp) {
        const diff = Date.now() - new Date(timestamp).getTime();
        const minutes = Math.floor(diff / 60000);
        
        if (minutes < 1) return 'menos de 1 min';
        if (minutes < 60) return `${minutes} min`;
        
        const hours = Math.floor(minutes / 60);
        const remainingMins = minutes % 60;
        return `${hours}h ${remainingMins}m`;
    }

    getRoomColorClass(roomType) {
        const colors = {
            'general': 'bg-blue-100',
            'medical': 'bg-green-100',
            'support': 'bg-purple-100',
            'emergency': 'bg-red-100'
        };
        return colors[roomType] || 'bg-blue-100';
    }

    getRoomIcon(roomType) {
        const icons = {
            'general': '<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path></svg>',
            'medical': '<svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path></svg>',
            'support': '<svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364"></path></svg>',
            'emergency': '<svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
        };
        return icons[roomType] || icons['general'];
    }

    updateChatStatus(status) {
        const statusElement = document.getElementById('chatStatus');
        if (statusElement) {
            statusElement.textContent = status;
            
            // Cambiar color según el estado
            statusElement.className = 'text-sm font-medium ';
            if (status === 'En chat') {
                statusElement.className += 'text-green-600';
            } else if (status === 'Conectado') {
                statusElement.className += 'text-blue-600';
            } else {
                statusElement.className += 'text-gray-500';
            }
        }
    }

    showPatientTyping() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) {
            indicator.classList.remove('hidden');
            indicator.innerHTML = `
                <div class="flex items-center space-x-2 text-gray-500 text-sm">
                    <div class="flex space-x-1">
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s;"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
                    </div>
                    <span>El paciente está escribiendo...</span>
                </div>
            `;
        }
    }

    hidePatientTyping() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) {
            indicator.classList.add('hidden');
        }
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    showNotification(message, type = 'info', duration = 4000) {
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            info: 'bg-blue-500',
            warning: 'bg-yellow-500'
        };
        
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm text-white ${colors[type]}`;
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

    async init() {
        try {
            const token = this.getAgentBearerToken();
            if (!token) {
                this.showNotification('Error: No hay token de autenticación disponible', 'error');
                return;
            }
            
            await this.loadRoomsFromAuthService();
            console.log('✅ StaffClient inicializado exitosamente');
        } catch (error) {
            console.error('❌ Error inicializando:', error);
            this.showNotification('Error de inicialización: ' + error.message, 'error');
        }
    }

    destroy() {
        if (this.refreshInterval) clearInterval(this.refreshInterval);
        if (this.chatSocket) {
            this.chatSocket.disconnect();
            this.isConnectedToChat = false;
            this.sessionJoined = false;
        }
    }
}

// Inicializar cliente global
window.staffClient = new StaffClient();

// Listeners para el input de mensajes
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('agentMessageInput');
    const sendButton = document.getElementById('agentSendButton');
    
    if (messageInput && sendButton) {
        // Habilitar/deshabilitar botón según contenido
        messageInput.addEventListener('input', function() {
            sendButton.disabled = this.value.trim() === '';
        });
        
        // Enviar con Enter
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() && staffClient.isConnectedToChat) {
                    staffClient.sendMessage();
                }
            }
        });
        
        // Manejar indicador de typing
        let typingTimer;
        messageInput.addEventListener('input', function() {
            if (staffClient.chatSocket && staffClient.sessionJoined) {
                staffClient.chatSocket.emit('typing', {
                    session_id: staffClient.currentSessionId,
                    user_type: 'agent'
                });
                
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    staffClient.chatSocket.emit('stop_typing', {
                        session_id: staffClient.currentSessionId,
                        user_type: 'agent'
                    });
                }, 1000);
            }
        });
    }
});

console.log('🔧 StaffClient v5.0 CORREGIDO - WebSocket y Mensajería');