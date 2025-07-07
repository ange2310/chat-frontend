class StaffClient {
    constructor() {
        // 🏠 URLs LOCALES DIRECTAS PARA DESARROLLO
        this.authServiceUrl = 'http://localhost:3010';  // ✅ Para login, salas, validación
        this.chatServiceUrl = 'http://localhost:3011/chats'; // ✅ Para sesiones, mensajes
        this.wsUrl = 'ws://localhost:3011';                  // ✅ Para WebSocket
        
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
        
        // TOKENS
        this.agentBearerToken = null;
        this.patientPToken = null;
        
        console.log('✅ StaffClient para desarrollo local inicializado');
        console.log('🔗 Auth Service:', this.authServiceUrl);
        console.log('💬 Chat Service:', this.chatServiceUrl);
        console.log('🔌 WebSocket:', this.wsUrl);
    }

    // ====== GESTIÓN DE TOKENS CON DEBUG ======
    getAgentBearerToken() {
        console.log('🔍 [StaffClient] getAgentBearerToken() llamado');
        
        // Intentar obtener desde meta tag
        const phpTokenMeta = document.querySelector('meta[name="staff-token"]');
        console.log('🔍 [StaffClient] Meta tag encontrado:', !!phpTokenMeta);
        
        if (phpTokenMeta && phpTokenMeta.content && phpTokenMeta.content.trim() !== '') {
            const token = phpTokenMeta.content.trim();
            console.log('🔑 [StaffClient] Token obtenido desde meta tag:', `${token.substring(0, 30)}...`);
            
            // Verificar que sea un JWT válido
            try {
                const parts = token.split('.');
                if (parts.length === 3) {
                    const payload = JSON.parse(atob(parts[1]));
                    console.log('✅ [StaffClient] Token es JWT válido:', {
                        id: payload.id,
                        email: payload.email,
                        role: payload.role,
                        exp: new Date(payload.exp * 1000).toISOString()
                    });
                    this.agentBearerToken = token;
                    return token;
                } else {
                    console.error('❌ [StaffClient] Token no es JWT válido (partes:', parts.length, ')');
                }
            } catch (e) {
                console.error('❌ [StaffClient] Error decodificando token:', e);
            }
        } else {
            console.error('❌ [StaffClient] Meta tag vacío o no encontrado');
        }
        
        console.error('❌ [StaffClient] NO HAY BEARER TOKEN DISPONIBLE');
        return null;
    }

    getAuthHeaders() {
        console.log('🔍 [StaffClient] getAuthHeaders() llamado');
        
        const token = this.getAgentBearerToken();
        if (!token) {
            console.error('❌ [StaffClient] No hay token para headers');
            throw new Error('Bearer token no disponible');
        }
        
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`
        };
        
        console.log('🔍 [StaffClient] Headers generados:', {
            'Content-Type': headers['Content-Type'],
            'Accept': headers['Accept'],
            'Authorization': `Bearer ${token.substring(0, 30)}...`
        });
        
        return headers;
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

    // ====== CARGAR SALAS DESDE AUTH-SERVICE CON DEBUG ======
    async loadRoomsFromAuthService() {
        try {
            console.log('📡 [StaffClient] loadRoomsFromAuthService() iniciado');
            
            // Verificar token antes de hacer la llamada
            const token = this.getAgentBearerToken();
            if (!token) {
                throw new Error('No hay token disponible');
            }
            
            const url = `${this.authServiceUrl}/rooms/available`;
            console.log('🔗 [StaffClient] URL:', url);
            
            const headers = this.getAuthHeaders();
            console.log('🔍 [StaffClient] Headers para request:', headers);
            
            console.log('📡 [StaffClient] Enviando request...');
            
            const response = await fetch(url, {
                method: 'GET',
                headers: headers
            });

            console.log('📡 [StaffClient] Respuesta recibida:', {
                status: response.status,
                statusText: response.statusText,
                ok: response.ok
            });

            if (response.ok) {
                const data = await response.json();
                console.log('📊 [StaffClient] Datos de salas:', data);
                
                const rooms = data.data?.rooms || data.rooms || [];
                
                if (Array.isArray(rooms) && rooms.length > 0) {
                    this.rooms = rooms;
                    this.displayRooms();
                    console.log(`✅ [StaffClient] ${rooms.length} salas cargadas desde servidor`);
                    return rooms;
                } else {
                    console.log('⚠️ [StaffClient] No hay salas en el servidor, usando fallback');
                    return this.loadRoomsFallback();
                }
            } else {
                const errorText = await response.text();
                console.error('❌ [StaffClient] Error del servidor:', {
                    status: response.status,
                    statusText: response.statusText,
                    body: errorText
                });
                throw new Error(`Error HTTP ${response.status}: ${errorText}`);
            }
            
        } catch (error) {
            console.error('❌ [StaffClient] Error cargando salas:', error);
            this.showNotification('Error conectando con el servidor. Usando salas de prueba.', 'warning');
            return this.loadRoomsFallback();
        }
    }

    loadRoomsFallback() {
        console.log('🚨 [StaffClient] Cargando salas de desarrollo...');
        
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
        console.log(`✅ [StaffClient] ${this.rooms.length} salas de desarrollo cargadas`);
        return this.rooms;
    }

    // ====== FUNCIÓN DE CONECTIVIDAD PARA DEBUG ======
    async testLocalConnectivity() {
        console.log('🧪 [StaffClient] Probando conectividad local...');
        
        const tests = [
            { name: 'Auth Health', url: 'http://localhost:3010/health' },
            { name: 'Chat Health', url: 'http://localhost:3011/health' },
        ];

        const results = [];

        for (const test of tests) {
            try {
                console.log(`🔍 [StaffClient] Probando: ${test.name}`);
                const response = await fetch(test.url, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                
                const status = `${response.status} ${response.statusText}`;
                console.log(`   ✅ [StaffClient] ${test.name}: ${status}`);
                
                results.push({ name: test.name, status, success: response.ok });
                
                if (response.ok) {
                    try {
                        const data = await response.json();
                        console.log(`   📊 [StaffClient] Respuesta:`, data);
                    } catch (e) {
                        console.log(`   📊 [StaffClient] Respuesta: (no JSON)`);
                    }
                }
            } catch (error) {
                console.log(`   ❌ [StaffClient] ${test.name}: ${error.message}`);
                results.push({ name: test.name, status: error.message, success: false });
            }
        }

        // Test con autenticación
        try {
            const token = this.getAgentBearerToken();
            if (token) {
                console.log('🔍 [StaffClient] Probando rooms con autenticación...');
                const response = await fetch('http://localhost:3010/rooms/available', {
                    method: 'GET',
                    headers: this.getAuthHeaders()
                });
                
                const status = `${response.status} ${response.statusText}`;
                console.log(`   ✅ [StaffClient] Rooms autenticado: ${status}`);
                results.push({ name: 'Rooms (autenticado)', status, success: response.ok });
                
                if (response.ok) {
                    const data = await response.json();
                    console.log(`   📊 [StaffClient] Salas disponibles:`, data);
                } else {
                    const errorText = await response.text();
                    console.log(`   ❌ [StaffClient] Error en rooms:`, errorText);
                }
            } else {
                console.log('   ⚠️ [StaffClient] No hay token para probar autenticación');
                results.push({ name: 'Rooms (autenticado)', status: 'No token', success: false });
            }
        } catch (error) {
            console.log(`   ❌ [StaffClient] Rooms autenticado: ${error.message}`);
            results.push({ name: 'Rooms (autenticado)', status: error.message, success: false });
        }

        console.table(results);
        return results;
    }

    // ====== RESTO DE MÉTODOS (sin cambios importantes) ======
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

    // ====== UTILIDADES ======
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
            console.log('🚀 [StaffClient] Inicializando...');
            
            // Verificar token inmediatamente
            const token = this.getAgentBearerToken();
            if (!token) {
                console.error('❌ [StaffClient] No hay token disponible durante inicialización');
                this.showNotification('Error: No hay token de autenticación disponible', 'error');
                return;
            }
            
            await this.loadRoomsFromAuthService();
            console.log('✅ [StaffClient] Inicializado exitosamente');
        } catch (error) {
            console.error('❌ [StaffClient] Error inicializando:', error);
            this.showNotification('Error de inicialización: ' + error.message, 'error');
        }
    }

    // ====== SELECCIONAR SALA ======
    async selectRoom(roomId) {
        try {
            console.log(`🎯 [StaffClient] Seleccionando sala: ${roomId}`);
            
            this.currentRoom = roomId;
            const room = this.rooms.find(r => r.id === roomId);
            
            if (!room) {
                console.error(`❌ [StaffClient] Sala no encontrada: ${roomId}`);
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
            
            console.log(`✅ [StaffClient] Sala seleccionada: ${room.name}`);
            
        } catch (error) {
            console.error(`❌ [StaffClient] Error seleccionando sala:`, error);
            this.showNotification('Error seleccionando sala: ' + error.message, 'error');
        }
    }

    // ====== CARGAR SESIONES DE UNA SALA ======
    async loadSessionsByRoom(roomId) {
        try {
            console.log(`📡 [StaffClient] Cargando sesiones para sala: ${roomId}`);
            
            const url = `${this.chatServiceUrl}/sessions?room_id=${roomId}&include_expired=false`;
            console.log('🔗 [StaffClient] URL sesiones:', url);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            console.log(`📡 [StaffClient] Respuesta sesiones ${roomId}:`, response.status);

            if (response.ok) {
                const result = await response.json();
                console.log('📊 [StaffClient] Datos de sesiones:', result);
                
                if (result.success && result.data && result.data.sessions) {
                    const processedSessions = result.data.sessions.map(session => this.processSessionData(session));
                    this.sessionsByRoom[roomId] = processedSessions;
                    this.displayRoomSessions(processedSessions, roomId);
                    console.log(`✅ [StaffClient] ${processedSessions.length} sesiones cargadas para ${roomId}`);
                    return processedSessions;
                } else {
                    console.log(`⚠️ [StaffClient] No hay sesiones para ${roomId}`);
                    this.sessionsByRoom[roomId] = [];
                    this.displayRoomSessions([], roomId);
                    return [];
                }
            } else {
                const errorText = await response.text();
                console.error('❌ [StaffClient] Error cargando sesiones:', response.status, errorText);
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error(`❌ [StaffClient] Error cargando sesiones para ${roomId}:`, error);
            this.sessionsByRoom[roomId] = [];
            this.displayRoomSessions([], roomId);
            this.showNotification('Error cargando sesiones: ' + error.message, 'error');
            return [];
        }
    }

    // ====== PROCESAR DATOS DE SESIÓN ======
    processSessionData(session) {
        return {
            id: session.id,
            room_id: session.room_id,
            status: session.status || 'waiting',
            created_at: session.created_at,
            updated_at: session.updated_at,
            user_data: session.user_data,
            metadata: session.metadata
        };
    }

    // ====== MOSTRAR SESIONES DE UNA SALA ======
    displayRoomSessions(sessions, roomId) {
        const container = document.getElementById('sessionsContainer');
        if (!container) {
            console.warn('⚠️ [StaffClient] Container de sesiones no encontrado');
            return;
        }

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

    // ====== CREAR CARD DE SESIÓN ======
    createSessionCard(session) {
        const patientName = this.getPatientNameFromSession(session);
        const statusColor = this.getStatusColor(session.status);
        const timeAgo = this.getTimeAgo(session.created_at);
        
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
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <span class="px-3 py-1 rounded-full text-sm font-medium ${statusColor}">
                            ${this.getStatusText(session.status)}
                        </span>
                        <div class="mt-2">
                            ${session.status === 'waiting' ? 
                                `<button onclick="staffClient.takeSession('${session.id}')" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                    Tomar
                                </button>` :
                                `<button class="px-4 py-2 bg-gray-300 text-gray-500 rounded-lg text-sm cursor-not-allowed" disabled>
                                    ${session.status === 'active' ? 'En curso' : 'No disponible'}
                                </button>`
                            }
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ====== UTILIDADES PARA SESIONES ======
    getPatientNameFromSession(session) {
        if (session.user_data) {
            try {
                const userData = typeof session.user_data === 'string' 
                    ? JSON.parse(session.user_data) 
                    : session.user_data;
                
                if (userData.nombreCompleto) return userData.nombreCompleto;
                if (userData.name) return userData.name;
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

    // ====== TOMAR SESIÓN ======
    async takeSession(sessionId) {
        try {
            console.log(`👤 [StaffClient] Tomando sesión: ${sessionId}`);
            
            const response = await fetch(`${this.chatServiceUrl}/sessions/${sessionId}/assign/me`, {
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
                console.log('✅ [StaffClient] Sesión asignada:', result);
                
                if (result.success) {
                    this.showNotification('Sesión asignada exitosamente', 'success');
                    
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
            console.error('❌ [StaffClient] Error tomando sesión:', error);
            this.showNotification('Error al tomar la sesión: ' + error.message, 'error');
        }
    }

    // ====== ABRIR CHAT CON PACIENTE ======
    async openPatientChat(sessionId) {
        try {
            console.log(`💬 [StaffClient] Abriendo chat para sesión: ${sessionId}`);
            
            this.currentSessionId = sessionId;
            
            // Aquí iría la lógica para conectar al WebSocket del chat
            // y cargar el historial de mensajes
            
            this.showNotification('Chat iniciado', 'success');
            
        } catch (error) {
            console.error('❌ [StaffClient] Error abriendo chat:', error);
            this.showNotification('Error al abrir chat: ' + error.message, 'error');
        }
    }

    // ====== MÉTODOS DE CICLO DE VIDA ======
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

// Debug helpers para desarrollo local
window.debugStaff = {
    testConnectivity: () => window.staffClient.testLocalConnectivity(),
    getUrls: () => ({
        auth: window.staffClient.authServiceUrl,
        chat: window.staffClient.chatServiceUrl,
        ws: window.staffClient.wsUrl
    }),
    loadRooms: () => window.staffClient.loadRoomsFromAuthService(),
    getToken: () => window.staffClient.getAgentBearerToken(),
    testAuth: () => {
        const token = window.staffClient.getAgentBearerToken();
        console.log('🔑 [DEBUG] Token disponible:', !!token);
        if (token) {
            console.log('🔑 [DEBUG] Token preview:', token.substring(0, 20) + '...');
            
            // Decodificar JWT
            try {
                const parts = token.split('.');
                if (parts.length === 3) {
                    const payload = JSON.parse(atob(parts[1]));
                    console.log('🔑 [DEBUG] JWT payload:', payload);
                }
            } catch (e) {
                console.error('❌ [DEBUG] Error decodificando JWT:', e);
            }
        }
        return { hasToken: !!token, tokenPreview: token?.substring(0, 20) + '...' };
    },
    selectRoom: (roomId) => window.staffClient.selectRoom(roomId),
    loadSessions: (roomId) => window.staffClient.loadSessionsByRoom(roomId),
    getCurrentRoom: () => window.staffClient.currentRoom,
    getRooms: () => window.staffClient.rooms,
    getSessions: (roomId) => window.staffClient.sessionsByRoom[roomId] || []
};

console.log('🔧 StaffClient v3.0 para desarrollo local con debugging cargado');
console.log('🛠️ Debug: window.debugStaff.testConnectivity()');
console.log('🛠️ Debug: window.debugStaff.testAuth()');