<?php
// public/admin.php - Panel de Administrador
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

// Solo admins pueden acceder
if ($userRole !== 'admin') {
    header("Location: /practicas/chat-frontend/public/index.php?error=not_admin");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador - <?= htmlspecialchars($user['name'] ?? 'Admin') ?></title>
    
    <meta name="admin-token" content="<?= $_SESSION['pToken'] ?>">
    <meta name="admin-user" content='<?= json_encode($user) ?>'>
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nav-link.active { background: #dc2626; color: white; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 500; color: #6b7280; text-decoration: none; border-radius: 0.5rem; transition: all 0.15s ease-in-out; }
        .nav-link:hover { background: #f3f4f6; color: #1f2937; }
        .stats-card { background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); border: 1px solid #e5e7eb; }
        .room-card { background: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; transition: all 0.15s ease-in-out; }
        .room-card:hover { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border-color: #dc2626; }
        .assignment-row { background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 0.5rem; }
        .status-active { color: #059669; background: #d1fae5; }
        .status-inactive { color: #dc2626; background: #fee2e2; }
        .status-maintenance { color: #d97706; background: #fef3c7; }
    </style>
</head>
<body class="h-full bg-gray-50">
    <div class="min-h-full flex">
        
        <!-- Sidebar -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-red-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Panel de Administrador</h1>
                        <p class="text-sm text-gray-500">Gestión del Sistema</p>
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
                    
                    <a href="#rooms" onclick="showSection('rooms')" 
                       id="nav-rooms" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                        Gestión de Salas
                    </a>
                    
                    <a href="#assignments" onclick="showSection('assignments')" 
                       id="nav-assignments" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                        Asignaciones
                    </a>
                    
                    <a href="#reports" onclick="showSection('reports')" 
                       id="nav-reports" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Reportes
                    </a>
                    
                    <a href="#config" onclick="showSection('config')" 
                       id="nav-config" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Configuración
                    </a>
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
                                    <p class="text-sm font-medium text-gray-600">Total Salas</p>
                                    <p id="stat-total-rooms" class="text-2xl font-bold text-blue-600">0</p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Agentes Asignados</p>
                                    <p id="stat-assigned-agents" class="text-2xl font-bold text-green-600">0</p>
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
                                    <p class="text-sm font-medium text-gray-600">Sesiones Activas</p>
                                    <p id="stat-active-sessions" class="text-2xl font-bold text-purple-600">0</p>
                                </div>
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Performance</p>
                                    <p id="stat-performance" class="text-2xl font-bold text-yellow-600">0%</p>
                                </div>
                                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones Rápidas -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <button onclick="showCreateRoomModal()" 
                                    class="p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900">Nueva Sala</h4>
                                <p class="text-sm text-gray-600">Crear sala de chat</p>
                            </button>
                            
                            <button onclick="showAssignAgentModal()" 
                                    class="p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900">Asignar Agente</h4>
                                <p class="text-sm text-gray-600">Asignar a sala</p>
                            </button>
                            
                            <button onclick="showSection('reports')" 
                                    class="p-4 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors">
                                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900">Ver Reportes</h4>
                                <p class="text-sm text-gray-600">Métricas del sistema</p>
                            </button>
                            
                            <button onclick="adminClient.refreshDashboard()" 
                                    class="p-4 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                                <svg class="w-8 h-8 text-red-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900">Actualizar</h4>
                                <p class="text-sm text-gray-600">Refrescar datos</p>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- GESTIÓN DE SALAS -->
                <div id="rooms-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Gestión de Salas</h3>
                                    <p class="text-sm text-gray-600 mt-1">Crear, editar y administrar salas de chat</p>
                                </div>
                                <button onclick="showCreateRoomModal()" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Nueva Sala
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="roomsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando salas...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ASIGNACIONES -->
                <div id="assignments-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Asignaciones de Agentes</h3>
                                    <p class="text-sm text-gray-600 mt-1">Gestionar asignaciones de agentes a salas</p>
                                </div>
                                <button onclick="showAssignAgentModal()" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                    </svg>
                                    Nueva Asignación
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="assignmentsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando asignaciones...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- REPORTES -->
                <div id="reports-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Reportes y Estadísticas</h3>
                                    <p class="text-sm text-gray-600 mt-1">Métricas de desempeño del sistema</p>
                                </div>
                                <button onclick="adminClient.refreshReports()" 
                                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="reportsContainer">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <h4 class="font-semibold text-gray-900 mb-4">Estadísticas de Chat</h4>
                                        <div id="chatStatsContainer" class="space-y-2">
                                            <div class="text-center py-4">
                                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-purple-600 mx-auto mb-2"></div>
                                                <p class="text-sm text-gray-500">Cargando...</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <h4 class="font-semibold text-gray-900 mb-4">Performance de Agentes</h4>
                                        <div id="agentStatsContainer" class="space-y-2">
                                            <div class="text-center py-4">
                                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-purple-600 mx-auto mb-2"></div>
                                                <p class="text-sm text-gray-500">Cargando...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CONFIGURACIÓN -->
                <div id="config-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Configuración del Sistema</h3>
                                    <p class="text-sm text-gray-600 mt-1">Parámetros y configuración general</p>
                                </div>
                                <button onclick="adminClient.saveConfig()" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                    Guardar Cambios
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
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

    <!-- Modal Crear Sala -->
    <div id="createRoomModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Crear Nueva Sala</h3>
            
            <form id="createRoomForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                    <input type="text" id="roomName" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Nombre de la sala" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea id="roomDescription" class="w-full px-3 py-2 border border-gray-300 rounded-lg" rows="3" placeholder="Descripción de la sala"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                    <select id="roomType" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="general">General</option>
                        <option value="medical">Médico</option>
                        <option value="support">Soporte</option>
                        <option value="emergency">Emergencia</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Capacidad Máxima</label>
                    <input type="number" id="roomCapacity" class="w-full px-3 py-2 border border-gray-300 rounded-lg" value="10" min="1">
                </div>
            </form>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('createRoomModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Cancelar
                </button>
                <button onclick="adminClient.createRoom()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Crear Sala
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Asignar Agente -->
    <div id="assignAgentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Asignar Agente a Sala</h3>
            
            <form id="assignAgentForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Agente</label>
                    <select id="agentSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                        <option value="">Seleccionar agente...</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sala</label>
                    <select id="roomSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                        <option value="">Seleccionar sala...</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Horario</label>
                    <select id="scheduleType" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="24/7">24/7</option>
                        <option value="business">Horario comercial</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>
            </form>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('assignAgentModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Cancelar
                </button>
                <button onclick="adminClient.assignAgent()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Asignar
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/admin-client.js"></script>
    
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
                'rooms': 'Gestión de Salas',
                'assignments': 'Asignaciones',
                'reports': 'Reportes',
                'config': 'Configuración'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName];
            
            // Cargar datos de la sección
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
            console.log('✅ Panel de administrador cargado');
            
            updateTime();
            setInterval(updateTime, 1000);
            
            try {
                await adminClient.init();
                console.log('🚀 AdminClient inicializado');
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