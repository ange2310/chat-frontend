<?php
// public/preauth.php - Acceso directo para pacientes con pToken
require_once __DIR__ . '/../config/config.php';

// Obtener pToken de la URL
$pToken = $_GET['pToken'] ?? $_POST['pToken'] ?? null;

if (!$pToken) {
    // Si no hay pToken, redirigir al login de staff
    header("Location: /index.php");
    exit;
}

// Limpiar y validar pToken básico
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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        medical: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'pulse-slow': 'pulse 3s infinite'
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .chat-bubble-user {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        }
        
        .chat-bubble-agent {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
        }
        
        .typing-indicator {
            display: inline-flex;
            align-items: center;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #64748b;
            margin: 0 2px;
            animation: typingDot 1.4s infinite ease-in-out;
        }
        
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes typingDot {
            0%, 80%, 100% { transform: scale(0); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }

        .emoji-hover:hover {
            transform: scale(1.2);
            transition: transform 0.1s ease;
        }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-blue-50 via-white to-blue-50">
    
    <!-- Header Moderno -->
    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b border-gray-200/50 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-medical-600 to-medical-700 rounded-xl flex items-center justify-center shadow-lg">
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
        
        <!-- Estado de Validación -->
        <div id="validationSection" class="mb-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 animate-fade-in">
                <div class="flex items-center justify-center space-x-3">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-medical-600"></div>
                    <p class="text-lg font-medium text-gray-700">Validando credenciales de acceso...</p>
                </div>
                <div class="mt-4 text-center text-sm text-gray-500">
                    Verificando pToken: <span class="font-mono bg-gray-100 px-2 py-1 rounded"><?= substr($pToken, 0, 15) ?>...</span>
                </div>
            </div>
        </div>

        <!-- Sección de Salas (oculta inicialmente) -->
        <div id="roomSelectionSection" class="hidden">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden animate-slide-up">
                <div class="bg-gradient-to-r from-medical-600 to-medical-700 px-6 py-6 text-white">
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
                    <!-- Loading de salas -->
                    <div id="roomsLoading" class="text-center py-12">
                        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-medical-600 mx-auto"></div>
                        <p class="text-gray-500 mt-4 font-medium">Cargando salas disponibles...</p>
                        <p class="text-sm text-gray-400 mt-1">Conectando con nuestros profesionales</p>
                    </div>
                    
                    <!-- Grid de salas -->
                    <div id="roomsGrid" class="hidden grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2">
                        <!-- Las salas se cargarán aquí -->
                    </div>
                    
                    <!-- Error de salas -->
                    <div id="roomsError" class="hidden text-center py-12">
                        <div class="text-red-500 mb-4">
                            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Error de Conexión</h3>
                        <p class="text-gray-600 mb-6">No se pudieron cargar las salas disponibles</p>
                        <button onclick="refreshRooms()" 
                                class="bg-medical-600 text-white px-6 py-3 rounded-lg hover:bg-medical-700 transition-colors font-medium">
                            🔄 Reintentar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de Chat -->
        <div id="chatSection" class="hidden">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden animate-slide-up">
                
                <!-- Chat Header Mejorado -->
                <div class="bg-gradient-to-r from-medical-600 to-medical-700 text-white px-6 py-4">
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

                <!-- Chat Messages Area -->
                <div class="h-96 flex flex-col bg-gray-50">
                    <div id="chatMessages" class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                        <!-- Mensaje inicial del sistema -->
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

                    <!-- Typing Indicator Mejorado -->
                    <div id="typingIndicator" class="hidden px-4 py-2">
                        <div class="flex justify-start">
                            <div class="chat-bubble-agent rounded-2xl px-4 py-3 shadow-sm max-w-xs">
                                <div class="flex items-center space-x-2">
                                    <div class="typing-indicator">
                                        <div class="typing-dot"></div>
                                        <div class="typing-dot"></div>
                                        <div class="typing-dot"></div>
                                    </div>
                                    <span class="text-sm text-gray-500 ml-2">El doctor está escribiendo...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Message Input Area Moderna -->
                    <div class="bg-white border-t border-gray-200 p-4">
                        
                        <!-- Emoji Picker Mejorado -->
                        <div id="emojiPicker" class="hidden mb-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <div class="grid grid-cols-8 gap-2">
                                <!-- Emojis médicos y generales -->
                                <button onclick="insertEmoji('😊')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">😊</button>
                                <button onclick="insertEmoji('😢')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">😢</button>
                                <button onclick="insertEmoji('😟')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">😟</button>
                                <button onclick="insertEmoji('😷')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">😷</button>
                                <button onclick="insertEmoji('🤒')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">🤒</button>
                                <button onclick="insertEmoji('🩺')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">🩺</button>
                                <button onclick="insertEmoji('💊')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">💊</button>
                                <button onclick="insertEmoji('🏥')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">🏥</button>
                                <button onclick="insertEmoji('👨‍⚕️')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">👨‍⚕️</button>
                                <button onclick="insertEmoji('👩‍⚕️')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">👩‍⚕️</button>
                                <button onclick="insertEmoji('❤️')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">❤️</button>
                                <button onclick="insertEmoji('👍')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">👍</button>
                                <button onclick="insertEmoji('👎')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">👎</button>
                                <button onclick="insertEmoji('🙏')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">🙏</button>
                                <button onclick="insertEmoji('💉')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">💉</button>
                                <button onclick="insertEmoji('🩹')" class="emoji-hover p-2 hover:bg-gray-200 rounded-lg text-lg transition-all">🩹</button>
                            </div>
                        </div>

                        <!-- Input Controls Modernos -->
                        <div class="flex items-end space-x-3">
                            <div class="flex-1">
                                <div class="relative">
                                    <textarea 
                                        id="messageInput" 
                                        placeholder="Escribe tu mensaje aquí... 💬"
                                        maxlength="500"
                                        rows="1"
                                        class="block w-full px-4 py-3 pr-20 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-medical-500 focus:border-transparent resize-none bg-white shadow-sm"
                                        style="min-height: 48px; max-height: 120px;"
                                    ></textarea>
                                    <div class="absolute bottom-2 right-2 text-xs text-gray-400 flex items-center space-x-1">
                                        <span id="charCount">0</span>
                                        <span>/500</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons Modernos -->
                            <div class="flex space-x-2">
                                <!-- Emoji Button -->
                                <button 
                                    onclick="toggleEmojiPicker()"
                                    class="p-3 text-gray-400 hover:text-medical-600 hover:bg-medical-50 rounded-xl transition-colors"
                                    title="Agregar emoji"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>

                                <!-- Send Button -->
                                <button 
                                    id="sendButton" 
                                    onclick="sendMessage()"
                                    disabled
                                    class="p-3 bg-medical-600 text-white rounded-xl hover:bg-medical-700 focus:outline-none focus:ring-2 focus:ring-medical-500 focus:ring-offset-2 disabled:bg-gray-300 disabled:cursor-not-allowed transition-all shadow-sm"
                                    title="Enviar mensaje"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Quick Actions Modernas -->
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button onclick="insertQuickMessage('help')" class="quick-action-btn px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors border border-gray-200">
                                🆘 Necesito ayuda
                            </button>
                            <button onclick="insertQuickMessage('symptoms')" class="quick-action-btn px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors border border-gray-200">
                                🤒 Tengo síntomas
                            </button>
                            <button onclick="insertQuickMessage('medication')" class="quick-action-btn px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors border border-gray-200">
                                💊 Consulta medicación
                            </button>
                            <button onclick="insertQuickMessage('thanks')" class="quick-action-btn px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors border border-gray-200">
                                🙏 Gracias
                            </button>
                        </div>

                        <!-- Botón para volver -->
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <button onclick="backToRooms()" 
                                    class="text-sm text-medical-600 hover:text-medical-700 flex items-center transition-colors font-medium">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Volver a selección de salas
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

    <!-- Loading Spinner Global -->
    <div id="globalLoading" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 shadow-2xl text-center max-w-sm">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-medical-600 mx-auto mb-4"></div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Procesando...</h3>
            <p id="loadingMessage" class="text-gray-600 text-sm">Validando acceso</p>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Configuración
        const CONFIG = {
            AUTH_SERVICE_URL: 'http://187.33.158.246:8080/auth',
            PATIENT_TOKEN: '<?= $pToken ?>',
            DEBUG: true
        };

        let currentSession = null;
        let chatActive = false;

        // Inicialización
        document.addEventListener('DOMContentLoaded', async () => {
            console.log('🚀 Iniciando portal de pacientes');
            updateTime();
            setInterval(updateTime, 1000);
            
            setupEventListeners();
            await validatePatientAccess();
        });

        // Actualizar hora
        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Validar acceso del paciente
        async function validatePatientAccess() {
            try {
                console.log('🔑 Validando pToken:', CONFIG.PATIENT_TOKEN.substring(0, 15) + '...');
                console.log('🌐 URL de validación:', `${CONFIG.AUTH_SERVICE_URL}/validate-token`);
                
                // Probar primero con GET (query string)
                const getResponse = await fetch(`${CONFIG.AUTH_SERVICE_URL}/validate-token?ptoken=${encodeURIComponent(CONFIG.PATIENT_TOKEN)}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                console.log('📡 Respuesta GET status:', getResponse.status);
                
                let result;
                try {
                    result = await getResponse.json();
                    console.log('📋 Respuesta completa:', result);
                } catch (parseError) {
                    console.error('❌ Error parseando JSON:', parseError);
                    const text = await getResponse.text();
                    console.log('📄 Respuesta como texto:', text);
                    throw new Error('Respuesta del servidor no es JSON válido');
                }
                
                // Verificar diferentes formatos de respuesta
                if (getResponse.ok && (result.success || result.status === 'OK')) {
                    console.log('✅ pToken válido con GET');
                    await showRoomSelection();
                    return;
                }

                // Si GET falló, probar con POST
                console.log('🔄 Intentando validación con POST...');
                const postResponse = await fetch(`${CONFIG.AUTH_SERVICE_URL}/validate-token`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        ptoken: CONFIG.PATIENT_TOKEN
                    })
                });

                console.log('📡 Respuesta POST status:', postResponse.status);
                
                const postResult = await postResponse.json();
                console.log('📋 Respuesta POST:', postResult);
                
                if (postResponse.ok && (postResult.success || postResult.status === 'OK')) {
                    console.log('✅ pToken válido con POST');
                    await showRoomSelection();
                } else {
                    console.error('❌ pToken inválido en ambos métodos');
                    console.error('GET result:', result);
                    console.error('POST result:', postResult);
                    showValidationError(postResult.message || result.message || 'Token no válido');
                }

            } catch (error) {
                console.error('❌ Error de conexión:', error);
                console.error('Error stack:', error.stack);
                
                // Verificar si el auth-service está funcionando
                try {
                    const healthResponse = await fetch(`${CONFIG.AUTH_SERVICE_URL}/../health`);
                    if (healthResponse.ok) {
                        console.log('✅ Auth-service está funcionando');
                        showValidationError('Error validando token. Verifica que el pToken sea correcto.');
                    } else {
                        console.log('❌ Auth-service no responde en /health');
                        showValidationError('Servicio de autenticación no disponible.');
                    }
                } catch (healthError) {
                    console.log('❌ No se puede conectar con auth-service:', healthError);
                    showValidationError('Error de conexión con el servidor. Verifica tu conexión.');
                }
            }
        }

        // Mostrar error de validación
        function showValidationError(message) {
            document.getElementById('validationSection').classList.add('hidden');
            document.getElementById('validationError').classList.remove('hidden');
        }

        // Mostrar selección de salas
        async function showRoomSelection() {
            document.getElementById('validationSection').classList.add('hidden');
            document.getElementById('roomSelectionSection').classList.remove('hidden');
            
            await loadAvailableRooms();
        }

        // Cargar salas disponibles
        async function loadAvailableRooms() {
            try {
                console.log('🏠 Cargando salas disponibles...');
                
                const response = await fetch(`${CONFIG.AUTH_SERVICE_URL}/rooms/available`, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${CONFIG.PATIENT_TOKEN}`,
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();
                
                if (response.ok && result.success) {
                    displayRooms(result.data.rooms || []);
                } else {
                    console.error('Error cargando salas:', result);
                    showRoomsError('No se pudieron cargar las salas disponibles');
                }

            } catch (error) {
                console.error('Error de red cargando salas:', error);
                showRoomsError('Error de conexión al cargar salas');
            }
        }

        // Mostrar salas
        function displayRooms(rooms) {
            const roomsGrid = document.getElementById('roomsGrid');
            const roomsLoading = document.getElementById('roomsLoading');
            
            roomsLoading.classList.add('hidden');
            roomsGrid.classList.remove('hidden');
            
            if (!rooms || rooms.length === 0) {
                showRoomsError('No hay salas disponibles en este momento');
                return;
            }
            
            roomsGrid.innerHTML = rooms.map(room => `
                <div onclick="selectRoom('${room.id}', '${room.name}')" 
                     class="cursor-pointer p-6 border-2 border-gray-200 rounded-xl hover:border-medical-300 hover:shadow-lg transition-all duration-300 group bg-white ${!room.available ? 'opacity-60' : ''}">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-14 h-14 ${getRoomColorClass(room.type)} rounded-xl flex items-center justify-center group-hover:scale-105 transition-transform shadow-sm">
                                ${getRoomIcon(room.type)}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg font-semibold text-gray-900 group-hover:text-medical-700 transition-colors">${room.name}</h3>
                                ${room.available ? 
                                    '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✨ Disponible</span>' :
                                    '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">⏳ No disponible</span>'
                                }
                            </div>
                            <p class="text-gray-600 text-sm mb-3">${room.description}</p>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center text-gray-500">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    ${room.available ? `⚡ ${room.estimated_wait || '5-10 min'}` : (room.estimated_wait || 'Fuera de horario')}
                                </div>
                                ${room.current_queue > 0 ? 
                                    `<span class="text-orange-600 font-medium">👥 ${room.current_queue} en cola</span>` : 
                                    '<span class="text-green-600 font-medium">🟢 Sin cola</span>'
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Mostrar error de salas
        function showRoomsError(message) {
            document.getElementById('roomsLoading').classList.add('hidden');
            document.getElementById('roomsGrid').classList.add('hidden');
            
            const errorEl = document.getElementById('roomsError');
            errorEl.classList.remove('hidden');
            
            const errorMessage = errorEl.querySelector('p');
            if (errorMessage) {
                errorMessage.textContent = message;
            }
        }

        // Seleccionar sala
        async function selectRoom(roomId, roomName) {
            if (!roomId || !roomName) {
                showNotification('Error: Datos de sala inválidos', 'error');
                return;
            }

            console.log('🎯 Seleccionando sala:', roomId, roomName);
            showLoading('Conectando con ' + roomName + '...');
            
            try {
                const response = await fetch(`${CONFIG.AUTH_SERVICE_URL}/rooms/${roomId}/select`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${CONFIG.PATIENT_TOKEN}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_data: {
                            source: 'patient_portal',
                            selected_at: new Date().toISOString()
                        }
                    })
                });

                const result = await response.json();
                
                if (response.ok && result.success) {
                    currentSession = {
                        roomId: roomId,
                        roomName: roomName,
                        ptoken: result.data.ptoken || CONFIG.PATIENT_TOKEN,
                        sessionData: result.data
                    };
                    
                    showNotification(`Conectado a ${roomName} exitosamente`, 'success');
                    openChat(roomName);
                } else {
                    showNotification(result.message || 'Error seleccionando sala', 'error');
                }

            } catch (error) {
                console.error('Error seleccionando sala:', error);
                showNotification('Error de conexión al seleccionar sala', 'error');
            } finally {
                hideLoading();
            }
        }

        // Abrir chat
        function openChat(roomName) {
            document.getElementById('roomSelectionSection').classList.add('hidden');
            document.getElementById('chatSection').classList.remove('hidden');
            
            document.getElementById('chatRoomName').textContent = roomName;
            document.getElementById('chatStatus').textContent = 'Conectando...';
            
            // Limpiar mensajes previos
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.innerHTML = `
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
            `;
            
            chatActive = true;
            
            // Simular conexión
            setTimeout(() => {
                document.getElementById('chatStatus').textContent = 'Conectado';
                addMessageToChat(
                    `¡Hola! Bienvenido a ${roomName}. Soy el Dr. García y te voy a atender hoy. ¿En qué puedo ayudarte? 👨‍⚕️`,
                    'agent',
                    'Dr. García'
                );
            }, 2000);
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
            const input = e.target;
            const charCount = input.value.length;
            
            // Auto-resize
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
            
            // Update counter
            document.getElementById('charCount').textContent = charCount;
            
            // Enable/disable send button
            const sendButton = document.getElementById('sendButton');
            if (sendButton) {
                sendButton.disabled = charCount === 0;
                
                if (charCount > 0) {
                    sendButton.classList.remove('bg-gray-300');
                    sendButton.classList.add('bg-medical-600', 'hover:bg-medical-700');
                } else {
                    sendButton.classList.add('bg-gray-300');
                    sendButton.classList.remove('bg-medical-600', 'hover:bg-medical-700');
                }
            }
        }

        function handleKeyDown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (e.target.value.trim()) {
                    sendMessage();
                }
            }
        }

        // Enviar mensaje
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !chatActive) return;
            
            addMessageToChat(message, 'user');
            
            // Limpiar input
            input.value = '';
            input.style.height = 'auto';
            document.getElementById('charCount').textContent = '0';
            document.getElementById('sendButton').disabled = true;
            
            // Simular respuesta del agente
            simulateAgentResponse(message);
        }

        // Agregar mensaje al chat
        function addMessageToChat(content, senderType, senderName = null) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageElement = document.createElement('div');
            
            const isUser = senderType === 'user';
            const time = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            
            messageElement.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-4 animate-fade-in`;
            
            const bubbleClass = isUser ? 'chat-bubble-user text-white' : 'chat-bubble-agent text-gray-800';
            
            messageElement.innerHTML = `
                <div class="max-w-xs lg:max-w-md">
                    <div class="${bubbleClass} rounded-2xl px-4 py-3 shadow-sm">
                        ${!isUser && senderName ? `
                            <div class="flex items-center space-x-2 mb-2">
                                <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-gray-700">${senderName}</span>
                            </div>
                        ` : ''}
                        <p class="text-sm leading-relaxed">${formatMessage(content)}</p>
                        <p class="text-xs ${isUser ? 'text-blue-100' : 'text-gray-500'} mt-2 opacity-75">${time}</p>
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Formatear mensaje
        function formatMessage(message) {
            return message
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" class="underline">$1</a>');
        }

        // Simular respuesta del agente
        function simulateAgentResponse(userMessage) {
            showTypingIndicator();
            
            const responses = [
                "Entiendo tu consulta. Déjame revisar la información que me proporcionas. 🩺",
                "Gracias por esa información. ¿Podrías contarme desde cuándo presentas estos síntomas?",
                "Basado en lo que me describes, te haré algunas preguntas adicionales para poder ayudarte mejor.",
                "Es importante que me proporciones todos los detalles. ¿Has tenido algún síntoma adicional?",
                "Perfecto. Con esta información puedo darte una mejor orientación. ¿Tienes alguna alergia conocida a medicamentos?",
                "Te recomiendo algunas medidas que puedes tomar. ¿Tienes alguna pregunta específica sobre tu situación? 💊"
            ];
            
            const lowerMessage = userMessage.toLowerCase();
            let specificResponse = null;
            
            if (lowerMessage.includes('dolor')) {
                specificResponse = "Entiendo que tienes dolor. ¿Puedes describir la intensidad del 1 al 10 y si es constante o intermitente? 😟";
            } else if (lowerMessage.includes('fiebre')) {
                specificResponse = "La fiebre puede indicar varias cosas. ¿Has medido tu temperatura? ¿Qué otros síntomas tienes? 🤒";
            } else if (lowerMessage.includes('tos')) {
                specificResponse = "La tos puede tener muchas causas. ¿Es seca o con flemas? ¿Desde cuándo la tienes? 😷";
            } else if (lowerMessage.includes('gracias')) {
                specificResponse = "De nada, estoy aquí para ayudarte. ¿Hay algo más que te preocupe? 😊";
            }
            
            setTimeout(() => {
                hideTypingIndicator();
                const response = specificResponse || responses[Math.floor(Math.random() * responses.length)];
                addMessageToChat(response, 'agent', 'Dr. García');
            }, 2000 + Math.random() * 3000);
        }

        // Indicadores de escritura
        function showTypingIndicator() {
            document.getElementById('typingIndicator').classList.remove('hidden');
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').classList.add('hidden');
        }

        // Emoji picker
        function toggleEmojiPicker() {
            const picker = document.getElementById('emojiPicker');
            picker.classList.toggle('hidden');
        }

        function insertEmoji(emoji) {
            const input = document.getElementById('messageInput');
            const cursorPos = input.selectionStart;
            const textBefore = input.value.substring(0, cursorPos);
            const textAfter = input.value.substring(cursorPos);
            
            input.value = textBefore + emoji + textAfter;
            input.focus();
            input.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
            
            // Trigger input event
            input.dispatchEvent(new Event('input'));
            
            document.getElementById('emojiPicker').classList.add('hidden');
        }

        // Mensajes rápidos
        function insertQuickMessage(type) {
            const messages = {
                help: '¿Podrían ayudarme con información sobre mi consulta?',
                symptoms: 'Tengo algunos síntomas que me gustaría consultar 🤒',
                medication: '¿Podrían revisar mi medicación actual? 💊',
                thanks: 'Muchas gracias por su atención y profesionalismo 🙏'
            };
            
            const input = document.getElementById('messageInput');
            input.value = messages[type] || messages.help;
            input.focus();
            input.dispatchEvent(new Event('input'));
        }

        // Volver a salas
        function backToRooms() {
            if (chatActive) {
                if (confirm('¿Estás seguro de que quieres volver a la selección de salas? Esto terminará tu consulta actual.')) {
                    actuallyBackToRooms();
                }
            } else {
                actuallyBackToRooms();
            }
        }

        function actuallyBackToRooms() {
            document.getElementById('chatSection').classList.add('hidden');
            document.getElementById('roomSelectionSection').classList.remove('hidden');
            
            chatActive = false;
            currentSession = null;
            
            // Limpiar chat
            document.getElementById('chatMessages').innerHTML = '';
            document.getElementById('messageInput').value = '';
            document.getElementById('charCount').textContent = '0';
            
            console.log('🔄 Volviendo a selección de salas');
        }

        function confirmEndChat() {
            if (confirm('¿Estás seguro de que quieres finalizar la consulta?')) {
                endChat();
            }
        }

        function endChat() {
            chatActive = false;
            showNotification('Consulta finalizada. ¡Que tengas un buen día! 👋', 'success');
            
            setTimeout(() => {
                window.location.href = 'https://www.tpsalud.com';
            }, 3000);
        }

        function minimizeChat() {
            const chatSection = document.getElementById('chatSection');
            chatSection.classList.toggle('opacity-50');
            chatSection.classList.toggle('scale-95');
        }

        function refreshRooms() {
            showNotification('Actualizando salas disponibles...', 'info');
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
            const notification = document.createElement('div');
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm text-white ${colors[type]} animate-slide-up`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 hover:opacity-75">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                'support': '<svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM12 18a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V18.75A.75.75 0 0112 18z"></path></svg>',
                'emergency': '<svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
            };
            return icons[roomType] || icons['general'];
        }

        // Ocultar emoji picker al hacer clic fuera
        document.addEventListener('click', function(e) {
            const emojiPicker = document.getElementById('emojiPicker');
            const emojiButton = e.target.closest('button[onclick="toggleEmojiPicker()"]');
            
            if (emojiPicker && !emojiButton && !emojiPicker.contains(e.target)) {
                emojiPicker.classList.add('hidden');
            }
        });

        console.log('🏥 Portal de pacientes cargado - v2.0');
    </script>
</body>
</html>