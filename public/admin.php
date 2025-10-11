<?php
session_start();

if (!isset($_SESSION['staffJWT']) || empty($_SESSION['staffJWT'])) {
    header("Location: index.php?error=no_session");
    exit;
}

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: index.php?error=no_user");
    exit;
}

$user = json_decode($_SESSION['user'], true);
if (!$user) {
    header("Location: index.php?error=invalid_user");
    exit;
}

$userRole = $user['role']['name'] ?? $user['role'] ?? 'agent';
if (is_numeric($userRole)) {
    $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
    $userRole = $roleMap[$userRole] ?? 'agent';
}

if (!in_array($userRole, ['supervisor', 'admin'])) {
    header("Location: index.php?error=not_supervisor");
    exit;
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$adminServiceUrl = $protocol . '://' . $host . ':3013';
$authServiceUrl = $protocol . '://' . $host . ':3010';

if (strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false) {
    $adminServiceUrl = 'https://api.your-domain.com/admin';
    $authServiceUrl = 'https://api.your-domain.com/auth';
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador - <?= htmlspecialchars($user['name'] ?? 'Admin') ?></title>
    
    <meta name="admin-token" content="<?= $_SESSION['staffJWT'] ?>">
    <meta name="admin-user" content='<?= json_encode($user) ?>'>
    <meta name="admin-service-url" content="<?= $adminServiceUrl ?>">
    <meta name="auth-service-url" content="<?= $authServiceUrl ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/admin-styles.css" rel="stylesheet">
</head>
<body class="h-full bg-gray-50">
    <div class="mobile-nav-backdrop lg:hidden" id="mobileNavBackdrop" onclick="closeMobileNav()"></div>
    
    <div class="min-h-full flex">
        <div class="hidden lg:flex w-64 bg-white border-r border-gray-200 flex-col sidebar-fixed">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-red-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Panel Admin</h1>
                        <p class="text-sm text-gray-500">Gestión Sistema</p>
                    </div>
                </div>
            </div>
            
            <nav class="flex-1 p-4">
                <div class="space-y-1" id="desktopNav">
                </div>
            </nav>
                
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-600 text-white rounded-full flex items-center justify-center font-semibold">
                        <?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($user['name'] ?? 'Administrador') ?></p>
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
        
        <div class="mobile-nav fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col lg:hidden" id="mobileNav">
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-red-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="font-semibold text-gray-900 text-sm">Panel Admin</h1>
                        </div>
                    </div>
                    <button onclick="closeMobileNav()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <nav class="flex-1 p-4">
                <div class="space-y-1" id="mobileNavItems">
                </div>
            </nav>
                
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class=.sidebar-main {"w-8 h-8 bg-red-600 text-white rounded-full flex items-center justify-center font-semibold text-sm">
                        <?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate text-sm"><?= htmlspecialchars($user['name'] ?? 'Admin') ?></p>
                        <p class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($userRole) ?></p>
                    </div>
                    <button onclick="logout()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg" title="Cerrar sesión">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex-1 flex flex-col">
            <div class="flex-1 flex flex-col content-with-sidebar">
            <header class="bg-white border-b border-gray-200">
                <div class="px-4 sm:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <button onclick="openMobileNav()" class="lg:hidden p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                            </button>
                            <h2 id="sectionTitle" class="text-lg sm:text-xl font-semibold text-gray-900">Dashboard</h2>
                        </div>
                        <div class="flex items-center gap-2 sm:gap-4">
                            <span id="currentTime" class="text-xs sm:text-sm text-gray-500"></span>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span class="text-xs sm:text-sm text-gray-600 hidden sm:inline">En línea</span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-auto">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="section-content p-4 sm:p-6">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs sm:text-sm font-medium text-gray-600">Total Salas</p>
                                    <p id="stat-total-rooms" class="text-lg sm:text-2xl font-bold text-blue-600">0</p>
                                </div>
                                <div class="w-8 h-8 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs sm:text-sm font-medium text-gray-600">Agentes</p>
                                    <p id="stat-total-agents" class="text-lg sm:text-2xl font-bold text-green-600">0</p>
                                </div>
                                <div class="w-8 h-8 sm:w-12 sm:h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs sm:text-sm font-medium text-gray-600">Activas</p>
                                    <p id="stat-active-sessions" class="text-lg sm:text-2xl font-bold text-purple-600">0</p>
                                </div>
                                <div class="w-8 h-8 sm:w-12 sm:h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 sm:w-6 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs sm:text-sm font-medium text-gray-600">Completadas</p>
                                    <p id="stat-completed-sessions" class="text-lg sm:text-2xl font-bold text-yellow-600">0</p>
                                </div>
                                <div class="w-8 h-8 sm:w-12 sm:h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 sm:w-6 sm:h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-4 sm:p-6 mb-6 sm:mb-8">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Estado de Sesiones</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="text-center p-3 sm:p-4 bg-blue-50 rounded-lg">
                                <div id="stat-waiting-sessions" class="text-xl sm:text-2xl font-bold text-blue-600">0</div>
                                <div class="text-xs sm:text-sm text-blue-600">En Espera</div>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-green-50 rounded-lg">
                                <div id="stat-active-sessions-detail" class="text-xl sm:text-2xl font-bold text-green-600">0</div>
                                <div class="text-xs sm:text-sm text-green-600">En Progreso</div>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-gray-50 rounded-lg">
                                <div id="stat-total-sessions" class="text-xl sm:text-2xl font-bold text-gray-600">0</div>
                                <div class="text-xs sm:text-sm text-gray-600">Total Sesiones</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                            <button onclick="showCreateRoomModal()" 
                                    class="p-3 sm:p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-blue-600 mb-2 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900 text-xs sm:text-sm">Nueva Sala</h4>
                                <p class="text-xs text-gray-600 hidden sm:block">Crear sala de chat</p>
                            </button>
                            
                            <button onclick="showAssignAgentModal()" 
                                    class="p-3 sm:p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-green-600 mb-2 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900 text-xs sm:text-sm">Asignar</h4>
                                <p class="text-xs text-gray-600 hidden sm:block">Asignar a sala</p>
                            </button>
                            
                            <button onclick="showSection('reports')" 
                                    class="p-3 sm:p-4 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-purple-600 mb-2 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900 text-xs sm:text-sm">Reportes</h4>
                                <p class="text-xs text-gray-600 hidden sm:block">Ver métricas</p>
                            </button>
                            
                            <button onclick="adminClient.refreshDashboard()" 
                                    class="p-3 sm:p-4 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-red-600 mb-2 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900 text-xs sm:text-sm">Actualizar</h4>
                                <p class="text-xs text-gray-600 hidden sm:block">Refrescar</p>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Users Section -->
                <div id="users-section" class="section-content hidden p-4 sm:p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Gestión de Usuarios</h3>
                                    <p class="text-sm text-gray-600 mt-1">Crear y administrar usuarios del sistema</p>
                                </div>
                                <button onclick="showCreateUserModal()" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 btn-responsive">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Nuevo Usuario
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-4 sm:p-6">
                            <div id="usersContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando usuarios...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rooms Section -->
                <div id="rooms-section" class="section-content hidden p-4 sm:p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Gestión de Salas</h3>
                                    <p class="text-sm text-gray-600 mt-1">Crear, editar y administrar salas de chat</p>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="showDeletedRoomsModal()" 
                                            class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 btn-responsive">
                                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Papelera
                                    </button>
                                    <button onclick="showCreateRoomModal()" 
                                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 btn-responsive">
                                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Nueva Sala
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 sm:p-6">
                            <div id="roomsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando salas...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignments Section -->
                <div id="assignments-section" class="section-content hidden p-4 sm:p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Asignaciones de Agentes</h3>
                                    <p class="text-sm text-gray-600 mt-1">Gestionar asignaciones de agentes a salas</p>
                                </div>
                                <button onclick="showAssignAgentModal()" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 btn-responsive">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                    </svg>
                                    Nueva Asignación
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-4 sm:p-6">
                            <div id="assignmentsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando asignaciones...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Section -->
                <div id="reports-section" class="section-content hidden p-4 sm:p-6">
                    <div class="bg-white rounded-lg shadow mb-6">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                                        Dashboard de Reportes
                                    </h3>
                                    <p class="text-sm text-gray-600 mt-1">Análisis de performance y métricas del sistema</p>
                                </div>
                                
                                <div class="flex flex-col sm:flex-row gap-3 items-center">
                                    <div class="flex flex-col sm:flex-row gap-2 items-center">
                                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Rango de fechas:</label>
                                        <div class="flex gap-2">
                                            <input type="date" id="startDate" 
                                                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                            <span class="text-gray-500 self-center">—</span>
                                            <input type="date" id="endDate" 
                                                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <button onclick="adminClient.setQuickDateRange('today')" 
                                                class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-xs font-medium transition-colors">
                                            Hoy
                                        </button>
                                        <button onclick="adminClient.setQuickDateRange('week')" 
                                                class="px-3 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-xs font-medium transition-colors">
                                            7 días
                                        </button>
                                        <button onclick="adminClient.setQuickDateRange('month')" 
                                                class="px-3 py-2 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 text-xs font-medium transition-colors">
                                            30 días
                                        </button>
                                    </div>
                                    
                                    <button onclick="adminClient.loadReportsWithDateRange()" 
                                            class="px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:from-purple-700 hover:to-pink-700 btn-responsive font-medium shadow-md transition-all">
                                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Actualizar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="reportsContainer">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 sm:p-6 text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-blue-100 text-xs sm:text-sm font-medium">Total Chats</p>
                                        <p id="report-total-chats" class="text-2xl sm:text-3xl font-bold mt-1">0</p>
                                        <div class="flex items-center mt-2">
                                            <span id="report-chats-trend" class="text-xs sm:text-sm text-blue-100">
                                                <span id="report-chats-change">+0%</span> vs período anterior
                                            </span>
                                        </div>
                                    </div>
                                    <div class="bg-white/20 rounded-full p-3">
                                        <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 sm:p-6 text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-green-100 text-xs sm:text-sm font-medium">Chats Atendidos</p>
                                        <p id="report-attended-chats" class="text-2xl sm:text-3xl font-bold mt-1">0</p>
                                        <div class="flex items-center mt-2">
                                            <span id="report-attendance-rate" class="text-xs sm:text-sm text-green-100">
                                                <span id="report-attendance-percentage">0%</span> tasa de atención
                                            </span>
                                        </div>
                                    </div>
                                    <div class="bg-white/20 rounded-full p-3">
                                        <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div class="tiempo-promedio-card rounded-xl p-4 sm:p-6 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-orange-100 text-xs sm:text-sm font-medium">Tiempo Promedio</p>
                                        <p id="report-avg-duration" class="text-2xl sm:text-3xl font-bold mt-1">0m</p>
                                        <div class="flex items-center mt-2">
                                            <span id="report-duration-trend" class="text-xs sm:text-sm text-orange-100">
                                                <span id="report-duration-change">0m</span> vs promedio
                                            </span>
                                        </div>
                                    </div>
                                    <div class="bg-white/20 rounded-full p-3">
                                        <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 sm:p-6 text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-purple-100 text-xs sm:text-sm font-medium">Agentes Activos</p>
                                        <p id="report-active-agents" class="text-2xl sm:text-3xl font-bold mt-1">0</p>
                                        <div class="flex items-center mt-2">
                                            <span id="report-agents-trend" class="text-xs sm:text-sm text-purple-100">
                                                <span id="report-agents-online">0</span> en línea ahora
                                            </span>
                                        </div>
                                    </div>
                                    <div class="bg-white/20 rounded-full p-3">
                                        <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                                <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-6 py-4">
                                    <h4 class="text-white font-bold text-lg flex items-center">
                                        Performance de Chats
                                    </h4>
                                </div>
                                <div class="p-6">
                                    <div id="chatPerformanceContainer" class="space-y-4">
                                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg">
                                            <div>
                                                <p class="text-sm text-gray-600">Chats Completados</p>
                                                <p id="chat-completed-count" class="text-2xl font-bold text-blue-600">0</p>
                                            </div>
                                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                                <div id="chat-completed-progress" class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center">
                                                    <span id="chat-completed-percent" class="text-xs font-bold text-white">0%</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-red-50 to-pink-50 rounded-lg">
                                            <div>
                                                <p class="text-sm text-gray-600">Chats Abandonados</p>
                                                <p id="chat-abandoned-count" class="text-2xl font-bold text-red-600">0</p>
                                            </div>
                                            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
                                                <div id="chat-abandoned-progress" class="w-12 h-12 rounded-full bg-gradient-to-r from-red-400 to-red-600 flex items-center justify-center">
                                                    <span id="chat-abandoned-percent" class="text-xs font-bold text-white">0%</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg">
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="text-sm font-medium text-gray-700">Tasa de Oportunidad</span>
                                                <span id="opportunity-rate-value" class="text-lg font-bold text-green-600">0%</span>
                                            </div>
                                            <div class="w-full bg-green-200 rounded-full h-3">
                                                <div id="opportunity-rate-bar" class="bg-gradient-to-r from-green-400 to-green-600 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                                <div class="bg-gradient-to-r from-green-500 to-teal-600 px-6 py-4">
                                    <h4 class="text-white font-bold text-lg flex items-center">
                                        Rendimiento de Agentes
                                    </h4>
                                </div>
                                <div class="p-6">
                                    <div id="agentPerformanceContainer" class="space-y-4">
                                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-emerald-50 to-green-50 rounded-lg">
                                            <div>
                                                <p class="text-sm text-gray-600">Mensajes Enviados</p>
                                                <p id="agent-messages-count" class="text-2xl font-bold text-emerald-600">0</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm text-gray-600">Tiempo Respuesta</p>
                                                <p id="agent-response-time" class="text-xl font-bold text-orange-600">0m</p>
                                            </div>
                                        </div>

                                        <div id="topAgentsContainer" class="mt-6">
                                            <h5 class="font-semibold text-gray-800 mb-3 flex items-center">
                                                Top Agentes del Período
                                            </h5>
                                            <div id="topAgentsList" class="space-y-2">
                                                <div class="text-center py-4 text-gray-500">
                                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-green-600 mx-auto mb-2"></div>
                                                    <p class="text-sm">Cargando rankings...</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                       <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white rounded-xl shadow-lg p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h5 class="font-bold text-gray-800">Estado en Vivo</h5>
                                    <div class="flex items-center text-green-600">
                                        <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                                        <span class="text-xs font-medium">EN VIVO</span>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Sesiones Activas</span>
                                        <span id="live-active-sessions" class="font-bold text-blue-600">0</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">En Cola</span>
                                        <span id="live-waiting-sessions" class="font-bold text-orange-600">0</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Agentes Online</span>
                                        <span id="live-online-agents" class="font-bold text-green-600">0</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-xl shadow-lg p-6">
                                <h5 class="font-bold text-gray-800 mb-4">Período Seleccionado</h5>
                                <div class="space-y-3">
                                    <div class="text-center p-3 bg-gradient-to-r from-purple-100 to-pink-100 rounded-lg">
                                        <p class="text-xs text-gray-600">Desde</p>
                                        <p id="selected-start-date" class="font-bold text-purple-700">--</p>
                                    </div>
                                    <div class="text-center p-3 bg-gradient-to-r from-purple-100 to-pink-100 rounded-lg">
                                        <p class="text-xs text-gray-600">Hasta</p>
                                        <p id="selected-end-date" class="font-bold text-purple-700">--</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Section -->
                <div id="profile-section" class="section-content hidden p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Mi Perfil</h2>
                            <p class="text-gray-600 text-sm">Gestiona tu información personal y configuración de seguridad</p>
                        </div>
                    </div>

                    <!-- Contenedor del perfil -->
                    <div id="profileContainer">
                        <div class="text-center py-12">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mx-auto mb-4"></div>
                            <p class="text-gray-500">Cargando perfil...</p>
                        </div>
                    </div>
                </div>

                <!-- Config Section -->
                <div id="config-section" class="section-content hidden p-4 sm:p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Configuración del Sistema</h3>
                                    <p class="text-sm text-gray-600 mt-1">Parámetros y configuración general</p>
                                </div>
                                <button onclick="adminClient.saveConfig()" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 btn-responsive">
                                    Guardar Cambios
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-4 sm:p-6">
                            <div id="configContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando configuración...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full modal-content">
            <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Crear Nuevo Usuario</h3>
                
                <form id="createUserForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre Completo</label>
                        <input type="text" id="userName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Nombre del usuario" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="userEmail" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="usuario@ejemplo.com" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                        <input type="password" id="userPassword" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Mínimo 8 caracteres" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirmar Contraseña</label>
                        <input type="password" id="userPasswordConfirm" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Repetir contraseña" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rol</label>
                        <select id="userRole" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                            <option value="">Seleccionar rol...</option>
                            <option value="2">Agente</option>
                            <option value="3">Supervisor</option>
                            <option value="4">Administrador</option>
                        </select>
                    </div>
                </form>
                
                <div class="flex flex-col sm:flex-row gap-3 mt-6">
                    <button onclick="closeModal('createUserModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 btn-responsive">
                        Cancelar
                    </button>
                    <button onclick="adminClient.createUser()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 btn-responsive">
                        Crear Usuario
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Room Modal -->
    <div id="createRoomModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full modal-content">
            <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Crear Nueva Sala</h3>
                
                <form id="createRoomForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                        <input type="text" id="roomName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Nombre de la sala" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                        <textarea id="roomDescription" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" rows="3" placeholder="Descripción de la sala"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                            <select id="roomType" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="general">General</option>
                                <option value="medical">Médico</option>
                                <option value="support">Soporte</option>
                                <option value="emergency">Emergencia</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Máx. Agentes</label>
                            <input type="number" id="roomMaxAgents" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="10" min="1">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                        <select id="roomPriority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="1">Baja</option>
                            <option value="2">Media</option>
                            <option value="3">Alta</option>
                        </select>
                    </div>
                </form>
                
                <div class="flex flex-col sm:flex-row gap-3 mt-6">
                    <button onclick="closeModal('createRoomModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 btn-responsive">
                        Cancelar
                    </button>
                    <button onclick="adminClient.createRoom()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 btn-responsive">
                        Crear Sala
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div id="editRoomModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full modal-content">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Editar Sala</h3>
                    <button onclick="closeModal('editRoomModal')" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="editRoomForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                        <input type="text" id="editRoomName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                        <textarea id="editRoomDescription" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" rows="3"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                            <select id="editRoomType" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="general">General</option>
                                <option value="medical">Médico</option>
                                <option value="support">Soporte</option>
                                <option value="emergency">Emergencia</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Máximo Agentes</label>
                            <input type="number" id="editRoomMaxAgents" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" min="1">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                            <select id="editRoomPriority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="1">Baja</option>
                                <option value="2">Media</option>
                                <option value="3">Alta</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                            <select id="editRoomStatus" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="available">Disponible</option>
                                <option value="busy">Ocupada</option>
                                <option value="maintenance">Mantenimiento</option>
                                <option value="disabled">Deshabilitada</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="editRoomIsActive" class="mr-2 h-4 w-4 text-red-600 border-gray-300 rounded">
                        <label class="text-sm text-gray-700">Sala activa</label>
                    </div>
                </form>
                
                <div class="flex flex-col sm:flex-row gap-3 mt-6">
                    <button onclick="closeModal('editRoomModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 btn-responsive">
                        Cancelar
                    </button>
                    <button onclick="adminClient.saveRoomChanges()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 btn-responsive">
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Deleted Rooms Modal -->
    <div id="deletedRoomsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full modal-content">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Papelera de Salas</h3>
                        <p class="text-sm text-gray-600">Salas eliminadas que pueden ser restauradas</p>
                    </div>
                    <button onclick="closeModal('deletedRoomsModal')" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="deletedRoomsContainer" class="max-h-96 overflow-y-auto">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-600 mx-auto mb-4"></div>
                        <p class="text-gray-500">Cargando salas eliminadas...</p>
                    </div>
                </div>
                
                <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-500">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Las salas eliminadas se pueden restaurar completamente
                    </p>
                    <button onclick="closeModal('deletedRoomsModal')" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Agent Modal -->
    <div id="assignAgentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full modal-content">
            <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Asignar Agente a Sala</h3>
                
                <form id="assignAgentForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Agente</label>
                        <select id="agentSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                            <option value="">Seleccionar agente...</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sala</label>
                        <select id="roomSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                            <option value="">Seleccionar sala...</option>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                            <select id="assignmentPriority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="1">Baja</option>
                                <option value="2">Media</option>
                                <option value="3">Alta</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Chats Máximos</label>
                            <input type="number" id="maxConcurrentChats" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="5" min="1">
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="isPrimaryAgent" class="mr-2 h-4 w-4 text-red-600 border-gray-300 rounded">
                        <label class="text-sm text-gray-700">Agente principal</label>
                    </div>
                </form>
                
                <div class="flex flex-col sm:flex-row gap-3 mt-6">
                    <button onclick="closeModal('assignAgentModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 btn-responsive">
                        Cancelar
                    </button>
                    <button onclick="adminClient.assignAgent()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 btn-responsive">
                        Asignar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Assignment Modal -->
    <div id="editAssignmentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full modal-content">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Editar Asignación</h3>
                    <button onclick="closeModal('editAssignmentModal')" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="editAssignmentForm" class="space-y-4">
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-sm text-gray-600">Agente: <span id="editAssignmentAgentName" class="font-medium"></span></p>
                        <p class="text-sm text-gray-600">Sala: <span id="editAssignmentRoomName" class="font-medium"></span></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                            <select id="editAssignmentPriority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="1">Baja</option>
                                <option value="2">Media</option>
                                <option value="3">Alta</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Chats Máximos</label>
                            <input type="number" id="editMaxConcurrentChats" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" min="1">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                        <select id="editAssignmentStatus" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                            <option value="suspended">Suspendido</option>
                        </select>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="editIsPrimaryAgent" class="mr-2 h-4 w-4 text-red-600 border-gray-300 rounded">
                        <label class="text-sm text-gray-700">Agente principal</label>
                    </div>
                    
                    <div class="border-t pt-4">
                        <button type="button" onclick="adminClient.showScheduleModal()" 
                                class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 btn-responsive">
                            Gestionar Horarios
                        </button>
                    </div>
                </form>
                
                <div class="flex flex-col sm:flex-row gap-3 mt-6">
                    <button onclick="closeModal('editAssignmentModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 btn-responsive">
                        Cancelar
                    </button>
                    <button onclick="adminClient.saveAssignmentChanges()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 btn-responsive">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full modal-content">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Gestión de Horarios</h3>
                    <button onclick="closeModal('scheduleModal')" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-lg mb-4">
                    <p class="text-sm text-gray-600">Agente: <span id="scheduleAgentName" class="font-medium"></span></p>
                    <p class="text-sm text-gray-600">Sala: <span id="scheduleRoomName" class="font-medium"></span></p>
                </div>
                
                <div id="scheduleContainer" class="space-y-3 max-h-96 overflow-y-auto">
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3 mt-6">
                    <button onclick="closeModal('scheduleModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 btn-responsive">
                        Cancelar
                    </button>
                    <button onclick="adminClient.saveScheduleChanges()" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 btn-responsive">
                        Guardar Horarios
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Editar Perfil -->
    <div id="editProfileModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Editar Perfil</h3>
                    <button onclick="closeModal('editProfileModal')" 
                            class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="editProfileForm" onsubmit="event.preventDefault(); adminClient.updateProfile();" class="p-6 space-y-4">
                    <div>
                        <label for="profileName" class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                        <input type="text" id="profileName" name="name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            placeholder="Tu nombre completo">
                    </div>
                    
                    <div>
                        <label for="profileEmail" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="profileEmail" name="email" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            placeholder="tu@email.com">
                    </div>
                    
                    <div class="flex gap-3 pt-4">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500">
                            Guardar Cambios
                        </button>
                        <button type="button" onclick="closeModal('editProfileModal')" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cambiar Contraseña -->
    <div id="changePasswordModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Cambiar Contraseña</h3>
                    <button onclick="closeModal('changePasswordModal')" 
                            class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="changePasswordForm" onsubmit="event.preventDefault(); adminClient.changeOwnPassword();" class="p-6 space-y-4">
                    <div>
                        <label for="currentPassword" class="block text-sm font-medium text-gray-700 mb-1">Contraseña Actual</label>
                        <input type="password" id="currentPassword" name="currentPassword" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            placeholder="Tu contraseña actual">
                    </div>
                    
                    <div>
                        <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">Nueva Contraseña</label>
                        <input type="password" id="newPassword" name="newPassword" required
                            oninput="adminClient.validatePasswordStrength('newPassword', 'newPasswordStrength')"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            placeholder="Mínimo 8 caracteres">
                        <div id="newPasswordStrength" class="mt-1"></div>
                    </div>
                    
                    <div>
                        <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required
                            oninput="adminClient.validatePasswordMatch('newPassword', 'confirmPassword', 'passwordMatchIndicator')"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            placeholder="Repite la nueva contraseña">
                        <div id="passwordMatchIndicator" class="mt-1"></div>
                    </div>
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <div class="flex">
                            <svg class="w-5 h-5 text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <p class="text-sm text-yellow-800">
                                Evita usar caracteres especiales como ñ, acentos o símbolos no estándar.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 pt-4">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500">
                            Cambiar Contraseña
                        </button>
                        <button type="button" onclick="closeModal('changePasswordModal')" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Resetear Contraseña de Usuarios -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Resetear Contraseña de Usuario</h3>
                    <button onclick="closeModal('resetPasswordModal')" 
                            class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="resetPasswordForm" onsubmit="event.preventDefault(); adminClient.resetUserPassword();" class="p-6 space-y-4">
                    <div>
                        <label for="resetUserSelect" class="block text-sm font-medium text-gray-700 mb-1">Seleccionar Usuario</label>
                        <select id="resetUserSelect" name="userId" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <option value="">Cargando usuarios...</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="resetPassword" class="block text-sm font-medium text-gray-700 mb-1">Nueva Contraseña</label>
                        <input type="password" id="resetPassword" name="password" required
                            oninput="adminClient.validatePasswordStrength('resetPassword', 'resetPasswordStrength')"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            placeholder="Mínimo 8 caracteres">
                        <div id="resetPasswordStrength" class="mt-1"></div>
                    </div>
                    
                    <div>
                        <label for="confirmResetPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirmar Contraseña</label>
                        <input type="password" id="confirmResetPassword" name="confirmPassword" required
                            oninput="adminClient.validatePasswordMatch('resetPassword', 'confirmResetPassword', 'resetPasswordMatchIndicator')"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            placeholder="Repite la contraseña">
                        <div id="resetPasswordMatchIndicator" class="mt-1"></div>
                    </div>
                    
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <div class="flex">
                            <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <div class="text-sm text-red-800">
                                <p class="font-medium">Privilegio de Administrador</p>
                                <p>Evita caracteres especiales (ñ, acentos). Se cerrarán todas las sesiones activas del usuario.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 pt-4">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500">
                            Resetear Contraseña
                        </button>
                        <button type="button" onclick="closeModal('resetPasswordModal')" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="assets/js/admin-app.js"></script>
</body>
</html>