<?php
// public/staff.php - Panel para personal MINIMALISTA
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

protectStaffPage();

$auth = auth();
$user = $auth->getUser();
$userRole = $user['role']['name'] ?? $user['role'] ?? 'agent';

if (is_numeric($userRole)) {
    $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
    $userRole = $roleMap[$userRole] ?? 'agent';
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Médico</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="/practicas/chat-frontend/public/assets/css/main.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="h-full bg-gray-50">
    <div class="min-h-full flex">
        <!-- Sidebar Minimalista -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <!-- Logo -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                        <svg class="icon icon-sm text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Panel Médico</h1>
                        <p class="text-sm text-gray-500">v2.0</p>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="flex-1 p-4">
                <div class="space-y-1">
                    <a href="#dashboard" onclick="showSection('dashboard')" 
                       class="nav-link bg-primary text-white flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="icon icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2v0a2 2 0 012-2h6l2 2h6a2 2 0 012 2v1" />
                        </svg>
                        Dashboard
                    </a>
                    
                    <a href="#chats" onclick="showSection('chats')" 
                       class="nav-link text-gray-600 hover:bg-gray-100 hover:text-gray-900 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="icon icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                        </svg>
                        Chats
                        <span id="chatsBadge" class="ml-auto bg-success text-white text-xs rounded-full px-2 py-1 hidden">0</span>
                    </a>
                    
                    <?php if (in_array($userRole, ['supervisor', 'admin'])): ?>
                    <a href="#supervision" onclick="showSection('supervision')" 
                       class="nav-link text-gray-600 hover:bg-gray-100 hover:text-gray-900 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="icon icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Supervisión
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'admin'): ?>
                    <a href="#admin" onclick="showSection('admin')" 
                       class="nav-link text-gray-600 hover:bg-gray-100 hover:text-gray-900 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="icon icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Admin
                    </a>
                    <?php endif; ?>

                    <a href="#rooms" onclick="showSection('rooms')" 
                       class="nav-link text-gray-600 hover:bg-gray-100 hover:text-gray-900 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="icon icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4" />
                        </svg>
                        Salas
                    </a>
                </div>
            </nav>
                
            <!-- User Profile -->
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="avatar avatar-md avatar-user">
                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></p>
                        <p class="text-sm text-gray-500 capitalize"><?= htmlspecialchars($userRole) ?></p>
                    </div>
                    <button onclick="confirmLogout()" class="btn-ghost p-2" title="Cerrar sesión">
                        <svg class="icon icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <div class="w-2 h-2 bg-success rounded-full"></div>
                                <span class="text-sm text-gray-600">En línea</span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-6 overflow-auto">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="section-content">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Chats Activos</p>
                                        <p id="activeChatsCount" class="text-2xl font-bold text-gray-900">--</p>
                                    </div>
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg class="icon text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">En Cola</p>
                                        <p id="queueCount" class="text-2xl font-bold text-gray-900">--</p>
                                    </div>
                                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                        <svg class="icon text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Tiempo Promedio</p>
                                        <p id="avgTime" class="text-2xl font-bold text-gray-900">-- min</p>
                                    </div>
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                        <svg class="icon text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Completados</p>
                                        <p id="completedToday" class="text-2xl font-bold text-gray-900">--</p>
                                    </div>
                                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <svg class="icon text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">Actividad Reciente</h3>
                                <button onclick="refreshDashboard()" class="btn btn-secondary">
                                    <svg class="icon icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="recentActivity" class="space-y-3">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando actividad...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chats Section -->
                <div id="chats-section" class="section-content hidden">
                    <div class="card">
                        <div class="card-header">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">Chats Activos</h3>
                                <div class="flex gap-2">
                                    <button onclick="createNewChat()" class="btn btn-primary">
                                        <svg class="icon icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Nuevo Chat
                                    </button>
                                    <button onclick="refreshChats()" class="btn btn-secondary">
                                        <svg class="icon icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Actualizar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="activeChats" class="space-y-4">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando chats...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rooms Section -->
                <div id="rooms-section" class="section-content hidden">
                    <div class="card">
                        <div class="card-header">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">Gestión de Salas</h3>
                                <button onclick="refreshRooms()" class="btn btn-primary">
                                    <svg class="icon icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="roomsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div class="text-center py-8 col-span-full">
                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando salas...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supervision Section -->
                <?php if (in_array($userRole, ['supervisor', 'admin'])): ?>
                <div id="supervision-section" class="section-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="font-semibold text-gray-900">Rendimiento</h3>
                            </div>
                            <div class="card-body">
                                <div id="agentPerformance" class="space-y-3">
                                    <div class="text-center py-8">
                                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary mx-auto mb-4"></div>
                                        <p class="text-gray-500">Cargando datos...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="font-semibold text-gray-900">Estadísticas</h3>
                            </div>
                            <div class="card-body">
                                <div id="systemStats" class="space-y-3">
                                    <div class="text-center py-8">
                                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary mx-auto mb-4"></div>
                                        <p class="text-gray-500">Cargando estadísticas...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Admin Section -->
                <?php if ($userRole === 'admin'): ?>
                <div id="admin-section" class="section-content hidden">
                    <div class="space-y-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="font-semibold text-gray-900">Acciones Rápidas</h3>
                            </div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <button onclick="showCreateUserModal()" class="card hover:shadow-md cursor-pointer">
                                        <div class="card-body text-center">
                                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                                <svg class="icon text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                                </svg>
                                            </div>
                                            <h4 class="font-medium text-gray-900">Crear Usuario</h4>
                                            <p class="text-sm text-gray-600">Agregar personal</p>
                                        </div>
                                    </button>
                                    
                                    <button onclick="showSystemConfig()" class="card hover:shadow-md cursor-pointer">
                                        <div class="card-body text-center">
                                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                                <svg class="icon text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </div>
                                            <h4 class="font-medium text-gray-900">Configuración</h4>
                                            <p class="text-sm text-gray-600">Ajustes sistema</p>
                                        </div>
                                    </button>
                                    
                                    <button onclick="generateReports()" class="card hover:shadow-md cursor-pointer">
                                        <div class="card-body text-center">
                                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                                <svg class="icon text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            </div>
                                            <h4 class="font-medium text-gray-900">Reportes</h4>
                                            <p class="text-sm text-gray-600">Generar informes</p>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="font-semibold text-gray-900">Estado del Sistema</h3>
                            </div>
                            <div class="card-body">
                                <div id="systemStatus" class="space-y-4">
                                    <div class="text-center py-8">
                                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary mx-auto mb-4"></div>
                                        <p class="text-gray-500">Verificando sistema...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Loading Global -->
    <div id="globalLoading" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-8 shadow-2xl text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
            <h3 class="font-semibold text-gray-900 mb-2">Procesando...</h3>
            <p id="loadingMessage" class="text-gray-600 text-sm">Cargando datos</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/practicas/chat-frontend/public/assets/js/auth-client.js"></script>
    <script src="/practicas/chat-frontend/public/assets/js/chat-client.js"></script>
    
    <script>
        // Configuration
        const CONFIG = {
            AUTH_SERVICE_URL: 'http://187.33.158.246:8080/auth',
            USER_ROLE: '<?= $userRole ?>',
            USER_DATA: <?= json_encode($user) ?>
        };

        let currentUser = CONFIG.USER_DATA;
        let dashboardData = {};

        // Initialize staff panel
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🏥 Panel minimalista iniciado');
            console.log('👤 Usuario:', currentUser.name, '| Rol:', CONFIG.USER_ROLE);
            
            window.authClient = new AuthClient(CONFIG.AUTH_SERVICE_URL);
            
            loadDashboardData();
            updateTime();
            setInterval(updateTime, 1000);
            
            setupRealtimeUpdates();
        });
        
        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('es-ES');
        }
        
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
                link.classList.remove('bg-primary', 'text-white');
                link.classList.add('text-gray-600', 'hover:bg-gray-100', 'hover:text-gray-900');
            });
            
            // Highlight active nav
            event.target.classList.remove('text-gray-600', 'hover:bg-gray-100', 'hover:text-gray-900');
            event.target.classList.add('bg-primary', 'text-white');
            
            // Update section title
            const titles = {
                'dashboard': 'Dashboard',
                'chats': 'Chats Activos',
                'rooms': 'Gestión de Salas',
                'supervision': 'Supervisión',
                'admin': 'Administración'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName] || 'Panel';
            
            loadSectionData(sectionName);
        }
        
        // Load dashboard data
        async function loadDashboardData() {
            try {
                updateDashboardStats({
                    activeChats: Math.floor(Math.random() * 15) + 5,
                    queueCount: Math.floor(Math.random() * 8) + 2,
                    avgTime: Math.floor(Math.random() * 20) + 8,
                    completedToday: Math.floor(Math.random() * 50) + 25
                });
                
                loadRecentActivity();
                
            } catch (error) {
                console.error('Error loading dashboard data:', error);
                showNotification('Error cargando datos del dashboard', 'error');
            }
        }
        
        function updateDashboardStats(data) {
            document.getElementById('activeChatsCount').textContent = data.activeChats || '0';
            document.getElementById('queueCount').textContent = data.queueCount || '0';
            document.getElementById('avgTime').textContent = (data.avgTime || 0) + ' min';
            document.getElementById('completedToday').textContent = data.completedToday || '0';
            
            const chatsBadge = document.getElementById('chatsBadge');
            if (data.activeChats > 0) {
                chatsBadge.textContent = data.activeChats;
                chatsBadge.classList.remove('hidden');
            } else {
                chatsBadge.classList.add('hidden');
            }
        }
        
        function loadRecentActivity() {
            const activities = [
                { time: '10:45', action: 'Chat iniciado', user: 'Dr. García', type: 'chat' },
                { time: '10:42', action: 'Consulta finalizada', user: 'Dra. López', type: 'completed' },
                { time: '10:40', action: 'Chat transferido', user: 'Enf. Martínez', type: 'transfer' },
                { time: '10:38', action: 'Paciente en espera', user: 'Sistema', type: 'queue' },
                { time: '10:35', action: 'Emergencia atendida', user: 'Dr. Rodríguez', type: 'emergency' }
            ];
            
            const container = document.getElementById('recentActivity');
                            container.innerHTML = activities.map(activity => `
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-8 h-8 ${getActivityColor(activity.type)} rounded-full flex items-center justify-center">
                        ${getActivityIcon(activity.type)}
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">${activity.action}</p>
                        <p class="text-sm text-gray-500">${activity.user} • ${activity.time}</p>
                    </div>
                </div>
            `).join('');
        }
        
        function getActivityColor(type) {
            const colors = {
                'chat': 'bg-blue-100',
                'completed': 'bg-green-100',
                'transfer': 'bg-yellow-100',
                'queue': 'bg-purple-100',
                'emergency': 'bg-red-100'
            };
            return colors[type] || 'bg-gray-100';
        }
        
        function getActivityIcon(type) {
            const icons = {
                'chat': '<svg class="icon icon-sm text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path></svg>',
                'completed': '<svg class="icon icon-sm text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
                'transfer': '<svg class="icon icon-sm text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>',
                'queue': '<svg class="icon icon-sm text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path></svg>',
                'emergency': '<svg class="icon icon-sm text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
            };
            return icons[type] || '';
        }
        
        function loadSectionData(sectionName) {
            switch(sectionName) {
                case 'chats':
                    loadActiveChats();
                    break;
                case 'rooms':
                    loadRoomsData();
                    break;
                case 'supervision':
                    loadSupervisionData();
                    break;
                case 'admin':
                    loadAdminData();
                    break;
            }
        }
        
        async function loadActiveChats() {
            try {
                const chats = [
                    { 
                        id: 'chat_001', 
                        patient_name: 'María González', 
                        room_name: 'Consulta General', 
                        status: 'active',
                        started_at: new Date(Date.now() - 15 * 60000).toISOString(),
                        agent: 'Dr. García'
                    },
                    { 
                        id: 'chat_002', 
                        patient_name: 'Carlos Ruiz', 
                        room_name: 'Emergencias', 
                        status: 'waiting',
                        started_at: new Date(Date.now() - 5 * 60000).toISOString(),
                        agent: null
                    }
                ];
                
                displayActiveChats(chats);
                
            } catch (error) {
                console.error('Error loading active chats:', error);
                showNotification('Error cargando chats activos', 'error');
            }
        }
        
        function displayActiveChats(chats) {
            const container = document.getElementById('activeChats');
            
            if (!chats || chats.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                        <h3 class="font-medium text-gray-900 mb-2">No hay chats activos</h3>
                        <p class="text-gray-600">Los nuevos chats aparecerán aquí</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = chats.map(chat => `
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 text-sm font-medium">
                                    ${(chat.patient_name || 'P').charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900">${chat.patient_name || 'Paciente Anónimo'}</h4>
                                <p class="text-sm text-gray-500">
                                    ${chat.room_name} | ${formatTimeAgo(chat.started_at)} | ${chat.agent || 'Sin asignar'}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                chat.status === 'active' ? 'bg-green-100 text-green-800' : 
                                chat.status === 'waiting' ? 'bg-yellow-100 text-yellow-800' : 
                                'bg-gray-100 text-gray-800'
                            }">
                                ${chat.status === 'active' ? 'Activo' : 
                                  chat.status === 'waiting' ? 'En espera' : 
                                  chat.status}
                            </span>
                            <button onclick="openChat('${chat.id}', '${chat.patient_name}')" 
                                    class="btn btn-primary">
                                Abrir
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        async function loadRoomsData() {
            try {
                if (!window.authClient.isAuthenticated()) {
                    showNotification('Error: No hay sesión activa', 'error');
                    return;
                }
                
                const rooms = await window.authClient.getAvailableRooms();
                displayRoomsData(rooms);
                
            } catch (error) {
                console.error('Error loading rooms:', error);
                showNotification('Error cargando salas: ' + error.message, 'error');
            }
        }
        
        function displayRoomsData(rooms) {
            const container = document.getElementById('roomsList');
            
            if (!rooms || rooms.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center py-12">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                        <h3 class="font-medium text-gray-900 mb-2">No hay salas</h3>
                        <p class="text-gray-600">Las salas aparecerán aquí</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = rooms.map(room => `
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-medium text-gray-900">${room.name}</h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                room.available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                            }">
                                ${room.available ? 'Disponible' : 'No disponible'}
                            </span>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">${room.description}</p>
                        <div class="flex items-center justify-between text-sm text-gray-500">
                            <span>Tiempo: ${room.estimated_wait}</span>
                            <span>En cola: ${room.current_queue || 0}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        // Utility functions
        function formatTimeAgo(timestamp) {
            const now = new Date();
            const time = new Date(timestamp);
            const diff = Math.floor((now - time) / 1000 / 60); // minutes
            
            if (diff < 1) return 'Ahora';
            if (diff < 60) return `${diff}m`;
            if (diff < 1440) return `${Math.floor(diff / 60)}h`;
            return `${Math.floor(diff / 1440)}d`;
        }
        
        function setupRealtimeUpdates() {
            setInterval(() => {
                loadDashboardData();
                
                const activeSection = document.querySelector('.section-content:not(.hidden)');
                if (activeSection && activeSection.id === 'chats-section') {
                    loadActiveChats();
                }
            }, 30000);
        }
        
        function refreshDashboard() {
            showNotification('Actualizando...', 'info', 2000);
            loadDashboardData();
        }
        
        function refreshChats() {
            showNotification('Actualizando chats...', 'info', 2000);
            loadActiveChats();
        }
        
        function refreshRooms() {
            showNotification('Actualizando salas...', 'info', 2000);
            loadRoomsData();
        }
        
        function confirmLogout() {
            if (confirm('¿Cerrar sesión?')) {
                window.authClient.logout();
            }
        }
        
        function openChat(chatId, patientName) {
            showNotification(`Abriendo chat con ${patientName}...`, 'info');
        }
        
        function createNewChat() {
            showNotification('Función en desarrollo...', 'info');
        }
        
        // Placeholder functions
        function showCreateUserModal() { showNotification('Crear usuario - En desarrollo', 'info'); }
        function showSystemConfig() { showNotification('Configuración - En desarrollo', 'info'); }
        function generateReports() { showNotification('Reportes - En desarrollo', 'info'); }
        function loadSupervisionData() { console.log('Loading supervision data'); }
        function loadAdminData() { console.log('Loading admin data'); }
        
        // Notification system
        function showNotification(message, type = 'info', duration = 4000) {
            const notification = document.createElement('div');
            const colors = {
                success: 'notification-success',
                error: 'notification-error',
                warning: 'notification-warning',
                info: 'notification-info'
            };
            
            notification.className = `notification ${colors[type]}`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 hover:opacity-75">
                        <svg class="icon icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }
        
        console.log('🏥 Panel de staff minimalista v3.0');
        console.log('👤 Usuario actual:', currentUser.name);
        console.log('🔑 Rol:', CONFIG.USER_ROLE);
    </script>
</body>
</html>