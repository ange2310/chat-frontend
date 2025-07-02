<?php
// public/index.php - CORREGIDO SIN BUCLES
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = auth();

// FORZAR LOGOUT si se pide
if (isset($_GET['logout']) || isset($_GET['force_logout'])) {
    $auth->logout();
    session_destroy();
    session_unset();
    
    // Limpiar localStorage también
    echo "<script>localStorage.clear(); sessionStorage.clear();</script>";
    
    header("Location: /practicas/chat-frontend/public/index.php");
    exit;
}

// Si ya está autenticado en PHP, ir directo a staff
if ($auth->isAuthenticated() && $auth->isStaff()) {
    debugLog("Usuario ya autenticado en PHP, redirigiendo a staff");
    header("Location: /practicas/chat-frontend/public/staff.php");
    exit;
}

// Verificar si hay datos POST de sincronización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_token'])) {
    $token = $_POST['sync_token'];
    $userData = json_decode($_POST['sync_user'] ?? '{}', true);
    
    if ($token && $userData) {
        debugLog("Intentando sincronización directa en index.php");
        
        // Validar token directamente aquí
        if (validateTokenWithService($token)) {
            debugLog("Token válido, guardando en sesión");
            
            // Guardar en sesión PHP
            $_SESSION['pToken'] = $token;
            $_SESSION['user'] = json_encode($userData);
            
            // Redirigir inmediatamente a staff
            header("Location: /practicas/chat-frontend/public/staff.php");
            exit;
        } else {
            debugLog("Token inválido en sincronización", null, 'ERROR');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Médico - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    
    <!-- Pantalla de Login -->
    <div id="loginScreen" class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto h-12 w-12 bg-white rounded-xl flex items-center justify-center mb-4 shadow-lg">
                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-white">Portal Médico</h2>
                <p class="text-blue-100 mt-2">Acceso al sistema</p>
            </div>

            <!-- Login Form -->
            <div class="bg-white rounded-xl shadow-xl p-8">
                <form id="loginForm" class="space-y-6">
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input 
                            id="email" 
                            type="email" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="tu@email.com"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                        <div class="relative">
                            <input 
                                id="password" 
                                type="password" 
                                required
                                class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Tu contraseña"
                            >
                            <button 
                                type="button" 
                                id="togglePassword" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                                onclick="togglePasswordVisibility()"
                            >
                                <svg id="eyeOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="eyeClosed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button 
                        type="submit" 
                        id="submitBtn"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 font-medium transition-colors disabled:opacity-50"
                    >
                        <span id="normalText">Iniciar Sesión</span>
                        <span id="loadingText" class="hidden">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Iniciando...
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Form oculto para sincronización -->
    <form id="syncForm" method="POST" style="display: none;">
        <input type="hidden" id="syncToken" name="sync_token">
        <input type="hidden" id="syncUser" name="sync_user">
    </form>

    <script>
        class SimpleAuth {
            constructor() {
                this.baseURL = 'http://187.33.158.246:8080/auth';
            }

            async login(email, password) {
                try {
                    const response = await fetch(`${this.baseURL}/login`, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ email, password })
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        return { success: true, data: result.data };
                    } else {
                        return { success: false, error: result.message || 'Error en login' };
                    }
                } catch (error) {
                    return { success: false, error: 'Error de conexión' };
                }
            }
        }

        const auth = new SimpleAuth();

        // Función para mostrar/ocultar contraseña
        window.togglePasswordVisibility = function() {
            const passwordField = document.getElementById('password');
            const eyeOpen = document.getElementById('eyeOpen');
            const eyeClosed = document.getElementById('eyeClosed');
            
            if (passwordField.type === 'password') {
                // Mostrar contraseña
                passwordField.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                // Ocultar contraseña
                passwordField.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        };

        // VERIFICACIÓN INICIAL: localStorage con sincronización directa
        window.addEventListener('DOMContentLoaded', () => {
            console.log('🔍 Verificando sesión...');
            
            const token = localStorage.getItem('pToken');
            const user = localStorage.getItem('user');
            
            if (token && user) {
                console.log('✅ Sesión encontrada en localStorage, sincronizando...');
                
                // Sincronizar DIRECTAMENTE con PHP via POST
                document.getElementById('syncToken').value = token;
                document.getElementById('syncUser').value = user;
                document.getElementById('syncForm').submit();
                
                return;
            }
            
            console.log('❌ No hay sesión, mostrando login');
        });

        // Form handler
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                alert('Complete todos los campos');
                return;
            }
            
            // Show loading
            const submitBtn = document.getElementById('submitBtn');
            const normalText = document.getElementById('normalText');
            const loadingText = document.getElementById('loadingText');
            
            submitBtn.disabled = true;
            normalText.classList.add('hidden');
            loadingText.classList.remove('hidden');
            
            try {
                const result = await auth.login(email, password);
                
                if (result.success) {
                    console.log('✅ Login exitoso');
                    
                    // Guardar en localStorage
                    localStorage.setItem('pToken', result.data.access_token);
                    localStorage.setItem('user', JSON.stringify(result.data.user));
                    
                    // Sincronizar inmediatamente con PHP
                    document.getElementById('syncToken').value = result.data.access_token;
                    document.getElementById('syncUser').value = JSON.stringify(result.data.user);
                    document.getElementById('syncForm').submit();
                    
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Error de conexión');
            } finally {
                submitBtn.disabled = false;
                normalText.classList.remove('hidden');
                loadingText.classList.add('hidden');
            }
        });
    </script>
</body>
</html>