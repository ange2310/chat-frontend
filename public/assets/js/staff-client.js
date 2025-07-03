// public/assets/js/staff-client.js - SISTEMA COMPLETO PARA AGENTES

class StaffClient {
    constructor() {
        // URLs del backend en servidor
        this.authServiceUrl = 'http://187.33.158.246:8080/auth';
        this.chatServiceUrl = 'http://187.33.158.246:8080/chats';
        this.wsUrl = 'ws://187.33.158.246:8080';
        
        // Estado
        this.currentRoom = null;
        this.currentSessionId = null;
        this.currentSession = null;
        this.rooms = [];
        this.sessionsByRoom = {};
        this.currentPatientData = null;
        this.refreshInterval = null;
        this.chatSocket = null;
        this.isConnectedToChat = false;
        
        console.log('🏥 StaffClient inicializado - Sistema completo para agentes');
    }

    // ====== OBTENER TOKEN Y HEADERS ======
    getToken() {
        if (typeof window.getToken === 'function') {
            const globalToken = window.getToken();
            if (globalToken && globalToken.trim() !== '') {
                return globalToken;
            }
        }
        
        const phpTokenMeta = document.querySelector('meta[name="staff-token"]')?.content;
        if (phpTokenMeta && phpTokenMeta.trim() !== '') {
            return phpTokenMeta;
        }
        
        const localToken = localStorage.getItem('pToken');
        if (localToken && localToken.trim() !== '') {
            return localToken;
        }
        
        console.error('❌ NO HAY TOKEN DISPONIBLE');
        return null;
    }

    getAuthHeaders() {
        const token = this.getToken();
        if (!token) {
            throw new Error('Token no disponible');
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
                return JSON.parse(userMeta.content);
            } catch (e) {
                console.warn('Error parsing user meta:', e);
            }
        }
    }

    // ====== DEBUG: VERIFICAR ENDPOINTS DISPONIBLES ======
    async debugEndpoints() {
        console.log('🔍 Verificando endpoints disponibles...');
        
        const endpoints = [
            // Primero verificar salud del chat-service
            { url: `${this.chatServiceUrl}/rooms`, name: 'Chat Rooms (mock)' },
            { url: `${this.chatServiceUrl}/rooms/available`, name: 'Rooms from Auth' },
            { url: `${this.chatServiceUrl}/sessions`, name: 'Sessions' },
            { url: `${this.chatServiceUrl}/patient-info`, name: 'Patient Info' },
            
            // Verificar auth-service directo
            { url: `${this.authServiceUrl}/rooms/available`, name: 'Auth Service Direct' },
            { url: `${this.authServiceUrl}/health`, name: 'Auth Health' }
        ];
        
        for (const endpoint of endpoints) {
            try {
                console.log(`\n🔍 Probando: ${endpoint.name}`);
                console.log(`   📡 URL: ${endpoint.url}`);
                
                const response = await fetch(endpoint.url, {
                    method: 'GET',
                    headers: this.getAuthHeaders()
                });
                
                console.log(`   📊 Status: ${response.status} ${response.statusText}`);
                
                if (response.ok) {
                    const data = await response.json();
                    console.log(`   ✅ Response:`, data);
                    
                    // Si es rooms/available y tiene salas, intentar usarlas
                    if (endpoint.url.includes('rooms/available') && data.success && data.data && data.data.rooms) {
                        console.log(`   🎯 ¡Encontré ${data.data.rooms.length} salas aquí!`);
                    }
                } else {
                    const errorText = await response.text();
                    console.log(`   ❌ Error Response:`, errorText);
                }
            } catch (error) {
                console.log(`   💥 Exception:`, error.message);
            }
        }
        
        // También probar token
        console.log('\n🔑 Info del Token:');
        const token = this.getToken();
        console.log(`   Token length: ${token ? token.length : 0}`);
        console.log(`   Token preview: ${token ? token.substring(0, 30) + '...' : 'NO TOKEN'}`);
    }

    // ====== 1. CARGAR SALAS DESDE AUTH-SERVICE ======
    // ====== 1. CARGAR SALAS USANDO LA MISMA LÓGICA DEL AUTH-CLIENT ======
    async loadRoomsFromAuthService() {
        try {
            console.log('🏠 Cargando salas desde auth-service...');
            
            const token = this.getToken();
            if (!token) {
                throw new Error('Token requerido para obtener salas');
            }

            const response = await fetch(`${this.chatServiceUrl}/rooms/available`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            console.log(`📡 Respuesta salas:`, response.status);

            if (response.ok) {
                const data = await response.json();
                console.log('✅ Datos de salas:', data);
                
                const rooms = data.data?.rooms || data.rooms || [];
                
                if (Array.isArray(rooms)) {
                    console.log(`✅ ${rooms.length} salas encontradas`);
                    this.rooms = rooms;
                    this.displayRooms();
                    return rooms;
                } else {
                    console.log('⚠️ Respuesta no contiene salas:', data);
                    throw new Error('Respuesta no contiene salas válidas');
                }
            } else {
                const errorData = await response.json().catch(() => ({}));
                console.log(`❌ Error ${response.status}:`, errorData);
                throw new Error(`Error HTTP ${response.status}: ${errorData.message || 'Error obteniendo salas'}`);
            }

        } catch (error) {
            console.error('❌ Error obteniendo salas:', error);
            
            // FALLBACK: Salas hardcodeadas para testing
            console.log('🔄 FALLBACK: Usando salas de prueba...');
            this.rooms = [
                {
                    id: 'general',
                    name: 'Consultas Generales',
                    description: 'Consultas generales y información básica',
                    type: 'general',
                    available: true,
                    estimated_wait: '5-10 min',
                    current_queue: 0,
                    active_sessions: 0
                },
                {
                    id: 'medical',
                    name: 'Consultas Médicas',
                    description: 'Consultas médicas especializadas',
                    type: 'medical',
                    available: true,
                    estimated_wait: '10-15 min',
                    current_queue: 0,
                    active_sessions: 0
                },
                {
                    id: 'support',
                    name: 'Soporte Técnico',
                    description: 'Soporte técnico y problemas de la plataforma',
                    type: 'support',
                    available: true,
                    estimated_wait: '2-5 min',
                    current_queue: 0,
                    active_sessions: 0
                },
                {
                    id: 'emergency',
                    name: 'Emergencias',
                    description: 'Para casos de emergencia médica',
                    type: 'emergency',
                    available: true,
                    estimated_wait: 'Inmediato',
                    current_queue: 0,
                    active_sessions: 0
                }
            ];
            
            console.log(`📋 Usando ${this.rooms.length} salas de fallback`);
            this.displayRooms();
            
            // Mostrar warning pero no error crítico
            this.showNotification(`Error cargando salas: ${error.message}. Usando salas de prueba.`, 'warning', 8000);
            
            return this.rooms;
        }
    }

    // ====== 2. MOSTRAR SALAS EN LA UI ======
    displayRooms() {
        const roomsContainer = document.getElementById('roomsContainer');
        if (!roomsContainer) {
            console.warn('⚠️ No se encontró contenedor de salas');
            return;
        }

        if (this.rooms.length === 0) {
            roomsContainer.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                    </svg>
                    <p class="text-gray-500">No hay salas disponibles</p>
                </div>
            `;
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

    // ====== 3. SELECCIONAR SALA Y CARGAR SESIONES ======
    async selectRoom(roomId) {
        try {
            console.log('🎯 Seleccionando sala:', roomId);
            
            this.currentRoom = roomId;
            const room = this.rooms.find(r => r.id === roomId);
            
            if (!room) {
                throw new Error('Sala no encontrada');
            }

            this.showRoomSessions(room);
            await this.loadSessionsByRoom(roomId);
            
            console.log(`✅ Sala ${room.name} seleccionada`);
            
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

            if (!response.ok) {
                throw new Error(`Error HTTP ${response.status}`);
            }

            const result = await response.json();
            console.log(`📋 Sesiones de sala ${roomId}:`, result);

            if (result.success && result.data && result.data.sessions) {
                const processedSessions = result.data.sessions.map(session => this.processSessionData(session));
                
                this.sessionsByRoom[roomId] = processedSessions;
                console.log(`✅ ${processedSessions.length} sesiones cargadas`);
                
                this.displayRoomSessions(processedSessions, roomId);
                return processedSessions;
            } else {
                console.log(`📝 No hay sesiones para sala ${roomId}`);
                this.sessionsByRoom[roomId] = [];
                this.displayRoomSessions([], roomId);
                return [];
            }
            
        } catch (error) {
            console.error(`❌ Error cargando sesiones para sala ${roomId}:`, error);
            this.showError('Error cargando sesiones: ' + error.message);
            return [];
        }
    }

    processSessionData(sessionData) {
        // Calcular expiración
        const createdAt = new Date(sessionData.created_at || Date.now());
        const expiresAt = new Date(createdAt.getTime() + (30 * 60 * 1000)); // 30 minutos
        const now = new Date();
        const timeLeft = Math.max(0, expiresAt - now);
        
        return {
            ...sessionData,
            expires_at: expiresAt.toISOString(),
            time_left_ms: timeLeft,
            time_left_minutes: Math.floor(timeLeft / 60000),
            is_expired: timeLeft === 0,
            urgency_level: timeLeft < 5 * 60 * 1000 ? 'urgent' : timeLeft < 10 * 60 * 1000 ? 'warning' : 'normal'
        };
    }

    // ====== 4. MOSTRAR SESIONES CON CONTEO REGRESIVO ======
    showRoomSessions(room) {
        const roomsSection = document.getElementById('rooms-list-section');
        if (roomsSection) {
            roomsSection.classList.add('hidden');
        }

        const sessionsSection = document.getElementById('room-sessions-section');
        if (sessionsSection) {
            sessionsSection.classList.remove('hidden');
        }

        const roomHeaderName = document.getElementById('currentRoomName');
        if (roomHeaderName) {
            roomHeaderName.textContent = room.name;
        }
    }

    displayRoomSessions(sessions, roomId) {
        const sessionsContainer = document.getElementById('sessionsContainer');
        if (!sessionsContainer) return;

        if (sessions.length === 0) {
            sessionsContainer.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                    </svg>
                    <p class="text-gray-500">No hay sesiones en esta sala</p>
                    <button onclick="staffClient.goBackToRooms()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
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

        // Iniciar conteo regresivo
        this.startCountdown();
    }

    createSessionCard(session) {
        const patientName = this.getPatientName(session);
        const roomName = this.getRoomName(session.room_id); // ← Mostrar nombre en lugar de ID
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
                
                ${isUrgent && !isExpired ? 
                    '<div class="mt-3 p-2 bg-orange-50 border border-orange-200 rounded text-orange-700 text-sm font-medium">⚠️ Sesión próxima a expirar</div>' : ''
                }
                
                ${isExpired ? 
                    '<div class="mt-3 p-2 bg-red-50 border border-red-200 rounded text-red-700 text-sm font-medium">❌ Sesión expirada</div>' : ''
                }
            </div>
        `;
    }

    // ====== 5. CONTEO REGRESIVO EN TIEMPO REAL ======
    startCountdown() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }

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
        }, 60000); // Actualizar cada minuto
    }

    // ====== 6. TOMAR SESIÓN (ASIGNAR) ======
    async takeSession(sessionId) {
        try {
            console.log('👤 Tomando sesión:', sessionId);
            
            const currentUser = this.getCurrentUser();
            
            // USAR LA RUTA CORRECTA SEGÚN TUS RUTAS
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
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Error HTTP ${response.status}: ${errorText}`);
            }
            
            const result = await response.json();
            console.log('📋 Sesión tomada:', result);
            
            if (result.success) {
                this.showSuccess('Sesión asignada exitosamente');
                await this.loadSessionsByRoom(this.currentRoom);
                
                // Abrir chat automáticamente
                setTimeout(() => {
                    this.openPatientChat(sessionId);
                }, 1000);
            } else {
                throw new Error(result.message || 'Error asignando sesión');
            }
            
        } catch (error) {
            console.error('❌ Error tomando sesión:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== 7. ABRIR CHAT CON INFORMACIÓN DEL PACIENTE ======
    async openPatientChat(sessionId) {
        try {
            console.log('💬 Abriendo chat para sesión:', sessionId);
            
            const session = this.findSessionById(sessionId);
            if (!session) {
                throw new Error('Sesión no encontrada');
            }

            this.currentSession = session;
            this.currentSessionId = sessionId;
            
            // Cargar información del paciente
            await this.loadPatientInfo(session);
            
            // Mostrar panel de chat
            this.showChatPanel(session);
            
            // Conectar al chat
            await this.connectToChat(session);
            
            console.log('✅ Chat del paciente abierto');
            
        } catch (error) {
            console.error('❌ Error abriendo chat:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async loadPatientInfo(session) {
        try {
            console.log('📋 Cargando información del paciente...');
            
            if (!session.ptoken) {
                console.warn('⚠️ No hay pToken para obtener información del paciente');
                this.currentPatientData = { patient_name: 'Paciente', patient_id: session.user_id };
                return;
            }
            
            const response = await fetch(`${this.chatServiceUrl}/patient-info?ptoken=${session.ptoken}`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data) {
                    this.currentPatientData = result.data;
                    console.log('✅ Información del paciente obtenida:', this.currentPatientData);
                } else {
                    console.warn('⚠️ No se pudo obtener información completa del paciente');
                    this.currentPatientData = { patient_name: 'Paciente', patient_id: session.user_id };
                }
            } else {
                console.warn('⚠️ Error obteniendo información del paciente');
                this.currentPatientData = { patient_name: 'Paciente', patient_id: session.user_id };
            }
            
        } catch (error) {
            console.error('❌ Error cargando información del paciente:', error);
            this.currentPatientData = { patient_name: 'Paciente', patient_id: session.user_id };
        }
    }

    showChatPanel(session) {
        // Ocultar sección de sesiones
        const sessionsSection = document.getElementById('room-sessions-section');
        if (sessionsSection) {
            sessionsSection.classList.add('hidden');
        }

        // Mostrar panel de chat
        const chatPanel = document.getElementById('patient-chat-panel');
        if (chatPanel) {
            chatPanel.classList.remove('hidden');
        }

        // Actualizar header del chat
        this.updateChatHeader(session);
        
        // Mostrar información del paciente en sidebar
        this.displayPatientInfo();
    }

    updateChatHeader(session) {
        const patientName = this.getPatientName(session);
        const roomName = this.getRoomName(session.room_id); // ← Usar nombre en lugar de ID
        
        const elements = {
            'chatPatientName': patientName,
            'chatPatientId': `${patientName} - ${roomName}`, // ← Mostrar nombre del paciente y sala
            'chatRoomName': roomName, // ← Nombre de sala
            'chatSessionStatus': this.formatStatus(session.status)
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    displayPatientInfo() {
        if (!this.currentPatientData) return;

        const updates = {};

        // Información básica del paciente
        if (this.currentPatientData.patient_data) {
            const patientData = this.currentPatientData.patient_data;
            updates['patientInfoName'] = patientData.nombreCompleto || 'No disponible';
        }

        // Información de membresía
        if (this.currentPatientData.membership_info) {
            const membership = this.currentPatientData.membership_info;
            
            updates['patientInfoEPS'] = membership.eps || 'No disponible';
            updates['patientInfoPlan'] = membership.plan || 'No disponible';
            updates['patientInfoStatus'] = membership.estado || 'Activo';
            
            if (membership.beneficiario) {
                const beneficiario = membership.beneficiario;
                updates['patientInfoDocument'] = beneficiario.documento || 'No disponible';
                updates['patientInfoPhone'] = beneficiario.telefono || 'No disponible';
                updates['patientInfoEmail'] = beneficiario.email || 'No disponible';
                updates['patientInfoCity'] = beneficiario.ciudad || 'No disponible';
            }
            
            if (membership.tomador) {
                updates['patientInfoTomador'] = membership.tomador.nombre || 'No disponible';
                updates['patientInfoCompany'] = membership.tomador.empresa || 'No disponible';
            }
        }

        // Aplicar actualizaciones
        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    // ====== 8. CONECTAR AL CHAT Y CARGAR MENSAJES ======
    async connectToChat(session) {
        try {
            console.log('🔌 Conectando al chat...');
            
            if (!session.ptoken) {
                throw new Error('No hay pToken para conectar al chat');
            }
            
            // Conectar WebSocket
            this.chatSocket = io(this.wsUrl, {
                path: '/socket.io/',
                transports: ['websocket', 'polling'],
                autoConnect: true,
                auth: {
                    ptoken: session.ptoken
                }
            });
            
            this.setupChatSocketEvents();
            
            // Cargar historial de mensajes
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
            
            // Autenticar
            this.chatSocket.emit('authenticate', { 
                ptoken: this.currentSession.ptoken,
                agent_mode: true 
            });
        });
        
        this.chatSocket.on('authenticated', (data) => {
            console.log('✅ Chat autenticado:', data);
            
            // Unirse a la sesión
            this.chatSocket.emit('join_session', { 
                session_id: this.currentSessionId,
                agent_mode: true
            });
        });
        
        this.chatSocket.on('message_received', (data) => {
            console.log('📨 Mensaje recibido:', data);
            this.addMessageToChat(data, false);
        });
        
        this.chatSocket.on('file_uploaded', (data) => {
            console.log('📎 Archivo recibido:', data);
            this.addFileToChat(data, false);
        });
        
        this.chatSocket.on('disconnect', () => {
            console.log('🔌 Chat desconectado');
            this.isConnectedToChat = false;
            this.updateChatStatus('Desconectado');
        });
    }

    async loadChatHistory(sessionId) {
        try {
            console.log('📚 Cargando historial del chat...');
            
            const response = await fetch(`${this.chatServiceUrl}/messages/${sessionId}?limit=100`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data && result.data.messages) {
                    const messages = result.data.messages;
                    console.log(`📨 Cargando ${messages.length} mensajes`);
                    
                    const messagesContainer = document.getElementById('patientChatMessages');
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '';
                        
                        messages.forEach(message => {
                            const isFromAgent = message.sender_type === 'agent';
                            this.addMessageToChat(message, isFromAgent);
                        });
                        
                        this.scrollToBottom();
                    }
                }
            }
            
        } catch (error) {
            console.error('❌ Error cargando historial:', error);
        }
    }

    // ====== 9. FUNCIONES DE CHAT ======
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
        
        if (!message || !this.isConnectedToChat || !this.chatSocket) {
            return;
        }
        
        console.log('📤 Enviando mensaje:', message);
        
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

    // ====== 10. FUNCIONES DE TRANSFERENCIA ======
    async transferInternal(toAgentId, reason) {
        try {
            console.log('🔄 Transferencia interna:', { toAgentId, reason });
            
            const currentUser = this.getCurrentUser();
            
            // USAR ENDPOINT DEL TRANSFERCONTROLLER
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
            console.error('❌ Error en transferencia interna:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async requestExternalTransfer(toRoom, reason, priority = 'medium') {
        try {
            console.log('📤 Solicitando transferencia externa:', { toRoom, reason });
            
            const currentUser = this.getCurrentUser();
            
            // USAR ENDPOINT DEL TRANSFERCONTROLLER
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
                this.showSuccess('Solicitud de transferencia enviada al supervisor');
            } else {
                throw new Error(result.message || 'Error solicitando transferencia');
            }
            
        } catch (error) {
            console.error('❌ Error solicitando transferencia:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== 11. FINALIZAR SESIÓN ======
    async endSession(reason = 'completed_by_agent', notes = '') {
        try {
            console.log('🏁 Finalizando sesión:', { reason, notes });
            
            const currentUser = this.getCurrentUser();
            
            // USAR LA RUTA CORRECTA SEGÚN TUS RUTAS
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
                throw new Error(result.message || 'Error finalizando sesión');
            }
            
        } catch (error) {
            console.error('❌ Error finalizando sesión:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== 12. DEVOLVER SESIÓN A LA COLA ======
    async returnToQueue(reason = 'returned_to_queue') {
        try {
            console.log('↩️ Devolviendo sesión a la cola:', { reason });
            
            const currentUser = this.getCurrentUser();
            
            // USAR LA RUTA CORRECTA SEGÚN TUS RUTAS
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
                throw new Error(result.message || 'Error devolviendo sesión');
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
        if (session.patient_data) {
            return session.patient_data.name || session.patient_data.nombreCompleto || 'Paciente';
        }
        
        if (session.user_data) {
            try {
                const userData = typeof session.user_data === 'string' 
                    ? JSON.parse(session.user_data) 
                    : session.user_data;
                
                if (userData.nombreCompleto) {
                    return userData.nombreCompleto;
                }
            } catch (e) {
                console.warn('Error parseando user_data:', e);
            }
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
        // Si es el agente actual
        const currentUser = this.getCurrentUser();
        if (agentId === currentUser.id) {
            return currentUser.name;
        }
        
        // Para otros agentes, mostrar ID abreviado por ahora
        // En el futuro se puede hacer una consulta al backend para obtener nombres
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
        if (chatStatus) {
            chatStatus.textContent = status;
        }
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
        this.currentPatientData = null;
        
        const chatPanel = document.getElementById('patient-chat-panel');
        if (chatPanel) {
            chatPanel.classList.add('hidden');
        }
        
        const sessionsSection = document.getElementById('room-sessions-section');
        if (sessionsSection) {
            sessionsSection.classList.remove('hidden');
        }
        
        // Recargar sesiones
        if (this.currentRoom) {
            this.loadSessionsByRoom(this.currentRoom);
        }
    }

    goBackToRooms() {
        const sessionsSection = document.getElementById('room-sessions-section');
        if (sessionsSection) {
            sessionsSection.classList.add('hidden');
        }

        const roomsSection = document.getElementById('rooms-list-section');
        if (roomsSection) {
            roomsSection.classList.remove('hidden');
        }

        this.currentRoom = null;
        
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
    }

    showRoomsError(message) {
        const roomsContainer = document.getElementById('roomsContainer');
        if (roomsContainer) {
            roomsContainer.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-red-600 font-medium">Error cargando salas</p>
                    <p class="text-gray-500 text-sm mb-4">${message}</p>
                    <button onclick="staffClient.loadRoomsFromAuthService()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Reintentar
                    </button>
                </div>
            `;
        }
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showWarning(message) {
        this.showNotification(message, 'warning');
    }

    showNotification(message, type = 'info', duration = 4000) {
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            info: 'bg-blue-500',
            warning: 'bg-yellow-500'  // ← Agregar color warning
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

    // ====== INICIALIZACIÓN ======
    async init() {
        try {
            console.log('🚀 Inicializando StaffClient completo...');
            
            // Debug de endpoints disponibles
            if (console && typeof console.log === 'function') {
                console.log('🔧 Para debug, ejecuta: staffClient.debugEndpoints()');
            }
            
            await this.loadRoomsFromAuthService();
            this.startAutoRefresh();
            
            console.log('✅ StaffClient inicializado correctamente');
            
        } catch (error) {
            console.error('❌ Error inicializando StaffClient:', error);
            this.showError('Error de inicialización: ' + error.message);
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
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        if (this.chatSocket) {
            this.chatSocket.disconnect();
        }
    }
    // ====== MÉTODO AUXILIAR PARA DEBUG SIMPLIFICADO ======
    async debugAuthService() {
        console.log('🔍 Verificando auth-service endpoint...');
        
        try {
            const token = this.getToken();
            console.log('🔑 Token preview:', token ? token.substring(0, 30) + '...' : 'NO TOKEN');
            
            if (!token) {
                console.error('❌ No hay token disponible');
                return;
            }

            const response = await fetch(`${this.authServiceUrl}/rooms/available`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });
            
            console.log(`📊 Status: ${response.status} ${response.statusText}`);
            
            if (response.ok) {
                const data = await response.json();
                console.log('✅ Response:', data);
                
                if (data.success && data.data && data.data.rooms) {
                    console.log(`🎯 ¡Encontré ${data.data.rooms.length} salas!`);
                }
            } else {
                const errorText = await response.text();
                console.log('❌ Error Response:', errorText);
            }
            
        } catch (error) {
            console.log('💥 Exception:', error.message);
        }
    }
}

// Inicializar cliente global
window.staffClient = new StaffClient();

// Funciones de debug disponibles globalmente
window.debugStaff = {
    endpoints: () => window.staffClient.debugEndpoints(),
    loadRooms: () => window.staffClient.loadRoomsFromAuthService(),
    getToken: () => window.staffClient.getToken(),
    testAuth: async () => {
        const token = window.staffClient.getToken();
        console.log('🔑 Token:', token ? token.substring(0, 20) + '...' : 'No token');
        
        try {
            const response = await fetch(`${window.staffClient.authServiceUrl}/health`, {
                headers: window.staffClient.getAuthHeaders()
            });
            console.log('🏥 Auth health:', response.status, await response.text());
        } catch (error) {
            console.error('❌ Auth test error:', error);
        }
    }
    
};

console.log('🏥 staff-client.js completo cargado');
console.log('🔧 Debug disponible: debugStaff.endpoints(), debugStaff.testAuth(), debugStaff.loadRooms()');