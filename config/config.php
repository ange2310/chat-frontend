<?php
// config/config.php - Configuración completa con URLs de nginx
define('APP_NAME', 'Sistema de Chat Médico');
define('APP_VERSION', '2.1.0');
define('APP_ENV', 'development');

// ===============================
// URLs DE SERVICIOS BACKEND - A TRAVÉS DE NGINX
// ===============================
// ✅ TODAS LAS URLs VAN A TRAVÉS DE NGINX EN EL PUERTO 8080
define('AUTH_SERVICE_URL', 'http://localhost:3010/auth');
define('CHAT_SERVICE_URL', 'http://localhost:3011/chats');
define('CHAT_WS_URL', 'ws://187.33.158.246:8080/socket.io');  // ← WebSocket a través de nginx
define('ADMIN_SERVICE_URL', 'http://187.33.158.246:8080/admin');
define('SUPERVISOR_SERVICE_URL', 'http://187.33.158.246:8080/supervisor');

// URLs auxiliares
define('AUTH_HEALTH_URL', 'http://localhost:3010/health');
define('CHAT_HEALTH_URL', 'http://187.33.158.246:8080/chat/health');

// ===============================
// CONFIGURACIÓN DE SESIÓN
// ===============================
ini_set('session.cookie_lifetime', 86400); // 24 horas
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

// ===============================
// CONFIGURACIÓN DE ERRORES
// ===============================
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ===============================
// CONFIGURACIÓN DE TIMEZONE
// ===============================
date_default_timezone_set('America/Bogota');

// ===============================
// CONFIGURACIÓN DE TIMEOUTS
// ===============================
define('HTTP_TIMEOUT', 30); // segundos
define('AUTH_TIMEOUT', 10);
define('CHAT_TIMEOUT', 15);

// ===============================
// FUNCIONES HELPER PRINCIPALES
// ===============================

/**
 * Obtener URL de servicio específico
 */
function getServiceURL($service) {
    $urls = [
        'auth' => AUTH_SERVICE_URL,
        'chat' => CHAT_SERVICE_URL,
        'admin' => ADMIN_SERVICE_URL,
        'supervisor' => SUPERVISOR_SERVICE_URL,
        'auth_health' => AUTH_HEALTH_URL,
        'chat_health' => CHAT_HEALTH_URL
    ];
    
    return $urls[$service] ?? false;
}

/**
 * Incluir componente de manera segura
 */
function includeComponent($component) {
    $file = __DIR__ . "/../public/components/{$component}.php";
    if (file_exists($file)) {
        include $file;
    } else {
        if (APP_ENV === 'development') {
            echo "<!-- Component {$component} not found at {$file} -->";
        }
    }
}

/**
 * Redirección segura
 */
function redirectTo($url) {
    // Sanitizar URL
    $url = filter_var($url, FILTER_SANITIZE_URL);
    header("Location: $url");
    exit;
}

/**
 * Respuesta JSON estandarizada
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log de debug mejorado
 */
function debugLog($message, $data = null, $level = 'INFO') {
    if (APP_ENV !== 'development') return;
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] CHAT-FRONTEND: {$message}";
    
    if ($data !== null) {
        $logMessage .= " " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    error_log($logMessage);
    
    // También mostrar en consola del navegador en desarrollo
    if (isset($_GET['debug']) || APP_ENV === 'development') {
        echo "<script>console.log(" . json_encode($logMessage) . ");</script>";
    }
}

// ===============================
// FUNCIONES DE CONECTIVIDAD (ACTUALIZADAS)
// ===============================

/**
 * Verificar conectividad con auth-service a través de nginx
 */
function checkAuthServiceConnection() {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: Chat-Frontend/2.1.0'
                ]
            ]
        ]);
        
        $result = @file_get_contents(AUTH_HEALTH_URL, false, $context);
        
        if ($result === false) {
            debugLog("No se puede conectar con auth-service via nginx", ['url' => AUTH_HEALTH_URL], 'ERROR');
            return false;
        }
        
        $health = json_decode($result, true);
        if ($health && isset($health['status']) && $health['status'] === 'OK') {
            debugLog("Conexión con auth-service via nginx exitosa", [
                'version' => $health['version'] ?? 'unknown',
                'uptime' => $health['uptime'] ?? 0
            ]);
            return true;
        }
        
        debugLog("Auth-service responde pero con estado no OK", $health, 'WARN');
        return false;
        
    } catch (Exception $e) {
        debugLog("Excepción verificando auth-service", ['error' => $e->getMessage()], 'ERROR');
        return false;
    }
}

/**
 * Verificar conectividad con chat-service a través de nginx
 */
function checkChatServiceConnection() {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: Chat-Frontend/2.1.0'
                ]
            ]
        ]);
        
        $result = @file_get_contents(CHAT_HEALTH_URL, false, $context);
        
        if ($result === false) {
            debugLog("No se puede conectar con chat-service via nginx", ['url' => CHAT_HEALTH_URL], 'ERROR');
            return false;
        }
        
        $health = json_decode($result, true);
        if ($health && isset($health['status']) && $health['status'] === 'OK') {
            debugLog("Conexión con chat-service via nginx exitosa", [
                'version' => $health['version'] ?? 'unknown'
            ]);
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        debugLog("Excepción verificando chat-service", ['error' => $e->getMessage()], 'ERROR');
        return false;
    }
}

// ===============================
// FUNCIONES DE AUTENTICACIÓN (ACTUALIZADAS)
// ===============================

/**
 * Validar token con auth-service a través de nginx
 */
function validateTokenWithService($token) {
    if (!$token || trim($token) === '') {
        debugLog("Token vacío para validación", null, 'WARN');
        return false;
    }
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => AUTH_TIMEOUT,
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: Chat-Frontend/2.1.0'
                ],
                'content' => json_encode(['token' => $token])
            ]
        ]);
        
        $validateUrl = AUTH_SERVICE_URL . '/validate-token';
        $result = @file_get_contents($validateUrl, false, $context);
        
        if ($result === false) {
            debugLog("Error validando token con auth-service via nginx", [
                'url' => $validateUrl,
                'token_preview' => substr($token, 0, 10) . '...'
            ], 'ERROR');
            return false;
        }
        
        $response = json_decode($result, true);
        if ($response && isset($response['success']) && $response['success']) {
            debugLog("Token validado exitosamente via nginx", [
                'user_id' => $response['data']['user']['id'] ?? 'unknown'
            ]);
            return $response['data']['user'] ?? true;
        }
        
        debugLog("Token inválido según auth-service", [
            'response' => $response
        ], 'WARN');
        return false;
        
    } catch (Exception $e) {
        debugLog("Excepción validando token", ['error' => $e->getMessage()], 'ERROR');
        return false;
    }
}

/**
 * Hacer login con auth-service a través de nginx
 */
function loginWithService($email, $password, $remember = false) {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => AUTH_TIMEOUT,
                'method' => 'POST',
                'header' => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: Chat-Frontend/2.1.0'
                ],
                'content' => json_encode([
                    'email' => $email,
                    'password' => $password,
                    'remember' => $remember
                ])
            ]
        ]);
        
        $loginUrl = AUTH_SERVICE_URL . '/login';
        $result = @file_get_contents($loginUrl, false, $context);
        
        if ($result === false) {
            debugLog("Error en login con auth-service via nginx", [
                'email' => $email,
                'url' => $loginUrl
            ], 'ERROR');
            return [
                'success' => false, 
                'error' => 'Error de conexión con el servidor de autenticación'
            ];
        }
        
        $response = json_decode($result, true);
        if ($response && isset($response['success'])) {
            debugLog("Respuesta de login recibida via nginx", [
                'success' => $response['success'],
                'user_email' => $response['data']['user']['email'] ?? 'unknown',
                'has_token' => isset($response['data']['access_token'])
            ]);
            return $response;
        }
        
        debugLog("Respuesta inválida de login", ['response' => $response], 'ERROR');
        return [
            'success' => false, 
            'error' => 'Respuesta inválida del servidor'
        ];
        
    } catch (Exception $e) {
        debugLog("Excepción en login", ['error' => $e->getMessage()], 'ERROR');
        return [
            'success' => false, 
            'error' => 'Error interno: ' . $e->getMessage()
        ];
    }
}

/**
 * Registrar usuario con auth-service a través de nginx
 */
function registerWithService($userData) {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => AUTH_TIMEOUT,
                'method' => 'POST',
                'header' => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: Chat-Frontend/2.1.0'
                ],
                'content' => json_encode($userData)
            ]
        ]);
        
        $registerUrl = AUTH_SERVICE_URL . '/register';
        $result = @file_get_contents($registerUrl, false, $context);
        
        if ($result === false) {
            debugLog("Error en registro con auth-service via nginx", [
                'email' => $userData['email'] ?? 'unknown',
                'url' => $registerUrl
            ], 'ERROR');
            return [
                'success' => false, 
                'error' => 'Error de conexión con el servidor de autenticación'
            ];
        }
        
        $response = json_decode($result, true);
        if ($response && isset($response['success'])) {
            debugLog("Respuesta de registro recibida via nginx", [
                'success' => $response['success'],
                'user_email' => $response['data']['user']['email'] ?? 'unknown'
            ]);
            return $response;
        }
        
        debugLog("Respuesta inválida de registro", ['response' => $response], 'ERROR');
        return [
            'success' => false, 
            'error' => 'Respuesta inválida del servidor'
        ];
        
    } catch (Exception $e) {
        debugLog("Excepción en registro", ['error' => $e->getMessage()], 'ERROR');
        return [
            'success' => false, 
            'error' => 'Error interno: ' . $e->getMessage()
        ];
    }
}

// ===============================
// FUNCIONES DE SALAS (ACTUALIZADAS)
// ===============================

/**
 * Obtener salas disponibles desde auth-service a través de nginx
 */
function getAvailableRoomsFromService($token = null) {
    if (!$token || trim($token) === '') {
        debugLog("Token requerido para obtener salas", null, 'WARN');
        return [];
    }
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => AUTH_TIMEOUT,
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: Chat-Frontend/2.1.0'
                ]
            ]
        ]);
        
        $roomsUrl = AUTH_SERVICE_URL . '/rooms/available';
        $result = @file_get_contents($roomsUrl, false, $context);
        
        if ($result === false) {
            debugLog("Error obteniendo salas desde auth-service via nginx", [
                'url' => $roomsUrl,
                'token_preview' => substr($token, 0, 10) . '...'
            ], 'ERROR');
            return [];
        }
        
        $response = json_decode($result, true);
        if ($response && isset($response['success']) && $response['success']) {
            $rooms = $response['data']['rooms'] ?? [];
            debugLog("Salas obtenidas desde auth-service via nginx", [
                'count' => count($rooms),
                'summary' => $response['data']['summary'] ?? []
            ]);
            return $rooms;
        }
        
        debugLog("Respuesta inválida de auth-service para salas", [
            'response' => $response
        ], 'ERROR');
        return [];
        
    } catch (Exception $e) {
        debugLog("Excepción obteniendo salas", ['error' => $e->getMessage()], 'ERROR');
        return [];
    }
}

/**
 * Seleccionar sala y obtener pToken a través de nginx
 */
function selectRoomWithService($token, $roomId, $userData = []) {
    if (!$token || !$roomId) {
        debugLog("Token y roomId requeridos para seleccionar sala", [
            'has_token' => !!$token,
            'room_id' => $roomId
        ], 'WARN');
        return ['success' => false, 'error' => 'Parámetros incompletos'];
    }
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => AUTH_TIMEOUT,
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: Chat-Frontend/2.1.0'
                ],
                'content' => json_encode([
                    'user_data' => array_merge([
                        'source' => 'php_frontend',
                        'selected_at' => date('c')
                    ], $userData)
                ])
            ]
        ]);
        
        $selectUrl = AUTH_SERVICE_URL . "/rooms/{$roomId}/select";
        $result = @file_get_contents($selectUrl, false, $context);
        
        if ($result === false) {
            debugLog("Error seleccionando sala via nginx", [
                'url' => $selectUrl,
                'room_id' => $roomId
            ], 'ERROR');
            return [
                'success' => false, 
                'error' => 'Error de conexión al seleccionar sala'
            ];
        }
        
        $response = json_decode($result, true);
        if ($response && isset($response['success'])) {
            debugLog("Respuesta de selección de sala via nginx", [
                'success' => $response['success'],
                'room_id' => $roomId,
                'has_ptoken' => isset($response['data']['ptoken'])
            ]);
            return $response;
        }
        
        debugLog("Respuesta inválida al seleccionar sala", [
            'response' => $response
        ], 'ERROR');
        return [
            'success' => false, 
            'error' => 'Respuesta inválida del servidor'
        ];
        
    } catch (Exception $e) {
        debugLog("Excepción seleccionando sala", [
            'error' => $e->getMessage(),
            'room_id' => $roomId
        ], 'ERROR');
        return [
            'success' => false, 
            'error' => 'Error interno: ' . $e->getMessage()
        ];
    }
}

// ===============================
// FUNCIONES DE UTILIDAD
// ===============================

/**
 * Sanitizar entrada de texto
 */
function sanitizeInput($input) {
    if (is_string($input)) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    return $input;
}

/**
 * Validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar UUID v4
 */
function isValidUUID($uuid) {
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
}

/**
 * Obtener IP del cliente
 */
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Obtener información del navegador
 */
function getBrowserInfo() {
    return [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
        'ip' => getClientIP(),
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct'
    ];
}

// ===============================
// INICIALIZACIÓN Y VERIFICACIONES
// ===============================

// Log de inicio del sistema
debugLog("Sistema de chat frontend iniciado con nginx proxy", [
    'version' => APP_VERSION,
    'environment' => APP_ENV,
    'php_version' => PHP_VERSION,
    'auth_service' => AUTH_SERVICE_URL,
    'chat_service' => CHAT_SERVICE_URL,
    'websocket_url' => CHAT_WS_URL,
    'timezone' => date_default_timezone_get(),
    'timestamp' => date('c')
]);

// Verificar conectividad con servicios en desarrollo
if (APP_ENV === 'development') {
    $authConnected = checkAuthServiceConnection();
    $chatConnected = checkChatServiceConnection();
    
    debugLog("Estado de conectividad inicial via nginx", [
        'auth_service' => $authConnected ? 'CONNECTED' : 'DISCONNECTED',
        'chat_service' => $chatConnected ? 'CONNECTED' : 'DISCONNECTED'
    ], $authConnected && $chatConnected ? 'INFO' : 'WARN');
    
    // Mostrar información de debug en el navegador
    if (isset($_GET['debug'])) {
        echo "<script>
            console.log('%c🚀 CHAT FRONTEND CONFIG DEBUG (NGINX)', 'background: #007acc; color: white; padding: 2px 5px; border-radius: 3px;');
            console.log('Auth Service (nginx):', '" . AUTH_SERVICE_URL . "', " . ($authConnected ? "'✅ Connected'" : "'❌ Disconnected'") . ");
            console.log('Chat Service (nginx):', '" . CHAT_SERVICE_URL . "', " . ($chatConnected ? "'✅ Connected'" : "'❌ Disconnected'") . ");
            console.log('WebSocket (nginx):', '" . CHAT_WS_URL . "');
            console.log('Environment:', '" . APP_ENV . "');
            console.log('Version:', '" . APP_VERSION . "');
        </script>";
    }
}

// ===============================
// CONSTANTES ADICIONALES
// ===============================

// Roles de usuario
define('ROLE_PATIENT', 1);
define('ROLE_AGENT', 2);
define('ROLE_SUPERVISOR', 3);
define('ROLE_ADMIN', 4);

// Estados de chat
define('CHAT_STATUS_WAITING', 'waiting');
define('CHAT_STATUS_ACTIVE', 'active');
define('CHAT_STATUS_ENDED', 'ended');
define('CHAT_STATUS_TRANSFERRED', 'transferred');

// Tipos de sala
define('ROOM_TYPE_GENERAL', 'general');
define('ROOM_TYPE_MEDICAL', 'medical');
define('ROOM_TYPE_SUPPORT', 'support');
define('ROOM_TYPE_EMERGENCY', 'emergency');

debugLog("Configuración completa cargada exitosamente con nginx proxy", [
    'constants_defined' => [
        'roles' => ['PATIENT', 'AGENT', 'SUPERVISOR', 'ADMIN'],
        'chat_statuses' => ['WAITING', 'ACTIVE', 'ENDED', 'TRANSFERRED'],
        'room_types' => ['GENERAL', 'MEDICAL', 'SUPPORT', 'EMERGENCY']
    ]
]);

?>