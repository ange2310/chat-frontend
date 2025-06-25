<?php
// public/index.php - Punto de entrada para pacientes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = auth();
$isAuthenticated = $auth->isAuthenticated();
$user = $isAuthenticated ? $auth->getUser() : null;
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Chat - Portal Pacientes</title>
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
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between items-center">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">Sistema de Chat</h1>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <?php if ($isAuthenticated): ?>
                            <!-- Usuario autenticado -->
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                        <span class="text-white text-sm font-medium">
                                            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                                        </span>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 user-name">
                                        <?= htmlspecialchars($user['name'] ?? 'Usuario') ?>
                                    </span>
                                </div>
                                <button onclick="logout()" 
                                        class="auth-required text-sm text-gray-500 hover:text-gray-700 transition-colors">
                                    Cerrar Sesión
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Usuario no autenticado -->
                            <div class="guest-only flex items-center space-x-3">
                                <button onclick="showAuthModal(); showLoginForm()" 
                                        class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                                    Iniciar Sesión
                                </button>
                                <button onclick="showAuthModal(); showRegisterForm()" 
                                        class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors">
                                    Registrarse
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
            <?php if ($isAuthenticated): ?>
                <!-- Chat Interface -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                                </svg>
                            </div>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Bienvenido al Sistema de Chat</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Hola <?= htmlspecialchars($user['name'] ?? 'Usuario') ?>, selecciona una sala para comenzar a chatear.
                            </p>
                            <div class="mt-6">
                                <button onclick="loadAvailableRooms()" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                                    </svg>
                                    Ver Salas Disponibles
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rooms List Container -->
                <div id="roomsContainer" class="mt-6 hidden">
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Salas Disponibles</h3>
                            <div id="roomsList" class="space-y-3">
                                <!-- Rooms will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Container -->
                <div id="chatContainer" class="mt-6 hidden">
                    <!-- Chat interface will be loaded here -->
                </div>

            <?php else: ?>
                <!-- Landing Page -->
                <div class="text-center">
                    <div class="mx-auto max-w-md">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                            <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                            </svg>
                        </div>
                        <h2 class="mt-2 text-xl font-bold text-gray-900">Sistema de Chat Médico</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Conecta con nuestro equipo de profesionales de la salud para recibir atención personalizada.
                        </p>
                        <div class="mt-6 space-y-3">
                            <button onclick="showAuthModal(); showRegisterForm()" 
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Crear Cuenta
                            </button>
                            <button onclick="showAuthModal(); showLoginForm()" 
                                    class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Iniciar Sesión
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Features Section -->
                <div class="mt-16">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Seguro y Privado</h3>
                            <p class="mt-1 text-sm text-gray-500">Todas las conversaciones están encriptadas y protegidas.</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                                <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Atención 24/7</h3>
                            <p class="mt-1 text-sm text-gray-500">Disponible las 24 horas del día, los 7 días de la semana.</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
                                <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Respuesta Rápida</h3>
                            <p class="mt-1 text-sm text-gray-500">Conecta inmediatamente con profesionales disponibles.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <!-- Auth Modal -->
    <?php include __DIR__ . '../components/auth-modal.php'; ?>

    <!-- Scripts -->
    <script src="../assets/js/auth-client.js"></script>
    <script src="../assets/js/chat-client.js"></script>
    
    <script>
        // Initialize page based on auth status
        document.addEventListener('DOMContentLoaded', () => {
            const isAuthenticated = <?= json_encode($isAuthenticated) ?>;
            console.log('Page loaded, authenticated:', isAuthenticated);
        });
        
        // Load available rooms
        async function loadAvailableRooms() {
            try {
                const response = await fetch('http://187.33.158.246/auth/rooms/available', {
                    headers: window.authClient.getAuthHeaders()
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayRooms(result.data.rooms);
                    document.getElementById('roomsContainer').classList.remove('hidden');
                } else {
                    window.authClient.showError(result.message || 'Error cargando salas');
                }
            } catch (error) {
                console.error('Error loading rooms:', error);
                window.authClient.showError('Error de conexión al cargar salas');
            }
        }
        
        // Display rooms list
        function displayRooms(rooms) {
            const roomsList = document.getElementById('roomsList');
            roomsList.innerHTML = '';
            
            rooms.forEach(room => {
                const roomElement = document.createElement('div');
                roomElement.className = 'border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-colors';
                roomElement.onclick = () => selectRoom(room.id, room.name);
                
                roomElement.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">${room.name}</h4>
                            <p class="text-sm text-gray-500">${room.description}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                Tiempo estimado: ${room.estimated_wait} | 
                                Agentes disponibles: ${room.agents_online}
                            </p>
                        </div>
                        <div class="flex items-center">
                            ${room.available ? 
                                '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Disponible</span>' :
                                '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">No disponible</span>'
                            }
                        </div>
                    </div>
                `;
                
                roomsList.appendChild(roomElement);
            });
        }
        
        // Select room and create pToken
        async function selectRoom(roomId, roomName) {
            try {
                window.authClient.showLoading();
                
                const response = await fetch(`http://187.33.158.246/auth/rooms/${roomId}/select`, {
                    method: 'POST',
                    headers: window.authClient.getAuthHeaders(),
                    body: JSON.stringify({
                        user_data: {
                            source: 'patient_portal'
                        }
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.authClient.showSuccess(`Conectado a ${roomName}`);
                    initializeChat(result.data.ptoken, roomId, roomName);
                } else {
                    window.authClient.showError(result.message || 'Error seleccionando sala');
                }
            } catch (error) {
                console.error('Error selecting room:', error);
                window.authClient.showError('Error de conexión al seleccionar sala');
            } finally {
                window.authClient.hideLoading();
            }
        }
        
        // Initialize chat with pToken
        function initializeChat(ptoken, roomId, roomName) {
            console.log('Initializing chat with pToken:', ptoken.substring(0, 15) + '...');
            
            // Hide rooms, show chat
            document.getElementById('roomsContainer').classList.add('hidden');
            
            // Create chat interface
            const chatContainer = document.getElementById('chatContainer');
            chatContainer.innerHTML = `
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Chat: ${roomName}</h3>
                            <button onclick="endChat()" class="text-sm text-red-600 hover:text-red-800">
                                Terminar Chat
                            </button>
                        </div>
                        
                        <div id="chatMessages" class="h-96 overflow-y-auto border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
                            <div class="text-center text-gray-500 text-sm">
                                Conectando al sistema de chat...
                            </div>
                        </div>
                        
                        <div class="flex space-x-2">
                            <input type="text" id="messageInput" placeholder="Escribe tu mensaje..." 
                                   class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <button onclick="sendMessage()" 
                                    class="bg-primary text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Enviar
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            chatContainer.classList.remove('hidden');
            
            // Initialize chat client with pToken
            if (window.chatClient) {
                window.chatClient.connect(ptoken, roomId);
            }
        }
        
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (message && window.chatClient) {
                window.chatClient.sendMessage(message);
                input.value = '';
            }
        }
        
        function endChat() {
            if (confirm('¿Estás seguro de que quieres terminar el chat?')) {
                if (window.chatClient) {
                    window.chatClient.disconnect();
                }
                
                document.getElementById('chatContainer').classList.add('hidden');
                document.getElementById('roomsContainer').classList.add('hidden');
            }
        }
    </script>
</body>
</html>