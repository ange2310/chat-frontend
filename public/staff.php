<?php
// public/staff.php - CON CHAT COMPLETO
session_start();

// VERIFICACIÓN BÁSICA
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

error_log("[STAFF] Usuario: " . $user['name'] . " Token: " . substr($_SESSION['pToken'], 0, 15) . "...");
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Médico - <?= htmlspecialchars($user['name'] ?? 'Staff') ?></title>
    
    <meta name="staff-token" content="<?= $_SESSION['pToken'] ?>">
    <meta name="staff-user" content='<?= json_encode($user) ?>'>
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nav-link.active { background: #2563eb; color: white; }
        
        /* Chat Styles - Similares al paciente pero desde perspectiva del agente */
        .chat-fullscreen { 
            position: fixed; 
            inset: 0; 
            z-index: 50; 
            display: flex; 
            flex-direction: column; 
            background: white; 
        }
        
        .chat-header { 
            background: white; 
            border-bottom: 1px solid #e5e7eb; 
            padding: 1rem 1.5rem; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            min-height: 70px; 
        }
        
        .chat-messages { 
            flex: 1; 
            overflow-y: auto; 
            padding: 1.5rem; 
            background: #f8fafc; 
            display: flex; 
            flex-direction: column; 
            gap: 1rem; 
        }
        
        .chat-input-area { 
            background: white; 
            border-top: 1px solid #e5e7eb; 
            padding: 1.5rem; 
        }
        
        .message { 
            display: flex; 
            gap: 0.75rem; 
            max-width: 80%; 
        }
        
        .message-patient { 
            align-self: flex-start; 
        }
        
        .message-agent { 
            align-self: flex-end; 
            flex-direction: row-reverse; 
        }
        
        .message-content { 
            background: #e5e7eb; 
            border: 1px solid #d1d5db; 
            border-radius: 1rem; 
            padding: 0.75rem 1rem; 
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); 
            position: relative; 
            word-wrap: break-word; 
        }
        
        .message-agent .message-content { 
            background: #2563eb; 
            color: white; 
            border-color: #2563eb; 
        }
        
        .message-time { 
            font-size: 0.75rem; 
            color: #6b7280; 
            margin-top: 0.25rem; 
        }
        
        .message-agent .message-time { 
            color: rgba(255, 255, 255, 0.7); 
        }
        
        .chat-input { 
            width: 100%; 
            min-height: 44px; 
            max-height: 120px; 
            padding: 0.75rem 60px 0.75rem 1rem; 
            border: 1px solid #d1d5db; 
            border-radius: 1.5rem; 
            font-size: 14px; 
            resize: none; 
            background: #f9fafb; 
            transition: all 0.15s ease-in-out; 
        }
        
        .chat-input:focus { 
            outline: none; 
            border-color: #2563eb; 
            background: white; 
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1); 
        }
        
        .chat-input-container { 
            position: relative; 
        }
        
        .chat-input-actions { 
            position: absolute; 
            right: 0.5rem; 
            top: 50%; 
            transform: translateY(-50%); 
            display: flex; 
            align-items: center; 
            gap: 0.25rem; 
        }
        
        .chat-input-btn { 
            width: 36px; 
            height: 36px; 
            border-radius: 50%; 
            border: none; 
            background: transparent; 
            color: #9ca3af; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            transition: all 0.15s ease-in-out; 
        }
        
        .chat-input-btn:hover { 
            background: #f3f4f6; 
            color: #6b7280; 
        }
        
        .chat-input-btn.btn-send { 
            background: #2563eb; 
            color: white; 
        }
        
        .chat-input-btn.btn-send:hover { 
            background: #1d4ed8; 
            transform: scale(1.05); 
        }
        
        .chat-input-btn:disabled { 
            opacity: 0.4; 
            cursor: not-allowed; 
        }
        
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            color: #6b7280;
            font-size: 13px;
        }
        
        .typing-dots {
            display: flex;
            gap: 4px;
        }
        
        .typing-dot {
            width: 6px;
            height: 6px;
            background: #9ca3af;
            border-radius: 50%;
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Panel Médico</h1>
                        <p class="text-sm text-gray-500">Con Chat</p>
                    </div>
                </div>
            </div>
            
            <nav class="flex-1 p-4">
                <div class="space-y-1">
                    <a href="#dashboard" onclick="showSection('dashboard')" 
                       class="nav-link active flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        Dashboard
                    </a>
                    <a href="#sessions" onclick="showSection('sessions')" 
                       class="nav-link text-gray-600 hover:bg-gray-100 hover:text-gray-900 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        Sesiones
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
                    <button onclick="logout()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            
            <header class="bg-white border-b border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h2 id="sectionTitle" class="text-xl font-semibold text-gray-900">Dashboard</h2>
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
                
                <!-- Dashboard -->
                <div id="dashboard-section" class="section-content p-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Panel de Control</h3>
                        <p class="text-gray-600 mb-4">
                            Bienvenido <strong><?= htmlspecialchars($user['name']) ?></strong> 
                            (<?= htmlspecialchars($userRole) ?>)
                        </p>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-2">Estado del Sistema</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span>Token Staff:</span>
                                    <span class="font-mono text-green-600">✅ <?= substr($_SESSION['pToken'], 0, 20) ?>...</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Usuario:</span>
                                    <span><?= htmlspecialchars($user['name']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Rol:</span>
                                    <span class="capitalize"><?= htmlspecialchars($userRole) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sesiones -->
                <div id="sessions-section" class="section-content hidden p-6">
                    
                    <!-- Lista de Sesiones -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">Sesiones de Chat</h3>
                                <button onclick="loadSessions()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="sessionsList">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando sesiones...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Chat Pantalla Completa -->
    <div id="chatSection" class="hidden chat-fullscreen">
        
        <!-- Chat Header -->
        <div class="chat-header">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center space-x-4">
                    <button onclick="closeChat()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <span id="patientInitials" class="text-lg font-semibold text-green-700">P</span>
                    </div>
                    <div>
                        <h2 id="chatPatientName" class="text-xl font-bold text-gray-900">Paciente</h2>
                        <p class="text-sm text-gray-500">
                            Chat médico • 
                            <span id="chatStatus" class="text-green-600">Conectado</span>
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="text-center">
                        <div class="text-sm text-gray-500">Agente:</div>
                        <div class="font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                    </div>
                    
                    <button onclick="endChatSession()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Finalizar Chat
                    </button>
                </div>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <!-- Los mensajes aparecerán aquí -->
        </div>

        <!-- Indicador de escritura -->
        <div id="typingIndicator" class="hidden typing-indicator">
            <div class="flex items-center space-x-2">
                <span>El paciente está escribiendo</span>
                <div class="typing-dots">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="chat-input-area">
            <div class="chat-input-container">
                <textarea 
                    id="messageInput" 
                    class="chat-input"
                    placeholder="Escribe tu respuesta al paciente..."
                    maxlength="500"
                    rows="1"
                ></textarea>
                
                <div class="chat-input-actions">
                    <button id="sendButton" class="chat-input-btn btn-send" onclick="sendMessage()" disabled title="Enviar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex justify-between items-center mt-3 text-xs text-gray-500">
                <div>
                    <input type="file" id="fileInput" accept="image/*,.pdf,.doc,.docx" class="hidden" onchange="handleFileUpload(this.files)">
                    <button onclick="document.getElementById('fileInput').click()" 
                            class="text-blue-600 hover:text-blue-700 flex items-center space-x-1 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                        </svg>
                        <span>Adjuntar archivo</span>
                    </button>
                </div>
                <span><span id="charCount">0</span>/500</span>
            </div>
        </div>
    </div>

    <!-- Socket.IO -->
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    
    <script>
        // CONFIGURACIÓN
        const CONFIG = {
            USER_DATA: <?= json_encode($user) ?>,
            CHAT_SERVICE_URL: 'http://187.33.158.246:8080/chats',
            WS_URL: 'ws://187.33.158.246:8080'
        };

        let sessions = [];
        let currentSession = null;
        let chatSocket = null;
        let isConnectedToChat = false;
        let previousSection = 'sessions'; // Recordar sección anterior

        // OBTENER TOKEN STAFF (para HTTP)
        function getStaffToken() {
            const token = document.querySelector('meta[name="staff-token"]')?.content;
            if (token && token.trim() !== '') {
                return token.trim();
            }
            console.error('❌ No hay token staff');
            return null;
        }

        // HEADERS PARA HTTP (usando JWT del staff)
        function getAuthHeaders() {
            const token = getStaffToken();
            if (!token) {
                throw new Error('No hay token staff');
            }
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`
            };
        }

        // CARGAR SESIONES (HTTP con JWT del staff)
        async function loadSessions() {
            try {
                console.log('📡 Cargando sesiones...');
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                console.log('📡 Status:', response.status);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Error HTTP ${response.status}: ${errorText}`);
                }
                
                const result = await response.json();
                console.log('📋 Sesiones recibidas:', result);
                
                if (result.success && result.data && result.data.sessions) {
                    sessions = result.data.sessions;
                    console.log(`✅ ${sessions.length} sesiones cargadas`);
                    displaySessions();
                } else {
                    console.warn('⚠️ No hay sesiones');
                    sessions = [];
                    displaySessions();
                }
                
            } catch (error) {
                console.error('❌ Error cargando sesiones:', error);
                showError('Error: ' + error.message);
                
                document.getElementById('sessionsList').innerHTML = `
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <p class="text-red-600 font-medium">Error cargando sesiones</p>
                        <p class="text-gray-500 text-sm mb-4">${error.message}</p>
                        <button onclick="loadSessions()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Reintentar
                        </button>
                    </div>
                `;
            }
        }

        // MOSTRAR SESIONES
        function displaySessions() {
            const container = document.getElementById('sessionsList');
            
            if (sessions.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 103 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                        <p class="text-gray-500">No hay sesiones disponibles</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="space-y-4">
                    ${sessions.map(session => createSessionCard(session)).join('')}
                </div>
            `;
        }

        // CREAR TARJETA DE SESIÓN
        function createSessionCard(session) {
            const patientName = getPatientName(session);
            const patientId = session.user_id || 'unknown';
            const status = session.status || 'waiting';
            const createdAt = new Date(session.created_at || Date.now()).toLocaleString('es-ES');
            const hasPToken = !!(session.ptoken);
            
            let statusColor = 'yellow';
            if (status === 'active') statusColor = 'green';
            else if (status === 'completed') statusColor = 'gray';
            
            return `
                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-lg font-semibold text-blue-700">${getInitials(patientName)}</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">${patientName}</h3>
                                <p class="text-sm text-gray-500">ID: ${patientId}</p>
                                <p class="text-sm text-gray-400">Creado: ${createdAt}</p>
                                ${hasPToken ? 
                                    '<p class="text-xs text-green-600">✅ Chat disponible</p>' : 
                                    '<p class="text-xs text-red-600">❌ Sin pToken</p>'
                                }
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-${statusColor}-100 text-${statusColor}-800">
                                ${formatStatus(status)}
                            </span>
                            
                            <div class="mt-2 space-x-2">
                                ${status === 'waiting' ? 
                                    `<button onclick="assignSession('${session.id}')" 
                                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                        Tomar
                                    </button>` : ''
                                }
                                
                                ${hasPToken ? 
                                    `<button onclick="openChat('${session.id}')" 
                                            class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                        Abrir Chat
                                    </button>` :
                                    `<button disabled class="px-3 py-1 bg-gray-400 text-white text-sm rounded cursor-not-allowed">
                                        Sin Chat
                                    </button>`
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // ASIGNAR SESIÓN (HTTP con JWT del staff)
        async function assignSession(sessionId) {
            try {
                console.log('👤 Asignando sesión:', sessionId);
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions/${sessionId}/assign`, {
                    method: 'PUT',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({
                        agent_id: CONFIG.USER_DATA.id,
                        agent_data: {
                            name: CONFIG.USER_DATA.name,
                            email: CONFIG.USER_DATA.email
                        }
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}`);
                }
                
                const result = await response.json();
                console.log('📋 Asignación exitosa:', result);
                
                if (result.success) {
                    showSuccess('Sesión asignada exitosamente');
                    loadSessions(); // Recargar lista
                } else {
                    throw new Error(result.message || 'Error asignando sesión');
                }
                
            } catch (error) {
                console.error('❌ Error asignando:', error);
                showError('Error: ' + error.message);
            }
        }

        // ABRIR CHAT (WebSocket con pToken del paciente)
        async function openChat(sessionId) {
            try {
                console.log('💬 Abriendo chat para sesión:', sessionId);
                
                const session = sessions.find(s => s.id === sessionId);
                if (!session) {
                    throw new Error('Sesión no encontrada');
                }
                
                if (!session.ptoken) {
                    throw new Error('Esta sesión no tiene pToken para chat');
                }
                
                currentSession = session;
                
                // Mostrar UI del chat (overlay sobre la vista actual)
                document.getElementById('chatSection').classList.remove('hidden');
                updateChatHeader(session);
                
                // Conectar WebSocket usando el pToken del paciente
                await connectChatWebSocket(session.ptoken, session.id);
                
                console.log('✅ Chat abierto exitosamente');
                
            } catch (error) {
                console.error('❌ Error abriendo chat:', error);
                showError('Error: ' + error.message);
            }
        }

        // CONECTAR WEBSOCKET PARA CHAT (usando pToken del paciente)
        async function connectChatWebSocket(pToken, sessionId) {
            try {
                console.log('🔌 Conectando WebSocket del chat...');
                console.log('🔑 Usando pToken del paciente:', pToken.substring(0, 15) + '...');
                
                chatSocket = io(CONFIG.WS_URL, {
                    path: '/socket.io/',
                    transports: ['websocket', 'polling'],
                    autoConnect: true,
                    auth: {
                        ptoken: pToken
                    }
                });
                
                chatSocket.on('connect', () => {
                    console.log('✅ Chat WebSocket conectado');
                    isConnectedToChat = true;
                    updateChatStatus('Conectado');
                    
                    // Autenticar
                    chatSocket.emit('authenticate', { ptoken: pToken });
                });
                
                chatSocket.on('authenticated', (data) => {
                    console.log('✅ Chat autenticado:', data);
                    
                    // Unirse a la sesión
                    chatSocket.emit('join_session', { 
                        session_id: sessionId,
                        agent_mode: true,
                        agent_data: {
                            id: CONFIG.USER_DATA.id,
                            name: CONFIG.USER_DATA.name,
                            email: CONFIG.USER_DATA.email
                        }
                    });
                });
                
                chatSocket.on('session_joined', (data) => {
                    console.log('✅ Sesión unida:', data);
                    loadChatHistory(sessionId);
                });
                
                chatSocket.on('message_received', (data) => {
                    console.log('📨 Mensaje recibido:', data);
                    addMessageToChat(data, false); // false = no es del agente
                    playNotificationSound();
                });
                
                chatSocket.on('file_uploaded', (data) => {
                    console.log('📎 Archivo recibido:', data);
                    addFileToChat(data, false);
                });
                
                chatSocket.on('user_typing', () => {
                    showTypingIndicator();
                });
                
                chatSocket.on('user_stop_typing', () => {
                    hideTypingIndicator();
                });
                
                chatSocket.on('disconnect', () => {
                    console.log('🔌 Chat desconectado');
                    isConnectedToChat = false;
                    updateChatStatus('Desconectado');
                });
                
                chatSocket.on('connect_error', (error) => {
                    console.error('❌ Error WebSocket chat:', error);
                    updateChatStatus('Error de conexión');
                    showError('Error conectando chat: ' + error.message);
                });
                
            } catch (error) {
                console.error('❌ Error conectando WebSocket chat:', error);
                throw error;
            }
        }

        // CARGAR HISTORIAL DEL CHAT
        async function loadChatHistory(sessionId) {
            try {
                console.log('📚 Cargando historial del chat...');
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/messages/${sessionId}?limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.messages) {
                        const messages = result.data.messages;
                        console.log(`📨 Cargando ${messages.length} mensajes`);
                        
                        // Limpiar chat
                        document.getElementById('chatMessages').innerHTML = '';
                        
                        // Agregar mensajes
                        messages.forEach(message => {
                            const isFromAgent = message.sender_type === 'agent';
                            addMessageToChat(message, isFromAgent);
                        });
                        
                        scrollToBottom();
                    }
                } else {
                    console.warn('No se pudo cargar historial');
                }
                
            } catch (error) {
                console.error('❌ Error cargando historial:', error);
            }
        }

        // AGREGAR MENSAJE AL CHAT
        function addMessageToChat(messageData, isFromAgent = false) {
            const messagesContainer = document.getElementById('chatMessages');
            if (!messagesContainer) return;

            const messageDiv = document.createElement('div');
            const timeLabel = formatTime(messageData.timestamp || messageData.created_at);

            messageDiv.className = `message ${isFromAgent ? 'message-agent' : 'message-patient'}`;
            
            if (isFromAgent) {
                // Mensaje del agente (yo)
                messageDiv.innerHTML = `
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #2563eb;">
                        ${CONFIG.USER_DATA.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="message-content">
                        <p>${escapeHtml(messageData.content || '')}</p>
                        <div class="message-time">${timeLabel}</div>
                    </div>
                `;
            } else {
                // Mensaje del paciente
                messageDiv.innerHTML = `
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #10b981;">
                        P
                    </div>
                    <div class="message-content">
                        <div class="text-sm font-medium text-gray-700 mb-1">Paciente:</div>
                        <p>${escapeHtml(messageData.content || '')}</p>
                        <div class="message-time">${timeLabel}</div>
                    </div>
                `;
            }
            
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        // ENVIAR MENSAJE
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !isConnectedToChat || !chatSocket) {
                return;
            }
            
            console.log('📤 Enviando mensaje:', message);
            
            chatSocket.emit('send_message', {
                content: message,
                message_type: 'text',
                session_id: currentSession.id,
                sender_type: 'agent',
                agent_data: {
                    id: CONFIG.USER_DATA.id,
                    name: CONFIG.USER_DATA.name
                }
            });
            
            // Agregar mensaje inmediatamente a la UI
            addMessageToChat({
                content: message,
                timestamp: new Date().toISOString(),
                sender_type: 'agent'
            }, true);
            
            // Limpiar input
            input.value = '';
            updateCharCount();
            document.getElementById('sendButton').disabled = true;
            input.focus();
        }

        // SUBIR ARCHIVO
        async function handleFileUpload(files) {
            if (!files || files.length === 0 || !currentSession) return;
            
            const file = files[0];
            
            if (file.size > 10 * 1024 * 1024) {
                showError('Archivo muy grande (máximo 10MB)');
                return;
            }
            
            try {
                console.log('📎 Subiendo archivo:', file.name);
                
                const formData = new FormData();
                formData.append('file', file);
                formData.append('session_id', currentSession.id);
                formData.append('user_id', CONFIG.USER_DATA.id);
                formData.append('sender_type', 'agent');
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/files/upload`, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    console.log('✅ Archivo subido');
                    showSuccess('Archivo enviado');
                } else {
                    throw new Error(result.message || 'Error subiendo archivo');
                }
                
            } catch (error) {
                console.error('❌ Error subiendo archivo:', error);
                showError('Error: ' + error.message);
            }
        }

        // CERRAR CHAT
        function closeChat() {
            console.log('🔄 Cerrando chat...');
            
            if (chatSocket) {
                chatSocket.disconnect();
                chatSocket = null;
                console.log('🔌 Socket desconectado');
            }
            
            isConnectedToChat = false;
            currentSession = null;
            
            // Forzar ocultar chat
            const chatSection = document.getElementById('chatSection');
            if (chatSection) {
                chatSection.classList.add('hidden');
                chatSection.style.display = 'none';
                console.log('🙈 Chat ocultado');
            }
            
            // Mostrar la sección anterior
            const targetSection = document.getElementById(previousSection + '-section');
            if (targetSection) {
                targetSection.classList.remove('hidden');
                targetSection.style.display = 'block';
                console.log(`👀 Mostrando ${previousSection}`);
            }
            
            // Actualizar navegación
            updateNavigation(previousSection);
            
            console.log(`✅ Chat cerrado - regresando a ${previousSection}`);
        }
        
        // FUNCIÓN AUXILIAR PARA ACTUALIZAR NAVEGACIÓN
        function updateNavigation(sectionName) {
            // Actualizar links del sidebar
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                link.classList.add('text-gray-600', 'hover:bg-gray-100', 'hover:text-gray-900');
            });
            
            // Activar el link correcto
            const activeLink = document.querySelector(`a[href="#${sectionName}"]`);
            if (activeLink) {
                activeLink.classList.remove('text-gray-600', 'hover:bg-gray-100', 'hover:text-gray-900');
                activeLink.classList.add('active');
            }
            
            // Actualizar título
            const titles = {
                'dashboard': 'Dashboard',
                'sessions': 'Sesiones de Chat'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName] || 'Panel';
        }

        // FINALIZAR SESIÓN
        async function endChatSession() {
            if (!currentSession || !confirm('¿Finalizar esta sesión de chat?')) {
                return;
            }
            
            try {
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions/${currentSession.id}/end`, {
                    method: 'PUT',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({
                        ended_by: CONFIG.USER_DATA.id,
                        end_reason: 'completed_by_agent'
                    })
                });
                
                if (response.ok) {
                    showSuccess('Sesión finalizada exitosamente');
                    closeChat(); // Esto ya regresa a sesiones
                    loadSessions(); // Recargar lista
                } else {
                    throw new Error('Error finalizando sesión');
                }
                
            } catch (error) {
                console.error('❌ Error finalizando sesión:', error);
                showError('Error: ' + error.message);
            }
        }

        // UTILIDADES DE UI
        function updateChatHeader(session) {
            const patientName = getPatientName(session);
            
            document.getElementById('chatPatientName').textContent = patientName;
            document.getElementById('patientInitials').textContent = getInitials(patientName);
        }

        function updateChatStatus(status) {
            document.getElementById('chatStatus').textContent = status;
        }

        function showTypingIndicator() {
            document.getElementById('typingIndicator').classList.remove('hidden');
            scrollToBottom();
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').classList.add('hidden');
        }

        function scrollToBottom() {
            const container = document.getElementById('chatMessages');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        function updateCharCount() {
            const input = document.getElementById('messageInput');
            const count = input.value.length;
            document.getElementById('charCount').textContent = count;
            document.getElementById('sendButton').disabled = count === 0;
        }

        function playNotificationSound() {
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmYfBSuPze/R');
                audio.volume = 0.1;
                audio.play().catch(() => {});
            } catch (error) {
                // Ignorar errores de audio
            }
        }

        // UTILIDADES GENERALES
        function getPatientName(session) {
            if (session.patient_data) {
                return session.patient_data.name || session.patient_data.nombreCompleto || 'Paciente';
            }
            
            if (session.user_data) {
                try {
                    const userData = typeof session.user_data === 'string' 
                        ? JSON.parse(session.user_data) 
                        : session.user_data;
                    
                    if (userData.nombreCompleto) {
                        return userData.nombreCompleto;
                    }
                    
                    if (userData.primer_nombre) {
                        return [
                            userData.primer_nombre,
                            userData.segundo_nombre,
                            userData.primer_apellido,
                            userData.segundo_apellido
                        ].filter(n => n).join(' ');
                    }
                } catch (e) {
                    console.warn('Error parseando user_data:', e);
                }
            }
            
            return 'Paciente';
        }

        function getInitials(name) {
            return name.split(' ')
                      .map(part => part.charAt(0))
                      .join('')
                      .substring(0, 2)
                      .toUpperCase();
        }

        function formatStatus(status) {
            const statusMap = {
                'waiting': 'En Espera',
                'active': 'Activo',
                'completed': 'Completado',
                'expired': 'Expirado'
            };
            return statusMap[status] || status;
        }

        function formatTime(timestamp) {
            try {
                const date = new Date(timestamp);
                return date.toLocaleTimeString('es-ES', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
            } catch (error) {
                return '';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // NAVEGACIÓN
        function showSection(sectionName) {
            // Guardar sección anterior (excepto si es el chat)
            if (sectionName !== 'chat') {
                previousSection = sectionName;
            }
            
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.remove('hidden');
            }
            
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                link.classList.add('text-gray-600', 'hover:bg-gray-100', 'hover:text-gray-900');
            });
            
            if (event && event.target) {
                const navLink = event.target.closest('.nav-link');
                if (navLink) {
                    navLink.classList.remove('text-gray-600', 'hover:bg-gray-100', 'hover:text-gray-900');
                    navLink.classList.add('active');
                }
            }
            
            const titles = {
                'dashboard': 'Dashboard',
                'sessions': 'Sesiones de Chat'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName] || 'Panel';
            
            if (sectionName === 'sessions') {
                loadSessions();
            }
        }

        // NOTIFICACIONES
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
                info: 'bg-blue-500'
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

        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('es-ES');
        }

        function logout() {
            if (confirm('¿Cerrar sesión?')) {
                if (chatSocket) chatSocket.disconnect();
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = '/practicas/chat-frontend/public/logout.php';
            }
        }

        // SETUP DE INPUT
        document.addEventListener('DOMContentLoaded', () => {
            console.log('✅ Panel con chat cargado');
            console.log('👤 Usuario:', CONFIG.USER_DATA.name);
            
            updateTime();
            setInterval(updateTime, 1000);
            
            // Setup input del chat
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', updateCharCount);
                messageInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        if (messageInput.value.trim()) {
                            sendMessage();
                        }
                    }
                });
            }
            
            showSuccess(`¡Bienvenido ${CONFIG.USER_DATA.name}!`);
        });

        // DEBUG
        window.getStaffToken = getStaffToken;
        window.loadSessions = loadSessions;
        window.openChat = openChat;

        // 1. PRIMERO: Agregar debugging en loadSessions() para ver qué datos llegan
async function loadSessions() {
    try {
        console.log('📡 Cargando sesiones...');
        
        const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions`, {
            method: 'GET',
            headers: getAuthHeaders()
        });
        
        console.log('📡 Status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Error HTTP ${response.status}: ${errorText}`);
        }
        
        const result = await response.json();
        console.log('📋 Respuesta completa del backend:', result);
        
        if (result.success && result.data && result.data.sessions) {
            sessions = result.data.sessions;
            console.log(`✅ ${sessions.length} sesiones cargadas`);
            
            // NUEVO: Debugging detallado de cada sesión
            sessions.forEach((session, index) => {
                console.log(`🔍 Sesión ${index + 1}:`, {
                    id: session.id,
                    user_id: session.user_id,
                    status: session.status,
                    ptoken: session.ptoken,
                    pToken: session.pToken, // Verificar ambas variantes
                    patient_token: session.patient_token,
                    chat_token: session.chat_token,
                    // Mostrar todas las propiedades para identificar el campo correcto
                    all_keys: Object.keys(session)
                });
            });
            
            displaySessions();
        } else {
            console.warn('⚠️ No hay sesiones');
            sessions = [];
            displaySessions();
        }
        
    } catch (error) {
        console.error('❌ Error cargando sesiones:', error);
        showError('Error: ' + error.message);
        
        document.getElementById('sessionsList').innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <p class="text-red-600 font-medium">Error cargando sesiones</p>
                <p class="text-gray-500 text-sm mb-4">${error.message}</p>
                <button onclick="loadSessions()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Reintentar
                </button>
            </div>
        `;
    }
}

// 2. VERIFICAR DIFERENTES NOMBRES DE CAMPO EN createSessionCard()
function createSessionCard(session) {
    const patientName = getPatientName(session);
    const patientId = session.user_id || 'unknown';
    const status = session.status || 'waiting';
    const createdAt = new Date(session.created_at || Date.now()).toLocaleString('es-ES');
    
    // NUEVO: Verificar múltiples variantes del token
    const pToken = session.ptoken || session.pToken || session.patient_token || session.chat_token || session.token;
    const hasPToken = !!(pToken && pToken.trim() !== '');
    
    // NUEVO: Log para debugging
    console.log(`🔍 Sesión ${session.id} - pToken encontrado:`, {
        ptoken: session.ptoken,
        pToken: session.pToken,
        patient_token: session.patient_token,
        chat_token: session.chat_token,
        token: session.token,
        final_pToken: pToken,
        hasPToken: hasPToken
    });
    
    let statusColor = 'yellow';
    if (status === 'active') statusColor = 'green';
    else if (status === 'completed') statusColor = 'gray';
    
    return `
        <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-lg font-semibold text-blue-700">${getInitials(patientName)}</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">${patientName}</h3>
                        <p class="text-sm text-gray-500">ID: ${patientId}</p>
                        <p class="text-sm text-gray-400">Creado: ${createdAt}</p>
                        ${hasPToken ? 
                            `<p class="text-xs text-green-600">✅ Chat disponible (${pToken.substring(0, 10)}...)</p>` : 
                            '<p class="text-xs text-red-600">❌ Sin pToken</p>'
                        }
                        <!-- NUEVO: Mostrar información de debug -->
                        <details class="text-xs text-gray-400 mt-1">
                            <summary>Debug Info</summary>
                            <pre class="mt-1 text-xs bg-gray-100 p-2 rounded">${JSON.stringify({
                                id: session.id,
                                status: session.status,
                                ptoken: session.ptoken ? session.ptoken.substring(0, 15) + '...' : null,
                                keys: Object.keys(session)
                            }, null, 2)}</pre>
                        </details>
                    </div>
                </div>
                
                <div class="text-right">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-${statusColor}-100 text-${statusColor}-800">
                        ${formatStatus(status)}
                    </span>
                    
                    <div class="mt-2 space-x-2">
                        ${status === 'waiting' ? 
                            `<button onclick="assignSession('${session.id}')" 
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Tomar
                            </button>` : ''
                        }
                        
                        ${hasPToken ? 
                            `<button onclick="openChat('${session.id}')" 
                                    class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                Abrir Chat
                            </button>` :
                            `<button onclick="requestPToken('${session.id}')" 
                                    class="px-3 py-1 bg-orange-600 text-white text-sm rounded hover:bg-orange-700">
                                Generar Token
                            </button>`
                        }
                    </div>
                </div>
            </div>
        </div>
    `;
}

// 3. NUEVA FUNCIÓN: Solicitar pToken si no existe
async function requestPToken(sessionId) {
    try {
        console.log('🔑 Solicitando pToken para sesión:', sessionId);
        
        const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions/${sessionId}/generate-token`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify({
                agent_id: CONFIG.USER_DATA.id
            })
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP ${response.status}`);
        }
        
        const result = await response.json();
        console.log('🔑 Token generado:', result);
        
        if (result.success) {
            showSuccess('Token generado exitosamente');
            loadSessions(); // Recargar para mostrar el nuevo token
        } else {
            throw new Error(result.message || 'Error generando token');
        }
        
    } catch (error) {
        console.error('❌ Error generando token:', error);
        showError('Error: ' + error.message);
    }
}

// 4. MODIFICAR openChat() para manejar diferentes nombres de campo
async function openChat(sessionId) {
    try {
        console.log('💬 Abriendo chat para sesión:', sessionId);
        
        const session = sessions.find(s => s.id === sessionId);
        if (!session) {
            throw new Error('Sesión no encontrada');
        }
        
        // NUEVO: Verificar múltiples variantes del token
        const pToken = session.ptoken || session.pToken || session.patient_token || session.chat_token || session.token;
        
        if (!pToken || pToken.trim() === '') {
            throw new Error('Esta sesión no tiene pToken para chat. Intenta generar uno.');
        }
        
        console.log('🔑 Usando pToken:', pToken.substring(0, 15) + '...');
        
        currentSession = session;
        
        // Mostrar UI del chat
        document.getElementById('chatSection').classList.remove('hidden');
        updateChatHeader(session);
        
        // Conectar WebSocket usando el pToken encontrado
        await connectChatWebSocket(pToken, session.id);
        
        console.log('✅ Chat abierto exitosamente');
        
    } catch (error) {
        console.error('❌ Error abriendo chat:', error);
        showError('Error: ' + error.message);
    }
}

// 5. NUEVA FUNCIÓN: Verificar estructura de datos del backend
async function debugBackendResponse() {
    try {
        console.log('🔍 Verificando respuesta del backend...');
        
        const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions?debug=true`, {
            method: 'GET',
            headers: getAuthHeaders()
        });
        
        const result = await response.json();
        console.log('🔍 Estructura completa de respuesta:', result);
        
        if (result.data && result.data.sessions && result.data.sessions.length > 0) {
            const firstSession = result.data.sessions[0];
            console.log('🔍 Primera sesión - todas las propiedades:');
            Object.keys(firstSession).forEach(key => {
                console.log(`  ${key}:`, firstSession[key]);
            });
        }
        
    } catch (error) {
        console.error('❌ Error en debug:', error);
    }
}

// 6. AGREGAR AL FINAL DEL SCRIPT PARA DEBUGGING AUTOMÁTICO
document.addEventListener('DOMContentLoaded', () => {
    // ... código existente ...
    
    // NUEVO: Agregar función de debug
    window.debugBackendResponse = debugBackendResponse;
    window.requestPToken = requestPToken;
    
    console.log('🛠️ Funciones de debug disponibles:');
    console.log('- debugBackendResponse(): Verificar estructura de datos del backend');
    console.log('- requestPToken(sessionId): Generar token para una sesión');
});
    </script>
</body>
</html>