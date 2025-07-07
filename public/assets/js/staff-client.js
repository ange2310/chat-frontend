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
        this.sessionJoined = false;
        
        // TOKENS MEJORADOS
        this.agentBearerToken = null;
        this.patientPToken = null;
        
        console.log('✅ StaffClient mejorado inicializado');
    }

    // ====== GESTIÓN DE TOKENS MEJORADA ======
    getAgentBearerToken() {
        // Primero intentar desde meta tag
        const phpTokenMeta = document.querySelector('meta[name="staff-token"]')?.content;
        if (phpTokenMeta && phpTokenMeta.trim() !== '') {
            console.log('🔑 Bearer token obtenido desde meta tag');
            this.agentBearerToken = phpTokenMeta;
            return phpTokenMeta;
        }
        
        console.error('❌ NO HAY BEARER TOKEN DISPONIBLE');
        return null;
    }

    getAuthHeaders() {
        const token = this.getAgentBearerToken();
        if (!token) throw new Error('Bearer token no disponible');
        
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
        return { id: 'unknown', name: 'Usuario', email: 'unknown@example.com' };
    }

    // ====== CARGAR SALAS ======
    async loadRoomsFromAuthService() {
        try {
            console.log('📡 Cargando salas...');
            
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
            console.error('❌ Error cargando salas:', error);
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
            
            const response = await fetch(`${this.chatServiceUrl}/sessions/${sessionId}/assign`, {
                method: 'POST',
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

    async openPatientChat(sessionId) {
        try {
            console.log('💬 Abriendo chat para sesión:', sessionId);
            
            // Buscar sesión en todas las salas
            const session = this.findSessionById(sessionId);
            if (!session) {
                // Si no está en cache, cargar desde el servidor
                console.log('📡 Sesión no encontrada en cache, cargando desde servidor...');
                const loadedSession = await this.loadSessionFromServer(sessionId);
                if (!loadedSession) {
                    throw new Error('Sesión no encontrada');
                }
                this.currentSession = loadedSession;
            } else {
                this.currentSession = session;
            }

            this.currentSessionId = sessionId;
            
            console.log('📋 Sesión cargada para chat:', this.currentSession);
            
            // Extraer pToken del paciente desde la sesión
            this.patientPToken = this.currentSession.ptoken;
            if (!this.patientPToken) {
                console.error('❌ No se encontró pToken del paciente en la sesión');
                throw new Error('No se encontró pToken del paciente');
            }
            
            console.log('🎫 pToken del paciente encontrado:', this.patientPToken.substring(0, 15) + '...');
            
            // Mostrar panel de chat
            this.showChatPanel(this.currentSession);
            
            // Conectar al chat DESPUÉS de mostrar el panel
            await this.connectToChat(this.currentSession);
            
            console.log('✅ Chat abierto y conectado para sesión:', sessionId);
            
        } catch (error) {
            console.error('❌ Error abriendo chat:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // Cargar sesión desde servidor si no está en cache
    async loadSessionFromServer(sessionId) {
        try {
            const response = await fetch(`${this.chatServiceUrl}/sessions/${sessionId}`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success && result.data) {
                    return result.data;
                }
            }
            return null;
        } catch (error) {
            console.error('❌ Error cargando sesión desde servidor:', error);
            return null;
        }
    }

    showChatPanel(session) {
        document.getElementById('room-sessions-section').classList.add('hidden');
        document.getElementById('patient-chat-panel').classList.remove('hidden');

        this.updateChatHeader(session);
        // displayPatientInfo se llamará después de cargar los datos con el pToken
    }

    updateChatHeader(session) {
        const patientName = this.getPatientName(session);
        const roomName = this.getRoomName(session.room_id);
        
        const elements = {
            'chatPatientName': patientName,
            'chatPatientId': session.id,
            'chatRoomName': roomName,
            'chatSessionStatus': this.formatStatus(session.status),
            'chatPatientInitials': this.getPatientInitials(patientName)
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        });
    }

    // ====== EXTRACCIÓN DE DATOS DEL PACIENTE - COMO PREAUTH.PHP ======
    async validatePatientPToken(pToken) {
        try {
            console.log('🔍 Validando pToken del paciente para extraer datos...');
            
            const response = await fetch(`${this.authServiceUrl}/validate-token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ 
                    ptoken: pToken 
                })
            });

            if (response.ok) {
                const result = await response.json();
                console.log('📨 Respuesta de validación pToken:', result);
                
                if (result.success && result.data && result.data.data) {
                    return {
                        success: true,
                        data: result.data
                    };
                } else {
                    throw new Error('pToken inválido o sin datos');
                }
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('❌ Error validando pToken:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async extractPatientDataFromPToken(pToken) {
        try {
            console.log('👤 Extrayendo datos del paciente desde pToken...');
            
            // Validar pToken igual que en preauth.php
            const validationResult = await this.validatePatientPToken(pToken);
            
            if (validationResult.success) {
                // Extraer datos de la membresía igual que preauth.php
                if (validationResult.data && validationResult.data.data && 
                    validationResult.data.data.membresias && 
                    validationResult.data.data.membresias.length > 0) {
                    
                    const membresia = validationResult.data.data.membresias[0];
                    console.log('💳 Procesando membresía:', membresia);
                    
                    // Extraer nombre del tomador
                    const nomTomador = membresia.nomTomador || 'Sistema de Atención';
                    
                    // Buscar beneficiario principal igual que preauth.php
                    const beneficiarioPrincipal = membresia.beneficiarios?.find(ben => ben.tipo_ben === 'PPAL');
                    
                    if (beneficiarioPrincipal) {
                        console.log('👤 Beneficiario principal encontrado:', beneficiarioPrincipal);
                        
                        // Construir nombre completo igual que preauth.php
                        const nombreCompleto = [
                            beneficiarioPrincipal.primer_nombre,
                            beneficiarioPrincipal.segundo_nombre,
                            beneficiarioPrincipal.primer_apellido,
                            beneficiarioPrincipal.segundo_apellido
                        ].filter(nombre => nombre && nombre.trim()).join(' ');
                        
                        // Crear objeto de datos del paciente
                        this.patientData = {
                            nombreCompleto: nombreCompleto,
                            nomTomador: nomTomador,
                            beneficiario: beneficiarioPrincipal,
                            // Datos específicos para mostrar en la UI
                            patient_name: nombreCompleto,
                            patient_id: beneficiarioPrincipal.id || 'N/A',
                            document: beneficiarioPrincipal.documento || 'No disponible',
                            phone: beneficiarioPrincipal.telefono || 'No disponible',
                            email: beneficiarioPrincipal.email || 'No disponible',
                            city: beneficiarioPrincipal.ciudad || 'No disponible',
                            eps: membresia.eps || 'No disponible',
                            plan: membresia.plan || 'No disponible',
                            status: membresia.estado || 'Activo'
                        };
                        
                        console.log('✅ Datos del paciente extraídos:', this.patientData);
                        
                        // Mostrar en la UI
                        this.displayPatientInfo();
                        
                        // Actualizar nombre en el header del chat también
                        const chatPatientName = document.getElementById('chatPatientName');
                        if (chatPatientName && nombreCompleto !== 'Paciente') {
                            chatPatientName.textContent = nombreCompleto;
                        }
                        
                        return true;
                    } else {
                        console.warn('⚠️ No se encontró beneficiario principal');
                        this.setDefaultPatientData();
                        return false;
                    }
                } else {
                    console.warn('⚠️ No se encontraron datos de membresía');
                    this.setDefaultPatientData();
                    return false;
                }
            } else {
                console.error('❌ Error validando pToken:', validationResult.error);
                this.setDefaultPatientData();
                return false;
            }
            
        } catch (error) {
            console.error('❌ Error extrayendo datos del paciente:', error);
            this.setDefaultPatientData();
            return false;
        }
    }

    setDefaultPatientData() {
        this.patientData = {
            patient_name: 'Paciente',
            patient_id: 'N/A',
            document: 'No disponible',
            phone: 'No disponible',
            email: 'No disponible',
            city: 'No disponible',
            eps: 'No disponible',
            plan: 'No disponible',
            status: 'No disponible'
        };
        this.displayPatientInfo();
    }

    displayPatientInfo() {
        if (!this.patientData) {
            console.warn('⚠️ No hay datos del paciente para mostrar');
            return;
        }

        console.log('📋 Mostrando información del paciente:', this.patientData);

        const updates = {
            'patientInfoName': this.patientData.patient_name || this.patientData.nombreCompleto || 'Paciente',
            'patientInfoDocument': this.patientData.document || 'No disponible',
            'patientInfoPhone': this.patientData.phone || 'No disponible',
            'patientInfoEmail': this.patientData.email || 'No disponible',
            'patientInfoCity': this.patientData.city || 'No disponible',
            'patientInfoEPS': this.patientData.eps || 'No disponible',
            'patientInfoPlan': this.patientData.plan || 'No disponible',
            'patientInfoStatus': this.patientData.status || 'No disponible',
            'patientInfoTomador': this.patientData.nomTomador || 'No disponible'
        };

        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
                console.log(`📝 Actualizado ${id}: ${value}`);
            } else {
                console.warn(`⚠️ Elemento ${id} no encontrado`);
            }
        });
        
        console.log('✅ Información del paciente mostrada correctamente');
    }

    // ====== CONEXIÓN AL CHAT - MEJORADA ======
    async connectToChat(session) {
        try {
            console.log('🔌 Conectando al chat como agente...');
            
            // OBTENER BEARER TOKEN DEL AGENTE
            const agentBearerToken = this.getAgentBearerToken();
            if (!agentBearerToken) {
                throw new Error('No hay Bearer token del agente disponible');
            }
            
            console.log('🔑 Usando Bearer token del agente:', agentBearerToken.substring(0, 15) + '...');
            console.log('🎫 Usando pToken del paciente:', this.patientPToken.substring(0, 15) + '...');
            
            // GUARDAR TOKENS
            this.agentBearerToken = agentBearerToken;
            
            // CONECTAR WEBSOCKET
            this.chatSocket = io(this.wsUrl, {
                path: '/socket.io/',
                transports: ['websocket', 'polling'],
                autoConnect: true,
                auth: {
                    ptoken: agentBearerToken,           // ← Bearer token para autenticación del agente
                    ptoken: this.patientPToken,       // ← pToken para acceso a datos del paciente  
                    agent_mode: true,
                    user_type: 'staff'
                }
            });
            
            this.setupChatSocketEvents();
            
            // Esperar conexión
            await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => reject(new Error('Timeout conectando WebSocket')), 10000);
                
                this.chatSocket.on('connect', () => {
                    clearTimeout(timeout);
                    resolve();
                });
                
                this.chatSocket.on('connect_error', (error) => {
                    clearTimeout(timeout);
                    reject(error);
                });
            });
            
            // DESPUÉS DE CONECTAR: Extraer datos del paciente y cargar historial
            await this.extractPatientDataFromPToken(this.patientPToken);
            await this.loadChatHistory(session.id);
            
        } catch (error) {
            console.error('❌ Error conectando al chat:', error);
            throw error;
        }
    }

    setupChatSocketEvents() {
        // CONEXIÓN
        this.chatSocket.on('connect', () => {
            console.log('✅ Agente conectado al WebSocket');
            this.isConnectedToChat = true;
            this.updateChatStatus('Conectado');
            
            // AUTENTICACIÓN CON BEARER TOKEN DEL AGENTE
            console.log('🔐 Autenticando agente con Bearer token...');
            this.chatSocket.emit('authenticate', { 
                ptoken: this.agentBearerToken,           // ← Bearer token del agente
                agent_mode: true,
                user_type: 'staff',
                session_id: this.currentSessionId
            });
        });
        
        // AUTENTICACIÓN EXITOSA
        this.chatSocket.on('authenticated', (data) => {
            console.log('✅ Agente autenticado con Bearer token:', data);
            
            // UNIRSE A LA SESIÓN ESPECÍFICA
            console.log('🏠 Uniéndose a la sesión:', this.currentSessionId);
            this.chatSocket.emit('join_session', { 
                session_id: this.currentSessionId,
                agent_mode: true,
                action: 'agent_join',
                agent_data: {
                    user_id: this.getCurrentUser()?.id,
                    name: this.getCurrentUser()?.name,
                    bearer_token: this.agentBearerToken,
                    patient_ptoken: this.patientPToken  // ← Para referencia
                }
            });
        });
        
        // CONFIRMACIONES DE UNIÓN
        this.chatSocket.on('session_joined', (data) => {
            console.log('✅ Agente unido a sesión exitosamente:', data);
            this.updateChatStatus('En sesión activa');
            this.sessionJoined = true;
        });
        
        this.chatSocket.on('agent_joined_session', (data) => {
            console.log('✅ Evento agent_joined_session:', data);
            this.updateChatStatus('Agente en sesión');
            this.sessionJoined = true;
        });
        
        this.chatSocket.on('room_joined', (data) => {
            console.log('✅ Sala unida exitosamente:', data);
            this.updateChatStatus('En sala activa');
            this.sessionJoined = true;
        });
        
        // ERRORES
        this.chatSocket.on('join_error', (data) => {
            console.error('❌ Error uniéndose a sesión:', data);
            this.updateChatStatus('Error de unión');
            this.showError('Error uniéndose a la sesión: ' + data.error);
        });
        
        this.chatSocket.on('auth_error', (data) => {
            console.error('❌ Error de autenticación:', data);
            this.updateChatStatus('Error de autenticación');
            this.showError('Error de autenticación: ' + data.error);
        });
        
        // MENSAJES
        this.chatSocket.on('message_received', (data) => {
            console.log('📨 Mensaje recibido:', data);
            
            if (data.session_id === this.currentSessionId) {
                const isFromAgent = data.sender_type === 'agent' || data.sender_type === 'staff';
                this.addMessageToChat(data, isFromAgent);
                
                if (!isFromAgent) {
                    this.playNotificationSound();
                }
            }
        });
        
        this.chatSocket.on('message_sent', (data) => {
            console.log('✅ Mensaje del agente enviado exitosamente:', data);
        });
        
        this.chatSocket.on('message_error', (data) => {
            console.error('❌ Error enviando mensaje:', data);
            this.showError('Error enviando mensaje: ' + data.error);
            
            // Si el error es de autenticación/sala, reintentar unión
            if (data.error && (data.error.includes('No en sala') || data.error.includes('autenticado'))) {
                console.log('🔄 Reintentando unión debido a error...');
                this.rejoinSession();
            }
        });
        
        // TYPING
        this.chatSocket.on('start_typing', (data) => {
            if (data.session_id === this.currentSessionId && data.sender_type === 'patient') {
                this.showTypingIndicator();
            }
        });
        
        this.chatSocket.on('stop_typing', (data) => {
            if (data.session_id === this.currentSessionId && data.sender_type === 'patient') {
                this.hideTypingIndicator();
            }
        });
        
        // DESCONEXIÓN
        this.chatSocket.on('disconnect', () => {
            console.log('🔌 Agente desconectado del chat');
            this.isConnectedToChat = false;
            this.sessionJoined = false;
            this.updateChatStatus('Desconectado');
        });
        
        this.chatSocket.on('connect_error', (error) => {
            console.error('❌ Error de conexión del agente:', error);
            this.updateChatStatus('Error de conexión');
        });
    }

    rejoinSession() {
        if (!this.currentSessionId || !this.chatSocket || !this.agentBearerToken) return;
        
        console.log('🔄 Reintentando unión a sesión con Bearer token...');
        
        // Reautenticar primero
        this.chatSocket.emit('authenticate', { 
            token: this.agentBearerToken,
            agent_mode: true,
            user_type: 'staff',
            session_id: this.currentSessionId,
            force_auth: true
        });
        
        // Después intentar unirse
        setTimeout(() => {
            this.chatSocket.emit('join_session', { 
                session_id: this.currentSessionId,
                agent_mode: true,
                force_join: true,
                bearer_token: this.agentBearerToken,
                agent_data: {
                    user_id: this.getCurrentUser()?.id,
                    name: this.getCurrentUser()?.name
                }
            });
        }, 1000);
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

    // ====== ENVIAR MENSAJE - MEJORADO ======
    sendMessage() {
        const input = document.getElementById('agentMessageInput');
        const message = input.value.trim();
        
        if (!message || !this.isConnectedToChat || !this.chatSocket) {
            console.warn('⚠️ No se puede enviar: sin mensaje, sin conexión o sin socket');
            return;
        }

        if (!this.currentSessionId) {
            console.warn('⚠️ No hay sesión activa');
            this.showError('No hay sesión activa');
            return;
        }
        
        if (!this.sessionJoined) {
            console.warn('⚠️ No unido a sesión, reintentando...');
            this.rejoinSession();
            this.showError('Reintentando unión a la sesión...');
            return;
        }
        
        console.log('📤 Enviando mensaje del agente:', message);
        
        // ESTRUCTURA COMPLETA DEL MENSAJE CON BEARER TOKEN
        const messageData = {
            content: message,
            message_type: 'text',
            session_id: this.currentSessionId,
            sender_type: 'agent',
            sender_id: this.getCurrentUser()?.id,
            timestamp: new Date().toISOString(),
            agent_mode: true,
            bearer_token: this.agentBearerToken  // ← Incluir Bearer token para verificación
        };
        
        console.log('📤 Datos del mensaje completos:', messageData);
        
        this.chatSocket.emit('send_message', messageData);
        
        // Agregar a UI inmediatamente
        this.addMessageToChat({
            content: message,
            timestamp: new Date().toISOString(),
            sender_type: 'agent',
            session_id: this.currentSessionId
        }, true);
        
        input.value = '';
        this.updateSendButton();
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

    playNotificationSound() {
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmYfBSuPze/R');
            audio.volume = 0.2;
            audio.play().catch(() => {});
        } catch (error) {
            // Ignorar errores de audio
        }
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
        this.sessionJoined = false;
        this.currentSession = null;
        this.currentSessionId = null;
        this.patientData = null;
        this.patientPToken = null;
        
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
            console.log('🚀 Inicializando StaffClient mejorado...');
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

// INSTANCIA GLOBAL
window.staffClient = new StaffClient();

// FUNCIONES DE DEBUG MEJORADAS
window.debugStaff = {
    // Mostrar todos los tokens
    showTokens: () => {
        console.log('🔑 BEARER TOKEN AGENTE:', window.staffClient.agentBearerToken || 'No disponible');
        console.log('🎫 PTOKEN PACIENTE:', window.staffClient.patientPToken || 'No disponible');
    },
    
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
    extractPatientData: async () => {
        if (window.staffClient.patientPToken) {
            await window.staffClient.extractPatientDataFromPToken(window.staffClient.patientPToken);
            console.log('🔄 Datos re-extraídos y mostrados');
        } else {
            console.log('❌ No hay pToken para extraer datos');
        }
    },
    
    // Test de conexión y autenticación
    testConnection: () => {
        const bearerToken = window.staffClient.getAgentBearerToken();
        console.log('🔑 Bearer token disponible:', bearerToken ? 'SÍ' : 'NO');
        console.log('🔌 WebSocket conectado:', window.staffClient.isConnectedToChat);
        console.log('🏠 Sesión unida:', window.staffClient.sessionJoined);
        return bearerToken;
    },
    
    // Forzar reconexión
    reconnect: async () => {
        if (window.staffClient.currentSession) {
            await window.staffClient.connectToChat(window.staffClient.currentSession);
            console.log('🔄 Reconexión forzada');
        } else {
            console.log('❌ No hay sesión actual para reconectar');
        }
    }
};