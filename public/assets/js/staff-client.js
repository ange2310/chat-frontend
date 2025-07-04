class StaffClient {
    constructor() {
        this.authServiceUrl = 'http://187.33.158.246:8080/auth';
        this.chatServiceUrl = 'http://187.33.158.246:8080/chats';
        this.wsUrl = 'ws://187.33.158.246:8080';
        
        this.currentRoom = null;
        this.currentSessionId = null;
        this.currentSession = null;
        this.rooms = [];
        this.sessionsByRoom = {};
        this.patientData = null;
        this.refreshInterval = null;
        this.chatSocket = null;
        this.isConnectedToChat = false;
        
        console.log('StaffClient simplificado inicializado');
    }

    getToken() {
        const phpTokenMeta = document.querySelector('meta[name="staff-token"]')?.content;
        if (phpTokenMeta && phpTokenMeta.trim() !== '') {
            return phpTokenMeta;
        }
        console.error('NO HAY TOKEN DISPONIBLE');
        return null;
    }

    getAuthHeaders() {
        const token = this.getToken();
        if (!token) throw new Error('Token no disponible');
        
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
                return JSON.parse(userMeta.content);
            } catch (e) {
                console.warn('Error parsing user meta:', e);
            }
        }
    }

    async loadRoomsFromAuthService() {
        try {
            console.log('Cargando salas...');
            
            const response = await fetch(`${this.chatServiceUrl}/rooms/available`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const data = await response.json();
                const rooms = data.data?.rooms || data.rooms || [];
                
                if (Array.isArray(rooms)) {
                    this.rooms = rooms;
                    this.displayRooms();
                    return rooms;
                }
            }
            
            throw new Error('No se pudieron cargar las salas');
            
        } catch (error) {
            console.error('Error cargando salas:', error);
            this.rooms = [
                {
                    id: 'general',
                    name: 'Consultas Generales',
                    description: 'Consultas generales y información básica',
                    type: 'general',
                    available: true,
                    estimated_wait: '5-10 min',
                    current_queue: 0
                },
                {
                    id: 'medical',
                    name: 'Consultas Médicas',
                    description: 'Consultas médicas especializadas',
                    type: 'medical',
                    available: true,
                    estimated_wait: '10-15 min',
                    current_queue: 0
                }
            ];
            this.displayRooms();
            this.showNotification('Error cargando salas. Usando salas de prueba.', 'warning');
            return this.rooms;
        }
    }

    displayRooms() {
        const roomsContainer = document.getElementById('roomsContainer');
        if (!roomsContainer) return;

        if (this.rooms.length === 0) {
            roomsContainer.innerHTML = '<div class="text-center py-12"><p class="text-gray-500">No hay salas disponibles</p></div>';
            return;
        }

        roomsContainer.innerHTML = `
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
            console.log('🎯 Seleccionando sala:', roomId);
            
            this.currentRoom = roomId;
            const room = this.rooms.find(r => r.id === roomId);
            
            if (!room) throw new Error('Sala no encontrada');

            this.showRoomSessions(room);
            await this.loadSessionsByRoom(roomId);
            
        } catch (error) {
            console.error('❌ Error seleccionando sala:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async loadSessionsByRoom(roomId) {
        try {
            console.log(`📡 Cargando sesiones para sala: ${roomId}`);
            
            const response = await fetch(`${this.chatServiceUrl}/sessions?room_id=${roomId}&include_expired=false`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data && result.data.sessions) {
                    const processedSessions = result.data.sessions.map(session => this.processSessionData(session));
                    this.sessionsByRoom[roomId] = processedSessions;
                    this.displayRoomSessions(processedSessions, roomId);
                    return processedSessions;
                }
            }
            
            this.sessionsByRoom[roomId] = [];
            this.displayRoomSessions([], roomId);
            return [];
            
        } catch (error) {
            console.error(`❌ Error cargando sesiones:`, error);
            this.showError('Error cargando sesiones');
            return [];
        }
    }

    processSessionData(sessionData) {
        const createdAt = new Date(sessionData.created_at || Date.now());
        const expiresAt = new Date(createdAt.getTime() + (30 * 60 * 1000));
        const now = new Date();
        const timeLeft = Math.max(0, expiresAt - now);
        
        return {
            ...sessionData,
            expires_at: expiresAt.toISOString(),
            time_left_ms: timeLeft,
            time_left_minutes: Math.floor(timeLeft / 60000),
            is_expired: timeLeft === 0
        };
    }

    showRoomSessions(room) {
        document.getElementById('rooms-list-section').classList.add('hidden');
        document.getElementById('room-sessions-section').classList.remove('hidden');

        const roomHeaderName = document.getElementById('currentRoomName');
        if (roomHeaderName) roomHeaderName.textContent = room.name;
    }

    displayRoomSessions(sessions, roomId) {
        const sessionsContainer = document.getElementById('sessionsContainer');
        if (!sessionsContainer) return;

        if (sessions.length === 0) {
            sessionsContainer.innerHTML = `
                <div class="text-center py-12">
                    <p class="text-gray-500 mb-4">No hay sesiones en esta sala</p>
                    <button onclick="staffClient.goBackToRooms()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Volver a Salas
                    </button>
                </div>
            `;
            return;
        }

        sessionsContainer.innerHTML = `
            <div class="space-y-4">
                ${sessions.map(session => this.createSessionCard(session)).join('')}
            </div>
        `;

        this.startCountdown();
    }

    createSessionCard(session) {
        const patientName = this.getPatientName(session);
        const roomName = this.getRoomName(session.room_id);
        const timeElapsed = this.getTimeElapsed(session.created_at);
        const isUrgent = session.time_left_minutes <= 5;
        const isExpired = session.is_expired;
        
        let statusClass = 'border-l-yellow-500';
        let statusColor = 'text-yellow-600';
        let statusText = 'En Espera';
        
        if (session.status === 'active') {
            statusClass = 'border-l-green-500';
            statusColor = 'text-green-600';
            statusText = 'Activo';
        } else if (isExpired) {
            statusClass = 'border-l-red-500';
            statusColor = 'text-red-600';
            statusText = 'Expirado';
        } else if (isUrgent) {
            statusClass = 'border-l-orange-500';
            statusColor = 'text-orange-600';
            statusText = 'Urgente';
        }
        
        return `
            <div class="bg-white rounded-lg shadow-sm border-l-4 ${statusClass} p-6 hover:shadow-md transition-all"
                 data-session-id="${session.id}">
                
                <div class="flex items-start justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-lg font-semibold text-blue-700">${this.getPatientInitials(patientName)}</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">${patientName}</h3>
                            <p class="text-sm text-gray-500">Sala: ${roomName}</p>
                            <p class="text-sm text-gray-400">Creado: ${new Date(session.created_at).toLocaleString('es-ES')}</p>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor} bg-gray-100">
                            ${statusText}
                        </span>
                        
                        <div class="mt-2 text-sm text-gray-500">
                            <div>Transcurrido: ${timeElapsed} min</div>
                            <div class="countdown-timer ${isUrgent || isExpired ? 'text-red-600 font-bold' : ''}" 
                                 data-expires="${session.expires_at}">
                                ${isExpired ? 'EXPIRADO' : `Expira en: ${session.time_left_minutes} min`}
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Agente: ${session.agent_id ? this.getAgentName(session.agent_id) : 'Sin asignar'}
                    </div>
                    
                    <div class="space-x-2">
                        ${session.status === 'waiting' && !isExpired ? 
                            `<button onclick="staffClient.takeSession('${session.id}')" 
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Tomar Chat
                            </button>` : ''
                        }
                        
                        <button onclick="staffClient.openPatientChat('${session.id}')" 
                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                            Ver Chat
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    startCountdown() {
        if (this.countdownInterval) clearInterval(this.countdownInterval);

        this.countdownInterval = setInterval(() => {
            const timers = document.querySelectorAll('.countdown-timer');
            
            timers.forEach(timer => {
                const expiresAt = new Date(timer.dataset.expires);
                const now = new Date();
                const timeLeft = Math.max(0, expiresAt - now);
                const minutesLeft = Math.floor(timeLeft / 60000);
                
                if (timeLeft === 0) {
                    timer.textContent = 'EXPIRADO';
                    timer.classList.add('text-red-600', 'font-bold');
                } else if (minutesLeft <= 5) {
                    timer.textContent = `Expira en: ${minutesLeft} min`;
                    timer.classList.add('text-red-600', 'font-bold');
                } else {
                    timer.textContent = `Expira en: ${minutesLeft} min`;
                }
            });
        }, 60000);
    }

    // ====== TOMAR SESIÓN ======
    async takeSession(sessionId) {
        try {
            console.log('👤 Tomando sesión:', sessionId);
            
            const currentUser = this.getCurrentUser();
            
            const response = await fetch(`${this.chatServiceUrl}/sessions/${sessionId}/assign/me`, {
                method: 'PUT',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    agent_id: currentUser.id,
                    agent_data: {
                        name: currentUser.name,
                        email: currentUser.email
                    }
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.showSuccess('Sesión asignada exitosamente');
                    await this.loadSessionsByRoom(this.currentRoom);
                    setTimeout(() => this.openPatientChat(sessionId), 1000);
                }
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('❌ Error tomando sesión:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== ABRIR CHAT ======
    async openPatientChat(sessionId) {
        try {
            console.log('💬 Abriendo chat para sesión:', sessionId);
            
            const session = this.findSessionById(sessionId);
            if (!session) throw new Error('Sesión no encontrada');

            this.currentSession = session;
            this.currentSessionId = sessionId;
            
            // Extraer datos del paciente
            this.extractPatientDataFromSession(session);
            
            this.showChatPanel(session);
            await this.connectToChat(session);
            
        } catch (error) {
            console.error('❌ Error abriendo chat:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // SIMPLIFICADO: extraer datos del paciente de la sesión
    extractPatientDataFromSession(session) {
        console.log('🔍 Session completa recibida:', session);
        
        this.patientData = {
            patient_name: 'Paciente',
            patient_id: session.user_id || session.id || 'N/A',
            document: 'No disponible',
            phone: 'No disponible',
            email: 'No disponible',
            city: 'No disponible',
            eps: 'No disponible',
            plan: 'No disponible',
            status: 'Activo'
        };

        // Buscar en user_data
        if (session.user_data) {
            try {
                let userData = session.user_data;
                if (typeof userData === 'string') {
                    userData = JSON.parse(userData);
                }
                console.log('📋 user_data encontrado:', userData);
                
                if (userData.nombreCompleto) this.patientData.patient_name = userData.nombreCompleto;
                if (userData.documento) this.patientData.document = userData.documento;
                if (userData.telefono) this.patientData.phone = userData.telefono;
                if (userData.email) this.patientData.email = userData.email;
                if (userData.ciudad) this.patientData.city = userData.ciudad;
                
            } catch (e) {
                console.warn('Error parseando user_data:', e);
            }
        }

        console.log('✅ Datos finales del paciente:', this.patientData);
    }

    showChatPanel(session) {
        document.getElementById('room-sessions-section').classList.add('hidden');
        document.getElementById('patient-chat-panel').classList.remove('hidden');

        this.updateChatHeader(session);
        this.displayPatientInfo();
    }

    updateChatHeader(session) {
        const patientName = this.getPatientName(session);
        const roomName = this.getRoomName(session.room_id);
        
        const elements = {
            'chatPatientName': patientName,
            'chatPatientId': `${patientName} - ${roomName}`,
            'chatRoomName': roomName,
            'chatSessionStatus': this.formatStatus(session.status),
            'chatPatientInitials': this.getPatientInitials(patientName)
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        });
    }

    // SIMPLIFICADO: mostrar información básica del paciente
    displayPatientInfo() {
        if (!this.patientData) return;

        const updates = {
            'patientInfoName': this.patientData.patient_name,
            'patientInfoDocument': this.patientData.document,
            'patientInfoPhone': this.patientData.phone,
            'patientInfoEmail': this.patientData.email,
            'patientInfoCity': this.patientData.city,
            'patientInfoEPS': this.patientData.eps,
            'patientInfoPlan': this.patientData.plan,
            'patientInfoStatus': this.patientData.status
        };

        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        });
        
        console.log('📋 Información del paciente mostrada');
    }

    // ====== CHAT SOCKET ======
    async connectToChat(session) {
        try {
            console.log('🔌 Conectando al chat...');
            
            if (!session.ptoken) throw new Error('No hay pToken para conectar al chat');
            
            this.chatSocket = io(this.wsUrl, {
                path: '/socket.io/',
                transports: ['websocket', 'polling'],
                autoConnect: true,
                auth: {
                    ptoken: session.ptoken
                }
            });
            
            this.setupChatSocketEvents();
            await this.loadChatHistory(session.id);
            
        } catch (error) {
            console.error('❌ Error conectando al chat:', error);
            throw error;
        }
    }

    setupChatSocketEvents() {
        this.chatSocket.on('connect', () => {
            console.log('✅ Chat conectado');
            this.isConnectedToChat = true;
            this.updateChatStatus('Conectado');
            
            this.chatSocket.emit('authenticate', { 
                ptoken: this.currentSession.ptoken,
                agent_mode: true 
            });
        });
        
        this.chatSocket.on('authenticated', () => {
            console.log('✅ Chat autenticado');
            this.chatSocket.emit('join_session', { 
                session_id: this.currentSessionId,
                agent_mode: true
            });
        });
        
        this.chatSocket.on('message_received', (data) => {
            console.log('📨 Mensaje recibido:', data);
            this.addMessageToChat(data, false);
        });
        
        this.chatSocket.on('disconnect', () => {
            console.log('🔌 Chat desconectado');
            this.isConnectedToChat = false;
            this.updateChatStatus('Desconectado');
        });
    }

    async loadChatHistory(sessionId) {
        try {
            console.log('📚 Cargando historial del chat para sesión:', sessionId);
            
            const response = await fetch(`${this.chatServiceUrl}/messages/${sessionId}?limit=100`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data && result.data.messages) {
                    const messages = result.data.messages;
                    console.log(`📨 Cargando ${messages.length} mensajes del historial`);
                    
                    const messagesContainer = document.getElementById('patientChatMessages');
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '';
                        
                        // Ordenar mensajes por timestamp
                        const sortedMessages = messages.sort((a, b) => {
                            const timeA = new Date(a.timestamp || a.created_at || 0);
                            const timeB = new Date(b.timestamp || b.created_at || 0);
                            return timeA - timeB;
                        });
                        
                        sortedMessages.forEach(message => {
                            const isFromAgent = message.sender_type === 'agent' || message.sender_type === 'staff';
                            this.addMessageToChat(message, isFromAgent);
                        });
                        
                        this.scrollToBottom();
                    }
                } else {
                    console.log('📭 No hay mensajes en el historial');
                }
            } else {
                console.warn('⚠️ Error cargando historial:', response.status);
            }
            
        } catch (error) {
            console.error('❌ Error cargando historial:', error);
        }
    }

    addMessageToChat(messageData, isFromAgent = false) {
        const messagesContainer = document.getElementById('patientChatMessages');
        if (!messagesContainer) return;

        const messageDiv = document.createElement('div');
        const timeLabel = this.formatTime(messageData.timestamp || messageData.created_at);

        messageDiv.className = `flex ${isFromAgent ? 'justify-end' : 'justify-start'} mb-4`;

        if (isFromAgent) {
            messageDiv.innerHTML = `
                <div class="max-w-xs lg:max-w-md">
                    <div class="bg-blue-600 text-white rounded-lg px-4 py-2">
                        <p>${this.escapeHtml(messageData.content)}</p>
                    </div>
                    <div class="text-xs text-gray-500 mt-1 text-right">Agente • ${timeLabel}</div>
                </div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="max-w-xs lg:max-w-md">
                    <div class="bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
                        <p>${this.escapeHtml(messageData.content)}</p>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Paciente • ${timeLabel}</div>
                </div>
            `;
        }

        messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }

    sendMessage() {
        const input = document.getElementById('agentMessageInput');
        const message = input.value.trim();
        
        if (!message || !this.isConnectedToChat || !this.chatSocket) return;
        
        console.log('📤 Enviando mensaje del agente:', message);
        
        this.chatSocket.emit('send_message', {
            content: message,
            message_type: 'text',
            session_id: this.currentSessionId,
            sender_type: 'agent'
        });
        
        // Agregar a UI inmediatamente
        this.addMessageToChat({
            content: message,
            timestamp: new Date().toISOString(),
            sender_type: 'agent'
        }, true);
        
        input.value = '';
        this.updateSendButton();
    }

    // ====== ACCIONES DE SESIÓN ======
    async transferInternal(toAgentId, reason) {
        try {
            const currentUser = this.getCurrentUser();
            
            const response = await fetch(`${this.chatServiceUrl}/transfers/internal`, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    session_id: this.currentSessionId,
                    from_agent_id: currentUser.id,
                    to_agent_id: toAgentId,
                    reason: reason
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Transferencia interna completada');
                this.closeChat();
            } else {
                throw new Error(result.message || 'Error en transferencia');
            }
            
        } catch (error) {
            console.error('❌ Error en transferencia:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async requestExternalTransfer(toRoom, reason, priority = 'medium') {
        try {
            const currentUser = this.getCurrentUser();
            
            const response = await fetch(`${this.chatServiceUrl}/transfers/request`, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    session_id: this.currentSessionId,
                    from_agent_id: currentUser.id,
                    to_room: toRoom,
                    reason: reason,
                    priority: priority
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Solicitud de transferencia enviada');
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('❌ Error solicitando transferencia:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async endSession(reason = 'completed_by_agent', notes = '') {
        try {
            const currentUser = this.getCurrentUser();
            
            const response = await fetch(`${this.chatServiceUrl}/sessions/${this.currentSessionId}/end-by-agent`, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    agent_id: currentUser.id,
                    reason: reason,
                    notes: notes
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Sesión finalizada exitosamente');
                this.closeChat();
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('❌ Error finalizando sesión:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async returnToQueue(reason = 'returned_to_queue') {
        try {
            const currentUser = this.getCurrentUser();
            
            const response = await fetch(`${this.chatServiceUrl}/sessions/${this.currentSessionId}/return`, {
                method: 'PUT',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    agent_id: currentUser.id,
                    reason: reason
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Sesión devuelta a la cola');
                this.closeChat();
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('❌ Error devolviendo sesión:', error);
            this.showError('Error: ' + error.message);
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

    getPatientName(session) {
        if (session.user_data) {
            try {
                const userData = typeof session.user_data === 'string' 
                    ? JSON.parse(session.user_data) 
                    : session.user_data;
                
                if (userData.nombreCompleto) return userData.nombreCompleto;
            } catch (e) {}
        }
        return 'Paciente';
    }

    getPatientInitials(name) {
        return name.split(' ').map(part => part.charAt(0)).join('').substring(0, 2).toUpperCase();
    }

    getTimeElapsed(timestamp) {
        const now = new Date();
        const start = new Date(timestamp);
        return Math.floor((now - start) / 60000);
    }

    formatStatus(status) {
        const statusMap = {
            'waiting': 'En Espera',
            'active': 'Activo',
            'completed': 'Completado',
            'expired': 'Expirado'
        };
        return statusMap[status] || status;
    }

    formatTime(timestamp) {
        try {
            if (!timestamp) return 'Ahora';
            const date = new Date(timestamp);
            if (isNaN(date.getTime())) return 'Ahora';
            return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        } catch (error) {
            return 'Ahora';
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    getRoomName(roomId) {
        const room = this.rooms.find(r => r.id === roomId);
        return room ? room.name : `Sala ${roomId}`;
    }

    getAgentName(agentId) {
        const currentUser = this.getCurrentUser();
        if (agentId === currentUser.id) return currentUser.name;
        return `Agente ${agentId.substring(0, 8)}...`;
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
        const chatStatus = document.getElementById('chatStatus');
        if (chatStatus) chatStatus.textContent = status;
    }

    updateSendButton() {
        const input = document.getElementById('agentMessageInput');
        const button = document.getElementById('agentSendButton');
        
        if (input && button) {
            button.disabled = input.value.trim() === '';
        }
    }

    scrollToBottom() {
        const container = document.getElementById('patientChatMessages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    closeChat() {
        if (this.chatSocket) {
            this.chatSocket.disconnect();
            this.chatSocket = null;
        }
        
        this.isConnectedToChat = false;
        this.currentSession = null;
        this.currentSessionId = null;
        this.patientData = null;
        
        document.getElementById('patient-chat-panel').classList.add('hidden');
        document.getElementById('room-sessions-section').classList.remove('hidden');
        
        if (this.currentRoom) {
            this.loadSessionsByRoom(this.currentRoom);
        }
    }

    goBackToRooms() {
        document.getElementById('room-sessions-section').classList.add('hidden');
        document.getElementById('rooms-list-section').classList.remove('hidden');

        this.currentRoom = null;
        
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
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
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4">×</button>
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
            console.log('🚀 Inicializando StaffClient...');
            await this.loadRoomsFromAuthService();
            this.startAutoRefresh();
        } catch (error) {
            console.error('❌ Error inicializando:', error);
            this.showError('Error de inicialización');
        }
    }

    startAutoRefresh() {
        this.refreshInterval = setInterval(() => {
            if (this.currentRoom) {
                this.loadSessionsByRoom(this.currentRoom);
            }
        }, 30000);
    }

    destroy() {
        if (this.refreshInterval) clearInterval(this.refreshInterval);
        if (this.countdownInterval) clearInterval(this.countdownInterval);
        if (this.chatSocket) this.chatSocket.disconnect();
    }
}

window.staffClient = new StaffClient();

// FUNCIONES DE DEBUG
window.debugStaff = {
    // Mostrar datos de la sesión actual
    showSessionData: () => {
        if (window.staffClient.currentSession) {
            console.log('📋 SESIÓN ACTUAL:', JSON.stringify(window.staffClient.currentSession, null, 2));
        } else {
            console.log('❌ No hay sesión actual');
        }
    },
    
    // Mostrar datos del paciente extraídos
    showPatientData: () => {
        if (window.staffClient.patientData) {
            console.log('👤 DATOS DEL PACIENTE:', JSON.stringify(window.staffClient.patientData, null, 2));
        } else {
            console.log('❌ No hay datos del paciente');
        }
    },
    
    // Forzar extracción de datos
    extractPatientData: () => {
        if (window.staffClient.currentSession) {
            window.staffClient.extractPatientDataFromSession(window.staffClient.currentSession);
            window.staffClient.displayPatientInfo();
            console.log('🔄 Datos re-extraídos y mostrados');
        } else {
            console.log('❌ No hay sesión para extraer datos');
        }
    },
    
    // Test de conexión
    testConnection: () => window.staffClient.getToken()
};