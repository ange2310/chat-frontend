<?php
// config/config-helper.php - Helper para configuración y debugging
require_once __DIR__ . '/config.php';

class ConfigHelper {
    
    /**
     * Verificar el estado completo del sistema
     */
    public static function checkSystemHealth() {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => APP_ENV,
            'version' => APP_VERSION,
            'checks' => []
        ];
        
        // 1. Verificar servicios backend
        $results['checks']['auth_service'] = self::checkAuthService();
        $results['checks']['chat_service'] = self::checkChatService();
        
        // 2. Verificar configuración PHP
        $results['checks']['php_config'] = self::checkPHPConfig();
        
        // 3. Verificar archivos frontend
        $results['checks']['frontend_files'] = self::checkFrontendFiles();
        
        // 4. Verificar permisos
        $results['checks']['permissions'] = self::checkPermissions();
        
        // 5. Verificar conectividad de red
        $results['checks']['network'] = self::checkNetworkConnectivity();
        
        return $results;
    }
    
    /**
     * Verificar auth-service
     */
    private static function checkAuthService() {
        $check = [
            'name' => 'Auth Service',
            'status' => 'unknown',
            'details' => []
        ];
        
        try {
            $url = AUTH_SERVICE_URL . '/../health';
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET',
                    'header' => ['Accept: application/json']
                ]
            ]);
            
            $result = @file_get_contents($url, false, $context);
            
            if ($result === false) {
                $check['status'] = 'error';
                $check['details']['error'] = 'No se puede conectar con auth-service';
                $check['details']['url'] = $url;
            } else {
                $data = json_decode($result, true);
                if ($data && isset($data['status']) && $data['status'] === 'OK') {
                    $check['status'] = 'success';
                    $check['details']['version'] = $data['version'] ?? 'unknown';
                    $check['details']['service'] = $data['service'] ?? 'auth-service';
                    $check['details']['uptime'] = $data['uptime'] ?? 'unknown';
                } else {
                    $check['status'] = 'warning';
                    $check['details']['response'] = $data;
                }
            }
        } catch (Exception $e) {
            $check['status'] = 'error';
            $check['details']['exception'] = $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Verificar chat-service
     */
    private static function checkChatService() {
        $check = [
            'name' => 'Chat Service',
            'status' => 'unknown',
            'details' => []
        ];
        
        try {
            $url = CHAT_SERVICE_URL . '/health';
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET',
                    'header' => ['Accept: application/json']
                ]
            ]);
            
            $result = @file_get_contents($url, false, $context);
            
            if ($result === false) {
                $check['status'] = 'warning';
                $check['details']['note'] = 'Chat service no está disponible (opcional)';
                $check['details']['url'] = $url;
            } else {
                $data = json_decode($result, true);
                if ($data && isset($data['status']) && $data['status'] === 'OK') {
                    $check['status'] = 'success';
                    $check['details']['version'] = $data['version'] ?? 'unknown';
                } else {
                    $check['status'] = 'warning';
                    $check['details']['response'] = $data;
                }
            }
        } catch (Exception $e) {
            $check['status'] = 'warning';
            $check['details']['exception'] = $e->getMessage();
        }
        
        return $check;
    }
    
    /**
     * Verificar configuración PHP
     */
    private static function checkPHPConfig() {
        $check = [
            'name' => 'PHP Configuration',
            'status' => 'success',
            'details' => []
        ];
        
        // Verificar versión PHP
        $phpVersion = PHP_VERSION;
        $check['details']['php_version'] = $phpVersion;
        
        if (version_compare($phpVersion, '7.4.0', '<')) {
            $check['status'] = 'warning';
            $check['details']['php_warning'] = 'PHP version is old, consider upgrading';
        }
        
        // Verificar extensiones
        $requiredExtensions = ['curl', 'json', 'mbstring'];
        $missingExtensions = [];
        
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        
        if (!empty($missingExtensions)) {
            $check['status'] = 'error';
            $check['details']['missing_extensions'] = $missingExtensions;
        }
        
        // Verificar configuración de sesión
        $check['details']['session_config'] = [
            'cookie_lifetime' => ini_get('session.cookie_lifetime'),
            'gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
            'cookie_secure' => ini_get('session.cookie_secure'),
            'cookie_httponly' => ini_get('session.cookie_httponly')
        ];
        
        return $check;
    }
    
    /**
     * Verificar archivos frontend
     */
    private static function checkFrontendFiles() {
        $check = [
            'name' => 'Frontend Files',
            'status' => 'success',
            'details' => []
        ];
        
        $requiredFiles = [
            'public/index.php' => 'Main login page',
            'public/staff.php' => 'Staff dashboard',
            'public/preauth.php' => 'Patient portal',
            'public/assets/css/main.css' => 'Main stylesheet',
            'public/assets/js/auth-client.js' => 'Auth client library',
            'public/assets/js/chat-client.js' => 'Chat client library'
        ];
        
        $missingFiles = [];
        $fileInfo = [];
        
        foreach ($requiredFiles as $file => $description) {
            $fullPath = __DIR__ . '/../' . $file;
            if (file_exists($fullPath)) {
                $fileInfo[$file] = [
                    'exists' => true,
                    'size' => filesize($fullPath),
                    'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    'description' => $description
                ];
            } else {
                $missingFiles[] = $file;
                $fileInfo[$file] = [
                    'exists' => false,
                    'description' => $description
                ];
            }
        }
        
        if (!empty($missingFiles)) {
            $check['status'] = 'error';
            $check['details']['missing_files'] = $missingFiles;
        }
        
        $check['details']['files'] = $fileInfo;
        
        return $check;
    }
    
    /**
     * Verificar permisos de archivos
     */
    private static function checkPermissions() {
        $check = [
            'name' => 'File Permissions',
            'status' => 'success',
            'details' => []
        ];
        
        $checkPaths = [
            'public/' => 'readable',
            'public/assets/' => 'readable',
            'config/' => 'readable'
        ];
        
        $permissionIssues = [];
        
        foreach ($checkPaths as $path => $requiredPerm) {
            $fullPath = __DIR__ . '/../' . $path;
            if (file_exists($fullPath)) {
                $perms = fileperms($fullPath);
                $check['details'][$path] = [
                    'permissions' => substr(sprintf('%o', $perms), -4),
                    'readable' => is_readable($fullPath),
                    'writable' => is_writable($fullPath)
                ];
                
                if ($requiredPerm === 'readable' && !is_readable($fullPath)) {
                    $permissionIssues[] = "$path is not readable";
                }
            } else {
                $permissionIssues[] = "$path does not exist";
            }
        }
        
        if (!empty($permissionIssues)) {
            $check['status'] = 'warning';
            $check['details']['issues'] = $permissionIssues;
        }
        
        return $check;
    }
    
    /**
     * Verificar conectividad de red
     */
    private static function checkNetworkConnectivity() {
        $check = [
            'name' => 'Network Connectivity',
            'status' => 'success',
            'details' => []
        ];
        
        // Test básico de conectividad
        $testUrls = [
            'google_dns' => '8.8.8.8',
            'auth_service_host' => '187.33.158.246'
        ];
        
        foreach ($testUrls as $name => $host) {
            $connection = @fsockopen($host, 80, $errno, $errstr, 5);
            if (is_resource($connection)) {
                $check['details'][$name] = 'reachable';
                fclose($connection);
            } else {
                $check['details'][$name] = "unreachable ($errno: $errstr)";
                $check['status'] = 'warning';
            }
        }
        
        // Verificar resolución DNS
        $dnsTest = gethostbyname('google.com');
        $check['details']['dns_resolution'] = ($dnsTest !== 'google.com') ? 'working' : 'failed';
        
        if ($check['details']['dns_resolution'] === 'failed') {
            $check['status'] = 'error';
        }
        
        return $check;
    }
    
    /**
     * Test de login completo
     */
    public static function testLogin($email = 'admin@tpsalud.com', $password = 'Admin123') {
        $test = [
            'timestamp' => date('Y-m-d H:i:s'),
            'credentials' => ['email' => $email, 'password' => '***'],
            'steps' => []
        ];
        
        try {
            // Step 1: Preparar request
            $test['steps']['prepare'] = [
                'status' => 'success',
                'url' => AUTH_SERVICE_URL . '/login',
                'method' => 'POST'
            ];
            
            // Step 2: Enviar request
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ],
                    'content' => json_encode([
                        'email' => $email,
                        'password' => $password
                    ])
                ]
            ]);
            
            $result = @file_get_contents(AUTH_SERVICE_URL . '/login', false, $context);
            
            if ($result === false) {
                $test['steps']['request'] = [
                    'status' => 'error',
                    'error' => 'Failed to connect to auth service'
                ];
                return $test;
            }
            
            // Step 3: Analizar respuesta
            $response = json_decode($result, true);
            
            if (!$response) {
                $test['steps']['response'] = [
                    'status' => 'error',
                    'error' => 'Invalid JSON response',
                    'raw_response' => substr($result, 0, 200)
                ];
                return $test;
            }
            
            $test['steps']['response'] = [
                'status' => $response['success'] ? 'success' : 'error',
                'success' => $response['success'],
                'message' => $response['message'] ?? 'No message'
            ];
            
            // Step 4: Verificar token (si login exitoso)
            if ($response['success'] && isset($response['data']['access_token'])) {
                $token = $response['data']['access_token'];
                $test['steps']['token'] = [
                    'status' => 'success',
                    'token_length' => strlen($token),
                    'token_preview' => substr($token, 0, 20) . '...',
                    'user_info' => $response['data']['user'] ?? []
                ];
                
                // Step 5: Validar token
                $validateContext = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'method' => 'POST',
                        'header' => [
                            'Authorization: Bearer ' . $token,
                            'Content-Type: application/json',
                            'Accept: application/json'
                        ],
                        'content' => json_encode(['token' => $token])
                    ]
                ]);
                
                $validateResult = @file_get_contents(AUTH_SERVICE_URL . '/validate-token', false, $validateContext);
                
                if ($validateResult) {
                    $validateResponse = json_decode($validateResult, true);
                    $test['steps']['validate'] = [
                        'status' => $validateResponse['success'] ? 'success' : 'error',
                        'valid' => $validateResponse['success'],
                        'token_type' => $validateResponse['data']['token_type'] ?? 'unknown'
                    ];
                } else {
                    $test['steps']['validate'] = [
                        'status' => 'error',
                        'error' => 'Failed to validate token'
                    ];
                }
            } else {
                $test['steps']['token'] = [
                    'status' => 'error',
                    'error' => 'No token received'
                ];
            }
            
        } catch (Exception $e) {
            $test['steps']['exception'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
        
        return $test;
    }
    
    /**
     * Generar reporte completo del sistema
     */
    public static function generateSystemReport() {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'system_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'environment' => APP_ENV,
                'app_version' => APP_VERSION
            ],
            'health_check' => self::checkSystemHealth(),
            'login_test' => self::testLogin(),
            'configuration' => [
                'auth_service_url' => AUTH_SERVICE_URL,
                'chat_service_url' => CHAT_SERVICE_URL,
                'chat_ws_url' => CHAT_WS_URL,
                'session_timeout' => ini_get('session.gc_maxlifetime'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ]
        ];
        
        return $report;
    }
    
    /**
     * Generar página HTML del reporte
     */
    public static function generateReportHTML() {
        $report = self::generateSystemReport();
        
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 System Health Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .status-success { color: #10b981; }
        .status-error { color: #ef4444; }
        .status-warning { color: #f59e0b; }
        .status-unknown { color: #6b7280; }
        pre { background: #f9fafb; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">🔧 System Health Report</h1>
            <p class="text-gray-600 mb-6">Generated: ' . $report['generated_at'] . '</p>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-6">';
        
        // Health Checks
        $html .= '<div class="border rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-4">🏥 Health Checks</h2>';
        
        foreach ($report['health_check']['checks'] as $check) {
            $statusClass = 'status-' . $check['status'];
            $statusIcon = [
                'success' => '✅',
                'error' => '❌',
                'warning' => '⚠️',
                'unknown' => '❓'
            ][$check['status']] ?? '❓';
            
            $html .= '<div class="mb-3">
                        <div class="flex items-center gap-2">
                            <span>' . $statusIcon . '</span>
                            <span class="font-medium">' . $check['name'] . '</span>
                            <span class="' . $statusClass . '">' . strtoupper($check['status']) . '</span>
                        </div>
                        <pre class="text-xs mt-2">' . htmlspecialchars(json_encode($check['details'], JSON_PRETTY_PRINT)) . '</pre>
                      </div>';
        }
        
        $html .= '</div>';
        
        // Login Test
        $html .= '<div class="border rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-4">🔐 Login Test</h2>';
        
        foreach ($report['login_test']['steps'] as $stepName => $step) {
            $statusClass = 'status-' . $step['status'];
            $statusIcon = [
                'success' => '✅',
                'error' => '❌',
                'warning' => '⚠️'
            ][$step['status']] ?? '❓';
            
            $html .= '<div class="mb-3">
                        <div class="flex items-center gap-2">
                            <span>' . $statusIcon . '</span>
                            <span class="font-medium">' . ucfirst($stepName) . '</span>
                            <span class="' . $statusClass . '">' . strtoupper($step['status']) . '</span>
                        </div>
                        <pre class="text-xs mt-2">' . htmlspecialchars(json_encode($step, JSON_PRETTY_PRINT)) . '</pre>
                      </div>';
        }
        
        $html .= '</div>
                </div>
                
                <div class="space-y-6">';
        
        // Configuration
        $html .= '<div class="border rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-4">⚙️ Configuration</h2>
                    <pre>' . htmlspecialchars(json_encode($report['configuration'], JSON_PRETTY_PRINT)) . '</pre>
                  </div>';
        
        // System Info
        $html .= '<div class="border rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-4">💻 System Info</h2>
                    <pre>' . htmlspecialchars(json_encode($report['system_info'], JSON_PRETTY_PRINT)) . '</pre>
                  </div>
                </div>
            </div>
            
            <div class="mt-6">
                <h2 class="text-lg font-semibold mb-4">📊 Raw Report Data</h2>
                <pre>' . htmlspecialchars(json_encode($report, JSON_PRETTY_PRINT)) . '</pre>
            </div>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
}

// Si se llama directamente, mostrar reporte
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo ConfigHelper::generateReportHTML();
}