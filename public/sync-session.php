<?php
// public/sync-session.php - Sincronizar localStorage con PHP Session
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['token']) || !isset($input['user'])) {
    echo json_encode(['success' => false, 'error' => 'Token and user data required']);
    exit;
}

$token = $input['token'];
$userData = $input['user'];

try {
    // Validar token con auth-service
    $context = stream_context_create([
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
    
    $validateUrl = 'http://187.33.158.246:8080/auth/validate-token';
    $result = @file_get_contents($validateUrl, false, $context);
    
    if ($result === false) {
        echo json_encode(['success' => false, 'error' => 'Cannot connect to auth service']);
        exit;
    }
    
    $response = json_decode($result, true);
    
    if ($response && isset($response['success']) && $response['success']) {
        // Token válido, guardar en sesión PHP
        $_SESSION['pToken'] = $token;
        $_SESSION['user'] = json_encode($userData);
        
        echo json_encode([
            'success' => true,
            'message' => 'Session synchronized successfully',
            'session_id' => session_id(),
            'user' => $userData
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid token',
            'details' => $response
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Validation error: ' . $e->getMessage()
    ]);
}
?>