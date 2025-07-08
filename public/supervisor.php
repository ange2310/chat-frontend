<?php
session_start();

// Verificación básica
if (!isset($_SESSION['staffJWT']) || empty($_SESSION['staffJWT'])) {
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

// Solo supervisores y admins pueden acceder
if (!in_array($userRole, ['supervisor', 'admin'])) {
    header("Location: /practicas/chat-frontend/public/index.php?error=not_supervisor");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Supervisor - <?= htmlspecialchars($user['name'] ?? 'Supervisor') ?></title>
    
    <meta name="supervisor-token" content="<?= $_SESSION['staffJWT'] ?>">
    <meta name="supervisor-user" content='<?= json_encode($user) ?>'>
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nav-link.active { background: #2563eb; color: white; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; color: #6b7280; text-decoration: none; border-radius: 0.5rem; transition: all 0.15s ease-in-out; }
        .nav-link:hover { background: #f3f4f6; color: #1f2937; }
        .stats-card { background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); border: 1px solid #e5e7eb; }
        .priority-high { border-left: 4px solid #dc2626; }
        .priority-medium { border-left: 4px solid #f59e0b; }
        .priority-low { border-left: 4px solid #10b981; }
        .notification-unread { background: #fef3c7; border-left: 4px solid #f59e0b; }
        .notification-read { background: white; border-left: 4px solid #e5e7eb; }
        .pulse-red { animation: pulse-red 2s infinite; }
        @keyframes pulse-red { 0%, 100% { background-color: #fecaca; } 50% { background-color: #dc2626; } }
    </style>
</head>
<body class="h-full bg-gray-50">
    <div class="min-h-full flex">
        
        <!-- Sidebar -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Panel de Supervisor</h1>
                        <p class="text-sm text-gray-500">Gestión y Control</p>
                    </div>
                </div>
            </div>
            
            <nav class="flex-1 p-4">
                <div class="space-y-1">
                    <a href="#dashboard" onclick="showSection('dashboard')" 
                       id="nav-dashboard" class="nav-link active">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Dashboard
                    </a>
                    
                    <a href="#transfers" onclick="showSection('transfers')" 
                       id="nav-transfers" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        Transferencias
                        <span id="transfersCount" class="ml-auto px-2 py-1 bg-red-500 text-white text-xs rounded-full hidden">0</span>
                    </a>
                    
                    <a href="#escalations" onclick="showSection('escalations')" 
                       id="nav-escalations" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        Escalaciones
                        <span id="escalationsCount" class="ml-auto px-2 py-1 bg-orange-500 text-white text-xs rounded-full hidden">0</span>
                    </a>
                    
                    <a href="#analysis" onclick="showSection('analysis')" 
                       id="nav-analysis" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Análisis De Mal Direccionamiento
                    </a>
                    
                    <a href="#notifications" onclick="showSection('notifications')" 
                       id="nav-notifications" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM21 3v9h-9V3h9z"></path>
                        </svg>
                        Notificaciones
                        <span id="notificationsCount" class="ml-auto px-2 py-1 bg-blue-500 text-white text-xs rounded-full hidden">0</span>
                    </a>
                </div>
            </nav>
                
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-semibold">
                        <?= strtoupper(substr($user['name'] ?? 'S', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($user['name'] ?? 'Supervisor') ?></p>
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
                
                <!-- DASHBOARD -->
                <div id="dashboard-section" class="section-content p-6">
                    
                    <!-- Métricas Principales -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Transferencias Pendientes</p>
                                    <p id="stat-pending-transfers" class="text-2xl font-bold text-red-600">0</p>
                                </div>
                                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Escalaciones Activas</p>
                                    <p id="stat-active-escalations" class="text-2xl font-bold text-orange-600">0</p>
                                </div>
                                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Agentes Activos</p>
                                    <p id="stat-active-agents" class="text-2xl font-bold text-green-600">0</p>
                                </div>
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Notificaciones</p>
                                    <p id="stat-unread-notifications" class="text-2xl font-bold text-blue-600">0</p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones Rápidas -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <button onclick="supervisorClient.runMisdirectionAnalysis()" 
                                    class="p-4 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors">
                                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900">Análizar </h4>
                                <p class="text-sm text-gray-600">Detectar mal direccionamiento</p>
                            </button>
                            
                            <button onclick="supervisorClient.refreshDashboard()" 
                                    class="p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900">Actualizar</h4>
                                <p class="text-sm text-gray-600">Refrescar métricas</p>
                            </button>
                            
                            <button onclick="showSection('notifications')" 
                                    class="p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5z"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900">Notificaciones</h4>
                                <p class="text-sm text-gray-600">Ver todas</p>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TRANSFERENCIAS -->
                <div id="transfers-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Solicitudes de Transferencia</h3>
                                    <p class="text-sm text-gray-600 mt-1">Gestionar transferencias entre salas</p>
                                </div>
                                <button onclick="supervisorClient.loadTransfers()" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="transfersContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando transferencias...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ESCALACIONES -->
                <div id="escalations-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Escalaciones Automáticas</h3>
                                    <p class="text-sm text-gray-600 mt-1">Sesiones escaladas tras múltiples transferencias</p>
                                </div>
                                <button onclick="supervisorClient.loadEscalations()" 
                                        class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="escalationsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando escalaciones...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ANÁLISIS -->
                <div id="analysis-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Análisis de Mal Direccionamiento</h3>
                                    <p class="text-sm text-gray-600 mt-1">Detectar conversaciones mal dirigidas por estadística de saltos</p>
                                </div>
                                <button onclick="supervisorClient.runMisdirectionAnalysis()" 
                                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                    Ejecutar Análisis
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="analysisContainer">
                                <div class="text-center py-12">
                                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    <p class="text-gray-500">Haz clic en "Ejecutar Análisis" para iniciar el análisis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NOTIFICACIONES -->
                <div id="notifications-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Centro de Notificaciones</h3>
                                    <p class="text-sm text-gray-600 mt-1">Alertas del sistema y notificaciones importantes</p>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="supervisorClient.markAllNotificationsRead()" 
                                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                                        Marcar Todo Leído
                                    </button>
                                    <button onclick="supervisorClient.loadNotifications()" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        Actualizar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="notificationsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando notificaciones...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Detalles de Transferencia -->
    <div id="transferModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-2xl w-full mx-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Detalles de Transferencia</h3>
                <button onclick="closeModal('transferModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div id="transferDetails" class="space-y-4">
                <!-- Los detalles se cargarán aquí -->
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('transferModal')" 
                        class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Cerrar
                </button>
                <button id="approveTransferBtn" onclick="supervisorClient.approveTransfer()" 
                        class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Aprobar
                </button>
                <button id="rejectTransferBtn" onclick="supervisorClient.rejectTransfer()" 
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Rechazar
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/supervisor-client.js"></script>
    
    <script>
        function showSection(sectionName) {
            // Ocultar todas las secciones
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            
            // Remover active de todos los nav-links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Mostrar sección seleccionada
            document.getElementById(`${sectionName}-section`).classList.remove('hidden');
            document.getElementById(`nav-${sectionName}`).classList.add('active');
            
            // Actualizar título
            const titles = {
                'dashboard': 'Dashboard',
                'transfers': 'Transferencias',
                'escalations': 'Escalaciones',
                'analysis': 'Análisis RF6',
                'notifications': 'Notificaciones'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName];
            
            // Cargar datos de la sección
            switch(sectionName) {
                case 'dashboard':
                    supervisorClient.loadDashboard();
                    break;
                case 'transfers':
                    supervisorClient.loadTransfers();
                    break;
                case 'escalations':
                    supervisorClient.loadEscalations();
                    break;
                case 'notifications':
                    supervisorClient.loadNotifications();
                    break;
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function logout() {
            if (confirm('¿Cerrar sesión?')) {
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = '/practicas/chat-frontend/public/logout.php';
            }
        }

        function updateTime() {
            document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('es-ES');
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', async () => {
            console.log('✅ Panel de supervisor cargado');
            
            updateTime();
            setInterval(updateTime, 1000);
            
            try {
                await supervisorClient.init();
                console.log('🚀 SupervisorClient inicializado');
            } catch (error) {
                console.error('❌ Error inicializando:', error);
            }
        });

        // Atajos de teclado
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