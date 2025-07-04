<?php
// public/staff.php - SIMPLIFICADO
session_start();

// Verificación básica
if (!isset($_SESSION['pToken']) || empty($_SESSION['pToken'])) {
    header("Location: /practicas/chat-frontend/public/index.php?error=no_session");
    exit;
}

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: /practicas/chat-frontend/public/index.php?error=no_user");
    exit;
}

$user = json_decode($_SESSION['user'], true);
if (!$user) {
    header("Location: /practicas/chat-frontend/public/index.php?error=invalid_user");
    exit;
}

$userRole = $user['role']['name'] ?? $user['role'] ?? 'agent';
if (is_numeric($userRole)) {
    $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
    $userRole = $roleMap[$userRole] ?? 'agent';
}

$validStaffRoles = ['agent', 'supervisor', 'admin'];
if (!in_array($userRole, $validStaffRoles)) {
    header("Location: /practicas/chat-frontend/public/index.php?error=not_staff");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Agente - <?= htmlspecialchars($user['name'] ?? 'Staff') ?></title>
    
    <meta name="staff-token" content="<?= $_SESSION['pToken'] ?>">
    <meta name="staff-user" content='<?= json_encode($user) ?>'>
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nav-link.active { background: #2563eb; color: white; }
        .countdown-urgent { animation: pulse-red 1s infinite; }
        @keyframes pulse-red { 0%, 100% { color: #dc2626; } 50% { color: #ef4444; } }
        .patient-sidebar { width: 320px; min-width: 320px; }
        .chat-main { flex: 1; min-width: 0; }
    </style>
</head>
<body class="h-full bg-gray-50">
    <div class="min-h-full flex">
        
        <!-- Sidebar -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Panel de Agente</h1>
                        <p class="text-sm text-gray-500">Sistema Médico</p>
                    </div>
                </div>
            </div>
            
            <nav class="flex-1 p-4">
                <div class="space-y-1">
                    <a href="#rooms" onclick="showRoomsSection()" 
                       class="nav-link active flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                        Salas de Atención
                    </a>
                </div>
            </nav>
                
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></p>
                        <p class="text-sm text-gray-500 capitalize"><?= htmlspecialchars($userRole) ?></p>
                    </div>
                    <button onclick="logout()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg" title="Cerrar sesión">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="flex-1 flex flex-col">
            
            <!-- Header -->
            <header class="bg-white border-b border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h2 id="sectionTitle" class="text-xl font-semibold text-gray-900">Salas de Atención</h2>
                        <div class="flex items-center gap-4">
                            <span id="currentTime" class="text-sm text-gray-500"></span>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span class="text-sm text-gray-600">En línea</span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-auto">
                
                <!-- LISTA DE SALAS -->
                <div id="rooms-list-section" class="section-content p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Salas Disponibles</h3>
                                    <p class="text-sm text-gray-600 mt-1">Selecciona una sala para ver las sesiones</p>
                                </div>
                                <button onclick="staffClient.loadRoomsFromAuthService()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="roomsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando salas...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SESIONES DE UNA SALA -->
                <div id="room-sessions-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <button onclick="staffClient.goBackToRooms()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                        </svg>
                                    </button>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">
                                            Sesiones en: <span id="currentRoomName">Sala</span>
                                        </h3>
                                        <p class="text-sm text-gray-600">Pacientes esperando atención</p>
                                    </div>
                                </div>
                                <button onclick="staffClient.loadSessionsByRoom(staffClient.currentRoom)" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="sessionsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando sesiones...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CHAT CON PACIENTE -->
                <div id="patient-chat-panel" class="section-content hidden">
                    <div class="h-full flex">
                        
                        <!-- Chat Principal -->
                        <div class="chat-main flex flex-col bg-white">
                            
                            <!-- Header del Chat -->
                            <div class="bg-white border-b border-gray-200 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <button onclick="staffClient.closeChat()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                            </svg>
                                        </button>
                                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                            <span id="chatPatientInitials" class="text-lg font-semibold text-green-700">P</span>
                                        </div>
                                        <div>
                                            <h2 id="chatPatientName" class="text-xl font-bold text-gray-900">Paciente</h2>
                                            <p class="text-sm text-gray-500">
                                                <span id="chatRoomName">Sala</span> • 
                                                <span id="chatSessionStatus" class="text-green-600">Activo</span>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Botones de Acción -->
                                    <div class="flex items-center gap-2">
                                        <button onclick="showTransferModal()" 
                                                class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                            Transferir
                                        </button>
                                        <button onclick="showReturnModal()" 
                                                class="px-3 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 text-sm">
                                            Devolver
                                        </button>
                                        <button onclick="showEndSessionModal()" 
                                                class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                            Finalizar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Mensajes del Chat -->
                            <div class="flex-1 overflow-y-auto p-6 bg-gray-50" id="patientChatMessages">
                                <!-- Los mensajes aparecerán aquí -->
                            </div>

                            <!-- Input del Chat -->
                            <div class="bg-white border-t border-gray-200 p-4">
                                <div class="flex items-end gap-3">
                                    <div class="flex-1">
                                        <textarea 
                                            id="agentMessageInput" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                            rows="2"
                                            placeholder="Escribe tu respuesta..."
                                            maxlength="500"
                                            onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); staffClient.sendMessage();}"
                                            oninput="staffClient.updateSendButton()"
                                        ></textarea>
                                    </div>
                                    <button 
                                        id="agentSendButton"
                                        onclick="staffClient.sendMessage()" 
                                        disabled
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="flex justify-between items-center mt-2 text-xs text-gray-500">
                                    <span>Enter para enviar, Shift+Enter para nueva línea</span>
                                    <span id="chatStatus">Desconectado</span>
                                </div>
                            </div>
                        </div>

                        <!-- Información del Paciente -->
                        <div class="patient-sidebar bg-gray-50 border-l border-gray-200 overflow-y-auto">
                            <div class="p-6">
                                
                                <!-- Información Personal -->
                                <div class="mb-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Información del Paciente</h3>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Nombre</label>
                                            <p id="patientInfoName" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Documento</label>
                                            <p id="patientInfoDocument" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Teléfono</label>
                                            <p id="patientInfoPhone" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Email</label>
                                            <p id="patientInfoEmail" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Ciudad</label>
                                            <p id="patientInfoCity" class="text-sm text-gray-900">-</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información de Membresía -->
                                <div class="mb-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Membresía</h3>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">EPS</label>
                                            <p id="patientInfoEPS" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Plan</label>
                                            <p id="patientInfoPlan" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Estado</label>
                                            <p id="patientInfoStatus" class="text-sm text-gray-900">-</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- ID de Sesión -->
                                <div class="bg-gray-100 rounded-lg p-3">
                                    <div class="text-xs text-gray-500 mb-1">ID de Sesión</div>
                                    <div id="chatPatientId" class="text-xs font-mono text-gray-700">-</div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Transferencia -->
    <div id="transferModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Transferir Sesión</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                    <select id="transferType" class="w-full px-3 py-2 border border-gray-300 rounded-lg" onchange="toggleTransferFields()">
                        <option value="internal">Transferencia Interna</option>
                        <option value="external">Transferencia Externa</option>
                    </select>
                </div>
                
                <div id="internalTransferFields">
                    <label class="block text-sm font-medium text-gray-700 mb-2">ID del Agente</label>
                    <input type="text" id="targetAgentId" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="agent_123">
                </div>
                
                <div id="externalTransferFields" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sala Destino</label>
                    <select id="targetRoom" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="general">Consultas Generales</option>
                        <option value="medical">Consultas Médicas</option>
                        <option value="support">Soporte Técnico</option>
                        <option value="emergency">Emergencias</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo</label>
                    <textarea id="transferReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg" rows="3" placeholder="Motivo de la transferencia..." required></textarea>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('transferModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Cancelar
                </button>
                <button onclick="executeTransfer()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Transferir
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Finalizar Sesión -->
    <div id="endSessionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Finalizar Sesión</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo</label>
                    <select id="endReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="completed_by_agent">Consulta completada</option>
                        <option value="patient_resolved">Problema resuelto</option>
                        <option value="patient_disconnected">Paciente desconectado</option>
                        <option value="technical_issues">Problemas técnicos</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notas (opcional)</label>
                    <textarea id="endNotes" class="w-full px-3 py-2 border border-gray-300 rounded-lg" rows="3" placeholder="Resumen de la consulta..."></textarea>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('endSessionModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg">
                    Cancelar
                </button>
                <button onclick="executeEndSession()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Finalizar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Devolver -->
    <div id="returnModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Devolver a Cola</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo</label>
                    <select id="returnReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="need_specialist">Necesita especialista</option>
                        <option value="technical_issues">Problemas técnicos</option>
                        <option value="patient_unavailable">Paciente no disponible</option>
                        <option value="other">Otro motivo</option>
                    </select>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('returnModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg">
                    Cancelar
                </button>
                <button onclick="executeReturn()" class="flex-1 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                    Devolver
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <script src="assets/js/staff-client.js"></script>
    
    <script>
        function showRoomsSection() {
            hideAllSections();
            document.getElementById('rooms-list-section').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = 'Salas de Atención';
        }

        function hideAllSections() {
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
        }

        function showTransferModal() {
            document.getElementById('transferModal').classList.remove('hidden');
        }

        function showEndSessionModal() {
            document.getElementById('endSessionModal').classList.remove('hidden');
        }

        function showReturnModal() {
            document.getElementById('returnModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function toggleTransferFields() {
            const transferType = document.getElementById('transferType').value;
            const internalFields = document.getElementById('internalTransferFields');
            const externalFields = document.getElementById('externalTransferFields');
            
            if (transferType === 'internal') {
                internalFields.classList.remove('hidden');
                externalFields.classList.add('hidden');
            } else {
                internalFields.classList.add('hidden');
                externalFields.classList.remove('hidden');
            }
        }

        async function executeTransfer() {
            const transferType = document.getElementById('transferType').value;
            const reason = document.getElementById('transferReason').value.trim();
            
            if (!reason) {
                alert('Ingresa el motivo');
                return;
            }
            
            try {
                if (transferType === 'internal') {
                    const targetAgentId = document.getElementById('targetAgentId').value.trim();
                    if (!targetAgentId) {
                        alert('Ingresa el ID del agente');
                        return;
                    }
                    await staffClient.transferInternal(targetAgentId, reason);
                } else {
                    const targetRoom = document.getElementById('targetRoom').value;
                    await staffClient.requestExternalTransfer(targetRoom, reason);
                }
                
                closeModal('transferModal');
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function executeEndSession() {
            const reason = document.getElementById('endReason').value;
            const notes = document.getElementById('endNotes').value.trim();
            
            try {
                await staffClient.endSession(reason, notes);
                closeModal('endSessionModal');
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function executeReturn() {
            const reason = document.getElementById('returnReason').value;
            
            try {
                await staffClient.returnToQueue(reason);
                closeModal('returnModal');
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        function getToken() {
            return '<?= $_SESSION['pToken'] ?>';
        }

        function logout() {
            if (confirm('¿Cerrar sesión?')) {
                if (staffClient.chatSocket) {
                    staffClient.chatSocket.disconnect();
                }
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = '/practicas/chat-frontend/public/logout.php';
            }
        }

        function updateTime() {
            document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('es-ES');
        }

        document.addEventListener('DOMContentLoaded', async () => {
            console.log('✅ Panel de agente cargado');
            
            updateTime();
            setInterval(updateTime, 1000);
            
            try {
                await staffClient.init();
                console.log('🚀 StaffClient inicializado');
            } catch (error) {
                console.error('❌ Error inicializando:', error);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.fixed.inset-0').forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        modal.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html>