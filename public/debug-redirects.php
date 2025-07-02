<?php
// public/debug-redirects.php - Rastreador de redirecciones
require_once __DIR__ . '/../config/config.php';

// Solo en desarrollo
if (APP_ENV !== 'development') {
    http_response_code(404);
    exit('Not found');
}

// Obtener información detallada
$server_info = [
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'none',
    'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'none',
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'none',
    'REDIRECT_STATUS' => $_SERVER['REDIRECT_STATUS'] ?? 'none',
    'REDIRECT_URL' => $_SERVER['REDIRECT_URL'] ?? 'none'
];

$apache_info = [];
if (function_exists('apache_get_version')) {
    $apache_info['version'] = apache_get_version();
}
if (function_exists('apache_request_headers')) {
    $apache_info['headers'] = apache_request_headers();
}

session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🕵️ Debug de Redirecciones</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .code-block {
            background: #1a1a1a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            overflow-x: auto;
            white-space: pre-wrap;
            font-size: 12px;
        }
        .alert { padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">🕵️ Debug de Redirecciones</h1>
            <p class="text-gray-600 mb-6">Generado: <?= date('Y-m-d H:i:s') ?></p>

            <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', 'dashboard') !== false): ?>
            <div class="alert alert-danger">
                <h3 class="font-bold">🚨 DASHBOARD DETECTADO EN LA URL!</h3>
                <p>La URL actual contiene "dashboard": <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></p>
            </div>
            <?php endif; ?>

            <!-- Información del Servidor -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h2 class="text-xl font-semibold mb-4">🌐 Información del Servidor</h2>
                <div class="code-block"><?= htmlspecialchars(json_encode($server_info, JSON_PRETTY_PRINT)) ?></div>
            </div>

            <!-- Información de Apache -->
            <?php if (!empty($apache_info)): ?>
            <div class="bg-blue-50 rounded-lg p-4 mb-6">
                <h2 class="text-xl font-semibold mb-4">🔧 Información de Apache</h2>
                <div class="code-block"><?= htmlspecialchars(json_encode($apache_info, JSON_PRETTY_PRINT)) ?></div>
            </div>
            <?php endif; ?>

            <!-- Información de PHP Session -->
            <div class="bg-yellow-50 rounded-lg p-4 mb-6">
                <h2 class="text-xl font-semibold mb-4">🔑 Información de Sesión PHP</h2>
                <div class="code-block"><?= htmlspecialchars(json_encode($_SESSION ?? [], JSON_PRETTY_PRINT)) ?></div>
            </div>

            <!-- Test de Archivos .htaccess -->
            <div class="bg-green-50 rounded-lg p-4 mb-6">
                <h2 class="text-xl font-semibold mb-4">📁 Verificación de .htaccess</h2>
                <?php
                $htaccess_locations = [
                    __DIR__ . '/.htaccess',
                    __DIR__ . '/../.htaccess',
                    $_SERVER['DOCUMENT_ROOT'] . '/.htaccess',
                    '/var/www/html/.htaccess'
                ];
                
                foreach ($htaccess_locations as $location) {
                    echo "<div class='mb-2'>";
                    echo "<strong>$location:</strong> ";
                    if (file_exists($location)) {
                        echo "<span class='text-green-600'>✅ Existe</span>";
                        $content = file_get_contents($location);
                        if (strpos($content, 'dashboard') !== false) {
                            echo " <span class='text-red-600'>🚨 CONTIENE 'dashboard'!</span>";
                            echo "<div class='code-block mt-2'>" . htmlspecialchars($content) . "</div>";
                        }
                    } else {
                        echo "<span class='text-gray-500'>❌ No existe</span>";
                    }
                    echo "</div>";
                }
                ?>
            </div>

            <!-- Rastreador de Redirecciones en Tiempo Real -->
            <div class="bg-purple-50 rounded-lg p-4 mb-6">
                <h2 class="text-xl font-semibold mb-4">🔄 Rastreador en Tiempo Real</h2>
                <button onclick="startRedirectTracking()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 mb-4">
                    Iniciar Rastreo
                </button>
                <div id="redirectLog" class="code-block min-h-[200px]">Rastreo no iniciado...</div>
            </div>

            <!-- Tests Manuales -->
            <div class="bg-red-50 rounded-lg p-4">
                <h2 class="text-xl font-semibold mb-4">🧪 Tests Manuales</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button onclick="testIndex()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Test index.php
                    </button>
                    <button onclick="testStaff()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        Test staff.php
                    </button>
                    <button onclick="testLogout()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        Test logout.php
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let redirectTrackingActive = false;
        let originalLocation = window.location.href;

        function log(message, type = 'info') {
            const logDiv = document.getElementById('redirectLog');
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                error: 'color: #ef4444;',
                warning: 'color: #f59e0b;',
                success: 'color: #10b981;',
                info: 'color: #3b82f6;'
            };
            
            logDiv.innerHTML += `<span style="${colors[type] || colors.info}">[${timestamp}] ${message}</span>\n`;
            logDiv.scrollTop = logDiv.scrollHeight;
            
            console.log(`[REDIRECT-DEBUG] ${message}`);
        }

        function startRedirectTracking() {
            if (redirectTrackingActive) {
                log('Rastreo ya está activo', 'warning');
                return;
            }
            
            redirectTrackingActive = true;
            document.getElementById('redirectLog').innerHTML = '';
            log('🕵️ Rastreo de redirecciones iniciado', 'success');
            log(`📍 URL inicial: ${window.location.href}`, 'info');
            
            // Interceptar history API
            const originalPushState = history.pushState;
            const originalReplaceState = history.replaceState;
            
            history.pushState = function(...args) {
                log(`🚨 PUSH STATE: ${JSON.stringify(args)}`, 'error');
                console.trace('Stack trace for pushState:');
                return originalPushState.apply(this, args);
            };
            
            history.replaceState = function(...args) {
                log(`🚨 REPLACE STATE: ${JSON.stringify(args)}`, 'error');
                console.trace('Stack trace for replaceState:');
                return originalReplaceState.apply(this, args);
            };
            
            // Monitorear cambios de URL
            setInterval(() => {
                if (originalLocation !== window.location.href) {
                    log(`🔄 URL cambió: ${originalLocation} → ${window.location.href}`, 'warning');
                    
                    if (window.location.href.includes('dashboard')) {
                        log('🚨 DASHBOARD DETECTADO EN LA URL!', 'error');
                        log(`📱 User Agent: ${navigator.userAgent}`, 'info');
                        log(`📊 Referrer: ${document.referrer}`, 'info');
                        console.trace('Stack trace when dashboard detected:');
                    }
                    
                    originalLocation = window.location.href;
                }
            }, 100);
            
            // Interceptar XMLHttpRequest
            const originalXHROpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function(method, url, ...args) {
                log(`📡 XHR: ${method} ${url}`, 'info');
                return originalXHROpen.apply(this, [method, url, ...args]);
            };
            
            // Interceptar fetch
            const originalFetch = window.fetch;
            window.fetch = function(url, options = {}) {
                log(`📡 FETCH: ${url}`, 'info');
                return originalFetch(url, options);
            };
            
            // Interceptar window.location assignments
            let locationDescriptor = Object.getOwnPropertyDescriptor(window, 'location') || 
                                   Object.getOwnPropertyDescriptor(Window.prototype, 'location');
            
            log('✅ Interceptores configurados', 'success');
        }

        function testIndex() {
            log('🧪 Probando redirección a index.php...', 'info');
            setTimeout(() => {
                window.location.href = '/practicas/chat-frontend/public/index.php';
            }, 1000);
        }

        function testStaff() {
            log('🧪 Probando redirección a staff.php...', 'info');
            setTimeout(() => {
                window.location.href = '/practicas/chat-frontend/public/staff.php';
            }, 1000);
        }

        function testLogout() {
            log('🧪 Probando redirección a logout.php...', 'info');
            setTimeout(() => {
                window.location.href = '/practicas/chat-frontend/public/logout.php';
            }, 1000);
        }

        // Auto-start tracking
        document.addEventListener('DOMContentLoaded', () => {
            log('🟢 Debug page loaded', 'success');
            
            // Verificar si ya estamos en una URL problemática
            if (window.location.href.includes('dashboard')) {
                log('🚨 YA ESTAMOS EN UNA URL CON DASHBOARD!', 'error');
                log(`📍 URL actual: ${window.location.href}`, 'error');
            }
            
            // Auto-start tracking después de 2 segundos
            setTimeout(() => {
                startRedirectTracking();
            }, 2000);
        });
    </script>
</body>
</html>