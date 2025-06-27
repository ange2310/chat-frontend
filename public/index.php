<?php
// public/index.php - Portal de staff simplificado
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = auth();
$isAuthenticated = $auth->isAuthenticated();

// Redireccionar si ya está autenticado
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
    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }
        .notification.show {
            transform: translateX(0);
        }
        .notification.success {
            background-color: #10b981;
            color: white;
        }
        .notification.error {
            background-color: #ef4444;
            color: white;
        }
        .notification.info {
            background-color: #3b82f6;
            color: white;
        }
    </style>
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

            <!-- Formulario -->
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <form id="loginForm" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <div class="mt-1">
                            <input 
                                id="email" 
                                type="email" 
                                required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="tu.email@hospital.com"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <div class="mt-1">
                            <input 
                                id="password" 
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
                                id="remember" 
                                type="checkbox" 
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            >
                            <label for="remember" class="ml-2 block text-sm text-gray-900">Recordarme</label>
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
            </div>
        </div>
    </div>

    <script>
        // Configuración
        const CONFIG = {
            AUTH_SERVICE_URL: 'http://187.33.158.246:8080/auth',
            USE_PROXY: true
        };

        // Sistema de notificaciones
        function showNotification(message, type = 'info', duration = 5000) {
            // Remover notificaciones existentes
            const existing = document.querySelectorAll('.notification');
            existing.forEach(n => n.remove());

            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
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
            
            // Mostrar
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Auto-ocultar
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }

        // Cliente de auth simplificado
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

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();
                
                if (result.success || result.data) {
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
            console.log('🏥 Portal médico iniciado');
            
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
        }

        async function handleLogin(event) {
            event.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const remember = document.getElementById('remember').checked;
            
            // Validación
            if (!email || !password) {
                showNotification('Por favor completa todos los campos', 'error');
                return;
            }
            
            if (!email.includes('@')) {
                showNotification('Formato de email inválido', 'error');
                return;
            }
            
            setLoading(true);
            
            try {
                const result = await auth.login(email, password, remember);
                
                if (result.success) {
                    showNotification('¡Bienvenido! Redirigiendo...', 'success');
                    setTimeout(() => {
                        window.location.href = '/staff.php';
                    }, 1500);
                } else {
                    showNotification(result.error, 'error');
                }
                
            } catch (error) {
                console.error('Error en login:', error);
                showNotification('Error de conexión. Verifica tu conexión.', 'error');
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
            
            btn.disabled = loading;
            text.textContent = loading ? 'Verificando...' : 'Iniciar Sesión';
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
            showNotification('Contacta al administrador del sistema para recuperar tu contraseña: admin@hospital.com', 'info', 7000);
        }

        // Helper para debug
        window.debugAuth = {
            testCredentials: () => {
                document.getElementById('email').value = 'admin@tpsalud.com';
                document.getElementById('password').value = 'Admin123';
            },
            clearAuth: () => {
                localStorage.clear();
                location.reload();
            }
        };
    </script>
</body>
</html>