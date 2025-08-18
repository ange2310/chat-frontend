/*class StaffClient {
    constructor() {
        this.authServiceUrl = 'http://localhost:3010';
        this.chatServiceUrl = 'http://localhost:3011';
        this.wsUrl = 'http://localhost';
        this.fileServiceUrl = 'http://localhost:3011/files';
        
        this.currentRoom = null;
        this.currentSessionId = null;
        this.currentSession = null;
        this.rooms = [];
        this.sessionsByRoom = {};
        this.refreshInterval = null;
        
        this.chatSocket = null;
        this.isConnectedToChat = false;
        this.sessionJoined = false;
        
        this.agentBearerToken = null;
        
        this.pendingConversations = [];
        this.myChats = [];
        this.allChatsForSidebar = {};
        this.lastFileUploadTime = 0;
    }

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
                console.error('Error decodificando token:', e);
            }
        }
        
        console.error('NO HAY BEARER TOKEN DISPONIBLE');
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
                return user;
            } catch (e) {
                console.warn('Error parsing user meta:', e);
            }
        }
        
        return { 
            id: 'unknown', 
            name: 'Usuario', 
            email: 'unknown@example.com' 
        };
    }

    async loadPendingConversations() {
        const container = document.getElementById('pendingConversationsContainer');
        const countBadge = document.getElementById('pendingCount');
        
        if (!container || !countBadge) {
            return;
        }
        
        container.innerHTML = `
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-500">Cargando conversaciones pendientes...</p>
            </div>
        `;

        try {
            const response = await fetch('http://localhost:3011/chats/sessions?waiting=true', {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data && result.data.sessions) {
                    this.pendingConversations = result.data.sessions;
                    
                    const actuallyWaiting = this.pendingConversations.filter(session => 
                        session.status === 'waiting' && !session.agent_id
                    );
                    
                    this.pendingConversations = actuallyWaiting;
                    countBadge.textContent = this.pendingConversations.length;
                    
                    if (this.pendingConversations.length === 0) {
                        container.innerHTML = `
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">¡Todo al día!</h3>
                                <p class="text-gray-500">No hay conversaciones pendientes por atender</p>
                            </div>
                        `;
                    } else {
                        this.renderPendingConversations(this.pendingConversations);
                    }
                } else {
                    throw new Error('Formato de respuesta inesperado');
                }
            } else {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('Error cargando conversaciones:', error);
            countBadge.textContent = '!';
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Error al cargar</h3>
                    <p class="text-gray-500 mb-4">No se pudieron cargar las conversaciones: ${error.message}</p>
                    <button onclick="staffClient.loadPendingConversations()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Reintentar
                    </button>
                </div>
            `;
        }
    }

    renderPendingConversations(conversations) {
        const container = document.getElementById('pendingConversationsContainer');
        
        const html = conversations.map((conv, index) => {
            const waitTime = this.getWaitTime(conv.created_at);
            const urgencyClass = this.getUrgencyClass(waitTime);
            const patientName = this.getPatientNameFromSession(conv);
            const roomName = this.getRoomNameFromSession(conv);
            
            return `
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-lg font-semibold text-blue-700">
                                    ${patientName.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">${patientName}</h4>
                                <p class="text-sm text-gray-600">${roomName}</p>
                                <p class="text-xs text-gray-500">ID: ${conv.id}</p>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <p class="text-sm font-medium ${urgencyClass}">
                                        Esperando ${waitTime}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        ${new Date(conv.created_at).toLocaleTimeString('es-ES')}
                                    </p>
                                </div>
                                <button 
                                    onclick="staffClient.takeConversation('${conv.id}')"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                                    Tomar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = `<div class="space-y-3">${html}</div>`;
    }

    async loadMyChats() {
        const container = document.getElementById('myChatsContainer');
        const countBadge = document.getElementById('myChatsCount');
        
        if (!container || !countBadge) {
            return;
        }
        
        container.innerHTML = `
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-500">Cargando mis chats...</p>
            </div>
        `;

        try {
            const currentUser = this.getCurrentUser();
            const response = await fetch(`http://localhost:3011/chats/sessions?agent_id=${currentUser.id}&active=true`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data && result.data.sessions) {
                    this.myChats = result.data.sessions.filter(session => 
                        session.status === 'active' && session.agent_id === currentUser.id
                    );
                    
                    countBadge.textContent = this.myChats.length;
                    
                    if (this.myChats.length === 0) {
                        container.innerHTML = `
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Sin chats activos</h3>
                                <p class="text-gray-500">No tienes conversaciones activas en este momento</p>
                            </div>
                        `;
                    } else {
                        this.renderMyChats(this.myChats);
                    }
                } else {
                    throw new Error('Formato de respuesta inesperado');
                }
            } else {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('Error cargando mis chats:', error);
            countBadge.textContent = '!';
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Error al cargar</h3>
                    <p class="text-gray-500 mb-4">No se pudieron cargar tus chats: ${error.message}</p>
                    <button onclick="staffClient.loadMyChats()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Reintentar
                    </button>
                </div>
            `;
        }
    }

    renderMyChats(chats) {
        const container = document.getElementById('myChatsContainer');
        
        const html = chats.map(chat => {
            const patientName = this.getPatientNameFromSession(chat);
            const roomName = this.getRoomNameFromSession(chat);
            const activeTime = this.getActiveTime(chat.updated_at);
            
            return `
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <span class="text-lg font-semibold text-green-700">
                                    ${patientName.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">${patientName}</h4>
                                <p class="text-sm text-gray-600">${roomName}</p>
                                <p class="text-xs text-gray-500">ID: ${chat.id}</p>
                                <p class="text-xs text-green-600">Activo desde ${activeTime}</p>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <button 
                                onclick="staffClient.openChatFromMyChats('${chat.id}')"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                                Continuar
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = `<div class="space-y-3">${html}</div>`;
    }

    async loadChatsSidebar() {
        const container = document.getElementById('chatsSidebarContainer');
        
        if (!container) {
            return;
        }

        try {
            const currentUser = this.getCurrentUser();
            if (!currentUser || !currentUser.id) {
                throw new Error('Usuario actual no válido');
            }
            
            const myChatsResponse = await fetch(`http://localhost:3011/chats/sessions?agent_id=${currentUser.id}&active=true`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            const pendingResponse = await fetch('http://localhost:3011/chats/sessions?waiting=true', {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (myChatsResponse.ok && pendingResponse.ok) {
                const myChatsResult = await myChatsResponse.json();
                const pendingResult = await pendingResponse.json();
                
                const myActiveChats = (myChatsResult.success && myChatsResult.data && myChatsResult.data.sessions) ? 
                    myChatsResult.data.sessions.filter(s => s && s.status === 'active' && s.agent_id === currentUser.id) : [];
                
                const pendingChats = (pendingResult.success && pendingResult.data && pendingResult.data.sessions) ? 
                    pendingResult.data.sessions.filter(s => s && s.status === 'waiting' && !s.agent_id) : [];
                
                this.allChatsForSidebar = {
                    myChats: myActiveChats,
                    pending: pendingChats
                };
                
                this.renderChatsSidebar(this.allChatsForSidebar);
            } else {
                throw new Error(`Error en respuestas: myChats=${myChatsResponse.status}, pending=${pendingResponse.status}`);
            }
            
        } catch (error) {
            console.error('Error loading chats sidebar:', error);
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-sm">Error cargando chats</p>
                    <button onclick="staffClient.loadChatsSidebar()" class="text-blue-600 text-xs mt-2 hover:underline">Reintentar</button>
                </div>
            `;
        }
    }

    renderChatsSidebar(chatsData) {
        const container = document.getElementById('chatsSidebarContainer');
        
        if (!container) {
            return;
        }
        
        if (!chatsData) {
            chatsData = { myChats: [], pending: [] };
        }
        
        let html = '';
        
        if (chatsData.myChats && Array.isArray(chatsData.myChats) && chatsData.myChats.length > 0) {
            html += `<div class="section-divider" data-title="Mis Chats"></div>`;
            chatsData.myChats.forEach(chat => {
                if (!chat || !chat.id) {
                    return;
                }
                
                const patientName = this.getPatientNameFromSession(chat);
                const roomName = this.getRoomNameFromSession(chat);
                const isActive = this.currentSession && this.currentSession.id === chat.id;
                
                html += `
                    <div class="chat-item ${isActive ? 'active' : ''} p-3 border-b border-gray-100 cursor-pointer" 
                         onclick="staffClient.selectChatFromSidebar('${chat.id}')" data-chat-id="${chat.id}">
                        <div class="status-indicator status-mine"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-sm font-semibold text-blue-700 sidebar-initials-${chat.id}">
                                    ${patientName.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 text-sm truncate sidebar-name-${chat.id}">${patientName}</h4>
                                <p class="text-xs text-gray-500 truncate">${roomName}</p>
                                <p class="text-xs text-green-600">Activo</p>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        if (chatsData.pending && Array.isArray(chatsData.pending) && chatsData.pending.length > 0) {
            html += `<div class="section-divider" data-title="Pendientes"></div>`;
            chatsData.pending.forEach(chat => {
                if (!chat || !chat.id) {
                    return;
                }
                
                const patientName = this.getPatientNameFromSession(chat);
                const roomName = this.getRoomNameFromSession(chat);
                const waitTime = this.getWaitTime(chat.created_at || new Date().toISOString());
                
                html += `
                    <div class="chat-item p-3 border-b border-gray-100 cursor-pointer" 
                         onclick="staffClient.takeConversationFromSidebar('${chat.id}')" data-chat-id="${chat.id}">
                        <div class="status-indicator status-unattended"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-sm font-semibold text-red-700 sidebar-initials-${chat.id}">
                                    ${patientName.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 text-sm truncate sidebar-name-${chat.id}">${patientName}</h4>
                                <p class="text-xs text-gray-500 truncate">${roomName}</p>
                                <p class="text-xs text-red-600">Esperando ${waitTime}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        if (!html) {
            html = `
                <div class="text-center py-8 text-gray-500">
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <p class="text-sm">No hay chats disponibles</p>
                    <p class="text-xs text-gray-400 mt-1">Los chats aparecerán aquí</p>
                </div>
            `;
        }
        
        container.innerHTML = html;
        
        // Actualizar nombres de manera asíncrona también en la sidebar
        this.updateSidebarNamesAsync([...(chatsData.myChats || []), ...(chatsData.pending || [])]);
    }

    async updateSidebarNamesAsync(chats) {
        console.log('🔄 Actualizando nombres en sidebar de manera asíncrona...');
        
        for (const chat of chats) {
            try {
                if (chat.ptoken) {
                    const patientInfo = await this.getPatientInfoWithPToken(chat);
                    const realName = patientInfo.nombreCompleto || 
                        `${patientInfo.primer_nombre} ${patientInfo.segundo_nombre} ${patientInfo.primer_apellido} ${patientInfo.segundo_apellido}`
                        .replace(/\s+/g, ' ').trim();
                    
                    if (realName && realName !== 'Paciente') {
                        const nameElement = document.querySelector(`.sidebar-name-${chat.id}`);
                        const initialsElement = document.querySelector(`.sidebar-initials-${chat.id}`);
                        
                        if (nameElement) {
                            nameElement.textContent = realName;
                            nameElement.title = realName; // Tooltip para nombres largos
                        }
                        
                        if (initialsElement) {
                            const initials = ((patientInfo.primer_nombre?.[0] || '') + (patientInfo.primer_apellido?.[0] || '')).toUpperCase() || 
                                           realName.charAt(0).toUpperCase();
                            initialsElement.textContent = initials;
                        }
                    }
                }
            } catch (error) {
                console.warn(`⚠️ Error obteniendo nombre para sidebar ${chat.id}:`, error);
            }
            
            await new Promise(resolve => setTimeout(resolve, 50));
        }
        
        console.log('✅ Actualización de nombres en sidebar completada');
    }

    async takeConversation(sessionId) {
        try {
            const conversation = this.pendingConversations.find(c => c.id === sessionId);
            if (!conversation) {
                throw new Error('Conversación no encontrada en la lista local');
            }
            
            await this.takeConversationWithSession(sessionId, conversation);
            
        } catch (error) {
            console.error('Error tomando conversación:', error);
            this.showNotification('Error al tomar la conversación: ' + error.message, 'error');
        }
    }

    async takeConversationWithSession(sessionId, conversation) {
        try {
            const response = await fetch(`http://localhost:3011/chats/sessions/${sessionId}/assign/me`, {
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
                    this.currentSession = conversation;
                    window.currentSession = conversation;
                    
                    this.showNotification('Sesión asignada exitosamente', 'success');
                    
                    setTimeout(() => {
                        this.openChatDirectly(conversation);
                    }, 1000);
                } else {
                    throw new Error(result.message || 'Error asignando sesión');
                }
            } else {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('Error tomando conversación:', error);
            throw error;
        }
    }

    async openChatFromMyChats(sessionId) {
        try {
            let session = null;
            
            if (this.myChats && this.myChats.length > 0) {
                session = this.myChats.find(chat => chat.id === sessionId);
            }
            
            if (!session) {
                console.error('Sesión no encontrada en mis chats:', sessionId);
                this.showNotification('Sesión no encontrada', 'error');
                return;
            }
            
            await this.openChatDirectly(session);
        } catch (error) {
            console.error('Error abriendo chat desde mis chats:', error);
            this.showNotification('Error abriendo chat: ' + error.message, 'error');
        }
    }

    async selectChatFromSidebar(sessionId) {
        try {
            let session = null;
            
            if (this.allChatsForSidebar.myChats) {
                session = this.allChatsForSidebar.myChats.find(chat => chat.id === sessionId);
            }
            
            if (!session && this.myChats) {
                session = this.myChats.find(chat => chat.id === sessionId);
            }
            
            if (!session) {
                console.error('Sesión no encontrada en mis chats:', sessionId);
                this.showNotification('Sesión no encontrada', 'error');
                return;
            }
            
            await this.openChatDirectly(session);
        } catch (error) {
            console.error('Error seleccionando chat desde sidebar:', error);
            this.showNotification('Error abriendo chat: ' + error.message, 'error');
        }
    }

    async takeConversationFromSidebar(sessionId) {
        try {
            let conversation = null;
            
            if (this.allChatsForSidebar.pending) {
                conversation = this.allChatsForSidebar.pending.find(chat => chat.id === sessionId);
            }
            
            if (!conversation && this.pendingConversations) {
                conversation = this.pendingConversations.find(chat => chat.id === sessionId);
            }
            
            if (!conversation) {
                console.error('Conversación no encontrada:', sessionId);
                this.showNotification('Conversación no encontrada', 'error');
                return;
            }
            
            await this.takeConversationWithSession(sessionId, conversation);
            
            setTimeout(() => {
                this.loadChatsSidebar();
            }, 1000);
            
        } catch (error) {
            console.error('Error tomando conversación desde sidebar:', error);
            this.showNotification('Error tomando conversación: ' + error.message, 'error');
        }
    }

    async openChatDirectly(session) {
        try {
            if (!session) {
                throw new Error('Sesión no proporcionada');
            }
            
            if (!session.id) {
                throw new Error('Sesión sin ID válido');
            }
            
            this.currentSession = session;
            window.currentSession = session;
            
            document.querySelectorAll('.section-content').forEach(s => s.classList.add('hidden'));
            document.getElementById('patient-chat-panel').classList.remove('hidden');

            const enrichedSession = await this.openPatientChat(session);

            const sessionToUse = enrichedSession || session;
            
            const patientName = this.getPatientNameFromSession(sessionToUse);
            const roomName = this.getRoomNameFromSession(sessionToUse);

            document.getElementById('sectionTitle').textContent = `Chat con ${patientName}`;

            await this.loadChatsSidebar();
            
        } catch (error) {
            console.error('Error abriendo chat directamente:', error);
            this.showNotification('Error abriendo chat: ' + error.message, 'error');
            
            if (typeof window.showPendingSection === 'function') {
                window.showPendingSection();
            }
        }
    }

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
                    return rooms;
                } else {
                    return this.loadRoomsFallback();
                }
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('Error cargando salas:', error);
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

    async selectRoom(roomId) {
        try {
            this.currentRoom = roomId;
            const room = this.rooms.find(r => r.id === roomId);
            
            if (!room) {
                this.showNotification('Sala no encontrada', 'error');
                return;
            }
            
            document.getElementById('currentRoomName').textContent = room.name;
            
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            document.getElementById('room-sessions-section').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = `Sesiones en: ${room.name}`;
            
            await this.loadSessionsByRoom(roomId);
            
        } catch (error) {
            console.error('Error seleccionando sala:', error);
            this.showNotification('Error seleccionando sala: ' + error.message, 'error');
        }
    }

    async loadSessionsByRoom(roomId) {
        try {
            const baseUrl = 'http://localhost:3011/chats';
            const url = `${baseUrl}/sessions?room_id=${roomId}&include_expired=false`;
            
            const response = await fetch(url, {
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
                } else {
                    this.sessionsByRoom[roomId] = [];
                    this.displayRoomSessions([], roomId);
                    return [];
                }
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error(`Error cargando sesiones para ${roomId}:`, error);
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
            agent_id: session.agent_id || null,
            patient_data: session.patient_data || {}
        };
    }

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
                            ${isMySession ? '<p class="text-xs text-blue-600 font-medium">Tu sesión</p>' : ''}
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

    async takeSession(sessionId) {
        try {
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
                    
                    const session = this.findSessionById(sessionId);
                    if (session) {
                        session.status = 'active';
                        session.agent_id = this.getCurrentUser().id;
                        this.openPatientChat(session);
                    }
                    
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
            console.error('Error tomando sesión:', error);
            this.showNotification('Error al tomar la sesión: ' + error.message, 'error');
        }
    }

    async continueSession(sessionId) {
        try {
            const session = this.findSessionById(sessionId);
            if (!session) {
                this.showNotification('Sesión no encontrada', 'error');
                return;
            }
            
            this.openPatientChat(session);
            
        } catch (error) {
            console.error('Error continuando sesión:', error);
            this.showNotification('Error al continuar la sesión: ' + error.message, 'error');
        }
    }

    async openPatientChat(session) {
        try {
            this.currentSessionId = session.id;
            this.currentSession = session;
            window.currentSession = session;

            document.querySelectorAll('.section-content')
                    .forEach(s => s.classList.add('hidden'));
            document.getElementById('patient-chat-panel')
                    .classList.remove('hidden');

            // Intentar obtener información del paciente
            const patientInfo = await this.getPatientInfoWithPToken(session);
            this.updatePatientInfoUI(patientInfo, session);

            const msgCont = document.getElementById('patientChatMessages');
            if (msgCont) msgCont.innerHTML = '';

            await this.connectToChatWebSocket();
            await this.loadChatHistory();

            return session;

        } catch (error) {
            console.error('Error abriendo chat:', error);
            this.showNotification('Error al abrir chat: ' + error.message, 'error');
            throw error;
        }
    }

    async getPatientInfoWithPToken(session) {
        // Primero intentar extraer de los datos existentes
        let patientInfo = this.extractPatientInfo(session);
        
        // Si no hay datos y existe ptoken, consultar al backend
        if (this.isPatientInfoEmpty(patientInfo) && session.ptoken) {
            try {
                console.log('📞 Consultando información del paciente con ptoken:', session.ptoken);
                const ptokenData = await this.fetchPatientDataFromPToken(session.ptoken);
                if (ptokenData) {
                    patientInfo = ptokenData;
                    console.log('✅ Información del paciente obtenida desde ptoken');
                }
            } catch (error) {
                console.error('❌ Error obteniendo datos del ptoken:', error);
            }
        }
        
        return patientInfo;
    }

    isPatientInfoEmpty(patientInfo) {
        const essentialFields = ['primer_nombre', 'primer_apellido', 'nombreCompleto', 'id', 'email'];
        return essentialFields.every(field => !patientInfo[field]);
    }

    async fetchPatientDataFromPToken(ptoken) {
        try {
            const response = await fetch(`http://localhost:3010
                /validate-token?ptoken=${encodeURIComponent(ptoken)}`, {
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

            // Buscar el beneficiario principal
            const beneficiarios = result.data.data.membresias[0].beneficiarios;
            const beneficiarioPrincipal = beneficiarios.find(b => b.tipo_ben === 'PPAL') || beneficiarios[0];
            
            if (!beneficiarioPrincipal) {
                throw new Error('No se encontró beneficiario principal');
            }

            // Obtener datos de la membresía
            const membresia = result.data.data.membresias[0];

            // Mapear los datos al formato esperado
            return {
                // Nombres
                primer_nombre: beneficiarioPrincipal.primer_nombre || '',
                segundo_nombre: beneficiarioPrincipal.segundo_nombre || '',
                primer_apellido: beneficiarioPrincipal.primer_apellido || '',
                segundo_apellido: beneficiarioPrincipal.segundo_apellido || '',
                nombreCompleto: `${beneficiarioPrincipal.primer_nombre} ${beneficiarioPrincipal.segundo_nombre} ${beneficiarioPrincipal.primer_apellido} ${beneficiarioPrincipal.segundo_apellido}`.replace(/\s+/g, ' ').trim(),
                
                // Documento e identificación
                id: beneficiarioPrincipal.id || '',
                tipo_documento: beneficiarioPrincipal.tipo_id || 'CC',
                
                // Contacto
                telefono: beneficiarioPrincipal.telefono || '',
                email: beneficiarioPrincipal.email || '',
                
                // Ubicación
                ciudad: beneficiarioPrincipal.ciudad || '',
                direccion: beneficiarioPrincipal.direccion || '',
                
                // Información médica
                eps: beneficiarioPrincipal.eps || '',
                plan: membresia.plan || '',
                habilitado: membresia.habilitado || beneficiarioPrincipal.estado || '',
                
                // Información del tomador
                nomTomador: membresia.nomTomador || '',
                
                // Otros campos
                edad: beneficiarioPrincipal.edad || '',
                fecha_nacimiento: beneficiarioPrincipal.nacimiento || '',
                genero: beneficiarioPrincipal.genero || '',
                
                // Datos adicionales
                codigo_beneficiario: beneficiarioPrincipal.codigo_ben || '',
                estado_civil: beneficiarioPrincipal.estadoCivil || '',
                tipo_beneficiario: beneficiarioPrincipal.tipo_ben || ''
            };

        } catch (error) {
            console.error('❌ Error fetchPatientDataFromPToken:', error);
            throw error;
        }
    }

    extractPatientInfo(session) {
        let patientData = {};
        
        // Prioridad 1: session.patient_data
        if (session.patient_data && Object.keys(session.patient_data).length > 0) {
            patientData = session.patient_data;
        }
        // Prioridad 2: session.user_data (parseado si es string)
        else if (session.user_data) {
            try {
                const userData = typeof session.user_data === 'string' 
                    ? JSON.parse(session.user_data) 
                    : session.user_data;
                
                if (userData && typeof userData === 'object') {
                    patientData = userData;
                }
            } catch (e) {
                console.warn('Error parseando user_data:', e);
            }
        }

        // Estructura final normalizada
        return {
            // Nombres
            primer_nombre: patientData.primer_nombre || patientData.firstName || patientData.nombre || '',
            segundo_nombre: patientData.segundo_nombre || patientData.middleName || '',
            primer_apellido: patientData.primer_apellido || patientData.lastName || patientData.apellido || '',
            segundo_apellido: patientData.segundo_apellido || patientData.secondLastName || '',
            nombreCompleto: patientData.nombreCompleto || patientData.fullName || patientData.name || '',
            
            // Documento e identificación
            id: patientData.id || patientData.document || patientData.documento || patientData.cedula || '',
            tipo_documento: patientData.tipo_documento || patientData.documentType || 'CC',
            
            // Contacto
            telefono: patientData.telefono || patientData.phone || patientData.celular || '',
            email: patientData.email || patientData.correo || '',
            
            // Ubicación
            ciudad: patientData.ciudad || patientData.city || patientData.municipio || '',
            departamento: patientData.departamento || patientData.state || '',
            direccion: patientData.direccion || patientData.address || '',
            
            // Información médica
            eps: patientData.eps || patientData.insurance || patientData.aseguradora || '',
            plan: patientData.plan || patientData.planType || patientData.tipoplan || '',
            habilitado: patientData.habilitado || patientData.status || patientData.estado || '',
            
            // Información del tomador
            nomTomador: patientData.nomTomador || patientData.policyHolder || patientData.tomador || '',
            
            // Otros campos
            edad: patientData.edad || patientData.age || '',
            fecha_nacimiento: patientData.fecha_nacimiento || patientData.birthDate || patientData.fechaNacimiento || '',
            genero: patientData.genero || patientData.gender || patientData.sexo || ''
        };
    }

    updatePatientInfoUI(patientInfo, session) {
        // Construir nombre completo
        const fullName = patientInfo.nombreCompleto || 
            `${patientInfo.primer_nombre} ${patientInfo.segundo_nombre} ${patientInfo.primer_apellido} ${patientInfo.segundo_apellido}`
            .replace(/\s+/g, ' ').trim() || 
            this.getPatientNameFromSession(session);

        // Actualizar encabezado del chat
        const chatPatientName = document.getElementById('chatPatientName');
        if (chatPatientName) chatPatientName.textContent = fullName;

        const chatPatientInitials = document.getElementById('chatPatientInitials');
        if (chatPatientInitials) {
            const initials = ((patientInfo.primer_nombre?.[0] || '') + (patientInfo.primer_apellido?.[0] || '')).toUpperCase() || 
                           fullName.charAt(0).toUpperCase();
            chatPatientInitials.textContent = initials;
        }

        const chatPatientId = document.getElementById('chatPatientId');
        if (chatPatientId) chatPatientId.textContent = session.id;

        const chatRoomName = document.getElementById('chatRoomName');
        if (chatRoomName) chatRoomName.textContent = this.getRoomNameFromSession(session);

        const chatSessionStatus = document.getElementById('chatSessionStatus');
        if (chatSessionStatus) chatSessionStatus.textContent = 'Activo';

        // Actualizar sidebar de información del paciente
        this.updatePatientInfoSidebar(patientInfo, fullName);
    }

    updatePatientInfoSidebar(patientInfo, fullName) {
        const updates = [
            { id: 'patientInfoName', value: fullName },
            { id: 'patientInfoDocument', value: patientInfo.id || '-' },
            { id: 'patientInfoPhone', value: patientInfo.telefono || '-' },
            { id: 'patientInfoEmail', value: patientInfo.email || '-' },
            { id: 'patientInfoCity', value: patientInfo.ciudad || '-' },
            { id: 'patientInfoEPS', value: patientInfo.eps || '-' },
            { id: 'patientInfoPlan', value: patientInfo.plan || '-' },
            { 
                id: 'patientInfoStatus', 
                value: patientInfo.habilitado === 'S' || patientInfo.habilitado === 'Activo' || patientInfo.habilitado === 'activo' 
                    ? 'Vigente' 
                    : patientInfo.habilitado === 'N' || patientInfo.habilitado === 'Inactivo' || patientInfo.habilitado === 'inactivo'
                    ? 'Inactivo'
                    : patientInfo.habilitado || 'No especificado'
            },
            { id: 'patientInfoTomador', value: patientInfo.nomTomador || '-' }
        ];

        updates.forEach(update => {
            const element = document.getElementById(update.id);
            if (element) {
                element.textContent = update.value;
            }
        });
    }

    debugPatientInfo(session = null) {
        const sessionToDebug = session || this.currentSession;
        
        if (!sessionToDebug) {
            console.log('No hay sesión para debuggear');
            return;
        }

        console.group('🔍 DEBUG: Información del Paciente');
        
        console.log('📊 Sesión completa:', sessionToDebug);
        
        console.log('📋 session.patient_data:', sessionToDebug.patient_data);
        console.log('📋 session.user_data:', sessionToDebug.user_data);
        console.log('🔑 session.ptoken:', sessionToDebug.ptoken);
        
        if (sessionToDebug.user_data && typeof sessionToDebug.user_data === 'string') {
            try {
                console.log('📋 session.user_data (parseado):', JSON.parse(sessionToDebug.user_data));
            } catch (e) {
                console.log('❌ Error parseando user_data:', e);
            }
        }
        
        const extractedInfo = this.extractPatientInfo(sessionToDebug);
        console.log('✅ Información extraída (local):', extractedInfo);
        
        // Si hay ptoken, probar consulta
        if (sessionToDebug.ptoken) {
            console.log('🔍 Probando consulta con ptoken...');
            this.fetchPatientDataFromPToken(sessionToDebug.ptoken)
                .then(ptokenData => {
                    console.log('📞 Datos desde ptoken:', ptokenData);
                })
                .catch(error => {
                    console.log('❌ Error consultando ptoken:', error);
                });
        }
        
        const fullName = extractedInfo.nombreCompleto || 
            `${extractedInfo.primer_nombre} ${extractedInfo.segundo_nombre} ${extractedInfo.primer_apellido} ${extractedInfo.segundo_apellido}`
            .replace(/\s+/g, ' ').trim() || 
            this.getPatientNameFromSession(sessionToDebug);
            
        console.log('👤 Nombre final construido:', fullName);
        
        console.log('📍 Elementos UI encontrados:');
        const uiElements = [
            'patientInfoName', 'patientInfoDocument', 'patientInfoPhone', 
            'patientInfoEmail', 'patientInfoCity', 'patientInfoEPS', 
            'patientInfoPlan', 'patientInfoStatus', 'patientInfoTomador'
        ];
        
        uiElements.forEach(id => {
            const element = document.getElementById(id);
            console.log(`- ${id}:`, element ? 'Encontrado' : 'NO ENCONTRADO');
        });
        
        console.groupEnd();
        
        // Mostrar resumen en pantalla
        this.showNotification(`Debug completado. Revisa la consola para ver los datos del paciente.`, 'info', 8000);
    }

    async connectToChatWebSocket() {
        try {
            if (this.chatSocket) {
                this.chatSocket.disconnect();
                this.isConnectedToChat = false;
                this.sessionJoined = false;
            }
            
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
                this.isConnectedToChat = true;
                this.updateChatStatus('Conectado');
                
                setTimeout(() => {
                    this.joinChatSession();
                }, 500);
            });
            
            this.chatSocket.on('disconnect', () => {
                this.isConnectedToChat = false;
                this.sessionJoined = false;
                this.updateChatStatus('Desconectado');
            });
            
            this.chatSocket.on('chat_joined', (data) => {
                this.sessionJoined = true;
                this.updateChatStatus('En chat');
                this.setupFileEventHandlers();
            });
            
            this.chatSocket.on('new_message', (data) => {
                this.handleNewChatMessage(data);
            });
            
            this.chatSocket.on('message_sent', (data) => {
                // Mensaje enviado confirmado
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
                console.error('Error en socket de chat:', error);
                this.showNotification('Error en chat: ' + (error.message || error), 'error');
            });
            
        } catch (error) {
            console.error('Error conectando WebSocket de chat:', error);
            throw error;
        }
    }

    setupFileEventHandlers() {
        if (!this.chatSocket) {
            return;
        }
        
        this.chatSocket.on('file_uploaded', (data) => {
            this.handleFileUploaded(data);
        });
    }

    joinChatSession() {
        if (!this.chatSocket || !this.currentSessionId || !this.isConnectedToChat) {
            return;
        }
        
        const currentUser = this.getCurrentUser();
        
        this.chatSocket.emit('join_chat', {
            session_id: this.currentSessionId,
            user_id: currentUser.id,
            user_type: 'agent',
            user_name: currentUser.name
        });
    }

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

        const payload = {
            session_id: this.currentSessionId,
            user_id: currentUser.id,
            user_type: 'agent',
            user_name: currentUser.name,
            message_type: 'text',
            content: message
        };

        this.chatSocket.emit('send_message', payload, (response) => {
            if (response && response.success) {
                // Mensaje enviado exitosamente
            } else {
                console.error('Error enviando mensaje:', response?.message || 'Error desconocido');
                this.showNotification('Error enviando mensaje: ' + (response?.message || 'Error desconocido'), 'error');
            }
        });

        input.value = '';
        const sendButton = document.getElementById('agentSendButton');
        if (sendButton) sendButton.disabled = true;
    }

    handleNewChatMessage(data) {
        const messagesContainer = document.getElementById('patientChatMessages');
        if (!messagesContainer) return;

        const normalizedMessage = {
            user_id: data.user_id || data.sender_id,
            user_type: data.user_type || data.sender_type,
            user_name: data.user_name || data.sender_name || 'Usuario',
            content: data.content || '',
            timestamp: data.timestamp || data.created_at || Date.now(),
            message_type: data.message_type || 'text'
        };

        if (normalizedMessage.message_type === 'file_upload' || 
            normalizedMessage.content.includes('📎') ||
            (data.file_data && data.file_data.id)) {
            
            if (data.file_data) {
                const fileData = {
                    id: data.file_data.id,
                    original_name: data.file_data.original_name || data.file_data.name,
                    file_size: data.file_data.file_size || data.file_data.size,
                    download_url: data.file_data.download_url || `${this.fileServiceUrl}/download/${data.file_data.id}`
                };
                
                const currentUser = this.getCurrentUser();
                const isMyMessage = normalizedMessage.user_type === 'agent' && normalizedMessage.user_id === currentUser.id;
                
                this.addFileMessageToChat(fileData.original_name, fileData, isMyMessage);
                return;
            }
        }

        const currentUser = this.getCurrentUser();
        const isMyMessage = normalizedMessage.user_type === 'agent' && normalizedMessage.user_id === currentUser.id;

        const time = new Date(normalizedMessage.timestamp).toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });

        const wrapper = document.createElement('div');
        wrapper.className = 'mb-4';

        if (isMyMessage) {
            wrapper.innerHTML = `
                <div class="flex justify-end">
                    <div class="max-w-xs lg:max-w-md bg-blue-600 text-white rounded-lg px-4 py-2">
                        <div class="text-xs opacity-75 mb-1">Yo (${normalizedMessage.user_name})</div>
                        <p>${this.escapeHtml(normalizedMessage.content)}</p>
                        <div class="text-xs opacity-75 mt-1">${time}</div>
                    </div>
                </div>`;
        } else {
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
    }

    handleFileUploaded(data) {
        try {
            if (data.session_id !== this.currentSessionId) {
                return;
            }
            
            const timeSinceLastUpload = Date.now() - this.lastFileUploadTime;
            if (timeSinceLastUpload < 15000) {
                return;
            }
            
            const fileData = {
                id: data.file_id,
                original_name: data.file_name,
                file_size: data.file_size,
                file_type: data.file_type,
                download_url: data.download_url || `${this.fileServiceUrl}/download/${data.file_id}`
            };
            
            this.addFileMessageToChat(data.file_name, fileData, false);
            
            this.showNotification(`${data.uploader_name || 'Usuario'} envió un archivo: ${data.file_name}`, 'info');
            
        } catch (error) {
            console.error('Error procesando archivo recibido en agente:', error);
        }
    }

    addFileMessageToChat(fileName, fileData, isMine = false) {
        const messagesContainer = document.getElementById('patientChatMessages');
        if (!messagesContainer) {
            return;
        }

        const time = new Date().toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'mb-4';

        let downloadUrl = '#';
        if (fileData && fileData.id) {
            downloadUrl = `${this.fileServiceUrl}/download/${fileData.id}`;
        } else if (fileData && fileData.download_url) {
            downloadUrl = fileData.download_url;
        }

        if (isMine) {
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
                                ${fileData && fileData.file_size ? 
                                    `<p class="text-xs opacity-75">${this.formatFileSize(fileData.file_size)}</p>` : 
                                    ''
                                }
                            </div>
                        </div>
                        ${downloadUrl !== '#' ? 
                            `<a href="${downloadUrl}" target="_blank" class="inline-flex items-center text-xs bg-blue-500 hover:bg-blue-400 px-2 py-1 rounded mt-2 transition-colors">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                                </svg>
                                Descargar
                            </a>` : 
                            ''
                        }
                        <div class="text-xs opacity-75 mt-1">${time}</div>
                    </div>
                </div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="flex justify-start">
                    <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
                        <div class="text-xs font-medium text-gray-600 mb-1">Paciente</div>
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 flex-shrink-0 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm truncate">${fileName}</p>
                                ${fileData && fileData.file_size ? 
                                    `<p class="text-xs text-gray-500">${this.formatFileSize(fileData.file_size)}</p>` : 
                                    ''
                                }
                            </div>
                        </div>
                        ${downloadUrl !== '#' ? 
                            `<a href="${downloadUrl}" target="_blank" class="inline-flex items-center text-xs bg-gray-700 hover:bg-gray-600 text-white px-2 py-1 rounded mt-2 transition-colors">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                                </svg>
                                Descargar
                            </a>` : 
                            ''
                        }
                        <div class="text-xs text-gray-500 mt-1">${time}</div>
                    </div>
                </div>
            `;
        }

        messagesContainer.appendChild(messageDiv);
        
        setTimeout(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 100);
    }

    formatFileSize(bytes) {
        if (!bytes) return '';
        
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
    }

    async loadChatHistory() {
        if (!this.currentSessionId) return;
        
        try {
            const response = await fetch(`${this.chatServiceUrl.replace(/\/$/, '')}/messages/${this.currentSessionId}?limit=50`, {
                headers: this.getAuthHeaders()
            });
            
            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data && result.data.messages) {
                    const messagesContainer = document.getElementById('patientChatMessages');
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '';
                        
                        result.data.messages.forEach((msg) => {
                            if (msg.message_type === 'file_upload' || 
                                (msg.content && (msg.content.includes('📎') || msg.content.includes('archivo'))) ||
                                (msg.file_data && msg.file_data.id)) {
                                
                                if (msg.file_data) {
                                    const fileData = {
                                        id: msg.file_data.id,
                                        original_name: msg.file_data.original_name || msg.file_data.name,
                                        file_size: msg.file_data.file_size || msg.file_data.size,
                                        download_url: msg.file_data.download_url || `${this.fileServiceUrl}/download/${msg.file_data.id}`
                                    };
                                    
                                    const currentUser = this.getCurrentUser();
                                    const isMyMessage = (msg.sender_type || msg.user_type) === 'agent' && 
                                                       (msg.sender_id || msg.user_id) === currentUser.id;
                                    
                                    this.addFileMessageToChat(fileData.original_name, fileData, isMyMessage);
                                } else {
                                    let fileName = 'Archivo';
                                    if (msg.content && msg.content.includes('📎')) {
                                        const match = msg.content.match(/📎\s*(.+)/);
                                        if (match) {
                                            fileName = match[1].trim();
                                        }
                                    }
                                    
                                    const basicFileData = {
                                        id: null,
                                        original_name: fileName,
                                        file_size: null,
                                        download_url: '#'
                                    };
                                    
                                    const currentUser = this.getCurrentUser();
                                    const isMyMessage = (msg.sender_type || msg.user_type) === 'agent' && 
                                                       (msg.sender_id || msg.user_id) === currentUser.id;
                                    
                                    this.addFileMessageToChat(fileName, basicFileData, isMyMessage);
                                }
                            } else {
                                this.handleNewChatMessage({
                                    content: msg.content,
                                    user_type: msg.sender_type || msg.user_type,
                                    user_id: msg.sender_id || msg.user_id,
                                    user_name: msg.sender_name || msg.user_name || 'Usuario',
                                    timestamp: msg.timestamp || msg.created_at,
                                    message_type: msg.message_type || 'text'
                                });
                            }
                        });
                    }
                }
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
        } catch (error) {
            console.error('Error cargando historial:', error);
            this.showNotification('Error cargando historial de chat', 'warning');
        }
    }

    async loadAvailableAgentsForTransfer() {
        const agentSelect = document.getElementById('targetAgentSelect');
        const loadingSpinner = document.getElementById('agentLoadingSpinner');
        
        try {
            agentSelect.innerHTML = '<option value="">Cargando agentes...</option>';
            agentSelect.disabled = true;
            if (loadingSpinner) loadingSpinner.classList.remove('hidden');
            
            const agents = await this.loadAvailableAgents();
            
            agentSelect.innerHTML = '<option value="">Selecciona un agente...</option>';
            
            if (agents.length === 0) {
                agentSelect.innerHTML = '<option value="">No hay agentes disponibles</option>';
            } else {
                agents.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.id;
                    option.textContent = `${agent.name} (${agent.email})`;
                    agentSelect.appendChild(option);
                });
            }
            
            agentSelect.disabled = false;
            if (loadingSpinner) loadingSpinner.classList.add('hidden');
            
        } catch (error) {
            console.error('Error cargando agentes:', error);
            agentSelect.innerHTML = '<option value="">Error cargando agentes</option>';
            agentSelect.disabled = false;
            if (loadingSpinner) loadingSpinner.classList.add('hidden');
            this.showNotification('Error cargando lista de agentes', 'error');
        }
    }

    async endSession(reason, notes) {
        if (!this.currentSessionId) {
            throw new Error('No hay sesión activa');
        }

        const response = await fetch(`${this.chatServiceUrl}/chats/sessions/${this.currentSessionId}/end`, {
            method: 'POST',
            headers: this.getAuthHeaders(),
            body: JSON.stringify({
                reason: reason,
                notes: notes
            })
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `Error HTTP ${response.status}`);
        }

        const result = await response.json();
        if (result.success) {
            this.showNotification('Sesión finalizada exitosamente', 'success');
            this.disconnectFromCurrentSession();
        }
        return result;
    }

    disconnectFromCurrentSession() {
        if (this.chatSocket) {
            this.chatSocket.disconnect();
            this.isConnectedToChat = false;
            this.sessionJoined = false;
        }
        this.currentSessionId = null;
        this.currentSession = null;
        window.currentSession = null;
        
        if (typeof window.showPendingSection === 'function') {
            window.showPendingSection();
        }
    }

    async loadAvailableAgents() {
        try {
            const response = await fetch(`${this.chatServiceUrl}/chats/agents/available`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (!response.ok) {
                throw new Error(`Error HTTP ${response.status}`);
            }

            const result = await response.json();
            if (result.success && result.data && result.data.agents) {
                const currentUser = this.getCurrentUser();
                return result.data.agents.filter(agent => agent.id !== currentUser.id);
            } else {
                throw new Error('No se pudieron cargar los agentes');
            }

        } catch (error) {
            console.error('Error cargando agentes:', error);
            this.showNotification('Error cargando lista de agentes', 'warning');
            return [];
        }
    }

    async returnToQueue(reason) {
        if (!this.currentSessionId) {
            throw new Error('No hay sesión activa');
        }

        try {
            const response = await fetch(`${this.chatServiceUrl}/chats/sessions/${this.currentSessionId}/return`, {
                method: 'PUT',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    reason: reason
                })
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }

            const result = await response.json();
            if (result.success) {
                this.showNotification('Sesión devuelta a cola', 'success');
                this.disconnectFromCurrentSession();
            }
            return result;

        } catch (error) {
            console.error('Error devolviendo a cola:', error);
            throw error;
        }
    }

    async requestExternalTransfer(targetRoom, reason) {
        if (!this.currentSessionId) {
            throw new Error('No hay sesión activa');
        }

        const response = await fetch(`${this.chatServiceUrl}/transfers/request`, {
            method: 'POST',
            headers: this.getAuthHeaders(),
            body: JSON.stringify({
                session_id: this.currentSessionId,
                from_agent_id: this.getCurrentUser().id,
                to_room: targetRoom,
                reason: reason,
                priority: 'medium'
            })
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `Error HTTP ${response.status}`);
        }

        const result = await response.json();
        if (result.success) {
            this.showNotification('Solicitud de transferencia enviada', 'success');
            this.disconnectFromCurrentSession();
        }
        return result;
    }

    async transferInternal(targetAgentId, reason) {
        if (!this.currentSessionId) {
            throw new Error('No hay sesión activa');
        }

        const response = await fetch(`${this.chatServiceUrl}/transfers/internal`, {
            method: 'POST',
            headers: this.getAuthHeaders(),
            body: JSON.stringify({
                session_id: this.currentSessionId,
                from_agent_id: this.getCurrentUser().id,
                to_agent_id: targetAgentId,
                reason: reason
            })
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `Error HTTP ${response.status}`);
        }

        const result = await response.json();
        if (result.success) {
            this.showNotification('Transferencia interna exitosa', 'success');
            this.disconnectFromCurrentSession();
        }
        return result;
    }

    async uploadFile(file, description = '') {
        if (!file || !this.currentSessionId) {
            throw new Error('Faltan datos básicos para upload');
        }
        
        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('session_id', this.currentSessionId);
            formData.append('user_id', this.getCurrentUser().id);
            if (description) formData.append('description', description);
            
            const response = await fetch(`${this.fileServiceUrl}/upload`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.lastFileUploadTime = Date.now();
                return result.data;
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
        }
    }

    findSessionById(sessionId) {
        for (const roomSessions of Object.values(this.sessionsByRoom)) {
            const session = roomSessions.find(s => s.id === sessionId);
            if (session) return session;
        }
        return null;
    }

    async getPatientNameFromSessionAsync(session) {
        if (!session) {
            return 'Paciente';
        }
        
        try {
            const patientInfo = await this.getPatientInfoWithPToken(session);
            
            if (patientInfo.nombreCompleto) {
                return patientInfo.nombreCompleto;
            }
            
            const fullName = `${patientInfo.primer_nombre} ${patientInfo.segundo_nombre} ${patientInfo.primer_apellido} ${patientInfo.segundo_apellido}`
                .replace(/\s+/g, ' ').trim();
                
            return fullName || 'Paciente';
        } catch (error) {
            console.warn('Error obteniendo nombre del paciente:', error);
            return this.getPatientNameFromSession(session);
        }
    }

    getPatientNameFromSession(session) {
        if (!session) {
            return 'Paciente';
        }
        
        // Usar el extractor para obtener el nombre (versión síncrona)
        const patientInfo = this.extractPatientInfo(session);
        
        // Intentar con nombreCompleto primero
        if (patientInfo.nombreCompleto) {
            return patientInfo.nombreCompleto;
        }
        
        // Construir nombre completo desde partes
        const fullName = `${patientInfo.primer_nombre} ${patientInfo.segundo_nombre} ${patientInfo.primer_apellido} ${patientInfo.segundo_apellido}`
            .replace(/\s+/g, ' ').trim();
            
        if (fullName) {
            return fullName;
        }
        
        // Fallback: buscar en user_data directamente (método anterior)
        if (session.user_data) {
            try {
                const userData = typeof session.user_data === 'string' 
                    ? JSON.parse(session.user_data) 
                    : session.user_data;
                
                if (userData && userData.nombreCompleto) return userData.nombreCompleto;
                if (userData && userData.name) return userData.name;
            } catch (e) {
                console.warn('Error parseando user_data en getPatientNameFromSession:', e);
            }
        }
        
        return 'Paciente';
    }

    getRoomNameFromSession(session) {
        if (!session) {
            return 'Sala General';
        }
        
        if (session.room_id) {
            const roomNames = {
                'general': 'Consultas Generales',
                'medical': 'Consultas Médicas', 
                'support': 'Soporte Técnico',
                'emergency': 'Emergencias',
                
                '1': 'Consultas Generales',
                '2': 'Consultas Médicas',
                '3': 'Soporte Técnico', 
                '4': 'Emergencias',
                
                'consulta_general': 'Consultas Generales',
                'consulta_medica': 'Consultas Médicas',
                'soporte_tecnico': 'Soporte Técnico',
                'emergencias': 'Emergencias',
                
                'GENERAL': 'Consultas Generales',
                'MEDICAL': 'Consultas Médicas',
                'SUPPORT': 'Soporte Técnico',
                'EMERGENCY': 'Emergencias'
            };
            
            const roomIdString = String(session.room_id).toLowerCase();
            
            if (roomNames[session.room_id]) {
                return roomNames[session.room_id];
            }
            
            if (roomNames[roomIdString]) {
                return roomNames[roomIdString];
            }
            
            for (const [key, value] of Object.entries(roomNames)) {
                if (key.includes(roomIdString) || roomIdString.includes(key)) {
                    return value;
                }
            }
            
            const formattedName = String(session.room_id)
                .replace(/_/g, ' ')
                .replace(/-/g, ' ')
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');
            
            return `Sala ${formattedName}`;
        }
        
        return 'Sala General';
    }

    getWaitTime(createdAt) {
        const diff = Date.now() - new Date(createdAt).getTime();
        const minutes = Math.floor(diff / 60000);
        
        if (minutes < 1) return 'menos de 1 min';
        if (minutes < 60) return `${minutes} min`;
        
        const hours = Math.floor(minutes / 60);
        const remainingMins = minutes % 60;
        return `${hours}h ${remainingMins}m`;
    }

    getActiveTime(updatedAt) {
        const diff = Date.now() - new Date(updatedAt).getTime();
        const minutes = Math.floor(diff / 60000);
        
        if (minutes < 1) return 'hace menos de 1 min';
        if (minutes < 60) return `hace ${minutes} min`;
        
        const hours = Math.floor(minutes / 60);
        const remainingMins = minutes % 60;
        return `hace ${hours}h ${remainingMins}m`;
    }

    getUrgencyClass(waitTime) {
        if (waitTime.includes('h') || parseInt(waitTime) > 30) {
            return 'text-red-600 font-semibold countdown-urgent';
        } else if (parseInt(waitTime) > 15) {
            return 'text-yellow-600 font-semibold';
        }
        return 'text-green-600';
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
        } catch (error) {
            console.error('Error inicializando:', error);
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

window.staffClient = new StaffClient();

window.debugPatientInfo = () => {
    if (window.staffClient) {
        window.staffClient.debugPatientInfo();
    } else {
        console.error('StaffClient no disponible');
    }
};

window.testPTokenConsulta = async (ptoken = null) => {
    const tokenToTest = ptoken || (window.staffClient?.currentSession?.ptoken) || 'CC678AVEZVKADBT';
    
    console.log('🧪 Probando consulta de ptoken:', tokenToTest);
    
    try {
        const result = await window.staffClient.fetchPatientDataFromPToken(tokenToTest);
        console.log('✅ Resultado de la consulta:', result);
        return result;
    } catch (error) {
        console.error('❌ Error en la consulta:', error);
        return null;
    }
};

window.refreshPatientInfo = async () => {
    if (!window.staffClient?.currentSession) {
        console.log('❌ No hay sesión activa');
        return;
    }
    
    console.log('🔄 Refrescando información del paciente...');
    
    try {
        const patientInfo = await window.staffClient.getPatientInfoWithPToken(window.staffClient.currentSession);
        window.staffClient.updatePatientInfoUI(patientInfo, window.staffClient.currentSession);
        console.log('✅ Información del paciente actualizada');
        window.staffClient.showNotification('Información del paciente actualizada', 'success');
    } catch (error) {
        console.error('❌ Error refrescando información:', error);
        window.staffClient.showNotification('Error actualizando información del paciente', 'error');
    }
};

window.refreshAllPatientNames = async () => {
    if (!window.staffClient) {
        console.log('❌ StaffClient no disponible');
        return;
    }
    
    console.log('🔄 Refrescando todos los nombres de pacientes...');
    
    try {
        // Refresh nombres en conversaciones pendientes
        if (window.staffClient.pendingConversations?.length > 0) {
            await window.staffClient.updateConversationNamesAsync(window.staffClient.pendingConversations, 'pending');
        }
        
        // Refresh nombres en mis chats
        if (window.staffClient.myChats?.length > 0) {
            await window.staffClient.updateConversationNamesAsync(window.staffClient.myChats, 'mychats');
        }
        
        // Refresh nombres en sidebar
        await window.staffClient.loadChatsSidebar();
        
        console.log('✅ Todos los nombres actualizados');
        window.staffClient.showNotification('Nombres de pacientes actualizados', 'success');
    } catch (error) {
        console.error('❌ Error refrescando nombres:', error);
        window.staffClient.showNotification('Error actualizando nombres', 'error');
    }
};

window.testPatientNameExtraction = async (ptoken = 'CC678AVEZVKADBT') => {
    console.log('🧪 Probando extracción de nombre completo...');
    
    try {
        const result = await window.staffClient.fetchPatientDataFromPToken(ptoken);
        console.log('📊 Datos extraídos:', result);
        
        const fullName = result.nombreCompleto || 
            `${result.primer_nombre} ${result.segundo_nombre} ${result.primer_apellido} ${result.segundo_apellido}`
            .replace(/\s+/g, ' ').trim();
            
        console.log('👤 Nombre completo construido:', fullName);
        console.log('🆔 Documento:', result.id);
        console.log('📞 Teléfono:', result.telefono);
        console.log('📧 Email:', result.email);
        console.log('🏥 EPS:', result.eps);
        console.log('📋 Plan:', result.plan);
        console.log('👥 Tomador:', result.nomTomador);
        
        return result;
    } catch (error) {
        console.error('❌ Error en prueba:', error);
        return null;
    }
};

function handleAgentFileUpload(files) {
    if (!files || files.length === 0) return;
    
    if (!window.staffClient) {
        alert('Error: StaffClient no está listo');
        return;
    }
    
    const file = files[0];
    
    if (file.size > 10 * 1024 * 1024) {
        const sizeMB = Math.round(file.size / 1024 / 1024 * 100) / 100;
        alert(`Archivo muy grande: ${sizeMB}MB (máximo 10MB)`);
        return;
    }
    
    if (!window.staffClient.currentSessionId) {
        alert('Error: No hay sesión activa');
        return;
    }
    
    if (!window.staffClient.isConnectedToChat || !window.staffClient.sessionJoined) {
        alert('Error: Chat no está conectado. Inténtalo de nuevo.');
        return;
    }
    
    const fileInput = document.getElementById('agentFileInput');
    const uploadButton = document.querySelector('button[onclick*="agentFileInput"]') || 
                         document.getElementById('agentUploadButton');
    
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
    
    window.staffClient.uploadFile(file)
        .then((result) => {
            if (fileInput) {
                fileInput.value = '';
            }
            
            let fileData = null;
            let fileName = file.name;
            
            if (result && result.file) {
                fileData = result.file;
                fileName = fileData.original_name || fileData.file_name || file.name;
            } else if (result && result.original_name) {
                fileData = result;
                fileName = result.original_name;
            }
            
            window.staffClient.addFileMessageToChat(fileName, fileData, true);
            
            window.staffClient.showNotification(`Archivo subido: ${fileName}`, 'success');
            
        })
        .catch((error) => {
            window.staffClient.showNotification('Error subiendo archivo: ' + error.message, 'error');
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

window.handleAgentFileUpload = handleAgentFileUpload;

document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('agentMessageInput');
    const sendButton = document.getElementById('agentSendButton');
    
    if (messageInput && sendButton) {
        messageInput.addEventListener('input', function() {
            sendButton.disabled = this.value.trim() === '';
        });
        
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() && staffClient.isConnectedToChat) {
                    staffClient.sendMessage();
                }
            }
        });
        
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
*/