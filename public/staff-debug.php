<?php
// public/staff-debug.php - Versión con debugging paso a paso
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

protectStaffPage();

$auth = auth();
$user = $auth->getUser();
$userRole = $user['role']['name'] ?? $user['role'] ?? 'agent';

if (is_numeric($userRole)) {
    $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
    $userRole = $roleMap[$userRole] ?? 'agent';
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Médico - DEBUG</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .debug-log {
            position: fixed;
            top: 10px;
            right: 10px;
            width: 300px;
            max-height: 500px;
            background: #000;
            color: #00ff00;
            font-family: monospace;
            font-size: 12px;
            padding: 10px;
            border-radius: 5px;
            overflow-y: auto;
            z-index: 9999;
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="h-full bg-gray-50">
    
    <!-- Debug Log Flotante -->
    <div id="debugLog" class="debug-log">🔍 DEBUGGING INICIADO...\n</div>

    <div class="min-h-full flex">
        <!-- Sidebar Mínimo -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <div class="p-6 border-b border-gray-200">
                <h1 class="font-semibold text-gray-900">Panel Médico DEBUG</h1>
                <p class="text-sm text-gray-500">Rastreando problemas...</p>
            </div>
            
            <div class="p-4">
                <div class="space-y-2">
                    <div class="text-sm">
                        <strong>Usuario:</strong> <?= htmlspecialchars($user['name'] ?? 'Unknown') ?>
                    </div>
                    <div class="text-sm">
                        <strong>Rol:</strong> <?= htmlspecialchars($userRole) ?>
                    </div>
                </div>
            </div>
                
            <div class="p-4 border-t border-gray-200 mt-auto">
                <button onclick="logout()" class="w-full bg-red-600 text-white py-2 px-4 rounded hover:bg-red-700">
                    Logout
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <header class="bg-white border-b border-gray-200">
                <div class="px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-900">Dashboard DEBUG</h2>
                </div>
            </header>

            <main class="flex-1 p-6 overflow-auto">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">🧪 Testing Phase</h3>
                    <p class="text-gray-600 mb-4">Esta página cargará los scripts paso a paso para identificar cuál causa la redirección.</p>
                    
                    <div class="space-y-4">
                        <button onclick="loadAuthClient()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            1. Cargar AuthClient
                        </button>
                        <button onclick="loadChatClient()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                            2. Cargar ChatClient
                        </button>
                        <button onclick="loadMainScript()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                            3. Cargar Script Principal
                        </button>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        let debugStep = 0;
        
        function debugLog(message, type = 'info') {
            const colors = {
                error: '#ff0000',
                warning: '#ffaa00',
                success: '#00ff00',
                info: '#00aaff'
            };
            
            const log = document.getElementById('debugLog');
            const timestamp = new Date().toLocaleTimeString();
            log.innerHTML += `<span style="color: ${colors[type] || colors.info}">[${timestamp}] ${message}</span>\n`;
            log.scrollTop = log.scrollHeight;
            
            console.log(`[STAFF-DEBUG] ${message}`);
        }

        // Interceptores de redirección GLOBALES
        function setupRedirectInterceptors() {
            debugLog('🛡️ Configurando interceptores de redirección...', 'info');
            
            // Interceptar window.location changes
            const originalAssign = window.location.assign;
            const originalReplace = window.location.replace;
            
            window.location.assign = function(url) {
                debugLog('🚨 REDIRECT ATTEMPT (assign): ' + url, 'error');
                if (url.includes('dashboard')) {
                    debugLog('🚨 DASHBOARD REDIRECT BLOCKED!', 'error');
                    alert('DASHBOARD REDIRECT BLOCKED: ' + url);
                    return false;
                }
                return originalAssign.call(this, url);
            };
            
            window.location.replace = function(url) {
                debugLog('🚨 REDIRECT ATTEMPT (replace): ' + url, 'error');
                if (url.includes('dashboard')) {
                    debugLog('🚨 DASHBOARD REDIRECT BLOCKED!', 'error');
                    alert('DASHBOARD REDIRECT BLOCKED: ' + url);
                    return false;
                }
                return originalReplace.call(this, url);
            };
            
            // Interceptar history API
            const originalPushState = history.pushState;
            const originalReplaceState = history.replaceState;
            
            history.pushState = function(...args) {
                debugLog('🚨 PUSH STATE: ' + JSON.stringify(args), 'warning');
                if (JSON.stringify(args).includes('dashboard')) {
                    debugLog('🚨 DASHBOARD PUSH STATE BLOCKED!', 'error');
                    alert('DASHBOARD PUSH STATE BLOCKED');
                    return false;
                }
                return originalPushState.apply(this, args);
            };
            
            history.replaceState = function(...args) {
                debugLog('🚨 REPLACE STATE: ' + JSON.stringify(args), 'warning');
                if (JSON.stringify(args).includes('dashboard')) {
                    debugLog('🚨 DASHBOARD REPLACE STATE BLOCKED!', 'error');
                    alert('DASHBOARD REPLACE STATE BLOCKED');
                    return false;
                }
                return originalReplaceState.apply(this, args);
            };
            
            debugLog('✅ Interceptores configurados', 'success');
        }

        function loadAuthClient() {
            debugLog('📡 STEP 1: Cargando auth-client.js...', 'info');
            
            const script = document.createElement('script');
            script.src = 'assets/js/auth-client.js';
            
            script.onload = function() {
                debugLog('✅ auth-client.js cargado exitosamente', 'success');
                
                setTimeout(() => {
                    if (typeof AuthClient !== 'undefined') {
                        debugLog('✅ AuthClient clase disponible', 'success');
                        
                        try {
                            window.authClient = new AuthClient();
                            debugLog('✅ AuthClient instanciado correctamente', 'success');
                        } catch (e) {
                            debugLog('❌ Error instanciando AuthClient: ' + e.message, 'error');
                        }
                    } else {
                        debugLog('❌ AuthClient no definido después de cargar', 'error');
                    }
                }, 100);
            };
            
            script.onerror = function() {
                debugLog('❌ Error cargando auth-client.js', 'error');
            };
            
            document.head.appendChild(script);
        }

        function loadChatClient() {
            debugLog('📡 STEP 2: Cargando chat-client.js...', 'info');
            
            // Primero cargar Socket.IO
            const socketScript = document.createElement('script');
            socketScript.src = 'https://cdn.socket.io/4.7.2/socket.io.min.js';
            
            socketScript.onload = function() {
                debugLog('✅ Socket.IO cargado', 'success');
                
                // Luego cargar chat-client
                const chatScript = document.createElement('script');
                chatScript.src = 'assets/js/chat-client.js';
                
                chatScript.onload = function() {
                    debugLog('✅ chat-client.js cargado exitosamente', 'success');
                    
                    setTimeout(() => {
                        if (typeof ChatClient !== 'undefined') {
                            debugLog('✅ ChatClient clase disponible', 'success');
                        } else {
                            debugLog('❌ ChatClient no definido', 'error');
                        }
                    }, 100);
                };
                
                chatScript.onerror = function() {
                    debugLog('❌ Error cargando chat-client.js', 'error');
                };
                
                document.head.appendChild(chatScript);
            };
            
            socketScript.onerror = function() {
                debugLog('❌ Error cargando Socket.IO', 'error');
            };
            
            document.head.appendChild(socketScript);
        }

        function loadMainScript() {
            debugLog('📡 STEP 3: Cargando script principal...', 'info');
            
            // Aquí cargaríamos el script principal del staff.php original
            // Por ahora, solo simular
            setTimeout(() => {
                debugLog('✅ Script principal simulado', 'success');
                
                // Simular la configuración que podría estar causando problemas
                const CONFIG = {
                    AUTH_SERVICE_URL: 'http://187.33.158.246:8080/auth',
                    USER_ROLE: '<?= $userRole ?>',
                    USER_DATA: <?= json_encode($user) ?>
                };
                
                debugLog('📋 CONFIG cargado: ' + JSON.stringify(CONFIG), 'info');
                
                // Aquí es donde podría estar el problema - verificar si algo en CONFIG causa redirección
                
            }, 1000);
        }

        function logout() {
            debugLog('👋 Logout iniciado...', 'info');
            if (window.authClient) {
                window.authClient.logout();
            } else {
                window.location.href = '/practicas/chat-frontend/public/logout.php';
            }
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            debugLog('🟢 DOM cargado', 'success');
            debugLog('📍 URL actual: ' + window.location.href, 'info');
            debugLog('📍 Referrer: ' + document.referrer, 'info');
            
            setupRedirectInterceptors();
            
            debugLog('🎯 Listo para testing manual', 'success');
            debugLog('👆 Usa los botones para cargar scripts paso a paso', 'info');
        });

        // Monitor continuo de URL
        let lastUrl = window.location.href;
        setInterval(() => {
            if (lastUrl !== window.location.href) {
                debugLog('🚨 URL CAMBIÓ: ' + lastUrl + ' → ' + window.location.href, 'error');
                if (window.location.href.includes('dashboard')) {
                    debugLog('🚨 DASHBOARD DETECTADO EN URL!', 'error');
                    alert('DASHBOARD DETECTADO! URL: ' + window.location.href);
                }
                lastUrl = window.location.href;
            }
        }, 200);
    </script>
</body>
</html>