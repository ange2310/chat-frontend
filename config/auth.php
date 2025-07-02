<?php
// config/auth.php - Helper de autenticación CORREGIDO SIN redirecciones a dashboard

class AuthHelper {
    private $authServiceURL = 'http://187.33.158.246:8080/auth';
    
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
        return $_SESSION['user'] ?? null;
    }
    
    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function hasRole($role) {
        $user = $this->getUser();
        return $user && isset($user['role']) && $user['role'] === $role;
    }
    
    /**
     * Verificar si el usuario tiene acceso de staff
     */
    public function isStaff() {
        return $this->hasRole('admin') || $this->hasRole('supervisor') || $this->hasRole('agent');
    }
    
    /**
     * Redirigir si no está autenticado - CORREGIDO
     */
    public function requireAuth($redirectTo = '/practicas/chat-frontend/public/index.php') {
        if (!$this->isAuthenticated()) {
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Redirigir si no es staff - CORREGIDO
     */
    public function requireStaff($redirectTo = '/practicas/chat-frontend/public/index.php') {
        $this->requireAuth($redirectTo);
        if (!$this->isStaff()) {
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Redirigir si no tiene rol específico - CORREGIDO
     */
    public function requireRole($role, $redirectTo = '/practicas/chat-frontend/public/index.php') {
        $this->requireAuth($redirectTo);
        if (!$this->hasRole($role)) {
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Logout (limpiar sesión)
     */
    public function logout() {
        session_destroy();
        session_start();
    }
    
    /**
     * Obtener headers para requests AJAX desde JavaScript
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

// Middleware para proteger páginas - CORREGIDO SIN REDIRECCIÓN A DASHBOARD
function protectPage($requiredRole = null, $redirectTo = '/practicas/chat-frontend/public/index.php') {
    $auth = auth();
    
    if ($requiredRole) {
        $auth->requireRole($requiredRole, $redirectTo);
    } else {
        $auth->requireAuth($redirectTo);
    }
}

// FUNCIÓN CORREGIDA - ESTA ERA LA QUE CAUSABA EL PROBLEMA
function protectStaffPage($redirectTo = '/practicas/chat-frontend/public/index.php') {
    $auth = auth();
    
    // ANTES POSIBLEMENTE TENÍA: $redirectTo = '/dashboard/'
    // AHORA CORREGIDO PARA IR AL INDEX.PHP
    
    if (!$auth->isAuthenticated()) {
        header("Location: $redirectTo");
        exit;
    }
    
    if (!$auth->isStaff()) {
        header("Location: $redirectTo");
        exit;
    }
}

// Funciones adicionales para debugging
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