<?php
// public/index.php - Portal Médico Completo con Auth-Service
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = auth();
$isAuthenticated = $auth->isAuthenticated();
$user = $isAuthenticated ? $auth->getUser() : null;

// Redirect staff to their panel
if ($isAuthenticated && $auth->isStaff()) {
    header("Location: /staff.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Médico - Sistema de Consulta</title>
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
                    }
                }
            }
        }
    </script>
    <style>
        /* Asegurar que los elementos auth-required se muestren cuando sea necesario */
        .auth-required.show { display: block !important; }
        .guest-only.hide { display: none !important; }
        
        /* Animaciones suaves para transiciones */
        .auth-required, .guest-only {
            transition: opacity 0.3s ease-in-out;
        }
        
        /* Asegurar que el spinner esté visible */
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="h-full">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-medical-600 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                                </svg>
                            </div>
                            <h1 class="text-xl font-semibold text-gray-900">Portal Médico</h1>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Usuario autenticado - SIEMPRE PRESENTE, controlado por JS -->
                    <div id="userAuthenticatedSection" class="auth-required hidden flex items-center space-x-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-medical-100 rounded-full flex items-center justify-center">
                                <span id="userInitial" class="text-medical-700 text-sm font-medium">
                                    <?= $isAuthenticated ? strtoupper(substr($user['name'] ?? 'U', 0, 1)) : 'U' ?>
                                </span>
                            </div>
                            <span id="userDisplayName" class="text-sm font-medium text-gray-700 user-name">
                                <?= $isAuthenticated ? htmlspecialchars($user['name'] ?? 'Usuario') : 'Usuario' ?>
                            </span>
                        </div>
                        <button onclick="logout()" 
                                class="text-gray-500 hover:text-gray-700 text-sm transition-colors">
                            Cerrar Sesión
                        </button>
                    </div>
                    
                    <!-- Usuario no autenticado - SIEMPRE PRESENTE, controlado por JS -->
                    <div id="userGuestSection" class="guest-only flex items-center space-x-3">
                        <button onclick="showAuthModal('login')" 
                                class="text-medical-600 hover:text-medical-700 text-sm font-medium transition-colors">
                            Iniciar Sesión
                        </button>
                        <button onclick="showAuthModal('register')" 
                                class="bg-medical-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-medical-700 transition-colors">
                            Crear Cuenta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if ($isAuthenticated): ?>
            <!-- Estado: Usuario autenticado - Selección de sala -->
            <div id="roomSelectionSection" class="auth-required">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">
                                    Bienvenido, <span class="user-name"><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></span>
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">Selecciona el tipo de consulta que necesitas</p>
                            </div>
                            <button onclick="refreshRooms()" 
                                    class="bg-medical-100 text-medical-700 px-3 py-2 rounded-lg text-sm hover:bg-medical-200 transition-colors">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Actualizar
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <!-- Loading de salas -->
                        <div id="roomsLoading" class="text-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-medical-600 mx-auto"></div>
                            <p class="text-gray-500 mt-2">Cargando salas disponibles...</p>
                        </div>
                        
                        <!-- Grid de salas -->
                        <div id="roomsGrid" class="hidden grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-2">
                            <!-- Las salas se cargarán dinámicamente aquí -->
                        </div>
                        
                        <!-- Error de salas -->
                        <div id="roomsError" class="hidden text-center py-8">
                            <div class="text-red-500 mb-4">
                                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Error cargando salas</h3>
                            <p class="text-gray-600 mb-4">No se pudieron cargar las salas disponibles</p>
                            <button onclick="refreshRooms()" 
                                    class="bg-medical-600 text-white px-4 py-2 rounded-lg hover:bg-medical-700 transition-colors">
                                Reintentar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estado: En chat -->
            <div id="chatSection" class="hidden auth-required">
                <div class="bg-white shadow rounded-lg">
                    <div class="h-96 flex flex-col">
                        <!-- Chat Header -->
                        <div class="flex-shrink-0 bg-medical-600 text-white px-6 py-4 rounded-t-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 id="chatRoomName" class="font-medium">Chat Médico</h3>
                                        <p id="chatStatus" class="text-sm text-blue-100">Conectando...</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button onclick="minimizeChat()" class="p-2 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                        </svg>
                                    </button>
                                    <button onclick="endChat()" class="p-2 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Messages Area -->
                        <div id="chatMessages" class="flex-1 overflow-y-auto px-4 py-4 space-y-4 bg-gray-50">
                            <!-- Welcome message -->
                            <div class="flex justify-center">
                                <div class="bg-white px-4 py-2 rounded-full shadow-sm border">
                                    <p class="text-sm text-gray-600">Consulta médica iniciada</p>
                                </div>
                            </div>
                        </div>

                        <!-- Typing indicator -->
                        <div id="typingIndicator" class="hidden px-4 py-2">
                            <div class="flex justify-start">
                                <div class="bg-white rounded-lg px-4 py-2 shadow-sm border">
                                    <div class="flex items-center space-x-2">
                                        <div class="flex space-x-1">
                                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                        </div>
                                        <span class="text-sm text-gray-500">Escribiendo...</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Message Input -->
                        <div class="flex-shrink-0 bg-white border-t border-gray-200 p-4">
                            <div class="flex items-end space-x-2">
                                <div class="flex-1">
                                    <textarea 
                                        id="messageInput" 
                                        placeholder="Escribe tu mensaje..."
                                        rows="1"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500 focus:border-transparent resize-none"
                                        style="min-height: 40px; max-height: 120px;"
                                    ></textarea>
                                </div>
                                <button 
                                    id="emojiButton" 
                                    onclick="toggleEmojiPicker()"
                                    class="p-2 text-gray-400 hover:text-gray-600 rounded-lg transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                                <button 
                                    id="sendButton" 
                                    onclick="sendMessage()"
                                    disabled
                                    class="p-2 bg-medical-600 text-white rounded-lg hover:bg-medical-700 focus:outline-none focus:ring-2 focus:ring-medical-500 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Emoji Picker -->
                            <div id="emojiPicker" class="hidden mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="grid grid-cols-8 gap-2">
                                    <button onclick="insertEmoji('😊')" class="p-2 hover:bg-gray-200 rounded text-lg">😊</button>
                                    <button onclick="insertEmoji('😢')" class="p-2 hover:bg-gray-200 rounded text-lg">😢</button>
                                    <button onclick="insertEmoji('😟')" class="p-2 hover:bg-gray-200 rounded text-lg">😟</button>
                                    <button onclick="insertEmoji('😷')" class="p-2 hover:bg-gray-200 rounded text-lg">😷</button>
                                    <button onclick="insertEmoji('🤒')" class="p-2 hover:bg-gray-200 rounded text-lg">🤒</button>
                                    <button onclick="insertEmoji('👍')" class="p-2 hover:bg-gray-200 rounded text-lg">👍</button>
                                    <button onclick="insertEmoji('👎')" class="p-2 hover:bg-gray-200 rounded text-lg">👎</button>
                                    <button onclick="insertEmoji('🙏')" class="p-2 hover:bg-gray-200 rounded text-lg">🙏</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Estado: No autenticado -->
            <div class="guest-only text-center">
                <div class="mx-auto max-w-md">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-medical-100 mb-4">
                        <svg class="h-8 w-8 text-medical-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Portal de Consulta Médica</h2>
                    <p class="text-gray-600 mb-6">
                        Conecta con profesionales de la salud las 24 horas del día.
                    </p>
                    <div class="space-y-3">
                        <button onclick="showAuthModal('register')" 
                                class="w-full flex justify-center py-3 px-4 rounded-lg text-sm font-medium text-white bg-medical-600 hover:bg-medical-700 transition-colors">
                            Crear Cuenta
                        </button>
                        <button onclick="showAuthModal('login')" 
                                class="w-full flex justify-center py-3 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Iniciar Sesión
                        </button>
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="mt-16 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="text-center p-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Consulta Privada</h3>
                    <p class="text-sm text-gray-600">Todas las conversaciones son privadas y confidenciales.</p>
                </div>
                
                <div class="text-center p-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Disponible 24/7</h3>
                    <p class="text-sm text-gray-600">Atención médica cuando la necesites.</p>
                </div>
                
                <div class="text-center p-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Respuesta Rápida</h3>
                    <p class="text-sm text-gray-600">Conecta con profesionales en minutos.</p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Auth Modal -->
    <div id="authModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 id="authModalTitle" class="text-lg font-medium text-gray-900">Acceder</h3>
                        <button onclick="closeAuthModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Login Form -->
                <div id="loginForm" class="p-6">
                    <form onsubmit="handleLoginSubmit(event)" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Correo Electrónico</label>
                            <input type="email" id="loginEmail" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500"
                                   placeholder="tu@email.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                            <input type="password" id="loginPassword" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500"
                                   placeholder="Tu contraseña">
                        </div>
                        <div class="flex items-center">
                            <input id="rememberMe" type="checkbox" class="h-4 w-4 text-medical-600 focus:ring-medical-500 border-gray-300 rounded">
                            <label for="rememberMe" class="ml-2 block text-sm text-gray-700">
                                Recordarme
                            </label>
                        </div>
                        <button type="submit" 
                                class="w-full bg-medical-600 text-white py-2 px-4 rounded-lg hover:bg-medical-700 transition-colors">
                            Iniciar Sesión
                        </button>
                    </form>
                    <p class="mt-4 text-center text-sm text-gray-600">
                        ¿No tienes cuenta? 
                        <button onclick="showRegisterForm()" class="text-medical-600 hover:text-medical-700 font-medium">
                            Regístrate aquí
                        </button>
                    </p>
                </div>

                <!-- Register Form -->
                <div id="registerForm" class="hidden p-6">
                    <form onsubmit="handleRegisterSubmit(event)" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input type="text" id="firstName" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500"
                                       placeholder="Tu nombre">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Apellido</label>
                                <input type="text" id="lastName" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500"
                                       placeholder="Tu apellido">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Correo Electrónico</label>
                            <input type="email" id="registerEmail" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500"
                                   placeholder="tu@email.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                            <input type="password" id="registerPassword" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500"
                                   placeholder="Mínimo 8 caracteres">
                            <p class="text-xs text-gray-500 mt-1">Debe contener al menos una mayúscula, una minúscula y un número</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Contraseña</label>
                            <input type="password" id="confirmPassword" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500"
                                   placeholder="Repite tu contraseña">
                        </div>
                        <div class="flex items-center">
                            <input id="acceptTerms" type="checkbox" required class="h-4 w-4 text-medical-600 focus:ring-medical-500 border-gray-300 rounded">
                            <label for="acceptTerms" class="ml-2 block text-sm text-gray-700">
                                Acepto los <a href="#" class="text-medical-600 hover:text-medical-500">términos y condiciones</a>
                            </label>
                        </div>
                        <button type="submit" 
                                class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors">
                            Crear Cuenta
                        </button>
                    </form>
                    <p class="mt-4 text-center text-sm text-gray-600">
                        ¿Ya tienes cuenta? 
                        <button onclick="showLoginForm()" class="text-medical-600 hover:text-medical-700 font-medium">
                            Inicia sesión aquí
                        </button>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 shadow-xl">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-medical-600"></div>
                <span class="text-gray-700">Procesando...</span>
            </div>
        </div>
    </div>

    <!-- Include Auth Client CORREGIDO -->
    <script src="assets/js/auth-client.js"></script>
    
    <script>
        // Configuration - URLs CORREGIDAS
        const CONFIG = {
            AUTH_SERVICE_URL: 'http://187.33.158.246:8080/auth',
            CHAT_SERVICE_URL: 'http://187.33.158.246:8080/chat',
            WS_URL: 'ws://187.33.158.246:8080/ws'
        };

        // Global state
        let currentUser = <?= $isAuthenticated ? json_encode($user) : 'null' ?>;
        let currentSession = null;
        let currentRooms = [];

        // INICIALIZAR AUTHCLIENT AQUÍ - DESPUÉS DE QUE SE CARGUE EL SCRIPT
        setTimeout(() => {
            console.log('🔧 Inicializando AuthClient...');
            window.authClient = new AuthClient(CONFIG.AUTH_SERVICE_URL);
            console.log('✅ AuthClient inicializado con URL:', CONFIG.AUTH_SERVICE_URL);
            
            // Ahora sí inicializar el resto
            initializePortal();
        }, 100);

        // Initialize
        function initializePortal() {
            console.log('🚀 Portal Médico iniciado');
            console.log('CONFIG:', CONFIG);
            console.log('currentUser:', currentUser);
            console.log('authClient available:', !!window.authClient);

            // Esperar a que authClient esté completamente cargado
            setTimeout(() => {
                console.log('🔍 Verificando estado de autenticación...');
                console.log('🔍 currentUser desde PHP:', currentUser);
                console.log('🔍 authClient.isAuthenticated():', window.authClient?.isAuthenticated());
                console.log('🔍 authClient.getUser():', window.authClient?.getUser());
                
                // Priorizar AuthClient sobre PHP si hay conflicto
                const isAuthenticatedJS = window.authClient && window.authClient.isAuthenticated();
                const isAuthenticatedPHP = !!currentUser;
                
                console.log('🔍 Estado de auth:', {
                    js: isAuthenticatedJS,
                    php: isAuthenticatedPHP
                });
                
                if (isAuthenticatedJS && !isAuthenticatedPHP) {
                    console.log('⚠️ Conflicto: JS dice autenticado, PHP dice no autenticado');
                    console.log('✅ Priorizando estado de JavaScript (AuthClient)');
                    
                    // Usar datos de AuthClient
                    currentUser = window.authClient.getUser();
                    
                    // Forzar UI de usuario autenticado
                    forceAuthenticatedUI();
                    
                } else if (isAuthenticatedPHP && !isAuthenticatedJS) {
                    console.log('⚠️ Conflicto: PHP dice autenticado, JS dice no autenticado');
                    console.log('✅ Sincronizando AuthClient con datos de PHP');
                    
                    // Sincronizar AuthClient con PHP
                    if (window.authClient) {
                        window.authClient.user = currentUser;
                        window.authClient.updateUI();
                    }
                    
                    forceAuthenticatedUI();
                    
                } else if (isAuthenticatedJS && isAuthenticatedPHP) {
                    console.log('✅ Estados sincronizados: usuario autenticado');
                    forceAuthenticatedUI();
                    
                } else {
                    console.log('👤 Usuario no autenticado en ambos sistemas');
                    forceGuestUI();
                }
                
                setupEventListeners();
            }, 500);
        }
            
        // Función para forzar UI autenticada
        function forceAuthenticatedUI() {
            console.log('🔧 Forzando UI de usuario autenticado...');
            
            // Ocultar TODAS las secciones de invitado
            document.querySelectorAll('.guest-only').forEach(el => {
                el.style.display = 'none';
                el.classList.add('hidden');
            });
            
            // Mostrar TODAS las secciones de autenticado
            document.querySelectorAll('.auth-required').forEach(el => {
                el.style.display = 'block';
                el.classList.remove('hidden');
            });
            
            // Forzar mostrar sección específica de salas
            const roomSection = document.getElementById('roomSelectionSection');
            if (roomSection) {
                roomSection.style.display = 'block';
                roomSection.classList.remove('hidden');
                console.log('✅ Sección de salas forzada a visible');
            }
            
            // Forzar mostrar sección de usuario autenticado en header
            const userAuthSection = document.getElementById('userAuthenticatedSection');
            const userGuestSection = document.getElementById('userGuestSection');
            
            if (userAuthSection) {
                userAuthSection.style.display = 'flex';
                userAuthSection.classList.remove('hidden');
                userAuthSection.classList.add('flex');
                console.log('✅ Header de usuario autenticado visible');
            }
            if (userGuestSection) {
                userGuestSection.style.display = 'none';
                userGuestSection.classList.add('hidden');
                userGuestSection.classList.remove('flex');
            }
            
            // Actualizar información del usuario
            const user = window.authClient?.getUser() || currentUser;
            if (user) {
                const userInitial = document.getElementById('userInitial');
                const userDisplayName = document.getElementById('userDisplayName');
                
                if (userInitial && user.name) {
                    userInitial.textContent = user.name.charAt(0).toUpperCase();
                }
                if (userDisplayName && user.name) {
                    userDisplayName.textContent = user.name;
                }
            }
            
            // Cargar salas después de asegurar que todo esté visible
            setTimeout(() => {
                loadAvailableRooms();
            }, 200);
        }
        
        // Función para forzar UI de invitado
        function forceGuestUI() {
            console.log('🔧 Forzando UI de invitado...');
            
            const userAuthSection = document.getElementById('userAuthenticatedSection');
            const userGuestSection = document.getElementById('userGuestSection');
            
            if (userAuthSection) {
                userAuthSection.style.display = 'none';
                userAuthSection.classList.add('hidden');
                userAuthSection.classList.remove('flex');
            }
            if (userGuestSection) {
                userGuestSection.style.display = 'flex';
                userGuestSection.classList.remove('hidden');
                userGuestSection.classList.add('flex');
            }
            
            document.querySelectorAll('.auth-required').forEach(el => {
                el.style.display = 'none';
                el.classList.add('hidden');
            });
            document.querySelectorAll('.guest-only').forEach(el => {
                el.style.display = 'block';
                el.classList.remove('hidden');
            });
        }

        // ===============================
        // GESTIÓN DE SALAS COMPLETA
        // ===============================

        async function loadAvailableRooms() {
            console.log('🏠 Cargando salas disponibles...');
            console.log('🔍 AuthClient disponible:', !!window.authClient);
            console.log('🔍 Usuario autenticado:', window.authClient?.isAuthenticated());
            
            // Verificar que el usuario esté autenticado
            if (!window.authClient || !window.authClient.isAuthenticated()) {
                console.log('❌ Usuario no autenticado, no se pueden cargar salas');
                showRoomsError('Debes iniciar sesión para ver las salas disponibles');
                return;
            }
            
            // Mostrar loading
            const loadingEl = document.getElementById('roomsLoading');
            const gridEl = document.getElementById('roomsGrid');
            const errorEl = document.getElementById('roomsError');
            
            console.log('🔍 Elementos DOM:', {
                loading: !!loadingEl,
                grid: !!gridEl,
                error: !!errorEl
            });
            
            if (loadingEl) loadingEl.classList.remove('hidden');
            if (gridEl) gridEl.classList.add('hidden');
            if (errorEl) errorEl.classList.add('hidden');
            
            try {
                console.log('📡 Llamando a getAvailableRooms...');
                const rooms = await window.authClient.getAvailableRooms();
                console.log('📋 Respuesta de salas:', rooms);
                console.log('📋 Tipo de respuesta:', typeof rooms);
                console.log('📋 Es array:', Array.isArray(rooms));
                console.log('📋 Longitud:', rooms?.length);
                
                if (rooms && Array.isArray(rooms) && rooms.length > 0) {
                    currentRooms = rooms;
                    console.log('✅ Salas válidas recibidas, mostrando...');
                    displayRooms(rooms);
                } else if (rooms && rooms.length === 0) {
                    console.log('⚠️ Array de salas vacío');
                    showRoomsError('No hay salas disponibles en este momento. Intenta más tarde.');
                } else {
                    console.log('⚠️ Respuesta de salas inválida:', rooms);
                    showRoomsError('Error procesando las salas disponibles.');
                }
                
            } catch (error) {
                console.error('❌ Error cargando salas:', error);
                console.error('❌ Stack del error:', error.stack);
                
                // Determinar el mensaje de error apropiado
                let errorMessage = 'Error de conexión. Verifica tu conexión a internet y vuelve a intentar.';
                
                if (error.message.includes('404')) {
                    errorMessage = 'Servicio de salas no disponible. Contacta al administrador.';
                } else if (error.message.includes('403')) {
                    errorMessage = 'No tienes permisos para acceder a las salas.';
                } else if (error.message.includes('401')) {
                    errorMessage = 'Sesión expirada. Por favor, inicia sesión nuevamente.';
                }
                
                showRoomsError(errorMessage);
            }
        }

        // FUNCIÓN FALTANTE: showRoomsError
        function showRoomsError(message) {
            console.log('🔧 Mostrando error de salas:', message);
            
            const loadingEl = document.getElementById('roomsLoading');
            const gridEl = document.getElementById('roomsGrid');
            const errorEl = document.getElementById('roomsError');
            
            if (loadingEl) {
                loadingEl.classList.add('hidden');
                console.log('✅ Loading oculto');
            }
            if (gridEl) {
                gridEl.classList.add('hidden');
                console.log('✅ Grid oculto');
            }
            
            // Mostrar error - con fallback si el elemento no existe
            if (errorEl) {
                errorEl.classList.remove('hidden');
                
                const errorMessage = errorEl.querySelector('p');
                if (errorMessage) {
                    errorMessage.textContent = message;
                }
                console.log('✅ Error mostrado');
            } else {
                console.log('⚠️ Elemento roomsError no encontrado, usando alert fallback...');
                alert('Error: ' + message);
            }
        }

        // FUNCIÓN FALTANTE: displayRooms
        function displayRooms(rooms) {
            console.log('🏠 Mostrando', rooms.length, 'salas');
            
            const roomsGrid = document.getElementById('roomsGrid');
            const roomsLoading = document.getElementById('roomsLoading');
            
            if (!roomsGrid) {
                console.error('❌ Elemento roomsGrid no encontrado');
                return;
            }
            
            // Ocultar loading
            if (roomsLoading) roomsLoading.classList.add('hidden');
            roomsGrid.classList.remove('hidden');
            
            // Generar HTML de salas
            roomsGrid.innerHTML = rooms.map(room => `
                <div onclick="selectRoom('${room.id}', '${room.name}')" 
                     class="room-card cursor-pointer p-6 border border-gray-200 rounded-lg hover:border-medical-300 hover:shadow-md transition-all group ${!room.available ? 'opacity-60' : ''}">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 ${getRoomColorClass(room.type)} rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform">
                                ${getRoomIcon(room.type)}
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-medium text-gray-900">${room.name}</h4>
                                ${room.available ? 
                                    '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Disponible</span>' :
                                    '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">No disponible</span>'
                                }
                            </div>
                            <p class="text-sm text-gray-500 mt-1">${room.description}</p>
                            <div class="flex items-center justify-between mt-2">
                                <p class="text-xs text-gray-400">
                                    ${room.available ? 
                                        `Tiempo estimado: ${room.estimated_wait || '5-10 min'}` : 
                                        room.estimated_wait || 'Fuera de horario'
                                    }
                                </p>
                                ${room.current_queue > 0 ? 
                                    `<span class="text-xs text-orange-600">${room.current_queue} en cola</span>` : 
                                    ''
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
            
            console.log('✅ Salas mostradas en el DOM');
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
                'support': '<svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>',
                'emergency': '<svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
            };
            return icons[roomType] || icons['general'];
        }

        async function selectRoom(roomId, roomName) {
            console.log('🎯 Seleccionando sala:', roomId, roomName);
            
            if (!window.authClient.isAuthenticated()) {
                window.authClient.showError('Debes iniciar sesión para acceder a las salas');
                showAuthModal('login');
                return;
            }
            
            // Mostrar loading
            showLoading();
            
            try {
                const result = await window.authClient.selectRoom(roomId, {
                    selected_at: new Date().toISOString(),
                    source: 'patient_portal'
                });
                
                if (result.success) {
                    window.authClient.showSuccess(`Conectado a ${roomName} exitosamente`);
                    
                    // Guardar datos de sesión
                    currentSession = {
                        roomId: roomId,
                        roomName: roomName,
                        ptoken: result.ptoken,
                        sessionData: result.room_data
                    };
                    
                    // Mostrar interfaz de chat
                    showChatInterface(roomName);
                    
                    // Inicializar chat si existe el cliente
                    if (window.chatClient) {
                        window.chatClient.connect(result.ptoken, roomId);
                    } else {
                        // Simular chat básico
                        simulateBasicChat(roomName);
                    }
                    
                } else {
                    window.authClient.showError(result.error || 'Error seleccionando sala');
                }
                
            } catch (error) {
                console.error('❌ Error seleccionando sala:', error);
                window.authClient.showError('Error de conexión al seleccionar sala');
            } finally {
                hideLoading();
            }
        }

        function refreshRooms() {
            console.log('🔄 Actualizando salas...');
            window.authClient.showInfo('Actualizando salas disponibles...');
            loadAvailableRooms();
        }

        // ===============================
        // RESTO DE FUNCIONES (chat, etc.)
        // ===============================

        function showChatInterface(roomName) {
            document.getElementById('roomSelectionSection').classList.add('hidden');
            document.getElementById('chatSection').classList.remove('hidden');
            
            // Actualizar título del chat
            document.getElementById('chatRoomName').textContent = roomName;
            document.getElementById('chatStatus').textContent = 'Conectando...';
        }

        function simulateBasicChat(roomName) {
            // Simular mensaje de bienvenida
            setTimeout(() => {
                document.getElementById('chatStatus').textContent = 'Conectado';
                
                addMessageToChat(
                    `¡Hola! Te damos la bienvenida a ${roomName}. Un profesional te atenderá en breve. ¿En qué podemos ayudarte hoy?`,
                    'agent',
                    'Sistema Médico'
                );
            }, 1500);
        }

        function addMessageToChat(content, senderType, senderName = null) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageElement = document.createElement('div');
            
            const isUser = senderType === 'user';
            const time = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            
            messageElement.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-3`;
            messageElement.innerHTML = `
                <div class="max-w-xs lg:max-w-md">
                    <div class="${isUser ? 'bg-medical-600 text-white' : 'bg-white border border-gray-200'} rounded-lg px-4 py-2 shadow-sm">
                        ${!isUser && senderName ? `
                            <div class="flex items-center space-x-2 mb-2">
                                <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-gray-900">${senderName}</span>
                            </div>
                        ` : ''}
                        <p class="text-sm">${content}</p>
                        <p class="text-xs ${isUser ? 'text-blue-100' : 'text-gray-500'} mt-1">${time}</p>
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function setupEventListeners() {
            // Auto-resize textarea
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                    
                    const sendButton = document.getElementById('sendButton');
                    if (sendButton) {
                        sendButton.disabled = this.value.trim() === '';
                    }
                });

                messageInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        if (this.value.trim()) {
                            sendMessage();
                        }
                    }
                });
            }
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message) return;
            
            // Añadir mensaje del usuario
            addMessageToChat(message, 'user');
            
            // Limpiar input
            input.value = '';
            input.style.height = 'auto';
            document.getElementById('sendButton').disabled = true;
            
            // Simular respuesta del agente
            simulateAgentResponse(message);
        }

        function simulateAgentResponse(userMessage) {
            // Mostrar indicador de escritura
            showTypingIndicator();
            
            // Respuestas simuladas
            const responses = [
                "Entiendo tu consulta. Déjame revisar la información que me proporcionas.",
                "Gracias por esa información. ¿Podrías contarme más detalles sobre tus síntomas?",
                "Basado en lo que me describes, te haré algunas preguntas adicionales.",
                "Es importante que me proporciones todos los detalles para poder ayudarte mejor.",
                "Te voy a dar algunas recomendaciones. ¿Tienes alguna pregunta específica?"
            ];
            
            setTimeout(() => {
                hideTypingIndicator();
                const response = responses[Math.floor(Math.random() * responses.length)];
                addMessageToChat(response, 'agent', 'Dr. García');
            }, 2000 + Math.random() * 2000);
        }

        function showTypingIndicator() {
            document.getElementById('typingIndicator').classList.remove('hidden');
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').classList.add('hidden');
        }

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
            
            // Hide picker
            document.getElementById('emojiPicker').classList.add('hidden');
        }

        function endChat() {
            if (confirm('¿Estás seguro de que quieres terminar la consulta?')) {
                // Mostrar salas de nuevo
                document.getElementById('chatSection').classList.add('hidden');
                document.getElementById('roomSelectionSection').classList.remove('hidden');
                
                // Limpiar sesión actual
                currentSession = null;
                
                window.authClient.showSuccess('Consulta finalizada exitosamente');
                
                console.log('👋 Chat finalizado por el usuario');
            }
        }

        function minimizeChat() {
            // Toggle entre minimizado y maximizado
            const chatSection = document.getElementById('chatSection');
            chatSection.classList.toggle('minimized');
        }

        function showLoading() {
            document.getElementById('loadingSpinner').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').classList.add('hidden');
        }

        function logout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.authClient.logout();
            }
        }

        // Escuchar cambios de autenticación
        window.addEventListener('authStateChanged', function(event) {
            const { isAuthenticated, user } = event.detail;
            
            console.log('🔄 Evento authStateChanged:', { isAuthenticated, user: user?.name });
            
            if (isAuthenticated && user) {
                console.log('🔄 Usuario autenticado via evento, mostrando interfaz de paciente...');
                
                // Actualizar currentUser global
                currentUser = user;
                
                forceAuthenticatedUI();
                
            } else {
                console.log('🔄 Usuario no autenticado via evento, mostrando interfaz de invitado...');
                
                // Actualizar currentUser global
                currentUser = null;
                
                forceGuestUI();
            }
        });

        console.log('🚀 Portal Médico completamente funcional cargado!');
    </script>
</body>
</html>