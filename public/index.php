<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

if (!defined('AUTH_BASE_URL')) {
    define('AUTH_BASE_URL', 'http://localhost:3010/auth');
}

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

// Manejar sincronización POST - CORREGIDO SIN VALIDACIÓN EXTRA
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['sync_token']) &&
    isset($_POST['sync_user'])
) {
    $token    = $_POST['sync_token'];
    $userData = json_decode($_POST['sync_user'] ?? '{}', true);

    if ($token && $userData && is_array($userData)) {
        // Guardar datos en la sesión (token ya validado en frontend)
        $_SESSION['pToken']       = $token;
        $_SESSION['user']         = json_encode($userData);
        $_SESSION['is_logged_in'] = true;

        // Obtener rol
        $rawRole = $userData['role']['name'] ?? $userData['role'] ?? 'agent';

        if (is_numeric($rawRole)) {
            $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
            $rawRole = $roleMap[$rawRole] ?? 'agent';
        }

        $_SESSION['role'] = $rawRole;

        // Redirigir según rol SIN VALIDACIÓN EXTRA
        if (in_array($rawRole, ['supervisor', 'admin'])) {
            header("Location: /practicas/chat-frontend/public/supervisor.php");
        } else {
            header("Location: /practicas/chat-frontend/public/staff.php");
        }
        exit;
    }
}

// Solo verificar autenticación si NO venimos de redirección
if (!$redirected && $auth->isAuthenticated() && $auth->isStaff()) {
    $user = $auth->getUser();
    $userRole = $user['role']['name'] ?? $user['role'] ?? 'agent';

    if (is_numeric($userRole)) {
        $roleMap  = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
        $userRole = $roleMap[$userRole] ?? 'agent';
    }

    if ($userRole === 'supervisor' || $userRole === 'admin') {
        header("Location: /practicas/chat-frontend/public/supervisor.php");
    } else {
        header("Location: /practicas/chat-frontend/public/staff.php");
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Médico - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="/practicas/chat-frontend/public/assets/js/auth-client.js"></script>
    <script>
    const AUTH_BASE_URL = 'http://localhost:3010/auth';
    window.authClient   = new AuthClient(AUTH_BASE_URL);
    </script>

</head>
<body class="bg-gray-900">
    <div id="loginScreen" class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <div class="mx-auto h-12 w-12 bg-white rounded-xl flex items-center justify-center mb-4 shadow-lg">
                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-white">Portal Médico</h2>
                <p class="text-blue-100 mt-2">Acceso al sistema</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                <?php
                $errors = [
                    'no_session'     => 'Sesión expirada',
                    'no_user'        => 'Usuario no válido', 
                    'not_staff'      => 'Acceso no autorizado',
                    'not_supervisor' => 'Requiere permisos de supervisor'
                ];
                echo $errors[$_GET['error']] ?? 'Error desconocido';
                ?>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-xl p-8">
                <form id="loginForm" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input id="email" type="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="tu@email.com" />
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                        <div class="relative">
                            <input id="password" type="password" required class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Tu contraseña" />
                            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" onclick="togglePasswordVisibility()">
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

                    <button type="submit" id="submitBtn" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 font-medium transition-colors disabled:opacity-50">
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

    <form id="syncForm" method="POST" style="display: none;">
        <input type="hidden" id="syncToken" name="sync_token">
        <input type="hidden" id="syncUser"  name="sync_user">
    </form>

    <script>
        const auth = window.authClient;

        window.togglePasswordVisibility = function () {
            const pwd   = document.getElementById('password');
            const open  = document.getElementById('eyeOpen');
            const close = document.getElementById('eyeClosed');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                open.classList.add('hidden');
                close.classList.remove('hidden');
            } else {
                pwd.type = 'password';
                open.classList.remove('hidden');
                close.classList.add('hidden');
            }
        };

        window.addEventListener('DOMContentLoaded', async () => {
            const params = new URLSearchParams(window.location.search);
            if (params.get('redirected')) return;
            if (sessionStorage.getItem('staffSynced') === '1') return;
            
            const token = sessionStorage.getItem('staffJWT') || localStorage.getItem('pToken');
            const user  = localStorage.getItem('user');
            if (!token || !user) return;

            if (!(await auth.verifyToken(token))) {
                localStorage.removeItem('pToken');
                localStorage.removeItem('user');
                sessionStorage.removeItem('staffJWT');
                return;
            }
            
            sessionStorage.setItem('staffSynced', '1');
            document.getElementById('syncToken').value = token;
            document.getElementById('syncUser').value  = user;
            document.getElementById('syncForm').submit();
        });

        document.getElementById('loginForm').addEventListener('submit', async e => {
            e.preventDefault();

            const email    = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            if (!email || !password) {
                alert('Complete todos los campos');
                return;
            }

            const btn   = document.getElementById('submitBtn');
            const txt   = document.getElementById('normalText');
            const load  = document.getElementById('loadingText');
            btn.disabled = true;
            txt.classList.add('hidden');
            load.classList.remove('hidden');

            const result = await auth.login(email, password);
            if (result.success) {
                const accessToken = result.data.access_token; 
                sessionStorage.setItem('staffJWT', accessToken);
                localStorage.setItem('user', JSON.stringify(result.data.user));

                document.getElementById('syncToken').value = accessToken;
                document.getElementById('syncUser').value  = JSON.stringify(result.data.user);
                document.getElementById('syncForm').submit();
            } else {
                alert('Error: ' + result.error);
                btn.disabled = false;
                txt.classList.remove('hidden');
                load.classList.add('hidden');
            }
        });
    </script>
</body>
</html>