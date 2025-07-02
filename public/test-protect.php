<?php
// public/test-protect.php - Test específico de la función protectStaffPage()

echo "<h1>🧪 TEST: Antes de incluir protectStaffPage()</h1>";
echo "<p>URL actual: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Si esta página se carga, el problema NO está antes de protectStaffPage()</p>";

// Iniciar sesión manualmente
session_start();

// Verificar si hay datos de sesión
echo "<h2>📋 Estado de Sesión:</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "pToken: " . (isset($_SESSION['pToken']) ? 'EXISTS' : 'NO EXISTE') . "\n";
echo "User: " . (isset($_SESSION['user']) ? 'EXISTS' : 'NO EXISTE') . "\n";
echo "Session data: " . json_encode($_SESSION ?? [], JSON_PRETTY_PRINT) . "\n";
echo "</pre>";

echo "<h2>⚠️ AHORA VAMOS A INCLUIR LA FUNCIÓN PROBLEMÁTICA...</h2>";
echo "<p>Si la página redirije después de esto, confirmamos que protectStaffPage() es el culpable</p>";

// Hacer un flush para mostrar el contenido antes de la posible redirección
ob_flush();
flush();

// AQUÍ INCLUIMOS LA FUNCIÓN PROBLEMÁTICA
try {
    require_once __DIR__ . '/../config/config.php';
    echo "<p>✅ config.php incluido sin problemas</p>";
    ob_flush();
    flush();
    
    require_once __DIR__ . '/../config/auth.php';
    echo "<p>✅ auth.php incluido sin problemas</p>";
    ob_flush();
    flush();
    
    echo "<p>🔍 Ahora llamando protectStaffPage()...</p>";
    ob_flush();
    flush();
    
    // ESTA ES LA LÍNEA QUE PROBABLEMENTE CAUSA LA REDIRECCIÓN
    protectStaffPage();
    
    // Si llegamos aquí, protectStaffPage() NO causó redirección
    echo "<h1 style='color: green;'>✅ SUCCESS: protectStaffPage() NO causó redirección!</h1>";
    echo "<p>Esto significa que el usuario está autenticado correctamente</p>";
    
} catch (Exception $e) {
    echo "<h1 style='color: red;'>❌ ERROR: " . $e->getMessage() . "</h1>";
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test protectStaffPage()</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f0f0f0; }
        h1 { color: #333; }
        pre { background: #fff; padding: 10px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h2>🎯 Test Completado</h2>
    <p>Si ves este mensaje, significa que protectStaffPage() permitió el acceso.</p>
    
    <div style="background: yellow; padding: 10px; margin: 20px 0;">
        <strong>📋 Conclusiones:</strong>
        <ul>
            <li>Si esta página se carga = protectStaffPage() está bien</li>
            <li>Si redirije a dashboard = protectStaffPage() tiene un bug</li>
            <li>Si redirije a index.php = usuario no autenticado (normal)</li>
        </ul>
    </div>
    
    <a href="/practicas/chat-frontend/public/index.php" style="background: blue; color: white; padding: 10px; text-decoration: none; border-radius: 5px;">🏠 Volver al Index</a>
    
    <script>
        console.log('🟢 Test protectStaffPage() completado sin redirección');
        console.log('📍 URL final:', window.location.href);
        
        // Verificar si hay redirección después de cargar
        setTimeout(() => {
            console.log('✅ No hubo redirección después de 2 segundos');
        }, 2000);
    </script>
</body>
</html>