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

// Detectar automáticamente la URL base
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$adminServiceUrl = $protocol . '://' . $host . ':3013';
$authServiceUrl = $protocol . '://' . $host . ':3010';

// Fallback a URLs por defecto si es necesario
if (strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false) {
    // En producción, ajustar según sea necesario
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
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Navigation Styles */
        .nav-link.active { background: #dc2626; color: white; }
        .nav-link { 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
            padding: 0.75rem 1rem; 
            font-size: 0.875rem; 
            font-weight: 500; 
            color: #6b7280; 
            text-decoration: none; 
            border-radius: 0.5rem; 
            transition: all 0.15s ease-in-out; 
        }
        .nav-link:hover { background: #f3f4f6; color: #1f2937; }
        
        /* Card Styles */
        .stats-card { 
            background: white; 
            border-radius: 0.75rem; 
            padding: 1.5rem; 
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); 
            border: 1px solid #e5e7eb; 
        }
        .room-card { 
            background: white; 
            border: 1px solid #e5e7eb; 
            border-radius: 0.75rem; 
            padding: 1.5rem; 
            transition: all 0.15s ease-in-out; 
        }
        .room-card:hover { 
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); 
            border-color: #dc2626; 
        }
        .assignment-row { 
            background: white; 
            border: 1px solid #e5e7eb; 
            border-radius: 0.5rem; 
            padding: 1rem; 
            margin-bottom: 0.5rem; 
        }
        
        /* Status Styles */
        .status-active { color: #059669; background: #d1fae5; }
        .status-inactive { color: #dc2626; background: #fee2e2; }
        .status-maintenance { color: #d97706; background: #fef3c7; }
        
        /* Modal Styles */
        .modal-overlay { background-color: rgba(0, 0, 0, 0.5); }
        .modal-content { max-height: 90vh; overflow-y: auto; }
        
        /* Schedule Styles */
        .schedule-day { 
            border: 1px solid #e5e7eb; 
            border-radius: 0.5rem; 
            padding: 1rem; 
            margin-bottom: 0.5rem; 
        }
        .schedule-day.active { 
            background: #f0f9ff; 
            border-color: #0369a1; 
        }
        
        /* Mobile Navigation */
        .mobile-nav-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .mobile-nav-backdrop.active {
            opacity: 1;
            visibility: visible;
        }
        
        .mobile-nav {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        .mobile-nav.active {
            transform: translateX(0);
        }
        
        /* Responsive Grid Adjustments */
        @media (max-width: 768px) {
            .stats-card {
                padding: 1rem;
            }
            
            .room-card {
                padding: 1rem;
            }
            
            .assignment-row {
                padding: 0.75rem;
            }
            
            .modal-content {
                margin: 1rem;
                max-height: calc(100vh - 2rem);
            }
        }
        
        /* Mobile Table Styles */
        @media (max-width: 640px) {
            .mobile-card {
                display: block !important;
            }
            
            .mobile-card td {
                display: block !important;
                text-align: right !important;
                border: none !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            .mobile-card td:before {
                content: attr(data-label) ": ";
                float: left;
                font-weight: bold;
                color: #4b5563;
            }
        }
        
        /* Button Responsive Styles */
        @media (max-width: 640px) {
            .btn-responsive {
                font-size: 0.75rem !important;
                padding: 0.375rem 0.75rem !important;
            }
            
            .btn-icon-only {
                padding: 0.375rem !important;
            }
        }

        /* Fix para asegurar que el gradiente del tiempo promedio se aplique correctamente */
        .tiempo-promedio-card {
            background: linear-gradient(135deg, #f97316, #ea580c) !important;
            color: white !important;
        }
        
        .tiempo-promedio-card * {
            color: white !important;
        }
    </style>
</head>
<body class="h-full bg-gray-50">
    <!-- Mobile Navigation Backdrop -->
    <div class="mobile-nav-backdrop lg:hidden" id="mobileNavBackdrop" onclick="closeMobileNav()"></div>
    
    <div class="min-h-full flex">
        <!-- Desktop Sidebar -->
        <div class="hidden lg:flex w-64 bg-white border-r border-gray-200 flex-col">
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
                    <!-- Navigation items will be populated by JavaScript -->
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
        
        <!-- Mobile Sidebar -->
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
                    <!-- Navigation items will be populated by JavaScript -->
                </div>
            </nav>
                
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-red-600 text-white rounded-full flex items-center justify-center font-semibold text-sm">
                        <?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate text-sm"><?= htmlspecialchars($user['name'] ?? 'Admin') ?></p>
                        <p class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($userRole) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200">
                <div class="px-4 sm:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <!-- Mobile Menu Button -->
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
                    <!-- Stats Cards -->
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

                    <!-- Session Status Detail -->
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

                    <!-- Quick Actions -->
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

                <!-- Rooms Section -->
                <div id="rooms-section" class="section-content hidden p-4 sm:p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Gestión de Salas</h3>
                                    <p class="text-sm text-gray-600 mt-1">Crear, editar y administrar salas de chat</p>
                                </div>
                                <button onclick="showCreateRoomModal()" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 btn-responsive">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Nueva Sala
                                </button>
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
                    <!-- Header with Date Range Selector -->
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

                    <!-- Main Dashboard Grid -->
                    <div id="reportsContainer">
                        <!-- Key Metrics Cards -->
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

                            <!-- CORRECCIÓN: Card de Tiempo Promedio con clase forzada -->
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

                        <!-- Detailed Analytics Section -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                            <!-- Chat Performance Chart -->
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

                            <!-- Agent Performance -->
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

                                        <div class="p-4 bg-gradient-to-r from-yellow-50 to-amber-50 rounded-lg">
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="text-sm font-medium text-gray-700">Meta Alcanzada</span>
                                                <span id="goal-achievement-value" class="text-lg font-bold text-amber-600">0%</span>
                                            </div>
                                            <div class="w-full bg-amber-200 rounded-full h-3 mb-2">
                                                <div id="goal-achievement-bar" class="bg-gradient-to-r from-amber-400 to-amber-600 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
                                            </div>
                                            <div class="flex justify-between text-xs text-gray-600">
                                                <span>Chats en meta: <span id="chats-within-goal" class="font-semibold">0</span></span>
                                                <span>Total chats: <span id="agent-total-chats" class="font-semibold">0</span></span>
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

                        <!-- Real-time Indicators -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                                <h5 class="font-bold text-gray-800 mb-4">Métricas Rápidas</h5>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Tiempo Promedio Hoy</span>
                                        <span id="quick-avg-time" class="font-bold text-orange-600">0m</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Satisfacción</span>
                                        <span id="quick-satisfaction" class="font-bold text-pink-600">0%</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Resolución</span>
                                        <span id="quick-resolution" class="font-bold text-teal-600">0%</span>
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

    <!-- Modals -->
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

    <script>
        class AdminClient {
            constructor() {
                this.adminServiceUrl = 'http://187.33.158.246/admin';
                this.authServiceUrl = 'http://187.33.158.246/auth';
                this.refreshInterval = null;
                this.refreshIntervalTime = 30000;
                this.currentEditingRoom = null;
                this.currentEditingAssignment = null;
                this.currentScheduleAssignment = null;
                this.roomColors = [
                    'bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-pink-500', 
                    'bg-yellow-500', 'bg-indigo-500', 'bg-red-500', 'bg-orange-500',
                    'bg-teal-500', 'bg-cyan-500', 'bg-lime-500', 'bg-emerald-500'
                ];
                
                this.initializeNavigation();
            }

            initializeNavigation() {
                const navigationItems = [
                    {
                        id: 'dashboard',
                        name: 'Dashboard',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                               </svg>`,
                        active: true
                    },
                    {
                        id: 'rooms',
                        name: 'Salas',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                               </svg>`
                    },
                    {
                        id: 'assignments',
                        name: 'Asignaciones',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                               </svg>`
                    },
                    {
                        id: 'reports',
                        name: 'Reportes',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                               </svg>`
                    },
                    {
                        id: 'config',
                        name: 'Configuración',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                               </svg>`
                    }
                ];

                // Populate desktop navigation
                const desktopNav = document.getElementById('desktopNav');
                if (desktopNav) {
                    desktopNav.innerHTML = navigationItems.map(item => `
                        <a href="#${item.id}" onclick="showSection('${item.id}'); closeMobileNav();" 
                           id="nav-${item.id}" class="nav-link ${item.active ? 'active' : ''}">
                            ${item.icon}
                            ${item.name}
                        </a>
                    `).join('');
                }

                // Populate mobile navigation
                const mobileNavItems = document.getElementById('mobileNavItems');
                if (mobileNavItems) {
                    mobileNavItems.innerHTML = navigationItems.map(item => `
                        <a href="#${item.id}" onclick="showSection('${item.id}'); closeMobileNav();" 
                           id="mobile-nav-${item.id}" class="nav-link ${item.active ? 'active' : ''}">
                            ${item.icon}
                            ${item.name}
                        </a>
                    `).join('');
                }
            }

            getToken() {
                const phpTokenMeta = document.querySelector('meta[name="admin-token"]')?.content;
                if (phpTokenMeta && phpTokenMeta.trim() !== '') {
                    return phpTokenMeta;
                }
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
                        return null;
                    }
                }
                return null;
            }

            getRandomColor() {
                return this.roomColors[Math.floor(Math.random() * this.roomColors.length)];
            }

            getColorForRoom(room) {
                if (room.color && room.color.trim() !== '') {
                    return room.color;
                }
                
                if (room.id) {
                    const index = parseInt(room.id.toString().slice(-1)) % this.roomColors.length;
                    return this.roomColors[index];
                }
                
                if (room.name) {
                    let hash = 0;
                    for (let i = 0; i < room.name.length; i++) {
                        hash = room.name.charCodeAt(i) + ((hash << 5) - hash);
                    }
                    const index = Math.abs(hash) % this.roomColors.length;
                    return this.roomColors[index];
                }
                
                return this.roomColors[0];
            }

            async loadDashboard() {
                try {
                    console.log('🔄 Cargando dashboard desde backend...', this.adminServiceUrl);
                    
                    const response = await fetch(`${this.adminServiceUrl}/reports/dashboard`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    console.log('📊 Dashboard response status:', response.status);

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const result = await response.json();
                    console.log('📊 Dashboard data received:', result);

                    let dashboardData = null;
                    
                    if (result.success && result.data) {
                        dashboardData = result.data;
                    } else if (result.data) {
                        dashboardData = result.data;
                    } else if (result.dashboard_data || result.metrics) {
                        dashboardData = result;
                    } else {
                        console.warn('⚠️ Estructura de dashboard inesperada, usando datos disponibles:', Object.keys(result));
                        dashboardData = result;
                    }
                    
                    if (dashboardData) {
                        this.updateDashboardStats(dashboardData);
                        return dashboardData;
                    } else {
                        throw new Error('No se encontraron datos de dashboard válidos');
                    }
                    
                } catch (error) {
                    console.error('❌ Error en dashboard:', error);
                    this.updateDashboardStats({});
                    this.showError('Error cargando dashboard: ' + error.message);
                    throw error;
                }
            }

            updateDashboardStats(data) {
                try {
                    console.log('🔄 Actualizando dashboard stats:', data);
                    
                    const dashboardData = data.dashboard_data || {};
                    const metrics = data.metrics || {};
                    const summary = metrics.summary || {};
                    
                    const sessions = dashboardData.sessions || {};
                    const agents = dashboardData.agents || {};
                    const rooms = dashboardData.rooms || {};
                    
                    const updates = {
                        'stat-total-rooms': rooms.total || summary.active_rooms || 0,
                        'stat-total-agents': agents.total || summary.online_agents || 0,
                        'stat-active-sessions': sessions.active || summary.active_sessions || 0,
                        'stat-completed-sessions': sessions.completed || 0,
                        'stat-waiting-sessions': sessions.waiting || summary.waiting_sessions || 0,
                        'stat-total-sessions': sessions.total || 0,
                        'stat-active-sessions-detail': sessions.active || summary.active_sessions || 0
                    };

                    Object.entries(updates).forEach(([id, value]) => {
                        const element = document.getElementById(id);
                        if (element) {
                            element.textContent = value;
                            console.log(`✅ Updated ${id}: ${value}`);
                        } else {
                            console.warn(`⚠️ Element ${id} not found`);
                        }
                    });
                    
                } catch (error) {
                    console.error('❌ Error actualizando dashboard stats:', error);
                }
            }

            async refreshDashboard() {
                this.showNotification('Actualizando métricas...', 'info');
                await this.loadDashboard();
                this.showNotification('Métricas actualizadas', 'success');
            }

            async loadRooms() {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms?include_stats=true`, {
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
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
                        ${rooms.map(room => this.createRoomCard(room)).join('')}
                    </div>
                `;
            }

            createRoomCard(room) {
                const statusClass = this.getRoomStatusClass(room.status);
                const statusText = this.getRoomStatusText(room.status);
                const roomColor = this.getColorForRoom(room);
                
                return `
                    <div class="room-card">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 sm:w-12 sm:h-12 ${roomColor} rounded-lg"></div>
                                <div>
                                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900 truncate">${room.name}</h3>
                                    <p class="text-xs sm:text-sm text-gray-500 capitalize">${room.room_type || 'General'}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 ${statusClass} text-xs font-medium rounded-full">${statusText}</span>
                                ${room.is_active ? 
                                    '<div class="w-2 h-2 bg-green-500 rounded-full" title="Activa"></div>' : 
                                    '<div class="w-2 h-2 bg-gray-300 rounded-full" title="Inactiva"></div>'
                                }
                            </div>
                        </div>
                        
                        <p class="text-gray-600 text-xs sm:text-sm mb-4 line-clamp-2">${room.description || 'Sin descripción'}</p>
                        
                        <div class="grid grid-cols-3 gap-2 sm:gap-4 text-center mb-4">
                            <div>
                                <div class="text-sm sm:text-lg font-bold text-blue-600">${room.max_agents || 10}</div>
                                <div class="text-xs text-gray-500">Máx.</div>
                            </div>
                            <div>
                                <div class="text-sm sm:text-lg font-bold text-green-600">${room.assigned_agents || 0}</div>
                                <div class="text-xs text-gray-500">Asign.</div>
                            </div>
                            <div>
                                <div class="text-sm sm:text-lg font-bold text-purple-600">${room.active_agents || 0}</div>
                                <div class="text-xs text-gray-500">Activos</div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button onclick="adminClient.editRoom('${room.id}')" 
                                    class="flex-1 px-2 py-1 sm:px-3 sm:py-2 bg-blue-600 text-white text-xs sm:text-sm rounded hover:bg-blue-700 btn-responsive">
                                Editar
                            </button>
                            <button onclick="adminClient.toggleRoomStatus('${room.id}')" 
                                    class="flex-1 px-2 py-1 sm:px-3 sm:py-2 bg-yellow-600 text-white text-xs sm:text-sm rounded hover:bg-yellow-700 btn-responsive">
                                ${room.is_active ? 'Desact.' : 'Activar'}
                            </button>
                            <button onclick="adminClient.deleteRoom('${room.id}')" 
                                    class="px-2 py-1 sm:px-3 sm:py-2 bg-red-600 text-white text-xs sm:text-sm rounded hover:bg-red-700 btn-icon-only">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    const room_type = document.getElementById('roomType').value;
                    const max_agents = parseInt(document.getElementById('roomMaxAgents').value);
                    const priority = parseInt(document.getElementById('roomPriority').value);
                    const color = this.getRandomColor();
                    
                    if (!name) {
                        this.showError('El nombre es requerido');
                        return;
                    }
                    
                    const response = await fetch(`${this.adminServiceUrl}/rooms`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            name,
                            description,
                            room_type,
                            max_agents,
                            priority,
                            color
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Sala creada exitosamente');
                        closeModal('createRoomModal');
                        await this.loadRooms();
                        
                        document.getElementById('createRoomForm').reset();
                    } else {
                        throw new Error(result.message || 'Error creando sala');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async editRoom(roomId) {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        const rooms = result.data?.rooms || result.rooms || [];
                        const room = rooms.find(r => r.id === roomId);
                        
                        if (room) {
                            this.currentEditingRoom = room;
                            this.populateEditRoomModal(room);
                            document.getElementById('editRoomModal').classList.remove('hidden');
                        } else {
                            this.showError('Sala no encontrada');
                        }
                    }
                    
                } catch (error) {
                    this.showError('Error cargando datos de sala');
                }
            }

            populateEditRoomModal(room) {
                document.getElementById('editRoomName').value = room.name || '';
                document.getElementById('editRoomDescription').value = room.description || '';
                document.getElementById('editRoomType').value = room.room_type || 'general';
                document.getElementById('editRoomMaxAgents').value = room.max_agents || 10;
                document.getElementById('editRoomPriority').value = room.priority || 1;
                document.getElementById('editRoomStatus').value = room.status || 'available';
                document.getElementById('editRoomIsActive').checked = room.is_active !== false;
            }

            async saveRoomChanges() {
                try {
                    if (!this.currentEditingRoom) {
                        this.showError('No hay sala seleccionada para editar');
                        return;
                    }
                    
                    const updateData = {
                        name: document.getElementById('editRoomName').value.trim(),
                        description: document.getElementById('editRoomDescription').value.trim(),
                        room_type: document.getElementById('editRoomType').value,
                        max_agents: parseInt(document.getElementById('editRoomMaxAgents').value),
                        priority: parseInt(document.getElementById('editRoomPriority').value),
                        status: document.getElementById('editRoomStatus').value,
                        is_active: document.getElementById('editRoomIsActive').checked
                    };
                    
                    if (!updateData.name) {
                        this.showError('El nombre es requerido');
                        return;
                    }
                    
                    const response = await fetch(`${this.adminServiceUrl}/rooms/${this.currentEditingRoom.id}`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify(updateData)
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Sala actualizada exitosamente');
                        closeModal('editRoomModal');
                        this.currentEditingRoom = null;
                        await this.loadRooms();
                    } else {
                        throw new Error(result.message || 'Error actualizando sala');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async toggleRoomStatus(roomId) {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms/${roomId}/toggle`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            reason: 'Cambio manual desde panel de administrador'
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Estado de sala actualizado');
                        await this.loadRooms();
                    } else {
                        throw new Error(result.message || 'Error actualizando estado');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async deleteRoom(roomId) {
                if (!confirm('¿Estás seguro de eliminar esta sala? Esta acción no se puede deshacer.')) {
                    return;
                }
                
                try {
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
                    this.showError('Error: ' + error.message);
                }
            }

            async loadAssignments() {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/assignments?include_schedules=true`, {
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
                const statusClass = assignment.status === 'active' ? 'status-active' : 'status-inactive';
                
                return `
                    <div class="assignment-row">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center space-x-4">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs sm:text-sm font-semibold text-blue-700">${this.getAgentInitials(assignment.agent_name)}</span>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900 text-sm sm:text-base">${assignment.agent_name || 'Agente'}</h4>
                                    <p class="text-xs sm:text-sm text-gray-500">Sala: ${assignment.room_name || assignment.room_id}</p>
                                    <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-xs text-gray-500 mt-1">
                                        <span>Prior.: ${assignment.priority || 1}</span>
                                        <span>Máx.: ${assignment.max_concurrent_chats || 5}</span>
                                        ${assignment.is_primary_agent ? '<span class="text-yellow-600 font-medium">★ Principal</span>' : ''}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-4">
                                <span class="px-2 py-1 ${statusClass} text-xs font-medium rounded-full">
                                    ${assignment.status === 'active' ? 'Activo' : 'Inactivo'}
                                </span>
                                
                                <div class="flex gap-1 sm:gap-2">
                                    <button onclick="adminClient.editAssignment('${assignment.id}')" 
                                            class="px-2 py-1 sm:px-3 sm:py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 btn-responsive">
                                        Editar
                                    </button>
                                    <button onclick="adminClient.removeAssignment('${assignment.id}')" 
                                            class="px-2 py-1 sm:px-3 sm:py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 btn-responsive">
                                        Remover
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3 text-xs sm:text-sm text-gray-600">
                            <div class="flex flex-col sm:flex-row sm:space-x-4">
                                <span>Asignado: ${this.formatDate(assignment.assigned_at)}</span>
                                ${assignment.updated_at ? `<span>Actualizado: ${this.formatDate(assignment.updated_at)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }

            async loadAvailableAgents() {
                try {
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
                    //
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
                    //
                }
            }

            async assignAgent() {
                try {
                    const agent_id = document.getElementById('agentSelect').value;
                    const room_id = document.getElementById('roomSelect').value;
                    const priority = parseInt(document.getElementById('assignmentPriority').value);
                    const max_concurrent_chats = parseInt(document.getElementById('maxConcurrentChats').value);
                    const is_primary_agent = document.getElementById('isPrimaryAgent').checked;
                    
                    if (!agent_id || !room_id) {
                        this.showError('Selecciona agente y sala');
                        return;
                    }
                    
                    const response = await fetch(`${this.adminServiceUrl}/assignments`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            agent_id,
                            room_id,
                            priority,
                            max_concurrent_chats,
                            is_primary_agent
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Agente asignado exitosamente');
                        closeModal('assignAgentModal');
                        await this.loadAssignments();
                        
                        document.getElementById('assignAgentForm').reset();
                    } else {
                        throw new Error(result.message || 'Error asignando agente');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async editAssignment(assignmentId) {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/assignments`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        const assignments = result.data?.assignments || result.assignments || [];
                        const assignment = assignments.find(a => a.id === assignmentId);
                        
                        if (assignment) {
                            this.currentEditingAssignment = assignment;
                            this.populateEditAssignmentModal(assignment);
                            document.getElementById('editAssignmentModal').classList.remove('hidden');
                        } else {
                            this.showError('Asignación no encontrada');
                        }
                    }
                    
                } catch (error) {
                    this.showError('Error cargando datos de asignación');
                }
            }

            populateEditAssignmentModal(assignment) {
                document.getElementById('editAssignmentAgentName').textContent = assignment.agent_name || 'Sin nombre';
                document.getElementById('editAssignmentRoomName').textContent = assignment.room_name || assignment.room_id;
                document.getElementById('editAssignmentPriority').value = assignment.priority || 1;
                document.getElementById('editMaxConcurrentChats').value = assignment.max_concurrent_chats || 5;
                document.getElementById('editAssignmentStatus').value = assignment.status || 'active';
                document.getElementById('editIsPrimaryAgent').checked = assignment.is_primary_agent || false;
            }

            async saveAssignmentChanges() {
                try {
                    if (!this.currentEditingAssignment) {
                        this.showError('No hay asignación seleccionada para editar');
                        return;
                    }
                    
                    const updateData = {
                        priority: parseInt(document.getElementById('editAssignmentPriority').value),
                        max_concurrent_chats: parseInt(document.getElementById('editMaxConcurrentChats').value),
                        status: document.getElementById('editAssignmentStatus').value,
                        is_primary_agent: document.getElementById('editIsPrimaryAgent').checked
                    };
                    
                    const response = await fetch(`${this.adminServiceUrl}/assignments/${this.currentEditingAssignment.id}`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify(updateData)
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Asignación actualizada exitosamente');
                        closeModal('editAssignmentModal');
                        this.currentEditingAssignment = null;
                        await this.loadAssignments();
                    } else {
                        throw new Error(result.message || 'Error actualizando asignación');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async removeAssignment(assignmentId) {
                if (!confirm('¿Remover esta asignación?')) {
                    return;
                }
                
                try {
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
                    this.showError('Error: ' + error.message);
                }
            }

            async showScheduleModal() {
                try {
                    if (!this.currentEditingAssignment) {
                        this.showError('No hay asignación seleccionada');
                        return;
                    }
                    
                    this.currentScheduleAssignment = this.currentEditingAssignment;
                    
                    document.getElementById('scheduleAgentName').textContent = 
                        this.currentEditingAssignment.agent_name || 'Sin nombre';
                    document.getElementById('scheduleRoomName').textContent = 
                        this.currentEditingAssignment.room_name || this.currentEditingAssignment.room_id;
                    
                    await this.loadSchedulesForAssignment(this.currentEditingAssignment.id);
                    
                    document.getElementById('scheduleModal').classList.remove('hidden');
                    
                } catch (error) {
                    this.showError('Error mostrando modal de horarios');
                }
            }

            async loadSchedulesForAssignment(assignmentId) {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/assignments/${assignmentId}/schedule`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const schedules = result.data?.schedules || result.schedules || [];
                        
                        this.displaySchedules(schedules);
                        return schedules;
                    } else {
                        this.displaySchedules([]);
                    }
                    
                } catch (error) {
                    this.displaySchedules([]);
                }
            }

            displaySchedules(existingSchedules) {
                const container = document.getElementById('scheduleContainer');
                if (!container) return;

                const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                
                const schedulesByDay = {};
                existingSchedules.forEach(schedule => {
                    schedulesByDay[schedule.day_of_week] = schedule;
                });

                container.innerHTML = dayNames.map((dayName, dayIndex) => {
                    const existingSchedule = schedulesByDay[dayIndex];
                    const isActive = !!existingSchedule;
                    
                    return `
                        <div class="schedule-day ${isActive ? 'active' : ''}" data-day="${dayIndex}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="day_${dayIndex}" 
                                           class="day-checkbox" 
                                           ${isActive ? 'checked' : ''} 
                                           onchange="adminClient.toggleDay(${dayIndex})">
                                    <label for="day_${dayIndex}" class="font-medium text-gray-900 text-sm sm:text-base">${dayName}</label>
                                </div>
                                ${isActive ? '<span class="text-xs text-green-600 font-medium">Configurado</span>' : ''}
                            </div>
                            
                            <div class="schedule-times ${isActive ? '' : 'hidden'}" id="times_${dayIndex}">
                                <div class="grid grid-cols-2 gap-2 sm:gap-4">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Hora inicio</label>
                                        <input type="time" id="start_${dayIndex}" 
                                               class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                               value="${existingSchedule?.start_time || '09:00'}">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Hora fin</label>
                                        <input type="time" id="end_${dayIndex}" 
                                               class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                               value="${existingSchedule?.end_time || '17:00'}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            toggleDay(dayIndex) {
                const checkbox = document.getElementById(`day_${dayIndex}`);
                const timesContainer = document.getElementById(`times_${dayIndex}`);
                const dayContainer = document.querySelector(`[data-day="${dayIndex}"]`);
                
                if (checkbox.checked) {
                    timesContainer.classList.remove('hidden');
                    dayContainer.classList.add('active');
                } else {
                    timesContainer.classList.add('hidden');
                    dayContainer.classList.remove('active');
                }
            }

            normalizeTimeFormat(timeValue) {
                if (!timeValue) return '';
                
                const cleaned = timeValue.toString().trim();
                
                if (/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(cleaned)) {
                    return cleaned;
                }
                
                if (/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/.test(cleaned)) {
                    return cleaned.substring(0, 5);
                }
                
                const shortMatch = cleaned.match(/^(\d{1,2}):(\d{1,2})$/);
                if (shortMatch) {
                    const hour = shortMatch[1].padStart(2, '0');
                    const minute = shortMatch[2].padStart(2, '0');
                    return `${hour}:${minute}`;
                }
                
                return cleaned;
            }

            async saveScheduleChanges() {
                try {
                    if (!this.currentScheduleAssignment) {
                        this.showError('No hay asignación seleccionada');
                        return;
                    }
                    
                    const schedules = [];
                    const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    
                    for (let day = 0; day < 7; day++) {
                        const checkbox = document.getElementById(`day_${day}`);
                        
                        if (checkbox && checkbox.checked) {
                            const startTimeInput = document.getElementById(`start_${day}`);
                            const endTimeInput = document.getElementById(`end_${day}`);
                            
                            if (!startTimeInput || !endTimeInput) {
                                continue;
                            }
                            
                            const rawStartTime = startTimeInput.value;
                            const rawEndTime = endTimeInput.value;
                            
                            const startTime = this.normalizeTimeFormat(rawStartTime);
                            const endTime = this.normalizeTimeFormat(rawEndTime);
                            
                            if (!startTime || !endTime) {
                                this.showError(`Debe especificar hora de inicio y fin para ${dayNames[day]}`);
                                return;
                            }
                            
                            const timeRegex = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
                            
                            if (!timeRegex.test(startTime)) {
                                this.showError(`Formato de hora inicio inválido para ${dayNames[day]}. Use formato HH:MM`);
                                return;
                            }
                            
                            if (!timeRegex.test(endTime)) {
                                this.showError(`Formato de hora fin inválido para ${dayNames[day]}. Use formato HH:MM`);
                                return;
                            }
                            
                            const [startHour, startMinute] = startTime.split(':').map(Number);
                            const [endHour, endMinute] = endTime.split(':').map(Number);
                            
                            const startTotal = startHour * 60 + startMinute;
                            const endTotal = endHour * 60 + endMinute;
                            
                            if (startTotal >= endTotal) {
                                this.showError(`La hora de inicio debe ser menor que la hora de fin para ${dayNames[day]}`);
                                return;
                            }
                            
                            schedules.push({
                                day_of_week: parseInt(day),
                                start_time: startTime,
                                end_time: endTime,
                                timezone: 'America/Bogota',
                                is_available: true
                            });
                        }
                    }
                    
                    if (schedules.length === 0) {
                        this.showError('Debe seleccionar al menos un día con horarios');
                        return;
                    }
                    
                    const response = await fetch(`${this.adminServiceUrl}/assignments/${this.currentScheduleAssignment.id}/schedule`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({ schedules })
                    });
                    
                    let result;
                    try {
                        result = await response.json();
                    } catch (parseError) {
                        throw new Error('Error del servidor: respuesta inválida');
                    }
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Horarios guardados exitosamente');
                        closeModal('scheduleModal');
                        this.currentScheduleAssignment = null;
                        await this.loadAssignments();
                    } else {
                        let errorMessage = 'Error guardando horarios';
                        if (result.message) {
                            errorMessage = result.message;
                        } else if (result.error) {
                            errorMessage = result.error;
                        } else if (result.errors && Array.isArray(result.errors)) {
                            errorMessage = result.errors.join(', ');
                        }
                        
                        throw new Error(errorMessage);
                    }
                    
                } catch (error) {
                    this.showError(error.message || 'Error guardando horarios');
                }
            }

            async loadReports() {
                try {
                    console.log('🔄 Iniciando carga de reportes...');
                    
                    // Inicializar fechas por defecto si no están establecidas
                    this.initializeDateRanges();
                    
                    this.showLoadingStateForReports();
                    
                    // ✅ OBTENER FECHAS SELECCIONADAS
                    const startDate = document.getElementById('startDate')?.value;
                    const endDate = document.getElementById('endDate')?.value;
                    
                    console.log('📅 Fechas para reportes:', { startDate, endDate });
                    
                    // Cargar datos básicos CON FECHAS
                    const [chatStats, agentStats] = await Promise.allSettled([
                        this.loadChatStatistics(startDate, endDate), 
                        this.loadAgentStatistics(startDate, endDate)
                    ]);

                    console.log('📊 Resultados de carga:');
                    console.log('  - Chat stats:', chatStats.status, chatStats.value || chatStats.reason);
                    console.log('  - Agent stats:', agentStats.status, agentStats.value || agentStats.reason);

                    if (chatStats.status === 'fulfilled' && chatStats.value) {
                        this.displayChatStats(chatStats.value);
                    } else {
                        console.warn('⚠️ Chat stats failed:', chatStats.reason);
                        this.displayEmptyStats('chatStatsContainer', 'chat');
                    }

                    if (agentStats.status === 'fulfilled' && agentStats.value) {
                        this.displayAgentStats(agentStats.value);
                    } else {
                        console.warn('⚠️ Agent stats failed:', agentStats.reason);
                        this.displayEmptyStats('agentStatsContainer', 'agent');
                    }

                    // Actualizar live stats con datos del rango de fechas
                    await this.loadLiveStats(endDate);

                    // Actualizar fechas mostradas
                    this.updateDisplayedDateRange();

                    console.log('✅ Reportes cargados exitosamente');
                    
                } catch (error) {
                    console.error('❌ Error cargando reportes:', error);
                    this.showErrorStateForReports();
                }
            }

            async loadChatStatistics(startDate, endDate) {
                try {
                    console.log('💬 Cargando estadísticas de chat con fechas...', { startDate, endDate });
                    
                    // ✅ CONSTRUIR URL CON FECHAS ESPECÍFICAS
                    let url = `${this.adminServiceUrl}/reports/statistics`;
                    const params = new URLSearchParams();
                    
                    if (startDate && endDate) {
                        params.append('start_date', startDate);
                        params.append('end_date', endDate);
                        params.append('group_by', 'day');
                    }
                    
                    if (params.toString()) {
                        url += '?' + params.toString();
                    }
                    
                    console.log('💬 Chat stats URL:', url);
                    
                    const response = await fetch(url, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    console.log('💬 Chat stats response status:', response.status);

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const result = await response.json();
                    console.log('💬 Chat stats data received:', result);

                    if (result.statistics || result.data?.statistics || (result.success && result.statistics)) {
                        return result;
                    } else if (result.data) {
                        return result.data;
                    } else {
                        console.warn('⚠️ Estructura de respuesta inesperada, usando datos disponibles:', Object.keys(result));
                        return result;
                    }
                    
                } catch (error) {
                    console.error('❌ Error en chat stats:', error);
                    throw error;
                }
            }

            async loadAgentStatistics(startDate, endDate) {
                try {
                    console.log('👥 Cargando estadísticas de agentes con fechas...', { startDate, endDate });
                    
                    // ✅ CONSTRUIR URL CON FECHAS ESPECÍFICAS
                    let url = `${this.adminServiceUrl}/reports/agents`;
                    const params = new URLSearchParams();
                    
                    if (startDate && endDate) {
                        params.append('start_date', startDate);
                        params.append('end_date', endDate);
                    }
                    
                    if (params.toString()) {
                        url += '?' + params.toString();
                    }
                    
                    console.log('👥 Agent stats URL:', url);

                    const response = await fetch(url, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    console.log('👥 Agent stats response status:', response.status);

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const result = await response.json();
                    console.log('👥 Agent stats data received:', result);

                    if (result.agents || result.data?.agents || (result.success && result.agents)) {
                        return result;
                    } else if (result.data) {
                        return result.data;
                    } else {
                        console.warn('⚠️ Estructura de respuesta inesperada, usando datos disponibles:', Object.keys(result));
                        return result;
                    }
                    
                } catch (error) {
                    console.error('❌ Error en agent stats:', error);
                    throw error;
                }
            }

            // FUNCIÓN CORREGIDA: Solo usa datos reales del backend
            displayChatStats(data) {
                try {
                    console.log('💬 Mostrando chat stats:', data);
                    
                    let statistics = [];
                    
                    if (data.statistics && Array.isArray(data.statistics)) {
                        statistics = data.statistics;
                        console.log('💬 Found statistics in data.statistics:', statistics.length);
                    } else if (data.data && data.data.statistics && Array.isArray(data.data.statistics)) {
                        statistics = data.data.statistics;
                        console.log('💬 Found statistics in data.data.statistics:', statistics.length);
                    } else if (Array.isArray(data)) {
                        statistics = data;
                        console.log('💬 Data is array:', statistics.length);
                    } else {
                        console.warn('⚠️ No se encontraron estadísticas válidas en:', Object.keys(data));
                    }
                    
                    if (statistics.length === 0) {
                        console.warn('⚠️ Array de estadísticas está vacío');
                        this.displayEmptyStats('chatStatsContainer', 'chat');
                        return;
                    }

                    console.log('💬 Procesando', statistics.length, 'períodos de estadísticas');

                    let totalChats = 0;
                    let attendedChats = 0;
                    let abandonedChats = 0;
                    let totalResponseTime = 0;
                    let totalDuration = 0;
                    let responseTimeCount = 0;
                    let durationCount = 0;
                    let totalOpportunityRate = 0;
                    let periodCount = 0;

                    statistics.forEach((stat, index) => {
                        console.log(`💬 Procesando período ${index + 1}:`, stat);
                        
                        totalChats += stat.total_chats || 0;
                        attendedChats += stat.attended_chats || 0;
                        abandonedChats += stat.abandoned_chats || 0;
                        
                        if (stat.avg_response_time && stat.avg_response_time > 0) {
                            totalResponseTime += stat.avg_response_time;
                            responseTimeCount++;
                        }
                        
                        if (stat.avg_duration && stat.avg_duration > 0) {
                            totalDuration += stat.avg_duration;
                            durationCount++;
                        }

                        if (stat.opportunity_rate !== undefined) {
                            totalOpportunityRate += stat.opportunity_rate;
                            periodCount++;
                        }
                    });

                    // Calcular promedios reales únicamente
                    const avgResponseTime = responseTimeCount > 0 ? 
                        Math.round(totalResponseTime / responseTimeCount * 100) / 100 : 0;
                    const avgDuration = durationCount > 0 ? 
                        Math.round(totalDuration / durationCount * 100) / 100 : 0;
                    const attendanceRate = totalChats > 0 ? 
                        Math.round((attendedChats / totalChats) * 100 * 100) / 100 : 0;
                    const opportunityRate = periodCount > 0 ?
                        Math.round(totalOpportunityRate / periodCount * 100) / 100 : 0;
                    const abandonedRate = totalChats > 0 ? 
                        Math.round((abandonedChats / totalChats) * 100) : 0;

                    // Actualizar elementos del dashboard con datos reales únicamente
                    this.updateElement('report-total-chats', totalChats.toLocaleString());
                    this.updateElement('report-attended-chats', attendedChats.toLocaleString());
                    this.updateElement('report-avg-duration', avgDuration > 0 ? Math.round(avgDuration) + 'm' : '0m');
                    this.updateElement('report-attendance-percentage', attendanceRate + '%');
                    
                    // Chat performance section
                    this.updateElement('chat-completed-count', attendedChats.toLocaleString());
                    this.updateElement('chat-completed-percent', Math.round(attendanceRate) + '%');
                    this.updateElement('chat-abandoned-count', abandonedChats.toLocaleString());
                    this.updateElement('chat-abandoned-percent', abandonedRate + '%');
                    this.updateElement('opportunity-rate-value', opportunityRate + '%');
                    
                    // Update progress bars
                    this.updateProgressBar('chat-completed-progress', Math.round(attendanceRate));
                    this.updateProgressBar('chat-abandoned-progress', abandonedRate);
                    this.updateProgressBar('opportunity-rate-bar', opportunityRate);
                    
                    // Actualizar métricas rápidas con datos reales si están disponibles
                    if (avgDuration > 0) {
                        this.updateElement('quick-avg-time', Math.round(avgDuration) + 'm');
                    }
                    
                    console.log('✅ Chat stats displayed successfully');
                    
                } catch (error) {
                    console.error('❌ Error displaying chat stats:', error);
                    this.displayEmptyStats('chatStatsContainer', 'chat');
                }
            }

            // FUNCIÓN CORREGIDA: Solo usa datos reales del backend
            displayAgentStats(data) {
                try {
                    console.log('👥 Mostrando agent stats:', data);
                    
                    let agentCount = 0;
                    let agents = [];
                    
                    if (data.agent_count !== undefined && data.agents) {
                        agentCount = data.agent_count;
                        agents = data.agents;
                        console.log('👥 Found agents in direct properties:', agentCount, 'agents, array length:', agents.length);
                    } else if (data.data && data.data.agent_count !== undefined) {
                        agentCount = data.data.agent_count;
                        agents = data.data.agents || [];
                        console.log('👥 Found agents in data.agents:', agentCount, 'agents, array length:', agents.length);
                    } else if (Array.isArray(data.agents)) {
                        agents = data.agents;
                        agentCount = agents.length;
                        console.log('👥 Found agents array:', agents.length);
                    } else if (Array.isArray(data)) {
                        agents = data;
                        agentCount = agents.length;
                        console.log('👥 Data is agents array:', agents.length);
                    } else {
                        console.warn('⚠️ No se encontraron datos de agentes válidos en:', Object.keys(data));
                    }
                    
                    if (agents.length === 0 && agentCount === 0) {
                        console.warn('⚠️ Array de agentes está vacío');
                        this.displayEmptyStats('agentStatsContainer', 'agent');
                        return;
                    }

                    console.log('👥 Procesando', agents.length, 'agentes individuales');
                    
                    let totalChats = 0;
                    let totalMessages = 0;
                    let totalResponseTime = 0;
                    let responseTimeCount = 0;
                    let goalAchieved = 0;

                    agents.forEach((agent, index) => {
                        console.log(`👥 Procesando agente ${index + 1}:`, agent);
                        
                        totalChats += agent.total_chats || 0;
                        totalMessages += agent.total_messages_sent || 0;
                        goalAchieved += agent.chats_within_goal || 0;
                        
                        if (agent.avg_response_time && agent.avg_response_time > 0) {
                            totalResponseTime += agent.avg_response_time;
                            responseTimeCount++;
                        }
                    });

                    // Calcular promedios reales únicamente
                    const avgResponseTime = responseTimeCount > 0 ? 
                        Math.round(totalResponseTime / responseTimeCount * 100) / 100 : 0;
                    const goalAchievementRate = totalChats > 0 ?
                        Math.round((goalAchieved / totalChats) * 100 * 100) / 100 : 0;

                    // Actualizar elementos del dashboard con datos reales únicamente
                    this.updateElement('report-active-agents', agentCount);
                    this.updateElement('report-agents-online', agents.length);
                    
                    // Agent performance section
                    this.updateElement('agent-messages-count', totalMessages.toLocaleString());
                    this.updateElement('agent-response-time', avgResponseTime > 0 ? avgResponseTime + 'm' : '0m');
                    this.updateElement('goal-achievement-value', Math.round(goalAchievementRate) + '%');
                    this.updateElement('chats-within-goal', goalAchieved.toLocaleString());
                    this.updateElement('agent-total-chats', totalChats.toLocaleString());
                    
                    // Update progress bar
                    this.updateProgressBar('goal-achievement-bar', Math.round(goalAchievementRate));
                    
                    // Display top agents
                    this.displayTopAgents(agents.slice(0, 5));
                    
                    console.log('✅ Agent stats displayed successfully');
                    
                } catch (error) {
                    console.error('❌ Error displaying agent stats:', error);
                    this.displayEmptyStats('agentStatsContainer', 'agent');
                }
            }

            displayTopAgents(topAgents) {
                const container = document.getElementById('topAgentsList');
                if (!container) {
                    console.warn('⚠️ topAgentsList container not found');
                    return;
                }
                
                if (!topAgents || topAgents.length === 0) {
                    container.innerHTML = '<p class="text-sm text-gray-500 text-center py-2">No hay datos de agentes disponibles</p>';
                    return;
                }
                
                container.innerHTML = topAgents.map((agent, index) => `
                    <div class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border">
                        <div class="flex items-center space-x-3">
                            <span class="text-sm font-bold text-gray-600">#${index + 1}</span>
                            <div>
                                <p class="font-semibold text-sm text-gray-800">${agent.agent_name || agent.name || 'Agente'}</p>
                                <p class="text-xs text-gray-500">${agent.total_chats || 0} chats</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-green-600">${Math.round((agent.chats_within_goal || 0) / Math.max(agent.total_chats || 1, 1) * 100)}%</p>
                            <p class="text-xs text-gray-500">${agent.avg_response_time || 0}m resp.</p>
                        </div>
                    </div>
                `).join('');
            }

            async loadLiveStats() {
                try {
                    console.log('🔴 Cargando estadísticas en vivo...');
                    
                    // Usar datos del dashboard
                    const dashboardData = await this.loadDashboard();
                    
                    if (dashboardData) {
                        this.displayLiveStats(dashboardData);
                    } else {
                        this.showEmptyLiveStats();
                    }
                    
                } catch (error) {
                    console.log('ℹ️ Live stats error, using empty data');
                    this.showEmptyLiveStats();
                }
            }

            // FUNCIÓN CORREGIDA: Solo usa datos reales, sin valores de prueba
            displayLiveStats(data) {
                try {
                    console.log('🔴 Mostrando live stats usando dashboard data:', data);

                    // Usar datos del dashboard existente
                    const dashboardData = data.dashboard_data || data;
                    const sessions = dashboardData.sessions || {};
                    const agents = dashboardData.agents || {};

                    // Actualizar indicadores en vivo con datos reales únicamente
                    this.updateElement('live-active-sessions', sessions.active || 0);
                    this.updateElement('live-waiting-sessions', sessions.waiting || 0);
                    this.updateElement('live-online-agents', agents.online || agents.total || 0);

                    // Solo usar datos reales - si no hay datos, mostrar 0
                    this.updateElement('quick-avg-time', '0m');
                    this.updateElement('quick-satisfaction', '0%');
                    this.updateElement('quick-resolution', '0%');

                } catch (error) {
                    console.error('❌ Error mostrando live stats:', error);
                    this.showEmptyLiveStats();
                }
            }

            // FUNCIÓN CORREGIDA: Solo valores 0, sin datos de prueba
            showEmptyLiveStats() {
                this.updateElement('live-active-sessions', 0);
                this.updateElement('live-waiting-sessions', 0);
                this.updateElement('live-online-agents', 0);
                this.updateElement('quick-avg-time', '0m');
                this.updateElement('quick-satisfaction', '0%');
                this.updateElement('quick-resolution', '0%');
            }

            displayEmptyStats(containerId, type) {
                console.log('Displaying empty stats for:', type);
                
                if (type === 'chat') {
                    this.updateElement('report-total-chats', '0');
                    this.updateElement('report-attended-chats', '0');
                    this.updateElement('report-avg-duration', '0m');
                    this.updateElement('chat-completed-count', '0');
                    this.updateElement('chat-abandoned-count', '0');
                    this.updateElement('opportunity-rate-value', '0%');
                    this.updateProgressBar('opportunity-rate-bar', 0);
                }
                
                if (type === 'agent') {
                    this.updateElement('report-active-agents', '0');
                    this.updateElement('agent-messages-count', '0');
                    this.updateElement('agent-response-time', '0m');
                    this.updateElement('goal-achievement-value', '0%');
                    this.updateProgressBar('goal-achievement-bar', 0);
                    
                    const container = document.getElementById('topAgentsList');
                    if (container) {
                        container.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No hay datos disponibles</p>';
                    }
                }
            }

            showLoadingStateForReports() {
                // Loading state para los cards principales
                this.updateElement('report-total-chats', '...');
                this.updateElement('report-attended-chats', '...');
                this.updateElement('report-avg-duration', '...');
                this.updateElement('report-active-agents', '...');
                
                // Loading state para top agents
                const topAgentsContainer = document.getElementById('topAgentsList');
                if (topAgentsContainer) {
                    topAgentsContainer.innerHTML = `
                        <div class="text-center py-4 text-gray-500">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-green-600 mx-auto mb-2"></div>
                            <p class="text-sm">Cargando datos...</p>
                        </div>
                    `;
                }
            }

            showErrorStateForReports() {
                this.updateElement('report-total-chats', '0');
                this.updateElement('report-attended-chats', '0');
                this.updateElement('report-avg-duration', '0m');
                this.updateElement('report-active-agents', '0');
                
                const topAgentsContainer = document.getElementById('topAgentsList');
                if (topAgentsContainer) {
                    topAgentsContainer.innerHTML = `
                        <div class="text-center py-4">
                            <p class="text-sm text-gray-500 mb-2">Error cargando datos</p>
                            <button onclick="adminClient.loadReports()" class="px-3 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600">
                                Reintentar
                            </button>
                        </div>
                    `;
                }
            }

            updateElement(id, value) {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value;
                } else {
                    console.warn(`⚠️ Element with id '${id}' not found`);
                }
            }

            updateProgressBar(id, percentage) {
                const element = document.getElementById(id);
                if (element) {
                    element.style.width = `${Math.min(Math.max(percentage, 0), 100)}%`;
                } else {
                    console.warn(`⚠️ Progress bar with id '${id}' not found`);
                }
            }

            initializeDateRanges() {
                const startDateInput = document.getElementById('startDate');
                const endDateInput = document.getElementById('endDate');
                
                if (!startDateInput?.value || !endDateInput?.value) {
                    const today = new Date();
                    const thirtyDaysAgo = new Date(today);
                    thirtyDaysAgo.setDate(today.getDate() - 30);
                    
                    if (startDateInput) startDateInput.value = thirtyDaysAgo.toISOString().split('T')[0];
                    if (endDateInput) endDateInput.value = today.toISOString().split('T')[0];
                }
            }

            setQuickDateRange(period) {
                const today = new Date();
                const startDateInput = document.getElementById('startDate');
                const endDateInput = document.getElementById('endDate');
                
                if (!startDateInput || !endDateInput) return;
                
                let startDate;
                
                switch(period) {
                    case 'today':
                        startDate = new Date(today);
                        break;
                    case 'week':
                        startDate = new Date(today);
                        startDate.setDate(today.getDate() - 7);
                        break;
                    case 'month':
                        startDate = new Date(today);
                        startDate.setDate(today.getDate() - 30);
                        break;
                    default:
                        return;
                }
                
                startDateInput.value = startDate.toISOString().split('T')[0];
                endDateInput.value = today.toISOString().split('T')[0];
                
                // Auto-cargar los reportes
                this.loadReportsWithDateRange();
            }

            async loadReportsWithDateRange() {
                const startDateInput = document.getElementById('startDate');
                const endDateInput = document.getElementById('endDate');
                
                if (!startDateInput?.value || !endDateInput?.value) {
                    this.showError('Por favor selecciona un rango de fechas válido');
                    return;
                }
                
                const startDate = startDateInput.value;
                const endDate = endDateInput.value;
                
                if (new Date(startDate) > new Date(endDate)) {
                    this.showError('La fecha de inicio debe ser menor que la fecha final');
                    return;
                }
                
                console.log('📅 Cargando reportes con rango de fechas:', { startDate, endDate });
                
                this.showNotification('Cargando reportes para el período seleccionado...', 'info');
                
                try {
                    // ✅ CARGAR REPORTES CON FECHAS ESPECÍFICAS
                    this.showLoadingStateForReports();
                    
                    const [chatStats, agentStats] = await Promise.allSettled([
                        this.loadChatStatistics(startDate, endDate),
                        this.loadAgentStatistics(startDate, endDate)
                    ]);

                    if (chatStats.status === 'fulfilled' && chatStats.value) {
                        this.displayChatStats(chatStats.value);
                    } else {
                        console.warn('⚠️ Chat stats failed:', chatStats.reason);
                        this.displayEmptyStats('chatStatsContainer', 'chat');
                    }

                    if (agentStats.status === 'fulfilled' && agentStats.value) {
                        this.displayAgentStats(agentStats.value);
                    } else {
                        console.warn('⚠️ Agent stats failed:', agentStats.reason);
                        this.displayEmptyStats('agentStatsContainer', 'agent');
                    }

                    // Actualizar live stats con datos del rango final
                    await this.loadLiveStats(endDate);

                    // Actualizar fechas mostradas
                    this.updateDisplayedDateRange();
                    
                    this.showNotification('Reportes actualizados exitosamente', 'success');
                    console.log('✅ Reportes cargados con fechas específicas');
                    
                } catch (error) {
                    console.error('Error en loadReportsWithDateRange:', error);
                    this.showNotification('Error cargando reportes', 'error');
                    this.displayEmptyStats('chatStatsContainer', 'chat');
                    this.displayEmptyStats('agentStatsContainer', 'agent');
                    this.showEmptyLiveStats();
                }
            }

            // CORRECCIÓN: Función de formato de fecha sin problema de zona horaria
            updateDisplayedDateRange() {
                const startDate = document.getElementById('startDate')?.value;
                const endDate = document.getElementById('endDate')?.value;
                
                if (startDate && endDate) {
                    const formatDate = (dateStr) => {
                        try {
                            // CORRECCIÓN: Usar formato local sin conversión de zona horaria
                            const [year, month, day] = dateStr.split('-');
                            const date = new Date(year, month - 1, day);
                            return date.toLocaleDateString('es-ES', {
                                day: '2-digit',
                                month: 'short'
                            });
                        } catch (error) {
                            return dateStr;
                        }
                    };
                    
                    this.updateElement('selected-start-date', formatDate(startDate));
                    this.updateElement('selected-end-date', formatDate(endDate));
                } else {
                    this.updateElement('selected-start-date', '--');
                    this.updateElement('selected-end-date', '--');
                }
            }

            async refreshReports() {
                try {
                    this.showNotification('Actualizando reportes...', 'info');
                    await this.loadReports();
                    this.showNotification('Reportes actualizados exitosamente', 'success');
                } catch (error) {
                    console.error('❌ Error refrescando reportes:', error);
                    this.showNotification('Error actualizando reportes', 'error');
                }
            }

            async loadConfig() {
                try {
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
                    this.displayConfig({});
                    this.showError('Error cargando configuración: ' + error.message);
                }
            }

            displayConfig(config) {
                const container = document.getElementById('configContainer');
                if (!container) return;

                container.innerHTML = `
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-4">Configuración General</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Timeout de Sesión (min)</label>
                                        <input type="number" id="sessionTimeout" value="${config.session_timeout || 30}" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Máx. Transferencias</label>
                                        <input type="number" id="maxTransfers" value="${config.max_transfers || 3}" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tiempo Respuesta (seg)</label>
                                        <input type="number" id="responseTime" value="${config.max_response_time || 300}" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-4">Notificaciones</h4>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="emailNotifications" ${config.email_notifications ? 'checked' : ''}
                                               class="mr-2 h-4 w-4 text-red-600 border-gray-300 rounded">
                                        <label class="text-sm text-gray-700">Email</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="smsNotifications" ${config.sms_notifications ? 'checked' : ''}
                                               class="mr-2 h-4 w-4 text-red-600 border-gray-300 rounded">
                                        <label class="text-sm text-gray-700">SMS</label>
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
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Versión:</span>
                                    <span class="font-medium block">${config.system_version || '2.0.0'}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Base de Datos:</span>
                                    <span class="font-medium block">${config.database_status || 'Conectada'}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Última Actualización:</span>
                                    <span class="font-medium block">${this.formatDate(config.last_update)}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Uptime:</span>
                                    <span class="font-medium block">${config.uptime || 'N/A'}</span>
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
                    this.showError('Error: ' + error.message);
                }
            }

            getRoomStatusClass(status) {
                const classes = {
                    'available': 'bg-green-100 text-green-800',
                    'busy': 'bg-yellow-100 text-yellow-800',
                    'maintenance': 'bg-orange-100 text-orange-800',
                    'disabled': 'bg-red-100 text-red-800'
                };
                return classes[status] || 'bg-gray-100 text-gray-800';
            }

            getRoomStatusText(status) {
                const texts = {
                    'available': 'Disponible',
                    'busy': 'Ocupada',
                    'maintenance': 'Mantenimiento',
                    'disabled': 'Deshabilitada'
                };
                return texts[status] || 'Desconocido';
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
                notification.className = `fixed top-4 right-4 z-50 p-3 sm:p-4 rounded-lg shadow-lg max-w-xs sm:max-w-sm text-white text-sm ${colors[type]}`;
                notification.innerHTML = `
                    <div class="flex items-center justify-between">
                        <span>${message}</span>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">×</button>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, duration);
            }

            async init() {
                try {
                    await this.loadDashboard();
                    this.startAutoRefresh();
                } catch (error) {
                    this.showError('Error de inicialización');
                }
            }

            startAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
                
                this.refreshInterval = setInterval(async () => {
                    try {
                        const activeSection = document.querySelector('.section-content:not(.hidden)');
                        if (activeSection) {
                            const sectionId = activeSection.id;
                            if (sectionId === 'dashboard-section') {
                                await this.loadDashboard();
                            } else if (sectionId === 'reports-section') {
                                await this.loadReports();
                            }
                        }
                    } catch (error) {
                        // Silent error, skip refresh
                    }
                }, this.refreshIntervalTime);
            }

            stopAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                    this.refreshInterval = null;
                }
            }

            destroy() {
                this.stopAutoRefresh();
            }
        }

        window.adminClient = new AdminClient();

        // Navigation Functions
        function showSection(sectionName) {
            // Close mobile nav
            closeMobileNav();
            
            // Update sections
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            
            // Update navigation active states (both desktop and mobile)
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(`${sectionName}-section`).classList.remove('hidden');
            document.getElementById(`nav-${sectionName}`)?.classList.add('active');
            document.getElementById(`mobile-nav-${sectionName}`)?.classList.add('active');
            
            // Update title
            const titles = {
                'dashboard': 'Dashboard',
                'rooms': 'Gestión de Salas',
                'assignments': 'Asignaciones',
                'reports': 'Reportes',
                'config': 'Configuración'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName];
            
            // Load section data
            switch(sectionName) {
                case 'dashboard':
                    adminClient.loadDashboard();
                    break;
                case 'rooms':
                    adminClient.loadRooms();
                    break;
                case 'assignments':
                    adminClient.loadAssignments();
                    break;
                case 'reports':
                    adminClient.loadReports();
                    break;
                case 'config':
                    adminClient.loadConfig();
                    break;
            }
        }

        // Mobile Navigation Functions
        function openMobileNav() {
            document.getElementById('mobileNav').classList.add('active');
            document.getElementById('mobileNavBackdrop').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileNav() {
            document.getElementById('mobileNav').classList.remove('active');
            document.getElementById('mobileNavBackdrop').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Modal Functions
        function showCreateRoomModal() {
            document.getElementById('createRoomModal').classList.remove('hidden');
        }

        function showAssignAgentModal() {
            adminClient.loadAvailableAgents();
            adminClient.loadRoomsForSelect();
            document.getElementById('assignAgentModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            
            if (modalId === 'editRoomModal') {
                adminClient.currentEditingRoom = null;
            } else if (modalId === 'editAssignmentModal') {
                adminClient.currentEditingAssignment = null;
            } else if (modalId === 'scheduleModal') {
                adminClient.currentScheduleAssignment = null;
            }
        }

        function logout() {
            if (confirm('¿Cerrar sesión?')) {
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = 'logout.php';
            }
        }

        function updateTime() {
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = new Date().toLocaleTimeString('es-ES');
            }
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', async () => {
            updateTime();
            setInterval(updateTime, 1000);
            
            try {
                await adminClient.init();
            } catch (error) {
                console.error('Error initializing admin client:', error);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                // Close mobile nav
                closeMobileNav();
                
                // Close modals
                document.querySelectorAll('.fixed.inset-0').forEach(modal => {
                    if (!modal.classList.contains('hidden') && modal.id !== 'mobileNavBackdrop') {
                        modal.classList.add('hidden');
                    }
                });
                
                // Reset editing states
                adminClient.currentEditingRoom = null;
                adminClient.currentEditingAssignment = null;
                adminClient.currentScheduleAssignment = null;
            }
        });

        // Handle window resize for mobile nav
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) { // lg breakpoint
                closeMobileNav();
            }
        });

        // Prevent mobile nav closing when clicking inside it
        document.getElementById('mobileNav')?.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    </script>
</body>
</html>