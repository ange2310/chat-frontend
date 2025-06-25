<?php
// Configuración general
define('APP_NAME', 'Sistema de Chat');
define('APP_VERSION', '1.0.0');

// URLs de servicios backend - TU SERVIDOR REAL
define('AUTH_SERVICE_URL', 'http://187.33.158.246/auth');
define('CHAT_SERVICE_URL', 'ws://187.33.158.246/ws');
define('ADMIN_SERVICE_URL', 'http://187.33.158.246/admin');
define('SUPERVISOR_SERVICE_URL', 'http://187.33.158.246/supervisor');

// Configuración de base de datos LOCAL (para desarrollo)
// NOTA: Para la demo, no necesitas BD local, solo frontend
define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_system_demo');
define('DB_USER', 'root');  // Usuario por defecto de XAMPP
define('DB_PASS', '');      // Sin contraseña por defecto en XAMPP

// Configuración de sesión
ini_set('session.cookie_lifetime', 86400); // 24 horas
ini_set('session.gc_maxlifetime', 86400);

// Funciones helper
function getServiceURL($service) {
    switch ($service) {
        case 'auth':
            return AUTH_SERVICE_URL;
        case 'chat':
            return CHAT_SERVICE_URL;
        case 'admin':
            return ADMIN_SERVICE_URL;
        case 'supervisor':
            return SUPERVISOR_SERVICE_URL;
        default:
            return false;
    }
}

function includeComponent($component) {
    $file = __DIR__ . "/../public/components/{$component}.php";
    if (file_exists($file)) {
        include $file;
    } else {
        echo "<!-- Component {$component} not found at {$file} -->";
    }
}

function redirectTo($url) {
    header("Location: $url");
    exit;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Función para debug
function debugLog($message, $data = null) {
    if (APP_ENV === 'development') {
        error_log("[CHAT-DEBUG] " . $message . " " . json_encode($data));
    }
}

// Configuración de entorno
define('APP_ENV', 'development');
?>