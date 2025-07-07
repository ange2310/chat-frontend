class SupervisorClient {
    constructor() {
        this.supervisorServiceUrl = 'http://localhost:3014/supervisor';
        this.currentTransfer = null;
        this.refreshInterval = null;
        this.refreshIntervalTime = 30000; // 30 segundos
        
        console.log('🔧 SupervisorClient inicializado');
    }

    getToken() {
        const phpTokenMeta = document.querySelector('meta[name="supervisor-token"]')?.content;
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
            'Authorization': `Bearer ${token}`,
            'X-Supervisor-ID': this.getCurrentUser()?.id || 'supervisor'
        };
    }

    getCurrentUser() {
        const userMeta = document.querySelector('meta[name="supervisor-user"]');
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
            
            const response = await fetch(`${this.supervisorServiceUrl}/dashboard`, {
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
            'stat-pending-transfers': data.pending_transfers || 0,
            'stat-active-escalations': data.active_escalations || 0,
            'stat-active-agents': data.active_agents || 0,
            'stat-unread-notifications': data.unread_notifications || 0
        };

        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        });

        // Actualizar contadores en nav
        this.updateNavCounters(data);
        
        console.log('📊 Dashboard actualizado:', updates);
    }

    updateNavCounters(data) {
        const transfers = data.pending_transfers || 0;
        const escalations = data.active_escalations || 0;
        const notifications = data.unread_notifications || 0;

        this.updateNavCounter('transfersCount', transfers);
        this.updateNavCounter('escalationsCount', escalations);
        this.updateNavCounter('notificationsCount', notifications);
    }

    updateNavCounter(elementId, count) {
        const element = document.getElementById(elementId);
        if (element) {
            if (count > 0) {
                element.textContent = count;
                element.classList.remove('hidden');
            } else {
                element.classList.add('hidden');
            }
        }
    }

    async refreshDashboard() {
        this.showNotification('Actualizando métricas...', 'info');
        await this.loadDashboard();
        this.showNotification('Métricas actualizadas', 'success');
    }

    // ====== TRANSFERENCIAS (RF4) ======
    async loadTransfers() {
        try {
            console.log('🔄 Cargando transferencias pendientes...');
            
            // Nota: Las transferencias llegan via POST /transfers/pending
            // pero necesitamos un endpoint para listar las pendientes
            const response = await fetch(`${this.supervisorServiceUrl}/transfers/pending`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            let transfers = [];
            
            if (response.ok) {
                const result = await response.json();
                transfers = result.data?.transfers || result.transfers || [];
            } else {
                console.warn('No se pudieron cargar transferencias:', response.status);
            }
            
            this.displayTransfers(transfers);
            return transfers;
            
        } catch (error) {
            console.error('❌ Error cargando transferencias:', error);
            this.displayTransfers([]);
        }
    }

    displayTransfers(transfers) {
        const container = document.getElementById('transfersContainer');
        if (!container) return;

        if (transfers.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"></path>
                    </svg>
                    <p class="text-gray-500">No hay transferencias pendientes</p>
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
        const priorityClass = `priority-${priority}`;
        const timeAgo = this.getTimeAgo(transfer.created_at || transfer.timestamp);
        
        return `
            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all ${priorityClass}">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-4 mb-2">
                            <h4 class="font-semibold text-gray-900">Transferencia #${transfer.id}</h4>
                            <span class="px-2 py-1 bg-${priority === 'high' ? 'red' : priority === 'medium' ? 'yellow' : 'green'}-100 
                                         text-${priority === 'high' ? 'red' : priority === 'medium' ? 'yellow' : 'green'}-800 
                                         text-xs font-medium rounded-full capitalize">${priority}</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <span class="font-medium">Desde:</span> ${transfer.from_room || 'N/A'}
                            </div>
                            <div>
                                <span class="font-medium">Hacia:</span> ${transfer.to_room || 'N/A'}
                            </div>
                            <div>
                                <span class="font-medium">Agente:</span> ${transfer.from_agent_id || 'N/A'}
                            </div>
                            <div>
                                <span class="font-medium">Hace:</span> ${timeAgo}
                            </div>
                        </div>
                        
                        ${transfer.reason ? `
                            <div class="mt-2">
                                <span class="font-medium text-sm text-gray-600">Motivo:</span>
                                <p class="text-sm text-gray-700">${transfer.reason}</p>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="flex flex-col gap-2">
                        <button onclick="supervisorClient.viewTransferDetails('${transfer.id}')" 
                                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                            Ver Detalles
                        </button>
                        <button onclick="supervisorClient.quickApproveTransfer('${transfer.id}')" 
                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                            Aprobar
                        </button>
                        <button onclick="supervisorClient.quickRejectTransfer('${transfer.id}')" 
                                class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                            Rechazar
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    async viewTransferDetails(transferId) {
        try {
            console.log('🔍 Viendo detalles de transferencia:', transferId);
            
            // Buscar en las transferencias cargadas o hacer request específico
            this.currentTransfer = { id: transferId };
            
            const modalContent = document.getElementById('transferDetails');
            modalContent.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ID de Transferencia</label>
                            <p class="text-sm text-gray-900">${transferId}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Estado</label>
                            <p class="text-sm text-gray-900">Pendiente</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Motivo del Rechazo (Opcional)</label>
                        <textarea id="rejectionReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg" 
                                  rows="3" placeholder="Especifica el motivo si vas a rechazar..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Agente Asignado (Para Aprobación)</label>
                        <input type="text" id="assignedAgentId" class="w-full px-3 py-2 border border-gray-300 rounded-lg" 
                               placeholder="ID del agente que tomará la sesión">
                    </div>
                </div>
            `;
            
            document.getElementById('transferModal').classList.remove('hidden');
            
        } catch (error) {
            console.error('❌ Error viendo detalles:', error);
            this.showError('Error cargando detalles');
        }
    }

    async quickApproveTransfer(transferId) {
        const agentId = prompt('Ingresa el ID del agente que tomará la sesión:');
        if (!agentId) return;
        
        await this.approveTransferWithAgent(transferId, agentId);
    }

    async quickRejectTransfer(transferId) {
        const reason = prompt('Motivo del rechazo:');
        if (!reason) return;
        
        await this.rejectTransferWithReason(transferId, reason);
    }

    async approveTransfer() {
        if (!this.currentTransfer) return;
        
        const agentId = document.getElementById('assignedAgentId')?.value.trim();
        if (!agentId) {
            this.showError('Ingresa el ID del agente');
            return;
        }
        
        await this.approveTransferWithAgent(this.currentTransfer.id, agentId);
        document.getElementById('transferModal').classList.add('hidden');
    }

    async rejectTransfer() {
        if (!this.currentTransfer) return;
        
        const reason = document.getElementById('rejectionReason')?.value.trim() || 'Rechazado por supervisor';
        await this.rejectTransferWithReason(this.currentTransfer.id, reason);
        document.getElementById('transferModal').classList.add('hidden');
    }

    async approveTransferWithAgent(transferId, agentId) {
        try {
            console.log('✅ Aprobando transferencia:', transferId, 'para agente:', agentId);
            
            const response = await fetch(`${this.supervisorServiceUrl}/transfers/${transferId}/approve`, {
                method: 'PUT',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    assigned_agent_id: agentId,
                    supervisor_id: this.getCurrentUser()?.id,
                    approved_at: new Date().toISOString()
                })
            });
            
            if (response.ok) {
                this.showSuccess('Transferencia aprobada exitosamente');
                await this.loadTransfers();
                await this.loadDashboard();
            } else {
                const result = await response.json();
                throw new Error(result.message || 'Error aprobando transferencia');
            }
            
        } catch (error) {
            console.error('❌ Error aprobando transferencia:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async rejectTransferWithReason(transferId, reason) {
        try {
            console.log('❌ Rechazando transferencia:', transferId, 'motivo:', reason);
            
            const response = await fetch(`${this.supervisorServiceUrl}/transfers/${transferId}/reject`, {
                method: 'PUT',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    reason: reason,
                    supervisor_id: this.getCurrentUser()?.id,
                    rejected_at: new Date().toISOString()
                })
            });
            
            if (response.ok) {
                this.showSuccess('Transferencia rechazada');
                await this.loadTransfers();
                await this.loadDashboard();
            } else {
                const result = await response.json();
                throw new Error(result.message || 'Error rechazando transferencia');
            }
            
        } catch (error) {
            console.error('❌ Error rechazando transferencia:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== ESCALACIONES (RF5) ======
    async loadEscalations() {
        try {
            console.log('⚠️ Cargando escalaciones...');
            
            // Las escalaciones llegan via POST /escalations/auto
            // pero necesitamos un endpoint para listar las activas
            const response = await fetch(`${this.supervisorServiceUrl}/escalations`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            let escalations = [];
            
            if (response.ok) {
                const result = await response.json();
                escalations = result.data?.escalations || result.escalations || [];
            } else {
                console.warn('No se pudieron cargar escalaciones:', response.status);
            }
            
            this.displayEscalations(escalations);
            return escalations;
            
        } catch (error) {
            console.error('❌ Error cargando escalaciones:', error);
            this.displayEscalations([]);
        }
    }

    displayEscalations(escalations) {
        const container = document.getElementById('escalationsContainer');
        if (!container) return;

        if (escalations.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-gray-500">No hay escalaciones activas</p>
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
        const isUrgent = escalation.failed_transfer_count >= 3;
        
        return `
            <div class="border border-orange-200 rounded-lg p-4 hover:shadow-md transition-all ${isUrgent ? 'pulse-red' : ''}">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-4 mb-2">
                            <h4 class="font-semibold text-gray-900">Escalación #${escalation.session_id}</h4>
                            <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs font-medium rounded-full">
                                ${escalation.failed_transfer_count} Intentos Fallidos
                            </span>
                            ${isUrgent ? '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">URGENTE</span>' : ''}
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                            <div>
                                <span class="font-medium">Sesión:</span> ${escalation.session_id}
                            </div>
                            <div>
                                <span class="font-medium">Prioridad:</span> ${escalation.priority || 'Alta'}
                            </div>
                            <div>
                                <span class="font-medium">Hace:</span> ${timeAgo}
                            </div>
                            <div>
                                <span class="font-medium">Estado:</span> ${escalation.status || 'Pendiente'}
                            </div>
                        </div>
                        
                        ${escalation.reason ? `
                            <div class="bg-orange-50 border border-orange-200 rounded p-2">
                                <span class="font-medium text-sm text-orange-800">Motivo:</span>
                                <p class="text-sm text-orange-700">${escalation.reason}</p>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="flex flex-col gap-2">
                        <button onclick="supervisorClient.takeEscalation('${escalation.session_id}')" 
                                class="px-3 py-1 bg-orange-600 text-white text-sm rounded hover:bg-orange-700">
                            Tomar Control
                        </button>
                        <button onclick="supervisorClient.assignEscalation('${escalation.session_id}')" 
                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                            Asignar Agente
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    async takeEscalation(sessionId) {
        try {
            console.log('👤 Tomando control de escalación:', sessionId);
            
            const response = await fetch(`${this.supervisorServiceUrl}/escalations/${sessionId}/take`, {
                method: 'PUT',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    supervisor_id: this.getCurrentUser()?.id
                })
            });
            
            if (response.ok) {
                this.showSuccess('Escalación tomada exitosamente');
                await this.loadEscalations();
                await this.loadDashboard();
            } else {
                const result = await response.json();
                throw new Error(result.message || 'Error tomando escalación');
            }
            
        } catch (error) {
            console.error('❌ Error tomando escalación:', error);
            this.showError('Error: ' + error.message);
        }
    }

    async assignEscalation(sessionId) {
        const agentId = prompt('Ingresa el ID del agente para asignar esta escalación:');
        if (!agentId) return;
        
        try {
            console.log('👤 Asignando escalación:', sessionId, 'a agente:', agentId);
            
            const response = await fetch(`${this.supervisorServiceUrl}/escalations/${sessionId}/assign`, {
                method: 'PUT',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    agent_id: agentId,
                    supervisor_id: this.getCurrentUser()?.id
                })
            });
            
            if (response.ok) {
                this.showSuccess('Escalación asignada exitosamente');
                await this.loadEscalations();
                await this.loadDashboard();
            } else {
                const result = await response.json();
                throw new Error(result.message || 'Error asignando escalación');
            }
            
        } catch (error) {
            console.error('❌ Error asignando escalación:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== ANÁLISIS RF6 ======
    async runMisdirectionAnalysis() {
        try {
            console.log('🔍 Ejecutando análisis de mal direccionamiento (RF6)...');
            
            const container = document.getElementById('analysisContainer');
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mx-auto mb-4"></div>
                    <p class="text-gray-600">Analizando patrones de transferencia...</p>
                </div>
            `;
            
            const response = await fetch(`${this.supervisorServiceUrl}/misdirection/analyze`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });
            
            if (response.ok) {
                const result = await response.json();
                
                if (result.success && result.data) {
                    this.displayAnalysisResults(result.data);
                } else {
                    throw new Error('No se recibieron resultados del análisis');
                }
            } else {
                const result = await response.json();
                throw new Error(result.message || 'Error en análisis');
            }
            
        } catch (error) {
            console.error('❌ Error en análisis RF6:', error);
            
            const container = document.getElementById('analysisContainer');
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-red-600 font-medium">Error en análisis</p>
                    <p class="text-gray-500 text-sm">${error.message}</p>
                    <button onclick="supervisorClient.runMisdirectionAnalysis()" 
                            class="mt-4 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Reintentar
                    </button>
                </div>
            `;
        }
    }

    displayAnalysisResults(analysisData) {
        const container = document.getElementById('analysisContainer');
        
        const misdirectedSessions = analysisData.misdirected_sessions || [];
        const statistics = analysisData.statistics || {};
        
        container.innerHTML = `
            <div class="space-y-6">
                <!-- Estadísticas del Análisis -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-900">Sesiones Analizadas</h4>
                        <p class="text-2xl font-bold text-blue-700">${statistics.total_sessions || 0}</p>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <h4 class="font-semibold text-red-900">Mal Dirigidas</h4>
                        <p class="text-2xl font-bold text-red-700">${misdirectedSessions.length}</p>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h4 class="font-semibold text-green-900">Tasa de Precisión</h4>
                        <p class="text-2xl font-bold text-green-700">${statistics.accuracy_rate || '0%'}</p>
                    </div>
                </div>
                
                <!-- Sesiones Mal Dirigidas -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sesiones Mal Dirigidas Detectadas</h3>
                    
                    ${misdirectedSessions.length === 0 ? `
                        <div class="text-center py-8 bg-green-50 border border-green-200 rounded-lg">
                            <svg class="w-12 h-12 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-green-700 font-medium">¡Excelente!</p>
                            <p class="text-green-600">No se detectaron sesiones mal dirigidas</p>
                        </div>
                    ` : `
                        <div class="space-y-3">
                            ${misdirectedSessions.map(session => this.createMisdirectionCard(session)).join('')}
                        </div>
                    `}
                </div>
                
                <!-- Acciones Recomendadas -->
                ${analysisData.recommendations ? `
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <h4 class="font-semibold text-yellow-900 mb-2">Recomendaciones</h4>
                        <ul class="text-sm text-yellow-800 space-y-1">
                            ${analysisData.recommendations.map(rec => `<li>• ${rec}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
            </div>
        `;
    }

    createMisdirectionCard(session) {
        return `
            <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-4 mb-2">
                            <h4 class="font-semibold text-red-900">Sesión #${session.session_id}</h4>
                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">
                                ${session.jump_count} Saltos
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm text-red-800">
                            <div>
                                <span class="font-medium">Sala Actual:</span> ${session.current_room}
                            </div>
                            <div>
                                <span class="font-medium">Sala Sugerida:</span> ${session.suggested_room}
                            </div>
                            <div>
                                <span class="font-medium">Confianza:</span> ${session.confidence || 'N/A'}
                            </div>
                            <div>
                                <span class="font-medium">Agente:</span> ${session.current_agent || 'N/A'}
                            </div>
                        </div>
                        
                        ${session.reason ? `
                            <div class="mt-2">
                                <span class="font-medium text-sm text-red-800">Motivo:</span>
                                <p class="text-sm text-red-700">${session.reason}</p>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="flex flex-col gap-2">
                        <button onclick="supervisorClient.suggestRedirection('${session.session_id}', '${session.suggested_room}')" 
                                class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                            Sugerir Reenvío
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    async suggestRedirection(sessionId, suggestedRoom) {
        try {
            console.log('🔄 Sugiriendo reenvío para sesión:', sessionId, 'a sala:', suggestedRoom);
            
            const response = await fetch(`${this.supervisorServiceUrl}/misdirection/${sessionId}/suggest`, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    suggested_room: suggestedRoom,
                    supervisor_id: this.getCurrentUser()?.id
                })
            });
            
            if (response.ok) {
                this.showSuccess('Sugerencia de reenvío enviada');
            } else {
                const result = await response.json();
                throw new Error(result.message || 'Error enviando sugerencia');
            }
            
        } catch (error) {
            console.error('❌ Error sugiriendo reenvío:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ====== NOTIFICACIONES ======
    async loadNotifications() {
        try {
            console.log('🔔 Cargando notificaciones...');
            
            const response = await fetch(`${this.supervisorServiceUrl}/notifications`, {
                method: 'GET',
                headers: this.getAuthHeaders()
            });

            let notifications = [];
            
            if (response.ok) {
                const result = await response.json();
                notifications = result.data?.notifications || result.notifications || [];
            } else {
                console.warn('No se pudieron cargar notificaciones:', response.status);
            }
            
            this.displayNotifications(notifications);
            return notifications;
            
        } catch (error) {
            console.error('❌ Error cargando notificaciones:', error);
            this.displayNotifications([]);
        }
    }

    displayNotifications(notifications) {
        const container = document.getElementById('notificationsContainer');
        if (!container) return;

        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5z"></path>
                    </svg>
                    <p class="text-gray-500">No hay notificaciones</p>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="space-y-3">
                ${notifications.map(notification => this.createNotificationCard(notification)).join('')}
            </div>
        `;
    }

    createNotificationCard(notification) {
        const isUnread = !notification.read_at;
        const cardClass = isUnread ? 'notification-unread' : 'notification-read';
        const timeAgo = this.getTimeAgo(notification.created_at || notification.timestamp);
        
        return `
            <div class="border rounded-lg p-4 ${cardClass}">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-semibold text-gray-900">${notification.title || 'Notificación'}</h4>
                            ${isUnread ? '<span class="w-2 h-2 bg-blue-500 rounded-full"></span>' : ''}
                        </div>
                        
                        <p class="text-sm text-gray-700 mb-2">${notification.message || notification.content}</p>
                        
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>${notification.type || 'Info'}</span>
                            <span>${timeAgo}</span>
                        </div>
                    </div>
                    
                    ${isUnread ? `
                        <button onclick="supervisorClient.markNotificationRead('${notification.id}')" 
                                class="ml-4 px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                            Marcar Leído
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    async markNotificationRead(notificationId) {
        try {
            console.log('📖 Marcando notificación como leída:', notificationId);
            
            const response = await fetch(`${this.supervisorServiceUrl}/notifications/${notificationId}/read`, {
                method: 'PUT',
                headers: this.getAuthHeaders()
            });
            
            if (response.ok) {
                await this.loadNotifications();
                await this.loadDashboard();
            } else {
                console.warn('Error marcando notificación:', response.status);
            }
            
        } catch (error) {
            console.error('❌ Error marcando notificación:', error);
        }
    }

    async markAllNotificationsRead() {
        try {
            console.log('📖 Marcando todas las notificaciones como leídas...');
            
            const response = await fetch(`${this.supervisorServiceUrl}/notifications/mark-all-read`, {
                method: 'PUT',
                headers: this.getAuthHeaders()
            });
            
            if (response.ok) {
                this.showSuccess('Todas las notificaciones marcadas como leídas');
                await this.loadNotifications();
                await this.loadDashboard();
            } else {
                console.warn('Error marcando todas las notificaciones:', response.status);
            }
            
        } catch (error) {
            console.error('❌ Error marcando todas las notificaciones:', error);
            this.showError('Error marcando notificaciones');
        }
    }

    // ====== UTILIDADES ======
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
                await this.loadDashboard();
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
            console.log('🚀 Inicializando SupervisorClient...');
            
            // Cargar dashboard inicial
            await this.loadDashboard();
            
            // Iniciar auto-refresh
            this.startAutoRefresh();
            
            console.log('✅ SupervisorClient inicializado exitosamente');
            
        } catch (error) {
            console.error('❌ Error inicializando SupervisorClient:', error);
            this.showError('Error de inicialización');
        }
    }

    destroy() {
        this.stopAutoRefresh();
        console.log('🧹 SupervisorClient destruido');
    }
}

// Crear instancia global
window.supervisorClient = new SupervisorClient();

// Debug helpers
window.debugSupervisor = {
    getToken: () => window.supervisorClient.getToken(),
    getUser: () => window.supervisorClient.getCurrentUser(),
    loadDashboard: () => window.supervisorClient.loadDashboard(),
    runAnalysis: () => window.supervisorClient.runMisdirectionAnalysis(),
    testNotification: () => window.supervisorClient.showNotification('Test notification', 'success')
};

console.log('🔧 SupervisorClient v1.0 cargado');