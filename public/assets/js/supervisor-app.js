
        const API_BASE = 'http://187.33.158.246';
        const CHAT_API = `${API_BASE}/chat`;
        const AUTH_API = `${API_BASE}/auth`;
        const FILE_API = `${API_BASE}/chat/files`;
        const SUPERVISOR_API = `${API_BASE}/supervisor`;

        let supervisorChatSocket = null;
        let currentSupervisorSession = null;
        let isSupervisorConnected = false;
        let supervisorSessionJoined = false;
        let supervisorIsTyping = false;
        let supervisorTypingTimer;
        let supervisorChatTimer = null;
        let supervisorTimerInterval = null;
        let sentMessages = new Set();
        let messageIdCounter = 0;
        let chatHistoryCache = new Map();
        let currentGroupRoom = null;
        let groupChatSocket = null;
        let isGroupChatConnected = false;
        let groupChatJoined = false;
        let currentGroupRoomId= null;
        let isSilentMode = true;

        class SupervisorClient {
            constructor() {
                this.supervisorServiceUrl = SUPERVISOR_API;
                this.currentSession = null;
                this.refreshInterval = null;
                this.refreshIntervalTime = 30000;
                this.currentTransfer = null;
                this.currentEscalation = null;
                this.selectedAgent = null;
            }

            getToken() {
                const phpTokenMeta = document.querySelector('meta[name="supervisor-token"]')?.content;
                if (phpTokenMeta && phpTokenMeta.trim() !== '') {
                    return phpTokenMeta;
                }
                return null;
            }

            getAuthHeaders() {
                const token = this.getToken();
                if (!token) throw new Error('Token no disponible');
                
                return {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'x-supervisor-id': this.getCurrentUser()?.id || 'supervisor'
                };
            }

            getCurrentUser() {
                const userMeta = document.querySelector('meta[name="supervisor-user"]');
                if (userMeta && userMeta.getAttribute('content')) {
                    try {
                        return JSON.parse(userMeta.getAttribute('content'));
                    } catch (e) {
                        console.warn('Error parsing user data:', e);
                        return null;
                    }
                }
                return null;
            }

            getRoomDisplayName(roomId) {
                if (!roomId) return 'Sala General';
                
                const roomNames = {
                    'general': 'Consultas Generales',
                    'medical': 'Consultas Médicas',
                    'emergency': 'Emergencias',
                    'support': 'Soporte Técnico',
                    'billing': 'Facturación',
                    'pharmacy': 'Farmacia'
                };
                
                return roomNames[roomId] || roomId || 'Sala General';
            }

            
            // ✅ FUNCIONES DE EXTRACCIÓN DE DATOS DEL PACIENTE (CON DEBUGGING MEJORADO)
            extractPatientInfo(session) {
                console.log('🔍 === EXTRAYENDO INFORMACIÓN DEL PACIENTE ===');
                console.log('📊 Sesión para extraer:', session);
                
                let patientData = {};
                
                // 🔍 BÚSQUEDA EN MÚLTIPLES UBICACIONES
                if (session.patient_data && Object.keys(session.patient_data).length > 0) {
                    console.log('✅ Usando session.patient_data:', session.patient_data);
                    patientData = session.patient_data;
                } else if (session.user_data) {
                    try {
                        console.log('🔄 Intentando usar session.user_data:', session.user_data);
                        const userData = typeof session.user_data === 'string' 
                            ? JSON.parse(session.user_data) 
                            : session.user_data;
                        
                        if (userData && typeof userData === 'object') {
                            console.log('✅ userData parseado exitosamente:', userData);
                            patientData = userData;
                        } else {
                            console.log('⚠️ userData no es un objeto válido:', userData);
                        }
                    } catch (e) {
                        console.warn('❌ Error parseando user_data:', e);
                    }
                } else {
                    console.log('⚠️ No se encontraron patient_data ni user_data');
                }

                const extractedInfo = {
                    primer_nombre: patientData.primer_nombre || patientData.firstName || patientData.nombre || '',
                    segundo_nombre: patientData.segundo_nombre || patientData.middleName || '',
                    primer_apellido: patientData.primer_apellido || patientData.lastName || patientData.apellido || '',
                    segundo_apellido: patientData.segundo_apellido || patientData.secondLastName || '',
                    nombreCompleto: patientData.nombreCompleto || patientData.fullName || patientData.name || '',
                    id: patientData.id || patientData.document || patientData.documento || patientData.cedula || '',
                    tipo_documento: patientData.tipo_documento || patientData.documentType || 'CC',
                    telefono: patientData.telefono || patientData.phone || patientData.celular || '',
                    email: patientData.email || patientData.correo || '',
                    ciudad: patientData.ciudad || patientData.city || patientData.municipio || '',
                    departamento: patientData.departamento || patientData.state || '',
                    direccion: patientData.direccion || patientData.address || '',
                    eps: patientData.eps || patientData.insurance || patientData.aseguradora || '',
                    plan: patientData.plan || patientData.planType || patientData.tipoplan || '',
                    habilitado: patientData.habilitado || patientData.status || patientData.estado || '',
                    nomTomador: patientData.nomTomador || patientData.policyHolder || patientData.tomador || '',
                    edad: patientData.edad || patientData.age || '',
                    fecha_nacimiento: patientData.fecha_nacimiento || patientData.birthDate || patientData.fechaNacimiento || '',
                    genero: patientData.genero || patientData.gender || patientData.sexo || ''
                };

                console.log('📋 Información extraída:', extractedInfo);
                console.log('✅ === FIN EXTRACCIÓN DE INFORMACIÓN DEL PACIENTE ===');
                
                return extractedInfo;
            }

            async fetchPatientDataFromPToken(ptoken) {
                try {
                    console.log('🔍 Consultando información del paciente con ptoken (supervisor):', ptoken);
                    
                    const response = await fetch(`${AUTH_API}/validate-token?ptoken=${encodeURIComponent(ptoken)}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const result = await response.json();
                    
                    if (!result.success || !result.data?.data?.membresias?.[0]?.beneficiarios) {
                        throw new Error('Formato de respuesta inválido');
                    }

                    const beneficiarios = result.data.data.membresias[0].beneficiarios;
                    const beneficiarioPrincipal = beneficiarios.find(b => b.tipo_ben === 'PPAL') || beneficiarios[0];
                    
                    if (!beneficiarioPrincipal) {
                        throw new Error('No se encontró beneficiario principal');
                    }

                    const membresia = result.data.data.membresias[0];

                    const patientData = {
                        primer_nombre: beneficiarioPrincipal.primer_nombre || '',
                        segundo_nombre: beneficiarioPrincipal.segundo_nombre || '',
                        primer_apellido: beneficiarioPrincipal.primer_apellido || '',
                        segundo_apellido: beneficiarioPrincipal.segundo_apellido || '',
                        nombreCompleto: `${beneficiarioPrincipal.primer_nombre} ${beneficiarioPrincipal.segundo_nombre} ${beneficiarioPrincipal.primer_apellido} ${beneficiarioPrincipal.segundo_apellido}`.replace(/\s+/g, ' ').trim(),
                        id: beneficiarioPrincipal.id || '',
                        tipo_documento: beneficiarioPrincipal.tipo_id || 'CC',
                        telefono: beneficiarioPrincipal.telefono || '',
                        email: beneficiarioPrincipal.email || '',
                        ciudad: beneficiarioPrincipal.ciudad || '',
                        direccion: beneficiarioPrincipal.direccion || '',
                        eps: beneficiarioPrincipal.eps || '',
                        plan: membresia.plan || '',
                        habilitado: membresia.habilitado || beneficiarioPrincipal.estado || '',
                        nomTomador: membresia.nomTomador || '',
                        edad: beneficiarioPrincipal.edad || '',
                        fecha_nacimiento: beneficiarioPrincipal.nacimiento || '',
                        genero: beneficiarioPrincipal.genero || ''
                    };
                    
                    console.log('✅ Información del paciente obtenida desde ptoken (supervisor):', patientData);
                    return patientData;

                } catch (error) {
                    console.error('❌ Error fetchPatientDataFromPToken (supervisor):', error);
                    throw error;
                }
            }

            async getPatientInfoWithPToken(session) {
                console.log('🔍 === INICIANDO OBTENCIÓN DE DATOS DEL PACIENTE ===');
                console.log('📊 Sesión recibida:', session);
                
                // 🥇 PRIMER INTENTO: Datos locales de la sesión
                console.log('1️⃣ Intentando extraer datos locales...');
                let patientInfo = this.extractPatientInfo(session);
                console.log('📊 Datos extraídos localmente:', patientInfo);
                console.log('❓ ¿Datos locales están vacíos?', this.isPatientInfoEmpty(patientInfo));
                
                // 🥈 SEGUNDO INTENTO: Consulta por ptoken (solo si datos están vacíos)
                if (this.isPatientInfoEmpty(patientInfo) && session.ptoken) {
                    try {
                        console.log('2️⃣ Datos locales vacíos, consultando con ptoken:', session.ptoken);
                        const ptokenData = await this.fetchPatientDataFromPToken(session.ptoken);
                        if (ptokenData) {
                            patientInfo = ptokenData;
                            console.log('✅ Información del paciente obtenida desde ptoken:', ptokenData);
                        }
                    } catch (error) {
                        console.error('❌ Error obteniendo datos del ptoken:', error);
                    }
                } else if (this.isPatientInfoEmpty(patientInfo) && !session.ptoken) {
                    console.log('⚠️ Datos locales vacíos Y no hay ptoken disponible');
                } else if (!this.isPatientInfoEmpty(patientInfo)) {
                    console.log('✅ Usando datos locales (no vacíos)');
                } else {
                    console.log('🔄 Sin ptoken, manteniendo datos locales');
                }
                
                console.log('📋 === INFORMACIÓN FINAL DEL PACIENTE ===');
                console.log('👤 Datos finales:', patientInfo);
                console.log('❓ ¿Datos finales están vacíos?', this.isPatientInfoEmpty(patientInfo));
                console.log('✅ === FIN OBTENCIÓN DE DATOS DEL PACIENTE ===');
                
                return patientInfo;
            }

            isPatientInfoEmpty(patientInfo) {
                const essentialFields = ['primer_nombre', 'primer_apellido', 'nombreCompleto', 'id', 'email'];
                const fieldValues = essentialFields.map(field => ({
                    field: field,
                    value: patientInfo[field],
                    isEmpty: !patientInfo[field]
                }));
                
                const isEmpty = essentialFields.every(field => !patientInfo[field]);
                
                console.log('🔍 Análisis de campos esenciales:', fieldValues);
                console.log('📊 ¿Información está vacía?', isEmpty);
                
                return isEmpty;
            }

            getPatientNameFromSession(session) {
                if (!session) return 'Paciente';
                
                const patientInfo = this.extractPatientInfo(session);
                
                if (patientInfo.nombreCompleto) {
                    return patientInfo.nombreCompleto;
                }
                
                const fullName = `${patientInfo.primer_nombre} ${patientInfo.segundo_nombre} ${patientInfo.primer_apellido} ${patientInfo.segundo_apellido}`
                    .replace(/\s+/g, ' ').trim();
                    
                if (fullName) {
                    return fullName;
                }
                
                if (session.user_data) {
                    try {
                        const userData = typeof session.user_data === 'string' 
                            ? JSON.parse(session.user_data) 
                            : session.user_data;
                        
                        if (userData && userData.nombreCompleto) return userData.nombreCompleto;
                        if (userData && userData.name) return userData.name;
                    } catch (e) {
                        console.warn('Error parseando user_data:', e);
                    }
                }
                
                return 'Paciente';
            }

            getRoomNameFromSession(session) {
                if (!session) return 'Sala General';
                
                // 🔧 NUEVO: Primero intentar usar el room_name que viene del backend
                if (session.room_name && session.room_name.trim()) {
                    return session.room_name.trim();
                }
                
                // 🔧 NUEVO: Segundo intento, buscar en user_data si hay room_name guardado
                if (session.user_data) {
                    try {
                        const userData = typeof session.user_data === 'string' 
                            ? JSON.parse(session.user_data) 
                            : session.user_data;
                        
                        if (userData && userData.room_name && userData.room_name.trim()) {
                            return userData.room_name.trim();
                        }
                    } catch (e) {
                        console.warn('Error parseando user_data para room_name:', e);
                    }
                }
                
                let roomId = session.room_id || session.roomId || session.room || session.type;
                
                if (roomId) {
                    const roomNames = {
                        '1': 'Consultas Generales',
                        '2': 'Consultas Médicas',
                        '3': 'Soporte Técnico', 
                        '4': 'Emergencias',
                        'general': 'Consultas Generales',
                        'medical': 'Consultas Médicas', 
                        'support': 'Soporte Técnico',
                        'emergency': 'Emergencias',
                        'emergencias': 'Emergencias',
                        'consulta_general': 'Consultas Generales',
                        'consultas_generales': 'Consultas Generales',
                        'consulta_medica': 'Consultas Médicas',
                        'consultas_medicas': 'Consultas Médicas',
                        'soporte_tecnico': 'Soporte Técnico'
                    };
                    
                    const roomIdString = String(roomId).toLowerCase().trim();
                    
                    if (roomNames[roomId]) {
                        return roomNames[roomId];
                    }
                    
                    if (roomNames[roomIdString]) {
                        return roomNames[roomIdString];
                    }
                    
                    for (const [key, value] of Object.entries(roomNames)) {
                        if (key.toLowerCase().includes(roomIdString) || roomIdString.includes(key.toLowerCase())) {
                            return value;
                        }
                    }
                    
                    // Para UUIDs, generar nombre descriptivo
                    if (this.isValidUUID(roomIdString)) {
                        console.warn(`UUID de sala sin mapeo: ${roomIdString}. Usando nombre genérico.`);
                        return 'Sala Especializada';
                    }
                    
                    const formattedName = String(roomId)
                        .replace(/_/g, ' ')
                        .replace(/-/g, ' ')
                        .split(' ')
                        .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                        .join(' ');
                    
                    return `Sala ${formattedName}`;
                }
                
                return 'Sala General';
            }

            isValidUUID(str) {
                const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
                return uuidRegex.test(str);
            }

            checkForNewItems(currentTransfers, currentEscalations) {
                if (this.lastTransferCount !== undefined && currentTransfers.length > this.lastTransferCount) {
                    const newCount = currentTransfers.length - this.lastTransferCount;
                    this.showNotification(`${newCount} nueva(s) transferencia(s) recibida(s)`, 'info');
                    this.playNotificationSound();
                }
                
                if (this.lastEscalationCount !== undefined && currentEscalations.length > this.lastEscalationCount) {
                    const newCount = currentEscalations.length - this.lastEscalationCount;
                    this.showNotification(`${newCount} nueva(s) escalación(es) recibida(s)`, 'warning');
                    this.playAlertSound();
                }
                
                this.lastTransferCount = currentTransfers.length;
                this.lastEscalationCount = currentEscalations.length;
            }

            playNotificationSound() {
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmklCEGH0fPRdSECCwAA');
                    audio.volume = 0.3;
                    audio.play().catch(() => {});
                } catch (e) {}
            }

            playAlertSound() {
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmklCEGH0fPRdSECCwAA');
                    audio.volume = 0.5;
                    audio.play().catch(() => {});
                } catch (e) {}
            }

            async loadTransfers() {
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/transfers/pending`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const transfers = result.data?.transfers || [];
                        
                        this.checkForNewItems(transfers, this.lastEscalationCount || 0);
                        
                        this.displayTransfers(transfers);
                        this.updateNavCounter('transfersCount', transfers.length);
                        return transfers;
                    } else {
                        throw new Error(`Error ${response.status}: ${response.statusText}`);
                    }
                } catch (error) {
                    console.error('Error loading transfers:', error);
                    this.showNotification('Error cargando transferencias: ' + error.message, 'error');
                    this.displayTransfers([]);
                    this.updateNavCounter('transfersCount', 0);
                }
            }

            async loadEscalations() {
                console.log('Cargando escalaciones desde:', `${this.supervisorServiceUrl}/escalations`);
                
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/escalations`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        console.log('Escalaciones recibidas:', result);
                        
                        const escalations = result.data?.escalations || [];
                        console.log('Escalaciones encontradas:', escalations.length);
                        
                        this.checkForNewItems(this.lastTransferCount || 0, escalations);
                        
                        this.displayEscalations(escalations);
                        this.updateNavCounter('escalationsCount', escalations.length);
                        return escalations;
                    } else {
                        throw new Error(`Error ${response.status}: ${response.statusText}`);
                    }
                } catch (error) {
                    console.error('Error loading escalations:', error);
                    this.showNotification('Error cargando escalaciones: ' + error.message, 'error');
                    this.displayEscalations([]);
                    this.updateNavCounter('escalationsCount', 0);
                }
            }

            displayTransfers(transfers) {
                const container = document.getElementById('transfersContainer');
                if (!container) return;

                if (transfers.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"></path>
                            </svg>
                            <p>No hay transferencias pendientes</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="space-y-4">
                        ${transfers.map(transfer => this.createTransferCard(transfer)).join('')}
                    </div>
                `;
            }

            createTransferCard(transfer) {
                const priority = transfer.priority || 'medium';
                const timeAgo = this.getTimeAgo(transfer.created_at || transfer.timestamp);
                const minutesPending = transfer.minutes_pending || 0;
                const isUrgent = transfer.is_urgent || minutesPending > 5;
                
                // Extraer información del paciente del objeto transfer con múltiples fallbacks
                let patientPhone = '';
                let patientEps = '';
                
                // Función para limpiar datos vacíos o con solo espacios
                const cleanData = (value) => {
                    if (!value || typeof value !== 'string') return null;
                    const cleaned = value.trim();
                    return cleaned.length > 0 ? cleaned : null;
                };
                
                // Teléfono y EPS con limpieza
                const rawPhone = cleanData(transfer.patient_phone) || 
                                cleanData(transfer.patient_data?.telefono) || 
                                cleanData(transfer.patient_data?.phone);
                if (rawPhone) patientPhone = rawPhone;
                              
                const rawEps = cleanData(transfer.patient_eps) || 
                              cleanData(transfer.patient_data?.eps) || 
                              cleanData(transfer.patient_data?.insurance);
                if (rawEps) patientEps = rawEps;
                
                console.log('Transfer data for card (cleaned):', {
                    transfer,
                    sessionId: transfer.session_id
                });
                
                return `
                    <div class="transfer-card priority-${priority} ${isUrgent ? 'border-red-400 bg-red-50' : ''}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <h4 class="font-semibold text-gray-900">Transfer #${transfer.id.substring(0, 8)}...</h4>
                                    <span class="status-badge status-${priority}">${priority.toUpperCase()}</span>
                                    ${isUrgent ? '<span class="status-badge status-urgent">URGENTE</span>' : ''}
                                </div>
                                
                                ${(patientPhone || patientEps) ? `
                                    <div class="bg-blue-50 rounded-lg p-3 mb-3">
                                        <div class="text-xs font-medium text-blue-700 mb-1">INFORMACIÓN ADICIONAL</div>
                                        <div class="grid grid-cols-2 gap-2 text-sm">
                                            ${patientPhone ? `<div><span class="font-medium text-blue-800">Teléfono:</span> ${patientPhone}</div>` : ''}
                                            ${patientEps ? `<div><span class="font-medium text-blue-800">EPS:</span> ${patientEps}</div>` : ''}
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                                    <div class="col-span-2">
                                        <span class="font-medium">Transferencia:</span> 
                                        <span class="text-purple-600 font-medium">${transfer.transfer_direction || (transfer.from_room_name + ' → ' + transfer.to_room_name)}</span>
                                    </div>
                                    <div><span class="font-medium">Solicitado por:</span> ${transfer.from_agent_name || 'Agente desconocido'}</div>
                                    <div><span class="font-medium">Tiempo pendiente:</span> 
                                        <span class="${minutesPending > 5 ? 'text-red-600 font-semibold' : 'text-gray-600'}">${minutesPending} min</span>
                                    </div>
                                </div>
                                
                                ${transfer.reason ? `
                                    <div class="bg-gray-50 rounded p-2 mt-2">
                                        <span class="font-medium text-sm text-gray-600">Motivo:</span>
                                        <p class="text-sm text-gray-700 mt-1">${transfer.reason}</p>
                                    </div>
                                ` : ''}
                                
                                ${transfer.session_id ? `
                                    <div class="bg-blue-50 rounded p-2 mt-2">
                                        <span class="font-medium text-xs text-blue-700">ID Sesión:</span>
                                        <p class="text-xs text-blue-800 font-mono">${transfer.session_id}</p>
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div class="flex flex-col gap-2 ml-4">
                                <button onclick="supervisorClient.approveTransfer('${transfer.id}')" 
                                        class="btn btn-success whitespace-nowrap">
                                    Aprobar
                                </button>
                                <button onclick="supervisorClient.rejectTransfer('${transfer.id}')" 
                                        class="btn btn-danger whitespace-nowrap">
                                    Rechazar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            displayEscalations(escalations) {
                this.lastEscalationsData = escalations;
                const container = document.getElementById('escalationsContainer');
                if (!container) return;

                if (escalations.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <p>No hay escalaciones activas</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="space-y-4">
                        ${escalations.map(escalation => this.createEscalationCard(escalation)).join('')}
                    </div>
                `;
            }

            createEscalationCard(escalation) {
                const timeAgo = this.getTimeAgo(escalation.created_at || escalation.timestamp);
                const isUrgent = escalation.urgency_indicator || escalation.failed_transfer_count >= 4;
                const priority = escalation.priority || 'high';
                const minutesWaiting = escalation.minutes_waiting || 0;
                
                // Extraer información del paciente del objeto escalation con múltiples fallbacks
                let patientPhone = '';
                let patientEps = '';
                
                // Teléfono y EPS con fallbacks similares
                patientPhone = escalation.patient_phone || 
                              escalation.patient_data?.telefono || 
                              escalation.patient_data?.phone ||
                              escalation.session?.patient_data?.telefono ||
                              escalation.session?.patient_data?.phone || '';
                              
                patientEps = escalation.patient_eps || 
                            escalation.patient_data?.eps || 
                            escalation.patient_data?.insurance ||
                            escalation.session?.patient_data?.eps ||
                            escalation.session?.patient_data?.insurance || '';
                
                console.log('Escalation data for card:', {
                    escalation
                });
                
                return `
                    <div class="escalation-card priority-${priority} ${isUrgent ? 'border-red-400 bg-red-50' : ''}" data-room-id="${escalation.current_room || escalation.room_id || ''}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <h4 class="font-semibold text-gray-900">Escalación #${escalation.session_id.substring(0, 12)}...</h4>
                                    <span class="status-badge status-${priority}">${escalation.failed_transfer_count} Intentos</span>
                                    ${isUrgent ? '<span class="status-badge status-urgent">CRÍTICO</span>' : ''}
                                    ${escalation.assigned_supervisor ? '<span class="status-badge status-active">ASIGNADO</span>' : ''}
                                </div>
                                
                                ${(patientPhone || patientEps) ? `
                                    <div class="bg-purple-50 rounded-lg p-3 mb-3">
                                        <div class="text-xs font-medium text-purple-700 mb-1">INFORMACIÓN ADICIONAL</div>
                                        <div class="grid grid-cols-2 gap-2 text-sm">
                                            ${patientPhone ? `<div><span class="font-medium text-purple-800">Teléfono:</span> ${patientPhone}</div>` : ''}
                                            ${patientEps ? `<div><span class="font-medium text-purple-800">EPS:</span> ${patientEps}</div>` : ''}
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                                    <div><span class="font-medium">Sala Actual:</span> 
                                        <span class="text-blue-600 font-medium">${escalation.room_name || this.getRoomDisplayName(escalation.current_room)}</span>
                                    </div>
                                    <div><span class="font-medium">Estado:</span> ${escalation.session_status || 'N/A'}</div>
                                    <div><span class="font-medium">Esperando:</span> 
                                        <span class="${minutesWaiting > 20 ? 'text-red-600 font-semibold' : 'text-orange-600'}">${minutesWaiting} min</span>
                                    </div>
                                    <div><span class="font-medium">Prioridad:</span> 
                                        <span class="capitalize ${priority === 'urgent' || priority === 'critical' ? 'text-red-600 font-semibold' : 'text-gray-600'}">${priority}</span>
                                    </div>
                                </div>
                                
                                ${escalation.supervisor_name ? `
                                    <div class="bg-yellow-50 rounded p-2 mt-2">
                                        <span class="text-xs font-medium text-yellow-700">ASIGNADO A:</span>
                                        <p class="text-sm text-yellow-800">${escalation.supervisor_name}</p>
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div class="flex flex-col gap-2 ml-4">
                                ${!escalation.assigned_supervisor ? `
                                    <button onclick="supervisorClient.takeEscalation('${escalation.session_id}')" 
                                            class="btn btn-warning whitespace-nowrap">
                                        Tomar Control
                                    </button>
                                ` : escalation.assigned_supervisor === this.getCurrentUser()?.id ? `
                                    <button onclick="supervisorClient.openExistingSupervisionChat('${escalation.session_id}')" 
                                            class="btn btn-primary whitespace-nowrap">
                                        Abrir Chat
                                    </button>
                                ` : `
                                    <div class="text-xs text-gray-500 text-center p-2">
                                        Tomado por<br>otro supervisor
                                    </div>
                                `}
                                
                                <button onclick="supervisorClient.assignEscalation('${escalation.session_id}')" 
                                        class="btn btn-success whitespace-nowrap">
                                    Asignar Agente
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            async approveTransfer(transferId) {
                this.currentTransfer = transferId;
                
                if (confirm('¿Aprobar esta transferencia? La sesión se moverá a la sala destino.')) {
                    try {
                        const response = await fetch(`${this.supervisorServiceUrl}/transfers/${transferId}/approve`, {
                            method: 'PUT',
                            headers: this.getAuthHeaders(),
                            body: JSON.stringify({
                                notes: null,
                            })
                        });
                        
                        if (response.ok) {
                            const result = await response.json();
                            this.showNotification('Transferencia aprobada - Sesión movida a nueva sala', 'success');
                            setTimeout(() => this.loadTransfers(), 1000);
                        } else {
                            throw new Error('Error del servidor');
                        }
                    } catch (error) {
                        console.error('Error aprobando transferencia:', error);
                        this.showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                this.currentTransfer = null;
            }

            async rejectTransfer(transferId) {
                const reason = prompt('Motivo del rechazo?');
                if (!reason) return;

                this.saveRejectionReason(transferId, reason);
                
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/transfers/${transferId}/reject`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            reason: reason,
                            supervisor_id: this.getCurrentUser()?.id
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        this.showNotification(`Transferencia ${transferId} rechazada`, 'info');
                        
                        // 🔧 CORREGIDO: Mejor detección de escalaciones automáticas
                        if (result.data?.escalation_triggered || result.escalation_triggered) {
                            this.showNotification('Se ha creado una escalación automática', 'warning');
                            
                            // 🔧 CORREGIDO: Actualizar ambas secciones con mejor timing
                            setTimeout(async () => {
                                await this.loadTransfers();
                                await this.loadEscalations();
                                
                                // 🔧 NUEVO: Segunda verificación para asegurar que se cargue la nueva escalación
                                setTimeout(async () => {
                                    await this.loadEscalations();
                                }, 3000);
                            }, 2000);
                        } else {
                            setTimeout(() => this.loadTransfers(), 1000);
                        }
                    } else {
                        throw new Error('Error del servidor');
                    }
                } catch (error) {
                    console.error('Error rechazando transferencia:', error);
                    this.showNotification('Error: ' + error.message, 'error');
                }
            }
            // Funciones para manejo de motivos de rechazo
            saveRejectionReason(transferId, reason) {
                try {
                    // Buscar en las transferencias del DOM para extraer las salas
                    let fromRoom = 'desconocida', toRoom = 'desconocida';
                    
                    const transferCards = document.querySelectorAll('.transfer-card');
                    for (const card of transferCards) {
                        if (card.innerHTML.includes(transferId.substring(0, 8))) {
                            const directionElement = card.querySelector('.text-purple-600');
                            if (directionElement && directionElement.textContent.includes(' → ')) {
                                const parts = directionElement.textContent.split(' → ');
                                fromRoom = parts[0].trim();
                                toRoom = parts[1].trim();
                                break;
                            }
                        }
                    }
                    
                    const patternKey = `${fromRoom}_TO_${toRoom}`;
                    const rejections = JSON.parse(localStorage.getItem('supervisor_rejections_by_pattern') || '{}');
                    
                    if (!rejections[patternKey]) {
                        rejections[patternKey] = [];
                    }
                    
                    rejections[patternKey].push({
                        reason: reason,
                        timestamp: new Date().toISOString(),
                        supervisor: this.getCurrentUser()?.name || 'Supervisor',
                        transferId: transferId,
                        fromRoom: fromRoom,
                        toRoom: toRoom
                    });
                    
                    localStorage.setItem('supervisor_rejections_by_pattern', JSON.stringify(rejections));
                    console.log('Motivo guardado para patrón:', patternKey, '-', reason);
                    console.log('Salas detectadas:', fromRoom, '->', toRoom);
                } catch (error) {
                    console.warn('Error guardando motivo de rechazo:', error);
                }
            }

            getRejectionReasonsForPattern(fromRoom, toRoom) {
                try {
                    const rejections = JSON.parse(localStorage.getItem('supervisor_rejections_by_pattern') || '{}');
                    console.log('🔍 Debug - Todas las claves disponibles:', Object.keys(rejections));
                    console.log('🔍 Debug - Buscando fromRoom:', fromRoom, 'toRoom:', toRoom);
                    
                    // Buscar por múltiples formatos posibles
                    const possibleKeys = [
                        `${fromRoom}_TO_${toRoom}`,
                        `${toRoom}_TO_${fromRoom}`, // Por si están invertidos
                    ];
                    
                    let patternReasons = [];
                    
                    // Buscar también por nombres de sala similares
                    for (const [key, reasons] of Object.entries(rejections)) {
                        console.log('🔍 Debug - Comparando clave:', key);
                        if (possibleKeys.includes(key)) {
                            patternReasons.push(...reasons);
                            console.log('✅ Debug - Coincidencia exacta encontrada');
                        }
                    }
                    
                    console.log('🔍 Debug - Motivos encontrados:', patternReasons);
                    return patternReasons;
                } catch (error) {
                    console.warn('Error cargando motivos de rechazo:', error);
                    return [];
                }
            }
            async assignEscalation(sessionId) {
                this.currentEscalation = sessionId;
                
                // Obtener room_id de la escalación guardada
                const roomId = this.getRoomIdFromEscalation(sessionId);
                
                console.log('Asignando escalación de sala:', roomId || 'no especificada');
                await this.loadAvailableAgents(roomId);
                showModal('assignAgentModal');
            }

            async assignEscalationToAgent(sessionId, agentId, reason) {
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/escalations/${sessionId}/assign`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            agent_id: agentId,
                            supervisor_id: this.getCurrentUser()?.id,
                            reason: reason
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        this.showNotification('Escalación asignada exitosamente', 'success');
                        
                        if (result.data?.assigned_agent?.name) {
                            this.showNotification(`Asignado a: ${result.data.assigned_agent.name}`, 'info');
                        }
                        
                        await this.loadEscalations();
                    } else {
                        throw new Error('Error del servidor');
                    }
                } catch (error) {
                    console.error('Error asignando escalación:', error);
                    this.showNotification('Error: ' + error.message, 'error');
                }
            }

            async takeEscalation(sessionId) {
                try {
                    this.showNotification('Tomando control de la escalación...', 'info');
                    
                    const response = await fetch(`${this.supervisorServiceUrl}/escalations/${sessionId}/take`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            supervisor_id: this.getCurrentUser()?.id
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success && result.data) {
                            console.log('✅ Escalación tomada, datos iniciales:', result.data);
                            
                            // 🔧 MEJORADO: Cargar datos completos de la sesión incluyendo ptoken
                            const completeSessionData = await this.loadCompleteSessionData(sessionId, result.data);
                            
                            console.log('Datos completos de la sesión:', completeSessionData);
                            await this.openSupervisorChat(completeSessionData);
                            this.showNotification('Escalación tomada exitosamente', 'success');
                            await this.loadEscalations();
                        } else {
                            throw new Error('Respuesta del servidor inválida');
                        }
                    } else {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Error del servidor');
                    }
                } catch (error) {
                    console.error('Error tomando escalación:', error);
                    this.showNotification('Error tomando escalación: ' + error.message, 'error');
                }
            }

            // 🆕 FUNCIÓN: Abrir chat de supervisión existente
            async openExistingSupervisionChat(sessionId) {
                try {
                    console.log(' === ABRIENDO CHAT DE SUPERVISIÓN EXISTENTE ===');
                    console.log(' Session ID:', sessionId);
                    
                    // Crear datos iniciales básicos
                    const initialData = {
                        session_data: {
                            session_id: sessionId,
                            id: sessionId
                        }
                    };
                    
                    // Cargar datos completos de la sesión
                    console.log('Cargando datos completos...');
                    const completeSessionData = await this.loadCompleteSessionData(sessionId, initialData);
                    
                    console.log('Datos completos obtenidos:', completeSessionData);
                    await this.openSupervisorChat(completeSessionData);
                    
                } catch (error) {
                    console.error('Error abriendo chat de supervisión existente:', error);
                    this.showNotification('Error abriendo chat: ' + error.message, 'error');
                }
            }

            // Cargar datos completos de la sesión
            async loadCompleteSessionData(sessionId, initialData) {
                try {
                    console.log('🔍 Cargando datos completos para sesión:', sessionId);
                    
                    // Intentar cargar desde el endpoint de chat sessions (que SÍ funciona)
                    const response = await fetch(`${CHAT_API}/chats/sessions?session_id=${sessionId}`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        console.log('Respuesta de sessions completas:', result);
                        
                        if (result.success && result.data && result.data.sessions && result.data.sessions.length > 0) {
                            const completeSession = result.data.sessions[0];
                            console.log('Sesión completa encontrada:', completeSession);
                            
                            // Combinar datos iniciales con datos completos
                            const mergedData = {
                                ...initialData,
                                session_data: {
                                    ...initialData.session_data,
                                    ...completeSession,
                                    // Asegurar que el ptoken esté disponible
                                    ptoken: completeSession.ptoken || initialData.session_data?.ptoken,
                                    patient_data: completeSession.patient_data || completeSession.user_data || initialData.session_data?.patient_data,
                                    user_data: completeSession.user_data || completeSession.patient_data || initialData.session_data?.user_data
                                }
                            };
                            
                            console.log('Datos combinados:', mergedData);
                            return mergedData;
                        }
                    }
                    
                    console.log('No se pudieron cargar datos completos, usando datos iniciales');
                    return initialData;
                    
                } catch (error) {
                    console.error('Error cargando datos completos:', error);
                    console.log('Usando datos iniciales por error:', initialData);
                    return initialData;
                }
            }

            async loadAvailableAgents(roomId = null) {
                try {
                    let url = `${CHAT_API}/chats/agents/available`;
                    
                    // SOLO filtrar por sala específica si se proporciona
                    if (roomId) {
                        url += `?room_id=${encodeURIComponent(roomId)}`;
                        console.log('Filtrando agentes presentes para sala:', roomId);
                    } else {
                        console.log('Cargando todos los agentes presentes');
                    }
                    
                    const response = await fetch(url, {
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        const agents = result.data?.agents || [];
                        console.log(`Agentes presentes encontrados${roomId ? ' para la sala' : ''}:`, agents.length);
                        this.populateAgentDropdown(agents, roomId);
                    } else {
                        throw new Error('Error cargando agentes');
                    }
                } catch (error) {
                    console.error('Error loading agents:', error);
                    this.populateAgentDropdown([], roomId);
                }
            }

            populateAgentDropdown(agents, roomId = null) {
                const dropdown = document.getElementById('agentDropdownContent');
                if (!dropdown) return;
                
                if (agents.length === 0) {
                    const message = roomId 
                        ? `No hay agentes presentes en esta sala`
                        : `No hay agentes presentes disponibles`;
                    
                    dropdown.innerHTML = `
                        <div class="dropdown-item text-gray-500">
                            ${message}
                        </div>
                    `;
                    return;
                }
                
                dropdown.innerHTML = agents.map(agent => {
                    const agentName = agent.name || 'Agente sin nombre';
                    const isOnline = agent.recently_active;
                    const activeSessions = agent.active_sessions || 0;
                    const maxChats = agent.current_room?.max_concurrent_chats || agent.max_concurrent_chats || 'N/A';
                    
                    return `
                        <div class="dropdown-item" onclick="selectAgent('${agent.id}', '${agentName}')">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="font-medium">${agentName}</div>
                                    <div class="text-xs text-gray-500">
                                        ${activeSessions}/${maxChats} chats activos
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    ${agent.current_room?.is_primary_agent ? '<span class="text-xs text-blue-600 font-medium">★</span>' : ''}
                                    <div class="w-2 h-2 ${isOnline ? 'bg-green-500' : 'bg-gray-400'} rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            async runMisdirectionAnalysis() {
                const container = document.getElementById('analysisContainer');
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="loading-spinner mx-auto mb-4"></div>
                        <p>Analizando patrones de transferencia...</p>
                    </div>
                `;
                
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/misdirection/analyze`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        this.displayAnalysisResults(result.data || {});
                    } else {
                        throw new Error('Error ejecutando análisis');
                    }
                } catch (error) {
                    console.error('Error en análisis RF6:', error);
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg class="w-12 h-12 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>Error ejecutando análisis: ${error.message}</p>
                        </div>
                    `;
                }
            }
            

            getRoomIdFromEscalation(sessionId) {
                // Buscar en las escalaciones cargadas en la última respuesta
                if (this.lastEscalationsData) {
                    const escalation = this.lastEscalationsData.find(e => e.session_id === sessionId);
                    return escalation?.current_room || escalation?.room_id || null;
                }
                return null;
            }

            displayAnalysisResults(data) {
                const container = document.getElementById('analysisContainer');
                
                console.log('📊 Datos recibidos para análisis:', data);
                
                // ✅ USAR LA ESTRUCTURA REAL DE DATOS DEL BACKEND
                const results = data.results || {};
                const diagnostics = results.diagnostics || {};
                const patterns = results.patterns || [];
                
                const analysisData = {
                    total_transfers: diagnostics.total_transfers_in_db || 0,
                    transfers_analyzed: diagnostics.transfers_in_period || 0,
                    patterns_found: data.patterns_found || 0,
                    problematic_patterns: data.problematic_patterns || 0,
                    time_period: data.time_period || '24 horas',
                    analysis_expanded: diagnostics.analysis_expanded || false
                };
                
                // Calcular tasa de éxito
                const problemRate = analysisData.transfers_analyzed > 0 
                    ? Math.round((analysisData.problematic_patterns / analysisData.transfers_analyzed) * 100)
                    : 0;
                const successRate = Math.max(0, 100 - problemRate);

                console.log('📈 Datos procesados:', analysisData);

                container.innerHTML = `
                    <div class="space-y-6">
                        <!-- Métricas principales -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Base de Datos</p>
                                        <p class="text-xl font-semibold text-gray-900">${analysisData.total_transfers.toLocaleString()}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Analizadas</p>
                                        <p class="text-xl font-semibold text-gray-900">${analysisData.transfers_analyzed.toLocaleString()}</p>
                                        <p class="text-xs text-gray-500">${analysisData.time_period}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Patrones</p>
                                        <p class="text-xl font-semibold text-gray-900">${analysisData.patterns_found}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white p-4 rounded-lg border ${analysisData.problematic_patterns > 0 ? 'border-red-200 bg-red-50' : 'border-gray-200'} hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 ${analysisData.problematic_patterns > 0 ? 'bg-red-100' : 'bg-green-100'} rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 ${analysisData.problematic_patterns > 0 ? 'text-red-600' : 'text-green-600'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${analysisData.problematic_patterns > 0 ? 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'}"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Problemáticos</p>
                                        <p class="text-xl font-semibold ${analysisData.problematic_patterns > 0 ? 'text-red-700' : 'text-green-700'}">${analysisData.problematic_patterns}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estado general del análisis -->
                        <div class="bg-white rounded-lg border ${successRate >= 80 ? 'border-green-200' : successRate >= 60 ? 'border-yellow-200' : 'border-red-200'} p-6">
                            <div class="text-center">
                                <div class="w-16 h-16 mx-auto mb-4 ${successRate >= 80 ? 'bg-green-100' : successRate >= 60 ? 'bg-yellow-100' : 'bg-red-100'} rounded-full flex items-center justify-center">
                                    <svg class="w-8 h-8 ${successRate >= 80 ? 'text-green-600' : successRate >= 60 ? 'text-yellow-600' : 'text-red-600'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${successRate >= 80 ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : successRate >= 60 ? 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'}"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold ${successRate >= 80 ? 'text-green-800' : successRate >= 60 ? 'text-yellow-800' : 'text-red-800'} mb-2">
                                    ${successRate >= 80 ? 'Rendimiento Excelente' : successRate >= 60 ? 'Atención Requerida' : 'Acción Necesaria'}
                                </h3>
                                <p class="text-gray-600 mb-3">
                                    ${analysisData.problematic_patterns} patrones problemáticos de ${analysisData.patterns_found} detectados
                                </p>
                                <div class="inline-flex items-center px-4 py-2 ${successRate >= 80 ? 'bg-green-100 text-green-800' : successRate >= 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'} rounded-full">
                                    <span class="font-medium">Tasa de éxito: ${successRate}%</span>
                                </div>
                                ${analysisData.analysis_expanded ? `
                                    <div class="mt-3">
                                        <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                                            ℹ️ Análisis expandido a ${analysisData.time_period}
                                        </span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <!-- Lista de patrones problemáticos -->
                        ${patterns.length > 0 ? `
                            <div class="bg-white rounded-lg border border-gray-200">
                                <div class="p-4 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900">Patrones Detectados</h3>
                                    <p class="text-sm text-gray-600">Rutas de transferencia que requieren revisión</p>
                                </div>
                                <div class="divide-y divide-gray-200">
                                    ${patterns.map((pattern, index) => {
                                        const rejectionReasons = pattern.rejection_reasons || [];
                                        const reasonsId = `reasons-${index}`;
                                        
                                        console.log('Debug patrón', index, '- from_room_name:', pattern.from_room_name);
                                        console.log('Debug patrón', index, '- to_room_name:', pattern.to_room_name);
                                        console.log('Debug patrón', index, '- from_room:', pattern.from_room);
                                        console.log('Debug patrón', index, '- to_room:', pattern.to_room);
                                        
                                        return `
                                            <div class="p-4 hover:bg-gray-50 border-b border-gray-100">
                                                <div class="flex items-center justify-between mb-3">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="w-8 h-8 ${
                                                            pattern.risk_level === 'high' ? 'bg-red-100 text-red-600' : 
                                                            pattern.risk_level === 'medium' ? 'bg-yellow-100 text-yellow-600' : 
                                                            'bg-green-100 text-green-600'
                                                        } rounded-full flex items-center justify-center">
                                                            <span class="text-sm font-medium">${index + 1}</span>
                                                        </div>
                                                        <div>
                                                            <h4 class="font-medium text-gray-900">
                                                                ${pattern.from_room_name || pattern.from_room} → ${pattern.to_room_name || pattern.to_room}
                                                            </h4>
                                                            <p class="text-sm text-gray-500">Ruta de transferencia</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex space-x-2">
                                                        <span class="px-2 py-1 text-xs font-medium rounded ${
                                                            pattern.risk_level === 'high' ? 'bg-red-100 text-red-800' : 
                                                            pattern.risk_level === 'medium' ? 'bg-yellow-100 text-yellow-800' : 
                                                            'bg-green-100 text-green-800'
                                                        }">
                                                            ${pattern.risk_level === 'high' ? 'Alto' : pattern.risk_level === 'medium' ? 'Medio' : 'Bajo'}
                                                        </span>
                                                        ${pattern.is_problematic ? '<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded">Problemático</span>' : ''}
                                                    </div>
                                                </div>
                                                
                                                <div class="grid grid-cols-4 gap-4 text-sm mb-4">
                                                    <div class="text-center p-2 bg-gray-50 rounded">
                                                        <div class="font-medium text-gray-900">${pattern.transfer_count || 0}</div>
                                                        <div class="text-gray-600">Total</div>
                                                    </div>
                                                    <div class="text-center p-2 bg-red-50 rounded">
                                                        <div class="font-medium text-red-700">${pattern.rejected_count || 0}</div>
                                                        <div class="text-red-600">Rechazadas</div>
                                                    </div>
                                                    <div class="text-center p-2 bg-green-50 rounded">
                                                        <div class="font-medium text-green-700">${pattern.approved_count || 0}</div>
                                                        <div class="text-green-600">Aprobadas</div>
                                                    </div>
                                                    <div class="text-center p-2 ${(pattern.rejection_rate || 0) >= 30 ? 'bg-red-50' : (pattern.rejection_rate || 0) >= 10 ? 'bg-yellow-50' : 'bg-green-50'} rounded">
                                                        <div class="font-medium ${(pattern.rejection_rate || 0) >= 30 ? 'text-red-700' : (pattern.rejection_rate || 0) >= 10 ? 'text-yellow-700' : 'text-green-700'}">
                                                            ${pattern.rejection_rate || 0}%
                                                        </div>
                                                        <div class="${(pattern.rejection_rate || 0) >= 30 ? 'text-red-600' : (pattern.rejection_rate || 0) >= 10 ? 'text-yellow-600' : 'text-green-600'}">Rechazo</div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Barra desplegable de motivos -->
                                                ${rejectionReasons.length > 0 ? `
                                                    <div class="border-t border-gray-200 pt-3">
                                                        <button onclick="toggleReasons('${reasonsId}')" 
                                                                class="flex items-center justify-between w-full p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                                                            <span class="font-medium text-gray-700">Ver motivos de rechazo (${rejectionReasons.length})</span>
                                                            <svg id="arrow-${reasonsId}" class="w-5 h-5 text-gray-400 transform transition-transform" 
                                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                            </svg>
                                                        </button>
                                                        
                                                        <div id="${reasonsId}" class="hidden mt-2 space-y-2">
                                                            ${rejectionReasons.map(rejection => `
                                                                <div class="bg-white border border-gray-200 rounded p-3 text-sm rejection-reason-card">
                                                                    <div class="font-medium text-gray-800 mb-1">${rejection.reason}</div>
                                                                    <div class="text-gray-500 text-xs">
                                                                        ${rejection.supervisor} • ${new Date(rejection.timestamp).toLocaleDateString('es-ES', {
                                                                            year: 'numeric', 
                                                                            month: 'short', 
                                                                            day: 'numeric',
                                                                            hour: '2-digit',
                                                                            minute: '2-digit'
                                                                        })}
                                                                    </div>
                                                                </div>
                                                            `).join('')}
                                                        </div>
                                                    </div>
                                                ` : ''}
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        ` : `
                            <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 mx-auto mb-3 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="font-medium text-gray-900 mb-1">Sin Patrones Problemáticos</h3>
                                <p class="text-gray-500">No se detectaron rutas problemáticas en el período analizado.</p>
                            </div>
                        `}
                        
                        <!-- Información de diagnóstico -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-3">Información de Diagnóstico</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Período:</span>
                                    <div class="font-medium text-gray-900">${analysisData.time_period}</div>
                                </div>
                                <div>
                                    <span class="text-gray-600">Antes del filtro:</span>
                                    <div class="font-medium text-gray-900">${diagnostics.patterns_before_filter || 0}</div>
                                </div>
                                <div>
                                    <span class="text-gray-600">Después del filtro:</span>
                                    <div class="font-medium text-gray-900">${diagnostics.patterns_after_filter || 0}</div>
                                </div>
                                <div>
                                    <span class="text-gray-600">Expandido:</span>
                                    <div class="font-medium text-gray-900">${analysisData.analysis_expanded ? 'Sí' : 'No'}</div>
                                </div>
                            </div>
                            <div class="mt-3 text-xs text-gray-500">
                                Última actualización: ${new Date().toLocaleString('es-ES')}
                            </div>
                        </div>
                    </div>
                `;
            }

            // ✅ FUNCIÓN PRINCIPAL PARA ABRIR CHAT DE SUPERVISOR (MEJORADA CON MEJOR DEBUGGING)
            async openSupervisorChat(sessionData) {
                try {
                    console.log('🔍 === INICIANDO APERTURA DE CHAT SUPERVISOR ===');
                    console.log('📊 sessionData recibido:', sessionData);
                    
                    const session = sessionData.session_data || sessionData;
                    console.log('📋 session extraído:', session);
                    console.log('🔑 ptoken disponible:', session.ptoken ? 'SÍ' : 'NO');
                    console.log('👤 patient_data disponible:', session.patient_data ? 'SÍ' : 'NO');
                    console.log('📄 user_data disponible:', session.user_data ? 'SÍ' : 'NO');
                    
                    currentSupervisorSession = sessionData;
                    
                    hideAllSections();
                    document.getElementById('supervisor-chat-section').classList.remove('hidden');
                    document.getElementById('sectionTitle').textContent = 'Chat de Supervisión';
                    
                    // ✅ USAR LA LÓGICA DE PTOKEN CON MEJOR DEBUGGING
                    console.log('🔍 Obteniendo información del paciente...');
                    let patientInfo = await this.getPatientInfoWithPToken(session);
                    console.log('👤 Información del paciente obtenida:', patientInfo);
                    
                    // Si los datos del paciente están vacíos, crear información básica con el session_id
                    if (this.isPatientInfoEmpty(patientInfo)) {
                        const sessionId = session.session_id || session.id;
                        console.log('⚠️ Datos del paciente vacíos, creando información básica para session:', sessionId);
                        
                        patientInfo = {
                            nombreCompleto: sessionId ? `Paciente #${sessionId.substring(0, 8)}...` : 'Paciente',
                            id: sessionId ? `Sesión: ${sessionId.substring(0, 12)}...` : 'N/A',
                            primer_nombre: 'Paciente',
                            primer_apellido: 'Sin datos',
                            telefono: '',
                            email: '',
                            ciudad: '',
                            eps: '',
                            plan: '',
                            habilitado: 'Desconocido',
                            nomTomador: ''
                        };
                        
                        console.log('📝 Información básica creada:', patientInfo);
                    } else {
                        console.log('✅ Usando información completa del paciente');
                    }
                    
                    console.log('🎨 Actualizando interfaz...');
                    this.updateSupervisorChatUI(patientInfo, session);
                    
                    const msgContainer = document.getElementById('supervisorChatMessages');
                    if (msgContainer) msgContainer.innerHTML = '';
                    
                    const chatInput = document.getElementById('supervisorMessageInput');
                    const chatButton = document.getElementById('supervisorSendButton');
                    if (chatInput) {
                        chatInput.disabled = false;
                        chatInput.placeholder = 'Escribe como supervisor...';
                    }
                    if (chatButton) {
                        chatButton.disabled = false;
                    }
                    
                    console.log('⏰ Iniciando timer...');
                    this.startSupervisorTimer(session.created_at || new Date().toISOString());
                    
                    console.log('🔌 Conectando WebSocket...');
                    await this.connectSupervisorWebSocket();
                    
                    console.log('📜 Cargando historial...');
                    await this.loadSupervisorChatHistory();
                    
                    console.log('✅ === CHAT SUPERVISOR ABIERTO EXITOSAMENTE ===');
                    
                } catch (error) {
                    console.error('❌ Error abriendo chat de supervisor:', error);
                    this.showNotification('Error al abrir chat: ' + error.message, 'error');
                }
                
            }

            updateSupervisorChatUI(patientInfo, session) {
                const fullName = patientInfo.nombreCompleto || 
                    `${patientInfo.primer_nombre} ${patientInfo.segundo_nombre} ${patientInfo.primer_apellido} ${patientInfo.segundo_apellido}`
                    .replace(/\s+/g, ' ').trim() || 
                    this.getPatientNameFromSession(session);

                const chatPatientName = document.getElementById('supervisorChatPatientName');
                if (chatPatientName) chatPatientName.textContent = fullName;

                const chatPatientInitials = document.getElementById('supervisorChatPatientInitials');
                if (chatPatientInitials) {
                    const initials = ((patientInfo.primer_nombre?.[0] || '') + (patientInfo.primer_apellido?.[0] || '')).toUpperCase() || 
                                   fullName.charAt(0).toUpperCase();
                    chatPatientInitials.textContent = initials;
                }

                const chatSessionId = document.getElementById('supervisorChatSessionId');
                if (chatSessionId) chatSessionId.textContent = session?.session_id || session?.id || 'N/A';

                const chatRoomName = document.getElementById('supervisorChatRoomName');
                if (chatRoomName) chatRoomName.textContent = session?.room_name || this.getRoomNameFromSession(session);

                this.updateSupervisorPatientInfoSidebar(patientInfo, fullName);
            }

            updateSupervisorPatientInfoSidebar(patientInfo, fullName) {
                const updates = [
                    { id: 'supervisorPatientInfoName', value: fullName },
                    { id: 'supervisorPatientInfoDocument', value: patientInfo.id || '-' },
                    { id: 'supervisorPatientInfoPhone', value: patientInfo.telefono || '-' },
                    { id: 'supervisorPatientInfoEmail', value: patientInfo.email || '-' },
                    { id: 'supervisorPatientInfoCity', value: patientInfo.ciudad || '-' },
                    { id: 'supervisorPatientInfoEPS', value: patientInfo.eps || '-' },
                    { id: 'supervisorPatientInfoPlan', value: patientInfo.plan || '-' },
                    { 
                        id: 'supervisorPatientInfoStatus', 
                        value: patientInfo.habilitado === 'S' || patientInfo.habilitado === 'Activo' || patientInfo.habilitado === 'activo' 
                            ? 'Vigente' 
                            : patientInfo.habilitado === 'N' || patientInfo.habilitado === 'Inactivo' || patientInfo.habilitado === 'inactivo'
                            ? 'Inactivo'
                            : patientInfo.habilitado || 'No especificado'
                    },
                    { id: 'supervisorPatientInfoTomador', value: patientInfo.nomTomador || '-' }
                ];

                updates.forEach(update => {
                    const element = document.getElementById(update.id);
                    if (element) {
                        element.textContent = update.value;
                    }
                });
            }

            async connectSupervisorWebSocket() {
                try {
                    if (supervisorChatSocket) {
                        supervisorChatSocket.disconnect();
                        isSupervisorConnected = false;
                        supervisorSessionJoined = false;
                    }
                    
                    const token = this.getToken();
                    const currentUser = this.getCurrentUser();
                    
                    console.log('🔌 Conectando WebSocket como supervisor:', {
                        user_id: currentUser.id,
                        user_type: 'supervisor',
                        user_name: currentUser.name,
                        session_id: currentSupervisorSession?.session_data?.session_id || currentSupervisorSession?.session_data?.id
                    });
                    
                    supervisorChatSocket = io(API_BASE, {
                        transports: ['websocket', 'polling'],
                        auth: {
                            token: token,
                            user_id: currentUser.id,
                            user_type: 'supervisor',
                            user_name: currentUser.name,
                            session_id: currentSupervisorSession?.session_data?.session_id || currentSupervisorSession?.session_data?.id
                        }
                    });
                    
                    supervisorChatSocket.on('connect', () => {
                        isSupervisorConnected = true;
                        this.updateSupervisorChatStatus('Conectado');
                        console.log('✅ WebSocket supervisor conectado exitosamente');
                        
                        setTimeout(() => {
                            this.joinSupervisorChatSession();
                        }, 500);
                    });
                    
                    supervisorChatSocket.on('disconnect', () => {
                        isSupervisorConnected = false;
                        supervisorSessionJoined = false;
                        this.updateSupervisorChatStatus('Desconectado');
                        console.log('❌ WebSocket supervisor desconectado');

                        if (!currentSupervisorSession) {
                            closeAllMobileSidebars();
                        }
                    });
                    
                    supervisorChatSocket.on('chat_joined', (data) => {
                        supervisorSessionJoined = true;
                        this.updateSupervisorChatStatus('En supervisión');
                        console.log('✅ Supervisor se unió al chat exitosamente:', data);
                    });
                    
                    supervisorChatSocket.on('new_message', (data) => {
                        console.log('💬 Nuevo mensaje recibido en supervisor:', data);
                        this.handleSupervisorNewMessage(data);
                    });
                    
                    supervisorChatSocket.on('user_typing', (data) => {
                        if (data.user_type === 'patient' && data.user_id !== currentUser.id) {
                            this.showSupervisorPatientTyping();
                        }
                    });
                    
                    supervisorChatSocket.on('user_stop_typing', (data) => {
                        if (data.user_type === 'patient' && data.user_id !== currentUser.id) {
                            this.hideSupervisorPatientTyping();
                        }
                    });
                    
                    supervisorChatSocket.on('error', (error) => {
                        console.error('❌ Error en socket de supervisor:', error);
                        this.showNotification('Error en chat: ' + (error.message || error), 'error');
                    });
                    
                } catch (error) {
                    console.error('❌ Error conectando WebSocket de supervisor:', error);
                    this.updateSupervisorChatStatus('Sin WebSocket');
                }
            }

            joinSupervisorChatSession() {
                if (!supervisorChatSocket || !currentSupervisorSession || !isSupervisorConnected) {
                    console.warn('⚠️ No se puede unir al chat - faltan condiciones:', {
                        hasSocket: !!supervisorChatSocket,
                        hasSession: !!currentSupervisorSession,
                        isConnected: isSupervisorConnected
                    });
                    return;
                }
                
                const currentUser = this.getCurrentUser();
                const sessionId = currentSupervisorSession.session_data?.session_id || currentSupervisorSession.session_data?.id;
                
                const joinData = {
                    session_id: sessionId,
                    user_id: currentUser.id,
                    user_type: 'supervisor',
                    user_name: currentUser.name
                };
                
                console.log('🤝 Supervisor uniéndose al chat:', joinData);
                
                supervisorChatSocket.emit('join_chat', joinData);
            }

            updateSupervisorChatStatus(status) {
                const statusElement = document.getElementById('supervisorChatStatus');
                if (statusElement) {
                    statusElement.textContent = status;
                    
                    statusElement.className = 'text-sm font-medium ';
                    if (status === 'En supervisión') {
                        statusElement.className += 'text-purple-600';
                    } else if (status === 'Conectado') {
                        statusElement.className += 'text-blue-600';
                    } else {
                        statusElement.className += 'text-gray-500';
                    }
                }
            }

            handleSupervisorNewMessage(data) {
                const messagesContainer = document.getElementById('supervisorChatMessages');
                if (!messagesContainer) return;

                const currentUser = this.getCurrentUser();
                
                // Mejorar la lógica de identificación del remitente
                const isMyMessage = (data.user_type === 'supervisor' && data.user_id === currentUser.id) ||
                                   (data.sender_type === 'supervisor' && data.sender_id === currentUser.id);

                const messageId = `${data.user_id || data.sender_id}_${data.user_type || data.sender_type}_${data.content.substring(0, 20)}_${Date.now()}`;
                
                console.log('🔍 Supervisor message received:', {
                    data,
                    currentUserId: currentUser.id,
                    dataUserId: data.user_id || data.sender_id,
                    dataUserType: data.user_type || data.sender_type,
                    isMyMessage: isMyMessage
                });
                
                if (sentMessages.has(messageId)) {
                    return;
                }
                sentMessages.add(messageId);

                let timestamp = data.timestamp || data.created_at || Date.now();
                if (typeof timestamp === 'string') {
                    timestamp = new Date(timestamp);
                } else if (typeof timestamp === 'number') {
                    timestamp = new Date(timestamp);
                } else {
                    timestamp = new Date();
                }

                if (isNaN(timestamp.getTime())) {
                    timestamp = new Date();
                }

                const time = timestamp.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const wrapper = document.createElement('div');
                wrapper.className = 'mb-4';

                // Determinar el tipo de remitente y la etiqueta a mostrar
                let senderLabel = 'Paciente';
                let messageClass = 'bg-gray-200 text-gray-900';
                let justifyClass = 'justify-start';
                
                if (isMyMessage) {
                    senderLabel = 'Supervisor';
                    messageClass = 'bg-purple-600 text-white';
                    justifyClass = 'justify-end';
                } else if ((data.user_type || data.sender_type) === 'agent') {
                    senderLabel = 'Agente';
                    messageClass = 'bg-blue-200 text-blue-900';
                    justifyClass = 'justify-end';
                }

                wrapper.innerHTML = `
                    <div class="flex ${justifyClass}">
                        <div class="max-w-xs lg:max-w-md ${messageClass} rounded-lg px-4 py-2">
                            <div class="text-xs ${isMyMessage ? 'opacity-75' : 'font-medium text-gray-600'} mb-1">${senderLabel}</div>
                            <p>${this.escapeHtml(data.content)}</p>
                            <div class="text-xs ${isMyMessage ? 'opacity-75' : 'text-gray-500'} mt-1 ${isMyMessage ? 'text-right' : ''}">${time}</div>
                        </div>
                    </div>`;

                messagesContainer.appendChild(wrapper);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }

            async loadSupervisorChatHistory() {
                if (!currentSupervisorSession?.session_data) return;
                
                const messagesContainer = document.getElementById('supervisorChatMessages');
                if (!messagesContainer) return;

                messagesContainer.innerHTML = '';
                
                try {
                    const sessionId = currentSupervisorSession.session_data.session_id || currentSupervisorSession.session_data.id;
                    
                    console.log('📜 Cargando historial del chat para sesión:', sessionId);
                    
                    const response = await fetch(`${CHAT_API}/messages/${sessionId}?limit=50`, {
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        
                        if (result.success && result.data && result.data.messages) {
                            console.log('📋 Historial cargado:', result.data.messages.length, 'mensajes');
                            
                            result.data.messages.forEach((msg) => {
                                this.renderSupervisorMessageFromHistory(msg);
                            });
                            
                            setTimeout(() => {
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            }, 100);
                            return;
                        }
                    }
                    
                    console.log('📭 No hay mensajes en el historial');
                    messagesContainer.innerHTML = '<div class="text-center py-8 text-gray-500">No hay mensajes en el historial</div>';
                    
                } catch (error) {
                    console.error('❌ Error cargando historial del supervisor:', error);
                    this.showNotification('Error cargando historial de chat', 'warning');
                    messagesContainer.innerHTML = '<div class="text-center py-8 text-gray-500">Error cargando historial</div>';
                }
            }

            renderSupervisorMessageFromHistory(msg) {
                const messagesContainer = document.getElementById('supervisorChatMessages');
                if (!messagesContainer) return;

                const currentUser = this.getCurrentUser();
                
                // Mejorar la lógica de identificación del remitente para historial
                const isMyMessage = (msg.sender_type === 'supervisor' && msg.sender_id === currentUser.id) ||
                                   (msg.user_type === 'supervisor' && msg.user_id === currentUser.id);

                let timestamp = msg.timestamp || msg.created_at;
                if (typeof timestamp === 'string') {
                    timestamp = new Date(timestamp);
                } else if (typeof timestamp === 'number') {
                    timestamp = new Date(timestamp);
                } else {
                    timestamp = new Date();
                }

                if (isNaN(timestamp.getTime())) {
                    timestamp = new Date();
                }

                const time = timestamp.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const wrapper = document.createElement('div');
                wrapper.className = 'mb-4';

                // Determinar el tipo de remitente y los estilos
                let senderLabel = 'Paciente';
                let messageClass = 'bg-gray-200 text-gray-900';
                let justifyClass = 'justify-start';
                let labelClass = 'font-medium text-gray-600';
                let timeClass = 'text-gray-500';
                
                if (isMyMessage) {
                    senderLabel = 'Supervisor';
                    messageClass = 'bg-purple-600 text-white';
                    justifyClass = 'justify-end';
                    labelClass = 'opacity-75';
                    timeClass = 'opacity-75';
                } else if ((msg.sender_type || msg.user_type) === 'agent') {
                    senderLabel = 'Agente';
                    messageClass = 'bg-blue-200 text-blue-900';
                    justifyClass = 'justify-end';
                    labelClass = 'opacity-75';
                    timeClass = 'opacity-75';
                }

                wrapper.innerHTML = `
                    <div class="flex ${justifyClass}">
                        <div class="max-w-xs lg:max-w-md ${messageClass} rounded-lg px-4 py-2">
                            <div class="text-xs ${labelClass} mb-1">${senderLabel}</div>
                            <p>${this.escapeHtml(msg.content)}</p>
                            <div class="text-xs ${timeClass} mt-1 ${isMyMessage || (msg.sender_type || msg.user_type) === 'agent' ? 'text-right' : ''}">${time}</div>
                        </div>
                    </div>`;

                messagesContainer.appendChild(wrapper);
            }

            showSupervisorPatientTyping() {
                const indicator = document.getElementById('supervisorTypingIndicator');
                if (indicator) {
                    indicator.classList.remove('hidden');
                }
            }

            hideSupervisorPatientTyping() {
                const indicator = document.getElementById('supervisorTypingIndicator');
                if (indicator) {
                    indicator.classList.add('hidden');
                }
            }

            startSupervisorTimer(startTime) {
                this.stopSupervisorTimer();
                
                const timerElement = document.getElementById('supervisorChatTimer');
                if (!timerElement) return;

                const startDate = new Date(startTime);
                
                function updateTimer() {
                    const now = new Date();
                    const diff = now - startDate;
                    const totalMinutes = diff / (1000 * 60);
                    
                    timerElement.textContent = `• ${supervisorClient.formatTime(totalMinutes)}`;
                    timerElement.className = 'timer-display ml-2 text-purple-600';
                }
                
                updateTimer();
                supervisorTimerInterval = setInterval(updateTimer, 1000);
            }

            stopSupervisorTimer() {
                if (supervisorTimerInterval) {
                    clearInterval(supervisorTimerInterval);
                    supervisorTimerInterval = null;
                }
                
                const timerElement = document.getElementById('supervisorChatTimer');
                if (timerElement) {
                    timerElement.textContent = '';
                }
            }

            formatTime(minutes) {
                const mins = Math.floor(minutes);
                const secs = Math.floor((minutes - mins) * 60);
                return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
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

            updateNavCounter(elementId, count) {
                const element = document.getElementById(elementId);
                const mobileElement = document.getElementById('mobile' + elementId.charAt(0).toUpperCase() + elementId.slice(1));
                
                [element, mobileElement].forEach(el => {
                    if (el) {
                        el.textContent = count;
                        if (count > 0) {
                            el.classList.remove('hidden');
                            el.classList.add('animate-pulse');
                            setTimeout(() => {
                                el.classList.remove('animate-pulse');
                            }, 2000);
                        } else {
                            el.classList.add('hidden');
                        }
                    }
                });
            }

            getTimeAgo(timestamp) {
                try {
                    const now = new Date();
                    const time = new Date(timestamp);
                    const diffMs = now - time;
                    const diffMins = Math.floor(diffMs / 60000);
                    
                    if (diffMins < 1) return 'Ahora';
                    if (diffMins < 60) return `${diffMins} min`;
                    
                    const diffHours = Math.floor(diffMins / 60);
                    if (diffHours < 24) return `${diffHours}h`;
                    
                    const diffDays = Math.floor(diffHours / 24);
                    return `${diffDays}d`;
                } catch (error) {
                    return 'N/A';
                }
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

            startAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
                
                this.refreshInterval = setInterval(async () => {
                    try {
                        const activeSection = document.querySelector('.section-content:not(.hidden)');
                        if (activeSection) {
                            const sectionId = activeSection.id;
                            if (sectionId === 'transfers-section') {
                                await this.loadTransfers();
                            } else if (sectionId === 'escalations-section') {
                                await this.loadEscalations();
                            }
                        }
                        
                        // 🔧 NUEVO: Siempre actualizar escalaciones en background para detectar nuevas
                        if (activeSection?.id !== 'escalations-section') {
                            console.log('🔄 Actualizando escalaciones en background...');
                            await this.loadEscalations();
                        }
                        
                    } catch (error) {
                        console.warn('⚠️ Error en auto-refresh:', error);
                    }
                }, this.refreshIntervalTime);
            }

            stopAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                    this.refreshInterval = null;
                }
            }
            // ========== MÉTODOS PARA "MI PANEL" ==========

        async loadMyInfo() {
            try {
                const response = await fetch(`${API_BASE}/agent-assignments/my-info`, {
                    method: 'GET',
                    headers: this.getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    this.displayMyAssignments(result.data || {});
                } else {
                    throw new Error('Error cargando información');
                }
            } catch (error) {
                console.error('Error loading my info:', error);
                this.showNotification('Error cargando mis asignaciones: ' + error.message, 'error');
            }
        }

        displayMyAssignments(data) {
            const container = document.getElementById('myAssignmentsContainer');
            if (!container) return;

            const assignments = data.assignments?.rooms || [];
            
            if (assignments.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <p>No tienes asignaciones activas</p>
                        <p class="text-sm text-gray-500 mt-2">Tienes acceso a todas las salas</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="space-y-3">
                    ${assignments.map(assignment => `
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-gray-900">${assignment.room_name}</h4>
                                <span class="px-2 py-1 text-xs font-medium rounded ${
                                    assignment.is_primary_agent ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'
                                }">
                                    ${assignment.is_primary_agent ? 'Principal' : 'Secundario'}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
                                <div><span class="font-medium">Prioridad:</span> ${assignment.priority}</div>
                                <div><span class="font-medium">Max Chats:</span> ${assignment.max_concurrent_chats}</div>
                                <div class="col-span-2"><span class="font-medium">Estado:</span> 
                                    <span class="capitalize ${assignment.assignment_status === 'active' ? 'text-green-600' : 'text-gray-600'}">
                                        ${assignment.assignment_status}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                
                <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                    <div class="text-sm text-blue-800">
                        <strong>${assignments.length}</strong> asignación(es) activa(s)
                    </div>
                </div>
            `;
        }
        // ========== MÉTODOS PARA "MIS SALAS", "MIS SESIONES" Y "MIS HORARIOS" ==========
        async loadMyRooms() {
            try {
                const response = await fetch(`${API_BASE}/agent-assignments/my-rooms`, {
                    method: 'GET',
                    headers: this.getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    this.displayMyRooms(result.data || {});
                } else {
                    throw new Error('Error cargando salas');
                }
            } catch (error) {
                console.error('Error loading my rooms:', error);
                this.showNotification('Error cargando mis salas: ' + error.message, 'error');
            }
        }

        displayMyRooms(data) {
            const container = document.getElementById('myRoomsContainer');
            if (!container) return;

            const rooms = data.rooms || [];
            
            if (rooms.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <p>No se encontraron salas</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="space-y-3">
                    ${rooms.map(room => `
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-gray-900">${room.name || room.room_name}</h4>
                                ${room.is_assigned ? '<span class="text-xs text-blue-600">✓ Asignado</span>' : ''}
                            </div>
                            <p class="text-sm text-gray-600">${room.description || room.room_description || 'Sin descripción'}</p>
                            ${room.room_type ? `<p class="text-xs text-gray-500 mt-1">Tipo: ${room.room_type}</p>` : ''}
                        </div>
                    `).join('')}
                </div>
                
                <div class="mt-4 p-4 bg-purple-50 rounded-lg">
                    <div class="text-sm text-purple-800">
                        <strong>${rooms.length}</strong> sala(s) disponible(s) • Tipo: <strong>${data.access_type || 'all_rooms'}</strong>
                    </div>
                </div>
            `;
        }

        async loadMySessions() {
            try {
                const response = await fetch(`${API_BASE}/agent-assignments/my-sessions?status=all&limit=20`, {
                    method: 'GET',
                    headers: this.getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    this.displayMySessions(result.data || {});
                } else {
                    throw new Error('Error cargando sesiones');
                }
            } catch (error) {
                console.error('Error loading my sessions:', error);
                this.showNotification('Error cargando mis sesiones: ' + error.message, 'error');
            }
        }

        displayMySessions(data) {
            const container = document.getElementById('mySessionsContainer');
            if (!container) return;

            const sessions = data.sessions || [];
            
            if (sessions.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p>No tienes sesiones activas</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sala</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duración</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${sessions.map(session => `
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">${session.room_name || 'N/A'}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">${session.user_name || 'Usuario'}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs font-medium rounded ${
                                            session.status === 'active' ? 'bg-green-100 text-green-800' :
                                            session.status === 'waiting' ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-gray-100 text-gray-800'
                                        }">
                                            ${session.status}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">${session.duration_minutes || 0} min</td>
                                    <td class="px-4 py-3">
                                        ${session.status === 'active' ? `
                                            <button onclick="supervisorClient.openExistingSupervisionChat('${session.id || session.session_id}')"
                                                    class="text-xs px-2 py-1 bg-purple-600 text-white rounded hover:bg-purple-700">
                                                Ver
                                            </button>
                                        ` : '-'}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                
                ${data.working_status ? `
                    <div class="mt-4 p-4 ${data.working_status.can_receive_new_sessions ? 'bg-green-50' : 'bg-yellow-50'} rounded-lg">
                        <div class="text-sm ${data.working_status.can_receive_new_sessions ? 'text-green-800' : 'text-yellow-800'}">
                            <strong>Estado:</strong> ${data.working_status.message || 'N/A'}
                        </div>
                    </div>
                ` : ''}
            `;
        }

        async loadMySchedules() {
            try {
                const response = await fetch(`${API_BASE}/agent-assignments/my-schedules`, {
                    method: 'GET',
                    headers: this.getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    this.displayMySchedules(result.data || {});
                } else {
                    throw new Error('Error cargando horarios');
                }
            } catch (error) {
                console.error('Error loading my schedules:', error);
                this.showNotification('Error cargando mis horarios: ' + error.message, 'error');
            }
        }

        displayMySchedules(data) {
            const container = document.getElementById('mySchedulesContainer');
            if (!container) return;

            const schedulesByRoom = data.schedules_by_room || [];
            
            if (schedulesByRoom.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <p>No tienes horarios configurados</p>
                    </div>
                `;
                return;
            }

            const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

            container.innerHTML = `
                <div class="space-y-6">
                    ${schedulesByRoom.map(roomSchedule => `
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-3">${roomSchedule.room_name}</h4>
                            <div class="space-y-2">
                                ${roomSchedule.schedules.map(schedule => `
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                        <div class="flex items-center gap-3">
                                            <span class="font-medium text-gray-900">${dayNames[schedule.day_of_week]}</span>
                                            <span class="text-gray-600">${schedule.start_time} - ${schedule.end_time}</span>
                                        </div>
                                        <span class="px-2 py-1 text-xs font-medium rounded ${
                                            schedule.is_available ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                        }">
                                            ${schedule.is_available ? 'Disponible' : 'No disponible'}
                                        </span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `).join('')}
                </div>
                
                <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                    <div class="text-sm text-blue-800">
                        <strong>${data.total_schedules}</strong> horario(s) en <strong>${data.total_assignments}</strong> sala(s)
                    </div>
                </div>
            `;
        }

        // ========== MONITOR DE SALAS ==========
        async getRoomStatistics(room, timeframe = '24h') {
    try {
        console.log('📊 Obteniendo estadísticas para sala:', room.name || room.room_name);
        
        const roomId = room.id || room.room_id;
        
        // Construir URL con parámetros
        const params = new URLSearchParams({
            room_id: roomId,
            timeframe: timeframe
        });
        
        const response = await fetch(`${API_BASE}/admin/supervisor/rooms/${roomId}/statistics?${params}`, {
            method: 'GET',
            headers: this.getAuthHeaders()
        });
        
        if (!response.ok) {
            console.warn('⚠️ Error obteniendo estadísticas, usando valores por defecto');
            // Retornar estadísticas vacías en caso de error
            return {
                room_id: roomId,
                room_name: room.name || room.room_name,
                room_description: room.description || '',
                statistics: {
                    sessions: { active: 0, waiting: 0, completed: 0, abandoned: 0, total: 0 },
                    performance: { avg_duration_minutes: 0, avg_wait_time_minutes: 0, completion_rate: 0 },
                    agents: { total: 0, available: 0, busy: 0 },
                    transfers: { outgoing: 0, incoming: 0, rejected: 0 },
                    trend: { direction: 'stable', percentage: 0 }
                },
                last_updated: new Date().toISOString()
            };
        }
        
        const result = await response.json();
        
        if (result.success && result.data) {
            return {
                room_id: roomId,
                room_name: room.name || room.room_name,
                room_description: room.description || '',
                statistics: result.data.statistics || {},
                last_updated: result.data.last_updated || new Date().toISOString()
            };
        }
        
        // Si no hay datos, retornar estructura vacía
        return {
            room_id: roomId,
            room_name: room.name || room.room_name,
            room_description: room.description || '',
            statistics: {
                sessions: { active: 0, waiting: 0, completed: 0, abandoned: 0, total: 0 },
                performance: { avg_duration_minutes: 0, avg_wait_time_minutes: 0, completion_rate: 0 },
                agents: { total: 0, available: 0, busy: 0 },
                transfers: { outgoing: 0, incoming: 0, rejected: 0 },
                trend: { direction: 'stable', percentage: 0 }
            },
            last_updated: new Date().toISOString()
        };
        
    } catch (error) {
        console.error('❌ Error en getRoomStatistics:', error);
        throw error;
    }
}

    // ========== MONITOR DE SALAS MEJORADO ==========
async loadMyMonitor() {
    try {
        console.log('📊 === CARGANDO MONITOR DE SALAS ===');
        
        const timeframe = document.getElementById('statsTimeframe')?.value || '24h';
        
        this.showMonitorLoading();
        
        // ✅ USAR EL ENDPOINT CORRECTO QUE DEVUELVE TODAS LAS SALAS
        const url = `${API_BASE}/admin/supervisor/rooms/statistics?timeframe=${timeframe}`;
        console.log('🌐 URL completa:', url);
        
        const response = await fetch(url, {
            headers: this.getAuthHeaders()
        });
        
        console.log('📡 Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const result = await response.json();
        console.log('📦 Resultado completo del backend:', result);
        
        if (!result.success || !result.data) {
            throw new Error('Respuesta inválida del servidor');
        }
        
        const roomsData = result.data.rooms || [];
        const summary = result.data.summary || {};
        
        console.log('🏠 Salas recibidas:', roomsData.length);
        console.log('📊 Summary:', summary);
        
        if (roomsData.length > 0) {
            console.log('🔍 Primera sala de ejemplo:', roomsData[0]);
        }
        
        // Mostrar datos
        this.displayMonitorData({
            rooms: roomsData,
            summary,
            timeframe,
            total_rooms: roomsData.length
        });
        
        console.log('✅ Monitor cargado exitosamente');
        
    } catch (error) {
        console.error('❌ Error en loadMyMonitor:', error);
        console.error('❌ Error stack:', error.stack);
        this.showMonitorError(error.message);
    }
}
getEmptyStats() {
    return {
        sessions: { active: 0, waiting: 0, completed: 0, abandoned: 0, total: 0 },
        performance: { avg_duration_minutes: 0, avg_wait_time_minutes: 0, completion_rate: 0 },
        agents: { total: 0, available: 0, busy: 0 },
        transfers: { outgoing: 0, incoming: 0, rejected: 0 },
        trend: { direction: 'stable', percentage: 0 }
    };
}

calculateSummary(roomsData) {
    return {
        total_active: roomsData.reduce((sum, r) => sum + r.active_count, 0),
        total_waiting: roomsData.reduce((sum, r) => sum + r.waiting_count, 0),
        total_completed: roomsData.reduce((sum, r) => sum + (r.statistics?.sessions?.completed || 0), 0),
        avg_completion_rate: roomsData.length > 0 
            ? (roomsData.reduce((sum, r) => sum + parseFloat(r.statistics?.performance?.completion_rate || 0), 0) / roomsData.length).toFixed(2)
            : 0
    };
}

showMonitorLoading() {
    const container = document.getElementById('roomsStatsContainer');
    if (container) {
        container.innerHTML = `
            <div class="col-span-full flex items-center justify-center py-20">
                <div class="text-center">
                    <div class="loading-spinner mx-auto mb-4"></div>
                    <p class="text-gray-500">Cargando estadísticas...</p>
                </div>
            </div>
        `;
    }
}

showMonitorEmpty() {
    const container = document.getElementById('roomsStatsContainer');
    if (container) {
        container.innerHTML = `
            <div class="col-span-full text-center py-20">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <p class="text-gray-500">No tienes salas asignadas</p>
            </div>
        `;
    }
}

showMonitorError(message) {
    const container = document.getElementById('roomsStatsContainer');
    if (container) {
        container.innerHTML = `
            <div class="col-span-full text-center py-20">
                <svg class="w-16 h-16 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-red-500 font-medium">Error cargando estadísticas</p>
                <p class="text-gray-500 text-sm mt-2">${message}</p>
                <button onclick="supervisorClient.loadMyMonitor()" 
                        class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Reintentar
                </button>
            </div>
        `;
    }
}

displayMonitorData(data) {
    // Actualizar resumen
    this.updateStatsSummary(data.summary);
    
    // Renderizar salas
    const container = document.getElementById('roomsStatsContainer');
    if (!container) return;
    
    container.innerHTML = data.rooms.map(room => this.createRoomMonitorCard(room)).join('');
    
    // Setup handlers después de renderizar
    this.setupRoomTabHandlers();
}

updateStatsSummary(summary) {
    const updates = [
        { id: 'summaryTotalActive', value: summary.total_active || 0 },
        { id: 'summaryTotalWaiting', value: summary.total_waiting || 0 },
        { id: 'summaryTotalCompleted', value: summary.total_completed || 0 },
        { id: 'summaryAvgCompletion', value: `${summary.avg_completion_rate || 0}%` }
    ];
    
    updates.forEach(update => {
        const element = document.getElementById(update.id);
        if (element) element.textContent = update.value;
    });
}
// ========== FUNCIONES DE CONTROL DE TABS ==========

setupRoomTabHandlers() {
    // Esta función se llamará después de renderizar las salas
    // Permite manejar los tabs dinámicamente
}

switchRoomTab(roomId, tab) {
    console.log(`🔄 Cambiando a tab: ${tab} en sala: ${roomId}`);
    
    // Actualizar tabs
    const allTabs = document.querySelectorAll(`[id^="tab-${roomId}-"]`);
    allTabs.forEach(tabBtn => {
        tabBtn.classList.remove('active');
    });
    
    const activeTab = document.getElementById(`tab-${roomId}-${tab}`);
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // Actualizar contenido
    const allContents = document.querySelectorAll(`[id^="content-${roomId}-"]`);
    allContents.forEach(content => {
        content.classList.add('hidden');
    });
    
    const activeContent = document.getElementById(`content-${roomId}-${tab}`);
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
}

switchRoomTab(roomId, tab) {
    console.log(`🔄 Cambiando a tab: ${tab} en sala: ${roomId}`);
    
    // Actualizar tabs
    const allTabs = document.querySelectorAll(`[id^="tab-${roomId}-"]`);
    allTabs.forEach(tabBtn => {
        tabBtn.classList.remove('active');
    });
    
    const activeTab = document.getElementById(`tab-${roomId}-${tab}`);
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // Actualizar contenido
    const allContents = document.querySelectorAll(`[id^="content-${roomId}-"]`);
    allContents.forEach(content => {
        content.classList.add('hidden');
    });
    
    const activeContent = document.getElementById(`content-${roomId}-${tab}`);
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
}

async loadRoomSessions(roomId) {
    const container = document.getElementById(`sessions-list-${roomId.replace(/[^a-zA-Z0-9]/g, '_')}`);
    if (!container) return;
    
    container.innerHTML = `
        <div class="text-center py-8">
            <div class="loading-spinner mx-auto mb-4"></div>
            <p class="text-gray-500">Cargando sesiones...</p>
        </div>
    `;
    
    try {
        const response = await fetch(`${API_BASE}/admin/supervisor/rooms/${roomId}/sessions?status=all&limit=20`, {
            headers: this.getAuthHeaders()
        });
        
        if (!response.ok) throw new Error('Error cargando sesiones');
        
        const result = await response.json();
        const sessions = result.data?.sessions || [];
        
        if (sessions.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p class="text-sm">No hay sesiones en esta sala</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="space-y-3">
                ${sessions.map(session => `
                    <div class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center">
                                    <span class="text-white font-semibold">${(session.patient_name || 'P').charAt(0)}</span>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">${session.patient_name || 'Paciente'}</p>
                                    <p class="text-xs text-gray-500">${session.agent_name || 'Sin asignar'}</p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded ${
                                session.status === 'waiting' ? 'bg-yellow-100 text-yellow-800' :
                                session.status === 'active' ? 'bg-green-100 text-green-800' :
                                'bg-gray-100 text-gray-800'
                            }">
                                ${session.status}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">
                                ${session.duration_minutes} min • ${session.message_count} mensajes
                            </span>
                            ${session.status === 'active' || session.status === 'waiting' ? `
                                <button onclick="supervisorClient.openObserverChat('${session.id}')" 
                                        class="px-3 py-1 bg-purple-600 text-white text-xs rounded hover:bg-purple-700">
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Observar
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        
    } catch (error) {
        console.error('❌ Error cargando sesiones:', error);
        container.innerHTML = `
            <div class="text-center py-8 text-red-500">
                <p>Error cargando sesiones</p>
            </div>
        `;
    }
}

// ========== FIN FUNCIONES DE CONTROL DE TABS ==========

createRoomMonitorCard(room) {
    console.log('🎴 Creando card para:', room.room_name);
    console.log('📊 Datos completos de la sala:', room);

    // ✅ Extracción de datos mejorada
    const sessions = room.sessions || {
        total: 0,
        active: 0,
        waiting: 0,
        completed: 0,
        abandoned: 0,
        attendance_rate: 0,
        abandonment_rate: 0,
        avg_duration: 0,
        completion_rate: 0
    };

    const agents = room.agents || {
        total_assigned: 0,
        currently_active: 0,
        available_now: 0,
        on_session: 0,
        utilization_rate: 0
    };

    const messages = room.messages || {
        total_today: 0,
        from_patients: 0,
        from_agents: 0,
        avg_per_session: 0
    };

    // ✅ Usar los valores directos
    const activeSessions = sessions.active || 0;
    const waitingSessions = sessions.waiting || 0;
    const completedSessions = sessions.completed || 0;
    const abandonedSessions = sessions.abandoned || 0;
    const avgDuration = sessions.avg_duration || 0;
    const completionRate = sessions.completion_rate || 0;

    // ✅ Calcular capacidad
    const capacityPercent = agents.total_assigned > 0
        ? ((agents.on_session / agents.total_assigned) * 100).toFixed(0)
        : 0;
    const capacityColor = capacityPercent > 80 ? 'bg-red-500'
                        : capacityPercent > 50 ? 'bg-yellow-500'
                        : 'bg-green-500';

    const hasActiveSessions = activeSessions > 0 || waitingSessions > 0;

    // ✅ ID único para tabs
    const safeRoomId = room.room_id.replace(/[^a-zA-Z0-9]/g, '_');

    // ✅ Mantiene TODA la estructura de la primera función
    return `
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <!-- Header de la Sala -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold text-gray-900">${room.room_name}</h3>
                    <span class="px-3 py-1 text-xs font-medium rounded-full ${
                        hasActiveSessions ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                    }">
                        ${hasActiveSessions ? 'Activa' : 'Inactiva'}
                    </span>
                </div>
                <p class="text-sm text-gray-500">${room.room_description || 'Sala de chat'}</p>
            </div>

            <!-- Tabs: Estadísticas / Sesiones -->
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button onclick="supervisorClient.switchRoomTab('${safeRoomId}', 'stats')" 
                            id="tab-${safeRoomId}-stats"
                            class="room-tab active px-4 py-3 text-sm font-medium border-b-2">
                        Estadísticas
                    </button>
                    <button onclick="supervisorClient.switchRoomTab('${safeRoomId}', 'sessions')" 
                            id="tab-${safeRoomId}-sessions"
                            class="room-tab px-4 py-3 text-sm font-medium border-b-2">
                        Sesiones (${activeSessions + waitingSessions})
                        ${hasActiveSessions ? `<span class="ml-2 w-2 h-2 bg-green-500 rounded-full inline-block"></span>` : ''}
                    </button>
                </nav>
            </div>

            <!-- Contenido: Estadísticas -->
            <div id="content-${safeRoomId}-stats" class="room-content p-6">
                <div class="space-y-4">
                    <!-- Sesiones -->
                    <div>
                        <h4 class="text-xs font-medium text-gray-500 uppercase mb-3">Sesiones</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="text-center p-3 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">${activeSessions}</div>
                                <div class="text-xs text-green-700">Activas</div>
                            </div>
                            <div class="text-center p-3 bg-yellow-50 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600">${waitingSessions}</div>
                                <div class="text-xs text-yellow-700">En Espera</div>
                            </div>
                            <div class="text-center p-3 bg-blue-50 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">${completedSessions}</div>
                                <div class="text-xs text-blue-700">Completadas</div>
                            </div>
                            <div class="text-center p-3 bg-red-50 rounded-lg">
                                <div class="text-2xl font-bold text-red-600">${abandonedSessions}</div>
                                <div class="text-xs text-red-700">Abandonadas</div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance -->
                    <div>
                        <h4 class="text-xs font-medium text-gray-500 uppercase mb-3">Rendimiento</h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                <span class="text-sm text-gray-600">Tiempo promedio</span>
                                <span class="font-semibold text-gray-900">${avgDuration.toFixed(1)} min</span>
                            </div>
                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                <span class="text-sm text-gray-600">Tasa de éxito</span>
                                <span class="font-semibold text-gray-900">${completionRate.toFixed(1)}%</span>
                            </div>
                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                <span class="text-sm text-gray-600">Tasa abandono</span>
                                <span class="font-semibold text-gray-900">${sessions.abandonment_rate || 0}%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Agentes -->
                    <div>
                        <h4 class="text-xs font-medium text-gray-500 uppercase mb-3">Agentes</h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Total</span>
                                <span class="font-semibold text-gray-900">${agents.total_assigned || 0}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Disponibles</span>
                                <span class="font-semibold text-green-600">${agents.available_now || 0}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">En sesión</span>
                                <span class="font-semibold text-yellow-600">${agents.on_session || 0}</span>
                            </div>
                            <div class="mt-2">
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                    <span>Capacidad</span>
                                    <span>${capacityPercent}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="${capacityColor} h-2 rounded-full transition-all" style="width: ${capacityPercent}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mensajes -->
                    <div>
                        <h4 class="text-xs font-medium text-gray-500 uppercase mb-3">Mensajes</h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Total</span>
                                <span class="font-semibold text-gray-900">${messages.total_today || 0}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">De pacientes</span>
                                <span class="font-semibold text-blue-600">${messages.from_patients || 0}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">De agentes</span>
                                <span class="font-semibold text-purple-600">${messages.from_agents || 0}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Promedio/sesión</span>
                                <span class="font-semibold text-gray-900">${messages.avg_per_session || 0}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenido: Sesiones -->
            <div id="content-${safeRoomId}-sessions" class="room-content hidden p-6">
                <div class="flex items-center justify-between mb-4">
                    <button onclick="supervisorClient.loadRoomSessions('${room.room_id}')" 
                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Cargar Sesiones
                    </button>
                </div>
                <div id="sessions-list-${safeRoomId}">
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p class="text-sm">Haz clic en "Cargar Sesiones" para ver las sesiones activas</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span>Última actualización</span>
                    <span>${new Date().toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}</span>
                </div>
            </div>
        </div>
    `;
}

createSessionItem(session, roomId) {
    const userName = session.user_name || 'Usuario';
    const agentName = session.agent_name || 'Sin asignar';
    const duration = Math.round(session.duration_minutes || session.waiting_time_minutes || 0);
    const sessionId = session.id || session.session_id;
    const isWaiting = session.status === 'waiting';
    
    return `
        <div class="p-3 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-500 rounded-full flex items-center justify-center">
                        <span class="text-white text-xs font-semibold">${userName.charAt(0).toUpperCase()}</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">${userName}</p>
                        <p class="text-xs text-gray-500">${agentName}</p>
                    </div>
                </div>
                <span class="px-2 py-1 text-xs rounded ${isWaiting ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}">
                    ${isWaiting ? 'Esperando' : 'Activo'}
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-500">
                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    ${duration} min
                </span>
                <button onclick="supervisorClient.openObserverChat('${sessionId}')" 
                        class="px-3 py-1 bg-purple-600 text-white text-xs rounded hover:bg-purple-700">
                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Observar
                </button>
            </div>
        </div>
    `;
}

changeStatsTimeframe() {
    this.loadMyMonitor();
}

refreshRoomsStats() {
    this.showNotification('Actualizando estadísticas...', 'info', 1000);
    this.loadMyMonitor();
}

        displayRoomsStatistics(data) {
    console.log('🎨 === RENDERIZANDO ESTADÍSTICAS ===');
    console.log('📊 Data recibida:', data);
    
    const container = document.getElementById('roomsStatsContainer');
    if (!container) {
        console.error('❌ No se encontró el contenedor roomsStatsContainer');
        return;
    }
    
    const rooms = data.rooms || [];
    const summary = data.summary || {};
    
    console.log('📊 Salas a renderizar:', rooms.length);
    console.log('📈 Resumen:', summary);
    
    // Actualizar resumen general
    this.updateStatsSummary(summary);
    
    if (rooms.length === 0) {
        console.log('⚠️ No hay salas para mostrar');
        container.innerHTML = `
            <div class="col-span-full text-center py-20">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <p class="text-gray-500">No hay salas disponibles</p>
            </div>
        `;
        return;
    }
    
    try {
        console.log('🔨 Generando HTML de las salas...');
        const roomsHTML = rooms.map((room, index) => {
            console.log(`🏠 Renderizando sala ${index + 1}:`, room.room_name);
            return this.createRoomStatsCard(room);
        }).join('');
        
        console.log('✅ HTML generado, insertando en DOM...');
        container.innerHTML = roomsHTML;
        console.log('✅ === RENDERIZADO COMPLETO ===');
        
    } catch (error) {
        console.error('❌ Error renderizando:', error);
        container.innerHTML = `
            <div class="col-span-full text-center py-20">
                <svg class="w-16 h-16 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-red-500 font-medium">Error al mostrar las salas</p>
                <p class="text-gray-500 text-sm mt-2">${error.message}</p>
            </div>
        `;
    }
}

updateStatsSummary(summary) {
    console.log('📊 Actualizando resumen en UI:', summary);
    
    const updates = [
        { id: 'summaryTotalActive', value: summary.total_active || 0 },
        { id: 'summaryTotalWaiting', value: summary.total_waiting || 0 },
        { id: 'summaryTotalCompleted', value: summary.total_completed || 0 },
        { id: 'summaryAvgCompletion', value: `${summary.avg_completion_rate || 0}%` }
    ];
    
    updates.forEach(update => {
        const element = document.getElementById(update.id);
        if (element) {
            element.textContent = update.value;
            console.log(`✅ Actualizado ${update.id}: ${update.value}`);
        } else {
            console.warn(`⚠️ No se encontró elemento ${update.id}`);
        }
    });
}

createRoomStatsCard(room) {
    console.log('🎴 Creando card para:', room.room_name);
    
    const stats = room.statistics || {};
    
    // ✅ USAR LOS CONTADORES DIRECTOS EN LUGAR DE statistics.sessions
    const activeSessions = room.active_count || 0;
    const waitingSessions = room.waiting_count || 0;
    const completedSessions = stats.sessions?.completed || 0;
    const abandonedSessions = stats.sessions?.abandoned || 0;
    
    console.log('📊 Usando contadores directos:', {
        active: activeSessions,
        waiting: waitingSessions,
        completed: completedSessions,
        abandoned: abandonedSessions
    });
    
    const performance = stats.performance || {};
    const agents = stats.agents || {};
    const trend = stats.trend || { direction: 'stable', percentage: 0 };
    
    
    return `
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
            <!-- Header -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold text-gray-900">${room.room_name || 'Sala'}</h3>
                    <span class="px-3 py-1 text-xs font-medium rounded-full ${
                        activeSessions > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                    }">
                        ${activeSessions > 0 ? 'Activa' : 'Inactiva'}
                    </span>
                </div>
                <p class="text-sm text-gray-500">${room.room_description || 'Sala de chat'}</p>
            </div>
            
            <!-- Stats -->
            <div class="p-6 space-y-4">
                <div>
                    <h4 class="text-xs font-medium text-gray-500 uppercase mb-3">Sesiones</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="text-center p-3 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">${activeSessions}</div>
                            <div class="text-xs text-green-700">Activas</div>
                        </div>
                        <div class="text-center p-3 bg-yellow-50 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">${waitingSessions}</div>
                            <div class="text-xs text-yellow-700">En Espera</div>
                        </div>
                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">${completedSessions}</div>
                            <div class="text-xs text-blue-700">Completadas</div>
                        </div>
                        <div class="text-center p-3 bg-red-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">${abandonedSessions}</div>
                            <div class="text-xs text-red-700">Abandonadas</div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance -->
                <div>
                    <h4 class="text-xs font-medium text-gray-500 uppercase mb-3">Rendimiento</h4>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                            <span class="text-sm text-gray-600">Tiempo promedio</span>
                            <span class="font-semibold text-gray-900">${(sessions.avg_duration || 0).toFixed(1)} min</span>
                        </div>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                            <span class="text-sm text-gray-600">Tasa de éxito</span>
                            <span class="font-semibold text-gray-900">${sessions.completion_rate || 0}%</span>
                        </div>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                            <span class="text-sm text-gray-600">Tasa abandono</span>
                            <span class="font-semibold text-gray-900">${sessions.abandonment_rate || 0}%</span>
                        </div>
                    </div>
                </div>
                
                <!-- Agentes -->
                <div>
                    <h4 class="text-xs font-medium text-gray-500 uppercase mb-3">Agentes</h4>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Total</span>
                            <span class="font-semibold text-gray-900">${agents.total || agents.total_assigned || 0}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Disponibles</span>
                            <span class="font-semibold text-green-600">${agents.available || agents.available_now || 0}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Ocupados</span>
                            <span class="font-semibold text-yellow-600">${agents.busy || agents.on_session || 0}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span>Última actualización</span>
                    <span>${new Date(room.last_updated).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}</span>
                </div>
            </div>
        </div>
    `;
}

        changeStatsTimeframe() {
            this.loadMyMonitor();
        }

        refreshRoomsStats() {
            this.showNotification('Actualizando estadísticas...', 'info', 1000);
            this.loadMyMonitor();
        }

        async loadMonitorRoomSessions(room) {
        try {
            // 🔧 USAR EL MISMO ENDPOINT QUE EL AGENTE
            const roomId = room.id || room.room_id;
            
            console.log('🔍 === CARGANDO SESIONES (MÉTODO AGENTE) ===');
            console.log('📋 Sala:', room.name || room.room_name);
            console.log('🆔 Room ID:', roomId);
            
            // ⭐ ENDPOINT IDÉNTICO AL DEL AGENTE
            const url = `${CHAT_API}/chats/sessions?room_id=${roomId}&include_expired=false`;
            console.log('🌐 URL:', url);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });
            
            console.log('📡 Response status:', response.status);
            
            if (!response.ok) {
                console.error('❌ Error en respuesta:', response.status);
                return { ...room, sessions: [], waiting: 0, active: 0 };
            }
            
            const result = await response.json();
            console.log('📦 Resultado completo:', result);
            
            // ⭐ PROCESAR IGUAL QUE EL AGENTE
            if (result.success && result.data && result.data.sessions) {
                const sessions = result.data.sessions.map(session => this.processSessionData(session));
                
                console.log('📊 Sesiones encontradas:', sessions.length);
                
                if (sessions.length > 0) {
                    console.log('📋 Primera sesión:', sessions[0]);
                }
                
                // Contar por status
                const waiting = sessions.filter(s => s.status === 'waiting').length;
                const active = sessions.filter(s => s.status === 'active').length;
                
                console.log('⏳ Waiting:', waiting);
                console.log('✅ Active:', active);
                console.log('✅ === FIN CARGA SESIONES ===\n');
                
                return {
                    ...room,
                    room_id: roomId,
                    room_name: room.name || room.room_name,
                    sessions: sessions,
                    waiting: waiting,
                    active: active,
                    total: sessions.length
                };
            } else {
                console.log('Respuesta sin sesiones');
                return { ...room, sessions: [], waiting: 0, active: 0 };
            }
            
        } catch (error) {
            console.error('Error cargando sesiones:', error);
            return { ...room, sessions: [], waiting: 0, active: 0 };
        }
    }
    processSessionData(session) {
        return {
            id: session.id,
            room_id: session.room_id,
            status: session.status || 'waiting',
            created_at: session.created_at,
            updated_at: session.updated_at,
            user_data: session.user_data,
            user_id: session.user_id,
            agent_id: session.agent_id || null,
            agent_name: session.agent_name || null,
            user_name: session.user_name || this.getPatientNameFromSession(session),
            patient_data: session.patient_data || {},
            ptoken: session.ptoken,
            transfer_info: session.transfer_info,
            duration_minutes: session.duration_minutes || 0,
            waiting_time_minutes: session.waiting_time_minutes || 0
        };
    }

        updateMonitorStats() {
            if (!this.monitorRoomsData) return;
            
            const totalRooms = this.monitorRoomsData.length;
            const totalWaiting = this.monitorRoomsData.reduce((sum, room) => sum + (room.waiting || 0), 0);
            const totalActive = this.monitorRoomsData.reduce((sum, room) => sum + (room.active || 0), 0);
            
            const roomsEl = document.getElementById('monitorTotalRooms');
            const waitingEl = document.getElementById('monitorTotalWaiting');
            const activeEl = document.getElementById('monitorTotalActive');
            
            if (roomsEl) roomsEl.textContent = totalRooms;
            if (waitingEl) waitingEl.textContent = totalWaiting;
            if (activeEl) activeEl.textContent = totalActive;
        }

        applyMonitorFilters() {
            const viewFilter = document.getElementById('monitorViewFilter')?.value || 'all';
            const sortFilter = document.getElementById('monitorSortFilter')?.value || 'waiting_desc';
            
            if (!this.monitorRoomsData) return;
            
            let filteredRooms = [...this.monitorRoomsData];
            
            if (viewFilter === 'active') {
                filteredRooms = filteredRooms.filter(room => room.active > 0);
            } else if (viewFilter === 'waiting') {
                filteredRooms = filteredRooms.filter(room => room.waiting > 0);
            }
            
            if (sortFilter === 'waiting_desc') {
                filteredRooms.sort((a, b) => (b.waiting || 0) - (a.waiting || 0));
            } else if (sortFilter === 'active_desc') {
                filteredRooms.sort((a, b) => (b.active || 0) - (a.active || 0));
            } else if (sortFilter === 'name_asc') {
                filteredRooms.sort((a, b) => (a.room_name || '').localeCompare(b.room_name || ''));
            }
            
            this.renderMonitorRooms(filteredRooms);
        }

        renderMonitorRooms(rooms = this.monitorRoomsData) {
            const grid = document.getElementById('monitorRoomsGrid');
            if (!grid) return;
            
            if (!rooms || rooms.length === 0) {
                grid.innerHTML = `
                    <div class="col-span-full text-center py-20">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p class="text-gray-500">No tienes salas asignadas</p>
                    </div>
                `;
                return;
            }
            
            grid.innerHTML = rooms.map(room => this.createMonitorRoomCard(room)).join('');
        }

        createMonitorRoomCard(room) {
            const waitingSessions = (room.sessions || []).filter(s => s.status === 'waiting');
            const activeSessions = (room.sessions || []).filter(s => s.status === 'active');
            
            return `
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center text-white font-semibold text-lg">
                                ${(room.room_name || 'S').charAt(0)}
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">${room.room_name || 'Sala'}</h3>
                                <p class="text-xs text-gray-500">${room.room_description || room.description || 'Sala de chat'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="text-center p-3 bg-yellow-50 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">${room.waiting || 0}</div>
                            <div class="text-xs text-yellow-700">Pendientes</div>
                        </div>
                        <div class="text-center p-3 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">${room.active || 0}</div>
                            <div class="text-xs text-green-700">Activos</div>
                        </div>
                    </div>
                    
                    ${waitingSessions.length > 0 ? `
                        <div class="mb-3">
                            <h4 class="text-xs font-medium text-gray-600 uppercase mb-2">En Espera (${waitingSessions.length})</h4>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                ${waitingSessions.slice(0, 3).map(session => this.createMonitorSessionItem(session, 'waiting')).join('')}
                            </div>
                            ${waitingSessions.length > 3 ? `<div class="text-xs text-blue-600 mt-2">+${waitingSessions.length - 3} más</div>` : ''}
                        </div>
                    ` : ''}
                    
                    ${activeSessions.length > 0 ? `
                        <div>
                            <h4 class="text-xs font-medium text-gray-600 uppercase mb-2">Activos (${activeSessions.length})</h4>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                ${activeSessions.slice(0, 3).map(session => this.createMonitorSessionItem(session, 'active')).join('')}
                            </div>
                            ${activeSessions.length > 3 ? `<div class="text-xs text-blue-600 mt-2">+${activeSessions.length - 3} más</div>` : ''}
                        </div>
                    ` : ''}
                    
                    ${(room.sessions || []).length === 0 ? `
                        <div class="text-center py-6 text-gray-400 text-sm">
                            <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            Sin actividad
                        </div>
                    ` : ''}
                </div>
            `;
        }

        createMonitorSessionItem(session, type) {
            const userName = session.user_name || 'Usuario';
            const agentName = session.agent_name || 'Sin asignar';
            const duration = session.duration_minutes || session.waiting_time_minutes || 0;
            const sessionId = session.id || session.session_id;
            
            return `
                <div onclick="supervisorClient.openObserverChat('${sessionId}')" 
                    class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-gray-900">${userName}</span>
                        <span class="px-2 py-1 text-xs rounded ${type === 'waiting' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}">
                            ${type === 'waiting' ? 'Esperando' : 'Activo'}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <span>${agentName}</span>
                        <span>${duration} min</span>
                    </div>
                </div>
            `;
        }

        async openObserverChat(sessionId) {
            try {
                console.log('👁️ Abriendo chat en modo observador:', sessionId);
                
                const modal = document.getElementById('observerChatModal');
                modal.classList.remove('hidden');
                
                const messagesContainer = document.getElementById('observerChatMessages');
                messagesContainer.innerHTML = `
                    <div class="text-center py-20">
                        <div class="loading-spinner mx-auto mb-4"></div>
                        <p class="text-gray-400">Cargando historial...</p>
                    </div>
                `;
                
                const sessionResponse = await fetch(`${CHAT_API}/chats/sessions?session_id=${sessionId}`, {
                    headers: this.getAuthHeaders()
                });
                
                if (!sessionResponse.ok) throw new Error('Error cargando sesión');
                
                const sessionResult = await sessionResponse.json();
                const session = sessionResult.data?.sessions?.[0];
                
                if (!session) throw new Error('Sesión no encontrada');
                
                this.updateObserverChatHeader(session);
                await this.loadObserverChatHistory(sessionId);
                
            } catch (error) {
                console.error('❌ Error abriendo chat observador:', error);
                this.showNotification('Error abriendo chat: ' + error.message, 'error');
                this.closeObserverChat();
            }
        }

        updateObserverChatHeader(session) {
            const userName = session.user_name || 'Paciente';
            const agentName = session.agent_name || 'Sin asignar';
            const roomName = session.room_name || 'Sala';
            
            const initials = userName.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
            
            document.getElementById('observerChatInitials').textContent = initials;
            document.getElementById('observerChatTitle').textContent = userName;
            document.getElementById('observerChatSubtitle').textContent = `${roomName} • ${agentName}`;
        }

        async loadObserverChatHistory(sessionId) {
            try {
                const response = await fetch(`${CHAT_API}/messages/${sessionId}?limit=100`, {
                    headers: this.getAuthHeaders()
                });
                
                if (!response.ok) throw new Error('Error cargando mensajes');
                
                const result = await response.json();
                const messages = result.data?.messages || [];
                
                const container = document.getElementById('observerChatMessages');
                
                if (messages.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-8 text-gray-500">
                            <p>No hay mensajes en esta conversación</p>
                        </div>
                    `;
                    return;
                }
                
                container.innerHTML = messages.map(msg => this.createMessageBubble(msg)).join('');
                
                setTimeout(() => {
                    container.scrollTop = container.scrollHeight;
                }, 100);
                
            } catch (error) {
                console.error('❌ Error cargando historial:', error);
                document.getElementById('observerChatMessages').innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <p>Error cargando mensajes</p>
                    </div>
                `;
            }
        }

        createMessageBubble(message) {
            const senderType = message.sender_type || message.user_type || 'patient';
            const senderName = message.sender_name || message.user_name || 'Usuario';
            const content = this.escapeHtml(message.content || message.message || '');
            
            let timestamp = message.timestamp || message.created_at || new Date();
            if (typeof timestamp === 'string') {
                timestamp = new Date(timestamp);
            }
            
            const time = timestamp.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const senderLabels = {
                'patient': 'Paciente',
                'agent': 'Agente',
                'supervisor': 'Supervisor',
                'admin': 'Admin'
            };
            
            const senderLabel = senderLabels[senderType] || senderName;
            
            const messageClass = senderType === 'patient' ? 'justify-start' : 'justify-end';
            const bubbleClass = senderType === 'patient' ? 'bg-gray-200 text-gray-900' : 
                            senderType === 'supervisor' ? 'bg-purple-600 text-white' :
                            'bg-blue-600 text-white';
            
            return `
                <div class="flex ${messageClass} mb-4">
                    <div class="max-w-xs lg:max-w-md ${bubbleClass} rounded-lg px-4 py-2">
                        <div class="text-xs opacity-75 mb-1">${senderLabel}</div>
                        <p>${content}</p>
                        <div class="text-xs opacity-75 mt-1">${time}</div>
                    </div>
                </div>
            `;
        }

        closeObserverChat() {
            const modal = document.getElementById('observerChatModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        async refreshMonitor() {
            console.log('🔄 Actualizando monitor...');
            this.showNotification('Actualizando...', 'info', 1000);
            await this.loadMyMonitor();
        }


            async init() {
                try {
                    await this.loadTransfers();
                    await this.loadEscalations();
                    this.startAutoRefresh();
                    
                    // Refresh automático del monitor cada 30 segundos si está activo
                    setInterval(() => {
                        const monitorSection = document.getElementById('monitor-section');
                        if (monitorSection && !monitorSection.classList.contains('hidden')) {
                            this.loadMyMonitor();
                        }
                    }, 30000);
                    
                    console.log('✅ SupervisorClient inicializado correctamente');
                } catch (error) {
                    console.error('❌ Error de inicialización:', error);
                    this.showNotification('Error de inicialización del sistema', 'error');
                }
            }

            destroy() {
                this.stopAutoRefresh();
                this.stopSupervisorTimer();
                if (supervisorChatSocket) {
                    supervisorChatSocket.disconnect();
                }
                closeAllMobileSidebars();
            }

            // ========== MÉTODOS PARA CHAT GRUPAL ==========

            async loadGroupRooms() {
                try {
                    console.log('🏠 Cargando salas grupales...');
                    
                    const response = await fetch(`${API_BASE}/agent-assignments/my-rooms`, {
                        headers: this.getAuthHeaders()
                    });
                    
                    if (!response.ok) throw new Error('Error cargando salas');
                    
                    const result = await response.json();
                    const rooms = result.data?.rooms || [];
                    
                    console.log('✅ Salas cargadas:', rooms.length);
                    this.displayGroupRooms(rooms);
                    
                } catch (error) {
                    console.error('❌ Error cargando salas grupales:', error);
                    this.showNotification('Error cargando salas grupales', 'error'); // ✅ this.showNotification
                }
            }

            displayGroupRooms(rooms) {
                const container = document.getElementById('groupRoomsList');
                if (!container) return;
                
                if (rooms.length === 0) {
                    container.innerHTML = `
                        <div class="col-span-full text-center py-20">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <p class="text-gray-500">No tienes salas asignadas</p>
                        </div>
                    `;
                    return;
                }
                
                container.innerHTML = rooms.map(room => `
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer"
                        onclick="supervisorClient.joinGroupRoom('${room.id || room.room_id}', '${room.name || room.room_name}')">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-blue-500 rounded-lg flex items-center justify-center">
                                    <span class="text-white font-semibold text-lg">${(room.name || room.room_name || 'S').charAt(0)}</span>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">${room.name || room.room_name}</h4>
                                    <p class="text-sm text-gray-500">${room.description || room.room_description || 'Sala de chat'}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Click para unirse</span>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                    </div>
                `).join('');
            }

            async joinGroupRoom(roomId, roomName) {
            try {
                console.log('🚪 Administrador uniéndose a sala grupal:', roomId, roomName);
                
                // Resetear estado
                groupChatJoined = false;
                currentGroupRoomId = roomId;
                currentGroupRoom = { id: roomId, name: roomName };
                
                // Ocultar lista de salas, mostrar chat activo
                document.getElementById('groupRoomsList').classList.add('hidden');
                document.getElementById('activeGroupChat').classList.remove('hidden');
                
                // Actualizar UI
                document.getElementById('groupChatRoomName').textContent = roomName;
                document.getElementById('groupChatModeIndicator').textContent = 'Conectando...';
                document.getElementById('groupChatModeIndicator').className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800';
                
                // Deshabilitar input hasta conectar
                document.getElementById('groupChatInputEnabled').classList.add('hidden');
                document.getElementById('groupChatInputDisabled').classList.remove('hidden');
                
                // Conectar WebSocket si no está conectado
                if (!groupChatSocket || !isGroupChatConnected) {
                    await this.connectGroupChatWebSocket();
                }
                
                // Esperar a que el socket esté conectado
                await this.waitForGroupSocketConnection();
                
                // Unirse a la sala y esperar confirmación
                await this.emitJoinGroupRoom(roomId);
                
                // Habilitar input después de unirse
                document.getElementById('groupChatInputDisabled').classList.add('hidden');
                document.getElementById('groupChatInputEnabled').classList.remove('hidden');
                
                // Actualizar indicador
                document.getElementById('groupChatModeIndicator').textContent = 'Modo Administrador';
                document.getElementById('groupChatModeIndicator').className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800';
                
                this.showNotification('Conectado a la sala', 'success');
                
            } catch (error) {
                console.error('❌ Error uniéndose a sala grupal:', error);
                this.showNotification('Error uniéndose a sala: ' + error.message, 'error');
                this.exitGroupChat();
            }
        }

            async connectGroupChatWebSocket() {
                try {
                    console.log('🔌 Conectando WebSocket para chat grupal...');
                    
                    const token = this.getToken();
                    const currentUser = this.getCurrentUser();
                    
                    groupChatSocket = io(API_BASE, {
                        transports: ['websocket', 'polling'],
                        auth: {
                            token: token,
                            user_id: currentUser.id,
                            user_type: 'supervisor',
                            user_name: currentUser.name
                        }
                    });
                    
                    groupChatSocket.on('connect', () => {
                        isGroupChatConnected = true;
                        console.log('✅ WebSocket grupal conectado');
                    });
                    
                    groupChatSocket.on('disconnect', () => {
                        isGroupChatConnected = false;
                        groupChatJoined = false;
                        console.log('❌ WebSocket grupal desconectado');
                    });
                    
                    groupChatSocket.on('group_room_joined', (data) => {
                        groupChatJoined = true;
                        isSilentMode = false; // Admin nunca está en modo silencioso
                        currentGroupRoomId = data.room_id;
                        console.log('✅ Admin unido a sala grupal:', data);
                        console.log('Estado actual:', {
                            groupChatJoined,
                            currentGroupRoomId,
                            isGroupChatConnected
                        });
                        
                        this.updateGroupChatUI(data);
                        this.loadGroupChatHistory(data.room_id);
                    });
                    
                    groupChatSocket.on('new_group_message', (data) => {
                        console.log('💬 Nuevo mensaje grupal:', data);
                        this.handleGroupMessage(data);
                    });
                    
                    groupChatSocket.on('participant_joined', (data) => {
                        console.log('👋 Nuevo participante:', data);
                        this.showNotification(`${data.user_name || 'Usuario'} se unió a la sala`, 'info');
                    });
                    
                    groupChatSocket.on('participant_left', (data) => {
                        console.log('👋 Participante salió:', data);
                    });
                    
                    groupChatSocket.on('silent_mode_toggled', (data) => {
                        isSilentMode = data.is_silent;
                        this.updateSilentModeUI(data.is_silent, data.can_send_messages);
                        this.showNotification(
                            data.is_silent ? 'Modo observador activado' : 'Modo activo: puedes enviar mensajes',
                            'success'
                        );
                    });
                    
                    groupChatSocket.on('error', (error) => {
                        console.error('❌ Error en socket grupal:', error);
                        this.showNotification('Error: ' + (error.message || error), 'error');
                    });
                    
                } catch (error) {
                    console.error('❌ Error conectando WebSocket grupal:', error);
                    throw error;
                }
            }

            waitForGroupSocketConnection(timeout = 5000) {
                return new Promise((resolve, reject) => {
                    if (isGroupChatConnected) {
                        resolve();
                        return;
                    }
                    
                    const startTime = Date.now();
                    const checkConnection = setInterval(() => {
                        if (isGroupChatConnected) {
                            clearInterval(checkConnection);
                            resolve();
                        } else if (Date.now() - startTime > timeout) {
                            clearInterval(checkConnection);
                            reject(new Error('Timeout esperando conexión WebSocket'));
                        }
                    }, 100);
                });
            }

            emitJoinGroupRoom(roomId) {
                return new Promise((resolve, reject) => {
                    if (!groupChatSocket || !isGroupChatConnected) {
                        reject(new Error('Socket no conectado'));
                        return;
                    }
                    
                    const currentUser = this.getCurrentUser();
                    
                    console.log('📤 Emitiendo join_group_room:', {
                        room_id: roomId,
                        user_id: currentUser.id,
                        user_type: 'supervisor'  // ✅ CORREGIDO
                    });
                    
                    // Establecer timeout de 10 segundos
                    const timeout = setTimeout(() => {
                        if (!groupChatJoined) {
                            reject(new Error('Timeout: No se recibió confirmación de la sala'));
                        }
                    }, 10000);
                    
                    // Escuchar el evento de confirmación
                    const onJoined = (data) => {
                        clearTimeout(timeout);
                        groupChatSocket.off('group_room_joined', onJoined);
                        console.log('✅ Confirmación recibida de group_room_joined');
                        resolve(data);
                    };
                    
                    groupChatSocket.once('group_room_joined', onJoined);
                    
                    // Emitir el evento
                    groupChatSocket.emit('join_group_room', {
                        room_id: roomId,
                        user_id: currentUser.id,
                        user_type: 'supervisor'  // ✅ CORREGIDO
                    });
                });
            }

            updateGroupChatUI(data) {
                // Actualizar contador de participantes
                const participants = data.participants || [];
                document.getElementById('groupChatParticipantsCount').textContent = 
                    `${participants.length} participante${participants.length !== 1 ? 's' : ''}`;
                
                // ✅ Actualizar indicador de rol
                const indicator = document.getElementById('groupChatModeIndicator');
                if (indicator) {
                    indicator.textContent = 'Modo Supervisor';
                    indicator.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-800';
                }
                
                // Actualizar modo
                this.updateSilentModeUI(data.is_silent, data.can_send_messages);
            }

            updateSilentModeUI(isSilent, canSend) {
                const indicator = document.getElementById('groupChatModeIndicator');
                const toggleBtn = document.getElementById('toggleSilentModeBtn');
                const inputDisabled = document.getElementById('groupChatInputDisabled');
                const inputEnabled = document.getElementById('groupChatInputEnabled');
                
                if (isSilent) {
                    indicator.textContent = '👔 Supervisor (Observando)';  // ✅ MEJORADO
                    indicator.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800';
                    toggleBtn.innerHTML = `
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                        </svg>
                        Activar Voz
                    `;
                    inputDisabled.classList.remove('hidden');
                    inputEnabled.classList.add('hidden');
                } else {
                    indicator.textContent = '👔 Supervisor (Activo)';  // ✅ MEJORADO
                    indicator.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-800';
                    toggleBtn.innerHTML = `
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                        </svg>
                        Silenciar
                    `;
                    inputDisabled.classList.add('hidden');
                    inputEnabled.classList.remove('hidden');
                }
            }

            async loadGroupChatHistory(roomId) {
                const container = document.getElementById('groupChatMessages');
                container.innerHTML = '<div class="text-center text-gray-500 text-sm py-8">Cargando mensajes...</div>';
                
                try {
                    console.log('📜 Cargando historial del chat grupal para sala:', roomId);
                    
                    // 🔧 Usar API_BASE porque el backend ya tiene /chat en la ruta
                    const response = await fetch(`${CHAT_API}/group-chat/rooms/${roomId}/messages?limit=100`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    
                    const result = await response.json();
                    console.log('📦 Respuesta del historial grupal:', result);
                    
                    if (!result.success || !result.data || !result.data.messages) {
                        console.log('⚠️ No hay mensajes en la respuesta');
                        container.innerHTML = '<div class="text-center text-gray-500 text-sm py-8">No hay mensajes aún</div>';
                        return;
                    }
                    
                    const messages = result.data.messages;
                    console.log('✅ Mensajes cargados:', messages.length);
                    
                    if (messages.length === 0) {
                        container.innerHTML = '<div class="text-center text-gray-500 text-sm py-8">No hay mensajes aún</div>';
                        return;
                    }
                    
                    // Limpiar contenedor
                    container.innerHTML = '';
                    
                    // Renderizar cada mensaje
                    messages.forEach(msg => {
                        this.renderGroupMessageFromHistory(msg);
                    });
                    
                    // Scroll al final
                    setTimeout(() => {
                        container.scrollTop = container.scrollHeight;
                    }, 100);
                    
                    console.log('✅ Historial cargado exitosamente');
                    
                } catch (error) {
                    console.error('❌ Error cargando historial grupal:', error);
                    container.innerHTML = `
                        <div class="text-center text-red-500 text-sm py-8">
                            <p>Error cargando mensajes</p>
                            <p class="text-xs text-gray-500 mt-2">${error.message}</p>
                        </div>
                    `;
                }
            }

            renderGroupMessageFromHistory(msg) {
                const container = document.getElementById('groupChatMessages');
                if (!container) return;
                
                // Eliminar mensaje de "no hay mensajes" si existe
                const emptyMsg = container.querySelector('.text-center');
                if (emptyMsg && emptyMsg.textContent.includes('No hay mensajes')) {
                    emptyMsg.remove();
                }
                
                const currentUser = this.getCurrentUser();
                const isMyMessage = msg.sender_id === currentUser.id;
                
                // 🔧 Crear ID único para evitar duplicados
                const messageId = msg.id || `msg_${msg.sender_id}_${msg.created_at}`;
                
                // Verificar si el mensaje ya existe
                if (document.getElementById(messageId)) {
                    console.log('⚠️ Mensaje duplicado en historial, ignorando');
                    return;
                }
                
                const messageEl = document.createElement('div');
                messageEl.id = messageId;
                messageEl.className = `flex ${isMyMessage ? 'justify-end' : 'justify-start'} mb-4`;
                
                // Determinar tipo y label del remitente
                const senderType = msg.sender_type || 'patient';
                const senderLabel = senderType === 'supervisor' ? 'Supervisor' :
                                senderType === 'agent' ? 'Agente' :
                                senderType === 'admin' ? 'Admin' : 
                                msg.sender_name || 'Usuario';
                
                // Colores según tipo de remitente
                const bubbleColor = isMyMessage ? 'bg-purple-600 text-white' :
                                senderType === 'supervisor' ? 'bg-purple-100 text-purple-900' :
                                senderType === 'agent' ? 'bg-green-100 text-green-900' :
                                senderType === 'admin' ? 'bg-red-100 text-red-900' :
                                'bg-gray-200 text-gray-900';
                
                // Parsear timestamp
                let timestamp = msg.timestamp || msg.created_at;
                if (typeof timestamp === 'string') {
                    timestamp = new Date(timestamp);
                } else if (typeof timestamp === 'number') {
                    timestamp = new Date(timestamp);
                } else {
                    timestamp = new Date();
                }
                
                if (isNaN(timestamp.getTime())) {
                    timestamp = new Date();
                }
                
                const time = timestamp.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                messageEl.innerHTML = `
                    <div class="max-w-xs lg:max-w-md ${bubbleColor} rounded-lg px-4 py-2">
                        <div class="text-xs opacity-75 mb-1">${isMyMessage ? 'Tú' : senderLabel}</div>
                        <p class="text-sm">${this.escapeHtml(msg.content)}</p>
                        <div class="text-xs opacity-75 mt-1">${time}</div>
                    </div>
                `;
                
                container.appendChild(messageEl);
            }

            handleGroupMessage(data) {
                const container = document.getElementById('groupChatMessages');
                
                // Eliminar mensaje de "no hay mensajes"
                const emptyMsg = container.querySelector('.text-center');
                if (emptyMsg && emptyMsg.textContent.includes('No hay mensajes')) {
                    emptyMsg.remove();
                }
                
                const currentUser = this.getCurrentUser(); // ✅ this.getCurrentUser()
                const isMyMessage = data.sender_id === currentUser.id;
                
                const messageEl = document.createElement('div');
                messageEl.className = `flex ${isMyMessage ? 'justify-end' : 'justify-start'} mb-4`;
                
                const senderLabel = data.sender_type === 'supervisor' ? 'Supervisor' :
                                data.sender_type === 'agent' ? 'Agente' :
                                data.sender_type === 'admin' ? 'Admin' : 'Usuario';
                
                const bubbleColor = isMyMessage ? 'bg-purple-600 text-white' :
                                data.sender_type === 'supervisor' ? 'bg-purple-100 text-purple-900' :
                                data.sender_type === 'agent' ? 'bg-green-100 text-green-900' :
                                data.sender_type === 'admin' ? 'bg-red-100 text-red-900' :
                                'bg-gray-200 text-gray-900';
                
                const time = new Date().toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
                
                messageEl.innerHTML = `
                    <div class="max-w-xs lg:max-w-md ${bubbleColor} rounded-lg px-4 py-2">
                        <div class="text-xs opacity-75 mb-1">${isMyMessage ? 'Tú' : senderLabel}</div>
                        <p class="text-sm">${this.escapeHtml(data.content)}</p>
                        <div class="text-xs opacity-75 mt-1">${time}</div>
                    </div>
                `;
                
                container.appendChild(messageEl);
                container.scrollTop = container.scrollHeight;
            }

            toggleGroupSilentMode() {
                if (!groupChatSocket || !groupChatJoined) {
                    this.showNotification('No estás conectado a la sala', 'error'); // ✅ this.showNotification
                    return;
                }
                
                groupChatSocket.emit('toggle_silent_mode', {});
            }

            exitGroupChat() {
                // Desconectar del chat grupal
                if (groupChatSocket && groupChatJoined) {
                    groupChatSocket.disconnect();
                    groupChatSocket = null;
                    isGroupChatConnected = false;
                    groupChatJoined = false;
                }
                
                currentGroupRoomId = null;
                currentGroupRoom = null;
                
                // Mostrar lista de salas nuevamente
                document.getElementById('groupRoomsList').classList.remove('hidden');
                document.getElementById('activeGroupChat').classList.add('hidden');
                
                // Limpiar mensajes
                document.getElementById('groupChatMessages').innerHTML = '';
            }

            showGroupParticipants() {
                if (!groupChatSocket || !groupChatJoined) {
                    this.showNotification('No estás en una sala', 'error'); // ✅ this.showNotification
                    return;
                }
                
                // Emitir evento para obtener participantes
                groupChatSocket.emit('get_room_participants', {}, (response) => {
                    if (response && response.participants) {
                        this.displayGroupParticipants(response.participants);
                    }
                });
                
                document.getElementById('groupParticipantsModal').classList.remove('hidden');
            }

            displayGroupParticipants(participants) {
                const container = document.getElementById('groupParticipantsList');
                
                if (participants.length === 0) {
                    container.innerHTML = '<div class="text-center text-gray-500 py-4">No hay participantes</div>';
                    return;
                }
                
                container.innerHTML = participants.map(p => `
                    <div class="flex items-center justify-between py-3 border-b border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-blue-500 flex items-center justify-center">
                                <span class="text-white font-semibold">${(p.user_name || 'U').charAt(0)}</span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">${p.user_name || 'Usuario'}</p>
                                <p class="text-xs text-gray-500 capitalize">${p.role}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            ${p.is_silent ? 
                                '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded">Observando</span>' :
                                '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">Activo</span>'
                            }
                            ${p.is_online ? 
                                '<div class="w-2 h-2 bg-green-500 rounded-full"></div>' :
                                '<div class="w-2 h-2 bg-gray-400 rounded-full"></div>'
                            }
                        </div>
                    </div>
                `).join('');
            }

            closeGroupParticipants() {
                document.getElementById('groupParticipantsModal').classList.add('hidden');
            }

            refreshGroupRooms() {
                this.loadGroupRooms();
                this.showNotification('Actualizando salas...', 'info', 1000);
            }
        }

        window.supervisorClient = new SupervisorClient();
        window.openExistingSupervisionChat = (sessionId) => supervisorClient.openExistingSupervisionChat(sessionId);

        function openMobileNav() {
            document.getElementById('mobileNav').classList.add('active');
            document.getElementById('mobileNavBackdrop').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileNav() {
            document.getElementById('mobileNav').classList.remove('active');
            document.getElementById('mobileNavBackdrop').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Patient Info Sidebar Functions
        function openPatientInfoSidebar() {
            // En desktop no hacer nada - la sidebar ya está visible
            if (window.innerWidth >= 1024) return;
            
            const sidebar = document.getElementById('patientInfoSidebar');
            const backdrop = document.getElementById('patientInfoBackdrop');
            
            if (sidebar && backdrop) {
                sidebar.classList.add('active');
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closePatientInfoSidebar() {
            // En desktop no hacer nada - la sidebar debe permanecer visible
            if (window.innerWidth >= 1024) return;
            
            const sidebar = document.getElementById('patientInfoSidebar');
            const backdrop = document.getElementById('patientInfoBackdrop');
            
            if (sidebar && backdrop) {
                sidebar.classList.remove('active');
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        
        // Show/Hide patient info button during supervisor chat
        function showPatientInfoButton() {
            const button = document.getElementById('patientInfoButton');
            if (button) {
                button.style.display = 'flex';
                button.classList.remove('hidden');
            }
        }
        
        function hidePatientInfoButton() {
            const button = document.getElementById('patientInfoButton');
            if (button) {
                button.style.display = 'none';
                button.classList.add('hidden');
            }
            closePatientInfoSidebar();
        }
        
        function showSection(sectionName) {
    hideAllSections();
    
    const section = document.getElementById(`${sectionName}-section`);
    if (!section) {
        console.error(`❌ No se encontró la sección: ${sectionName}-section`);
        return;
    }
    
    section.classList.remove('hidden');
    
    // Update navigation active states (both desktop and mobile)
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    document.getElementById(`nav-${sectionName}`)?.classList.add('active');
    document.getElementById(`mobile-nav-${sectionName}`)?.classList.add('active');
    
    // Update title
    const titles = {
        'transfers': 'Transferencias Pendientes (RF4)',
        'escalations': 'Escalaciones Activas (RF5)', 
        'analysis': 'Análisis de Mal Direccionamiento (RF6)',
        'supervisor-chat': 'Chat de Supervisión',
        'my-panel': 'Mi panel de supervisor',
        'monitor': 'Monitor de Mis Salas',
        'group-chat': 'Chat Grupal'  // ✅ AGREGADO
    };
    
    const titleElement = document.getElementById('sectionTitle');
    if (titleElement) {
        titleElement.textContent = titles[sectionName] || 'Panel de Supervisor';
    }
    
    // Handle patient info button visibility - SIEMPRE mostrar en supervisor chat
    if (sectionName === 'supervisor-chat') {
        showPatientInfoButton();
    } else {
        hidePatientInfoButton();
    }

    // Cerrar navegación móvil al cambiar sección
    closeMobileNav();
    
    // Ajustar layout si es necesario
    setTimeout(adjustChatLayout, 100);
    
    // Load section data
    switch(sectionName) {
        case 'transfers':
            supervisorClient.loadTransfers();
            break;
        case 'escalations':
            supervisorClient.loadEscalations();
            break;
        case 'my-panel':
            supervisorClient.loadMyInfo();
            supervisorClient.loadMyRooms();
            supervisorClient.loadMySessions();
            supervisorClient.loadMySchedules();
            break;
        case 'monitor':
            supervisorClient.loadMyMonitor();
            break;
        case 'group-chat':  // ✅ AGREGADO
            supervisorClient.loadGroupRooms();
            break;
    }
}

        function hideAllSections() {
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
        }
        
        // Handle window resize for mobile nav
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) { // lg breakpoint
                closeMobileNav();
                closePatientInfoSidebar();
            }
        });

        // Prevent mobile nav closing when clicking inside it
        document.getElementById('mobileNav')?.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        // Prevent patient info sidebar closing when clicking inside it
        document.getElementById('patientInfoSidebar')?.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        // Close sidebars on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMobileNav();
                closePatientInfoSidebar();
            }
        });

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function toggleAgentDropdown() {
            const dropdown = document.getElementById('agentDropdownContent');
            dropdown.classList.toggle('show');
        }

        function selectAgent(agentId, agentName) {
            supervisorClient.selectedAgent = agentId;
            document.getElementById('selectedAgentText').textContent = agentName;
            document.getElementById('agentDropdownContent').classList.remove('show');
        }

        function confirmAgentAssignment() {
            if (!supervisorClient.selectedAgent) {
                supervisorClient.showNotification('Por favor selecciona un agente', 'warning');
                return;
            }
            
            const reason = document.getElementById('assignmentReason').value;
            
            if (supervisorClient.currentTransfer) {
                supervisorClient.approveTransferWithAgent(supervisorClient.currentTransfer, supervisorClient.selectedAgent);
            } else if (supervisorClient.currentEscalation) {
                supervisorClient.assignEscalationToAgent(supervisorClient.currentEscalation, supervisorClient.selectedAgent, reason);
            }
            
            supervisorClient.selectedAgent = null;
            supervisorClient.currentTransfer = null;
            supervisorClient.currentEscalation = null;
            document.getElementById('selectedAgentText').textContent = 'Seleccionar agente...';
            document.getElementById('assignmentReason').value = '';
            
            closeModal('assignAgentModal');
        }

        function sendSupervisorMessage() {
            const input = document.getElementById('supervisorMessageInput');
            if (!input) return;

            const message = input.value.trim();
            if (!message) return;

            const currentUser = supervisorClient.getCurrentUser();

            if (isSupervisorConnected && supervisorSessionJoined && supervisorChatSocket) {
                const payload = {
                    session_id: currentSupervisorSession.session_data?.session_id || currentSupervisorSession.session_data?.id,
                    user_id: currentUser.id,
                    user_type: 'supervisor',
                    user_name: currentUser.name,
                    sender_id: currentUser.id,      
                    sender_type: 'supervisor',      
                    message_type: 'text',
                    content: message
                };

                console.log('📤 Enviando mensaje como supervisor:', payload);

                supervisorChatSocket.emit('send_message', payload, (response) => {
                    if (response && !response.success) {
                        console.error('❌ Error enviando mensaje:', response?.message || 'Error desconocido');
                        supervisorClient.showNotification('Error enviando mensaje: ' + (response?.message || 'Error desconocido'), 'error');
                        
                        // Restaurar el mensaje en caso de error
                        if (input) input.value = message;
                    }
                });
                
                input.value = '';
                updateSupervisorSendButton();
            } else {
                supervisorClient.showNotification('Error: Chat no conectado', 'error');
            }
        }

        function handleSupervisorKeyDown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                
                const input = document.getElementById('supervisorMessageInput');
                if (input && input.value.trim()) {
                    sendSupervisorMessage();
                }
            }
            updateSupervisorSendButton();
        }

        function updateSupervisorSendButton() {
            const input = document.getElementById('supervisorMessageInput');
            const button = document.getElementById('supervisorSendButton');
            
            if (input && button) {
                button.disabled = !input.value.trim() || !currentSupervisorSession;
            }
        }

        function showSupervisorEndModal() {
            document.getElementById('supervisorEndModal').classList.remove('hidden');
        }

        function executeSupervisorEnd() {
            const reason = document.getElementById('supervisorEndReason').value;
            const notes = document.getElementById('supervisorEndNotes').value.trim();
            
            supervisorClient.showNotification('Sesión de supervisión finalizada', 'success');

            closeAllMobileSidebars();
            exitSupervisorChat();
            closeModal('supervisorEndModal');
        }

        function exitSupervisorChat() {
            if (currentSupervisorSession) {
                if (supervisorChatSocket) {
                    supervisorChatSocket.disconnect();
                }
                currentSupervisorSession = null;
                supervisorClient.stopSupervisorTimer();
            }
            closeAllMobileSidebars();
            showSection('escalations');
        }

        function logout() {
            if (confirm('Cerrar sesión?')) {
                supervisorClient.destroy();
                
                // Guardar motivos de rechazo antes de limpiar
                const rejectionReasons = localStorage.getItem('supervisor_rejections_by_pattern');
                
                // Limpiar localStorage y sessionStorage
                localStorage.clear();
                sessionStorage.clear();
                
                // Restaurar motivos de rechazo
                if (rejectionReasons) {
                    localStorage.setItem('supervisor_rejections_by_pattern', rejectionReasons);
                }
                
                window.location.href = 'logout.php';
            }
        }

        function updateTime() {
            document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('es-ES');
        }
        // Función global para manejar desplegables de motivos
        window.toggleReasons = function(reasonsId) {
            const container = document.getElementById(reasonsId);
            const arrow = document.getElementById(`arrow-${reasonsId}`);
            
            console.log('🔍 Toggle reasons clicked:', reasonsId);
            console.log('🔍 Container found:', !!container);
            console.log('🔍 Arrow found:', !!arrow);
            
            if (container && arrow) {
                if (container.classList.contains('hidden')) {
                    container.classList.remove('hidden');
                    arrow.classList.add('rotate-180');
                    console.log('✅ Showing reasons');
                } else {
                    container.classList.add('hidden');
                    arrow.classList.remove('rotate-180');
                    console.log('✅ Hiding reasons');
                }
            }
        };

       // =============== SISTEMA DE NAVEGACIÓN MÓVIL MEJORADO ===============

        // Inicialización de controles móviles
        function initializeMobileControls() {
            setupMobileEventListeners();
            window.addEventListener('orientationchange', handleOrientationChange);
            window.addEventListener('resize', debounce(handleResize, 250));
            
            // Configuración inicial
            handleResize();
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Event Listeners principales
        function setupMobileEventListeners() {
            // Cerrar con tecla ESC solo en móvil
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && window.innerWidth < 1024) {
                    closeMobileNav();
                    closePatientInfoSidebar();
                }
            });
            
            // Click en backdrop solo cierra en móvil
            const patientBackdrop = document.getElementById('patientInfoBackdrop');
            if (patientBackdrop) {
                patientBackdrop.addEventListener('click', () => {
                    if (window.innerWidth < 1024) {
                        closePatientInfoSidebar();
                    }
                });
            }
        }

        // Funciones de manejo de eventos
        function handleOrientationChange() {
            setTimeout(() => {
                handleResize();
                if (window.innerWidth < 1024) {
                    closeMobileNav();
                    closePatientInfoSidebar();
                }
            }, 100);
        }

        function handleResize() {
            const isDesktop = window.innerWidth >= 1024;
            const isMobile = window.innerWidth < 1024;
            
            if (isDesktop) {
                // En desktop - cerrar todo y resetear
                closeMobileNav();
                closePatientInfoSidebar();
                document.body.style.overflow = '';
                
                // Asegurar que la sidebar de paciente esté visible en desktop
                const sidebar = document.getElementById('patientInfoSidebar');
                const backdrop = document.getElementById('patientInfoBackdrop');
                
                if (sidebar) {
                    sidebar.classList.remove('active');
                    sidebar.style.display = 'block';
                    sidebar.style.position = 'static';
                    sidebar.style.transform = 'none';
                }
                
                if (backdrop) {
                    backdrop.classList.remove('active');
                }
                
                // Ocultar botón en desktop durante supervisor chat
                const headerBtn = document.getElementById('patientInfoButton');
                if (headerBtn) headerBtn.style.display = 'none';
                
            } else if (isMobile) {
                // En móvil - resetear sidebar a estado inicial
                const sidebar = document.getElementById('patientInfoSidebar');
                const backdrop = document.getElementById('patientInfoBackdrop');
                
                if (sidebar) {
                    sidebar.classList.remove('active');
                    sidebar.style.position = '';
                    sidebar.style.transform = '';
                    sidebar.style.display = '';
                }
                
                if (backdrop) {
                    backdrop.classList.remove('active');
                }
                
                document.body.style.overflow = '';
                
                // Mostrar botón de header en móvil si estamos en supervisor chat
                const supervisorChatSection = document.getElementById('supervisor-chat-section');
                const headerBtn = document.getElementById('patientInfoButton');
                
                if (headerBtn && supervisorChatSection && !supervisorChatSection.classList.contains('hidden')) {
                    headerBtn.style.display = 'flex';
                }
            }
            
            // Ajustar alturas del chat
            adjustChatLayout();
        }

        // Nueva función para ajustar layout del chat
        function adjustChatLayout() {
            const chatContainer = document.querySelector('.chat-container');
            const chatMessages = document.querySelector('.chat-messages');
            const chatHeader = document.querySelector('.chat-header');
            const chatInputArea = document.querySelector('.chat-input-area');
            
            if (!chatContainer || !chatMessages) return;
            
            const isDesktop = window.innerWidth >= 1024;
            
            if (isDesktop) {
                // Desktop - altura completa del viewport
                chatContainer.style.height = '100vh';
                chatContainer.style.width = 'calc(100vw - 256px)';
            } else {
                // Móvil - ocupar TODA la pantalla
                chatContainer.style.height = '100vh';
                chatContainer.style.width = '100vw';
                chatContainer.style.maxWidth = '100vw';
                chatContainer.style.margin = '0';
                chatContainer.style.padding = '0';
                
                // Ajustar mensajes para ocupar todo el espacio
                const headerHeight = chatHeader?.offsetHeight || 64;
                const inputHeight = chatInputArea?.offsetHeight || 90;
                chatMessages.style.maxHeight = `calc(100vh - ${headerHeight + inputHeight}px)`;
                chatMessages.style.width = '100%';
            }
        }


        function closeAllMobileSidebars() {
            closeMobileNav();
            
            // Solo cerrar patient info en móvil
            if (window.innerWidth < 1024) {
                closePatientInfoSidebar();
            }
            
            document.body.style.overflow = '';
        }

        // Prevenir cierre al hacer click dentro de sidebars
        function initializeSidebarClickPrevention() {
            // Navegación móvil
            document.getElementById('mobileNav')?.addEventListener('click', (e) => {
                e.stopPropagation();
            });
            
            // Patient info sidebar
            document.getElementById('patientInfoSidebar')?.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
        function toggleReasons(reasonsId) {
            const container = document.getElementById(reasonsId);
            const arrow = document.getElementById(`arrow-${reasonsId}`);
            
            if (container && arrow) {
                if (container.classList.contains('hidden')) {
                    container.classList.remove('hidden');
                    arrow.classList.add('rotate-180');
                } else {
                    container.classList.add('hidden');
                    arrow.classList.remove('rotate-180');
                }
            }
        }


        // ========== FUNCIONES GLOBALES PARA CHAT GRUPAL ==========

        function sendGroupMessage() {
            const input = document.getElementById('groupMessageInput');
            if (!input) {
                console.error('❌ Input no encontrado');
                return;
            }
            
            const message = input.value.trim();
            if (!message) {
                console.log('⚠️ Mensaje vacío');
                return;
            }
            
            console.log('📝 Estado actual:', {
                groupChatSocket: !!groupChatSocket,
                isGroupChatConnected,
                groupChatJoined,
                currentGroupRoomId
            });
            
            if (!groupChatSocket) {
                supervisorClient.showNotification('Socket no inicializado', 'error'); // ✅ CORREGIDO
                return;
            }
            
            if (!isGroupChatConnected) {
                supervisorClient.showNotification('No estás conectado al servidor', 'error'); // ✅ CORREGIDO
                return;
            }
            
            if (!groupChatJoined || !currentGroupRoomId) {
                supervisorClient.showNotification('No estás unido a una sala', 'error'); // ✅ CORREGIDO
                return;
            }
            
            const currentUser = supervisorClient.getCurrentUser(); // ✅ CORREGIDO
            
            console.log('📤 Enviando mensaje a sala:', currentGroupRoomId);
            
            groupChatSocket.emit('send_group_message', {
                room_id: currentGroupRoomId,
                content: message,
                message_type: 'text',
                sender_id: currentUser.id,
                sender_type: 'supervisor' // ✅ Supervisor, no admin
            });
            
            input.value = '';
            input.focus();
        }

        function handleGroupChatKeyDown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendGroupMessage();
            }
        }

        // Exponer funciones globalmente
        window.sendGroupMessage = sendGroupMessage;
        window.handleGroupChatKeyDown = handleGroupChatKeyDown;
        // Funciones globales
        window.openMobileNav = openMobileNav;
        window.closeMobileNav = closeMobileNav;
        window.openPatientInfoSidebar = openPatientInfoSidebar;
        window.closePatientInfoSidebar = closePatientInfoSidebar;
        window.handleResize = handleResize;
        window.handleOrientationChange = handleOrientationChange;
        window.closeAllMobileSidebars = closeAllMobileSidebars;
        window.showPatientInfoButton = showPatientInfoButton;
        window.hidePatientInfoButton = hidePatientInfoButton;
        window.adjustChatLayout = adjustChatLayout;

        document.addEventListener('DOMContentLoaded', async () => {
            console.log('🚀 Panel de supervisor cargado');
            
            updateTime();
            setInterval(updateTime, 1000);
            
            // Inicializar controles móviles
            initializeMobileControls();
            initializeSidebarClickPrevention();
            
            try {
                await supervisorClient.init();
                console.log('✅ SupervisorClient inicializado');
            } catch (error) {
                console.error('❌ Error inicializando:', error);
            }
        });

        window.addEventListener('beforeunload', () => {
            supervisorClient.destroy();
        });