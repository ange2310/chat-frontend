<?php
// public/index.php - Portal exclusivo para Staff (Agentes, Supervisores, Admins)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = auth();
$isAuthenticated = $auth->isAuthenticated();
$user = $isAuthenticated ? $auth->getUser() : null;

// Redireccionar al personal a su interfaz correspondiente
if ($isAuthenticated && $auth->isStaff()) {
    header("Location: /staff.php");
    exit;
}

// Verificar si hay un error en la URL
$error = $_GET['error'] ?? null;
$errorMessages = [
    'invalid_token' => 'Token de acceso inválido',
    'access_denied' => 'Acceso denegado',
    'session_expired' => 'Sesión expirada'
];
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Personal - Sistema Médico</title>
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
        
        .medical-gradient {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #0369a1 100%);
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="h-full medical-gradient">
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                    </svg>
                </div>
            </div>
            <h2 class="mt-6 text-center text-3xl font-bold text-white">
                Portal de Personal Médico
            </h2>
            <p class="mt-2 text-center text-lg text-blue-100">
                Acceso exclusivo para staff autorizado
            </p>
        </div>

        <!-- Login Form -->
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md animate-slide-up">
            <div class="glass-effect py-8 px-6 shadow-2xl sm:rounded-2xl sm:px-10">
                
                <!-- Error Message -->
                <?php if ($error && isset($errorMessages[$error])): ?>
                <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-800"><?= htmlspecialchars($errorMessages[$error]) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form id="loginForm" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Correo Electrónico
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                </svg>
                            </div>
                            <input 
                                id="email" 
                                name="email" 
                                type="email" 
                                autocomplete="email" 
                                required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500 focus:border-transparent text-gray-900 placeholder-gray-500"
                                placeholder="tu.email@hospital.com"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Contraseña
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <input 
                                id="password" 
                                name="password" 
                                type="password" 
                                autocomplete="current-password" 
                                required
                                class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-medical-500 focus:border-transparent text-gray-900 placeholder-gray-500"
                                placeholder="Tu contraseña"
                            >
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <svg id="eyeIcon" class="h-5 w-5 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input 
                                id="remember_me" 
                                name="remember_me" 
                                type="checkbox" 
                                class="h-4 w-4 text-medical-600 focus:ring-medical-500 border-gray-300 rounded"
                            >
                            <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                                Recordar sesión
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="#" onclick="showForgotPassword()" class="font-medium text-medical-600 hover:text-medical-500 transition-colors">
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div>
                    </div>

                    <div>
                        <button 
                            type="submit" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-medical-600 hover:bg-medical-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-medical-500 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            id="loginButton"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            <span id="loginButtonText">Iniciar Sesión</span>
                        </button>
                    </div>
                </form>

                <!-- Info Section -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-4">
                            <svg class="w-4 h-4 inline mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Portal exclusivo para personal médico autorizado
                        </p>
                        
                        <div class="text-xs text-gray-500">
                            <p>¿Eres paciente? <a href="/preauth.php" class="text-medical-600 hover:text-medical-500 font-medium">Accede con tu enlace personalizado</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features for Staff -->
        <div class="mt-12 sm:mx-auto sm:w-full sm:max-w-2xl">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 text-white">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold">Gestión de Chats</h3>
                    <p class="text-xs text-blue-100 mt-1">Maneja consultas en tiempo real</p>
                </div>
                
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 text-white">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold">Reportes</h3>
                    <p class="text-xs text-blue-100 mt-1">Estadísticas y métricas</p>
                </div>
                
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 text-white">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold">Supervisión</h3>
                    <p class="text-xs text-blue-100 mt-1">Control de calidad</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 shadow-2xl text-center max-w-sm">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-medical-600 mx-auto mb-4"></div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Verificando credenciales...</h3>
            <p class="text-gray-600 text-sm">Accediendo al sistema</p>
        </div>
    </div>

    <!-- Include Auth Client -->
    <script src="assets/js/auth-client.js"></script>
    
    <script>
        // Configuración
        const CONFIG = {
            AUTH_SERVICE_URL: 'http://187.33.158.246:8080/auth'
        };

        // Inicializar cliente de autenticación
        window.authClient = new AuthClient(CONFIG.AUTH_SERVICE_URL);

        // Event listeners
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🚀 Portal de staff iniciado');
            
            // Setup form handler
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', handleLogin);
            }

            // Check for existing auth
            if (window.authClient.isAuthenticated()) {
                console.log('Usuario ya autenticado, redirigiendo...');
                window.location.href = '/staff.php';
            }
        });

        // Handle login form submission
        async function handleLogin(event) {
            event.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const remember = document.getElementById('remember_me').checked;
            
            // Validación básica
            if (!email || !password) {
                showNotification('Por favor completa todos los campos', 'error');
                return;
            }
            
            if (!isValidEmail(email)) {
                showNotification('Formato de email inválido', 'error');
                return;
            }
            
            // Mostrar loading
            setLoginLoading(true);
            
            try {
                const result = await window.authClient.login(email, password, remember);
                
                if (result.success) {
                    showNotification('¡Bienvenido! Redirigiendo...', 'success');
                    
                    // Redireccionar según el rol
                    setTimeout(() => {
                        window.location.href = '/staff.php';
                    }, 1500);
                    
                } else {
                    showNotification(result.error || 'Credenciales inválidas', 'error');
                }
                
            } catch (error) {
                console.error('Error en login:', error);
                showNotification('Error de conexión. Verifica tu conexión e inténtalo de nuevo.', 'error');
            } finally {
                setLoginLoading(false);
            }
        }

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                `;
            }
        }

        // Show forgot password modal (placeholder)
        function showForgotPassword() {
            alert('Contacta al administrador del sistema para recuperar tu contraseña.\n\nEmail: admin@hospital.com\nTeléfono: +57 (1) 234-5678');
        }

        // Loading state for login button
        function setLoginLoading(loading) {
            const button = document.getElementById('loginButton');
            const buttonText = document.getElementById('loginButtonText');
            const spinner = document.getElementById('loadingSpinner');
            
            if (loading) {
                button.disabled = true;
                buttonText.textContent = 'Verificando...';
                button.innerHTML = `
                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                    <span>Verificando credenciales...</span>
                `;
                spinner.classList.remove('hidden');
            } else {
                button.disabled = false;
                button.innerHTML = `
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Iniciar Sesión</span>
                `;
                spinner.classList.add('hidden');
            }
        }

        // Notification system
        function showNotification(message, type = 'info', duration = 5000) {
            const notification = document.createElement('div');
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm text-white ${colors[type]} animate-fade-in`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            ${getNotificationIcon(type)}
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
            }, duration);
        }

        function getNotificationIcon(type) {
            const icons = {
                success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>',
                error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>',
                warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>',
                info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
            };
            return icons[type] || icons.info;
        }

        // Email validation
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Enter to submit form
            if (e.key === 'Enter' && (e.target.id === 'email' || e.target.id === 'password')) {
                document.getElementById('loginForm').dispatchEvent(new Event('submit'));
            }
        });

        console.log('🏥 Portal de staff listo - v2.0');
    </script>
</body>
</html>