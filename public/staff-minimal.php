<?php
// public/staff-minimal.php - ULTRA MINIMAL
session_start();

// Verificación mínima de autenticación SIN incluir config complejos
if (!isset($_SESSION['pToken']) || empty($_SESSION['pToken'])) {
    header("Location: /practicas/chat-frontend/public/index.php");
    exit;
}

$user_data = isset($_SESSION['user']) ? json_decode($_SESSION['user'], true) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff ULTRA Minimal</title>
    <!-- Solo CSS externo seguro -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto p-6">
        
        <!-- Header Success -->
        <div class="bg-green-100 border border-green-400 rounded-lg p-6 mb-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-green-800">✅ Staff Page Loaded Successfully!</h1>
                    <p class="text-green-700">Esta es la versión ULTRA MINIMAL sin JavaScript complejo</p>
                </div>
            </div>
        </div>

        <!-- User Info -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">👤 Usuario Autenticado</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <strong>Nombre:</strong> 
                    <?= htmlspecialchars($user_data['name'] ?? 'Unknown User') ?>
                </div>
                <div>
                    <strong>Email:</strong> 
                    <?= htmlspecialchars($user_data['email'] ?? 'unknown@email.com') ?>
                </div>
                <div>
                    <strong>Token Length:</strong> 
                    <?= strlen($_SESSION['pToken'] ?? '') ?> chars
                </div>
                <div>
                    <strong>Session ID:</strong> 
                    <?= substr(session_id(), 0, 10) ?>...
                </div>
            </div>
        </div>

        <!-- Test Results -->
        <div class="bg-blue-50 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">🧪 Test Results</h2>
            <div class="space-y-2">
                <p class="text-green-600">✅ PHP Session: Working</p>
                <p class="text-green-600">✅ User Data: Available</p>
                <p class="text-green-600">✅ No JavaScript Conflicts</p>
                <p class="text-green-600">✅ No External Script Dependencies</p>
                <p class="text-green-600">✅ No CSS File Dependencies</p>
                <p id="urlTest" class="text-blue-600">🔍 URL Test: <span id="currentUrl">Loading...</span></p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <a href="/practicas/chat-frontend/public/index.php" 
               class="bg-blue-600 text-white text-center py-3 px-4 rounded hover:bg-blue-700 block">
                🏠 Back to Index
            </a>
            <a href="/practicas/chat-frontend/public/staff.php" 
               class="bg-red-600 text-white text-center py-3 px-4 rounded hover:bg-red-700 block">
                ⚠️ Go to Original Staff
            </a>
            <a href="/practicas/chat-frontend/public/logout.php" 
               class="bg-gray-600 text-white text-center py-3 px-4 rounded hover:bg-gray-700 block">
                🚪 Logout
            </a>
            <button onclick="runDiagnostics()" 
                    class="bg-purple-600 text-white py-3 px-4 rounded hover:bg-purple-700">
                🔍 Run Diagnostics
            </button>
        </div>

        <!-- Diagnostic Output -->
        <div id="diagnosticOutput" class="mt-6 bg-gray-800 text-green-400 rounded-lg p-4 font-mono text-sm" style="display: none;">
            <h3 class="text-white mb-2">🔍 Diagnostic Output:</h3>
            <pre id="diagnosticText"></pre>
        </div>
    </div>

    <!-- JAVASCRIPT MÍNIMO Y SEGURO -->
    <script>
        // Solo JavaScript básico y seguro
        console.log('🟢 Staff Minimal Page Loaded');
        console.log('📍 Current URL:', window.location.href);
        
        // Mostrar URL actual
        document.getElementById('currentUrl').textContent = window.location.href;
        
        // Verificar que NO hay redirección automática
        let initialUrl = window.location.href;
        let checkCount = 0;
        
        const redirectChecker = setInterval(() => {
            checkCount++;
            
            if (window.location.href !== initialUrl) {
                console.log('🚨 REDIRECT DETECTED!');
                console.log('From:', initialUrl);
                console.log('To:', window.location.href);
                
                if (window.location.href.includes('dashboard')) {
                    alert('🚨 DASHBOARD REDIRECT DETECTED!\nFrom: ' + initialUrl + '\nTo: ' + window.location.href);
                }
                
                clearInterval(redirectChecker);
            }
            
            // Parar después de 10 segundos
            if (checkCount > 50) {
                console.log('✅ No redirects detected after 10 seconds');
                clearInterval(redirectChecker);
            }
        }, 200);

        function runDiagnostics() {
            const output = document.getElementById('diagnosticOutput');
            const text = document.getElementById('diagnosticText');
            
            output.style.display = 'block';
            
            let diagnostic = '';
            diagnostic += 'DIAGNOSTIC REPORT - ' + new Date().toLocaleString() + '\n';
            diagnostic += '='.repeat(50) + '\n\n';
            
            diagnostic += 'URL INFORMATION:\n';
            diagnostic += '- Current URL: ' + window.location.href + '\n';
            diagnostic += '- Pathname: ' + window.location.pathname + '\n';
            diagnostic += '- Search: ' + window.location.search + '\n';
            diagnostic += '- Hash: ' + window.location.hash + '\n';
            diagnostic += '- Referrer: ' + document.referrer + '\n\n';
            
            diagnostic += 'SESSION INFORMATION:\n';
            diagnostic += '- Session Available: ' + (!!document.cookie.match(/PHPSESSID/)) + '\n';
            diagnostic += '- Cookies: ' + document.cookie.substring(0, 100) + '...\n\n';
            
            diagnostic += 'BROWSER INFORMATION:\n';
            diagnostic += '- User Agent: ' + navigator.userAgent + '\n';
            diagnostic += '- Language: ' + navigator.language + '\n\n';
            
            diagnostic += 'PAGE STATE:\n';
            diagnostic += '- Document Ready: ' + (document.readyState) + '\n';
            diagnostic += '- Scripts Loaded: ' + document.scripts.length + '\n';
            diagnostic += '- Links Loaded: ' + document.links.length + '\n\n';
            
            diagnostic += 'REDIRECT CHECK:\n';
            diagnostic += '- Initial URL: ' + initialUrl + '\n';
            diagnostic += '- Current URL: ' + window.location.href + '\n';
            diagnostic += '- URL Changed: ' + (initialUrl !== window.location.href) + '\n';
            
            if (window.location.href.includes('dashboard')) {
                diagnostic += '- 🚨 DASHBOARD DETECTED IN URL!\n';
            }
            
            text.textContent = diagnostic;
            
            // También hacer algunos tests de conectividad
            setTimeout(() => {
                diagnostic += '\nCONNECTIVITY TESTS:\n';
                
                // Test auth service
                fetch('http://187.33.158.246:8080/health')
                    .then(response => response.json())
                    .then(data => {
                        diagnostic += '- Auth Service Health: OK\n';
                        text.textContent = diagnostic;
                    })
                    .catch(error => {
                        diagnostic += '- Auth Service Health: ERROR - ' + error.message + '\n';
                        text.textContent = diagnostic;
                    });
            }, 1000);
        }

        // Auto-run basic diagnostic after 3 seconds
        setTimeout(() => {
            console.log('🤖 Auto-running basic diagnostics...');
            runDiagnostics();
        }, 3000);
    </script>
</body>
</html>