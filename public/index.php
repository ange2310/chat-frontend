<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

// Prevenir múltiples redirecciones
$redirected = $_GET['redirected'] ?? false;

$auth = auth();


if (isset($_GET['logout']) || isset($_GET['force_logout'])) {
    $auth->logout();
    session_destroy();
    session_unset();
    header("Location: /practicas/chat-frontend/public/index.php");
    exit;
}

// Solo verificar autenticación si NO venimos de una redirección
if (!$redirected && $auth->isAuthenticated() && $auth->isStaff()) {
    debugLog("Usuario ya autenticado, redirigiendo según rol");
    
    $user = $auth->getUser();
    $userRole = $user['role']['name'] ?? $user['role'] ?? 'agent';
    
    // Normalizar rol numérico a string
    if (is_numeric($userRole)) {
        $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
        $userRole = $roleMap[$userRole] ?? 'agent';
    }
    
    // Redirigir según rol con flag para prevenir bucles
    if ($userRole === 'supervisor' || $userRole === 'admin') {
        header("Location: /practicas/chat-frontend/public/supervisor.php?from_login=1");
    } else {
        header("Location: /practicas/chat-frontend/public/staff.php?from_login=1");
    }
    exit;
}

// Manejar sincronización POST SOLO si no es redirección
if (!$redirected && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_token'])) {
    $token = $_POST['sync_token'];
    $userData = json_decode($_POST['sync_user'] ?? '{}', true);
    
    if ($token && $userData && validateTokenWithService($token)) {
        // Guardar en sesión
        $_SESSION['pToken'] = $token;
        $_SESSION['user'] = json_encode($userData);
        
        // Determinar redirección
        $userRole = $userData['role']['name'] ?? $userData['role'] ?? 'agent';
        if (is_numeric($userRole)) {
            $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
            $userRole = $roleMap[$userRole] ?? 'agent';
        }
        
        if ($userRole === 'supervisor' || $userRole === 'admin') {
            header("Location: /practicas/chat-frontend/public/supervisor.php?from_sync=1");
        } else {
            header("Location: /practicas/chat-frontend/public/staff.php?from_sync=1");
        }
        exit;
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

            <!-- Mostrar errores si existen -->
            <?php if(isset($_GET['error'])): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                <?php
                $errors = [
                    'no_session' => 'Sesión expirada',
                    'no_user' => 'Usuario no válido', 
                    'not_staff' => 'Acceso no autorizado',
                    'not_supervisor' => 'Requiere permisos de supervisor'
                ];
                echo $errors[$_GET['error']] ?? 'Error desconocido';
                ?>
            </div>
            <?php endif; ?>

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
                // CORREGIDO: Usar el proxy local en lugar del servidor directo
                this.baseURL = 'api.php';
            }

            async login(email, password) {
                try {
                    // CORREGIDO: Usar el proxy con parámetro endpoint
                    const response = await fetch(`${this.baseURL}?endpoint=auth/login`, {
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
                passwordField.type = 'text';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            } else {
                passwordField.type = 'password';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            }
        };

        // VERIFICACIÓN INICIAL SIMPLIFICADA
        window.addEventListener('DOMContentLoaded', () => {
            console.log('🔍 Verificando sesión...');
            
            // Solo verificar localStorage si no venimos de redirección
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('redirected')) {
                console.log('⏭️ Venimos de redirección, saltando verificación');
                return;
            }
            
            const token = localStorage.getItem('pToken');
            const user = localStorage.getItem('user');
            
            if (token && user) {
                console.log('Sesión encontrada, sincronizando...');
                
                // Sincronizar con PHP
                document.getElementById('syncToken').value = token;
                document.getElementById('syncUser').value = user;
                document.getElementById('syncForm').submit();
                
                return;
            }
            
            console.log('No hay sesión');
        });

        // Form handler SIMPLIFICADO
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
                    
                    // Sincronizar con PHP
                    document.getElementById('syncToken').value = result.data.access_token;
                    document.getElementById('syncUser').value = JSON.stringify(result.data.user);
                    document.getElementById('syncForm').submit();
                    
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Error:', error);
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