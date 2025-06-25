<?php
// public/staff.php - Panel para personal (agentes, supervisores, admins)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

// Proteger la página - solo personal autorizado
protectStaffPage();

$auth = auth();
$user = $auth->getUser();
$userRole = $user['role']['name'] ?? 'agent';
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Personal - Sistema de Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#64748B',
                        success: '#10B981',
                        warning: '#F59E0B',
                        error: '#EF4444'
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full">
    <div class="min-h-full">
        <!-- Sidebar Navigation -->
        <div class="fixed inset-y-0 left-0 w-64 bg-gray-800">
            <div class="flex h-16 items-center justify-center">
                <h1 class="text-white text-lg font-semibold">Panel de Personal</h1>
            </div>
            
            <nav class="mt-8 px-4">
                <div class="space-y-2">
                    <a href="#dashboard" onclick="showSection('dashboard')" 
                       class="nav-link bg-gray-900 text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        <svg class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2v0a2 2 0 012-2h6l2 2h6a2 2 0 012 2v1" />
                        </svg>
                        Dashboard
                    </a>
                    
                    <a href="#chats" onclick="showSection('chats')" 
                       class="nav-link text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        <svg class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                        </svg>
                        Chats Activos
                    </a>
                    
                    <?php if (in_array($userRole, ['supervisor', 'admin'])): ?>
                    <a href="#supervision" onclick="showSection('supervision')" 
                       class="nav-link text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        <svg class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Supervisión
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'admin'): ?>
                    <a href="#admin" onclick="showSection('admin')" 
                       class="nav-link text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        <svg class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Administración
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- User Profile Section -->
                <div class="mt-8 pt-6 border-t border-gray-700">
                    <div class="px-2">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">
                                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                                </span>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-white"><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></p>
                                <p class="text-xs text-gray-400 capitalize"><?= htmlspecialchars($userRole) ?></p>
                            </div>
                        </div>
                        <button onclick="logout()" 
                                class="mt-3 w-full text-left text-sm text-gray-400 hover:text-white transition-colors">
                            Cerrar Sesión
                        </button>
                    </div>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="ml-64 flex-1">
            <!-- Top Header -->
            <header class="bg-white shadow">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h2 id="sectionTitle" class="text-xl font-semibold text-gray-900">Dashboard</h2>
                        <div class="flex items-center space-x-4">
                            <span id="currentTime" class="text-sm text-gray-500"></span>
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-green-400 rounded-full"></div>
                                <span class="text-sm text-gray-700">En línea</span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="section-content">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Stats Cards -->
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                                        </svg>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Chats Activos</dt>
                                            <dd id="activeChatsCount" class="text-lg font-medium text-gray-900">--</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                        </svg>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Pacientes en Cola</dt>
                                            <dd id="queueCount" class="text-lg font-medium text-gray-900">--</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Tiempo Promedio</dt>
                                            <dd id="avgTime" class="text-lg font-medium text-gray-900">-- min</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Completados Hoy</dt>
                                            <dd id="completedToday" class="text-lg font-medium text-gray-900">--</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Actividad Reciente</h3>
                            <div id="recentActivity" class="space-y-3">
                                <div class="text-center text-gray-500 py-8">
                                    Cargando actividad reciente...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chats Section -->
                <div id="chats-section" class="section-content hidden">
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Chats Activos</h3>
                                <button onclick="refreshChats()" 
                                        class="bg-primary text-white px-3 py-2 rounded-md text-sm hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                            <div id="activeChats" class="space-y-4">
                                <div class="text-center text-gray-500 py-8">
                                    Cargando chats activos...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supervision Section -->
                <?php if (in_array($userRole, ['supervisor', 'admin'])): ?>
                <div id="supervision-section" class="section-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Agent Performance -->
                        <div class="bg-white shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Rendimiento Agentes</h3>
                                <div id="agentPerformance" class="space-y-3">
                                    <div class="text-center text-gray-500 py-8">
                                        Cargando datos de rendimiento...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Stats -->
                        <div class="bg-white shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Estadísticas del Sistema</h3>
                                <div id="systemStats" class="space-y-3">
                                    <div class="text-center text-gray-500 py-8">
                                        Cargando estadísticas...
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
                        <!-- User Management -->
                        <div class="bg-white shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Gestión de Usuarios</h3>
                                    <button onclick="showCreateUserModal()" 
                                            class="bg-primary text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700">
                                        Crear Usuario
                                    </button>
                                </div>
                                <div id="usersList" class="overflow-x-auto">
                                    <div class="text-center text-gray-500 py-8">
                                        Cargando usuarios...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Room Management -->
                        <div class="bg-white shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Gestión de Salas</h3>
                                    <button onclick="showCreateRoomModal()" 
                                            class="bg-primary text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700">
                                        Crear Sala
                                    </button>
                                </div>
                                <div id="roomsList" class="overflow-x-auto">
                                    <div class="text-center text-gray-500 py-8">
                                        Cargando salas...
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

    <!-- Chat Window Modal -->
    <div id="chatModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl h-5/6">
                <div class="flex h-full">
                    <!-- Chat Messages -->
                    <div class="flex-1 flex flex-col">
                        <div class="flex items-center justify-between p-4 border-b">
                            <div>
                                <h3 id="chatModalTitle" class="text-lg font-medium text-gray-900">Chat</h3>
                                <p id="chatModalPatient" class="text-sm text-gray-500">Paciente</p>
                            </div>
                            <button onclick="closeChatModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div id="chatModalMessages" class="flex-1 overflow-y-auto p-4 space-y-3">
                            <!-- Messages will be loaded here -->
                        </div>
                        
                        <div class="p-4 border-t">
                            <div class="flex space-x-2">
                                <input type="text" id="chatModalInput" placeholder="Escribe tu mensaje..." 
                                       class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <button onclick="sendChatMessage()" 
                                        class="bg-primary text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                    Enviar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Patient Info Sidebar -->
                    <div class="w-80 border-l bg-gray-50 p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Información del Paciente</h4>
                        <div id="patientInfo" class="space-y-3">
                            <!-- Patient info will be loaded here -->
                        </div>
                        
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Acciones</h4>
                            <div class="space-y-2">
                                <button onclick="transferChat()" 
                                        class="w-full bg-yellow-500 text-white px-3 py-2 rounded-md text-sm hover:bg-yellow-600">
                                    Transferir Chat
                                </button>
                                <button onclick="endChat()" 
                                        class="w-full bg-red-500 text-white px-3 py-2 rounded-md text-sm hover:bg-red-600">
                                    Finalizar Chat
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/auth-client.js"></script>
    <script src="../assets/js/chat-client.js"></script>
    <script src="../assets/js/staff-client.js"></script>
    
    <script>
        // Initialize staff panel
        document.addEventListener('DOMContentLoaded', () => {
            const userRole = '<?= $userRole ?>';
            console.log('Staff panel loaded for role:', userRole);
            
            // Load initial data
            loadDashboardData();
            updateTime();
            setInterval(updateTime, 1000);
            
            // Setup real-time updates
            setupRealtimeUpdates();
        });
        
        // Navigation
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').classList.remove('hidden');
            
            // Update nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('bg-gray-900', 'text-white');
                link.classList.add('text-gray-300', 'hover:bg-gray-700', 'hover:text-white');
            });
            
            event.target.classList.remove('text-gray-300', 'hover:bg-gray-700', 'hover:text-white');
            event.target.classList.add('bg-gray-900', 'text-white');
            
            // Update section title
            const titles = {
                'dashboard': 'Dashboard',
                'chats': 'Chats Activos',
                'supervision': 'Supervisión',
                'admin': 'Administración'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName] || 'Panel';
            
            // Load section specific data
            loadSectionData(sectionName);
        }
        
        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('es-ES');
        }
        
        async function loadDashboardData() {
            try {
                const response = await fetch('http://localhost:3013/dashboard/stats', {
                    headers: window.authClient.getAuthHeaders()
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        updateDashboardStats(result.data);
                    }
                }
            } catch (error) {
                console.error('Error loading dashboard data:', error);
            }
        }
        
        function updateDashboardStats(data) {
            document.getElementById('activeChatsCount').textContent = data.activeChats || '0';
            document.getElementById('queueCount').textContent = data.queueCount || '0';
            document.getElementById('avgTime').textContent = (data.avgTime || 0) + ' min';
            document.getElementById('completedToday').textContent = data.completedToday || '0';
        }
        
        function loadSectionData(sectionName) {
            switch(sectionName) {
                case 'chats':
                    loadActiveChats();
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
                const response = await fetch('http://localhost:3011/chats/active', {
                    headers: window.authClient.getAuthHeaders()
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        displayActiveChats(result.data.chats);
                    }
                }
            } catch (error) {
                console.error('Error loading active chats:', error);
            }
        }
        
        function displayActiveChats(chats) {
            const container = document.getElementById('activeChats');
            
            if (!chats || chats.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500 py-8">No hay chats activos</div>';
                return;
            }
            
            container.innerHTML = chats.map(chat => `
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 text-sm font-medium">
                                        ${(chat.patient_name || 'P').charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">${chat.patient_name || 'Paciente Anónimo'}</h4>
                                    <p class="text-xs text-gray-500">Sala: ${chat.room_name} | Iniciado: ${formatTime(chat.started_at)}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                chat.status === 'active' ? 'bg-green-100 text-green-800' : 
                                chat.status === 'waiting' ? 'bg-yellow-100 text-yellow-800' : 
                                'bg-gray-100 text-gray-800'
                            }">
                                ${chat.status === 'active' ? 'Activo' : chat.status === 'waiting' ? 'En espera' : chat.status}
                            </span>
                            <button onclick="openChat('${chat.id}', '${chat.patient_name}', '${chat.ptoken}')" 
                                    class="bg-primary text-white px-3 py-1 rounded text-xs hover:bg-blue-700">
                                Abrir
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        function openChat(chatId, patientName, ptoken) {
            document.getElementById('chatModalTitle').textContent = `Chat #${chatId}`;
            document.getElementById('chatModalPatient').textContent = patientName || 'Paciente Anónimo';
            document.getElementById('chatModal').classList.remove('hidden');
            
            // Load chat messages and patient info
            loadChatMessages(chatId, ptoken);
            loadPatientInfo(ptoken);
        }
        
        function closeChatModal() {
            document.getElementById('chatModal').classList.add('hidden');
        }
        
        function formatTime(timestamp) {
            return new Date(timestamp).toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
        
        function setupRealtimeUpdates() {
            // Setup WebSocket for real-time updates
            if (window.staffClient) {
                window.staffClient.setupRealtimeUpdates();
            }
            
            // Refresh data every 30 seconds
            setInterval(() => {
                loadDashboardData();
                const activeSection = document.querySelector('.section-content:not(.hidden)');
                if (activeSection && activeSection.id === 'chats-section') {
                    loadActiveChats();
                }
            }, 30000);
        }
        
        function refreshChats() {
            loadActiveChats();
            window.authClient.showSuccess('Chats actualizados');
        }
    </script>
</body>
</html>