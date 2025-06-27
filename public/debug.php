<?php
// public/debug.php - Página de diagnóstico para verificar el sistema
require_once __DIR__ . '/../config/config.php';

// Solo mostrar en desarrollo
if (APP_ENV !== 'development') {
    http_response_code(404);
    exit('Not found');
}

$testPToken = $_GET['ptoken'] ?? 'CC678AVEZVKADBT';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Sistema de Chat Médico</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .debug-section {
            margin-bottom: 2rem;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: #f9fafb;
        }
        .test-result {
            padding: 0.5rem;
            margin: 0.5rem 0;
            border-radius: 0.25rem;
            font-family: monospace;
            font-size: 0.875rem;
        }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">🔧 Sistema de Debug</h1>
            
            <!-- Configuración Actual -->
            <div class="debug-section">
                <h2 class="text-lg font-semibold mb-3">⚙️ Configuración Actual</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <strong>Auth Service URL:</strong><br>
                        <code class="bg-gray-200 px-2 py-1 rounded"><?= AUTH_SERVICE_URL ?></code>
                    </div>
                    <div>
                        <strong>Chat Service URL:</strong><br>
                        <code class="bg-gray-200 px-2 py-1 rounded"><?= CHAT_SERVICE_URL ?></code>
                    </div>
                    <div>
                        <strong>Environment:</strong><br>
                        <code class="bg-gray-200 px-2 py-1 rounded"><?= APP_ENV ?></code>
                    </div>
                    <div>
                        <strong>App Version:</strong><br>
                        <code class="bg-gray-200 px-2 py-1 rounded"><?= APP_VERSION ?></code>
                    </div>
                </div>
            </div>

            <!-- Test de Conectividad -->
            <div class="debug-section">
                <h2 class="text-lg font-semibold mb-3">🌐 Test de Conectividad</h2>
                <button onclick="testConnectivity()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Probar Conexiones
                </button>
                <div id="connectivityResults" class="mt-4"></div>
            </div>

            <!-- Test de pToken -->
            <div class="debug-section">
                <h2 class="text-lg font-semibold mb-3">🔑 Test de pToken</h2>
                <div class="flex gap-4 mb-4">
                    <input type="text" id="ptokenInput" value="<?= htmlspecialchars($testPToken) ?>" 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded" 
                           placeholder="Ingresa pToken a probar">
                    <button onclick="testPToken()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Probar pToken
                    </button>
                </div>
                <div id="ptokenResults" class="mt-4"></div>
            </div>

            <!-- Test de Salas -->
            <div class="debug-section">
                <h2 class="text-lg font-semibold mb-3">🏠 Test de Salas</h2>
                <button onclick="testRooms()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                    Probar Salas
                </button>
                <div id="roomsResults" class="mt-4"></div>
            </div>

            <!-- Test de Auth Staff -->
            <div class="debug-section">
                <h2 class="text-lg font-semibold mb-3">👨‍⚕️ Test de Auth Staff</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <input type="email" id="emailInput" value="admin@tpsalud.com" 
                           class="px-3 py-2 border border-gray-300 rounded" 
                           placeholder="Email">
                    <input type="password" id="passwordInput" value="Admin123" 
                           class="px-3 py-2 border border-gray-300 rounded" 
                           placeholder="Password">
                </div>
                <button onclick="testStaffLogin()" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    Probar Login Staff
                </button>
                <div id="staffResults" class="mt-4"></div>
            </div>

            <!-- Enlaces Rápidos -->
            <div class="debug-section">
                <h2 class="text-lg font-semibold mb-3">🔗 Enlaces Rápidos</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="preauth.php?pToken=<?= urlencode($testPToken) ?>" 
                       class="block bg-blue-500 text-white text-center px-4 py-2 rounded hover:bg-blue-600">
                        🔗 Probar como Paciente
                    </a>
                    <a href="index.php" 
                       class="block bg-green-500 text-white text-center px-4 py-2 rounded hover:bg-green-600">
                        👨‍⚕️ Portal de Staff
                    </a>
                    <a href="staff.php" 
                       class="block bg-purple-500 text-white text-center px-4 py-2 rounded hover:bg-purple-600">
                        📊 Panel de Admin
                    </a>
                    <a href="<?= AUTH_SERVICE_URL ?>/../health" target="_blank"
                       class="block bg-yellow-500 text-white text-center px-4 py-2 rounded hover:bg-yellow-600">
                        🏥 Health Auth Service
                    </a>
                </div>
            </div>

            <!-- Información del Sistema -->
            <div class="debug-section">
                <h2 class="text-lg font-semibold mb-3">📊 Información del Sistema</h2>
                <div class="text-sm space-y-2">
                    <div><strong>PHP Version:</strong> <?= PHP_VERSION ?></div>
                    <div><strong>Server Software:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></div>
                    <div><strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' ?></div>
                    <div><strong>Current Time:</strong> <?= date('Y-m-d H:i:s T') ?></div>
                    <div><strong>User Agent:</strong> <?= $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown' ?></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CONFIG = {
            AUTH_SERVICE_URL: '<?= AUTH_SERVICE_URL ?>',
            CHAT_SERVICE_URL: '<?= CHAT_SERVICE_URL ?>'
        };

        function addResult(containerId, message, type = 'info') {
            const container = document.getElementById(containerId);
            const div = document.createElement('div');
            div.className = `test-result ${type}`;
            div.innerHTML = message;
            container.appendChild(div);
        }

        function clearResults(containerId) {
            document.getElementById(containerId).innerHTML = '';
        }

        async function testConnectivity() {
            clearResults('connectivityResults');
            addResult('connectivityResults', '🔄 Probando conectividad...', 'info');

            // Test Auth Service Health
            try {
                const response = await fetch(`${CONFIG.AUTH_SERVICE_URL}/../health`);
                const data = await response.json();
                if (response.ok) {
                    addResult('connectivityResults', `✅ Auth Service OK - Version: ${data.version || 'unknown'}`, 'success');
                } else {
                    addResult('connectivityResults', `❌ Auth Service Error: ${response.status}`, 'error');
                }
            } catch (error) {
                addResult('connectivityResults', `❌ Auth Service Connection Error: ${error.message}`, 'error');
            }

            // Test Chat Service Health
            try {
                const response = await fetch(`${CONFIG.CHAT_SERVICE_URL}/health`);
                const data = await response.json();
                if (response.ok) {
                    addResult('connectivityResults', `✅ Chat Service OK - Version: ${data.version || 'unknown'}`, 'success');
                } else {
                    addResult('connectivityResults', `⚠️ Chat Service Status: ${response.status}`, 'warning');
                }
            } catch (error) {
                addResult('connectivityResults', `❌ Chat Service Connection Error: ${error.message}`, 'error');
            }

            // Test Internet connectivity
            try {
                const response = await fetch('https://httpbin.org/json');
                if (response.ok) {
                    addResult('connectivityResults', '✅ Internet connectivity OK', 'success');
                } else {
                    addResult('connectivityResults', '⚠️ Internet connectivity issues', 'warning');
                }
            } catch (error) {
                addResult('connectivityResults', '❌ No internet access', 'error');
            }
        }

        async function testPToken() {
            const ptoken = document.getElementById('ptokenInput').value.trim();
            clearResults('ptokenResults');
            
            if (!ptoken) {
                addResult('ptokenResults', '❌ Por favor ingresa un pToken', 'error');
                return;
            }

            addResult('ptokenResults', `🔄 Probando pToken: ${ptoken.substring(0, 15)}...`, 'info');

            // Test con GET
            try {
                const response = await fetch(`${CONFIG.AUTH_SERVICE_URL}/validate-token?ptoken=${encodeURIComponent(ptoken)}`, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                
                const data = await response.json();
                addResult('ptokenResults', `📡 GET Response (${response.status}): ${JSON.stringify(data, null, 2)}`, response.ok ? 'success' : 'error');
                
            } catch (error) {
                addResult('ptokenResults', `❌ GET Error: ${error.message}`, 'error');
            }

            // Test con POST
            try {
                const response = await fetch(`${CONFIG.AUTH_SERVICE_URL}/validate-token`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ptoken: ptoken })
                });
                
                const data = await response.json();
                addResult('ptokenResults', `📡 POST Response (${response.status}): ${JSON.stringify(data, null, 2)}`, response.ok ? 'success' : 'error');
                
            } catch (error) {
                addResult('ptokenResults', `❌ POST Error: ${error.message}`, 'error');
            }
        }

        async function testRooms() {
            clearResults('roomsResults');
            addResult('roomsResults', '🔄 Probando salas...', 'info');

            // Necesitamos un token válido primero
            const ptoken = document.getElementById('ptokenInput').value.trim();
            
            if (!ptoken) {
                addResult('roomsResults', '❌ Necesitas un pToken válido primero', 'error');
                return;
            }

            try {
                const response = await fetch(`${CONFIG.AUTH_SERVICE_URL}/rooms/available`, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${ptoken}`,
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                addResult('roomsResults', `📡 Rooms Response (${response.status}): ${JSON.stringify(data, null, 2)}`, response.ok ? 'success' : 'error');
                
            } catch (error) {
                addResult('roomsResults', `❌ Rooms Error: ${error.message}`, 'error');
            }
        }

        async function testStaffLogin() {
            const email = document.getElementById('emailInput').value.trim();
            const password = document.getElementById('passwordInput').value.trim();
            
            clearResults('staffResults');
            
            if (!email || !password) {
                addResult('staffResults', '❌ Por favor ingresa email y password', 'error');
                return;
            }

            addResult('staffResults', `🔄 Probando login: ${email}`, 'info');

            try {
                const response = await fetch(`${CONFIG.AUTH_SERVICE_URL}/login`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password
                    })
                });
                
                const data = await response.json();
                addResult('staffResults', `📡 Login Response (${response.status}): ${JSON.stringify(data, null, 2)}`, response.ok ? 'success' : 'error');
                
            } catch (error) {
                addResult('staffResults', `❌ Login Error: ${error.message}`, 'error');
            }
        }

        // Auto-test on load
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🔧 Debug page loaded');
            testConnectivity();
        });
    </script>
</body>
</html>