<?php
// public/staff.php - ACTUALIZADO CON PANEL DE AGENTE
session_start();

// VERIFICACIÓN SIMPLE Y DIRECTA
if (!isset($_SESSION['pToken']) || empty($_SESSION['pToken'])) {
    debugLog("No hay token en sesión, redirigiendo a login", null, 'WARN');
    header("Location: /practicas/chat-frontend/public/index.php?error=no_session");
    exit;
}

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    debugLog("No hay user en sesión, redirigiendo a login", null, 'WARN');
    header("Location: /practicas/chat-frontend/public/index.php?error=no_user");
    exit;
}

// Obtener datos del usuario
$user = json_decode($_SESSION['user'], true);
if (!$user) {
    debugLog("Datos de usuario inválidos", null, 'ERROR');
    header("Location: /practicas/chat-frontend/public/index.php?error=invalid_user");
    exit;
}

// Verificar que sea staff
$userRole = $user['role']['name'] ?? $user['role'] ?? 'agent';
if (is_numeric($userRole)) {
    $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
    $userRole = $roleMap[$userRole] ?? 'agent';
}

$validStaffRoles = ['agent', 'supervisor', 'admin'];
if (!in_array($userRole, $validStaffRoles)) {
    debugLog("Usuario no es staff: " . $userRole, null, 'ERROR');
    header("Location: /practicas/chat-frontend/public/index.php?error=not_staff");
    exit;
}

debugLog("Staff autenticado: " . $user['name'] . " (" . $userRole . ")");

// Incluir función de debug si existe
function debugLog($message, $data = null, $level = 'INFO') {
    if (defined('APP_ENV') && APP_ENV === 'development') {
        error_log("[STAFF-DEBUG] [{$level}] {$message}" . ($data ? " " . json_encode($data) : ""));
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Médico - <?= htmlspecialchars($user['name'] ?? 'Staff') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nav-link.active { background: #2563eb; color: white; }
        
        /* Estilos específicos para el panel de agente */
        .countdown-urgent { animation: pulse 2s infinite; }
        .chat-container { height: calc(100vh - 200px); }
        .messages-container { height: calc(100% - 120px); }
        .message-input-area { height: 80px; }
        
        .session-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .session-waiting { border-left-color: #f59e0b; }
        .session-active { border-left-color: #10b981; }
        .session-urgent { border-left-color: #ef4444; animation: pulse 2s infinite; }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .status-waiting { background-color: #f59e0b; }
        .status-active { background-color: #10b981; }
        .status-urgent { background-color: #ef4444; }
        
        .message-bubble {
            max-width: 75%;
            word-wrap: break-word;
        }
        
        .message-user {
            align-self: flex-end;
        }
        
        .message-agent {
            align-self: flex-start;
        }
        
        .typing-indicator {
            display: flex;
            align-items: center;
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
        .typing-dot:nth-child(3) { animation-delay: 0s; }
        
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
            
            <!-- Logo -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Panel Médico</h1>
                        <p class="text-sm text-gray-500">v3.1</p>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="flex-1 p-4">
                <div class="space-y-1">
                    <a href="#dashboard" onclick="showSection('dashboard')" 
                       class="nav-link active flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2v0a2 2 0 012-2h6l2 2h6a2 2 0 012 2v1" />
                        </svg>
                        Dashboard
                    </a>
                    
                    <a href="#chats" onclick="showSection('chats')" 
                       class="nav-link text-gray-600 hover:bg-gray-100 hover:text-gray-900 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                        </svg>
                        <?php if ($userRole === 'agent'): ?>
                            Chat de Agente
                        <?php else: ?>
                            Chats
                        <?php endif; ?>
                        <span id="chatsBadge" class="ml-auto bg-green-500 text-white text-xs rounded-full px-2 py-1 hidden">0</span>
                    </a>
                    
                    <?php if (in_array($userRole, ['supervisor', 'admin'])): ?>
                    <a href="#supervision" onclick="showSection('supervision')" 
                       class="nav-link text-gray-600 hover:bg-gray-100 hover:text-gray-900 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Supervisión
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'admin'): ?>
                    <a href="#admin" onclick="showSection('admin')" 
                       class="nav-link text-gray-600 hover:bg-gray-100 hover:text-gray-900 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Admin
                    </a>
                    <?php endif; ?>

                    <a href="#rooms" onclick="showSection('rooms')" 
                       class="nav-link text-gray-600 hover:bg-gray-100 hover:text-gray-900 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4" />
                        </svg>
                        Salas
                    </a>
                </div>
            </nav>
                
            <!-- User Profile -->
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></p>
                        <p class="text-sm text-gray-500 capitalize"><?= htmlspecialchars($userRole) ?></p>
                    </div>
                    <button onclick="confirmLogout()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg" title="Cerrar sesión">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            
            <!-- Top Header -->
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

            <!-- Page Content -->
            <main class="flex-1 overflow-auto">
                
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="section-content p-6">
                    
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Chats Activos</p>
                                    <p id="activeChatsCount" class="text-2xl font-bold text-gray-900">5</p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">En Cola</p>
                                    <p id="queueCount" class="text-2xl font-bold text-gray-900">3</p>
                                </div>
                                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Tiempo Promedio</p>
                                    <p id="avgTime" class="text-2xl font-bold text-gray-900">12 min</p>
                                </div>
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Completados Hoy</p>
                                    <p id="completedToday" class="text-2xl font-bold text-gray-900">28</p>
                                </div>
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">Actividad Reciente</h3>
                                <button onclick="refreshDashboard()" class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded text-gray-600 transition-colors">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <div id="recentActivity" class="space-y-3">
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path></svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900">Chat iniciado con paciente</p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($user['name']) ?> • hace 2 min</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900">Consulta finalizada</p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($user['name']) ?> • hace 5 min</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chats Section - PANEL DE AGENTE INTEGRADO -->
                <div id="chats-section" class="section-content hidden h-full">
                    <?php if ($userRole === 'agent'): ?>
                        <!-- Panel de Agente -->
                        <div class="flex h-full">
                            
                            <!-- Panel Izquierdo - Lista de Sesiones -->
                            <div class="w-1/3 bg-white border-r">
                                
                                <!-- Stats Rápidas -->
                                <div class="p-4 border-b bg-gray-50">
                                    <div class="grid grid-cols-3 gap-4 text-center">
                                        <div>
                                            <div class="text-lg font-bold text-blue-600" id="waitingCount">0</div>
                                            <div class="text-xs text-gray-500">En Cola</div>
                                        </div>
                                        <div>
                                            <div class="text-lg font-bold text-green-600" id="activeCount">0</div>
                                            <div class="text-xs text-gray-500">Activos</div>
                                        </div>
                                        <div>
                                            <div class="text-lg font-bold text-red-600" id="urgentCount">0</div>
                                            <div class="text-xs text-gray-500">Urgentes</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filtros -->
                                <div class="p-4 border-b">
                                    <div class="flex space-x-2">
                                        <button onclick="filterSessions('all')" class="filter-btn active px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded-full">
                                            Todos
                                        </button>
                                        <button onclick="filterSessions('waiting')" class="filter-btn px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-full">
                                            En Cola
                                        </button>
                                        <button onclick="filterSessions('active')" class="filter-btn px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-full">
                                            Mis Chats
                                        </button>
                                    </div>
                                </div>

                                <!-- Lista de Sesiones -->
                                <div class="overflow-y-auto" style="height: calc(100vh - 290px);">
                                    <div id="sessionsList" class="p-4 space-y-3">
                                        <div class="text-center py-8">
                                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                            <p class="text-gray-500">Cargando sesiones...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Panel Derecho - Chat Activo -->
                            <div class="flex-1 flex flex-col">
                                
                                <!-- Estado Sin Chat Seleccionado -->
                                <div id="noChatSelected" class="flex-1 flex items-center justify-center bg-gray-50">
                                    <div class="text-center">
                                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                                        </svg>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">No hay chat seleccionado</h3>
                                        <p class="text-gray-600">Selecciona una sesión de la lista para comenzar</p>
                                    </div>
                                </div>

                                <!-- Área de Chat Activo -->
                                <div id="activeChatArea" class="hidden flex-1 flex flex-col">
                                    
                                    <!-- Header del Chat -->
                                    <div class="bg-white border-b px-6 py-4">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-4">
                                                <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                                    <span id="patientInitial" class="font-semibold text-gray-700">P</span>
                                                </div>
                                                <div>
                                                    <h3 id="patientName" class="font-semibold text-gray-900">Paciente</h3>
                                                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                                                        <span id="sessionRoom">Sala General</span>
                                                        <span>•</span>
                                                        <span id="sessionDuration">0 min</span>
                                                        <span>•</span>
                                                        <div class="flex items-center space-x-1">
                                                            <div class="status-dot" id="sessionStatusDot"></div>
                                                            <span id="sessionStatus">Activo</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center space-x-2">
                                                <!-- Countdown Timer -->
                                                <div id="sessionTimer" class="px-3 py-1 bg-gray-100 rounded-full text-sm font-medium">
                                                    <span id="timerText">--:--</span>
                                                </div>
                                                
                                                <!-- Acciones -->
                                                <button onclick="transferSession()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg" title="Transferir">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                                    </svg>
                                                </button>
                                                
                                                <button onclick="endSession()" class="p-2 text-red-400 hover:text-red-600 rounded-lg" title="Finalizar">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Área de Mensajes -->
                                    <div id="messagesContainer" class="flex-1 overflow-y-auto p-4 bg-gray-50 messages-container">
                                        <div id="messagesList" class="space-y-3">
                                            <!-- Los mensajes aparecerán aquí -->
                                        </div>
                                        
                                        <!-- Indicador de escritura -->
                                        <div id="typingIndicator" class="hidden flex items-center space-x-2 mt-4">
                                            <div class="text-sm text-gray-500">El paciente está escribiendo</div>
                                            <div class="typing-indicator">
                                                <div class="typing-dot"></div>
                                                <div class="typing-dot"></div>
                                                <div class="typing-dot"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Área de Input -->
                                    <div class="bg-white border-t p-4 message-input-area">
                                        <div class="flex space-x-3">
                                            <div class="flex-1">
                                                <textarea 
                                                    id="messageInput" 
                                                    placeholder="Escribe tu mensaje..."
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                                    rows="2"
                                                    maxlength="500"
                                                ></textarea>
                                                <div class="flex justify-between items-center mt-1 text-xs text-gray-500">
                                                    <span>Shift + Enter para nueva línea</span>
                                                    <span><span id="charCount">0</span>/500</span>
                                                </div>
                                            </div>
                                            <button 
                                                id="sendButton" 
                                                onclick="sendMessage()" 
                                                disabled
                                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed self-start"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Vista para supervisores y admins -->
                        <div class="p-6">
                            <div class="bg-white rounded-lg shadow">
                                <div class="px-6 py-4 border-b border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-semibold text-gray-900">Gestión de Chats</h3>
                                        <button onclick="refreshChats()" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded transition-colors">
                                            Actualizar
                                        </button>
                                    </div>
                                </div>
                                <div class="p-6">
                                    <div id="activeChats" class="space-y-4">
                                        <div class="text-center py-8">
                                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                                            </svg>
                                            <h3 class="font-medium text-gray-900 mb-2">Vista de supervisión en desarrollo</h3>
                                            <p class="text-gray-600">Funcionalidades específicas para <?= $userRole ?> próximamente</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Rooms Section -->
                <div id="rooms-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">Gestión de Salas</h3>
                                <button onclick="refreshRooms()" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded transition-colors">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <div id="roomsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div class="col-span-full text-center py-8">
                                    <p class="text-gray-500">Cargando salas...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supervision Section -->
                <?php if (in_array($userRole, ['supervisor', 'admin'])): ?>
                <div id="supervision-section" class="section-content hidden p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="font-semibold text-gray-900 mb-4">Rendimiento de Agentes</h3>
                            <div class="space-y-3">
                                <p class="text-gray-600">Datos de rendimiento aparecerán aquí</p>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="font-semibold text-gray-900 mb-4">Estadísticas del Sistema</h3>
                            <div class="space-y-3">
                                <p class="text-gray-600">Estadísticas aparecerán aquí</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Admin Section -->
                <?php if ($userRole === 'admin'): ?>
                <div id="admin-section" class="section-content hidden p-6">
                    <div class="space-y-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="font-semibold text-gray-900 mb-4">Acciones de Administrador</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <button onclick="showNotification('Crear usuario - En desarrollo', 'info')" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="font-medium text-gray-900">Crear Usuario</h4>
                                    <p class="text-sm text-gray-600">Agregar personal</p>
                                </button>
                                
                                <button onclick="showNotification('Configuración - En desarrollo', 'info')" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="font-medium text-gray-900">Configuración</h4>
                                    <p class="text-sm text-gray-600">Ajustes sistema</p>
                                </button>
                                
                                <button onclick="showNotification('Reportes - En desarrollo', 'info')" class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 text-center">
                                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="font-medium text-gray-900">Reportes</h4>
                                    <p class="text-sm text-gray-600">Generar informes</p>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal de Transferencia -->
    <div id="transferModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Transferir Conversación</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sala de destino</label>
                    <select id="transferRoom" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="general">General</option>
                        <option value="medical">Médica</option>
                        <option value="support">Soporte</option>
                        <option value="emergency">Emergencias</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo (opcional)</label>
                    <textarea id="transferReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3" placeholder="Describe el motivo de la transferencia..."></textarea>
                </div>
                <div class="flex space-x-3">
                    <button onclick="closeTransferModal()" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancelar
                    </button>
                    <button onclick="confirmTransfer()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Transferir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    
    <script>
        // Configuration
        const CONFIG = {
            USER_ROLE: '<?= $userRole ?>',
            USER_DATA: <?= json_encode($user) ?>,
            CHAT_SERVICE_URL: 'http://187.33.158.246:8080/chats',
            WS_URL: 'ws://187.33.158.246:8080',
            REFRESH_INTERVAL: 30000, // 30 segundos
            TIMER_WARNING: 300, // 5 minutos en segundos
            TIMER_URGENT: 60 // 1 minuto en segundos
        };

        // Estado global
        let currentSessionId = null;
        let sessions = [];
        let messages = [];
        let refreshInterval = null;
        let timerInterval = null;
        let socket = null;
        let currentFilter = 'all';

        // Navigation
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            
            // Show selected section
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.remove('hidden');
            }
            
            // Update nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                link.classList.add('text-gray-600', 'hover:bg-gray-100', 'hover:text-gray-900');
            });
            
            // Highlight active nav
            if (event && event.target) {
                event.target.classList.remove('text-gray-600', 'hover:bg-gray-100', 'hover:text-gray-900');
                event.target.classList.add('active');
            }
            
            // Update section title
            const titles = {
                'dashboard': 'Dashboard',
                'chats': CONFIG.USER_ROLE === 'agent' ? 'Chat de Agente' : 'Gestión de Chats',
                'rooms': 'Gestión de Salas',
                'supervision': 'Supervisión',
                'admin': 'Administración'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName] || 'Panel';
            
            // Inicializar funcionalidades específicas de agente
            if (sectionName === 'chats' && CONFIG.USER_ROLE === 'agent') {
                initializeAgentChat();
            }
        }
        
        // Inicializar chat de agente
        async function initializeAgentChat() {
            console.log('🚀 Inicializando panel de agente...');
            
            try {
                await initializeWebSocket();
                await loadSessions();
                setupEventListeners();
                startAutoRefresh();
                
                console.log('✅ Panel de agente listo');
            } catch (error) {
                console.error('❌ Error inicializando agente:', error);
                showNotification('Error de inicialización', 'error');
            }
        }

        // Inicializar WebSocket
        async function initializeWebSocket() {
            try {
                const token = localStorage.getItem('pToken');
                if (!token) return;
                
                console.log('🔌 Conectando WebSocket...');
                
                socket = io(CONFIG.WS_URL, {
                    auth: { token: token },
                    transports: ['websocket', 'polling']
                });
                
                socket.on('connect', () => {
                    console.log('✅ WebSocket conectado');
                });
                
                socket.on('message_received', (data) => {
                    handleNewMessage(data);
                });
                
                socket.on('session_updated', (data) => {
                    handleSessionUpdate(data);
                });
                
                socket.on('disconnect', () => {
                    console.log('🔌 WebSocket desconectado');
                });
                
            } catch (error) {
                console.error('❌ Error WebSocket:', error);
            }
        }

        // Cargar sesiones
        async function loadSessions() {
            try {
                console.log('📡 Cargando sesiones...');
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions?limit=50`, {
                    headers: getAuthHeaders()
                });
                
                if (!response.ok) throw new Error('Error cargando sesiones');
                
                const result = await response.json();
                sessions = result.data?.sessions || [];
                
                console.log(`📋 ${sessions.length} sesiones cargadas`);
                updateSessionsList();
                updateStats();
                
            } catch (error) {
                console.error('❌ Error cargando sesiones:', error);
                showNotification('Error cargando sesiones', 'error');
            }
        }

        // Resto de funciones del agente (igual que en el artifact anterior)
        // ... [incluir todas las funciones del panel de agente]

        // Update time
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('es-ES');
            }
        }
        
        // Refresh functions
        function refreshDashboard() {
            showNotification('Dashboard actualizado', 'success', 2000);
        }
        
        function refreshChats() {
            if (CONFIG.USER_ROLE === 'agent') {
                loadSessions();
            }
            showNotification('Chats actualizados', 'success', 2000);
        }
        
        function refreshRooms() {
            showNotification('Salas actualizadas', 'success', 2000);
        }
        
        // Logout
        function confirmLogout() {
            if (confirm('¿Cerrar sesión?')) {
                // Limpiar localStorage
                localStorage.clear();
                sessionStorage.clear();
                
                // Desconectar WebSocket si existe
                if (socket) {
                    socket.disconnect();
                }
                
                // Ir a logout
                window.location.href = '/practicas/chat-frontend/public/logout.php';
            }
        }
        
        // Notification system
        function showNotification(message, type = 'info', duration = 4000) {
            const notification = document.createElement('div');
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm text-white ${colors[type]}`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 hover:opacity-75">×</button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
        }

        // Utilidades
        function getAuthHeaders() {
            const token = localStorage.getItem('pToken');
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`
            };
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            console.log('✅ Panel staff cargado exitosamente');
            console.log('👤 Usuario:', CONFIG.USER_DATA.name);
            console.log('🔑 Rol:', CONFIG.USER_ROLE);
            
            // Update time every second
            updateTime();
            setInterval(updateTime, 1000);
            
            // Show success message
            showNotification(`¡Bienvenido ${CONFIG.USER_DATA.name}!`, 'success');
        });

        // FUNCIONES ESPECÍFICAS DEL AGENTE (solo para rol agent)
        
        // Actualizar lista de sesiones
        function updateSessionsList() {
            if (CONFIG.USER_ROLE !== 'agent') return;
            
            const container = document.getElementById('sessionsList');
            if (!container) return;
            
            // Filtrar sesiones según el filtro actual
            let filteredSessions = sessions;
            
            if (currentFilter === 'waiting') {
                filteredSessions = sessions.filter(s => s.status === 'waiting');
            } else if (currentFilter === 'active') {
                filteredSessions = sessions.filter(s => s.agent_id === CONFIG.USER_DATA.id);
            }
            
            if (filteredSessions.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                        <p class="text-gray-500">${getEmptyMessage()}</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = filteredSessions.map(session => createSessionCard(session)).join('');
        }

        // Crear tarjeta de sesión
        function createSessionCard(session) {
            const waitTime = getWaitTime(session.created_at);
            const isUrgent = waitTime > 10; // Más de 10 minutos
            const isMyChat = session.agent_id === CONFIG.USER_DATA.id;
            
            let statusClass = 'session-waiting';
            let statusDotClass = 'status-waiting';
            
            if (session.status === 'active') {
                statusClass = isMyChat ? 'session-active' : 'session-waiting';
                statusDotClass = isMyChat ? 'status-active' : 'status-waiting';
            }
            
            if (isUrgent && session.status === 'waiting') {
                statusClass = 'session-urgent';
                statusDotClass = 'status-urgent';
            }
            
            return `
                <div class="session-card ${statusClass} bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:shadow-md transition-all"
                     onclick="selectSession('${session.id}')">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                <span class="text-sm font-semibold text-gray-700">P</span>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">Paciente #${session.id.substring(0, 8)}</div>
                                <div class="text-sm text-gray-500">${session.room_id}</div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="status-dot ${statusDotClass}"></div>
                            <span class="text-xs text-gray-500">${formatStatus(session.status)}</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">
                            ${session.status === 'waiting' ? `Esperando ${waitTime} min` : `Activo ${waitTime} min`}
                        </span>
                        
                        ${session.status === 'waiting' ? 
                            `<button onclick="event.stopPropagation(); assignToMe('${session.id}')" 
                                    class="px-3 py-1 bg-blue-600 text-white text-xs rounded-full hover:bg-blue-700">
                                Tomar
                            </button>` : 
                            (isMyChat ? `<span class="text-xs text-green-600 font-medium">Mi chat</span>` : `<span class="text-xs text-gray-500">Ocupado</span>`)
                        }
                    </div>
                    
                    ${isUrgent && session.status === 'waiting' ? 
                        `<div class="mt-2 px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full text-center font-medium">
                            ⚠️ Urgente - Más de ${waitTime} min esperando
                        </div>` : ''
                    }
                </div>
            `;
        }

        // Asignar sesión al agente actual
        async function assignToMe(sessionId) {
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
                
                if (!response.ok) throw new Error('Error asignando sesión');
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Sesión asignada exitosamente', 'success');
                    await loadSessions();
                    await selectSession(sessionId);
                } else {
                    throw new Error(result.message || 'Error asignando sesión');
                }
                
            } catch (error) {
                console.error('❌ Error asignando sesión:', error);
                showNotification('Error: ' + error.message, 'error');
            }
        }

        // Más funciones del agente...
        function selectSession(sessionId) {
            // Implementar selección de sesión
            console.log('Seleccionando sesión:', sessionId);
            // TODO: Implementar resto de la lógica
        }

        function filterSessions(filter) {
            currentFilter = filter;
            
            // Actualizar botones de filtro
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-blue-100', 'text-blue-700');
                btn.classList.add('bg-gray-100', 'text-gray-700');
            });
            
            if (event && event.target) {
                event.target.classList.remove('bg-gray-100', 'text-gray-700');
                event.target.classList.add('active', 'bg-blue-100', 'text-blue-700');
            }
            
            updateSessionsList();
        }

        function updateStats() {
            if (CONFIG.USER_ROLE !== 'agent') return;
            
            const waiting = sessions.filter(s => s.status === 'waiting').length;
            const active = sessions.filter(s => s.agent_id === CONFIG.USER_DATA.id && s.status === 'active').length;
            const urgent = sessions.filter(s => s.status === 'waiting' && getWaitTime(s.created_at) > 10).length;
            
            const waitingEl = document.getElementById('waitingCount');
            const activeEl = document.getElementById('activeCount');
            const urgentEl = document.getElementById('urgentCount');
            
            if (waitingEl) waitingEl.textContent = waiting;
            if (activeEl) activeEl.textContent = active;
            if (urgentEl) urgentEl.textContent = urgent;
        }

        function setupEventListeners() {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', updateCharCount);
                messageInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }
        }

        function updateCharCount() {
            const input = document.getElementById('messageInput');
            const sendButton = document.getElementById('sendButton');
            const charCountEl = document.getElementById('charCount');
            
            if (input && sendButton && charCountEl) {
                const count = input.value.length;
                charCountEl.textContent = count;
                sendButton.disabled = count === 0;
            }
        }

        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            
            refreshInterval = setInterval(() => {
                if (CONFIG.USER_ROLE === 'agent') {
                    loadSessions();
                }
            }, CONFIG.REFRESH_INTERVAL);
        }

        function sendMessage() {
            // TODO: Implementar envío de mensajes
            console.log('Enviando mensaje...');
        }

        function transferSession() {
            if (!currentSessionId) return;
            document.getElementById('transferModal').classList.remove('hidden');
        }

        function closeTransferModal() {
            document.getElementById('transferModal').classList.add('hidden');
        }

        function confirmTransfer() {
            console.log('Confirmando transferencia...');
            closeTransferModal();
        }

        function endSession() {
            console.log('Finalizando sesión...');
        }

        function handleNewMessage(data) {
            console.log('Nuevo mensaje:', data);
        }

        function handleSessionUpdate(data) {
            console.log('Sesión actualizada:', data);
        }

        // Utilidades
        function getWaitTime(timestamp) {
            const now = new Date();
            const start = new Date(timestamp);
            return Math.floor((now - start) / 60000); // minutos
        }

        function formatStatus(status) {
            const statusMap = {
                'waiting': 'Esperando',
                'active': 'Activo',
                'transferred': 'Transferido',
                'completed': 'Completado'
            };
            return statusMap[status] || status;
        }

        function getEmptyMessage() {
            const messages = {
                'all': 'No hay sesiones disponibles',
                'waiting': 'No hay pacientes en cola',
                'active': 'No tienes chats activos'
            };
            return messages[currentFilter] || 'No hay datos';
        }

        // Limpiar intervalos al cerrar
        window.addEventListener('beforeunload', () => {
            if (refreshInterval) clearInterval(refreshInterval);
            if (timerInterval) clearInterval(timerInterval);
            if (socket) socket.disconnect();
        });
    </script>
</body>
</html>