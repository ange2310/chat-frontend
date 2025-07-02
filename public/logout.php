<?php
// public/logout.php - Limpieza TOTAL de sesión
session_start();
session_destroy();
session_unset();

// Limpiar todas las cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-1000);
        setcookie($name, '', time()-1000, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrando Sesión...</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-white mx-auto mb-4"></div>
        <h1 class="text-xl font-semibold mb-2">Cerrando sesión...</h1>
        <p class="text-gray-300">Limpiando datos de autenticación</p>
    </div>

    <script>
        // Limpiar ABSOLUTAMENTE TODO del navegador
        function clearEverything() {
            console.log('🧹 LIMPIEZA TOTAL DE SESIÓN');
            
            // 1. Limpiar localStorage
            localStorage.clear();
            console.log('✅ localStorage limpiado');
            
            // 2. Limpiar sessionStorage
            sessionStorage.clear();
            console.log('✅ sessionStorage limpiado');
            
            // 3. Limpiar IndexedDB si existe
            if ('indexedDB' in window) {
                indexedDB.databases().then(databases => {
                    databases.forEach(db => {
                        indexedDB.deleteDatabase(db.name);
                    });
                });
            }
            
            // 4. Limpiar cualquier variable global
            if (window.authClient) {
                window.authClient = null;
            }
            if (window.chatClient) {
                window.chatClient = null;
            }
            
            console.log('✅ Limpieza completa terminada');
            
            // 5. Redirigir después de limpiar
            setTimeout(() => {
                window.location.href = '/practicas/chat-frontend/public/index.php';
            }, 2000);
        }

        // Ejecutar limpieza inmediatamente
        clearEverything();
    </script>
</body>
</html>