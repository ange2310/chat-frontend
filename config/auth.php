<?php
// config/auth.php - Helper de autenticación simplificado para demo

class AuthHelper {
    private $authServiceURL = 'http://187.33.158.246/auth';
    
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
     * Redirigir si no está autenticado
     */
    public function requireAuth($redirectTo = '/') {
        if (!$this->isAuthenticated()) {
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Redirigir si no es staff
     */
    public function requireStaff($redirectTo = '/') {
        $this->requireAuth($redirectTo);
        if (!$this->isStaff()) {
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Redirigir si no tiene rol específico
     */
    public function requireRole($role, $redirectTo = '/') {
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

// Middleware para proteger páginas
function protectPage($requiredRole = null, $redirectTo = '/') {
    $auth = auth();
    
    if ($requiredRole) {
        $auth->requireRole($requiredRole, $redirectTo);
    } else {
        $auth->requireAuth($redirectTo);
    }
}

function protectStaffPage($redirectTo = '/') {
    auth()->requireStaff($redirectTo);
}
?>