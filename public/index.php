<?php
// public/index.php - Portal Médico Mejorado con KISS
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
                    <?php if ($isAuthenticated): ?>
                        <!-- Usuario autenticado -->
                        <div class="flex items-center space-x-3">
                            <div class="flex items-center space-x-2">
                                <div class="w-8 h-8 bg-medical-100 rounded-full flex items-center justify-center">
                                    <span class="text-medical-700 text-sm font-medium">
                                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                                    </span>
                                </div>
                                <span class="text-sm font-medium text-gray-700">
                                    <?= htmlspecialchars($user['name'] ?? 'Usuario') ?>
                                </span>
                            </div>
                            <button onclick="logout()" 
                                    class="text-gray-500 hover:text-gray-700 text-sm transition-colors">
                                Cerrar Sesión
                            </button>
                        </div>
                        <!-- Usuario no autenticado -->
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if ($isAuthenticated): ?>
            <!-- Estado: Usuario autenticado - Selección de sala -->
            <div id="roomSelectionSection">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">
                            Bienvenido, <?= htmlspecialchars($user['name'] ?? 'Usuario') ?>
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Selecciona el tipo de consulta que necesitas</p>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-2">
                            <!-- Consulta General -->
                            <div onclick="selectRoom('general', 'Consulta General')" 
                                 class="room-card cursor-pointer p-6 border border-gray-200 rounded-lg hover:border-medical-300 hover:shadow-md transition-all group">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h4 class="text-sm font-medium text-gray-900">Consulta General</h4>
                                        <p class="text-sm text-gray-500">Consultas médicas generales y orientación</p>
                                        <p class="text-xs text-gray-400 mt-1">Tiempo estimado: 5-10 min</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Consulta Especializada -->
                            <div onclick="selectRoom('medical', 'Consulta Especializada')" 
                                 class="room-card cursor-pointer p-6 border border-gray-200 rounded-lg hover:border-medical-300 hover:shadow-md transition-all group">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h4 class="text-sm font-medium text-gray-900">Consulta Especializada</h4>
                                        <p class="text-sm text-gray-500">Atención médica especializada</p>
                                        <p class="text-xs text-gray-400 mt-1">Tiempo estimado: 10-15 min</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Soporte Técnico -->
                            <div onclick="selectRoom('support', 'Soporte Técnico')" 
                                 class="room-card cursor-pointer p-6 border border-gray-200 rounded-lg hover:border-medical-300 hover:shadow-md transition-all group">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h4 class="text-sm font-medium text-gray-900">Soporte Técnico</h4>
                                        <p class="text-sm text-gray-500">Ayuda con la plataforma</p>
                                        <p class="text-xs text-gray-400 mt-1">Tiempo estimado: 2-5 min</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Urgencias -->
                            <div onclick="selectRoom('emergency', 'Urgencias')" 
                                 class="room-card cursor-pointer p-6 border border-red-200 rounded-lg hover:border-red-300 hover:shadow-md transition-all bg-red-50 group">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center group-hover:bg-red-200 transition-colors">
                                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h4 class="text-sm font-medium text-gray-900">Urgencias</h4>
                                        <p class="text-sm text-gray-500">Atención médica urgente</p>
                                        <p class="text-xs text-gray-400 mt-1">Atención prioritaria</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estado: En chat -->
            <div id="chatSection" class="hidden">
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
            <div class="text-center">
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

    <?php if (!$isAuthenticated): ?>
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
                    <form id="loginFormData" class="space-y-4">
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
                    <form id="registerFormData" class="space-y-4">
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
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Contraseña</label>
                            <input type="password" id="confirmPassword" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500"
                                   placeholder="Repite tu contraseña">
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
    <?php endif; ?>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 shadow-xl">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-medical-600"></div>
                <span class="text-gray-700">Procesando...</span>
            </div>
        </div>
    </div>

    <!-- Include Auth Client -->
    <script src="assets/js/auth-client.js"></script>
    
    <script>
        // Configuration - usando tus URLs reales
        const CONFIG = {
            AUTH_SERVICE_URL: 'http://187.33.158.246:8080/auth',
            CHAT_SERVICE_URL: 'http://187.33.158.246:8080/chat',
            WS_URL: 'ws://187.33.158.246:8080/ws'
        };

        // Global state
        let currentUser = <?= $isAuthenticated ? json_encode($user) : 'null' ?>;
        let currentSession = null;
        let websocket = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Portal Médico iniciado');
            // Debug info
            console.log('CONFIG:', CONFIG);
            console.log('currentUser:', currentUser);
            console.log('authClient available:', !!window.authClient);

            if (currentUser) {
                console.log('✅ Usuario autenticado desde PHP:', currentUser.name);
                // Sincronizar con authClient si existe
                if (window.authClient) {
                    window.authClient.user = currentUser;
                    console.log('✅ AuthClient sincronizado');
                }
            }
            setupEventListeners();
            
            <?php if ($isAuthenticated): ?>
            console.log('Usuario autenticado:', <?= json_encode($user['name'] ?? 'Usuario') ?>);
            <?php endif; ?>
        });

        // Setup event listeners
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

            <?php if (!$isAuthenticated): ?>
            // Auth form submissions
            const loginForm = document.getElementById('loginFormData');
            const registerForm = document.getElementById('registerFormData');
            
            if (loginForm) {
                loginForm.addEventListener('submit', handleLogin);
            }
            
            if (registerForm) {
                registerForm.addEventListener('submit', handleRegister);
            }
            <?php endif; ?>
        }

        <?php if (!$isAuthenticated): ?>
        // Authentication functions
        async function handleLogin(e) {
            e.preventDefault();
            showLoading();

            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value;

            try {
                const response = await fetch(`${CONFIG.AUTH_SERVICE_URL}/login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showNotification('Inicio de sesión exitoso', 'success');
                    window.location.reload();
                } else {
                    showNotification(result.message || 'Error al iniciar sesión', 'error');
                }
            } catch (error) {
                console.error('Login error:', error);
                showNotification('Error de conexión', 'error');
            } finally {
                hideLoading();
            }
        }

        async function handleRegister(e) {
            e.preventDefault();
            
            const password = document.getElementById('registerPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                showNotification('Las contraseñas no coinciden', 'error');
                return;
            }

            showLoading();

            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const email = document.getElementById('registerEmail').value.trim();

            try {
                const response = await fetch(`${CONFIG.AUTH_SERVICE_URL}/register`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: `${firstName} ${lastName}`,
                        email,
                        password,
                        role: 1
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showNotification('Cuenta creada exitosamente', 'success');
                    window.location.reload();
                } else {
                    showNotification(result.message || 'Error al crear la cuenta', 'error');
                }
            } catch (error) {
                console.error('Register error:', error);
                showNotification('Error de conexión', 'error');
            } finally {
                hideLoading();
            }
        }

        // Modal functions
        function showAuthModal(type = 'login') {
            document.getElementById('authModal').classList.remove('hidden');
            if (type === 'login') {
                showLoginForm();
            } else {
                showRegisterForm();
            }
        }

        function closeAuthModal() {
            document.getElementById('authModal').classList.add('hidden');
        }

        function showLoginForm() {
            document.getElementById('authModalTitle').textContent = 'Iniciar Sesión';
            document.getElementById('loginForm').classList.remove('hidden');
            document.getElementById('registerForm').classList.add('hidden');
        }

        function showRegisterForm() {
            document.getElementById('authModalTitle').textContent = 'Crear Cuenta';
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('registerForm').classList.remove('hidden');
        }
        <?php endif; ?>

        <?php if ($isAuthenticated): ?>
        // Room selection
        async function selectRoom(roomId, roomName) {
            console.log('🎯 Seleccionando sala:', roomId, roomName);
            console.log('CONFIG.AUTH_SERVICE_URL:', CONFIG.AUTH_SERVICE_URL);
            showLoading();

            try {

                const token = window.authClient?.getToken() || '';
                console.log('Token disponible:', !!token);
                
                const url = `${CONFIG.AUTH_SERVICE_URL}/rooms/${roomId}/select`;
                console.log('URL de sala:', url);
                // Get pToken for the room
                const roomResponse = await fetch(`${CONFIG.AUTH_SERVICE_URL}/rooms/${roomId}/select`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${window.authClient?.getToken() || ''}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_data: {
                            source: 'patient_portal',
                            selected_at: new Date().toISOString()
                        }
                    })
                });

                const roomResult = await roomResponse.json();

                if (!roomResponse.ok || !roomResult.success) {
                    throw new Error(roomResult.message || 'Error seleccionando sala');
                }

                const pToken = roomResult.data.ptoken;

                // Join chat session
                const chatResponse = await fetch(`${CONFIG.CHAT_SERVICE_URL}/join`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        room_id: roomId,
                        ptoken: pToken,
                        user_data: { source: 'patient_portal' }
                    })
                });

                const chatResult = await chatResponse.json();

                if (chatResponse.ok && chatResult.success) {
                    currentSession = {
                        id: chatResult.data.session_id,
                        room_id: roomId,
                        room_name: roomName,
                        ptoken: pToken,
                        status: chatResult.data.status
                    };

                    document.getElementById('chatRoomName').textContent = roomName;
                    document.getElementById('chatStatus').textContent = 'Conectado';
                    
                    showChatInterface();
                    initWebSocket(pToken, roomId);
                    
                    showNotification(`Conectado a ${roomName}`, 'success');
                } else {
                    throw new Error(chatResult.message || 'Error iniciando chat');
                }
            } catch (error) {
                console.error('Error selecting room:', error);
                showNotification(error.message || 'Error conectando a la sala', 'error');
            } finally {
                hideLoading();
            }
        }

        function showChatInterface() {
            document.getElementById('roomSelectionSection').classList.add('hidden');
            document.getElementById('chatSection').classList.remove('hidden');
        }

        // WebSocket connection
        function initWebSocket(pToken, roomId) {
            try {
                websocket = new WebSocket(CONFIG.WS_URL);
                
                websocket.onopen = function() {
                    console.log('WebSocket conectado');
                    websocket.send(JSON.stringify({
                        type: 'authenticate',
                        ptoken: pToken
                    }));
                };

                websocket.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        handleWebSocketMessage(data);
                    } catch (error) {
                        console.error('Error parsing WebSocket message:', error);
                    }
                };

                websocket.onclose = function() {
                    console.log('WebSocket desconectado');
                    document.getElementById('chatStatus').textContent = 'Desconectado';
                };

                websocket.onerror = function(error) {
                    console.error('WebSocket error:', error);
                    document.getElementById('chatStatus').textContent = 'Error de conexión';
                };

            } catch (error) {
                console.error('Error initializing WebSocket:', error);
                initHTTPPolling();
            }
        }

        function handleWebSocketMessage(data) {
            switch (data.type) {
                case 'authenticated':
                    console.log('WebSocket authenticated');
                    websocket.send(JSON.stringify({
                        type: 'join_room',
                        room_id: currentSession.room_id,
                        session_id: currentSession.id
                    }));
                    break;

                case 'room_joined':
                    console.log('Joined room successfully');
                    document.getElementById('chatStatus').textContent = 'En línea';
                    break;

                case 'message_received':
                    addMessageToChat(data.content, data.sender_type, data.timestamp);
                    break;

                case 'user_typing':
                    showTypingIndicator();
                    break;

                case 'user_stop_typing':
                    hideTypingIndicator();
                    break;

                default:
                    console.log('Unknown WebSocket message type:', data.type);
            }
        }

        // Chat functions
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !currentSession) return;

            if (websocket && websocket.readyState === WebSocket.OPEN) {
                websocket.send(JSON.stringify({
                    type: 'send_message',
                    content: message,
                    session_id: currentSession.id
                }));
            } else {
                sendMessageHTTP(message);
            }

            addMessageToChat(message, 'patient');
            
            input.value = '';
            input.style.height = 'auto';
            document.getElementById('sendButton').disabled = true;
        }

        async function sendMessageHTTP(message) {
            try {
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL.replace('/chats', '')}/messages/send`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: currentSession.id,
                        content: message,
                        sender_id: currentUser.id
                    })
                });

                if (!response.ok) {
                    console.error('Error sending message via HTTP');
                }
            } catch (error) {
                console.error('Error sending message via HTTP:', error);
            }
        }

        function addMessageToChat(content, senderType, timestamp) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageElement = document.createElement('div');
            
            const isUser = senderType === 'patient';
            const time = timestamp ? 
                new Date(timestamp).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) : 
                new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            
            messageElement.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-3`;
            messageElement.innerHTML = `
                <div class="max-w-xs lg:max-w-md">
                    <div class="${isUser ? 'bg-medical-600 text-white' : 'bg-white border border-gray-200'} rounded-lg px-4 py-2 shadow-sm">
                        <p class="text-sm">${content}</p>
                        <p class="text-xs ${isUser ? 'text-blue-100' : 'text-gray-500'} mt-1">${time}</p>
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function showTypingIndicator() {
            document.getElementById('typingIndicator').classList.remove('hidden');
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').classList.add('hidden');
        }

        function endChat() {
            if (confirm('¿Estás seguro de que quieres terminar el chat?')) {
                if (websocket) {
                    websocket.close();
                    websocket = null;
                }
                
                currentSession = null;
                document.getElementById('roomSelectionSection').classList.remove('hidden');
                document.getElementById('chatSection').classList.add('hidden');
                showNotification('Chat finalizado', 'success');
            }
        }

        // Emoji functions
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

        // HTTP polling fallback
        function initHTTPPolling() {
            if (!currentSession) return;
            
            const pollMessages = async () => {
                try {
                    const response = await fetch(`${CONFIG.CHAT_SERVICE_URL.replace('/chats', '')}/messages/${currentSession.id}?limit=10`);
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success && result.data.messages) {
                            console.log('Polled messages:', result.data.messages.length);
                        }
                    }
                } catch (error) {
                    console.error('Error polling messages:', error);
                }
            };

            setInterval(pollMessages, 3000);
        }
        <?php endif; ?>

        function logout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                if (websocket) {
                    websocket.close();
                    websocket = null;
                }
                
                // Use auth client logout
                if (window.authClient) {
                    window.authClient.logout();
                } else {
                    // Fallback
                    window.location.href = '/logout.php';
                }
            }
        }

        // Utility functions
        function showLoading() {
            document.getElementById('loadingSpinner').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').classList.add('hidden');
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all transform ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                'bg-blue-500 text-white'
            }`;
            
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            ${type === 'success' ? 
                                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>' :
                                type === 'error' ?
                                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>' :
                                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                            }
                        </svg>
                        <span>${message}</span>
                    </div>
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
            }, 5000);
        }

        // Close emoji picker when clicking outside
        document.addEventListener('click', function(e) {
            const picker = document.getElementById('emojiPicker');
            const button = document.getElementById('emojiButton');
            
            if (picker && button && 
                !picker.contains(e.target) && 
                !button.contains(e.target)) {
                picker.classList.add('hidden');
            }
        });

        // Debug mode for development
        <?php if ($isAuthenticated): ?>
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            window.debugMedical = {
                getCurrentUser: () => currentUser,
                getCurrentSession: () => currentSession,
                getWebSocket: () => websocket,
                testNotification: (msg, type) => showNotification(msg, type),
                simulateMessage: (content) => addMessageToChat(content, 'agent'),
                testEmoji: () => insertEmoji('😊')
            };
            console.log('🛠️ Debug mode activo. Usa window.debugMedical');
        }
        <?php endif; ?>
    </script>
</body>
</html>