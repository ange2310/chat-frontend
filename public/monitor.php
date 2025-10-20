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

// Solo admin puede acceder
if ($userRole !== 'admin') {
    header("Location: index.php?error=not_authorized");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Global - Gran Hermano</title>
    
    <meta name="monitor-token" content="<?= $_SESSION['staffJWT'] ?>">
    <meta name="monitor-user" content='<?= json_encode($user) ?>'>
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/monitor-styles.css" rel="stylesheet">
</head>
<body class="h-full bg-gray-900">
    
    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700 sticky top-0 z-30">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white">Monitor Global</h1>
                            <p class="text-sm text-gray-400">Vista de todas las conversaciones</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <!-- Stats Summary -->
                    <div class="hidden md:flex items-center gap-6 px-6 py-2 bg-gray-900 rounded-lg">
                        <div class="text-center">
                            <div id="totalRoomsCount" class="text-2xl font-bold text-white">0</div>
                            <div class="text-xs text-gray-400">Salas</div>
                        </div>
                        <div class="text-center">
                            <div id="totalWaitingCount" class="text-2xl font-bold text-yellow-400">0</div>
                            <div class="text-xs text-gray-400">Pendientes</div>
                        </div>
                        <div class="text-center">
                            <div id="totalActiveCount" class="text-2xl font-bold text-green-400">0</div>
                            <div class="text-xs text-gray-400">Activos</div>
                        </div>
                    </div>
                    
                    <!-- Refresh Button -->
                    <button onclick="monitorClient.refreshAll()" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="hidden sm:inline">Actualizar</span>
                    </button>
                    
                    <!-- User Info -->
                    <div class="flex items-center gap-3 px-4 py-2 bg-gray-900 rounded-lg">
                        <div class="w-8 h-8 bg-red-600 text-white rounded-full flex items-center justify-center font-semibold text-sm">
                            <?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="hidden lg:block">
                            <p class="text-sm font-medium text-white"><?= htmlspecialchars($user['name'] ?? 'Admin') ?></p>
                            <p class="text-xs text-gray-400">Monitor Global</p>
                        </div>
                        <button onclick="logout()" class="p-2 text-gray-400 hover:text-white rounded-lg" title="Cerrar sesión">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        
        <!-- Filters Bar -->
        <div class="mb-6 flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-400">Vista:</label>
                <select id="viewFilter" onchange="monitorClient.applyFilters()" 
                        class="px-3 py-2 bg-gray-800 text-white border border-gray-700 rounded-lg text-sm">
                    <option value="all">Todas las salas</option>
                    <option value="active">Solo con actividad</option>
                    <option value="waiting">Solo con pendientes</option>
                </select>
            </div>
            
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-400">Ordenar por:</label>
                <select id="sortFilter" onchange="monitorClient.applyFilters()" 
                        class="px-3 py-2 bg-gray-800 text-white border border-gray-700 rounded-lg text-sm">
                    <option value="waiting_desc">Más pendientes</option>
                    <option value="active_desc">Más activos</option>
                    <option value="name_asc">Nombre A-Z</option>
                </select>
            </div>
            
            <div class="flex items-center gap-2 ml-auto">
                <label class="text-sm text-gray-400">Auto-refresh:</label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="autoRefreshToggle" class="sr-only peer" checked onchange="monitorClient.toggleAutoRefresh()">
                    <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
                <span id="autoRefreshStatus" class="text-xs text-gray-400">ON</span>
            </div>
        </div>

        <!-- Rooms Grid -->
        <div id="roomsGrid" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <!-- Loading State -->
            <div class="col-span-full flex items-center justify-center py-20">
                <div class="text-center">
                    <div class="loading-spinner mx-auto mb-4"></div>
                    <p class="text-gray-400">Cargando salas...</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Chat Modal -->
    <div id="chatModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <div class="flex items-center gap-3">
                    <button onclick="monitorClient.closeChat()" class="p-2 text-gray-400 hover:text-white rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                        <span id="chatModalInitials" class="text-white font-semibold">P</span>
                    </div>
                    <div>
                        <h3 id="chatModalTitle" class="text-lg font-semibold text-white">Paciente</h3>
                        <p id="chatModalSubtitle" class="text-sm text-gray-400">Sala • Agente</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 bg-red-900 text-red-200 text-xs font-medium rounded-full">
                        MODO OBSERVADOR
                    </span>
                </div>
            </div>
            
            <div id="chatModalMessages" class="modal-body">
                <!-- Messages will be loaded here -->
            </div>
            
            <div class="modal-footer">
                <div class="bg-gray-800 rounded-lg p-4 text-center text-sm text-gray-400">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Estás observando esta conversación sin participar
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <script src="assets/js/monitor-app.js"></script>
</body>
</html>