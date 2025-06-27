<?php
// public/index.php - Portal simplificado para Staff (Principio KISS)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = auth();
$isAuthenticated = $auth->isAuthenticated();

// Redireccionar si ya está autenticado
if ($isAuthenticated && $auth->isStaff()) {
    header("Location: /staff.php");
    exit;
}

// Manejar errores de la URL
$error = $_GET['error'] ?? null;
$errorMessages = [
    'invalid_token' => 'Token de acceso inválido',
    'access_denied' => 'Acceso denegado',
    'session_expired' => 'Sesión expirada',
    'cors_error' => 'Error de conexión con el servidor'
];
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Médico - Acceso Personal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .medical-gradient {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body class="h-full medical-gradient">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            
            <!-- Header Minimalista -->
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-white">Portal Médico</h2>
                <p class="mt-2 text-blue-100">Acceso para personal autorizado</p>
            </div>

            <!-- Formulario Limpio -->
            <div class="glass-card rounded-2xl shadow-2xl p-8">
                
                <!-- Mensaje de Error -->
                <?php if ($error && isset($errorMessages[$error])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-red-800"><?= htmlspecialchars($errorMessages[$error]) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Error dinámico -->
                <div id="errorMessage" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-red-800" id="errorText"></p>
                        </div>
                    </div>
                </div>

                <form id="loginForm" class="space-y-6">
                    <!-- Email Simple -->
                    <div>
                        <label for="email" class="sr-only">Email</label>
                        <input 
                            id="email" 
                            type="email" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 placeholder-gray-500 transition-colors"
                            placeholder="tu.email@hospital.com"
                        >
                    </div>

                    <!-- Password Simple -->
                    <div>
                        <label for="password" class="sr-only">Contraseña</label>
                        <input 
                            id="password" 
                            type="password" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 placeholder-gray-500 transition-colors"
                            placeholder="Tu contraseña"
                        >
                    </div>

                    <!-- Opciones -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input 
                                id="remember" 
                                type="checkbox" 
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            >
                            <label for="remember" class="ml-2 text-sm text-gray-700">Recordarme</label>
                        </div>
                        <div class="text-sm">
                            <a href="#" onclick="showForgotPassword()" class="font-medium text-blue-600 hover:text-blue-500">
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div>
                    </div>

                    <!-- Botón Limpio -->
                    <div>
                        <button 
                            type="submit" 
                            id="submitBtn"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all font-medium disabled:opacity-50"
                        >
                            <span id="submitText">Iniciar Sesión</span>
                        </button>
                    </div>
                </form>

                <!-- Footer Minimalista -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        ¿Eres paciente? 
                        <a href="/preauth.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Usa tu enlace personalizado
                        </a>
                    </p>
                </div>
            </div>

            <!-- Indicador de Estado -->
            <div class="text-center">
                <div id="statusIndicator" class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-white/10 text-white">
                    <div id="statusDot" class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                    <span id="statusText">Verificando conexión...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay Simple -->
    <div id="loadingOverlay" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 shadow-xl text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-700">Verificando credenciales...</p>
        </div>
    </div>

    <script>
        // Configuración simple
        const CONFIG = {
            AUTH_SERVICE_URL: 'http://187.33.158.246:8080/auth',
            USE_PROXY: true, // Usar proxy PHP para evitar CORS
            DEBUG: true
        };

        // Cliente de auth super simplificado
        class SimpleAuth {
            constructor() {
                this.token = localStorage.getItem('pToken');
                this.user = this.getUser();
            }

            async login(email, password, remember = false) {
                const url = CONFIG.USE_PROXY ? '/api/proxy.php' : CONFIG.AUTH_SERVICE_URL + '/login';
                
                const body = CONFIG.USE_PROXY ? {
                    endpoint: '/login',
                    method: 'POST',
                    data: { email, password, remember }
                } : { email, password, remember };

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });

                const result = await response.json();
                
                if (response.ok && (result.success || result.data)) {
                    const userData = result.data || result;
                    this.setAuth(userData.access_token, userData.user);
                    return { success: true, user: userData.user };
                } else {
                    return { 
                        success: false, 
                        error: result.message || result.error || 'Credenciales inválidas' 
                    };
                }
            }

            setAuth(token, user) {
                this.token = token;
                this.user = user;
                localStorage.setItem('pToken', token);
                localStorage.setItem('user', JSON.stringify(user));
            }

            getUser() {
                try {
                    const userData = localStorage.getItem('user');
                    return userData ? JSON.parse(userData) : null;
                } catch (e) {
                    return null;
                }
            }

            isAuthenticated() {
                return !!(this.token && this.user);
            }
        }

        const auth = new SimpleAuth();

        // Inicialización
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🏥 Portal médico iniciado (v2.0 - KISS)');
            
            if (auth.isAuthenticated()) {
                window.location.href = '/staff.php';
                return;
            }

            setupForm();
            checkHealth();
        });

        function setupForm() {
            const form = document.getElementById('loginForm');
            form.addEventListener('submit', handleLogin);

            // Enter para enviar
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit'));
                }
            });
        }

        async function handleLogin(event) {
            event.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const remember = document.getElementById('remember').checked;
            
            // Validación simple
            if (!email || !password) {
                showError('Por favor completa todos los campos');
                shakeForm();
                return;
            }
            
            if (!email.includes('@') || !email.includes('.')) {
                showError('Formato de email inválido');
                shakeForm();
                return;
            }
            
            setLoading(true);
            hideError();
            
            try {
                const result = await auth.login(email, password, remember);
                
                if (result.success) {
                    showSuccess('¡Bienvenido! Redirigiendo...');
                    setTimeout(() => {
                        window.location.href = '/staff.php';
                    }, 1500);
                } else {
                    showError(result.error);
                    shakeForm();
                }
                
            } catch (error) {
                console.error('Error en login:', error);
                showError('Error de conexión. Verifica tu conexión.');
                shakeForm();
            } finally {
                setLoading(false);
            }
        }

        async function checkHealth() {
            try {
                const response = await fetch('/api/health.php');
                const data = await response.json();
                
                if (data.status === 'ok') {
                    updateStatus('Sistema conectado', 'success');
                } else if (data.status === 'degraded') {
                    updateStatus('Sistema con problemas', 'warning');
                } else {
                    updateStatus('Sistema no disponible', 'error');
                }
            } catch (error) {
                updateStatus('Conexión limitada', 'warning');
            }
        }

        function setLoading(loading) {
            const btn = document.getElementById('submitBtn');
            const text = document.getElementById('submitText');
            const overlay = document.getElementById('loadingOverlay');
            
            btn.disabled = loading;
            text.textContent = loading ? 'Verificando...' : 'Iniciar Sesión';
            overlay.classList.toggle('hidden', !loading);
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            
            errorText.textContent = message;
            errorDiv.classList.remove('hidden');
            
            setTimeout(hideError, 5000);
        }

        function hideError() {
            document.getElementById('errorMessage').classList.add('hidden');
        }

        function showSuccess(message) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 z-50 p-4 bg-green-500 text-white rounded-lg shadow-lg';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 3000);
        }

        function shakeForm() {
            const form = document.querySelector('.glass-card');
            form.classList.add('shake');
            setTimeout(() => form.classList.remove('shake'), 500);
        }

        function updateStatus(text, type) {
            const statusText = document.getElementById('statusText');
            const statusDot = document.getElementById('statusDot');
            
            statusText.textContent = text;
            
            // Actualizar color del indicador
            statusDot.className = 'w-2 h-2 rounded-full mr-2 ';
            switch(type) {
                case 'success':
                    statusDot.className += 'bg-green-400 animate-pulse';
                    break;
                case 'warning':
                    statusDot.className += 'bg-yellow-400 animate-pulse';
                    break;
                case 'error':
                    statusDot.className += 'bg-red-400 animate-pulse';
                    break;
                default:
                    statusDot.className += 'bg-gray-400';
            }
        }

        function showForgotPassword() {
            alert('Contacta al administrador del sistema para recuperar tu contraseña:\n\nEmail: admin@hospital.com\nTeléfono: +57 (1) 234-5678');
        }

        // Debug helpers
        if (CONFIG.DEBUG) {
            window.debugAuth = {
                testCredentials: () => {
                    document.getElementById('email').value = 'admin@tpsalud.com';
                    document.getElementById('password').value = 'Admin123';
                },
                checkHealth: checkHealth,
                clearAuth: () => {
                    localStorage.clear();
                    location.reload();
                }
            };
            console.log('🔧 Debug helpers disponibles en window.debugAuth');
        }
    </script>
</body>
</html>