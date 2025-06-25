<?php
// Configuración general
define('APP_NAME', 'Sistema de Chat');
define('APP_VERSION', '1.0.0');

// URLs de servicios backend (A TRAVÉS DE API GATEWAY)
define('AUTH_SERVICE_URL', 'http://187.33.158.246/auth');
define('CHAT_SERVICE_URL', 'ws://187.33.158.246/ws');
define('ADMIN_SERVICE_URL', 'http://187.33.158.246/admin');
define('SUPERVISOR_SERVICE_URL', 'http://187.33.158.246/supervisor');

// Configuración de base de datos (si necesitas)
define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_system_db');
define('DB_USER', 'postgres');
define('DB_PASS', 'admin123');

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
    $file = __DIR__ . "/../components/{$component}.php";
    if (file_exists($file)) {
        include $file;
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
?>
define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_system');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');

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
    $file = __DIR__ . "/../components/{$component}.php";
    if (file_exists($file)) {
        include $file;
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
?>