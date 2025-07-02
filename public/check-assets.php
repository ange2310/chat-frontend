<?php
// public/check-assets.php - Verificar assets específicos
header('Content-Type: application/json');

$assets_to_check = [
    'CSS' => [
        'public/assets/css/main.css',
        '/practicas/chat-frontend/public/assets/css/main.css'
    ],
    'JS' => [
        'public/assets/js/auth-client.js',
        'assets/js/auth-client.js',
        'public/assets/js/chat-client.js', 
        'assets/js/chat-client.js',
        'public/assets/js/staff-client.js',
        'assets/js/staff-client.js'
    ]
];

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ],
    'assets' => []
];

foreach ($assets_to_check as $type => $paths) {
    $results['assets'][$type] = [];
    
    foreach ($paths as $path) {
        $full_path = $path;
        
        // Si no es una ruta absoluta, intentar desde el directorio actual
        if (!str_starts_with($path, '/')) {
            $full_path = __DIR__ . '/' . $path;
        }
        
        $info = [
            'path' => $path,
            'full_path' => $full_path,
            'exists' => false,
            'readable' => false,
            'size' => 0,
            'content_preview' => '',
            'has_dashboard_reference' => false
        ];
        
        if (file_exists($full_path)) {
            $info['exists'] = true;
            $info['readable'] = is_readable($full_path);
            $info['size'] = filesize($full_path);
            
            if ($info['readable'] && $info['size'] > 0) {
                $content = file_get_contents($full_path);
                $info['content_preview'] = substr($content, 0, 200) . '...';
                
                // Buscar referencias a 'dashboard'
                if (stripos($content, 'dashboard') !== false) {
                    $info['has_dashboard_reference'] = true;
                    
                    // Encontrar las líneas específicas con 'dashboard'
                    $lines = explode("\n", $content);
                    $dashboard_lines = [];
                    
                    foreach ($lines as $line_num => $line) {
                        if (stripos($line, 'dashboard') !== false) {
                            $dashboard_lines[] = [
                                'line' => $line_num + 1,
                                'content' => trim($line)
                            ];
                        }
                    }
                    
                    $info['dashboard_lines'] = $dashboard_lines;
                }
            }
        }
        
        $results['assets'][$type][] = $info;
    }
}

// También verificar archivos de configuración que podrían tener redirecciones
$config_files = [
    __DIR__ . '/.htaccess',
    __DIR__ . '/../.htaccess',
    $_SERVER['DOCUMENT_ROOT'] . '/.htaccess'
];

$results['config_files'] = [];

foreach ($config_files as $config_file) {
    $config_info = [
        'path' => $config_file,
        'exists' => false,
        'content' => '',
        'has_dashboard_reference' => false
    ];
    
    if (file_exists($config_file)) {
        $config_info['exists'] = true;
        $content = file_get_contents($config_file);
        $config_info['content'] = $content;
        
        if (stripos($content, 'dashboard') !== false) {
            $config_info['has_dashboard_reference'] = true;
        }
    }
    
    $results['config_files'][] = $config_info;
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>