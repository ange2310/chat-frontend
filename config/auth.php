<?php
// config/auth.php - CORREGIDO SIN REDIRECCIONES PROBLEMÁTICAS

class AuthHelper {
    private $authServiceURL = 'http://localhost:3010/auth';
    
    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated() {
        return isset($_SESSION['pToken']) && !empty($_SESSION['pToken']);
    }
    
    /**
     * Obtener token del usuario actual
     */
    public function getToken() {
        return $_SESSION['pToken'] ?? null;
    }
    
    /**
     * Obtener datos del usuario actual
     */
    public function getUser() {
        $userData = $_SESSION['user'] ?? null;
        if ($userData && is_string($userData)) {
            return json_decode($userData, true);
        }
        return $userData;
    }
    
    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function hasRole($role) {
        $user = $this->getUser();
        if (!$user) return false;
        
        $userRole = $user['role']['name'] ?? $user['role'] ?? null;
        
        // Manejar roles numéricos
        if (is_numeric($userRole)) {
            $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
            $userRole = $roleMap[$userRole] ?? null;
        }
        
        return $userRole === $role;
    }
    
    /**
     * Verificar si el usuario tiene acceso de staff
     */
    public function isStaff() {
        return $this->hasRole('admin') || $this->hasRole('supervisor') || $this->hasRole('agent');
    }
    
    /**
     * Logout (limpiar sesión)
     */
    public function logout() {
        session_destroy();
        session_unset();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Obtener headers para requests
     */
    public function getAuthHeaders() {
        $token = $this->getToken();
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ];
    }
}

// Función helper global
function auth() {
    return new AuthHelper();
}

// FUNCIONES DE PROTECCIÓN SIMPLIFICADAS Y CORREGIDAS

/**
 * Proteger página requiriendo autenticación - SIN REDIRECCIONES AUTOMÁTICAS
 */
function requireAuth() {
    $auth = auth();
    
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'No autenticado',
            'redirect' => '/practicas/chat-frontend/public/index.php'
        ]);
        exit;
    }
    
    return true;
}

/**
 * Proteger página de staff - VERSIÓN CORREGIDA SIN BUCLES
 */
function protectStaffPage() {
    $auth = auth();
    
    // Debug logging
    error_log("[AUTH-DEBUG] protectStaffPage() iniciado");
    error_log("[AUTH-DEBUG] isAuthenticated: " . ($auth->isAuthenticated() ? 'true' : 'false'));
    
    if (!$auth->isAuthenticated()) {
        error_log("[AUTH-DEBUG] No autenticado, redirigiendo a index.php");
        header("Location: /practicas/chat-frontend/public/index.php?error=not_authenticated");
        exit;
    }
    
    if (!$auth->isStaff()) {
        error_log("[AUTH-DEBUG] No es staff, redirigiendo a index.php");
        header("Location: /practicas/chat-frontend/public/index.php?error=not_staff");
        exit;
    }
    
    error_log("[AUTH-DEBUG] Staff autenticado correctamente");
    return true;
}

/**
 * Proteger página requiriendo rol específico
 */
function requireRole($role) {
    $auth = auth();
    
    if (!$auth->isAuthenticated()) {
        header("Location: /practicas/chat-frontend/public/index.php?error=not_authenticated");
        exit;
    }
    
    if (!$auth->hasRole($role)) {
        header("Location: /practicas/chat-frontend/public/index.php?error=insufficient_permissions");
        exit;
    }
    
    return true;
}

// Funciones de debug
function debugAuth($message) {
    if (defined('APP_ENV') && APP_ENV === 'development') {
        error_log("[AUTH-DEBUG] $message");
    }
}

function getAuthDebugInfo() {
    $auth = auth();
    return [
        'is_authenticated' => $auth->isAuthenticated(),
        'is_staff' => $auth->isStaff(),
        'user' => $auth->getUser(),
        'token_exists' => !!$auth->getToken(),
        'session_id' => session_id(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

?>