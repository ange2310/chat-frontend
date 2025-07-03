// public/assets/js/staff-client.js - LÓGICA REAL PARA STAFF CON SALAS Y DATOS DE BD

class StaffClient {
    constructor() {
        // URLs del backend reales
        this.authServiceUrl = 'http://187.33.158.246:8080/auth';
        this.chatServiceUrl = 'http://187.33.158.246:8080/chats';
        this.wsUrl = 'ws://187.33.158.246:8080';
        
        // Estado
        this.currentRoom = null;
        this.currentSessionId = null;
        this.rooms = [];
        this.sessionsByRoom = {};
        this.currentPatientData = null;
        this.refreshInterval = null;
        
        console.log('🏥 StaffClient inicializado para manejo de salas y datos reales');
    }

    // ====== OBTENER TOKEN DE AUTENTICACIÓN ======
    getToken() {
        // PRIORITARIO: Usar la función global de staff.php que YA FUNCIONA
        if (typeof window.getToken === 'function') {
            const globalToken = window.getToken();
            if (globalToken && globalToken.trim() !== '') {
                console.log('✅ Usando token global de staff.php:', globalToken.substring(0, 20) + '...');
                return globalToken;
            }
        }
        
        // FALLBACK 1: Meta tag de PHP
        const phpTokenMeta = document.querySelector('meta[name="staff-token"]')?.content;
        if (phpTokenMeta && phpTokenMeta.trim() !== '') {
            console.log('✅ Usando token de meta tag:', phpTokenMeta.substring(0, 20) + '...');
            // Sincronizar con localStorage
            localStorage.setItem('pToken', phpTokenMeta);
            return phpTokenMeta;
        }
        
        // FALLBACK 2: localStorage
        const localToken = localStorage.getItem('pToken');
        if (localToken && localToken.trim() !== '') {
            console.log('✅ Usando token de localStorage:', localToken.substring(0, 20) + '...');
            return localToken;
        }
        
        // ERROR: No hay token disponible
        console.error('❌ NO HAY TOKEN DISPONIBLE EN NINGÚN LADO');
        console.log('🔍 Debug completo:', {
            globalFunction: typeof window.getToken,
            globalToken: typeof window.getToken === 'function' ? (window.getToken() ? 'disponible' : 'vacío') : 'función no existe',
            metaTag: !!document.querySelector('meta[name="staff-token"]'),
            metaContent: document.querySelector('meta[name="staff-token"]')?.content ? 'disponible' : 'vacío',
            localStorage: localStorage.getItem('pToken') ? 'disponible' : 'vacío'
        });
        
        return null;
    }

    getAuthHeaders() {
        const token = this.getToken();
        
        if (!token) {
            console.error('❌ Token no disponible para headers de staff-client');
            throw new Error('Token no disponible');
        }
        
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`
        };
        
        // Debug headers
        console.log('🔑 Headers staff-client:', {
            hasAuth: !!headers.Authorization,
            authPreview: headers.Authorization ? headers.Authorization.substring(0, 30) + '...' : 'No auth',
            tokenLength: token ? token.length : 0
        });
        
        return headers;
    }

    // ====== CARGAR SALAS REALES DE LA BD ======
    async loadRoomsFromDB() {
        try {
            console.log('🏠 Cargando salas reales desde BD...');
            
            // Verificar token antes de hacer request
            const token = this.getToken();
            if (!token) {
                throw new Error('No hay token disponible para cargar salas');
            }
            
            console.log('🔑 Token encontrado para salas:', token.substring(0, 20) + '...');
            
            const headers = this.getAuthHeaders();
            
            const response = await fetch(`${this.authServiceUrl}/rooms/available`, {
                method: 'GET',
                headers: headers
            });

            console.log('📡 Respuesta salas - Status:', response.status, response.statusText);

            if (!response.ok) {
                // Si es error 401, intentar con función global de token
                if (response.status === 401) {
                    console.warn('⚠️ Error 401, intentando con token global...');
                    
                    // Intentar con la función global getToken
                    const globalToken = typeof window.getToken === 'function' ? window.getToken() : null;
                    if (globalToken && globalToken !== token) {
                        console.log('🔄 Reintentando con token global...');
                        
                        const retryHeaders = {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'Authorization': `Bearer ${globalToken}`
                        };
                        
                        const retryResponse = await fetch(`${this.authServiceUrl}/rooms/available`, {
                            method: 'GET',
                            headers: retryHeaders
                        });
                        
                        if (retryResponse.ok) {
                            console.log('✅ Reintento exitoso con token global');
                            const result = await retryResponse.json();
                            
                            if (result.success && result.data && result.data.rooms) {
                                this.rooms = result.data.rooms;
                                console.log(`✅ ${this.rooms.length} salas cargadas con token global`);
                                this.displayRooms();
                                return this.rooms;
                            }
                        }
                    }
                }
                
                const errorText = await response.text();
                throw new Error(`Error HTTP ${response.status}: ${response.statusText}\n${errorText}`);
            }

            const result = await response.json();
            console.log('📋 Respuesta de salas:', result);

            if (result.success && result.data && result.data.rooms) {
                this.rooms = result.data.rooms;
                console.log(`✅ ${this.rooms.length} salas cargadas desde BD`);
                
                // Mostrar salas en UI
                this.displayRooms();
                
                return this.rooms;
            } else {
                throw new Error('No se encontraron salas en la respuesta');
            }
            
        } catch (error) {
            console.error('❌ Error cargando salas:', error);
            this.showError('Error cargando salas: ' + error.message);
            
            // Mostrar UI de error
            const roomsContainer = document.getElementById('roomsContainer');
            if (roomsContainer) {
                roomsContainer.innerHTML = `
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <p class="text-red-600 font-medium">Error cargando salas</p>
                        <p class="text-gray-500 text-sm mb-4">${error.message}</p>
                        <button onclick="staffClient.loadRoomsFromDB()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Reintentar
                        </button>
                    </div>
                `;
            }
            
            return [];
        }
    }

    // ====== MOSTRAR SALAS EN LA UI ======
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

    // ====== CREAR TARJETA DE SALA ======
    createRoomCard(room) {
        const sessionsCount = this.sessionsByRoom[room.id]?.length || 0;
        const waitingCount = this.sessionsByRoom[room.id]?.filter(s => s.status === 'waiting').length || 0;
        
        return `
            <div class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-all cursor-pointer" 
                 onclick="staffClient.selectRoom('${room.id}')">
                
                <!-- Header de la sala -->
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

                <!-- Estadísticas de la sala -->
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
            
            if (!room) {
                throw new Error('Sala no encontrada');
            }

            // Actualizar UI - mostrar sección de sesiones
            this.showRoomSessions(room);
            
            // Cargar sesiones de esta sala específica
            await this.loadSessionsByRoom(roomId);
            
            console.log(`✅ Sala ${room.name} seleccionada`);
            
        } catch (error) {
            console.error('❌ Error seleccionando sala:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== CARGAR SESIONES POR SALA (BD REAL) ======
    async loadSessionsByRoom(roomId) {
        try {
            console.log(`📡 Cargando sesiones reales para sala: ${roomId}`);
            
            // Llamar al endpoint de sesiones filtrando por sala
            const response = await fetch(`${this.chatServiceUrl}/sessions?room_id=${roomId}`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (!response.ok) {
                throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            console.log(`📋 Respuesta sesiones sala ${roomId}:`, result);

            if (result.success && result.data && result.data.sessions) {
                // Procesar sesiones para extraer datos del paciente
                const processedSessions = result.data.sessions.map(session => this.processSessionData(session));
                
                this.sessionsByRoom[roomId] = processedSessions;
                console.log(`✅ ${processedSessions.length} sesiones cargadas para sala ${roomId}`);
                
                // Actualizar UI con las sesiones
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

    // ====== PROCESAR DATOS DE SESIÓN ======
    processSessionData(sessionData) {
        // Extraer información del paciente como en preauth.php
        let patientName = 'Paciente';
        let patientId = sessionData.user_id || 'unknown';
        
        // Intentar extraer nombre del paciente de diferentes fuentes
        if (sessionData.patient_data) {
            patientName = sessionData.patient_data.name || sessionData.patient_data.nombreCompleto || patientName;
            patientId = sessionData.patient_data.document || sessionData.patient_data.id || patientId;
        } else if (sessionData.user_data) {
            try {
                const userData = typeof sessionData.user_data === 'string' 
                    ? JSON.parse(sessionData.user_data) 
                    : sessionData.user_data;
                
                if (userData.nombreCompleto) {
                    patientName = userData.nombreCompleto;
                } else if (userData.primer_nombre) {
                    patientName = [
                        userData.primer_nombre,
                        userData.segundo_nombre,
                        userData.primer_apellido,
                        userData.segundo_apellido
                    ].filter(n => n).join(' ');
                }
                
                patientId = userData.numero_documento || userData.id || patientId;
            } catch (e) {
                console.warn('Error parseando user_data:', e);
            }
        }
        
        // Calcular tiempo de expiración
        const createdAt = new Date(sessionData.created_at || sessionData.createdAt || Date.now());
        const expiresAt = new Date(createdAt.getTime() + (30 * 60 * 1000)); // 30 minutos
        
        return {
            id: sessionData.id || sessionData._id,
            patient_name: patientName,
            patient_id: patientId,
            room_id: sessionData.room_id,
            status: sessionData.status || 'waiting',
            created_at: createdAt.toISOString(),
            expires_at: expiresAt.toISOString(),
            agent_id: sessionData.agent_id || null,
            user_data: sessionData.user_data,
            patient_data: sessionData.patient_data,
            ptoken: sessionData.ptoken // Importante para validar después
        };
    }

    // ====== MOSTRAR UI DE SESIONES DE LA SALA ======
    showRoomSessions(room) {
        // Ocultar lista de salas
        const roomsSection = document.getElementById('rooms-list-section');
        if (roomsSection) {
            roomsSection.classList.add('hidden');
        }

        // Mostrar sección de sesiones
        const sessionsSection = document.getElementById('room-sessions-section');
        if (sessionsSection) {
            sessionsSection.classList.remove('hidden');
        }

        // Actualizar header
        const roomHeaderName = document.getElementById('currentRoomName');
        if (roomHeaderName) {
            roomHeaderName.textContent = room.name;
        }
    }

    // ====== MOSTRAR SESIONES EN LA UI ======
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
    }

    // ====== CREAR TARJETA DE SESIÓN ======
    createSessionCard(session) {
        const timeElapsed = this.getTimeElapsed(session.created_at);
        const timeRemaining = this.getTimeRemaining(session.expires_at);
        const isUrgent = timeRemaining <= 5;
        
        let statusClass = 'border-l-yellow-500';
        let statusColor = 'text-yellow-600';
        let statusText = 'En Espera';
        
        if (session.status === 'active') {
            statusClass = 'border-l-green-500';
            statusColor = 'text-green-600';
            statusText = 'Activo';
        } else if (session.status === 'completed') {
            statusClass = 'border-l-gray-500';
            statusColor = 'text-gray-600';
            statusText = 'Completado';
        }
        
        if (isUrgent) {
            statusClass = 'border-l-red-500';
        }
        
        return `
            <div class="bg-white rounded-lg shadow-sm border-l-4 ${statusClass} p-6 hover:shadow-md transition-all cursor-pointer"
                 onclick="staffClient.openPatientChat('${session.id}')">
                
                <div class="flex items-start justify-between">
                    <!-- Info del paciente -->
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-lg font-semibold text-blue-700">${this.getPatientInitials(session.patient_name)}</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">${session.patient_name}</h3>
                            <p class="text-sm text-gray-500">ID: ${session.patient_id}</p>
                        </div>
                    </div>
                    
                    <!-- Estado y tiempo -->
                    <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor} bg-gray-100">
                            ${statusText}
                        </span>
                        <div class="mt-2 text-sm text-gray-500">
                            <div>Transcurrido: ${timeElapsed} min</div>
                            <div class="${isUrgent ? 'text-red-600 font-bold' : ''}">
                                Expira: ${timeRemaining} min
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones -->
                <div class="mt-4 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Creado: ${new Date(session.created_at).toLocaleString('es-ES')}
                    </div>
                    
                    <div class="space-x-2">
                        ${session.status === 'waiting' ? 
                            `<button onclick="event.stopPropagation(); staffClient.assignSession('${session.id}')" 
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Tomar Chat
                            </button>` : ''
                        }
                        
                        <button onclick="event.stopPropagation(); staffClient.openPatientChat('${session.id}')" 
                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                            Ver Chat
                        </button>
                    </div>
                </div>
                
                ${isUrgent ? 
                    '<div class="mt-3 p-2 bg-red-50 border border-red-200 rounded text-red-700 text-sm font-medium">⚠️ Sesión próxima a expirar</div>' : ''
                }
            </div>
        `;
    }

    // ====== ABRIR CHAT DEL PACIENTE ======
    async openPatientChat(sessionId) {
        try {
            console.log('💬 Abriendo chat del paciente:', sessionId);
            
            const session = this.findSessionById(sessionId);
            if (!session) {
                throw new Error('Sesión no encontrada');
            }

            this.currentSessionId = sessionId;
            
            // Extraer datos de membresía del paciente usando pToken
            await this.loadPatientMembershipData(session);
            
            // Mostrar panel de chat con información del paciente
            this.showChatPanel(session);
            
            console.log('✅ Chat del paciente abierto');
            
        } catch (error) {
            console.error('❌ Error abriendo chat:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== CARGAR DATOS DE MEMBRESÍA DEL PACIENTE ======
    async loadPatientMembershipData(session) {
        try {
            console.log('📋 Cargando datos de membresía del paciente...');
            
            // Si ya tenemos datos en user_data, usarlos primero
            if (session.user_data || session.patient_data) {
                const userData = session.user_data || session.patient_data;
                let patientData = userData;
                
                if (typeof userData === 'string') {
                    try {
                        patientData = JSON.parse(userData);
                    } catch (e) {
                        console.warn('Error parseando user_data:', e);
                    }
                }
                
                this.currentPatientData = patientData;
                console.log('✅ Datos de paciente obtenidos de sesión:', patientData);
                return;
            }
            
            // Si tenemos pToken, validarlo para obtener datos completos como en preauth.php
            if (session.ptoken) {
                console.log('🔍 Validando pToken para obtener membresía...');
                
                const response = await fetch(`${this.authServiceUrl}/validate-token`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ptoken: session.ptoken })
                });

                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.data && result.data.data.membresias) {
                        this.currentPatientData = result.data.data;
                        console.log('✅ Datos de membresía obtenidos del pToken:', this.currentPatientData);
                        return;
                    }
                }
            }
            
            // Fallback: crear datos básicos
            this.currentPatientData = {
                patient_name: session.patient_name,
                patient_id: session.patient_id
            };
            
            console.log('⚠️ Usando datos básicos del paciente');
            
        } catch (error) {
            console.error('❌ Error cargando datos de membresía:', error);
            this.currentPatientData = {
                patient_name: session.patient_name,
                patient_id: session.patient_id
            };
        }
    }

    // ====== MOSTRAR PANEL DE CHAT CON INFO DEL PACIENTE ======
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

        // Actualizar información del header del chat
        this.updateChatHeader(session);
        
        // Cargar y mostrar información completa del paciente
        this.displayPatientMembershipInfo();
        
        // Inicializar chat real
        this.initializeChat(session);
    }

    // ====== ACTUALIZAR HEADER DEL CHAT ======
    updateChatHeader(session) {
        const elements = {
            'chatPatientName': session.patient_name,
            'chatPatientId': session.patient_id,
            'chatRoomName': this.getRoomName(session.room_id),
            'chatSessionStatus': this.formatStatus(session.status)
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    // ====== MOSTRAR INFORMACIÓN DE MEMBRESÍA EN EL PANEL ======
    displayPatientMembershipInfo() {
        if (!this.currentPatientData) {
            console.warn('⚠️ No hay datos de paciente para mostrar');
            return;
        }

        console.log('📝 Mostrando información de membresía:', this.currentPatientData);

        // Extraer datos como en preauth.php
        let membresia = null;
        let beneficiario = null;

        if (this.currentPatientData.membresias && this.currentPatientData.membresias.length > 0) {
            membresia = this.currentPatientData.membresias[0];
            beneficiario = membresia.beneficiarios?.find(b => b.tipo_ben === 'PPAL') || membresia.beneficiarios?.[0];
        }

        // Actualizar elementos del panel
        const updates = {
            // Información Personal
            'membershipPatientName': beneficiario?.primer_nombre && beneficiario?.primer_apellido ? 
                [beneficiario.primer_nombre, beneficiario.segundo_nombre, beneficiario.primer_apellido, beneficiario.segundo_apellido]
                .filter(n => n).join(' ') : (this.currentPatientData.patient_name || 'No disponible'),
            'membershipPatientDoc': beneficiario?.numero_documento || this.currentPatientData.patient_id || 'No disponible',
            'membershipPatientDocType': beneficiario?.tipo_documento || 'CC',
            'membershipPatientBirth': beneficiario?.fecha_nacimiento ? 
                new Date(beneficiario.fecha_nacimiento).toLocaleDateString('es-ES') : 'No disponible',
            'membershipPatientGender': beneficiario?.genero || 'No disponible',
            
            // Contacto
            'membershipPatientPhone': beneficiario?.telefono || membresia?.telefono || 'No disponible',
            'membershipPatientEmail': beneficiario?.email || membresia?.email || 'No disponible',
            'membershipPatientCity': beneficiario?.ciudad || membresia?.ciudad || 'No disponible',
            
            // Membresía
            'membershipEPS': membresia?.eps || 'No disponible',
            'membershipPlan': membresia?.plan || 'No disponible',
            'membershipStatus': membresia?.estado || 'Activo',
            'membershipExpiry': membresia?.fecha_fin ? 
                new Date(membresia.fecha_fin).toLocaleDateString('es-ES') : 'No disponible',
            
            // Tomador
            'membershipTomadorName': membresia?.nomTomador || 'No disponible',
            'membershipTomadorDoc': membresia?.docTomador || 'No disponible',
            'membershipTomadorCompany': membresia?.empresa || 'No disponible'
        };

        // Aplicar actualizaciones
        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });

        console.log('✅ Información de membresía mostrada en panel');
    }

    // ====== UTILIDADES ======
    findSessionById(sessionId) {
        for (const roomSessions of Object.values(this.sessionsByRoom)) {
            const session = roomSessions.find(s => s.id === sessionId);
            if (session) return session;
        }
        return null;
    }

    getRoomName(roomId) {
        const room = this.rooms.find(r => r.id === roomId);
        return room ? room.name : `Sala ${roomId}`;
    }

    getPatientInitials(name) {
        return name.split(' ')
                  .map(part => part.charAt(0))
                  .join('')
                  .substring(0, 2)
                  .toUpperCase();
    }

    getTimeElapsed(timestamp) {
        const now = new Date();
        const start = new Date(timestamp);
        return Math.floor((now - start) / 60000);
    }

    getTimeRemaining(expiresAt) {
        const now = new Date();
        const expires = new Date(expiresAt);
        return Math.max(0, Math.floor((expires - now) / 60000));
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

    // ====== INICIALIZAR CHAT REAL ======
    async initializeChat(session) {
        try {
            console.log('💬 Inicializando chat real para sesión:', session.id);
            
            // Configurar ChatClient para modo agente
            if (!window.chatClient) {
                window.chatClient = new ChatClient();
            }
            
            const agentToken = this.getToken();
            if (!agentToken) {
                throw new Error('Token de agente no disponible');
            }
            
            // Configurar chat client
            window.chatClient.currentSessionId = session.id;
            window.chatClient.currentPToken = agentToken;
            window.chatClient.currentRoom = session.room_id;
            window.chatClient.isAgentMode = true;
            
            // Conectar WebSocket si no está conectado
            if (!window.chatClient.isConnected) {
                console.log('🔌 Conectando WebSocket para agente...');
                await window.chatClient.connectWebSocket(agentToken);
            }
            
            // Unirse a la sesión específica
            if (window.chatClient.isAuthenticated) {
                window.chatClient.sendToSocket('join_session', {
                    session_id: session.id,
                    agent_id: this.getCurrentUser().id,
                    agent_mode: true
                });
            }
            
            // Cargar historial de mensajes
            await this.loadChatHistory(session.id);
            
            console.log('✅ Chat real inicializado');
            
        } catch (error) {
            console.error('❌ Error inicializando chat:', error);
            this.showError('Error inicializando chat: ' + error.message);
        }
    }

    // ====== CARGAR HISTORIAL DEL CHAT ======
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
                    console.log(`📨 Cargando ${messages.length} mensajes del historial`);
                    
                    // Limpiar contenedor de mensajes
                    const messagesContainer = document.getElementById('patientChatMessages');
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '';
                        
                        // Agregar mensajes
                        messages.forEach(message => {
                            this.addMessageToChatUI(message);
                        });
                        
                        // Scroll al final
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }
            }
            
        } catch (error) {
            console.error('❌ Error cargando historial:', error);
        }
    }

    // ====== AGREGAR MENSAJE A LA UI DEL CHAT ======
    addMessageToChatUI(message) {
        const messagesContainer = document.getElementById('patientChatMessages');
        if (!messagesContainer) return;

        const isAgentMessage = message.sender_type === 'agent';
        const timeLabel = new Date(message.timestamp || message.created_at).toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });

        const messageDiv = document.createElement('div');
        messageDiv.className = `flex ${isAgentMessage ? 'justify-end' : 'justify-start'} mb-4`;

        if (isAgentMessage) {
            messageDiv.innerHTML = `
                <div class="max-w-xs lg:max-w-md">
                    <div class="bg-blue-600 text-white rounded-lg px-4 py-2">
                        <p>${this.escapeHtml(message.content)}</p>
                    </div>
                    <div class="text-xs text-gray-500 mt-1 text-right">Agente • ${timeLabel}</div>
                </div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="max-w-xs lg:max-w-md">
                    <div class="bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
                        <p>${this.escapeHtml(message.content)}</p>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Paciente • ${timeLabel}</div>
                </div>
            `;
        }

        messagesContainer.appendChild(messageDiv);
    }

    // ====== OBTENER USUARIO ACTUAL ======
    getCurrentUser() {
        // Obtener datos del usuario de PHP meta tag
        const userMeta = document.querySelector('meta[name="staff-user"]');
        if (userMeta && userMeta.content) {
            try {
                const userData = JSON.parse(userMeta.content);
                return {
                    id: userData.id,
                    name: userData.name,
                    email: userData.email,
                    role: userData.role?.name || userData.role || 'agent'
                };
            } catch (e) {
                console.warn('Error parsing user meta:', e);
            }
        }
        
        // Fallback
        return {
            id: 'staff_user',
            name: 'Agente',
            email: 'agente@sistema.com',
            role: 'agent'
        };
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    goBackToRooms() {
        // Ocultar sesiones
        const sessionsSection = document.getElementById('room-sessions-section');
        if (sessionsSection) {
            sessionsSection.classList.add('hidden');
        }

        // Mostrar salas
        const roomsSection = document.getElementById('rooms-list-section');
        if (roomsSection) {
            roomsSection.classList.remove('hidden');
        }

        this.currentRoom = null;
    }

    goBackToSessions() {
        // Ocultar chat
        const chatPanel = document.getElementById('patient-chat-panel');
        if (chatPanel) {
            chatPanel.classList.add('hidden');
        }

        // Mostrar sesiones
        const sessionsSection = document.getElementById('room-sessions-section');
        if (sessionsSection) {
            sessionsSection.classList.remove('hidden');
        }

        this.currentSessionId = null;
        this.currentPatientData = null;
    }

    // ====== MANEJO DE ERRORES ======
    showError(message) {
        console.error('❌', message);
        
        // Mostrar notificación
        if (window.authClient && window.authClient.showError) {
            window.authClient.showError(message);
        } else {
            alert('Error: ' + message);
        }
    }

    showSuccess(message) {
        console.log('✅', message);
        
        if (window.authClient && window.authClient.showSuccess) {
            window.authClient.showSuccess(message);
        }
    }

    // ====== INICIALIZACIÓN ======
    async init() {
        try {
            console.log('🚀 Inicializando StaffClient con datos reales...');
            
            // Cargar salas de la BD
            await this.loadRoomsFromDB();
            
            // Iniciar auto-refresh cada 30 segundos
            this.startAutoRefresh();
            
            console.log('✅ StaffClient inicializado correctamente');
            
        } catch (error) {
            console.error('❌ Error inicializando StaffClient:', error);
            this.showError('Error de inicialización: ' + error.message);
        }
    }

    startAutoRefresh() {
        // Refrescar datos cada 30 segundos
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
    }
}

// Inicializar cliente global
window.staffClient = new StaffClient();

// === FUNCIONES GLOBALES PARA HTML ===

// Función para seleccionar sesión desde HTML (para compatibilidad con staff.php)
window.selectSession = async function(sessionId) {
    try {
        console.log('🔗 selectSession global llamada para:', sessionId);
        
        // Si estamos en modo salas, pasar al staff-client
        if (window.staffClient && window.staffClient.currentRoom) {
            console.log('📋 Redirigiendo a staff-client.openPatientChat');
            await window.staffClient.openPatientChat(sessionId);
            return;
        }
        
        // Si estamos en modo chat normal (staff.php original)
        console.log('📋 Usando lógica de sesión normal de staff.php');
        // Aquí se puede llamar la función original de staff.php si existe
        
    } catch (error) {
        console.error('❌ Error en selectSession global:', error);
        if (window.staffClient) {
            window.staffClient.showError('Error: ' + error.message);
        }
    }
};

// Función para asignar sesión desde HTML
window.assignToMe = async function(sessionId) {
    try {
        console.log('🔗 assignToMe global llamada para:', sessionId);
        
        if (window.staffClient) {
            await window.staffClient.assignSession(sessionId);
        }
        
    } catch (error) {
        console.error('❌ Error en assignToMe global:', error);
        if (window.staffClient) {
            window.staffClient.showError('Error: ' + error.message);
        }
    }
};

console.log('🏥 staff-client.js cargado con lógica real de BD');