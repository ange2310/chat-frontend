<?php
// public/preauth.php - Portal de pacientes CON FLUJO REAL
require_once __DIR__ . '/../config/config.php';

// Obtener pToken de la URL
$pToken = $_GET['pToken'] ?? $_POST['pToken'] ?? null;

if (!$pToken) {
    header("Location: /index.php");
    exit;
}

$pToken = trim($pToken);
if (empty($pToken) || strlen($pToken) < 10) {
    header("Location: /index.php?error=invalid_token");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta Médica - Portal de Atención</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body class="h-full bg-gradient-to-br from-blue-50 via-white to-blue-50">
    
    <!-- Header -->
    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b border-gray-200/50 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Portal de Consulta Médica</h1>
                        <p class="text-sm text-gray-500">Atención profesional 24/7</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="hidden sm:flex items-center space-x-2 text-sm text-gray-600">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span>Conexión segura</span>
                    </div>
                    <div class="text-sm text-gray-500" id="currentTime"></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <!-- Validación del Token -->
        <div id="validationSection" class="mb-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-center space-x-3">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="text-lg font-medium text-gray-700">Validando credenciales de acceso...</p>
                </div>
                <div class="mt-4 text-center text-sm text-gray-500">
                    Verificando pToken: <span class="font-mono bg-gray-100 px-2 py-1 rounded"><?= substr($pToken, 0, 15) ?>...</span>
                </div>
            </div>
        </div>

        <!-- Selección de Salas -->
        <div id="roomSelectionSection" class="hidden">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold">Bienvenido a tu Consulta</h2>
                            <p class="text-blue-100 mt-1">Selecciona el tipo de atención que necesitas</p>
                        </div>
                        <div class="bg-white/20 rounded-lg p-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div id="roomsLoading" class="text-center py-12">
                        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="text-gray-500 mt-4 font-medium">Cargando salas disponibles...</p>
                    </div>
                    
                    <div id="roomsGrid" class="hidden grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2">
                        <!-- Las salas se cargarán aquí -->
                    </div>
                    
                    <div id="roomsError" class="hidden text-center py-12">
                        <div class="text-red-500 mb-4">
                            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Error de Conexión</h3>
                        <p class="text-gray-600 mb-6">No se pudieron cargar las salas disponibles</p>
                        <button onclick="refreshRooms()" 
                                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                            🔄 Reintentar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de Chat -->
        <div id="chatSection" class="hidden">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                
                <!-- Chat Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                                    </svg>
                                </div>
                                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-400 border-2 border-white rounded-full animate-pulse"></div>
                            </div>
                            <div>
                                <h3 id="chatRoomName" class="text-lg font-semibold">Chat Médico</h3>
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 bg-green-300 rounded-full animate-pulse"></div>
                                    <span id="chatStatus" class="text-sm text-blue-100">Conectando...</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <button onclick="minimizeChat()" 
                                    class="p-2 hover:bg-white/10 rounded-lg transition-colors" 
                                    title="Minimizar">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                </svg>
                            </button>
                            <button onclick="confirmEndChat()" 
                                    class="p-2 hover:bg-white/10 rounded-lg transition-colors" 
                                    title="Finalizar consulta">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="h-96 flex flex-col bg-gray-50">
                    <div id="chatMessages" class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                        <!-- Mensaje inicial -->
                        <div class="flex justify-center">
                            <div class="bg-white/80 backdrop-blur-sm px-4 py-2 rounded-full shadow-sm border border-gray-200">
                                <p class="text-sm text-gray-600 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    Chat médico seguro y privado
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Typing Indicator -->
                    <div id="typingIndicator" class="hidden px-4 py-2">
                        <div class="flex justify-start">
                            <div class="bg-white rounded-2xl px-4 py-3 shadow-sm border border-gray-200 max-w-xs">
                                <div class="flex items-center space-x-2">
                                    <div class="flex space-x-1">
                                        <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></div>
                                        <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                        <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                    </div>
                                    <span class="text-sm text-gray-500 ml-2">El doctor está escribiendo...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Message Input -->
                    <div class="bg-white border-t border-gray-200 p-4">
                        <div class="flex items-end space-x-3">
                            <div class="flex-1">
                                <div class="relative">
                                    <textarea 
                                        id="messageInput" 
                                        placeholder="Escribe tu mensaje aquí... 💬"
                                        maxlength="500"
                                        rows="1"
                                        class="block w-full px-4 py-3 pr-20 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none bg-white shadow-sm"
                                        style="min-height: 48px; max-height: 120px;"
                                    ></textarea>
                                    <div class="absolute bottom-2 right-2 text-xs text-gray-400">
                                        <span id="charCount">0</span>/500
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Send Button -->
                            <button 
                                id="sendButton" 
                                onclick="sendMessage()"
                                disabled
                                class="p-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:bg-gray-300 disabled:cursor-not-allowed transition-all shadow-sm"
                                title="Enviar mensaje"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Quick Actions -->
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button onclick="insertQuickMessage('help')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors border border-gray-200">
                                🆘 Necesito ayuda
                            </button>
                            <button onclick="insertQuickMessage('symptoms')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors border border-gray-200">
                                🤒 Tengo síntomas
                            </button>
                            <button onclick="insertQuickMessage('medication')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors border border-gray-200">
                                💊 Consulta medicación
                            </button>
                        </div>

                        <!-- File Upload -->
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <input type="file" id="fileInput" accept="image/*,.pdf,.doc,.docx" class="hidden" onchange="handleFileUpload(this.files)">
                            <button onclick="document.getElementById('fileInput').click()" 
                                    class="text-sm text-blue-600 hover:text-blue-700 flex items-center transition-colors font-medium">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                Adjuntar archivo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error de Validación -->
        <div id="validationError" class="hidden">
            <div class="bg-white rounded-2xl shadow-sm border border-red-200 overflow-hidden">
                <div class="bg-red-50 px-6 py-4 border-b border-red-200">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-red-900">Acceso No Autorizado</h3>
                            <p class="text-red-700">El token de acceso no es válido</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 text-center">
                    <p class="text-gray-600 mb-4">
                        Tu sesión ha expirado o el enlace de acceso no es válido.
                        <br>Por favor, solicita un nuevo enlace de acceso.
                    </p>
                    <button onclick="window.location.reload()" 
                            class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors">
                        Intentar de nuevo
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Loading Global -->
    <div id="globalLoading" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 shadow-2xl text-center max-w-sm">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Procesando...</h3>
            <p id="loadingMessage" class="text-gray-600 text-sm">Validando acceso</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/auth-client.js"></script>
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <script src="assets/js/chat-client.js"></script>
    
    <script>
        // Configuración
        const CONFIG = {
            PATIENT_TOKEN: '<?= $pToken ?>',
            DEBUG: true
        };

        let currentSession = null;
        let chatActive = false;

        // Inicialización
        document.addEventListener('DOMContentLoaded', async () => {
            console.log('🚀 Portal de pacientes iniciado');
            updateTime();
            setInterval(updateTime, 1000);
            
            setupEventListeners();
            await validatePatientAccess();
        });

        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // FLUJO REAL: 1. Validar pToken
        async function validatePatientAccess() {
            try {
                console.log('🔑 Validando pToken:', CONFIG.PATIENT_TOKEN.substring(0, 15) + '...');
                
                // Crear instancia temporal de auth client para validar
                const authClient = new AuthClient();
                const isValid = await authClient.verifyToken(CONFIG.PATIENT_TOKEN);
                
                if (isValid) {
                    console.log('✅ pToken válido');
                    authClient.token = CONFIG.PATIENT_TOKEN;
                    authClient.userType = 'patient';
                    window.authClient = authClient;
                    
                    await showRoomSelection();
                } else {
                    console.error('❌ pToken inválido');
                    showValidationError('Token de acceso inválido');
                }

            } catch (error) {
                console.error('❌ Error validando acceso:', error);
                showValidationError('Error de conexión. Verifica tu conexión a internet.');
            }
        }

        function showValidationError(message) {
            document.getElementById('validationSection').classList.add('hidden');
            document.getElementById('validationError').classList.remove('hidden');
        }

        // FLUJO REAL: 2. Mostrar salas disponibles
        async function showRoomSelection() {
            document.getElementById('validationSection').classList.add('hidden');
            document.getElementById('roomSelectionSection').classList.remove('hidden');
            
            await loadAvailableRooms();
        }

        // FLUJO REAL: 3. Cargar salas desde auth-service
        async function loadAvailableRooms() {
            try {
                console.log('🏠 Cargando salas desde auth-service...');
                
                const rooms = await window.authClient.getAvailableRooms(CONFIG.PATIENT_TOKEN);
                displayRooms(rooms);
                
            } catch (error) {
                console.error('Error cargando salas:', error);
                showRoomsError('No se pudieron cargar las salas: ' + error.message);
            }
        }

        function displayRooms(rooms) {
            const roomsLoading = document.getElementById('roomsLoading');
            const roomsGrid = document.getElementById('roomsGrid');
            
            roomsLoading.classList.add('hidden');
            roomsGrid.classList.remove('hidden');
            
            if (!rooms || rooms.length === 0) {
                showRoomsError('No hay salas disponibles');
                return;
            }
            
            roomsGrid.innerHTML = rooms.map(room => `
                <div onclick="selectRoom('${room.id}', '${room.name}')" 
                     class="cursor-pointer p-6 border-2 border-gray-200 rounded-xl hover:border-blue-300 hover:shadow-lg transition-all duration-300 group bg-white ${!room.available ? 'opacity-60' : ''}">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-14 h-14 ${getRoomColorClass(room.type)} rounded-xl flex items-center justify-center group-hover:scale-105 transition-transform shadow-sm">
                                ${getRoomIcon(room.type)}
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg font-semibold text-gray-900">${room.name}</h3>
                                ${room.available ? 
                                    '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">✨ Disponible</span>' :
                                    '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">⏳ No disponible</span>'
                                }
                            </div>
                            <p class="text-gray-600 text-sm mb-3">${room.description}</p>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">🕒 ${room.estimated_wait || '5-10 min'}</span>
                                <span class="text-gray-500">👥 ${room.current_queue || 0} en cola</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function showRoomsError(message) {
            document.getElementById('roomsLoading').classList.add('hidden');
            document.getElementById('roomsGrid').classList.add('hidden');
            document.getElementById('roomsError').classList.remove('hidden');
        }

        // FLUJO REAL: 4. Seleccionar sala → auth-service
        // FLUJO REAL: 4. Seleccionar sala → auth-service
        async function selectRoom(roomId, roomName) {
            console.log('🎯 Seleccionando sala:', roomId);
            showLoading('Conectando con ' + roomName + '...');
            
            try {
                // Paso 1: Seleccionar sala en auth-service
                const selectResult = await window.authClient.selectRoom(roomId, {
                    source: 'patient_portal',
                    browser: navigator.userAgent
                }, CONFIG.PATIENT_TOKEN);
                
                if (!selectResult.success) {
                    throw new Error(selectResult.error || 'Error seleccionando sala');
                }
                
                console.log('✅ Sala seleccionada en auth-service');
                
                // CRÍTICO: Usar el pToken actualizado que devolvió selectRoom
                const updatedPToken = selectResult.ptoken || CONFIG.PATIENT_TOKEN;
                console.log('🔑 Usando pToken actualizado para chat:', updatedPToken.substring(0, 15) + '...');
                
                // Paso 2: Crear instancia de chat client
                window.chatClient = new ChatClient();
                
                // Paso 3: Conectar al chat service con pToken actualizado
                await window.chatClient.connect(updatedPToken, roomId);
                
                showNotification(`Conectado a ${roomName}`, 'success');
                openChat(roomName);
                
            } catch (error) {
                console.error('❌ Error seleccionando sala:', error);
                showNotification('Error: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }

        // FLUJO REAL: 5. Abrir chat
        function openChat(roomName) {
            document.getElementById('roomSelectionSection').classList.add('hidden');
            document.getElementById('chatSection').classList.remove('hidden');
            
            document.getElementById('chatRoomName').textContent = roomName;
            document.getElementById('chatStatus').textContent = 'Conectado';
            
            chatActive = true;
            console.log('💬 Chat abierto:', roomName);
        }

        // Event listeners
        function setupEventListeners() {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', handleInputChange);
                messageInput.addEventListener('keydown', handleKeyDown);
            }
        }

        function handleInputChange(e) {
            const charCount = e.target.value.length;
            document.getElementById('charCount').textContent = charCount;
            
            const sendButton = document.getElementById('sendButton');
            sendButton.disabled = charCount === 0;
            
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

        function insertQuickMessage(type) {
            const messages = {
                help: '¿Podrían ayudarme con información sobre mi consulta?',
                symptoms: 'Tengo algunos síntomas que me gustaría consultar 🤒',
                medication: '¿Podrían revisar mi medicación actual? 💊'
            };
            
            const input = document.getElementById('messageInput');
            input.value = messages[type] || messages.help;
            input.focus();
            input.dispatchEvent(new Event('input'));
        }

        function confirmEndChat() {
            if (confirm('¿Finalizar consulta?')) {
                endChat();
            }
        }

        function minimizeChat() {
            const chatSection = document.getElementById('chatSection');
            chatSection.classList.toggle('opacity-50');
        }

        function refreshRooms() {
            showNotification('Actualizando salas...', 'info');
            loadAvailableRooms();
        }

        // Utilidades
        function showLoading(message = 'Cargando...') {
            document.getElementById('loadingMessage').textContent = message;
            document.getElementById('globalLoading').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('globalLoading').classList.add('hidden');
        }

        function showNotification(message, type = 'info', duration = 4000) {
            if (window.authClient) {
                window.authClient.showNotification(message, type, duration);
            } else {
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        }

        function getRoomColorClass(roomType) {
            const colors = {
                'general': 'bg-gradient-to-br from-blue-100 to-blue-200',
                'medical': 'bg-gradient-to-br from-green-100 to-green-200', 
                'support': 'bg-gradient-to-br from-purple-100 to-purple-200',
                'emergency': 'bg-gradient-to-br from-red-100 to-red-200'
            };
            return colors[roomType] || 'bg-gradient-to-br from-blue-100 to-blue-200';
        }

        function getRoomIcon(roomType) {
            const icons = {
                'general': '<svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path></svg>',
                'medical': '<svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path></svg>',
                'support': '<svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364"></path></svg>',
                'emergency': '<svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
            };
            return icons[roomType] || icons['general'];
        }

        console.log('🏥 Portal de pacientes v3.0 - FLUJO REAL BACKEND');
    </script>
</body>
</html>