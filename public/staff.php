<?php
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
        .typing-dots { display: flex; gap: 4px; }
        .typing-dot {
            width: 6px; height: 6px; background: #9ca3af; border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
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
                    <a href="#pending" onclick="showPendingSection()" 
                       class="nav-link active flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Conversaciones Pendientes
                        <span id="pendingCount" class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">0</span>
                    </a>
                    
                    <a href="#rooms" onclick="showRoomsSection()" 
                       class="nav-link flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                        Ver por Salas
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
                        <h2 id="sectionTitle" class="text-xl font-semibold text-gray-900">Conversaciones Pendientes</h2>
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
                
                <!-- CONVERSACIONES PENDIENTES (VISTA PRINCIPAL) -->
                <div id="pending-conversations-section" class="section-content p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Pacientes Esperando Atención</h3>
                                    <p class="text-sm text-gray-600 mt-1">Toma una conversación para comenzar a atender</p>
                                </div>
                                <button onclick="loadPendingConversations()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    🔄 Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="pendingConversationsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando conversaciones pendientes...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LISTA DE SALAS (VISTA SECUNDARIA) -->
                <div id="rooms-list-section" class="section-content hidden p-6">
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
                                    <button onclick="showRoomsSection()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                        </svg>
                                    </button>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">
                                            Sesiones en: <span id="currentRoomName">Sala</span>
                                        </h3>
                                        <p class="text-sm text-gray-600">Pacientes en esta sala</p>
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

                <!-- CHAT CON PACIENTE - DISEÑO ORIGINAL MEJORADO -->
                <div id="patient-chat-panel" class="section-content hidden">
                    <div class="h-full flex">
                        
                        <!-- Chat Principal -->
                        <div class="chat-main flex flex-col bg-white">
                            
                            <!-- Header del Chat -->
                            <div class="bg-white border-b border-gray-200 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <button onclick="goBackToPending()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
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
                            
                            <!-- Indicador de typing del paciente -->
                            <div id="typingIndicator" class="hidden px-6 py-2 bg-gray-50">
                                <div class="flex items-center space-x-2 text-sm text-gray-500">
                                    <span>El paciente está escribiendo</span>
                                    <div class="typing-dots">
                                        <div class="typing-dot"></div>
                                        <div class="typing-dot"></div>
                                        <div class="typing-dot"></div>
                                    </div>
                                </div>
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
                                            onkeydown="handleAgentKeyDown(event)"
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
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Tomador</label>
                                            <p id="patientInfoTomador" class="text-sm text-gray-900">-</p>
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
                        <option value="external">Transferencia Externa</option>
                        <option value="internal">Transferencia Interna</option>
                    </select>
                </div>
                
                <div id="externalTransferFields">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sala Destino</label>
                    <select id="targetRoom" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="general">Consultas Generales</option>
                        <option value="medical">Consultas Médicas</option>
                        <option value="support">Soporte Técnico</option>
                        <option value="emergency">Emergencias</option>
                    </select>
                </div>
                
                <div id="internalTransferFields" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">ID del Agente</label>
                    <input type="text" id="targetAgentId" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="agent_123">
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
        // ====== VARIABLES GLOBALES ======
        let currentSession = null;
        let pendingConversations = [];
        let refreshInterval = null;
        let agentTypingTimer;
        let agentIsTyping = false;

        // ====== FUNCIONES DE NAVEGACIÓN ======
        function showPendingSection() {
            hideAllSections();
            document.getElementById('pending-conversations-section').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = 'Conversaciones Pendientes';
            updateNavigation('pending');
            loadPendingConversations();
        }

        function showRoomsSection() {
            hideAllSections();
            document.getElementById('rooms-list-section').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = 'Salas de Atención';
            updateNavigation('rooms');
            if (typeof staffClient !== 'undefined') {
                staffClient.loadRoomsFromAuthService();
            }
        }

        function goBackToPending() {
            if (currentSession) {
                // Desconectar del chat actual
                if (typeof staffClient !== 'undefined' && staffClient.chatSocket) {
                    staffClient.chatSocket.disconnect();
                }
                currentSession = null;
            }
            showPendingSection();
        }

        function hideAllSections() {
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
        }

        function updateNavigation(active) {
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                link.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            
            if (active === 'pending') {
                document.querySelector('a[href="#pending"]').classList.add('active');
                document.querySelector('a[href="#pending"]').classList.remove('text-gray-600', 'hover:bg-gray-100');
            } else if (active === 'rooms') {
                document.querySelector('a[href="#rooms"]').classList.add('active');
                document.querySelector('a[href="#rooms"]').classList.remove('text-gray-600', 'hover:bg-gray-100');
            }
        }

        // ====== FUNCIONES PARA CONVERSACIONES PENDIENTES - URLs LOCALES ======
        async function loadPendingConversations() {
            const container = document.getElementById('pendingConversationsContainer');
            const countBadge = document.getElementById('pendingCount');
            
            // Mostrar loading
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-500">Cargando conversaciones pendientes...</p>
                </div>
            `;

            try {
                console.log('📡 Cargando conversaciones pendientes REALES...');
                
                // LLAMADA REAL A LA API LOCAL
                const response = await fetch('http://localhost:3011/chats/sessions?waiting', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + getToken()
                    }
                });

                if (response.ok) {
                    const result = await response.json();
                    console.log('📨 Respuesta de sesiones pendientes:', result);
                    
                    if (result.success && result.data && result.data.sessions) {
                        pendingConversations = result.data.sessions;
                        countBadge.textContent = pendingConversations.length;
                        
                        if (pendingConversations.length === 0) {
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
                            renderPendingConversations(pendingConversations);
                        }
                    } else {
                        throw new Error('Formato de respuesta inesperado');
                    }
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `Error HTTP ${response.status}`);
                }
                
            } catch (error) {
                console.error('❌ Error cargando conversaciones pendientes:', error);
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
                        <button onclick="loadPendingConversations()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Reintentar
                        </button>
                    </div>
                `;
            }
        }

        function renderPendingConversations(conversations) {
            const container = document.getElementById('pendingConversationsContainer');
            
            const html = conversations.map(conv => {
                const waitTime = getWaitTime(conv.created_at);
                const urgencyClass = getUrgencyClass(waitTime);
                const patientName = getPatientNameFromSession(conv);
                const roomName = getRoomNameFromSession(conv);
                
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
                                    <p class="text-xs text-gray-400">Estado: ${conv.status || 'waiting'}</p>
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
                                        onclick="takeConversation('${conv.id}')"
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

        function getPatientNameFromSession(session) {
            if (session.user_data) {
                try {
                    const userData = typeof session.user_data === 'string' 
                        ? JSON.parse(session.user_data) 
                        : session.user_data;
                    
                    if (userData.nombreCompleto) return userData.nombreCompleto;
                } catch (e) {
                    console.warn('Error parseando user_data:', e);
                }
            }
            return 'Paciente';
        }

        function getRoomNameFromSession(session) {
            if (session.room_id) {
                const roomNames = {
                    'general': 'Consultas Generales',
                    'medical': 'Consultas Médicas',
                    'support': 'Soporte Técnico',
                    'emergency': 'Emergencias'
                };
                return roomNames[session.room_id] || session.room_id;
            }
            return 'Sala General';
        }

        function getWaitTime(createdAt) {
            const diff = Date.now() - new Date(createdAt).getTime();
            const minutes = Math.floor(diff / 60000);
            
            if (minutes < 1) return 'menos de 1 min';
            if (minutes < 60) return `${minutes} min`;
            
            const hours = Math.floor(minutes / 60);
            const remainingMins = minutes % 60;
            return `${hours}h ${remainingMins}m`;
        }

        function getUrgencyClass(waitTime) {
            if (waitTime.includes('h') || parseInt(waitTime) > 30) {
                return 'text-red-600 font-semibold countdown-urgent';
            } else if (parseInt(waitTime) > 15) {
                return 'text-yellow-600 font-semibold';
            }
            return 'text-green-600';
        }

        // ====== FUNCIÓN PARA TOMAR UNA CONVERSACIÓN - URL LOCAL ======
        async function takeConversation(sessionId) {
            try {
                console.log('👤 Tomando sesión REAL:', sessionId);
                
                const response = await fetch(`http://localhost:3011/chats/sessions/${sessionId}/assign/me`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + getToken()
                    },
                    body: JSON.stringify({
                        agent_id: getCurrentUser().id,
                        agent_data: {
                            name: getCurrentUser().name,
                            email: getCurrentUser().email
                        }
                    })
                });
                
                if (response.ok) {
                    const result = await response.json();
                    console.log('✅ Sesión asignada:', result);
                    
                    if (result.success) {
                        // Encontrar la conversación en la lista local
                        const conversation = pendingConversations.find(c => c.id === sessionId);
                        if (!conversation) {
                            throw new Error('Conversación no encontrada en la lista local');
                        }
                        
                        // Establecer como conversación actual
                        currentSession = conversation;
                        
                        showSuccess('Sesión asignada exitosamente');
                        
                        // Ir al chat después de un breve delay
                        setTimeout(() => {
                            openChat(conversation);
                        }, 1000);
                    } else {
                        throw new Error(result.message || 'Error asignando sesión');
                    }
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `Error HTTP ${response.status}`);
                }
                
            } catch (error) {
                console.error('❌ Error tomando conversación:', error);
                showError('Error al tomar la conversación: ' + error.message);
            }
        }

        function openChat(conversation) {
            hideAllSections();
            document.getElementById('patient-chat-panel').classList.remove('hidden');
            
            const patientName = getPatientNameFromSession(conversation);
            const roomName = getRoomNameFromSession(conversation);
            
            document.getElementById('sectionTitle').textContent = `Chat con ${patientName}`;
            
            // Actualizar header del chat
            document.getElementById('chatPatientName').textContent = patientName;
            document.getElementById('chatPatientInitials').textContent = patientName.charAt(0).toUpperCase();
            document.getElementById('chatRoomName').textContent = roomName;
            document.getElementById('chatSessionStatus').textContent = 'Activo';
            document.getElementById('chatPatientId').textContent = conversation.id;
            
            // Limpiar chat anterior
            document.getElementById('patientChatMessages').innerHTML = '';
            
            // Usar StaffClient para conectar (esto manejará la extracción de datos del paciente)
            if (typeof staffClient !== 'undefined') {
                staffClient.openPatientChat(conversation.id);
            } else {
                console.error('❌ StaffClient no disponible');
                showError('Error: Cliente de staff no disponible');
            }
        }

        // ====== FUNCIONES DE TYPING PARA EL AGENTE ======
        function setupAgentTyping() {
            const agentInput = document.getElementById('agentMessageInput');
            if (!agentInput) return;
            
            console.log('🔧 Configurando indicadores de typing para agente');
            
            agentInput.addEventListener('input', () => {
                // Verificar que esté conectado al chat
                if (!staffClient.isConnectedToChat || !staffClient.chatSocket || !staffClient.currentSessionId) {
                    return;
                }
                
                // Indicar que está escribiendo
                if (!agentIsTyping) {
                    console.log('📝 Agente empezó a escribir');
                    staffClient.chatSocket.emit('start_typing', {
                        session_id: staffClient.currentSessionId,
                        sender_type: 'agent',
                        user_type: 'agent'
                    });
                    agentIsTyping = true;
                }
                
                // Resetear timer
                clearTimeout(agentTypingTimer);
                agentTypingTimer = setTimeout(() => {
                    if (staffClient.chatSocket && staffClient.currentSessionId) {
                        console.log('📝 Agente paró de escribir (timeout)');
                        staffClient.chatSocket.emit('stop_typing', {
                            session_id: staffClient.currentSessionId,
                            sender_type: 'agent',
                            user_type: 'agent'
                        });
                    }
                    agentIsTyping = false;
                }, 1000);
                
                // Actualizar botón de enviar
                updateSendButton();
            });
            
            agentInput.addEventListener('blur', () => {
                // Parar typing cuando se pierde el foco
                if (agentIsTyping && staffClient.chatSocket && staffClient.currentSessionId) {
                    console.log('📝 Agente paró de escribir (blur)');
                    staffClient.chatSocket.emit('stop_typing', {
                        session_id: staffClient.currentSessionId,
                        sender_type: 'agent',
                        user_type: 'agent'
                    });
                    agentIsTyping = false;
                }
                clearTimeout(agentTypingTimer);
            });
        }

        function handleAgentKeyDown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                
                // Parar typing antes de enviar
                if (agentIsTyping && staffClient.chatSocket && staffClient.currentSessionId) {
                    console.log('📝 Agente paró de escribir (enviando mensaje)');
                    staffClient.chatSocket.emit('stop_typing', {
                        session_id: staffClient.currentSessionId,
                        sender_type: 'agent',
                        user_type: 'agent'
                    });
                    agentIsTyping = false;
                }
                clearTimeout(agentTypingTimer);
                
                // Enviar mensaje si hay contenido
                const input = document.getElementById('agentMessageInput');
                if (input && input.value.trim()) {
                    staffClient.sendMessage();
                }
            }
            updateSendButton();
        }

        function updateSendButton() {
            const input = document.getElementById('agentMessageInput');
            const button = document.getElementById('agentSendButton');
            
            if (input && button) {
                button.disabled = !input.value.trim() || !currentSession;
            }
        }

        // ====== FUNCIONES DE MODALES ======
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
                showError('Error: ' + error.message);
            }
        }

        async function executeEndSession() {
            const reason = document.getElementById('endReason').value;
            const notes = document.getElementById('endNotes').value.trim();
            
            try {
                await staffClient.endSession(reason, notes);
                closeModal('endSessionModal');
            } catch (error) {
                showError('Error: ' + error.message);
            }
        }

        async function executeReturn() {
            const reason = document.getElementById('returnReason').value;
            
            try {
                await staffClient.returnToQueue(reason);
                closeModal('returnModal');
            } catch (error) {
                showError('Error: ' + error.message);
            }
        }

        // ====== FUNCIONES UTILITARIAS ======
        function getToken() {
            return '<?= $_SESSION['pToken'] ?>';
        }

        function getCurrentUser() {
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

        function logout() {
            if (confirm('¿Cerrar sesión?')) {
                if (staffClient && staffClient.chatSocket) {
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

        function showSuccess(message) {
            showNotification(message, 'success');
        }

        function showError(message) {
            showNotification(message, 'error');
        }

        function showNotification(message, type = 'info', duration = 4000) {
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

        // ====== INICIALIZACIÓN ======
        document.addEventListener('DOMContentLoaded', async () => {
            console.log('✅ Panel de agente con URLs locales cargado');
            
            updateTime();
            setInterval(updateTime, 1000);
            
            // Configurar typing del agente
            setupAgentTyping();
            
            // Cargar conversaciones pendientes por defecto
            showPendingSection();
            
            // Inicializar StaffClient
            try {
                if (typeof staffClient !== 'undefined') {
                    await staffClient.init();
                    console.log('🚀 StaffClient inicializado');
                } else {
                    console.error('❌ StaffClient no disponible');
                }
            } catch (error) {
                console.error('❌ Error inicializando StaffClient:', error);
            }
            
            // Refrescar automáticamente cada 30 segundos
            refreshInterval = setInterval(() => {
                if (document.getElementById('pending-conversations-section').classList.contains('hidden') === false) {
                    loadPendingConversations();
                }
            }, 30000);
        });

        // ====== EVENTOS GLOBALES ======
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