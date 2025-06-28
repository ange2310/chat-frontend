<?php
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
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta Médica - Portal de Atención</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .chat-fullscreen { position: fixed; inset: 0; z-index: 50; display: flex; flex-direction: column; background: white; }
        .chat-header { background: white; border-bottom: 1px solid #e5e7eb; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; min-height: 70px; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; background: #f8fafc; display: flex; flex-direction: column; gap: 1rem; }
        .chat-input-area { background: white; border-top: 1px solid #e5e7eb; padding: 1.5rem; }
        .message { display: flex; gap: 0.75rem; max-width: 80%; }
        .message-system { align-self: flex-start; }
        .message-user { align-self: flex-end; flex-direction: row-reverse; }
        .message-content { background: #e5e7eb; border: 1px solid #d1d5db; border-radius: 1rem; padding: 0.75rem 1rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); position: relative; word-wrap: break-word; }
        .message-user .message-content { background: var(--primary); color: white; border-color: var(--primary); }
        .message-time { font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem; }
        .message-user .message-time { color: rgba(255, 255, 255, 0.7); }
        .chat-input { width: 100%; min-height: 44px; max-height: 120px; padding: 0.75rem 60px 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 1.5rem; font-size: 14px; resize: none; background: #f9fafb; transition: all 0.15s ease-in-out; }
        .chat-input:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgb(3 114 185 / 0.1); }
        .chat-input-container { position: relative; }
        .chat-input-actions { position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); display: flex; align-items: center; gap: 0.25rem; }
        .chat-input-btn { width: 36px; height: 36px; border-radius: 50%; border: none; background: transparent; color: #9ca3af; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s ease-in-out; }
        .chat-input-btn:hover { background: #f3f4f6; color: #6b7280; }
        .chat-input-btn.btn-send { background: var(--primary); color: white; }
        .chat-input-btn.btn-send:hover { background: #0369a1; transform: scale(1.05); }
        .chat-input-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .emoji-picker { position: absolute; bottom: 100%; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); padding: 1rem; width: 300px; max-height: 200px; overflow-y: auto; z-index: 10; }
        .emoji-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 0.25rem; }
        .emoji-btn { width: 32px; height: 32px; border: none; background: transparent; border-radius: 0.375rem; font-size: 18px; cursor: pointer; transition: all 0.15s ease-in-out; display: flex; align-items: center; justify-content: center; }
        .emoji-btn:hover { background: #f3f4f6; transform: scale(1.1); }
        .typing-indicator { display: flex; align-items: center; gap: 0.75rem; padding: 1rem; color: #6b7280; font-size: 13px; }
        .typing-dots { display: flex; gap: 4px; }
        .typing-dot { width: 6px; height: 6px; background: #9ca3af; border-radius: 50%; animation: typing 1.4s infinite ease-in-out; }
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typing { 0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; } 40% { transform: scale(1); opacity: 1; } }
        :root {
        --primary: #2563eb;
        --primary-light: #3b82f6;
        --success: #10b981;
        --warning: #f59e0b;
        --error: #ef4444;
        --gray-100: #f1f5f9;
        --gray-200: #e2e8f0;
        --gray-300: #cbd5e1;
        --gray-400: #94a3b8;
        --gray-500: #64748b;
        --gray-600: #475569;
        --gray-700: #334155;
        --user-color: #3b82f6;
        --agent-color: #10b981;
        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --radius-sm: 0.375rem;
        --radius-lg: 1rem;
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --transition: all 0.15s ease-in-out;
        }

        /* Avatares bonitos */
        .avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: 600;
        color: white;
        flex-shrink: 0;
        }
        .avatar-sm { width: 32px; height: 32px; font-size: 12px; }
        .avatar-md { width: 40px; height: 40px; font-size: 14px; }
        .avatar-user { background: linear-gradient(135deg, var(--user-color), #1d4ed8); }
        .avatar-agent { background: linear-gradient(135deg, var(--agent-color), #059669); }
        .avatar-system { background: linear-gradient(135deg, var(--gray-500), var(--gray-600)); }

        /* Botones bonitos */
        .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-4);
        font-size: 14px;
        font-weight: 500;
        border: 1px solid transparent;
        border-radius: var(--radius-sm);
        transition: var(--transition);
        cursor: pointer;
        text-decoration: none;
        }
        .btn-ghost { background: transparent; color: var(--gray-600); }
        .btn-ghost:hover { background: var(--gray-100); }

        /* Iconos */
        .icon { width: 20px; height: 20px; stroke-width: 1.5; }
        .icon-sm { width: 16px; height: 16px; }

        /* Chat header mejorado */
        .chat-header-info { display: flex; align-items: center; gap: var(--space-3); }
        .chat-header-actions { display: flex; align-items: center; gap: var(--space-2); }

        /* Mejoras a los mensajes */
        .message-content { box-shadow: var(--shadow-sm); }
    </style>
</head>
<body class="h-full bg-gray-50">
    
    <!-- Header Profesional -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h1 class="text-lg font-semibold text-gray-900">Portal de Consulta Médica</h1>
                        <p class="text-sm text-gray-500">Atención profesional especializada</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                        <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                        <span>Conexión segura</span>
                    </div>
                    <div class="text-sm text-gray-500" id="currentTime"></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <!-- Validación del Token -->
        <div id="validationSection" class="mb-8">
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                <div class="flex items-center justify-center space-x-3">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                    <p class="text-lg font-medium text-gray-700">Validando credenciales de acceso</p>
                </div>
                <div class="mt-4 text-center text-sm text-gray-500">
                    Verificando token: <code class="bg-gray-100 px-2 py-1 rounded"><?= substr($pToken, 0, 15) ?>...</code>
                </div>
            </div>
        </div>

        <!-- Selección de Salas -->
        <div id="roomSelectionSection" class="hidden">
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold">Bienvenido a su consulta</h2>
                            <p class="text-blue-100 mt-1">Seleccione el tipo de atención médica que necesita</p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg p-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div id="roomsLoading" class="text-center py-12">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        <p class="text-gray-500 font-medium">Cargando salas disponibles</p>
                    </div>
                    
                    <div id="roomsGrid" class="hidden grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Las salas se cargarán aquí -->
                    </div>
                    
                    <div id="roomsError" class="hidden text-center py-12">
                        <div class="text-red-500 mb-4">
                            <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Error de conexión</h3>
                        <p class="text-gray-600 mb-6">No se pudieron cargar las salas disponibles</p>
                        <button onclick="refreshRooms()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Reintentar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error de Validación -->
        <div id="validationError" class="hidden">
            <div class="bg-white rounded-lg shadow border border-red-200 overflow-hidden">
                <div class="bg-red-50 px-6 py-4 border-b border-red-200">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-red-900">Acceso no autorizado</h3>
                            <p class="text-red-700">El token de acceso no es válido</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 text-center">
                    <p class="text-gray-600 mb-4">
                        Su sesión ha expirado o el enlace de acceso no es válido.
                        Por favor, solicite un nuevo enlace de acceso.
                    </p>
                    <button onclick="window.location.reload()" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors">
                        Intentar de nuevo
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Chat Pantalla Completa -->
    <div id="chatSection" class="hidden chat-fullscreen">
        <!-- Chat Header -->
        <div class="chat-header">
            <div class="flex items-center justify-between w-full">
                <div class="flex-1">
                    <div class="grid grid-cols-2 gap-8">
                        <div>
                            <div class="text-sm text-gray-500">Tomador:</div>
                            <div class="font-semibold text-gray-900" id="nomTomador">-</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Beneficiario:</div>
                            <div class="font-semibold text-gray-900" id="nombrePaciente">-</div>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center space-x-2">
                    <button onclick="confirmEndChat()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Finalizar consulta">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <!-- Mensaje inicial del sistema -->
            <div class="message message-system">
                <div class="message-content">
                    <div class="text-sm font-medium text-gray-700 mb-1">CEM:</div>
                    <p>Bienvenido a Teleorientación CEM. Para urgencias o emergencias comunicate al #586.</p>
                    <div class="message-time" id="systemMessageTime">Ahora</div>
                </div>
            </div>
        </div>

        <!-- Indicador de escritura -->
        <div id="typingIndicator" class="hidden typing-indicator">
            <div class="flex items-center space-x-2">
                <span>El doctor está escribiendo</span>
                <div class="typing-dots">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="chat-input-area">
            <div class="chat-input-container">
                <!-- Selector de emojis -->
                <div id="emojiPicker" class="hidden emoji-picker">
                    <div class="emoji-grid">
                        <button class="emoji-btn" data-emoji="😊">😊</button>
                        <button class="emoji-btn" data-emoji="😢">😢</button>
                        <button class="emoji-btn" data-emoji="😟">😟</button>
                        <button class="emoji-btn" data-emoji="😷">😷</button>
                        <button class="emoji-btn" data-emoji="🤒">🤒</button>
                        <button class="emoji-btn" data-emoji="🩺">🩺</button>
                        <button class="emoji-btn" data-emoji="💊">💊</button>
                        <button class="emoji-btn" data-emoji="🏥">🏥</button>
                        <button class="emoji-btn" data-emoji="👨‍⚕️">👨‍⚕️</button>
                        <button class="emoji-btn" data-emoji="👩‍⚕️">👩‍⚕️</button>
                        <button class="emoji-btn" data-emoji="❤️">❤️</button>
                        <button class="emoji-btn" data-emoji="👍">👍</button>
                        <button class="emoji-btn" data-emoji="👎">👎</button>
                        <button class="emoji-btn" data-emoji="🙏">🙏</button>
                        <button class="emoji-btn" data-emoji="💉">💉</button>
                        <button class="emoji-btn" data-emoji="🩹">🩹</button>
                    </div>
                </div>

                <textarea 
                    id="messageInput" 
                    class="chat-input"
                    placeholder="Escribe tu mensaje..."
                    maxlength="500"
                    rows="1"
                ></textarea>
                
                <div class="chat-input-actions">
                    <button id="emojiButton" class="chat-input-btn" type="button" title="Emojis">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </button>
                    
                    <button id="sendButton" class="chat-input-btn btn-send" onclick="sendMessage()" disabled title="Enviar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Contador de caracteres y file upload -->
            <div class="flex justify-between items-center mt-3 text-xs text-gray-500">
                <div>
                    <input type="file" id="fileInput" accept="image/*,.pdf,.doc,.docx" class="hidden" onchange="handleFileUpload(this.files)">
                    <button onclick="document.getElementById('fileInput').click()" 
                            class="text-blue-600 hover:text-blue-700 flex items-center space-x-1 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                        </svg>
                        <span>Adjuntar archivo</span>
                    </button>
                </div>
                <span><span id="charCount">0</span>/500</span>
            </div>
        </div>
    </div>

    <!-- Loading Global -->
    <div id="globalLoading" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-8 shadow-2xl text-center max-w-sm">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Procesando</h3>
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
        let patientData = null;

        // Inicialización
        document.addEventListener('DOMContentLoaded', async () => {
            console.log('🚀 Portal de pacientes iniciado');
            updateTime();
            setInterval(updateTime, 1000);
            
            setupEventListeners();
            setupEmojiPicker();
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
                
                const authClient = new AuthClient();
                
                // Usar validatePToken para obtener datos completos
                const validationResult = await authClient.validatePToken(CONFIG.PATIENT_TOKEN);
                
                if (validationResult.success) {
                    console.log('✅ pToken válido');
                    console.log('📋 Datos recibidos:', validationResult.data);

                // Extraer datos de la membresía
                if (validationResult.data && validationResult.data.data && validationResult.data.data.membresias && validationResult.data.data.membresias.length > 0) {
                    const membresia = validationResult.data.data.membresias[0];
                    
                    // Extraer nombre del tomador
                    const nomTomador = membresia.nomTomador || 'Sistema de Atención';
                    
                    // Buscar beneficiario principal
                    const beneficiarioPrincipal = membresia.beneficiarios.find(ben => ben.tipo_ben === 'PPAL');
                    
                    if (beneficiarioPrincipal) {
                        // Construir nombre completo del paciente
                        const nombreCompleto = [
                            beneficiarioPrincipal.primer_nombre,
                            beneficiarioPrincipal.segundo_nombre,
                            beneficiarioPrincipal.primer_apellido,
                            beneficiarioPrincipal.segundo_apellido
                        ].filter(nombre => nombre && nombre.trim()).join(' ');
                        
                        patientData = {
                            nombreCompleto: nombreCompleto,
                            nomTomador: nomTomador,
                            beneficiario: beneficiarioPrincipal
                        };
                        
                        console.log('👤 Datos extraídos:', patientData);
                    } else {
                        console.warn('⚠️ No se encontró beneficiario principal');
                    }
                } else {
                    console.warn('⚠️ No se encontraron datos de membresía');
                }
                    
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
                     class="cursor-pointer p-6 border border-gray-200 rounded-lg hover:border-blue-300 hover:shadow-lg transition-all duration-300 group bg-white ${!room.available ? 'opacity-60' : ''}">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 ${getRoomColorClass(room.type)} rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform">
                                ${getRoomIcon(room.type)}
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg font-semibold text-gray-900">${room.name}</h3>
                                ${room.available ? 
                                    '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">Disponible</span>' :
                                    '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">No disponible</span>'
                                }
                            </div>
                            <p class="text-gray-600 text-sm mb-3">${room.description}</p>
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <span>Tiempo estimado: ${room.estimated_wait || '5-10 min'}</span>
                                <span>En cola: ${room.current_queue || 0}</span>
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

        // FLUJO REAL: 4. Seleccionar sala
        async function selectRoom(roomId, roomName) {
            console.log('🎯 Seleccionando sala:', roomId);
            showLoading('Conectando con ' + roomName + '...');
            
            try {
                const selectResult = await window.authClient.selectRoom(roomId, {
                    source: 'patient_portal',
                    browser: navigator.userAgent
                }, CONFIG.PATIENT_TOKEN);
                
                if (!selectResult.success) {
                    throw new Error(selectResult.error || 'Error seleccionando sala');
                }
                
                console.log('✅ Sala seleccionada en auth-service');

                // Guardar datos del paciente si vienen en la respuesta
                if (selectResult.room_data && selectResult.room_data.patient) {
                    patientData = selectResult.room_data.patient;
                    console.log('👤 Datos del paciente guardados:', patientData);
                } else if (selectResult.room_data && selectResult.room_data.user) {
                    patientData = selectResult.room_data.user;
                    console.log('👤 Datos del usuario guardados:', patientData);
                }

                const updatedPToken = selectResult.ptoken || CONFIG.PATIENT_TOKEN;
                console.log('🔑 Usando pToken actualizado para chat:', updatedPToken.substring(0, 15) + '...');
                
                window.chatClient = new ChatClient();
                
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
            
            // Mostrar información del chat con verificaciones
            const nomTomadorElement = document.getElementById('nomTomador');
            if (nomTomadorElement) {
                if (patientData && patientData.nomTomador) {
                    nomTomadorElement.textContent = patientData.nomTomador;
                } else {
                    nomTomadorElement.textContent = 'Sistema de Atención';
                }
            }

            // Mostrar nombre del beneficiario con verificación
            const nombrePacienteElement = document.getElementById('nombrePaciente');
            if (nombrePacienteElement) {
                if (patientData && patientData.nombreCompleto) {
                    nombrePacienteElement.textContent = patientData.nombreCompleto;
                } else {
                    nombrePacienteElement.textContent = 'Paciente';
                }
            }

            console.log('👤 Nombres asignados - Tomador:', nomTomadorElement?.textContent, 'Beneficiario:', nombrePacienteElement?.textContent);
                            
            // Actualizar hora del mensaje del sistema con verificación
            const systemMessageTimeElement = document.getElementById('systemMessageTime');
            if (systemMessageTimeElement) {
                const now = new Date();
                systemMessageTimeElement.textContent = now.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            chatActive = true;
            console.log('💬 Chat abierto:', roomName);
            
            // Scroll al final con verificación
            setTimeout(() => {
                const messagesContainer = document.getElementById('chatMessages');
                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }, 100);
        }

        // Event listeners
        function setupEventListeners() {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', handleInputChange);
                messageInput.addEventListener('keydown', handleKeyDown);
            }
        }

        function setupEmojiPicker() {
            const emojiButton = document.getElementById('emojiButton');
            const emojiPicker = document.getElementById('emojiPicker');
            
            emojiButton.addEventListener('click', (e) => {
                e.stopPropagation();
                emojiPicker.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!emojiPicker.contains(e.target) && !emojiButton.contains(e.target)) {
                    emojiPicker.classList.add('hidden');
                }
            });

            document.querySelectorAll('.emoji-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const emoji = btn.dataset.emoji;
                    insertEmoji(emoji);
                    emojiPicker.classList.add('hidden');
                });
            });
        }

        function insertEmoji(emoji) {
            const input = document.getElementById('messageInput');
            const cursorPos = input.selectionStart;
            const textBefore = input.value.substring(0, cursorPos);
            const textAfter = input.value.substring(cursorPos);
            
            input.value = textBefore + emoji + textAfter;
            input.focus();
            input.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
            
            handleInputChange({ target: input });
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

        function backToRoomSelection() {
            if (window.chatClient) {
                window.chatClient.disconnect();
            }
            
            document.getElementById('chatSection').classList.add('hidden');
            document.getElementById('roomSelectionSection').classList.remove('hidden');
            
            showNotification('Chat finalizado', 'success');
            chatActive = false;
        }

        function confirmEndChat() {
            document.getElementById('confirmEndChatModal').classList.remove('hidden');
        }

        function closeConfirmModal() {
            document.getElementById('confirmEndChatModal').classList.add('hidden');
        }

        function acceptEndChat() {
            closeConfirmModal();
            backToRoomSelection();
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
                'general': 'bg-blue-100',
                'medical': 'bg-green-100', 
                'support': 'bg-purple-100',
                'emergency': 'bg-red-100'
            };
            return colors[roomType] || 'bg-blue-100';
        }

        function getRoomIcon(roomType) {
            const icons = {
                'general': '<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path></svg>',
                'medical': '<svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path></svg>',
                'support': '<svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364"></path></svg>',
                'emergency': '<svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
            };
            return icons[roomType] || icons['general'];
        }

        console.log('🏥 Portal de pacientes PROFESIONAL v4.0');
    </script>
    <!-- Modal de Confirmación -->
    <div id="confirmEndChatModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-sm w-full mx-4">
            <div class="text-center">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">¿Finalizar consulta?</h3>
                <p class="text-gray-600 mb-6">Esta acción cerrará la consulta médica actual y regresará a la selección de salas.</p>
                <div class="flex space-x-3">
                    <button onclick="closeConfirmModal()" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                        Cancelar
                    </button>
                    <button onclick="acceptEndChat()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Finalizar
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>