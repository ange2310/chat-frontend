<?php
// public/bypass-test.php - Test SIN protectStaffPage() ni configuraciones complejas
// ABSOLUTAMENTE MÍNIMO - sin includes problemáticos

// Solo verificación básica de sesión
session_start();

// NO usar require_once de config.php o auth.php que pueden tener redirecciones
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨 BYPASS TEST</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-red-100 min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        
        <!-- Header de ÉXITO -->
        <div class="bg-green-100 border-4 border-green-500 rounded-lg p-6 mb-6">
            <div class="flex items-center">
                <div class="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center mr-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-green-800">🎯 BYPASS TEST SUCCESSFUL!</h1>
                    <p class="text-green-700 text-lg">Esta página NO redirije a dashboard</p>
                    <p class="text-green-600">Esto confirma que el problema está en protectStaffPage() o archivos .htaccess</p>
                </div>
            </div>
        </div>

        <!-- Información de Debug -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-semibold mb-4">🔍 Información de Debug</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <strong>URL Actual:</strong><br>
                    <code class="bg-gray-200 px-2 py-1 rounded"><?= $_SERVER['REQUEST_URI'] ?? 'unknown' ?></code>
                </div>
                <div>
                    <strong>Método HTTP:</strong><br>
                    <code class="bg-gray-200 px-2 py-1 rounded"><?= $_SERVER['REQUEST_METHOD'] ?? 'unknown' ?></code>
                </div>
                <div>
                    <strong>Referrer:</strong><br>
                    <code class="bg-gray-200 px-2 py-1 rounded"><?= substr($_SERVER['HTTP_REFERER'] ?? 'none', 0, 50) ?>...</code>
                </div>
                <div>
                    <strong>User Agent:</strong><br>
                    <code class="bg-gray-200 px-2 py-1 rounded"><?= substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 50) ?>...</code>
                </div>
                <div>
                    <strong>Document Root:</strong><br>
                    <code class="bg-gray-200 px-2 py-1 rounded"><?= $_SERVER['DOCUMENT_ROOT'] ?? 'unknown' ?></code>
                </div>
                <div>
                    <strong>Script Name:</strong><br>
                    <code class="bg-gray-200 px-2 py-1 rounded"><?= $_SERVER['SCRIPT_NAME'] ?? 'unknown' ?></code>
                </div>
            </div>
        </div>

        <!-- Verificación de Archivos .htaccess -->
        <div class="bg-yellow-50 rounded-lg p-6 mb-6">
            <h2 class="text-2xl font-semibold mb-4">📁 Verificación de .htaccess</h2>
            
            <?php
            $htaccess_locations = [
                __DIR__ . '/.htaccess',
                __DIR__ . '/../.htaccess', 
                $_SERVER['DOCUMENT_ROOT'] . '/.htaccess',
                '/var/www/html/.htaccess',
                'C:/xampp/htdocs/.htaccess'
            ];
            
            $found_problem = false;
            
            foreach ($htaccess_locations as $location) {
                echo "<div class='mb-4 p-3 border rounded'>";
                echo "<strong>📄 $location:</strong><br>";
                
                if (file_exists($location)) {
                    echo "<span class='text-green-600'>✅ Archivo existe</span><br>";
                    
                    $content = file_get_contents($location);
                    $lines = explode("\n", $content);
                    
                    echo "<div class='mt-2 bg-gray-800 text-green-400 p-3 rounded font-mono text-xs max-h-40 overflow-y-auto'>";
                    
                    foreach ($lines as $line_num => $line) {
                        $line = trim($line);
                        if (empty($line) || $line[0] === '#') continue;
                        
                        // Buscar reglas problemáticas
                        if (stripos($line, 'dashboard') !== false || 
                            stripos($line, 'redirect') !== false ||
                            stripos($line, 'rewrite') !== false) {
                            
                            echo "<span style='background: red; color: white; font-weight: bold;'>";
                            echo "LÍNEA " . ($line_num + 1) . ": " . htmlspecialchars($line);
                            echo "</span><br>";
                            
                            $found_problem = true;
                        } else {
                            echo "LÍNEA " . ($line_num + 1) . ": " . htmlspecialchars($line) . "<br>";
                        }
                    }
                    
                    echo "</div>";
                } else {
                    echo "<span class='text-gray-500'>❌ Archivo no existe</span>";
                }
                
                echo "</div>";
            }
            
            if ($found_problem) {
                echo "<div class='bg-red-100 border border-red-400 rounded p-4 mt-4'>";
                echo "<h3 class='text-red-800 font-bold'>🚨 PROBLEMA ENCONTRADO EN .HTACCESS!</h3>";
                echo "<p class='text-red-700'>Se encontraron reglas que pueden estar causando la redirección a dashboard.</p>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- Test de Configuración PHP -->
        <div class="bg-blue-50 rounded-lg p-6 mb-6">
            <h2 class="text-2xl font-semibold mb-4">🔧 Test de Configuración PHP</h2>
            
            <?php
            echo "<div class='space-y-2 text-sm'>";
            echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
            echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "</p>";
            echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
            echo "<p><strong>Session Data:</strong> " . json_encode($_SESSION ?? []) . "</p>";
            
            // Verificar si hay variables de entorno problemáticas
            echo "<p><strong>SERVER_SOFTWARE:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "</p>";
            
            // Verificar headers de redirección
            $headers = headers_list();
            if (!empty($headers)) {
                echo "<p><strong>Headers enviados:</strong></p>";
                echo "<ul class='list-disc list-inside'>";
                foreach ($headers as $header) {
                    if (stripos($header, 'location') !== false || stripos($header, 'redirect') !== false) {
                        echo "<li class='text-red-600 font-bold'>🚨 " . htmlspecialchars($header) . "</li>";
                    } else {
                        echo "<li>" . htmlspecialchars($header) . "</li>";
                    }
                }
                echo "</ul>";
            } else {
                echo "<p class='text-green-600'>✅ No hay headers de redirección</p>";
            }
            echo "</div>";
            ?>
        </div>

        <!-- Acciones -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="/practicas/chat-frontend/public/index.php" 
               class="bg-blue-600 text-white text-center py-3 px-4 rounded hover:bg-blue-700 block">
                🏠 Volver a Index
            </a>
            <button onclick="testWithProtection()" 
                    class="bg-red-600 text-white py-3 px-4 rounded hover:bg-red-700">
                ⚠️ Test con protectStaffPage()
            </button>
            <button onclick="showSolution()" 
                    class="bg-green-600 text-white py-3 px-4 rounded hover:bg-green-700">
                💡 Mostrar Solución
            </button>
        </div>

        <!-- Área de Solución -->
        <div id="solutionArea" class="hidden mt-6 bg-green-50 border border-green-400 rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4 text-green-800">💡 SOLUCIÓN ENCONTRADA</h2>
            <div id="solutionContent" class="text-green-700"></div>
        </div>
    </div>

    <script>
        console.log('🟢 BYPASS TEST - Página cargada sin redirección');
        console.log('📍 URL actual:', window.location.href);
        console.log('✅ Confirmado: NO hay redirección automática cuando no usamos protectStaffPage()');

        function testWithProtection() {
            alert('Este test cargaría una página con protectStaffPage() para confirmar que ESE es el problema.');
            console.log('🔬 Test sugerido: crear página con solo protectStaffPage() incluido');
        }

        function showSolution() {
            const solutionArea = document.getElementById('solutionArea');
            const solutionContent = document.getElementById('solutionContent');
            
            solutionContent.innerHTML = `
                <h3 class="text-xl font-semibold mb-3">🎯 PROBLEMA IDENTIFICADO:</h3>
                <p class="mb-3">La función <code>protectStaffPage()</code> o archivos .htaccess están causando la redirección a "/dashboard/".</p>
                
                <h3 class="text-xl font-semibold mb-3">🛠️ SOLUCIONES:</h3>
                <ol class="list-decimal list-inside space-y-2">
                    <li><strong>Revisar config/auth.php:</strong> Buscar la función protectStaffPage() y eliminar cualquier redirección a "dashboard"</li>
                    <li><strong>Eliminar/renombrar archivos .htaccess problemáticos</strong> (si se encontraron arriba)</li>
                    <li><strong>Verificar configuración de XAMPP/Apache</strong> que pueda estar redirigiendo automáticamente</li>
                    <li><strong>Crear nueva función de protección</strong> sin redirecciones problemáticas</li>
                </ol>
                
                <h3 class="text-xl font-semibold mb-3 mt-4">⚡ ACCIÓN INMEDIATA:</h3>
                <p class="bg-yellow-100 p-3 rounded">
                    Abre <code>config/auth.php</code> y busca la función <code>protectStaffPage()</code>. 
                    Cualquier línea que contenga "dashboard" debe ser eliminada o modificada.
                </p>
            `;
            
            solutionArea.classList.remove('hidden');
        }

        // Verificar que no hay redirección después de cargar
        setTimeout(() => {
            if (window.location.href === window.location.href) {
                console.log('✅ CONFIRMADO: No hay redirección después de 3 segundos');
                console.log('💡 CONCLUSIÓN: El problema está en protectStaffPage() o .htaccess');
            }
        }, 3000);
    </script>
</body>
</html>