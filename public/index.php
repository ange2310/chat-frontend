<?php
// public/index.php - Portal de staff CORREGIDO
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = auth();
$isAuthenticated = $auth->isAuthenticated();

// REDIRECCIÓN CORREGIDA - Solo si ya está autenticado Y es staff
if ($isAuthenticated && $auth->isStaff()) {
    header("Location: /staff.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Médico - Acceso Personal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body class="h-full">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-12 w-12 bg-blue-600 rounded-lg flex items-center justify-center">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                    </svg>
                </div>
                <h2 class="mt-6 text-3xl font-bold text-gray-900">Portal Médico</h2>
                <p class="mt-2 text-sm text-gray-600">Acceso para personal autorizado</p>
            </div>

            <!-- Formulario de Login -->
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <form id="loginForm" onsubmit="handleLoginSubmit(event)" class="space-y-6">
                    <div>
                        <label for="loginEmail" class="block text-sm font-medium text-gray-700">Email</label>
                        <div class="mt-1">
                            <input 
                                id="loginEmail" 
                                type="email" 
                                required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="tu.email@hospital.com"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="loginPassword" class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <div class="mt-1">
                            <input 
                                id="loginPassword" 
                                type="password" 
                                required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Tu contraseña"
                            >
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input 
                                id="rememberMe" 
                                type="checkbox" 
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            >
                            <label for="rememberMe" class="ml-2 block text-sm text-gray-900">Recordarme</label>
                        </div>
                        <div class="text-sm">
                            <a href="#" onclick="showForgotPassword()" class="font-medium text-blue-600 hover:text-blue-500">
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div>
                    </div>

                    <div>
                        <button 
                            type="submit" 
                            id="submitBtn"
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                        >
                            <span id="submitText">Iniciar Sesión</span>
                        </button>
                    </div>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        ¿Eres paciente? 
                        <a href="/preauth.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Usa tu enlace personalizado
                        </a>
                    </p>
                </div>

                <!-- Estado de conexión -->
                <div class="mt-4 text-center">
                    <div id="connectionStatus" class="inline-flex items-center text-sm text-gray-500">
                        <div id="statusDot" class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                        <span id="statusText">Verificando conexión...</span>
                    </div>
                </div>

                <!-- Datos de prueba (solo desarrollo) -->
                <?php if (APP_ENV === 'development'): ?>
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                    <h4 class="text-sm font-medium text-yellow-800">Datos de prueba:</h4>
                    <p class="text-xs text-yellow-700 mt-1">
                        Email: <code>admin@tpsalud.com</code><br>
                        Password: <code>Admin123</code>
                    </p>
                    <button onclick="fillTestCredentials()" class="mt-2 text-xs bg-yellow-200 px-2 py-1 rounded">
                        Llenar automáticamente
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/auth-client.js"></script>
    
    <script>
        // Inicializar cliente de auth
        window.authClient = new AuthClient();
        
        // Verificar estado inicial
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🏥 Portal médico iniciado');
            
            // Si ya está autenticado, redirigir
            if (window.authClient.isAuthenticated() && window.authClient.isStaff()) {
                console.log('🔄 Usuario ya autenticado, redirigiendo...');
                window.location.href = '/staff.php';
                return;
            }
            
            checkServerHealth();
        });
        
        // Verificar estado del servidor
        async function checkServerHealth() {
            try {
                const response = await fetch('http://187.33.158.246:8080/auth/../health', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    updateStatus('Servidor conectado', 'success');
                    console.log('✅ Servidor OK:', data);
                } else {
                    updateStatus('Problemas con servidor', 'warning');
                }
            } catch (error) {
                console.error('❌ Error servidor:', error);
                updateStatus('Sin conexión', 'error');
            }
        }
        
        function updateStatus(text, type) {
            const statusText = document.getElementById('statusText');
            const statusDot = document.getElementById('statusDot');
            
            statusText.textContent = text;
            
            statusDot.className = 'w-2 h-2 rounded-full mr-2 ';
            switch(type) {
                case 'success':
                    statusDot.className += 'bg-green-400';
                    break;
                case 'warning':
                    statusDot.className += 'bg-yellow-400';
                    break;
                case 'error':
                    statusDot.className += 'bg-red-400';
                    break;
                default:
                    statusDot.className += 'bg-gray-400';
            }
        }
        
        function showForgotPassword() {
            window.authClient.showNotification('Contacta al administrador: admin@hospital.com', 'info', 7000);
        }
        
        <?php if (APP_ENV === 'development'): ?>
        function fillTestCredentials() {
            document.getElementById('loginEmail').value = 'admin@tpsalud.com';
            document.getElementById('loginPassword').value = 'Admin123';
        }
        <?php endif; ?>
        
        // Helper global para debug
        window.debugAuth = {
            testConnection: () => checkServerHealth(),
            getInfo: () => window.authClient.getChatStats?.() || 'No disponible',
            clearAuth: () => {
                localStorage.clear();
                location.reload();
            }
        };
    </script>
</body>
</html>