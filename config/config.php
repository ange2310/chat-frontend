<?php

define('APP_NAME', 'Sistema de Chat Médico');
define('APP_VERSION', '2.1.0');
define('APP_ENV', 'development');

define('AUTH_SERVICE_URL', 'http://187.33.158.246/auth');
define('CHAT_SERVICE_URL', 'http://187.33.158.246/chat');
define('CHAT_WS_URL', 'ws://187.33.158.246/chat/socket.io');
define('ADMIN_SERVICE_URL', 'http://187.33.158.246/admin');
define('SUPERVISOR_SERVICE_URL', 'http://187.33.158.246/supervisor');

define('AUTH_HEALTH_URL', 'http://187.33.158.246/auth/health');
define('CHAT_HEALTH_URL', 'http://187.33.158.246/chat/health');

ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

date_default_timezone_set('America/Bogota');

define('HTTP_TIMEOUT', 30);
define('AUTH_TIMEOUT', 10);
define('CHAT_TIMEOUT', 15);

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

function redirectTo($url) {
    $url = filter_var($url, FILTER_SANITIZE_URL);
    header("Location: $url");
    exit;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function debugLog($message, $data = null, $level = 'INFO') {
    if (APP_ENV !== 'development') return;
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] CHAT-FRONTEND: {$message}";
    
    if ($data !== null) {
        $logMessage .= " " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    error_log($logMessage);
    
    if (isset($_GET['debug']) || APP_ENV === 'development') {
        echo "<script>console.log(" . json_encode($logMessage) . ");</script>";
    }
}

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
            debugLog("No se puede conectar con auth-service", ['url' => AUTH_HEALTH_URL], 'ERROR');
            return false;
        }
        
        $health = json_decode($result, true);
        if ($health && isset($health['status']) && $health['status'] === 'OK') {
            debugLog("Conexión con auth-service exitosa", [
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
            debugLog("No se puede conectar con chat-service", ['url' => CHAT_HEALTH_URL], 'ERROR');
            return false;
        }
        
        $health = json_decode($result, true);
        if ($health && isset($health['status']) && $health['status'] === 'OK') {
            debugLog("Conexión con chat-service exitosa", [
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
            debugLog("Error validando token con auth-service", [
                'url' => $validateUrl,
                'token_preview' => substr($token, 0, 10) . '...'
            ], 'ERROR');
            return false;
        }
        
        $response = json_decode($result, true);
        if ($response && isset($response['success']) && $response['success']) {
            debugLog("Token validado exitosamente", [
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
            debugLog("Error en login con auth-service", [
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
            debugLog("Respuesta de login recibida", [
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
            debugLog("Error en registro con auth-service", [
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
            debugLog("Respuesta de registro recibida", [
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
            debugLog("Error obteniendo salas desde auth-service", [
                'url' => $roomsUrl,
                'token_preview' => substr($token, 0, 10) . '...'
            ], 'ERROR');
            return [];
        }
        
        $response = json_decode($result, true);
        if ($response && isset($response['success']) && $response['success']) {
            $rooms = $response['data']['rooms'] ?? [];
            debugLog("Salas obtenidas desde auth-service", [
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
            debugLog("Error seleccionando sala", [
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
            debugLog("Respuesta de selección de sala", [
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

function sanitizeInput($input) {
    if (is_string($input)) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    return $input;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidUUID($uuid) {
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
}

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

function getBrowserInfo() {
    return [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
        'ip' => getClientIP(),
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct'
    ];
}

debugLog("Sistema de chat frontend iniciado", [
    'version' => APP_VERSION,
    'environment' => APP_ENV,
    'php_version' => PHP_VERSION,
    'auth_service' => AUTH_SERVICE_URL,
    'chat_service' => CHAT_SERVICE_URL,
    'websocket_url' => CHAT_WS_URL,
    'timezone' => date_default_timezone_get(),
    'timestamp' => date('c')
]);

if (APP_ENV === 'development') {
    $authConnected = checkAuthServiceConnection();
    $chatConnected = checkChatServiceConnection();
    
    debugLog("Estado de conectividad inicial", [
        'auth_service' => $authConnected ? 'CONNECTED' : 'DISCONNECTED',
        'chat_service' => $chatConnected ? 'CONNECTED' : 'DISCONNECTED'
    ], $authConnected && $chatConnected ? 'INFO' : 'WARN');
    
    if (isset($_GET['debug'])) {
        echo "<script>
            console.log('%c🚀 CHAT FRONTEND CONFIG DEBUG', 'background: #007acc; color: white; padding: 2px 5px; border-radius: 3px;');
            console.log('Auth Service:', '" . AUTH_SERVICE_URL . "', " . ($authConnected ? "'✅ Connected'" : "'❌ Disconnected'") . ");
            console.log('Chat Service:', '" . CHAT_SERVICE_URL . "', " . ($chatConnected ? "'✅ Connected'" : "'❌ Disconnected'") . ");
            console.log('WebSocket:', '" . CHAT_WS_URL . "');
            console.log('Environment:', '" . APP_ENV . "');
            console.log('Version:', '" . APP_VERSION . "');
        </script>";
    }
}

define('ROLE_PATIENT', 1);
define('ROLE_AGENT', 2);
define('ROLE_SUPERVISOR', 3);
define('ROLE_ADMIN', 4);

define('CHAT_STATUS_WAITING', 'waiting');
define('CHAT_STATUS_ACTIVE', 'active');
define('CHAT_STATUS_ENDED', 'ended');
define('CHAT_STATUS_TRANSFERRED', 'transferred');

define('ROOM_TYPE_GENERAL', 'general');
define('ROOM_TYPE_MEDICAL', 'medical');
define('ROOM_TYPE_SUPPORT', 'support');
define('ROOM_TYPE_EMERGENCY', 'emergency');

debugLog("Configuración completa cargada exitosamente", [
    'constants_defined' => [
        'roles' => ['PATIENT', 'AGENT', 'SUPERVISOR', 'ADMIN'],
        'chat_statuses' => ['WAITING', 'ACTIVE', 'ENDED', 'TRANSFERRED'],
        'room_types' => ['GENERAL', 'MEDICAL', 'SUPPORT', 'EMERGENCY']
    ]
]);

?>