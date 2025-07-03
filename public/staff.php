<?php
// public/staff.php - PANEL INTEGRADO CON CHAT COMPLETO
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
        
        /* Estilos del chat integrado - MISMOS QUE PREAUTH.PHP */
        .chat-fullscreen { position: fixed; inset: 0; z-index: 50; display: flex; flex-direction: column; background: white; }
        .chat-header { background: white; border-bottom: 1px solid #e5e7eb; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; min-height: 70px; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; background: #f8fafc; display: flex; flex-direction: column; gap: 1rem; }
        .chat-input-area { background: white; border-top: 1px solid #e5e7eb; padding: 1.5rem; }
        .message { display: flex; gap: 0.75rem; max-width: 80%; }
        .message-system { align-self: flex-start; }
        .message-user { align-self: flex-end; flex-direction: row-reverse; }
        .message-content { background: #e5e7eb; border: 1px solid #d1d5db; border-radius: 1rem; padding: 0.75rem 1rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); position: relative; word-wrap: break-word; }
        .message-user .message-content { background: var(--primary); color: white; border-color: var(--primary); }
        .message-time { font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem; }
        .message-user .message-time { color: rgba(255, 255, 255, 0.7); }
        
        /* Chat input mejorado */
        .chat-input { width: 100%; min-height: 44px; max-height: 120px; padding: 0.75rem 60px 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 1.5rem; font-size: 14px; resize: none; background: #f9fafb; transition: all 0.15s ease-in-out; }
        .chat-input:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgb(3 114 185 / 0.1); }
        .chat-input-container { position: relative; }
        .chat-input-actions { position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); display: flex; align-items: center; gap: 0.25rem; }
        .chat-input-btn { width: 36px; height: 36px; border-radius: 50%; border: none; background: transparent; color: #9ca3af; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s ease-in-out; }
        .chat-input-btn:hover { background: #f3f4f6; color: #6b7280; }
        .chat-input-btn.btn-send { background: var(--primary); color: white; }
        .chat-input-btn.btn-send:hover { background: #0369a1; transform: scale(1.05); }
        .chat-input-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        
        /* Variables CSS */
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
        }
        
        /* Session cards con info de paciente */
        .session-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .session-waiting { border-left-color: #f59e0b; }
        .session-active { border-left-color: #10b981; }
        .session-urgent { border-left-color: #ef4444; animation: pulse 2s infinite; }
        .session-mine { border-left-color: #3b82f6; }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .status-waiting { background-color: #f59e0b; }
        .status-active { background-color: #10b981; }
        .status-urgent { background-color: #ef4444; }
        .status-mine { background-color: #3b82f6; }
        
        /* Timer styles */
        .timer-normal { color: #6b7280; }
        .timer-warning { color: #f59e0b; font-weight: 600; }
        .timer-urgent { color: #ef4444; font-weight: 700; animation: pulse 2s infinite; }
        
        /* Typing indicator */
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

        /* Panel de información del paciente */
        .patient-info-panel {
            min-width: 320px;
            max-width: 320px;
            height: 100%;
            overflow-y: auto;
        }

        .patient-info-section {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: white;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
        }

        .patient-info-header {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .patient-info-content {
            padding: 16px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 500;
            color: #6b7280;
            min-width: 80px;
            flex-shrink: 0;
        }

        .info-value {
            color: #111827;
            text-align: right;
            word-break: break-word;
            max-width: 60%;
        }

        /* Responsive adjustments */
        @media (max-width: 1400px) {
            .patient-info-panel {
                min-width: 280px;
                max-width: 280px;
            }
        }

        @media (max-width: 1200px) {
            .patient-info-panel {
                min-width: 260px;
                max-width: 260px;
            }
            
            .info-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .info-value {
                text-align: left;
                max-width: 100%;
                margin-top: 2px;
            }
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
                        <p class="text-sm text-gray-500">v3.2</p>
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
                        Chat de Agente
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

                <!-- Chats Section - PANEL DE AGENTE COMPLETO -->
                <div id="chats-section" class="section-content hidden h-full">
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
                                        <div class="text-lg font-bold text-green-600" id="myActiveCount">0</div>
                                        <div class="text-xs text-gray-500">Mis Chats</div>
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
                                    <button onclick="filterSessions('mine')" class="filter-btn px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-full">
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

                            <!-- Área de Chat Activo - DISEÑO CON PANEL DE INFORMACIÓN -->
                            <div id="activeChatArea" class="hidden flex-1 flex">
                                
                                <!-- Panel Principal del Chat (2/3) -->
                                <div class="flex-1 flex flex-col border-r border-gray-200">
                                    
                                    <!-- Header del Chat con info básica -->
                                    <div class="chat-header">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-4">
                                                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                                        <span id="patientHeaderInitials" class="text-lg font-bold text-blue-700">P</span>
                                                    </div>
                                                    <div>
                                                        <div class="font-semibold text-lg text-gray-900" id="patientHeaderName">-</div>
                                                        <div class="text-sm text-gray-500" id="roomHeaderName">-</div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center space-x-4">
                                                <!-- Timer de expiración -->
                                                <div class="text-center">
                                                    <div class="text-xs text-gray-500">Expira en:</div>
                                                    <div id="sessionTimer" class="timer-normal text-sm font-mono">
                                                        <span id="timerText">--:--</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Status -->
                                                <div class="text-center">
                                                    <div class="text-xs text-gray-500">Estado:</div>
                                                    <div class="flex items-center space-x-1">
                                                        <div class="status-dot" id="sessionStatusDot"></div>
                                                        <span id="sessionStatus" class="text-sm font-medium">-</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Acciones -->
                                                <div class="flex items-center space-x-2">
                                                    <button onclick="transferSession()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Transferir">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                                        </svg>
                                                    </button>
                                                    
                                                    <button onclick="endSession()" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-100 rounded-lg transition-colors" title="Finalizar">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                    
                                                    <button onclick="closeChat()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Cerrar">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Chat Messages -->
                                    <div class="chat-messages" id="chatMessages">
                                        <!-- Los mensajes aparecerán aquí -->
                                    </div>

                                    <!-- Indicador de escritura -->
                                    <div id="typingIndicator" class="hidden flex items-center space-x-2 px-6 py-2 bg-gray-50">
                                        <span class="text-sm text-gray-500">El paciente está escribiendo</span>
                                        <div class="typing-indicator">
                                            <div class="typing-dot"></div>
                                            <div class="typing-dot"></div>
                                            <div class="typing-dot"></div>
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

                                        <!-- Contador de caracteres y file upload -->
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

                                <!-- Panel de Información del Paciente -->
                                <div class="patient-info-panel bg-gray-50">
                                    
                                    <!-- Header del Panel -->
                                    <div class="bg-white border-b border-gray-200 px-4 py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold text-gray-900">Información del Paciente</h3>
                                                <p class="text-sm text-gray-500">Datos de membresía</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Contenido del Panel -->
                                    <div class="p-4 space-y-4">
                                        
                                        <!-- Información Personal -->
                                        <div class="patient-info-section">
                                            <div class="patient-info-header">
                                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                                                </svg>
                                                <span class="font-semibold text-gray-800">Datos Personales</span>
                                            </div>
                                            <div class="patient-info-content">
                                                <div class="info-row">
                                                    <span class="info-label">Nombre:</span>
                                                    <span id="patientFullName" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Documento:</span>
                                                    <span id="patientDocument" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Tipo:</span>
                                                    <span id="patientDocType" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Fecha Nac.:</span>
                                                    <span id="patientBirthDate" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Género:</span>
                                                    <span id="patientGender" class="info-value">-</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Información de Contacto -->
                                        <div class="patient-info-section">
                                            <div class="patient-info-header">
                                                <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                                <span class="font-semibold text-gray-800">Contacto</span>
                                            </div>
                                            <div class="patient-info-content">
                                                <div class="info-row">
                                                    <span class="info-label">Teléfono:</span>
                                                    <span id="patientPhone" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Email:</span>
                                                    <span id="patientEmail" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Ciudad:</span>
                                                    <span id="patientCity" class="info-value">-</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Información de Membresía -->
                                        <div class="patient-info-section">
                                            <div class="patient-info-header">
                                                <svg class="w-4 h-4 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                <span class="font-semibold text-gray-800">Membresía</span>
                                            </div>
                                            <div class="patient-info-content">
                                                <div class="info-row">
                                                    <span class="info-label">EPS:</span>
                                                    <span id="patientEPS" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Plan:</span>
                                                    <span id="patientPlan" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Estado:</span>
                                                    <span id="patientStatus" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Vigencia:</span>
                                                    <span id="patientVigencia" class="info-value">-</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Información del Tomador -->
                                        <div class="patient-info-section">
                                            <div class="patient-info-header">
                                                <svg class="w-4 h-4 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                                <span class="font-semibold text-gray-800">Tomador</span>
                                            </div>
                                            <div class="patient-info-content">
                                                <div class="info-row">
                                                    <span class="info-label">Nombre:</span>
                                                    <span id="tomadorName" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Documento:</span>
                                                    <span id="tomadorDocument" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Empresa:</span>
                                                    <span id="tomadorEmpresa" class="info-value">-</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Información de Sesión -->
                                        <div class="patient-info-section">
                                            <div class="patient-info-header">
                                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span class="font-semibold text-gray-800">Sesión</span>
                                            </div>
                                            <div class="patient-info-content">
                                                <div class="info-row">
                                                    <span class="info-label">Sala:</span>
                                                    <span id="sessionRoomName" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Inicio:</span>
                                                    <span id="sessionStartTime" class="info-value">-</span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">Duración:</span>
                                                    <span id="sessionDuration" class="info-value">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Otras secciones... -->
                <div id="rooms-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="font-semibold text-gray-900">Gestión de Salas</h3>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600">Funcionalidad de gestión de salas en desarrollo</p>
                        </div>
                    </div>
                </div>
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
    <script src="assets/js/auth-client.js"></script>
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <script src="assets/js/chat-client.js"></script>
    
    <script>
        /*
        ====================================================================
        🎯 SISTEMA DE CHAT REAL INTEGRADO PARA AGENTES
        ====================================================================
        
        Este sistema ahora usa APIs reales del backend:
        
        ✅ CONEXIONES REALES:
        - loadSessions() → GET /chats/sessions (sesiones reales de BD)
        - assignToMe() → PUT /chats/sessions/{id}/assign (asignar sesión real)
        - loadSessionMessages() → GET /chats/messages/{sessionId} (historial real)
        - sendMessage() → WebSocket send_message (mensajes en tiempo real)
        - handleFileUpload() → POST /chats/files/upload (archivos reales)
        - endSession() → PUT /chats/sessions/{id}/end (finalizar sesión real)
        - confirmTransfer() → PUT /chats/sessions/{id}/transfer (transferir real)
        
        ✅ DATOS REALES:
        - Nombres de pacientes extraídos de user_data/patient_data
        - Tiempos de expiración calculados desde created_at
        - Mensajes del historial completo cargados al abrir chat
        - Estados y prioridades basados en datos reales
        
        ✅ WEBSOCKET EN TIEMPO REAL:
        - message_received: Mensajes del paciente en tiempo real
        - file_uploaded: Archivos del paciente en tiempo real  
        - user_typing: Indicadores de escritura
        - session_updated: Actualizaciones de estado
        
        ✅ FUNCIONALIDADES COMPLETAS:
        - Chat completo con UI idéntica al cliente
        - Timer de expiración en tiempo real
        - Asignación automática de sesiones
        - Historial completo al abrir chat
        - Envío de mensajes y archivos como agente
        - Transferencias y finalizaciones reales
        */
        // Configuration
        const CONFIG = {
            USER_ROLE: '<?= $userRole ?>',
            USER_DATA: <?= json_encode($user) ?>,
            CHAT_SERVICE_URL: 'http://187.33.158.246:8080/chats',
            WS_URL: 'ws://187.33.158.246:8080',
            REFRESH_INTERVAL: 15000, // 15 segundos
            SESSION_TIMEOUT: 30 * 60, // 30 minutos en segundos
            TIMER_WARNING: 5 * 60, // 5 minutos
            TIMER_URGENT: 2 * 60 // 2 minutos
        };

        // Estado global
        let currentSessionId = null;
        let sessions = [];
        let refreshInterval = null;
        let timerInterval = null;
        let currentFilter = 'all';
        let currentAgentSession = null;

        // === UTILIDADES DE AUTENTICACIÓN ===
        function getToken() {
            const phpToken = '<?= $_SESSION["pToken"] ?? "" ?>';
            const localToken = localStorage.getItem('pToken');
            const token = phpToken || localToken;
            
            // Sincronizar tokens si es necesario
            if (phpToken && !localToken) {
                localStorage.setItem('pToken', phpToken);
                console.log('🔄 Token sincronizado automáticamente');
            }
            
            return token;
        }

        function getAuthHeaders() {
            const token = getToken();
            
            if (!token) {
                console.error('❌ No hay token disponible para headers');
                showNotification('Error: Token de autenticación no disponible', 'error');
                return {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                };
            }
            
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`
            };
        }

        // === INICIALIZACIÓN ===
        document.addEventListener('DOMContentLoaded', () => {
            console.log('✅ Panel staff cargado exitosamente');
            console.log('👤 Usuario:', CONFIG.USER_DATA.name);
            console.log('🔑 Rol:', CONFIG.USER_ROLE);
            
            // Verificar token usando función helper
            const token = getToken();
            
            console.log('🔍 Verificando token:', {
                hasToken: !!token,
                tokenPreview: token ? `${token.substring(0, 20)}...` : 'No disponible'
            });
            
            if (!token) {
                console.error('❌ No hay token disponible');
                showNotification('Error: No hay token de autenticación disponible', 'error');
                setTimeout(() => {
                    window.location.href = '/practicas/chat-frontend/public/index.php?error=no_token';
                }, 3000);
                return;
            }
            
            // Inicializar AuthClient con token correcto
            window.authClient = new AuthClient();
            window.authClient.token = token;
            window.authClient.user = CONFIG.USER_DATA;
            window.authClient.userType = 'staff';
            
            console.log('✅ AuthClient configurado:', {
                hasToken: !!window.authClient.token,
                user: window.authClient.user.name,
                userType: window.authClient.userType
            });
            
            // Update time every second
            updateTime();
            setInterval(updateTime, 1000);
            
            // Show success message
            showNotification(`¡Bienvenido ${CONFIG.USER_DATA.name}!`, 'success');
        });

        // === NAVEGACIÓN ===
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
                const navLink = event.target.closest('.nav-link');
                if (navLink) {
                    navLink.classList.remove('text-gray-600', 'hover:bg-gray-100', 'hover:text-gray-900');
                    navLink.classList.add('active');
                }
            }
            
            // Update section title
            const titles = {
                'dashboard': 'Dashboard',
                'chats': 'Chat de Agente',
                'rooms': 'Gestión de Salas',
                'supervision': 'Supervisión',
                'admin': 'Administración'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName] || 'Panel';
            
            // Inicializar funcionalidades específicas
            if (sectionName === 'chats') {
                initializeAgentChat();
            }
        }

        // === INICIALIZAR CHAT DE AGENTE CON WEBSOCKET REAL ===
        async function initializeAgentChat() {
            console.log('🚀 Inicializando panel de agente con conexiones reales...');
            
            try {
                // 1. Cargar sesiones reales
                await loadSessions();
                
                // 2. Inicializar WebSocket si no está conectado
                if (!window.chatClient) {
                    window.chatClient = new ChatClient();
                }
                
                // 3. Conectar WebSocket global para notificaciones
                const agentToken = getToken();
                if (agentToken && !window.chatClient.isConnected) {
                    try {
                        console.log('🔌 Conectando WebSocket global para agente...');
                        await window.chatClient.connectWebSocket(agentToken);
                        console.log('✅ WebSocket global conectado para agente');
                    } catch (wsError) {
                        console.warn('⚠️ No se pudo conectar WebSocket global:', wsError);
                        // Continuar sin WebSocket global, se conectará por sesión individual
                    }
                } else if (!agentToken) {
                    console.warn('⚠️ No hay token disponible para WebSocket global');
                }
                
                // 4. Setup event listeners
                setupChatEventListeners();
                
                // 5. Iniciar auto-refresh
                startAutoRefresh();
                
                console.log('✅ Panel de agente listo con conexiones reales');
            } catch (error) {
                console.error('❌ Error inicializando agente:', error);
                showNotification('Error de inicialización: ' + error.message, 'error');
            }
        }

        // === CARGAR SESIONES REALES ===
        async function loadSessions() {
            try {
                console.log('📡 Cargando sesiones reales desde backend...');
                
                const headers = getAuthHeaders();
                console.log('🔑 Headers para request:', {
                    hasAuth: !!headers.Authorization,
                    authPreview: headers.Authorization ? headers.Authorization.substring(0, 20) + '...' : 'No auth'
                });
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions`, {
                    method: 'GET',
                    headers: headers
                });
                
                console.log('📡 Status de respuesta:', response.status, response.statusText);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('❌ Error HTTP:', response.status, errorText);
                    throw new Error(`Error HTTP ${response.status}: ${response.statusText}\n${errorText}`);
                }
                
                const result = await response.json();
                console.log('📋 Respuesta del backend:', result);
                
                if (result.success && result.data && result.data.sessions) {
                    sessions = result.data.sessions.map(session => processSessionData(session));
                    console.log(`✅ ${sessions.length} sesiones reales cargadas`);
                } else {
                    console.warn('⚠️ No se encontraron sesiones:', result);
                    sessions = [];
                }
                
                updateSessionsList();
                updateStats();
                
            } catch (error) {
                console.error('❌ Error cargando sesiones reales:', error);
                showNotification('Error conectando con el servidor: ' + error.message, 'error');
                
                // Fallback: mostrar mensaje de error en la lista
                const container = document.getElementById('sessionsList');
                if (container) {
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <p class="text-red-600 font-medium">Error de conexión</p>
                            <p class="text-gray-500 text-sm mb-3">No se pudieron cargar las sesiones</p>
                            <p class="text-gray-400 text-xs mb-4">${error.message}</p>
                            <button onclick="loadSessions()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Reintentar
                            </button>
                        </div>
                    `;
                }
            }
        }

        // === PROCESAR DATOS DE SESIÓN DEL BACKEND ===
        function processSessionData(sessionData) {
            // Extraer información del paciente de los datos de la sesión
            let patientName = 'Paciente';
            let patientId = sessionData.user_id || 'unknown';
            
            // Intentar extraer nombre del paciente de diferentes fuentes
            if (sessionData.patient_data) {
                patientName = sessionData.patient_data.name || sessionData.patient_data.nombreCompleto || patientName;
                patientId = sessionData.patient_data.document || sessionData.patient_data.id || patientId;
            } else if (sessionData.user_data) {
                // Si viene en user_data (como string JSON)
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
            
            // Calcular tiempo de expiración (por defecto 30 minutos desde creación)
            const createdAt = new Date(sessionData.created_at || sessionData.createdAt || Date.now());
            const expiresAt = new Date(createdAt.getTime() + (30 * 60 * 1000)); // 30 minutos
            
            // Determinar prioridad basada en tiempo de espera
            const waitTime = (Date.now() - createdAt.getTime()) / 60000; // minutos
            let priority = 'normal';
            if (waitTime > 15) priority = 'urgent';
            else if (waitTime > 10) priority = 'high';
            
            return {
                id: sessionData.id || sessionData._id,
                patient_name: patientName,
                patient_id: patientId,
                room_id: sessionData.room_id || 'general',
                room_name: getRoomDisplayName(sessionData.room_id),
                status: sessionData.status || 'waiting',
                created_at: createdAt.toISOString(),
                agent_id: sessionData.agent_id || null,
                expires_at: expiresAt.toISOString(),
                priority: priority,
                user_data: sessionData.user_data,
                patient_data: sessionData.patient_data,
                last_message: sessionData.last_message,
                message_count: sessionData.message_count || 0
            };
        }

        // === OBTENER NOMBRE DISPLAY DE LA SALA ===
        function getRoomDisplayName(roomId) {
            const roomNames = {
                'general': 'Consulta General',
                'medical': 'Consulta Médica',
                'support': 'Soporte Técnico',
                'emergency': 'Urgencias',
                'pediatric': 'Pediatría',
                'cardiology': 'Cardiología',
                'dermatology': 'Dermatología'
            };
            return roomNames[roomId] || `Sala ${roomId}`;
        }

        // === ACTUALIZAR LISTA DE SESIONES ===
        function updateSessionsList() {
            const container = document.getElementById('sessionsList');
            if (!container) return;
            
            // Filtrar sesiones según el filtro actual
            let filteredSessions = sessions;
            
            if (currentFilter === 'waiting') {
                filteredSessions = sessions.filter(s => s.status === 'waiting');
            } else if (currentFilter === 'mine') {
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

        // === CREAR TARJETA DE SESIÓN ===
        function createSessionCard(session) {
            const timeElapsed = getTimeElapsed(session.created_at);
            const timeRemaining = getTimeRemaining(session.expires_at);
            const isMyChat = session.agent_id === CONFIG.USER_DATA.id;
            const isUrgent = session.priority === 'urgent' || timeRemaining <= 5;
            
            let statusClass = 'session-waiting';
            let statusDotClass = 'status-waiting';
            
            if (session.status === 'active' && isMyChat) {
                statusClass = 'session-mine';
                statusDotClass = 'status-mine';
            } else if (session.status === 'active') {
                statusClass = 'session-active';
                statusDotClass = 'status-active';
            }
            
            if (isUrgent) {
                statusClass = 'session-urgent';
                statusDotClass = 'status-urgent';
            }
            
            let timerClass = 'timer-normal';
            if (timeRemaining <= 5) {
                timerClass = 'timer-urgent';
            } else if (timeRemaining <= 10) {
                timerClass = 'timer-warning';
            }
            
            return `
                <div class="session-card ${statusClass} bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:shadow-md transition-all"
                     onclick="selectSession('${session.id}')">
                    
                    <!-- Header con paciente -->
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-sm font-semibold text-blue-700">${getPatientInitials(session.patient_name)}</span>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">${session.patient_name}</div>
                                <div class="text-sm text-gray-500">${session.room_name}</div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="status-dot ${statusDotClass}"></div>
                            <span class="text-xs text-gray-500">${formatStatus(session.status)}</span>
                        </div>
                    </div>
                    
                    <!-- Info de tiempo -->
                    <div class="grid grid-cols-2 gap-3 mb-3 text-sm">
                        <div>
                            <div class="text-xs text-gray-500">Tiempo transcurrido:</div>
                            <div class="font-medium">${timeElapsed} min</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Expira en:</div>
                            <div class="font-medium ${timerClass}">${timeRemaining} min</div>
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="flex items-center justify-between">
                        ${session.status === 'waiting' ? 
                            `<button onclick="event.stopPropagation(); assignToMe('${session.id}')" 
                                    class="px-3 py-1 bg-blue-600 text-white text-xs rounded-full hover:bg-blue-700 transition-colors">
                                Tomar Chat
                            </button>` : 
                            (isMyChat ? 
                                `<span class="text-xs text-blue-600 font-medium">Mi chat activo</span>` : 
                                `<span class="text-xs text-gray-500">Ocupado</span>`
                            )
                        }
                        
                        ${isUrgent ? 
                            `<span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full font-medium">
                                ⚠️ Urgente
                            </span>` : ''
                        }
                    </div>
                </div>
            `;
        }

        // === ASIGNAR SESIÓN AL AGENTE (API REAL) ===
        async function assignToMe(sessionId) {
            try {
                console.log('👤 Asignando sesión real:', sessionId);
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions/${sessionId}/assign`, {
                    method: 'PUT',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({
                        agent_id: CONFIG.USER_DATA.id,
                        agent_data: {
                            name: CONFIG.USER_DATA.name,
                            email: CONFIG.USER_DATA.email,
                            role: CONFIG.USER_ROLE
                        }
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                console.log('📋 Respuesta de asignación:', result);
                
                if (result.success) {
                    // Actualizar sesión local
                    const session = sessions.find(s => s.id === sessionId);
                    if (session) {
                        session.agent_id = CONFIG.USER_DATA.id;
                        session.status = 'active';
                    }
                    
                    showNotification('Sesión asignada exitosamente', 'success');
                    updateSessionsList();
                    updateStats();
                    
                    // Auto-seleccionar la sesión
                    await selectSession(sessionId);
                } else {
                    throw new Error(result.message || 'Error asignando sesión');
                }
                
            } catch (error) {
                console.error('❌ Error asignando sesión:', error);
                showNotification('Error: ' + error.message, 'error');
            }
        }

        // === SELECCIONAR SESIÓN (ABRIR CHAT) ===
        async function selectSession(sessionId) {
            try {
                console.log('🎯 Seleccionando sesión:', sessionId);
                
                const session = sessions.find(s => s.id === sessionId);
                if (!session) {
                    throw new Error('Sesión no encontrada');
                }
                
                // Solo permitir abrir chats propios o asignar chats en espera
                if (session.status === 'waiting') {
                    await assignToMe(sessionId);
                    return;
                }
                
                if (session.agent_id !== CONFIG.USER_DATA.id) {
                    showNotification('Esta sesión está siendo atendida por otro agente', 'warning');
                    return;
                }
                
                currentSessionId = sessionId;
                currentAgentSession = session;
                
                // Mostrar chat area
                document.getElementById('noChatSelected').classList.add('hidden');
                document.getElementById('activeChatArea').classList.remove('hidden');
                
                // Actualizar header del chat
                updateChatHeader(session);
                
                // Cargar información completa del paciente
                await loadPatientInfo(session);
                
                // Inicializar chat client si no existe
                if (!window.chatClient) {
                    window.chatClient = new ChatClient();
                }
                
                // Conectar al chat como agente
                await connectToChat(session);
                
                // Iniciar timer
                startSessionTimer(session);
                
                console.log('✅ Chat abierto exitosamente');
                
            } catch (error) {
                console.error('❌ Error seleccionando sesión:', error);
                showNotification('Error: ' + error.message, 'error');
            }
        }

        // === CONECTAR AL CHAT CON DATOS REALES ===
        async function connectToChat(session) {
            try {
                console.log('🔌 Conectando al chat real de la sesión:', session.id);
                
                // Obtener token usando función helper
                const agentToken = getToken();
                
                console.log('🔑 Verificando token para chat:', {
                    hasToken: !!agentToken,
                    tokenPreview: agentToken ? `${agentToken.substring(0, 20)}...` : 'No disponible'
                });
                
                if (!agentToken) {
                    throw new Error('Token de agente no disponible. Por favor, inicia sesión nuevamente.');
                }
                
                // Limpiar mensajes anteriores
                const messagesContainer = document.getElementById('chatMessages');
                if (messagesContainer) {
                    messagesContainer.innerHTML = '';
                }
                
                // Configurar el chat client para modo agente
                if (!window.chatClient) {
                    window.chatClient = new ChatClient();
                }
                
                window.chatClient.currentSessionId = session.id;
                window.chatClient.currentPToken = agentToken;
                window.chatClient.currentRoom = session.room_id;
                window.chatClient.isAgentMode = true;
                
                console.log('⚙️ ChatClient configurado:', {
                    sessionId: session.id,
                    roomId: session.room_id,
                    hasToken: !!agentToken,
                    isAgentMode: true
                });
                
                // Cargar historial de mensajes REAL
                await loadSessionMessages(session.id);
                
                // Conectar WebSocket si no está conectado
                if (!window.chatClient.isConnected) {
                    console.log('🔌 Conectando WebSocket...');
                    await window.chatClient.connectWebSocket(agentToken);
                } else {
                    console.log('✅ WebSocket ya conectado');
                }
                
                // Unirse a la sala específica de la sesión
                if (window.chatClient.isAuthenticated) {
                    console.log('🏠 Uniéndose a sesión...');
                    window.chatClient.sendToSocket('join_session', {
                        session_id: session.id,
                        agent_id: CONFIG.USER_DATA.id,
                        agent_mode: true
                    });
                } else {
                    console.warn('⚠️ WebSocket no autenticado, esperando...');
                    // Intentar autenticación
                    setTimeout(() => {
                        if (window.chatClient.socket && window.chatClient.socket.connected) {
                            window.chatClient.authenticateSocket(agentToken);
                        }
                    }, 1000);
                }
                
                console.log('✅ Conectado al chat real exitosamente');
                
            } catch (error) {
                console.error('❌ Error conectando al chat real:', error);
                showNotification('Error conectando al chat: ' + error.message, 'error');
                throw error;
            }
        }

        // === CARGAR MENSAJES REALES DE LA SESIÓN ===
        async function loadSessionMessages(sessionId) {
            try {
                console.log('📚 Cargando historial real de mensajes para sesión:', sessionId);
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/messages/${sessionId}?limit=100`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                if (!response.ok) {
                    console.warn(`⚠️ No se pudo cargar historial: ${response.status}`);
                    return;
                }
                
                const result = await response.json();
                console.log('📖 Respuesta de mensajes:', result);
                
                if (result.success && result.data && result.data.messages) {
                    const messages = result.data.messages;
                    console.log(`📨 Cargando ${messages.length} mensajes del historial`);
                    
                    // Agregar mensajes al chat en orden cronológico
                    messages.forEach(message => {
                        addMessageToChat(
                            message.content,
                            message.sender_type,
                            message.sender_id,
                            message.timestamp || message.created_at,
                            false // no scroll automático hasta el final
                        );
                        
                        // Si es un archivo, también agregarlo
                        if (message.message_type === 'file' && message.file_data) {
                            window.chatClient.addFileMessageToChat(message.file_data, message.sender_type === 'agent');
                        }
                    });
                    
                    // Scroll al final después de cargar todos
                    setTimeout(() => {
                        scrollToBottom();
                    }, 100);
                    
                } else {
                    console.log('📝 No hay mensajes previos en esta sesión');
                    
                    // Agregar mensaje de bienvenida del sistema si no hay historial
                    addSystemWelcomeMessage(session);
                }
                
            } catch (error) {
                console.error('❌ Error cargando historial de mensajes:', error);
                
                // Agregar mensaje de bienvenida como fallback
                addSystemWelcomeMessage(session);
            }
        }

        // === AGREGAR MENSAJES AL CHAT (REAL) ===
        function addMessageToChat(content, senderType, senderId, timestamp, scroll = true) {
            const messagesContainer = document.getElementById('chatMessages');
            if (!messagesContainer) return;
            
            try {
                // Determinar si es mensaje del agente actual
                const isMyMessage = (senderType === 'agent' && senderId === CONFIG.USER_DATA.id);
                const isPatientMessage = (senderType === 'patient' || senderType === 'user');
                const isSystemMessage = (senderType === 'system');
                
                const messageElement = document.createElement('div');
                messageElement.className = `message ${isMyMessage ? 'message-user' : (isPatientMessage ? 'message-system' : 'message-system')}`;
                
                const timeLabel = formatMessageTime(timestamp);
                
                if (isMyMessage) {
                    // Mensaje del agente actual (lado derecho, azul)
                    messageElement.innerHTML = `
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #2563eb;">
                            ${CONFIG.USER_DATA.name.charAt(0).toUpperCase()}
                        </div>
                        <div class="message-content" style="background: #2563eb; color: white;">
                            <p>${escapeHtml(content)}</p>
                            <div class="message-time">${timeLabel}</div>
                        </div>
                    `;
                } else if (isPatientMessage) {
                    // Mensaje del paciente (lado izquierdo, verde)
                    messageElement.innerHTML = `
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #10b981;">
                            P
                        </div>
                        <div class="message-content">
                            <div class="text-sm font-medium text-gray-700 mb-1">Paciente:</div>
                            <p>${escapeHtml(content)}</p>
                            <div class="message-time">${timeLabel}</div>
                        </div>
                    `;
                } else {
                    // Mensaje del sistema o de otro agente
                    messageElement.innerHTML = `
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0" style="background: #6b7280;">
                            S
                        </div>
                        <div class="message-content">
                            <div class="text-sm font-medium text-gray-700 mb-1">Sistema:</div>
                            <p>${escapeHtml(content)}</p>
                            <div class="message-time">${timeLabel}</div>
                        </div>
                    `;
                }
                
                messagesContainer.appendChild(messageElement);
                
                if (scroll) {
                    scrollToBottom();
                }
                
            } catch (error) {
                console.error('❌ Error agregando mensaje al chat:', error);
            }
        }

        // === AGREGAR MENSAJE DE BIENVENIDA ===
        function addSystemWelcomeMessage(session) {
            const welcomeMessages = [
                `Bienvenido/a ${session.patient_name}. Soy ${CONFIG.USER_DATA.name}, su ${CONFIG.USER_ROLE} asignado/a.`,
                'Estoy aquí para atenderle. ¿En qué puedo ayudarle hoy?'
            ];
            
            welcomeMessages.forEach((message, index) => {
                setTimeout(() => {
                    addMessageToChat(
                        message,
                        'system',
                        'welcome',
                        new Date().toISOString(),
                        index === welcomeMessages.length - 1 // solo scroll en el último
                    );
                }, index * 1000);
            });
        }

        // === UTILIDADES PARA MENSAJES ===
        function formatMessageTime(timestamp) {
            try {
                const date = new Date(timestamp);
                if (isNaN(date.getTime())) {
                    return '';
                }
                return date.toLocaleTimeString('es-ES', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
            } catch (error) {
                console.error('Error formateando tiempo:', error);
                return '';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function scrollToBottom() {
            const messagesContainer = document.getElementById('chatMessages');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }

        // === ACTUALIZAR HEADER DEL CHAT ===
        function updateChatHeader(session) {
            // Header principal
            const patientHeaderNameEl = document.getElementById('patientHeaderName');
            const patientHeaderInitialsEl = document.getElementById('patientHeaderInitials');
            const roomHeaderNameEl = document.getElementById('roomHeaderName');
            const sessionStatusEl = document.getElementById('sessionStatus');
            const sessionStatusDotEl = document.getElementById('sessionStatusDot');
            
            if (patientHeaderNameEl) patientHeaderNameEl.textContent = session.patient_name;
            if (patientHeaderInitialsEl) patientHeaderInitialsEl.textContent = getPatientInitials(session.patient_name);
            if (roomHeaderNameEl) roomHeaderNameEl.textContent = session.room_name; // Ya es el nombre, no el ID
            if (sessionStatusEl) sessionStatusEl.textContent = formatStatus(session.status);
            
            if (sessionStatusDotEl) {
                sessionStatusDotEl.className = 'status-dot status-mine';
            }
        }

        // === CARGAR INFORMACIÓN COMPLETA DEL PACIENTE ===
        async function loadPatientInfo(session) {
            try {
                console.log('👤 Cargando información completa del paciente...');
                
                // Primero intentar extraer de user_data si está disponible
                if (session.user_data || session.patient_data) {
                    const userData = session.user_data || session.patient_data;
                    let patientData = userData;
                    
                    // Si user_data es string JSON, parsearlo
                    if (typeof userData === 'string') {
                        try {
                            patientData = JSON.parse(userData);
                        } catch (e) {
                            console.warn('Error parseando user_data:', e);
                        }
                    }
                    
                    console.log('📋 Datos del paciente encontrados:', patientData);
                    displayPatientInfo(patientData, session);
                    return;
                }
                
                // Si no hay datos locales, intentar obtenerlos del auth service
                console.log('🔍 Obteniendo datos del paciente desde auth service...');
                
                // Necesitamos el pToken del paciente para validar y obtener sus datos
                // Este podría estar en session.ptoken o hacer una llamada específica
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions/${session.id}/patient`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success && result.data) {
                        console.log('✅ Datos del paciente obtenidos del backend:', result.data);
                        displayPatientInfo(result.data, session);
                        return;
                    }
                }
                
                // Fallback: mostrar información básica disponible
                console.log('⚠️ Usando información básica del paciente');
                displayBasicPatientInfo(session);
                
            } catch (error) {
                console.error('❌ Error cargando información del paciente:', error);
                displayBasicPatientInfo(session);
            }
        }

        // === MOSTRAR INFORMACIÓN COMPLETA DEL PACIENTE ===
        function displayPatientInfo(patientData, session) {
            console.log('📝 Mostrando información completa del paciente:', patientData);
            
            // Extraer datos de membresía si están disponibles
            let membresia = null;
            let beneficiario = null;
            
            if (patientData.membresias && patientData.membresias.length > 0) {
                membresia = patientData.membresias[0];
                beneficiario = membresia.beneficiarios?.find(b => b.tipo_ben === 'PPAL') || membresia.beneficiarios?.[0];
            }
            
            // Información Personal
            updateElement('patientFullName', beneficiario?.primer_nombre && beneficiario?.primer_apellido ? 
                [beneficiario.primer_nombre, beneficiario.segundo_nombre, beneficiario.primer_apellido, beneficiario.segundo_apellido]
                .filter(n => n).join(' ') : session.patient_name);
            
            updateElement('patientDocument', beneficiario?.numero_documento || 'No disponible');
            updateElement('patientDocType', beneficiario?.tipo_documento || 'CC');
            updateElement('patientBirthDate', beneficiario?.fecha_nacimiento ? 
                new Date(beneficiario.fecha_nacimiento).toLocaleDateString('es-ES') : 'No disponible');
            updateElement('patientGender', beneficiario?.genero || 'No disponible');
            
            // Información de Contacto
            updateElement('patientPhone', beneficiario?.telefono || membresia?.telefono || 'No disponible');
            updateElement('patientEmail', beneficiario?.email || membresia?.email || 'No disponible');
            updateElement('patientCity', beneficiario?.ciudad || membresia?.ciudad || 'No disponible');
            
            // Información de Membresía
            updateElement('patientEPS', membresia?.eps || 'No disponible');
            updateElement('patientPlan', membresia?.plan || 'No disponible');
            updateElement('patientStatus', membresia?.estado || 'Activo');
            updateElement('patientVigencia', membresia?.fecha_fin ? 
                new Date(membresia.fecha_fin).toLocaleDateString('es-ES') : 'No disponible');
            
            // Información del Tomador
            updateElement('tomadorName', membresia?.nomTomador || 'No disponible');
            updateElement('tomadorDocument', membresia?.docTomador || 'No disponible');
            updateElement('tomadorEmpresa', membresia?.empresa || 'No disponible');
            
            // Información de Sesión
            updateElement('sessionRoomName', session.room_name);
            updateElement('sessionStartTime', new Date(session.created_at).toLocaleString('es-ES'));
            updateElement('sessionDuration', calculateDuration(session.created_at));
        }

        // === MOSTRAR INFORMACIÓN BÁSICA DEL PACIENTE ===
        function displayBasicPatientInfo(session) {
            console.log('📝 Mostrando información básica del paciente');
            
            // Información Personal básica
            updateElement('patientFullName', session.patient_name);
            updateElement('patientDocument', session.patient_id || 'No disponible');
            updateElement('patientDocType', 'CC');
            updateElement('patientBirthDate', 'No disponible');
            updateElement('patientGender', 'No disponible');
            
            // Información de Contacto
            updateElement('patientPhone', 'No disponible');
            updateElement('patientEmail', 'No disponible');
            updateElement('patientCity', 'No disponible');
            
            // Información de Membresía
            updateElement('patientEPS', 'No disponible');
            updateElement('patientPlan', 'No disponible');
            updateElement('patientStatus', 'Activo');
            updateElement('patientVigencia', 'No disponible');
            
            // Información del Tomador
            updateElement('tomadorName', 'No disponible');
            updateElement('tomadorDocument', 'No disponible');
            updateElement('tomadorEmpresa', 'No disponible');
            
            // Información de Sesión
            updateElement('sessionRoomName', session.room_name);
            updateElement('sessionStartTime', new Date(session.created_at).toLocaleString('es-ES'));
            updateElement('sessionDuration', calculateDuration(session.created_at));
        }

        // === UTILIDADES PARA EL PANEL DE INFORMACIÓN ===
        function updateElement(elementId, value) {
            const element = document.getElementById(elementId);
            if (element) {
                element.textContent = value || 'No disponible';
            }
        }

        function calculateDuration(startTime) {
            try {
                const start = new Date(startTime);
                const now = new Date();
                const diffMs = now - start;
                const diffMins = Math.floor(diffMs / 60000);
                
                if (diffMins < 60) {
                    return `${diffMins} min`;
                } else {
                    const hours = Math.floor(diffMins / 60);
                    const mins = diffMins % 60;
                    return `${hours}h ${mins}m`;
                }
            } catch (error) {
                return 'No disponible';
            }
        }

        // === TIMER DE SESIÓN ===
        function startSessionTimer(session) {
            if (timerInterval) clearInterval(timerInterval);
            
            timerInterval = setInterval(() => {
                const timeRemaining = getTimeRemaining(session.expires_at);
                const timerTextEl = document.getElementById('timerText');
                const sessionTimerEl = document.getElementById('sessionTimer');
                
                if (timerTextEl) {
                    timerTextEl.textContent = formatMinutesToMMSS(timeRemaining);
                }
                
                if (sessionTimerEl) {
                    sessionTimerEl.className = 'text-center';
                    if (timeRemaining <= 2) {
                        sessionTimerEl.classList.add('timer-urgent');
                    } else if (timeRemaining <= 5) {
                        sessionTimerEl.classList.add('timer-warning');
                    }
                }
                
                // Si expira
                if (timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    handleSessionExpiry();
                }
            }, 1000);
        }

        // === MANEJAR EXPIRACIÓN DE SESIÓN ===
        function handleSessionExpiry() {
            showNotification('⏰ La sesión ha expirado', 'warning');
            
            if (currentAgentSession) {
                currentAgentSession.status = 'expired';
                updateSessionsList();
            }
            
            closeChat();
        }

        // === SETUP EVENT LISTENERS REALES ===
        function setupChatEventListeners() {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', handleInputChange);
                messageInput.addEventListener('keydown', handleKeyDown);
            }
            
            // Setup WebSocket event handlers para mensajes en tiempo real
            if (window.chatClient && window.chatClient.socket) {
                // Remover listeners anteriores para evitar duplicados
                window.chatClient.socket.off('message_received');
                window.chatClient.socket.off('file_uploaded');
                window.chatClient.socket.off('user_typing');
                window.chatClient.socket.off('user_stop_typing');
                window.chatClient.socket.off('session_updated');
                
                // Mensaje recibido del paciente
                window.chatClient.socket.on('message_received', (data) => {
                    console.log('📨 Mensaje recibido en agente:', data);
                    
                    // Solo mostrar si es de la sesión actual
                    if (data.session_id === currentSessionId && data.sender_type !== 'agent') {
                        addMessageToChat(
                            data.content,
                            data.sender_type,
                            data.sender_id,
                            data.timestamp,
                            true
                        );
                        
                        // Reproducir sonido de notificación
                        playNotificationSound();
                    }
                });
                
                // Archivo subido
                window.chatClient.socket.on('file_uploaded', (data) => {
                    console.log('📎 Archivo recibido en agente:', data);
                    
                    if (data.session_id === currentSessionId) {
                        const isMyFile = data.sender_type === 'agent' && data.sender_id === CONFIG.USER_DATA.id;
                        window.chatClient.addFileMessageToChat(data, isMyFile);
                    }
                });
                
                // Indicadores de escritura
                window.chatClient.socket.on('user_typing', (data) => {
                    if (data.session_id === currentSessionId && data.sender_type !== 'agent') {
                        showTypingIndicator();
                    }
                });
                
                window.chatClient.socket.on('user_stop_typing', (data) => {
                    if (data.session_id === currentSessionId) {
                        hideTypingIndicator();
                    }
                });
                
                // Actualización de sesión
                window.chatClient.socket.on('session_updated', (data) => {
                    console.log('🔄 Sesión actualizada:', data);
                    
                    // Actualizar sesión local
                    const sessionIndex = sessions.findIndex(s => s.id === data.session_id);
                    if (sessionIndex !== -1) {
                        sessions[sessionIndex] = { ...sessions[sessionIndex], ...data };
                        updateSessionsList();
                        updateStats();
                    }
                });
            }
        }

        // === INDICADORES DE ESCRITURA ===
        function showTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) {
                indicator.classList.remove('hidden');
                scrollToBottom();
            }
        }

        function hideTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) {
                indicator.classList.add('hidden');
            }
        }

        // === FINALIZAR SESIÓN CON API REAL ===
        async function endSession() {
            if (!currentSessionId || !currentAgentSession) return;
            
            if (confirm('¿Estás seguro de que quieres finalizar esta sesión?')) {
                try {
                    console.log('🔚 Finalizando sesión:', currentSessionId);
                    
                    const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions/${currentSessionId}/end`, {
                        method: 'PUT',
                        headers: getAuthHeaders(),
                        body: JSON.stringify({
                            agent_id: CONFIG.USER_DATA.id,
                            end_reason: 'completed_by_agent',
                            notes: 'Sesión finalizada por el agente'
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const result = await response.json();
                    console.log('📋 Respuesta de finalización:', result);
                    
                    if (result.success) {
                        // Actualizar sesión local
                        if (currentAgentSession) {
                            currentAgentSession.status = 'completed';
                        }
                        
                        showNotification('Sesión finalizada exitosamente', 'success');
                        updateSessionsList();
                        updateStats();
                        closeChat();
                    } else {
                        throw new Error(result.message || 'Error finalizando sesión');
                    }
                    
                } catch (error) {
                    console.error('❌ Error finalizando sesión:', error);
                    showNotification('Error: ' + error.message, 'error');
                }
            }
        }

        // === TRANSFERIR SESIÓN CON API REAL ===
        async function confirmTransfer() {
            if (!currentSessionId) return;
            
            const transferRoom = document.getElementById('transferRoom').value;
            const transferReason = document.getElementById('transferReason').value;
            
            try {
                console.log('🔄 Transfiriendo sesión:', currentSessionId);
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/sessions/${currentSessionId}/transfer`, {
                    method: 'PUT',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({
                        from_agent_id: CONFIG.USER_DATA.id,
                        target_room: transferRoom,
                        reason: transferReason,
                        transfer_type: 'room_transfer'
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                console.log('📋 Respuesta de transferencia:', result);
                
                if (result.success) {
                    showNotification(`Sesión transferida a ${transferRoom}`, 'success');
                    
                    // Actualizar sesión local
                    if (currentAgentSession) {
                        currentAgentSession.status = 'transferred';
                        currentAgentSession.agent_id = null;
                    }
                    
                    updateSessionsList();
                    updateStats();
                    closeChat();
                } else {
                    throw new Error(result.message || 'Error transfiriendo sesión');
                }
                
            } catch (error) {
                console.error('❌ Error transfiriendo sesión:', error);
                showNotification('Error: ' + error.message, 'error');
            }
            
            closeTransferModal();
        }

        // === NOTIFICACIÓN DE AUDIO ===
        function playNotificationSound() {
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmYfBSuPze/R');
                audio.volume = 0.3;
                audio.play().catch(() => {});
            } catch (error) {
                // Ignorar errores de audio
            }
        }

        function handleInputChange(e) {
            const charCount = e.target.value.length;
            const charCountEl = document.getElementById('charCount');
            const sendButton = document.getElementById('sendButton');
            
            if (charCountEl) charCountEl.textContent = charCount;
            if (sendButton) sendButton.disabled = charCount === 0;
            
            // Auto-resize
            e.target.style.height = 'auto';
            e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
        }

        function handleKeyDown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (e.target.value.trim()) {
                    sendMessage();
                }
            }
        }

        // === FUNCIONES DE CHAT CON API REAL ===
        function sendMessage() {
            const messageInput = document.getElementById('messageInput');
            if (!messageInput || !currentSessionId) return;
            
            const message = messageInput.value.trim();
            if (!message) return;
            
            // Enviar mensaje real a través del WebSocket
            if (window.chatClient && window.chatClient.isConnected) {
                window.chatClient.sendToSocket('send_message', {
                    content: message,
                    message_type: 'text',
                    session_id: currentSessionId,
                    sender_type: 'agent',
                    sender_id: CONFIG.USER_DATA.id,
                    timestamp: new Date().toISOString()
                });
                
                // Agregar el mensaje inmediatamente a la UI (optimistic update)
                addMessageToChat(
                    message,
                    'agent',
                    CONFIG.USER_DATA.id,
                    new Date().toISOString(),
                    true
                );
                
                console.log('📤 Mensaje de agente enviado:', message);
            } else {
                console.error('❌ No hay conexión WebSocket disponible');
                console.log('🔍 Estado del chat client:', {
                    chatClient: !!window.chatClient,
                    isConnected: window.chatClient?.isConnected,
                    socket: !!window.chatClient?.socket,
                    socketConnected: window.chatClient?.socket?.connected
                });
                showNotification('Error: No hay conexión con el servidor', 'error');
                return;
            }
            
            // Limpiar input
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            // Actualizar UI
            const charCountEl = document.getElementById('charCount');
            const sendButton = document.getElementById('sendButton');
            
            if (charCountEl) charCountEl.textContent = '0';
            if (sendButton) sendButton.disabled = true;
            
            messageInput.focus();
        }

        async function handleFileUpload(files) {
            if (!files || files.length === 0 || !currentSessionId) return;
            
            const file = files[0];
            
            // Validar tamaño (10MB máximo)
            if (file.size > 10 * 1024 * 1024) {
                showNotification('Archivo muy grande (máximo 10MB)', 'error');
                return;
            }
            
            try {
                console.log('📎 Subiendo archivo desde agente:', file.name);
                
                // Obtener token correcto usando función helper
                const token = getToken();
                
                if (!token) {
                    throw new Error('Token no disponible para subir archivo');
                }
                
                const formData = new FormData();
                formData.append('file', file);
                formData.append('session_id', currentSessionId);
                formData.append('sender_id', CONFIG.USER_DATA.id);
                formData.append('sender_type', 'agent');
                
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/files/upload`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`
                        // No agregar Content-Type para FormData
                    },
                    body: formData
                });
                
                const result = await response.json();
                console.log('📁 Resultado upload:', result);
                
                if (response.ok && result.success) {
                    console.log('✅ Archivo subido exitosamente por agente');
                    showNotification('Archivo enviado exitosamente', 'success');
                } else {
                    throw new Error(result.message || 'Error subiendo archivo');
                }
                
            } catch (error) {
                console.error('❌ Error upload archivo:', error);
                showNotification('Error subiendo archivo: ' + error.message, 'error');
            }
        }

        function closeChat() {
            currentSessionId = null;
            currentAgentSession = null;
            
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            
            // No desconectar WebSocket global, solo limpiar la sesión actual
            if (window.chatClient) {
                window.chatClient.currentSessionId = null;
            }
            
            document.getElementById('activeChatArea').classList.add('hidden');
            document.getElementById('noChatSelected').classList.remove('hidden');
            
            console.log('🔒 Chat cerrado');
        }

        function transferSession() {
            if (!currentSessionId) return;
            document.getElementById('transferModal').classList.remove('hidden');
        }

        function closeTransferModal() {
            document.getElementById('transferModal').classList.add('hidden');
        }

        function endSession() {
            if (!currentSessionId || !currentAgentSession) return;
            
            if (confirm('¿Estás seguro de que quieres finalizar esta sesión?')) {
                currentAgentSession.status = 'completed';
                
                showNotification('Sesión finalizada exitosamente', 'success');
                updateSessionsList();
                updateStats();
                closeChat();
            }
        }

        // === FILTROS ===
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

        // === ESTADÍSTICAS ===
        function updateStats() {
            const waiting = sessions.filter(s => s.status === 'waiting').length;
            const myActive = sessions.filter(s => s.agent_id === CONFIG.USER_DATA.id && s.status === 'active').length;
            const urgent = sessions.filter(s => s.priority === 'urgent' || getTimeRemaining(s.expires_at) <= 5).length;
            
            const waitingEl = document.getElementById('waitingCount');
            const myActiveEl = document.getElementById('myActiveCount');
            const urgentEl = document.getElementById('urgentCount');
            
            if (waitingEl) waitingEl.textContent = waiting;
            if (myActiveEl) myActiveEl.textContent = myActive;
            if (urgentEl) urgentEl.textContent = urgent;
            
            // Actualizar badge en navegación
            const chatsBadge = document.getElementById('chatsBadge');
            if (chatsBadge) {
                if (myActive > 0) {
                    chatsBadge.textContent = myActive;
                    chatsBadge.classList.remove('hidden');
                } else {
                    chatsBadge.classList.add('hidden');
                }
            }
        }

        // === AUTO-REFRESH ===
        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            
            refreshInterval = setInterval(() => {
                loadSessions();
            }, CONFIG.REFRESH_INTERVAL);
        }

        // === UTILIDADES ===
        function getPatientInitials(name) {
            return name.split(' ')
                      .map(part => part.charAt(0))
                      .join('')
                      .substring(0, 2)
                      .toUpperCase();
        }

        function getTimeElapsed(timestamp) {
            const now = new Date();
            const start = new Date(timestamp);
            return Math.floor((now - start) / 60000);
        }

        function getTimeRemaining(expiresAt) {
            const now = new Date();
            const expires = new Date(expiresAt);
            return Math.max(0, Math.floor((expires - now) / 60000));
        }

        function formatMinutesToMMSS(minutes) {
            if (minutes <= 0) return '00:00';
            
            const mins = Math.floor(minutes);
            const secs = Math.floor((minutes - mins) * 60);
            
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        function formatStatus(status) {
            const statusMap = {
                'waiting': 'Esperando',
                'active': 'Activo',
                'transferred': 'Transferido',
                'completed': 'Completado',
                'expired': 'Expirado'
            };
            return statusMap[status] || status;
        }

        function getEmptyMessage() {
            const messages = {
                'all': 'No hay sesiones disponibles',
                'waiting': 'No hay pacientes en cola',
                'mine': 'No tienes chats activos'
            };
            return messages[currentFilter] || 'No hay datos';
        }

        // === FUNCIONES GENERALES ===
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('es-ES');
            }
        }
        
        function refreshDashboard() {
            showNotification('Dashboard actualizado', 'success', 2000);
        }
        
        function confirmLogout() {
            if (confirm('¿Cerrar sesión?')) {
                // Limpiar todo
                localStorage.clear();
                sessionStorage.clear();
                
                if (refreshInterval) clearInterval(refreshInterval);
                if (timerInterval) clearInterval(timerInterval);
                if (window.chatClient) window.chatClient.disconnect();
                
                // Ir a logout
                window.location.href = '/practicas/chat-frontend/public/logout.php';
            }
        }
        
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

        // Limpiar intervalos al cerrar
        window.addEventListener('beforeunload', () => {
            if (refreshInterval) clearInterval(refreshInterval);
            if (timerInterval) clearInterval(timerInterval);
            if (window.chatClient) window.chatClient.disconnect();
        });
    </script>
</body>
</html>