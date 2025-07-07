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

    // ====== GESTIÓN DE TOKENS ======
    getAgentBearerToken() {
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

    // ====== CARGAR SALAS DESDE AUTH-SERVICE ======
    async loadRoomsFromAuthService() {
        try {
            console.log('📡 Cargando salas desde auth-service local...');
            
            // ✅ URL CORRECTA: auth-service puerto 3010
            const url = `${this.authServiceUrl}/rooms/available`;
            console.log('🔗 URL:', url);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            console.log('📡 Respuesta:', response.status, response.statusText);

            if (response.ok) {
                const data = await response.json();
                console.log('📊 Datos de salas:', data);
                
                const rooms = data.data?.rooms || data.rooms || [];
                
                if (Array.isArray(rooms) && rooms.length > 0) {
                    this.rooms = rooms;
                    this.displayRooms();
                    console.log(`✅ ${rooms.length} salas cargadas desde servidor`);
                    return rooms;
                } else {
                    console.log('⚠️ No hay salas en el servidor, usando fallback');
                    return this.loadRoomsFallback();
                }
            } else {
                const errorText = await response.text();
                console.error('❌ Error del servidor:', response.status, errorText);
                throw new Error(`Error HTTP ${response.status}: ${errorText}`);
            }
            
        } catch (error) {
            console.error('❌ Error cargando salas:', error);
            this.showNotification('Error conectando con el servidor. Usando salas de prueba.', 'warning');
            return this.loadRoomsFallback();
        }
    }

    loadRoomsFallback() {
        console.log('🚨 Cargando salas de desarrollo...');
        
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
        console.log(`✅ ${this.rooms.length} salas de desarrollo cargadas`);
        return this.rooms;
    }

    // ====== CARGAR SESIONES DESDE CHAT-SERVICE ======
    async loadSessionsByRoom(roomId) {
        try {
            console.log(`📡 Cargando sesiones para sala: ${roomId}`);
            
            // ✅ URL CORRECTA: chat-service puerto 3011
            const url = `${this.chatServiceUrl}/sessions?room_id=${roomId}&include_expired=false`;
            console.log('🔗 URL sesiones:', url);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            console.log(`📡 Respuesta sesiones ${roomId}:`, response.status);

            if (response.ok) {
                const result = await response.json();
                console.log('📊 Datos de sesiones:', result);
                
                if (result.success && result.data && result.data.sessions) {
                    const processedSessions = result.data.sessions.map(session => this.processSessionData(session));
                    this.sessionsByRoom[roomId] = processedSessions;
                    this.displayRoomSessions(processedSessions, roomId);
                    console.log(`✅ ${processedSessions.length} sesiones cargadas para ${roomId}`);
                    return processedSessions;
                } else {
                    console.log(`⚠️ No hay sesiones para ${roomId}`);
                    this.sessionsByRoom[roomId] = [];
                    this.displayRoomSessions([], roomId);
                    return [];
                }
            } else {
                const errorText = await response.text();
                console.error('❌ Error cargando sesiones:', response.status, errorText);
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error(`❌ Error cargando sesiones para ${roomId}:`, error);
            this.sessionsByRoom[roomId] = [];
            this.displayRoomSessions([], roomId);
            this.showError('Error cargando sesiones: ' + error.message);
            return [];
        }
    }

    // ====== FUNCIÓN DE CONECTIVIDAD PARA DEBUG ======
    async testLocalConnectivity() {
        console.log('🧪 Probando conectividad local...');
        
        const tests = [
            { name: 'Auth Health', url: 'http://localhost:3010/health' },
            { name: 'Chat Health', url: 'http://localhost:3011/health' },
            { name: 'Auth Rooms (sin auth)', url: 'http://localhost:3010/rooms/available' },
        ];

        const results = [];

        for (const test of tests) {
            try {
                console.log(`🔍 Probando: ${test.name}`);
                const response = await fetch(test.url, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                
                const status = `${response.status} ${response.statusText}`;
                console.log(`   ✅ ${test.name}: ${status}`);
                
                results.push({ name: test.name, status, success: response.ok });
                
                if (response.ok) {
                    try {
                        const data = await response.json();
                        console.log(`   📊 Respuesta:`, data);
                    } catch (e) {
                        console.log(`   📊 Respuesta: (no JSON)`);
                    }
                }
            } catch (error) {
                console.log(`   ❌ ${test.name}: ${error.message}`);
                results.push({ name: test.name, status: error.message, success: false });
            }
        }

        // Test con autenticación
        try {
            const token = this.getAgentBearerToken();
            if (token) {
                console.log('🔍 Probando rooms con autenticación...');
                const response = await fetch('http://localhost:3010/rooms/available', {
                    method: 'GET',
                    headers: this.getAuthHeaders()
                });
                
                const status = `${response.status} ${response.statusText}`;
                console.log(`   ✅ Rooms autenticado: ${status}`);
                results.push({ name: 'Rooms (autenticado)', status, success: response.ok });
                
                if (response.ok) {
                    const data = await response.json();
                    console.log(`   📊 Salas disponibles:`, data);
                }
            } else {
                console.log('   ⚠️ No hay token para probar autenticación');
                results.push({ name: 'Rooms (autenticado)', status: 'No token', success: false });
            }
        } catch (error) {
            console.log(`   ❌ Rooms autenticado: ${error.message}`);
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
            console.log('🚀 Inicializando StaffClient local...');
            await this.loadRoomsFromAuthService();
            console.log('✅ StaffClient inicializado exitosamente');
        } catch (error) {
            console.error('❌ Error inicializando StaffClient:', error);
            this.showNotification('Error de inicialización', 'error');
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
        console.log('🔑 Token disponible:', !!token);
        if (token) console.log('🔑 Token preview:', token.substring(0, 20) + '...');
        return { hasToken: !!token, tokenPreview: token?.substring(0, 20) + '...' };
    }
};

console.log('🔧 StaffClient v3.0 para desarrollo local cargado');
console.log('🛠️ Debug: window.debugStaff.testConnectivity()');
