class AdminClient {
    constructor() {
        this.adminServiceUrl = 'http://localhost:3013/admin';
        this.refreshInterval = null;
        this.refreshIntervalTime = 30000; // 30 segundos
        
        console.log('🔧 AdminClient inicializado');
    }

    getToken() {
        const phpTokenMeta = document.querySelector('meta[name="admin-token"]')?.content;
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
        const userMeta = document.querySelector('meta[name="admin-user"]');
        if (userMeta && userMeta.content) {
            try {
                return JSON.parse(userMeta.content);
            } catch (e) {
                console.warn('Error parsing user meta:', e);
            }
        }
        return null;
    }

    // ====== DASHBOARD ======
    async loadDashboard() {
        try {
            console.log('📊 Cargando dashboard...');
            
            const response = await fetch(`${this.adminServiceUrl}/reports/dashboard`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data) {
                    this.updateDashboardStats(result.data);
                    return result.data;
                } else {
                    console.warn('Respuesta del dashboard sin datos:', result);
                    this.updateDashboardStats({});
                }
            } else {
                console.warn('Error HTTP del dashboard:', response.status);
                this.updateDashboardStats({});
            }
            
        } catch (error) {
            console.error('❌ Error cargando dashboard:', error);
            this.updateDashboardStats({});
        }
    }

    updateDashboardStats(data) {
        const updates = {
            'stat-total-rooms': data.total_rooms || 0,
            'stat-assigned-agents': data.assigned_agents || 0,
            'stat-active-sessions': data.active_sessions || 0,
            'stat-performance': (data.system_performance || 0) + '%'
        };

        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        });
        
        console.log('📊 Dashboard actualizado:', updates);
    }

    async refreshDashboard() {
        this.showNotification('Actualizando métricas...', 'info');
        await this.loadDashboard();
        this.showNotification('Métricas actualizadas', 'success');
    }

    // ====== GESTIÓN DE SALAS (RF1) ======
    async loadRooms() {
        try {
            console.log('🏠 Cargando salas...');
            
            const response = await fetch(`${this.adminServiceUrl}/rooms`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                const rooms = result.data?.rooms || result.rooms || [];
                
                this.displayRooms(rooms);
                return rooms;
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('❌ Error cargando salas:', error);
            this.displayRooms([]);
            this.showError('Error cargando salas: ' + error.message);
        }
    }

    displayRooms(rooms) {
        const container = document.getElementById('roomsContainer');
        if (!container) return;

        if (rooms.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                    </svg>
                    <p class="text-gray-500">No hay salas registradas</p>
                    <button onclick="showCreateRoomModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Crear Primera Sala
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                ${rooms.map(room => this.createRoomCard(room)).join('')}
            </div>
        `;
    }

    createRoomCard(room) {
        const statusClass = this.getRoomStatusClass(room.status);
        const statusText = this.getRoomStatusText(room.status);
        
        return `
            <div class="room-card">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 ${this.getRoomColorClass(room.type)} rounded-lg flex items-center justify-center">
                            ${this.getRoomIcon(room.type)}
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">${room.name}</h3>
                            <p class="text-sm text-gray-500 capitalize">${room.type || 'General'}</p>
                        </div>
                    </div>
                    
                    <span class="px-2 py-1 ${statusClass} text-xs font-medium rounded-full">${statusText}</span>
                </div>
                
                <p class="text-gray-600 text-sm mb-4">${room.description || 'Sin descripción'}</p>
                
                <div class="grid grid-cols-2 gap-4 text-center mb-4">
                    <div>
                        <div class="text-xl font-bold text-blue-600">${room.capacity || 10}</div>
                        <div class="text-xs text-gray-500">Capacidad</div>
                    </div>
                    <div>
                        <div class="text-xl font-bold text-green-600">${room.current_sessions || 0}</div>
                        <div class="text-xs text-gray-500">Sesiones</div>
                    </div>
                </div>
                
                <div class="flex space-x-2">
                    <button onclick="adminClient.editRoom('${room.id}')" 
                            class="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                        Editar
                    </button>
                    <button onclick="adminClient.toggleRoomStatus('${room.id}')" 
                            class="flex-1 px-3 py-2 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                        ${room.status === 'active' ? 'Desactivar' : 'Activar'}
                    </button>
                    <button onclick="adminClient.deleteRoom('${room.id}')" 
                            class="px-3 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `;
    }

    async createRoom() {
        try {
            const name = document.getElementById('roomName').value.trim();
            const description = document.getElementById('roomDescription').value.trim();
            const type = document.getElementById('roomType').value;
            const capacity = parseInt(document.getElementById('roomCapacity').value);
            
            if (!name) {
                this.showError('El nombre es requerido');
                return;
            }
            
            console.log('🏠 Creando sala:', name);
            
            const response = await fetch(`${this.adminServiceUrl}/rooms`, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    name,
                    description,
                    type,
                    capacity,
                    is_active: true
                })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess('Sala creada exitosamente');
                closeModal('createRoomModal');
                await this.loadRooms();
                
                // Limpiar formulario
                document.getElementById('createRoomForm').reset();
            } else {
                throw new Error(result.message || 'Error creando sala');
            }
            
        } catch (error) {
            console.error('❌ Error creando sala:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async editRoom(roomId) {
        // Implementar modal de edición similar al de creación
        console.log('✏️ Editando sala:', roomId);
        this.showNotification('Función de edición próximamente', 'info');
    }

    async toggleRoomStatus(roomId) {
        try {
            console.log('🔄 Cambiando estado de sala:', roomId);
            
            const response = await fetch(`${this.adminServiceUrl}/rooms/${roomId}/toggle`, {
                method: 'PUT',
                headers: this.getAuthHeaders()
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess('Estado de sala actualizado');
                await this.loadRooms();
            } else {
                throw new Error(result.message || 'Error actualizando estado');
            }
            
        } catch (error) {
            console.error('❌ Error actualizando estado:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async deleteRoom(roomId) {
        if (!confirm('¿Estás seguro de eliminar esta sala? Esta acción no se puede deshacer.')) {
            return;
        }
        
        try {
            console.log('🗑️ Eliminando sala:', roomId);
            
            const response = await fetch(`${this.adminServiceUrl}/rooms/${roomId}`, {
                method: 'DELETE',
                headers: this.getAuthHeaders()
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess('Sala eliminada exitosamente');
                await this.loadRooms();
            } else {
                throw new Error(result.message || 'Error eliminando sala');
            }
            
        } catch (error) {
            console.error('❌ Error eliminando sala:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== GESTIÓN DE ASIGNACIONES (RF2) ======
    async loadAssignments() {
        try {
            console.log('👥 Cargando asignaciones...');
            
            const response = await fetch(`${this.adminServiceUrl}/assignments`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                const assignments = result.data?.assignments || result.assignments || [];
                
                this.displayAssignments(assignments);
                return assignments;
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('❌ Error cargando asignaciones:', error);
            this.displayAssignments([]);
            this.showError('Error cargando asignaciones: ' + error.message);
        }
    }

    displayAssignments(assignments) {
        const container = document.getElementById('assignmentsContainer');
        if (!container) return;

        if (assignments.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    <p class="text-gray-500">No hay asignaciones registradas</p>
                    <button onclick="showAssignAgentModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Crear Primera Asignación
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="space-y-4">
                ${assignments.map(assignment => this.createAssignmentRow(assignment)).join('')}
            </div>
        `;
    }

    createAssignmentRow(assignment) {
        const statusClass = assignment.is_active ? 'status-active' : 'status-inactive';
        
        return `
            <div class="assignment-row">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-sm font-semibold text-blue-700">${this.getAgentInitials(assignment.agent_name)}</span>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900">${assignment.agent_name || 'Agente'}</h4>
                            <p class="text-sm text-gray-500">Sala: ${assignment.room_name || assignment.room_id}</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <span class="px-2 py-1 ${statusClass} text-xs font-medium rounded-full">
                            ${assignment.is_active ? 'Activo' : 'Inactivo'}
                        </span>
                        
                        <div class="flex space-x-2">
                            <button onclick="adminClient.editAssignment('${assignment.id}')" 
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Editar
                            </button>
                            <button onclick="adminClient.removeAssignment('${assignment.id}')" 
                                    class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                                Remover
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 text-sm text-gray-600">
                    <div class="flex space-x-4">
                        <span>Horario: ${assignment.schedule_type || '24/7'}</span>
                        <span>Creado: ${this.formatDate(assignment.created_at)}</span>
                    </div>
                </div>
            </div>
        `;
    }

    async loadAvailableAgents() {
        try {
            console.log('👤 Cargando agentes disponibles...');
            
            const response = await fetch(`${this.adminServiceUrl}/assignments/available-agents`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                const agents = result.data?.agents || result.agents || [];
                
                const select = document.getElementById('agentSelect');
                if (select) {
                    select.innerHTML = '<option value="">Seleccionar agente...</option>' +
                        agents.map(agent => `<option value="${agent.id}">${agent.name} (${agent.email})</option>`).join('');
                }
                
                return agents;
            }
            
        } catch (error) {
            console.error('❌ Error cargando agentes:', error);
        }
    }

    async loadRoomsForSelect() {
        try {
            const response = await fetch(`${this.adminServiceUrl}/rooms`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                const rooms = result.data?.rooms || result.rooms || [];
                
                const select = document.getElementById('roomSelect');
                if (select) {
                    select.innerHTML = '<option value="">Seleccionar sala...</option>' +
                        rooms.filter(room => room.is_active).map(room => `<option value="${room.id}">${room.name}</option>`).join('');
                }
                
                return rooms;
            }
            
        } catch (error) {
            console.error('❌ Error cargando salas para select:', error);
        }
    }

    async assignAgent() {
        try {
            const agentId = document.getElementById('agentSelect').value;
            const roomId = document.getElementById('roomSelect').value;
            const scheduleType = document.getElementById('scheduleType').value;
            
            if (!agentId || !roomId) {
                this.showError('Selecciona agente y sala');
                return;
            }
            
            console.log('👥 Asignando agente:', agentId, 'a sala:', roomId);
            
            const response = await fetch(`${this.adminServiceUrl}/assignments`, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    agent_id: agentId,
                    room_id: roomId,
                    schedule_type: scheduleType,
                    is_active: true
                })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess('Agente asignado exitosamente');
                closeModal('assignAgentModal');
                await this.loadAssignments();
                
                // Limpiar formulario
                document.getElementById('assignAgentForm').reset();
            } else {
                throw new Error(result.message || 'Error asignando agente');
            }
            
        } catch (error) {
            console.error('❌ Error asignando agente:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async editAssignment(assignmentId) {
        console.log('✏️ Editando asignación:', assignmentId);
        this.showNotification('Función de edición próximamente', 'info');
    }

    async removeAssignment(assignmentId) {
        if (!confirm('¿Remover esta asignación?')) {
            return;
        }
        
        try {
            console.log('🗑️ Removiendo asignación:', assignmentId);
            
            const response = await fetch(`${this.adminServiceUrl}/assignments/${assignmentId}`, {
                method: 'DELETE',
                headers: this.getAuthHeaders()
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess('Asignación removida');
                await this.loadAssignments();
            } else {
                throw new Error(result.message || 'Error removiendo asignación');
            }
            
        } catch (error) {
            console.error('❌ Error removiendo asignación:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== REPORTES (RF8) ======
    async loadReports() {
        try {
            console.log('📊 Cargando reportes...');
            
            // Cargar estadísticas de chat
            this.loadChatStats();
            
            // Cargar estadísticas de agentes
            this.loadAgentStats();
            
        } catch (error) {
            console.error('❌ Error cargando reportes:', error);
            this.showError('Error cargando reportes: ' + error.message);
        }
    }

    async loadChatStats() {
        try {
            const response = await fetch(`${this.adminServiceUrl}/reports/statistics`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                const stats = result.data || result;
                
                const container = document.getElementById('chatStatsContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Total Mensajes:</span>
                                <span class="font-semibold">${stats.total_messages || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Sesiones Completadas:</span>
                                <span class="font-semibold">${stats.completed_sessions || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Tiempo Promedio:</span>
                                <span class="font-semibold">${stats.average_session_time || '0 min'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Satisfacción:</span>
                                <span class="font-semibold">${stats.satisfaction_rate || '0%'}</span>
                            </div>
                        </div>
                    `;
                }
            }
            
        } catch (error) {
            console.error('❌ Error cargando estadísticas de chat:', error);
        }
    }

    async loadAgentStats() {
        try {
            const response = await fetch(`${this.adminServiceUrl}/reports/agents`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                const stats = result.data || result;
                
                const container = document.getElementById('agentStatsContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Agentes Activos:</span>
                                <span class="font-semibold">${stats.active_agents || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Promedio Respuesta:</span>
                                <span class="font-semibold">${stats.average_response_time || '0s'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Transferencias:</span>
                                <span class="font-semibold">${stats.total_transfers || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Escalaciones:</span>
                                <span class="font-semibold">${stats.total_escalations || 0}</span>
                            </div>
                        </div>
                    `;
                }
            }
            
        } catch (error) {
            console.error('❌ Error cargando estadísticas de agentes:', error);
        }
    }

    async refreshReports() {
        this.showNotification('Actualizando reportes...', 'info');
        await this.loadReports();
        this.showNotification('Reportes actualizados', 'success');
    }

    // ====== CONFIGURACIÓN ======
    async loadConfig() {
        try {
            console.log('⚙️ Cargando configuración...');
            
            const response = await fetch(`${this.adminServiceUrl}/config`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            if (response.ok) {
                const result = await response.json();
                const config = result.data || result;
                
                this.displayConfig(config);
                return config;
            } else {
                throw new Error(`Error HTTP ${response.status}`);
            }
            
        } catch (error) {
            console.error('❌ Error cargando configuración:', error);
            this.displayConfig({});
            this.showError('Error cargando configuración: ' + error.message);
        }
    }

    displayConfig(config) {
        const container = document.getElementById('configContainer');
        if (!container) return;

        container.innerHTML = `
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-4">Configuración General</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Timeout de Sesión (minutos)</label>
                                <input type="number" id="sessionTimeout" value="${config.session_timeout || 30}" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Máximo de Transferencias</label>
                                <input type="number" id="maxTransfers" value="${config.max_transfers || 3}" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tiempo de Respuesta (segundos)</label>
                                <input type="number" id="responseTime" value="${config.max_response_time || 300}" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-4">Configuración de Notificaciones</h4>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input type="checkbox" id="emailNotifications" ${config.email_notifications ? 'checked' : ''}
                                       class="mr-2 h-4 w-4 text-red-600 border-gray-300 rounded">
                                <label class="text-sm text-gray-700">Notificaciones por email</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="smsNotifications" ${config.sms_notifications ? 'checked' : ''}
                                       class="mr-2 h-4 w-4 text-red-600 border-gray-300 rounded">
                                <label class="text-sm text-gray-700">Notificaciones por SMS</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="autoEscalation" ${config.auto_escalation ? 'checked' : ''}
                                       class="mr-2 h-4 w-4 text-red-600 border-gray-300 rounded">
                                <label class="text-sm text-gray-700">Escalación automática</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-900 mb-2">Información del Sistema</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Versión:</span>
                            <span class="font-medium">${config.system_version || '2.0.0'}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Base de Datos:</span>
                            <span class="font-medium">${config.database_status || 'Conectada'}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Última Actualización:</span>
                            <span class="font-medium">${this.formatDate(config.last_update)}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Uptime:</span>
                            <span class="font-medium">${config.uptime || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async saveConfig() {
        try {
            const configData = {
                session_timeout: parseInt(document.getElementById('sessionTimeout')?.value) || 30,
                max_transfers: parseInt(document.getElementById('maxTransfers')?.value) || 3,
                max_response_time: parseInt(document.getElementById('responseTime')?.value) || 300,
                email_notifications: document.getElementById('emailNotifications')?.checked || false,
                sms_notifications: document.getElementById('smsNotifications')?.checked || false,
                auto_escalation: document.getElementById('autoEscalation')?.checked || false
            };
            
            console.log('💾 Guardando configuración:', configData);
            
            const response = await fetch(`${this.adminServiceUrl}/config`, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify(configData)
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess('Configuración guardada exitosamente');
            } else {
                throw new Error(result.message || 'Error guardando configuración');
            }
            
        } catch (error) {
            console.error('❌ Error guardando configuración:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== UTILIDADES ======
    getRoomStatusClass(status) {
        const classes = {
            'active': 'bg-green-100 text-green-800',
            'inactive': 'bg-red-100 text-red-800',
            'maintenance': 'bg-yellow-100 text-yellow-800'
        };
        return classes[status] || 'bg-gray-100 text-gray-800';
    }

    getRoomStatusText(status) {
        const texts = {
            'active': 'Activa',
            'inactive': 'Inactiva',
            'maintenance': 'Mantenimiento'
        };
        return texts[status] || 'Desconocido';
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

    getAgentInitials(name) {
        if (!name) return 'A';
        return name.split(' ').map(part => part.charAt(0)).join('').substring(0, 2).toUpperCase();
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (error) {
            return 'N/A';
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

    startAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        this.refreshInterval = setInterval(async () => {
            try {
                // Solo refrescar la sección activa
                const activeSection = document.querySelector('.section-content:not(.hidden)');
                if (activeSection) {
                    const sectionId = activeSection.id;
                    if (sectionId === 'dashboard-section') {
                        await this.loadDashboard();
                    }
                }
                console.log('🔄 Auto-refresh completado');
            } catch (error) {
                console.warn('⚠️ Error en auto-refresh:', error);
            }
        }, this.refreshIntervalTime);
        
        console.log(`🔄 Auto-refresh activado cada ${this.refreshIntervalTime/1000}s`);
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
            console.log('🔄 Auto-refresh desactivado');
        }
    }

    async init() {
        try {
            console.log('🚀 Inicializando AdminClient...');
            
            // Cargar dashboard inicial
            await this.loadDashboard();
            
            // Iniciar auto-refresh
            this.startAutoRefresh();
            
            console.log('✅ AdminClient inicializado exitosamente');
            
        } catch (error) {
            console.error('❌ Error inicializando AdminClient:', error);
            this.showError('Error de inicialización');
        }
    }

    destroy() {
        this.stopAutoRefresh();
        console.log('🧹 AdminClient destruido');
    }
}

// Crear instancia global
window.adminClient = new AdminClient();

// Debug helpers
window.debugAdmin = {
    getToken: () => window.adminClient.getToken(),
    getUser: () => window.adminClient.getCurrentUser(),
    loadDashboard: () => window.adminClient.loadDashboard(),
    loadRooms: () => window.adminClient.loadRooms(),
    testNotification: () => window.adminClient.showNotification('Test notification', 'success')
};

console.log('🔧 AdminClient v1.0 cargado');