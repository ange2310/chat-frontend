class AuthClient {
    constructor() {
        // Usar tus URLs reales del servidor
        this.baseURL = 'http://187.33.158.246:8080/auth';
        this.token = this.getStoredToken();
        this.user = this.getStoredUser();
        
        console.log('🔐 AuthClient inicializado con servidor:', this.baseURL);
        this.init();
    }

    init() {
        // Verificar token existente al cargar
        if (this.token) {
            this.verifyToken().then(isValid => {
                if (!isValid) {
                    this.clearAuth();
                }
                this.updateUI();
            });
        } else {
            this.updateUI();
        }
    }

    // ===============================
    // AUTENTICACIÓN
    // ===============================

    async login(email, password, remember = false) {
        try {
            console.log('🔐 Iniciando login para:', email);

            const response = await fetch(`${this.baseURL}/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password, remember })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                this.setAuth(result.data.access_token, result.data.user);
                console.log('✅ Login exitoso');
                return { success: true, user: result.data.user, data: result.data };
            } else {
                console.log('❌ Login fallido:', result.message);
                return { success: false, error: result.message || 'Error de autenticación' };
            }
        } catch (error) {
            console.error('❌ Error en login:', error);
            return { success: false, error: 'Error de conexión con el servidor' };
        }
    }

    async register(userData) {
        try {
            console.log('📝 Registrando usuario:', userData.email);

            const response = await fetch(`${this.baseURL}/register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: userData.name,
                    email: userData.email,
                    password: userData.password,
                    role: userData.role || 1 // Paciente por defecto
                })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Auto-login después del registro
                this.setAuth(result.data.access_token, result.data.user);
                console.log('✅ Registro exitoso');
                return { success: true, user: result.data.user, data: result.data };
            } else {
                console.log('❌ Registro fallido:', result.message);
                return { success: false, error: result.message || 'Error en el registro' };
            }
        } catch (error) {
            console.error('❌ Error en registro:', error);
            return { success: false, error: 'Error de conexión con el servidor' };
        }
    }

    async verifyToken() {
        if (!this.token) return false;

        try {
            const response = await fetch(`${this.baseURL}/validate-token`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ token: this.token })
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success && result.data.user) {
                    this.user = result.data.user;
                    this.setStoredUser(this.user);
                    return true;
                }
            }

            return false;
        } catch (error) {
            console.error('❌ Error verificando token:', error);
            return false;
        }
    }

    logout() {
        console.log('👋 Cerrando sesión');
        this.clearAuth();
        this.updateUI();
        
        // Redirigir a home si estamos en una página protegida
        if (window.location.pathname.includes('staff')) {
            window.location.href = '/';
        } else {
            window.location.reload();
        }
    }

    // ===============================
    // GESTIÓN DE SALAS
    // ===============================

    async getAvailableRooms() {
        try {
            const response = await fetch(`${this.baseURL}/rooms/available`, {
                headers: this.getAuthHeaders()
            });

            const result = await response.json();

            if (result.success) {
                return result.data.rooms || [];
            }

            throw new Error(result.message || 'Error obteniendo salas');
        } catch (error) {
            console.error('❌ Error obteniendo salas:', error);
            return [];
        }
    }

    async selectRoom(roomId, userData = {}) {
        try {
            console.log('🎯 Seleccionando sala:', roomId);
            console.log('🔑 Token disponible:', !!this.token);
            console.log('👤 Usuario autenticado:', this.isAuthenticated());

            const response = await fetch(`${this.baseURL}/rooms/${roomId}/select`, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    user_data: {
                        source: 'patient_portal',
                        selected_at: new Date().toISOString(),
                        ...userData
                    }
                })
            });

            const result = await response.json();
            console.log('📥 Respuesta de sala:', result);

            if (result.success) {
                console.log('✅ Sala seleccionada, pToken obtenido');
                return {
                    success: true,
                    ptoken: result.data.ptoken,
                    room_data: result.data
                };
            }

            throw new Error(result.message || 'Error seleccionando sala');
        } catch (error) {
            console.error('❌ Error seleccionando sala:', error);
            return {
                success: false,
                error: error.message || 'Error de conexión'
            };
        }
    }

    // ===============================
    // ALMACENAMIENTO LOCAL
    // ===============================

    setAuth(token, user) {
        this.token = token;
        this.user = user;
        
        localStorage.setItem('pToken', token);
        localStorage.setItem('user', JSON.stringify(user));
        
        console.log('💾 Autenticación guardada para:', user.email || user.name);
        console.log('🔑 Token guardado (length):', token?.length || 0);
        
        // Disparar evento personalizado
        this.dispatchAuthEvent(true, user);
        
        // Actualizar UI inmediatamente
        this.updateUI();
    }

    clearAuth() {
        this.token = null;
        this.user = null;
        
        localStorage.removeItem('pToken');
        localStorage.removeItem('user');
        
        console.log('🧹 Autenticación limpiada');
        
        // Disparar evento personalizado
        this.dispatchAuthEvent(false, null);
    }

    getStoredToken() {
        return localStorage.getItem('pToken');
    }

    getStoredUser() {
        try {
            const userData = localStorage.getItem('user');
            return userData ? JSON.parse(userData) : null;
        } catch (error) {
            console.error('Error parsing stored user:', error);
            return null;
        }
    }

    setStoredUser(user) {
        localStorage.setItem('user', JSON.stringify(user));
    }

    // ===============================
    // HELPERS
    // ===============================

    isAuthenticated() {
        return !!(this.token && this.user);
    }

    getToken() {
        return this.token;
    }

    getUser() {
        return this.user;
    }

    hasRole(role) {
        const userRole = this.user?.role?.name || this.user?.role;
        return userRole === role;
    }

    isStaff() {
        return this.hasRole('admin') || this.hasRole('supervisor') || this.hasRole('agent');
    }

    getAuthHeaders() {
        if (!this.token) {
            console.warn('⚠️ No hay token disponible para headers de auth');
        }
        return {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
        };
    }

    dispatchAuthEvent(isAuthenticated, user) {
        window.dispatchEvent(new CustomEvent('authStateChanged', {
            detail: { isAuthenticated, user }
        }));
    }

    updateUI() {
        const isAuth = this.isAuthenticated();
        
        // Mostrar/ocultar elementos según autenticación
        const authRequired = document.querySelectorAll('.auth-required');
        const guestOnly = document.querySelectorAll('.guest-only');
        
        authRequired.forEach(el => {
            el.style.display = isAuth ? 'block' : 'none';
        });
        
        guestOnly.forEach(el => {
            el.style.display = isAuth ? 'none' : 'block';
        });
        
        // Actualizar elementos con clase user-name
        const userNameElements = document.querySelectorAll('.user-name');
        userNameElements.forEach(el => {
            if (isAuth && this.user?.name) {
                el.textContent = this.user.name;
            }
        });

        console.log('🔄 UI actualizada:', { 
            isAuthenticated: isAuth, 
            user: this.user?.name || 'none',
            token: this.token ? 'disponible' : 'no disponible'
        });
    }

    // ===============================
    // NOTIFICACIONES
    // ===============================

    showNotification(message, type = 'info', duration = 5000) {
        // Crear elemento de notificación
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all transform ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            type === 'warning' ? 'bg-yellow-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${this.getNotificationIcon(type)}
                    </svg>
                    <span>${message}</span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 hover:opacity-75">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remover
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, duration);
    }

    getNotificationIcon(type) {
        const icons = {
            success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>',
            error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>',
            warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>',
            info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
        };
        return icons[type] || icons.info;
    }

    // ===============================
    // UTILIDADES DE DESARROLLO
    // ===============================

    async testConnection() {
        try {
            console.log('🔍 Probando conexión con servidor...');
            
            const response = await fetch(`${this.baseURL}/health`);
            
            if (response.ok) {
                const data = await response.json();
                console.log('✅ Servidor respondiendo:', data);
                this.showNotification('Conexión con servidor exitosa', 'success');
                return true;
            } else {
                throw new Error(`Error ${response.status}: ${response.statusText}`);
            }
        } catch (error) {
            console.error('❌ Error de conexión:', error);
            this.showNotification('Error de conexión con el servidor', 'error');
            return false;
        }
    }

    getDebugInfo() {
        return {
            baseURL: this.baseURL,
            isAuthenticated: this.isAuthenticated(),
            user: this.user,
            hasToken: !!this.token,
            tokenLength: this.token?.length || 0,
            userRole: this.user?.role?.name || this.user?.role || 'none'
        };
    }
}

// ===============================
// INICIALIZACIÓN GLOBAL
// ===============================

// Crear instancia global
window.authClient = new AuthClient();

// Event listener para el estado de autenticación
window.addEventListener('authStateChanged', (event) => {
    const { isAuthenticated, user } = event.detail;
    console.log('🔄 Estado de auth cambió:', { 
        isAuthenticated, 
        userName: user?.name || 'none' 
    });
});

// ===============================
// FUNCIONES GLOBALES DE UTILIDAD
// ===============================

// Funciones para uso en HTML
window.showAuthModal = function(type = 'login') {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.classList.remove('hidden');
        
        if (type === 'login') {
            showLoginForm();
        } else {
            showRegisterForm();
        }
    }
};

window.closeAuthModal = function() {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.classList.add('hidden');
    }
};

window.showLoginForm = function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const title = document.getElementById('authModalTitle');
    
    if (loginForm) loginForm.classList.remove('hidden');
    if (registerForm) registerForm.classList.add('hidden');
    if (title) title.textContent = 'Iniciar Sesión';
};

window.showRegisterForm = function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const title = document.getElementById('authModalTitle');
    
    if (loginForm) loginForm.classList.add('hidden');
    if (registerForm) registerForm.classList.remove('hidden');
    if (title) title.textContent = 'Crear Cuenta';
};

window.logout = function() {
    if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
        window.authClient.logout();
    }
};

// ===============================
// MODO DEBUG (DISPONIBLE SIEMPRE)
// ===============================

window.debugAuth = {
    // Test de conexión
    testConnection: () => window.authClient.testConnection(),
    
    // Información del cliente
    getInfo: () => window.authClient.getDebugInfo(),
    
    // Estado actual
    getState: () => ({
        isAuthenticated: window.authClient.isAuthenticated(),
        user: window.authClient.getUser(),
        token: window.authClient.getToken()?.substring(0, 20) + '...'
    }),
    
    // Limpiar autenticación
    clearAuth: () => {
        window.authClient.clearAuth();
        console.log('🧹 Autenticación limpiada');
    },
    
    // Login de prueba
    testLogin: async (email = 'test@test.com', password = 'Password123') => {
        const result = await window.authClient.login(email, password);
        console.log('🔐 Test login result:', result);
        return result;
    },
    
    // Test de sala
    testRoom: async (roomId = 'general') => {
        if (!window.authClient.isAuthenticated()) {
            console.error('❌ Debes estar autenticado para probar salas');
            return;
        }
        const result = await window.authClient.selectRoom(roomId);
        console.log('🏠 Test room result:', result);
        return result;
    },
    
    // Test de notificaciones
    testNotification: (message = 'Test notification', type = 'info') => {
        window.authClient.showNotification(message, type);
    },
    
    // Help
    help: () => {
        console.log(`
🛠️ DEBUG AUTH CLIENT:
═══════════════════════════

• debugAuth.testConnection() - Probar conexión
• debugAuth.getInfo() - Info del cliente
• debugAuth.getState() - Estado actual
• debugAuth.clearAuth() - Limpiar auth
• debugAuth.testLogin() - Login de prueba
• debugAuth.testRoom() - Test selección sala
• debugAuth.testNotification() - Test notificación

URLs configuradas:
• Auth Service: ${window.authClient.baseURL}
        `);
    }
};

console.log('🛠️ Modo debug activado. Usa debugAuth.help() para ver comandos');