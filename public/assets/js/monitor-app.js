const API_BASE = 'http://187.33.158.246';
const CHAT_API = `${API_BASE}/chat`;
const AUTH_API = `${API_BASE}/auth`;

let monitorSocket = null;
let currentChatSession = null;
let autoRefreshInterval = null;
let roomsData = [];

class MonitorClient {
    constructor() {
        this.refreshInterval = 30000; // 30 segundos
        this.autoRefresh = true;
    }

    getToken() {
        const tokenMeta = document.querySelector('meta[name="monitor-token"]')?.content;
        return tokenMeta && tokenMeta.trim() !== '' ? tokenMeta : null;
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
        const userMeta = document.querySelector('meta[name="monitor-user"]');
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

    async init() {
        console.log('🚀 Inicializando Monitor Global...');
        await this.loadAllRooms();
        this.startAutoRefresh();
        console.log('✅ Monitor Global inicializado');
    }

    async loadAllRooms() {
        try {
            console.log('📥 Cargando todas las salas...');
            
            // Obtener lista de salas
            const roomsResponse = await fetch(`${API_BASE}/admin/rooms?include_stats=true`, {
                headers: this.getAuthHeaders()
            });
            
            if (!roomsResponse.ok) throw new Error('Error cargando salas');
            
            const roomsResult = await roomsResponse.json();
            const rooms = roomsResult.data?.rooms || [];
            
            // Cargar sesiones para cada sala
            const roomPromises = rooms.map(room => this.loadRoomSessions(room));
            roomsData = await Promise.all(roomPromises);
            
            this.updateGlobalStats();
            this.renderRooms();
            
        } catch (error) {
            console.error('❌ Error cargando salas:', error);
            this.showNotification('Error cargando información de las salas', 'error');
        }
    }

    async loadRoomSessions(room) {
        try {
            const response = await fetch(`${CHAT_API}/chats/sessions?room_id=${room.id}&status=all&limit=50`, {
                headers: this.getAuthHeaders()
            });
            
            if (!response.ok) return { ...room, sessions: [], waiting: 0, active: 0 };
            
            const result = await response.json();
            const sessions = result.data?.sessions || [];
            
            const waiting = sessions.filter(s => s.status === 'waiting').length;
            const active = sessions.filter(s => s.status === 'active').length;
            
            return {
                ...room,
                sessions: sessions,
                waiting: waiting,
                active: active,
                total: sessions.length
            };
            
        } catch (error) {
            console.warn(`⚠️ Error cargando sesiones para sala ${room.name}:`, error);
            return { ...room, sessions: [], waiting: 0, active: 0 };
        }
    }

    updateGlobalStats() {
        const totalRooms = roomsData.length;
        const totalWaiting = roomsData.reduce((sum, room) => sum + room.waiting, 0);
        const totalActive = roomsData.reduce((sum, room) => sum + room.active, 0);
        
        document.getElementById('totalRoomsCount').textContent = totalRooms;
        document.getElementById('totalWaitingCount').textContent = totalWaiting;
        document.getElementById('totalActiveCount').textContent = totalActive;
    }

    applyFilters() {
        const viewFilter = document.getElementById('viewFilter').value;
        const sortFilter = document.getElementById('sortFilter').value;
        
        let filteredRooms = [...roomsData];
        
        // Aplicar filtro de vista
        if (viewFilter === 'active') {
            filteredRooms = filteredRooms.filter(room => room.active > 0);
        } else if (viewFilter === 'waiting') {
            filteredRooms = filteredRooms.filter(room => room.waiting > 0);
        }
        
        // Aplicar ordenamiento
        if (sortFilter === 'waiting_desc') {
            filteredRooms.sort((a, b) => b.waiting - a.waiting);
        } else if (sortFilter === 'active_desc') {
            filteredRooms.sort((a, b) => b.active - a.active);
        } else if (sortFilter === 'name_asc') {
            filteredRooms.sort((a, b) => a.name.localeCompare(b.name));
        }
        
        this.renderRooms(filteredRooms);
    }

    renderRooms(rooms = roomsData) {
        const grid = document.getElementById('roomsGrid');
        
        if (rooms.length === 0) {
            grid.innerHTML = `
                <div class="col-span-full empty-state">
                    <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="text-gray-400 text-lg">No se encontraron salas</p>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = rooms.map(room => this.createRoomCard(room)).join('');
    }

    createRoomCard(room) {
        const waitingSessions = room.sessions.filter(s => s.status === 'waiting');
        const activeSessions = room.sessions.filter(s => s.status === 'active');
        
        const roomInitial = room.name.charAt(0).toUpperCase();
        
        return `
            <div class="room-card" data-room-id="${room.id}">
                <div class="room-header">
                    <div class="flex items-center gap-3">
                        <div class="room-icon">${roomInitial}</div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">${room.name}</h3>
                            <p class="text-sm text-gray-400">${room.description || 'Sin descripción'}</p>
                        </div>
                    </div>
                </div>
                
                <div class="room-stats">
                    <div class="stat-box">
                        <div class="stat-value text-yellow-400">${room.waiting}</div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value text-green-400">${room.active}</div>
                        <div class="stat-label">Activos</div>
                    </div>
                </div>
                
                <!-- Waiting Sessions -->
                ${waitingSessions.length > 0 ? `
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-gray-400 mb-2">En Espera (${waitingSessions.length})</h4>
                        <div class="session-list">
                            ${waitingSessions.slice(0, 3).map(session => this.createSessionItem(session, 'waiting')).join('')}
                        </div>
                        ${waitingSessions.length > 3 ? `
                            <button onclick="monitorClient.showAllSessions('${room.id}', 'waiting')" 
                                    class="mt-2 text-xs text-blue-400 hover:text-blue-300">
                                Ver ${waitingSessions.length - 3} más...
                            </button>
                        ` : ''}
                    </div>
                ` : ''}
                
                <!-- Active Sessions -->
                ${activeSessions.length > 0 ? `
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-gray-400 mb-2">Activos (${activeSessions.length})</h4>
                        <div class="session-list">
                            ${activeSessions.slice(0, 3).map(session => this.createSessionItem(session, 'active')).join('')}
                        </div>
                        ${activeSessions.length > 3 ? `
                            <button onclick="monitorClient.showAllSessions('${room.id}', 'active')" 
                                    class="mt-2 text-xs text-blue-400 hover:text-blue-300">
                                Ver ${activeSessions.length - 3} más...
                            </button>
                        ` : ''}
                    </div>
                ` : ''}
                
                ${room.sessions.length === 0 ? `
                    <div class="mt-4 text-center py-8 text-gray-500 text-sm">
                        <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        Sin actividad
                    </div>
                ` : ''}
            </div>
        `;
    }

    createSessionItem(session, type) {
        const userName = session.user_name || 'Usuario';
        const agentName = session.agent_name || 'Sin asignar';
        const duration = session.duration_minutes || session.waiting_time_minutes || 0;
        
        return `
            <div class="session-item" onclick="monitorClient.openChat('${session.id || session.session_id}', '${session.room_id}')">
                <div class="flex items-start justify-between mb-1">
                    <div class="flex-1">
                        <div class="text-sm font-medium text-white">${userName}</div>
                        <div class="text-xs text-gray-400">${agentName}</div>
                    </div>
                    <span class="px-2 py-1 text-xs font-medium rounded status-${type}">
                        ${type === 'waiting' ? 'Esperando' : 'Activo'}
                    </span>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span>${duration} min</span>
                    <span class="text-blue-400 hover:text-blue-300">Ver chat →</span>
                </div>
            </div>
        `;
    }

    async openChat(sessionId, roomId) {
        try {
            console.log('📖 Abriendo chat:', sessionId);
            
            // Mostrar modal con loading
            const modal = document.getElementById('chatModal');
            modal.classList.remove('hidden');
            
            const messagesContainer = document.getElementById('chatModalMessages');
            messagesContainer.innerHTML = `
                <div class="text-center py-20">
                    <div class="loading-spinner mx-auto mb-4"></div>
                    <p class="text-gray-400">Cargando historial...</p>
                </div>
            `;
            
            // Cargar información de la sesión
            const sessionResponse = await fetch(`${CHAT_API}/chats/sessions?session_id=${sessionId}`, {
                headers: this.getAuthHeaders()
            });
            
            if (!sessionResponse.ok) throw new Error('Error cargando sesión');
            
            const sessionResult = await sessionResponse.json();
            const session = sessionResult.data?.sessions?.[0];
            
            if (!session) throw new Error('Sesión no encontrada');
            
            // Actualizar header del modal
            this.updateChatModalHeader(session);
            
            // Cargar historial de mensajes
            await this.loadChatHistory(sessionId);
            
            // Guardar sesión actual
            currentChatSession = session;
            
            // Conectar WebSocket para actualizaciones en tiempo real
            this.connectChatWebSocket(sessionId);
            
        } catch (error) {
            console.error('❌ Error abriendo chat:', error);
            this.showNotification('Error abriendo chat: ' + error.message, 'error');
            this.closeChat();
        }
    }

    updateChatModalHeader(session) {
        const userName = session.user_name || 'Paciente';
        const agentName = session.agent_name || 'Sin asignar';
        const roomName = session.room_name || 'Sala';
        
        const initials = userName.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
        
        document.getElementById('chatModalInitials').textContent = initials;
        document.getElementById('chatModalTitle').textContent = userName;
        document.getElementById('chatModalSubtitle').textContent = `${roomName} • ${agentName}`;
    }

    async loadChatHistory(sessionId) {
        try {
            const response = await fetch(`${CHAT_API}/messages/${sessionId}?limit=100`, {
                headers: this.getAuthHeaders()
            });
            
            if (!response.ok) throw new Error('Error cargando mensajes');
            
            const result = await response.json();
            const messages = result.data?.messages || [];
            
            const container = document.getElementById('chatModalMessages');
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <svg class="w-12 h-12 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p class="text-gray-400">No hay mensajes en esta conversación</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = messages.map(msg => this.createMessageBubble(msg)).join('');
            
            // Scroll al final
            setTimeout(() => {
                container.scrollTop = container.scrollHeight;
            }, 100);
            
        } catch (error) {
            console.error('❌ Error cargando historial:', error);
            document.getElementById('chatModalMessages').innerHTML = `
                <div class="empty-state">
                    <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-gray-400">Error cargando mensajes</p>
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
        
        return `
            <div class="chat-message message-${senderType}">
                <div class="message-bubble">
                    <div class="message-sender">${senderLabel}</div>
                    <div class="message-content">${content}</div>
                    <div class="message-time">${time}</div>
                </div>
            </div>
        `;
    }

    connectChatWebSocket(sessionId) {
        if (monitorSocket) {
            monitorSocket.disconnect();
        }
        
        const token = this.getToken();
        const currentUser = this.getCurrentUser();
        
        console.log('🔌 Conectando WebSocket para sesión:', sessionId);
        
        monitorSocket = io(API_BASE, {
            transports: ['websocket', 'polling'],
            auth: {
                token: token,
                user_id: currentUser.id,
                user_type: 'admin',
                user_name: currentUser.name,
                session_id: sessionId,
                monitor_mode: true
            }
        });
        
        monitorSocket.on('connect', () => {
            console.log('✅ WebSocket conectado (modo monitor)');
            
            // Unirse a la sesión en modo observador
            monitorSocket.emit('join_chat', {
                session_id: sessionId,
                user_id: currentUser.id,
                user_type: 'admin',
                user_name: currentUser.name,
                monitor_mode: true
            });
        });
        
        monitorSocket.on('disconnect', () => {
            console.log('❌ WebSocket desconectado');
        });
        
        monitorSocket.on('new_message', (data) => {
            console.log('💬 Nuevo mensaje en tiempo real:', data);
            this.appendNewMessage(data);
        });
        
        monitorSocket.on('chat_joined', (data) => {
            console.log('✅ Observador unido al chat:', data);
        });
        
        monitorSocket.on('error', (error) => {
            console.error('❌ Error en WebSocket:', error);
        });
    }

    appendNewMessage(messageData) {
        const container = document.getElementById('chatModalMessages');
        if (!container) return;
        
        // Remover empty state si existe
        const emptyState = container.querySelector('.empty-state');
        if (emptyState) {
            emptyState.remove();
        }
        
        const messageBubble = this.createMessageBubble(messageData);
        container.insertAdjacentHTML('beforeend', messageBubble);
        
        // Scroll al final
        container.scrollTop = container.scrollHeight;
        
        // Animación de nuevo mensaje
        const lastMessage = container.lastElementChild;
        if (lastMessage) {
            lastMessage.classList.add('new-item');
            setTimeout(() => {
                lastMessage.classList.remove('new-item');
            }, 3000);
        }
    }

    closeChat() {
        const modal = document.getElementById('chatModal');
        modal.classList.add('hidden');
        
        if (monitorSocket) {
            monitorSocket.disconnect();
            monitorSocket = null;
        }
        
        currentChatSession = null;
    }

    showAllSessions(roomId, type) {
        const room = roomsData.find(r => r.id === roomId);
        if (!room) return;
        
        const sessions = room.sessions.filter(s => s.status === type);
        const typeName = type === 'waiting' ? 'En Espera' : 'Activos';
        
        // Crear modal simple para mostrar todas las sesiones
        const modalHtml = `
            <div class="modal" id="allSessionsModal" style="display: flex;">
                <div class="modal-content" style="max-width: 600px;">
                    <div class="modal-header">
                        <div class="flex items-center justify-between w-full">
                            <h3 class="text-lg font-semibold text-white">${room.name} - ${typeName} (${sessions.length})</h3>
                            <button onclick="document.getElementById('allSessionsModal').remove()" 
                                    class="p-2 text-gray-400 hover:text-white rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="space-y-2">
                            ${sessions.map(session => this.createSessionItem(session, type)).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    async refreshAll() {
        console.log('🔄 Actualizando todas las salas...');
        this.showNotification('Actualizando...', 'info', 1000);
        await this.loadAllRooms();
    }

    startAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
        
        if (this.autoRefresh) {
            autoRefreshInterval = setInterval(() => {
                // Solo refrescar si no hay modal abierto
                const modal = document.getElementById('chatModal');
                if (modal.classList.contains('hidden')) {
                    this.loadAllRooms();
                }
            }, this.refreshInterval);
            
            console.log(`✅ Auto-refresh activado (cada ${this.refreshInterval / 1000}s)`);
        }
    }

    stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
            console.log('❌ Auto-refresh desactivado');
        }
    }

    toggleAutoRefresh() {
        const toggle = document.getElementById('autoRefreshToggle');
        const status = document.getElementById('autoRefreshStatus');
        
        this.autoRefresh = toggle.checked;
        status.textContent = this.autoRefresh ? 'ON' : 'OFF';
        
        if (this.autoRefresh) {
            this.startAutoRefresh();
            this.showNotification('Auto-refresh activado', 'success', 2000);
        } else {
            this.stopAutoRefresh();
            this.showNotification('Auto-refresh desactivado', 'info', 2000);
        }
    }

    showNotification(message, type = 'info', duration = 4000) {
        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            info: 'bg-blue-600',
            warning: 'bg-yellow-600'
        };
        
        const icons = {
            success: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>`,
            error: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>`,
            info: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>`,
            warning: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>`
        };
        
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg max-w-sm animate-slide-in`;
        notification.innerHTML = `
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${icons[type]}
                </svg>
                <span class="flex-1">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 hover:opacity-75">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'slide-out 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }
        }, duration);
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    destroy() {
        this.stopAutoRefresh();
        if (monitorSocket) {
            monitorSocket.disconnect();
            monitorSocket = null;
        }
    }
}

// Inicializar cliente global
window.monitorClient = new MonitorClient();

// Función global para cerrar modal desde HTML
window.closeAllSessionsModal = function() {
    const modal = document.getElementById('allSessionsModal');
    if (modal) modal.remove();
};

// Función global de logout
function logout() {
    if (confirm('¿Cerrar sesión?')) {
        monitorClient.destroy();
        localStorage.clear();
        sessionStorage.clear();
        window.location.href = 'logout.php';
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', async () => {
    console.log('🚀 Monitor Global cargado');
    
    try {
        await monitorClient.init();
    } catch (error) {
        console.error('❌ Error inicializando monitor:', error);
        monitorClient.showNotification('Error de inicialización', 'error');
    }
});

// Cerrar modal con ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const chatModal = document.getElementById('chatModal');
        if (chatModal && !chatModal.classList.contains('hidden')) {
            monitorClient.closeChat();
        }
        
        const allSessionsModal = document.getElementById('allSessionsModal');
        if (allSessionsModal) {
            allSessionsModal.remove();
        }
    }
});

// Limpiar al salir
window.addEventListener('beforeunload', () => {
    monitorClient.destroy();
});