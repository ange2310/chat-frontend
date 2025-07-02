<?php
// public/staff-test.php - Versión MÍNIMA para encontrar el problema
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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Staff Test - SIN JavaScript</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-6">
        <div class="max-w-4xl mx-auto">
            
            <!-- Header de Test -->
            <div class="bg-green-100 border border-green-400 rounded-lg p-6 mb-6">
                <h1 class="text-2xl font-bold text-green-800 mb-2">🧪 Staff Test Page</h1>
                <p class="text-green-700">Esta es una versión MÍNIMA de staff.php sin JavaScript complejo</p>
                <p class="text-sm text-green-600 mt-2">Si esta página se carga sin redirección, el problema está en el JavaScript</p>
            </div>

            <!-- Información del Usuario -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">👤 Información del Usuario</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <strong>Nombre:</strong> <?= htmlspecialchars($user['name'] ?? 'Unknown') ?>
                    </div>
                    <div>
                        <strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'Unknown') ?>
                    </div>
                    <div>
                        <strong>Rol:</strong> <?= htmlspecialchars($userRole) ?>
                    </div>
                    <div>
                        <strong>ID:</strong> <?= htmlspecialchars($user['id'] ?? 'Unknown') ?>
                    </div>
                </div>
            </div>

            <!-- Debug Info -->
            <div class="bg-blue-50 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">🔧 Debug Info</h2>
                <div class="space-y-2 text-sm">
                    <p><strong>URL Actual:</strong> <?= $_SERVER['REQUEST_URI'] ?? 'unknown' ?></p>
                    <p><strong>Referrer:</strong> <?= $_SERVER['HTTP_REFERER'] ?? 'none' ?></p>
                    <p><strong>User Agent:</strong> <?= substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100) ?>...</p>
                    <p><strong>Session ID:</strong> <?= session_id() ?></p>
                    <p><strong>Auth Status:</strong> <?= $auth->isAuthenticated() ? 'Authenticated' : 'Not authenticated' ?></p>
                </div>
            </div>

            <!-- Enlaces de Test -->
            <div class="bg-yellow-50 rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">🔗 Enlaces de Test</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="/practicas/chat-frontend/public/index.php" 
                       class="block bg-blue-600 text-white text-center py-3 px-4 rounded hover:bg-blue-700">
                        🏠 Volver a Index
                    </a>
                    <a href="/practicas/chat-frontend/public/staff.php" 
                       class="block bg-red-600 text-white text-center py-3 px-4 rounded hover:bg-red-700">
                        ⚠️ Ir a Staff Original
                    </a>
                    <a href="/practicas/chat-frontend/public/logout.php" 
                       class="block bg-gray-600 text-white text-center py-3 px-4 rounded hover:bg-gray-700">
                        🚪 Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript MÍNIMO solo para debugging -->
    <script>
        console.log('🧪 Staff-test.php cargado');
        console.log('📍 URL actual:', window.location.href);
        console.log('📍 Pathname:', window.location.pathname);
        
        // Interceptar CUALQUIER intento de redirección
        const originalAssign = window.location.assign;
        const originalReplace = window.location.replace;
        
        window.location.assign = function(url) {
            console.log('🚨 REDIRECT ATTEMPT (assign):', url);
            alert('REDIRECT BLOCKED: ' + url);
            return originalAssign.call(this, url);
        };
        
        window.location.replace = function(url) {
            console.log('🚨 REDIRECT ATTEMPT (replace):', url);
            alert('REDIRECT BLOCKED: ' + url);
            return originalReplace.call(this, url);
        };
        
        // Interceptar cambios de href
        Object.defineProperty(window.location, 'href', {
            set: function(url) {
                console.log('🚨 HREF CHANGE ATTEMPT:', url);
                alert('HREF CHANGE BLOCKED: ' + url);
            },
            get: function() {
                return document.location.href;
            }
        });
        
        console.log('✅ Redirect interceptors instalados');
        
        // Monitor cada 500ms por cambios no autorizados
        let lastUrl = window.location.href;
        setInterval(() => {
            if (lastUrl !== window.location.href) {
                console.log('🚨 UNAUTHORIZED URL CHANGE:', {
                    from: lastUrl,
                    to: window.location.href
                });
                alert('UNAUTHORIZED URL CHANGE DETECTED!');
                lastUrl = window.location.href;
            }
        }, 500);
    </script>
</body>
</html>