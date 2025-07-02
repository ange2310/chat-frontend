class AuthClient {
    constructor(authServiceUrl = null) { 
        // ✅ URLS CORREGIDAS PARA NGINX PROXY
        this.baseURL = authServiceUrl || 'http://187.33.158.246:8080/auth';
        this.authServiceUrl = this.baseURL;  
        
        this.token = this.getStoredToken();
        this.user = this.getStoredUser();
        this.userType = 'staff'; // 'staff' o 'patient'
        
        console.log('🔐 AuthClient inicializado');
        console.log('🌐 Servidor (nginx):', this.baseURL);
        console.log('👤 Token disponible:', !!this.token);
        
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

    // ====== AUTENTICACIÓN STAFF (JWT) ======
    async login(email, password, remember = false) {
        try {
            console.log('🔐 Iniciando login staff para:', email);

            const response = await fetch(`${this.baseURL}/login`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password, remember })
            });

            console.log('📡 Respuesta del servidor:', response.status);
            
            const result = await response.json();
            console.log('📋 Datos recibidos:', result);

            if (response.ok && result.success) {
                this.userType = 'staff';
                this.setAuth(result.data.access_token, result.data.user);
                console.log('✅ Login staff exitoso');
                return { 
                    success: true, 
                    user: result.data.user, 
                    token: result.data.access_token,
                    data: result.data 
                };
            } else {
                console.log('❌ Login fallido:', result.message);
                return { 
                    success: false, 
                    error: result.message || 'Credenciales inválidas' 
                };
            }
        } catch (error) {
            console.error('❌ Error en login:', error);
            return { 
                success: false, 
                error: 'Error de conexión con el servidor: ' + error.message 
            };
        }
    }

    async register(userData) {
        try {
            console.log('📝 Registrando usuario staff:', userData.email);

            const response = await fetch(`${this.baseURL}/register`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    name: userData.name,
                    email: userData.email,
                    password: userData.password,
                    role: userData.role || 2 // Default: agent
                })
            });

            console.log('📡 Respuesta registro:', response.status);
            
            const result = await response.json();
            console.log('📋 Datos registro:', result);

            if (response.ok && result.success) {
                this.userType = 'staff';
                this.setAuth(result.data.access_token, result.data.user);
                console.log('✅ Registro exitoso');
                return { 
                    success: true, 
                    user: result.data.user, 
                    token: result.data.access_token,
                    data: result.data 
                };
            } else {
                console.log('❌ Registro fallido:', result.message);
                return { 
                    success: false, 
                    error: result.message || 'Error en el registro' 
                };
            }
        } catch (error) {
            console.error('❌ Error en registro:', error);
            return { 
                success: false, 
                error: 'Error de conexión con el servidor: ' + error.message 
            };
        }
    }

    // ====== VALIDACIÓN DE TOKENS (JWT y pToken) ======
    async verifyToken(token = null) {
        const tokenToVerify = token || this.token;
        if (!tokenToVerify) return false;

        try {
            console.log('🔍 Verificando token...');
            
            const response = await fetch(`${this.baseURL}/validate-token`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${tokenToVerify}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ token: tokenToVerify })
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    
                    // Verificar si es JWT (staff) o pToken (patient)
                    if (result.data.token_type === 'jwt' && result.data.payload) {
                        this.userType = 'staff';
                        if (!token) { // Solo actualizar si estamos verificando el token actual
                            this.user = result.data.payload;
                            this.setStoredUser(this.user);
                        }
                        console.log('✅ JWT válido (staff)');
                        return true;
                    } else if (result.data.token_type === 'ptoken') {
                        this.userType = 'patient';
                        console.log('✅ pToken válido (paciente)');
                        return true;
                    }
                }
            }

            console.log('❌ Token inválido');
            return false;
        } catch (error) {
            console.error('❌ Error verificando token:', error);
            return false;
        }
    }

    // ====== VALIDACIÓN ESPECÍFICA PARA PACIENTES ======
    async validatePToken(pToken) {
        try {
            console.log('🔍 Validando pToken de paciente...');
            
            const response = await fetch(`${this.baseURL}/validate-token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ ptoken: pToken })
            });

            const result = await response.json();
            
            if (response.ok && result.success && result.data.token_type === 'ptoken') {
                console.log('✅ pToken válido');
                return {
                    success: true,
                    data: result.data
                };
            } else {
                console.log('❌ pToken inválido:', result.message);
                return {
                    success: false,
                    error: result.message || 'pToken inválido'
                };
            }
        } catch (error) {
            console.error('❌ Error validando pToken:', error);
            return {
                success: false,
                error: 'Error de conexión: ' + error.message
            };
        }
    }

    async logout() {
        try {
            console.log('👋 Cerrando sesión...');
            console.log('🎯 Página actual:', window.location.href);
            
            if (this.token && this.userType === 'staff') {
                // Hacer logout en el servidor para JWT
                try {
                    await fetch(`${this.baseURL}/logout`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${this.token}`,
                            'Content-Type': 'application/json'
                        }
                    });
                    console.log('✅ Logout exitoso en servidor');
                } catch (serverError) {
                    console.warn('⚠️ Error en logout del servidor:', serverError);
                }
            }
            
            this.clearAuth();
            this.updateUI();
            
            // ✅ REDIRECCIÓN CORREGIDA Y CLARA:
            console.log('🔄 Iniciando redirección...');
            
            setTimeout(() => {
                const targetUrl = '/practicas/chat-frontend/public/index.php?logout=1';
                console.log('🎯 Redirigiendo a:', targetUrl);
                
                // Usar replace para evitar history issues
                window.location.replace(targetUrl);
            }, 500);
            
        } catch (error) {
            console.error('❌ Error en logout:', error);
            // Limpiar local aunque falle el servidor
            this.clearAuth();
            this.updateUI();
            
            // Forzar redirección aún con error
            setTimeout(() => {
                window.location.replace('/practicas/chat-frontend/public/logout.php');
            }, 500);
        }
    }

    // ====== GESTIÓN DE SALAS ======
    async getAvailableRooms(pToken = null) {
        console.log('📡 Obteniendo salas disponibles...');
        
        const tokenToUse = pToken || this.token;
        if (!tokenToUse) {
            throw new Error('Token requerido para obtener salas');
        }

        try {
            const response = await fetch(`${this.baseURL}/rooms/available`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${tokenToUse}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            console.log(`📡 Respuesta salas:`, response.status, response.statusText);

            if (response.ok) {
                const data = await response.json();
                console.log('✅ Datos de salas recibidos:', data);
                
                // Extraer salas de la respuesta
                const rooms = data.data?.rooms || data.rooms || [];
                
                if (Array.isArray(rooms)) {
                    console.log(`✅ ${rooms.length} salas encontradas`);
                    return rooms;
                } else {
                    console.log('⚠️ Respuesta no contiene array de salas:', data);
                    return [];
                }
            } else {
                const errorData = await response.json().catch(() => ({}));
                console.log(`❌ Error ${response.status}:`, errorData);
                throw new Error(`Error HTTP ${response.status}: ${errorData.message || 'Error obteniendo salas'}`);
            }

        } catch (error) {
            console.error('❌ Error obteniendo salas:', error);
            throw error;
        }
    }

    async selectRoom(roomId, userData = {}, pToken = null) {
        try {
            console.log('🎯 Seleccionando sala:', roomId);
            
            const tokenToUse = pToken || this.token;
            if (!tokenToUse) {
                throw new Error('Token requerido para seleccionar sala');
            }

            const response = await fetch(`${this.baseURL}/rooms/${roomId}/select`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${tokenToUse}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    user_data: {
                        source: this.userType === 'staff' ? 'staff_portal' : 'patient_portal',
                        selected_at: new Date().toISOString(),
                        ...userData
                    }
                })
            });

            const result = await response.json();
            console.log('📥 Respuesta de sala:', result);

            if (result.success) {
                console.log('✅ Sala seleccionada exitosamente');
                return {
                    success: true,
                    ptoken: result.data?.ptoken || tokenToUse,
                    room_data: result.data,
                    room: result.data?.room
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

    // ====== GESTIÓN LOCAL DE AUTH ======
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
        console.log('🧹 Limpiando autenticación...');
        console.log('📍 Token anterior:', this.token ? this.token.substring(0, 20) + '...' : 'ninguno');
        console.log('👤 Usuario anterior:', this.user?.name || 'ninguno');
        
        this.token = null;
        this.user = null;
        this.userType = 'staff';
        
        localStorage.removeItem('pToken');
        localStorage.removeItem('user');
        
        console.log('✅ Autenticación limpiada completamente');
        
        // Disparar evento personalizado
        this.dispatchAuthEvent(false, null);
    }

    getStoredToken() {
        const token = localStorage.getItem('pToken');
        if (token) {
            console.log('🔑 Token recuperado del localStorage:', token.substring(0, 20) + '...');
        }
        return token;
    }

    getStoredUser() {
        try {
            const userData = localStorage.getItem('user');
            if (userData) {
                const user = JSON.parse(userData);
                console.log('👤 Usuario recuperado del localStorage:', user.name || user.email || 'unknown');
                return user;
            }
            return null;
        } catch (error) {
            console.error('Error parsing stored user:', error);
            return null;
        }
    }

    setStoredUser(user) {
        localStorage.setItem('user', JSON.stringify(user));
    }

    // ====== INFORMACIÓN DEL USUARIO ======
    isAuthenticated() {
        return !!(this.token && (this.user || this.userType === 'patient'));
    }

    getToken() {
        return this.token;
    }

    getUser() {
        return this.user;
    }

    getUserType() {
        return this.userType;
    }

    isStaff() {
        return this.userType === 'staff' && this.user && (
            this.hasRole('admin') || 
            this.hasRole('supervisor') || 
            this.hasRole('agent')
        );
    }

    isPatient() {
        return this.userType === 'patient';
    }

    hasRole(role) {
        if (this.userType !== 'staff' || !this.user) return false;
        
        const userRole = this.user.role?.name || this.user.role;
        if (typeof userRole === 'string') {
            return userRole === role;
        }
        
        // Si es numérico (legacy)
        const roleMap = { 1: 'patient', 2: 'agent', 3: 'supervisor', 4: 'admin' };
        return roleMap[userRole] === role;
    }

    getAuthHeaders(token = null) {
        const tokenToUse = token || this.token;
        if (!tokenToUse) {
            console.warn('⚠️ No hay token disponible para headers de auth');
        }
        return {
            'Authorization': `Bearer ${tokenToUse}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    // ====== EVENTOS Y UI ======
    dispatchAuthEvent(isAuthenticated, user) {
        window.dispatchEvent(new CustomEvent('authStateChanged', {
            detail: { 
                isAuthenticated, 
                user, 
                userType: this.userType 
            }
        }));
    }

    updateUI() {
        const isAuth = this.isAuthenticated();
        
        console.log('🔄 Actualizando UI:', { 
            isAuthenticated: isAuth, 
            user: this.user?.name || 'none',
            userType: this.userType,
            token: this.token ? 'disponible' : 'no disponible'
        });
        
        // Solo actualizar UI para staff (los pacientes no tienen UI compleja)
        if (this.userType === 'staff') {
            this.updateStaffUI(isAuth);
        }
    }

    updateStaffUI(isAuth) {
        // Manejar secciones principales
        const authRequired = document.querySelectorAll('.auth-required');
        const guestOnly = document.querySelectorAll('.guest-only');
        
        authRequired.forEach(el => {
            if (isAuth) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
        
        guestOnly.forEach(el => {
            if (isAuth) {
                el.classList.add('hidden');
            } else {
                el.classList.remove('hidden');
            }
        });
        
        // Manejar elementos específicos del header
        const userAuthSection = document.getElementById('userAuthenticatedSection');
        const userGuestSection = document.getElementById('userGuestSection');
        
        if (userAuthSection && userGuestSection) {
            if (isAuth) {
                userAuthSection.classList.remove('hidden');
                userAuthSection.classList.add('flex');
                userGuestSection.classList.add('hidden');
                userGuestSection.classList.remove('flex');
            } else {
                userAuthSection.classList.add('hidden');
                userAuthSection.classList.remove('flex');
                userGuestSection.classList.remove('hidden');
                userGuestSection.classList.add('flex');
            }
        }
        
        // Actualizar información del usuario en el header
        if (isAuth && this.user) {
            const userInitial = document.getElementById('userInitial');
            const userDisplayName = document.getElementById('userDisplayName');
            
            if (userInitial) {
                userInitial.textContent = this.user.name ? this.user.name.charAt(0).toUpperCase() : 'U';
            }
            
            if (userDisplayName) {
                userDisplayName.textContent = this.user.name || 'Usuario';
            }
        }
        
        // Actualizar elementos con clase user-name (para compatibilidad)
        const userNameElements = document.querySelectorAll('.user-name');
        userNameElements.forEach(el => {
            if (isAuth && this.user?.name) {
                el.textContent = this.user.name;
            } else {
                el.textContent = 'Usuario';
            }
        });

        console.log('✅ UI staff actualizada correctamente');
    }

    // ====== NOTIFICACIONES ======
    showSuccess(message, duration = 3000) {
        this.showNotification(message, 'success', duration);
    }

    showError(message, duration = 5000) {
        this.showNotification(message, 'error', duration);
    }

    showInfo(message, duration = 4000) {
        this.showNotification(message, 'info', duration);
    }

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

    // ====== VALIDACIONES ======
    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    validatePassword(password) {
        // Al menos 8 caracteres, una mayúscula, una minúscula y un número
        const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;
        return strongPasswordRegex.test(password);
    }

    validateName(name) {
        return name && name.trim().length >= 2 && name.trim().length <= 50;
    }

    // ====== TESTING Y DEBUG ======
    async testConnection() {
        try {
            console.log('🔍 Probando conexión con servidor...');
            
            const response = await fetch(`http://187.33.158.246:8080/health`);
            
            if (response.ok) {
                const data = await response.json();
                console.log('✅ Servidor respondiendo:', data);
                this.showSuccess('Conexión con servidor exitosa');
                return true;
            } else {
                throw new Error(`Error ${response.status}: ${response.statusText}`);
            }
        } catch (error) {
            console.error('❌ Error de conexión:', error);
            this.showError('Error de conexión con el servidor');
            return false;
        }
    }

    getDebugInfo() {
        return {
            baseURL: this.baseURL,
            userType: this.userType,
            isAuthenticated: this.isAuthenticated(),
            isStaff: this.isStaff(),
            isPatient: this.isPatient(),
            user: this.user,
            hasToken: !!this.token,
            tokenLength: this.token?.length || 0,
            userRole: this.user?.role?.name || this.user?.role || 'none'
        };
    }
}

// Event listener para el estado de autenticación
window.addEventListener('authStateChanged', (event) => {
    const { isAuthenticated, user, userType } = event.detail;
    console.log('🔄 Estado de auth cambió:', { 
        isAuthenticated, 
        userName: user?.name || 'none',
        userType
    });
});

// ====== FUNCIONES GLOBALES PARA HTML ======

// Funciones de modal de autenticación (para staff)
window.showAuthModal = function(type = 'login') {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.classList.remove('hidden');
        
        if (type === 'login') {
            showLoginForm();
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
    const title = document.getElementById('authModalTitle');
    
    if (loginForm) loginForm.classList.remove('hidden');
    if (title) title.textContent = 'Iniciar Sesión';
};

window.logout = function() {
    if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
        window.authClient.logout();
    }
};

// Manejo de formulario de login (para páginas que lo usen)
window.handleLoginSubmit = async function(event) {
    event.preventDefault();
    
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    const remember = document.getElementById('rememberMe')?.checked || false;
    
    // Validaciones frontend
    if (!email || !password) {
        window.authClient.showError('Email y contraseña son requeridos');
        return;
    }
    
    if (!window.authClient.validateEmail(email)) {
        window.authClient.showError('Formato de email inválido');
        return;
    }
    
    // Mostrar loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>Iniciando sesión...';
    
    try {
        const result = await window.authClient.login(email, password, remember);
        
        if (result.success) {
            window.authClient.showSuccess('¡Bienvenido de vuelta!');
            if (typeof closeAuthModal === 'function') closeAuthModal();
            
            // ✅ REDIRECCIÓN CLARA Y EXPLÍCITA:
            console.log('🎯 Login exitoso, preparando redirección...');
            setTimeout(() => {
                if (window.authClient.isStaff()) {
                    const targetUrl = '/practicas/chat-frontend/public/staff.php';
                    console.log('🏥 Redirigiendo a panel de staff:', targetUrl);
                    window.location.href = targetUrl;
                } else {
                    console.log('🔄 Recargando página actual');
                    window.location.reload();
                }
            }, 1000);
        } else {
            window.authClient.showError(result.error || 'Error en el login');
        }
    } catch (error) {
        console.error('❌ Error en handleLoginSubmit:', error);
        window.authClient.showError('Error de conexión');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
};

// ====== UTILIDADES DEBUG ======
window.debugAuth = {
    // Test de conexión
    testConnection: () => window.authClient.testConnection(),
    
    // Información del cliente
    getInfo: () => window.authClient.getDebugInfo(),
    
    // Estado actual
    getState: () => ({
        isAuthenticated: window.authClient.isAuthenticated(),
        userType: window.authClient.getUserType(),
        user: window.authClient.getUser(),
        token: window.authClient.getToken()?.substring(0, 20) + '...'
    }),
    
    // Limpiar autenticación
    clearAuth: () => {
        window.authClient.clearAuth();
        console.log('🧹 Autenticación limpiada manualmente');
    },
    
    // Login de prueba
    testLogin: async (email = 'admin@tpsalud.com', password = 'Admin123') => {
        const result = await window.authClient.login(email, password);
        console.log('🔐 Test login result:', result);
        return result;
    },
    
    // Test de pToken
    testPToken: async (pToken = 'CC678AVEZVKADBT') => {
        if (!window.authClient) {
            console.error('❌ AuthClient no inicializado');
            return;
        }
        const result = await window.authClient.validatePToken(pToken);
        console.log('🔑 Test pToken result:', result);
        return result;
    },
    
    // Test de salas
    testRooms: async (pToken = null) => {
        if (!window.authClient.isAuthenticated() && !pToken) {
            console.error('❌ Debes estar autenticado o proporcionar pToken para probar salas');
            return;
        }
        const rooms = await window.authClient.getAvailableRooms(pToken);
        console.log('🏠 Salas disponibles:', rooms);
        return rooms;
    },
    
    // Test de selección de sala
    testSelectRoom: async (roomId = 'general', pToken = null) => {
        if (!window.authClient.isAuthenticated() && !pToken) {
            console.error('❌ Debes estar autenticado o proporcionar pToken para seleccionar salas');
            return;
        }
        const result = await window.authClient.selectRoom(roomId, {}, pToken);
        console.log('🎯 Resultado selección:', result);
        return result;
    },
    
    // 🔍 DEBUGGING ESPECÍFICO PARA EL PROBLEMA DEL DASHBOARD
    trackRedirects: () => {
        console.log('🕵️ Activando rastreo de redirecciones...');
        
        // Interceptar window.location changes
        let originalLocation = window.location.href;
        
        const checkLocationChange = () => {
            if (originalLocation !== window.location.href) {
                console.log('🚨 REDIRECCIÓN DETECTADA:', {
                    from: originalLocation,
                    to: window.location.href,
                    stack: new Error().stack
                });
                originalLocation = window.location.href;
            }
        };
        
        setInterval(checkLocationChange, 100);
        
        // Interceptar history API
        const originalPushState = history.pushState;
        const originalReplaceState = history.replaceState;
        
        history.pushState = function(...args) {
            console.log('🚨 PUSH STATE:', args);
            console.trace('Stack trace:');
            return originalPushState.apply(this, args);
        };
        
        history.replaceState = function(...args) {
            console.log('🚨 REPLACE STATE:', args);
            console.trace('Stack trace:');
            return originalReplaceState.apply(this, args);
        };
        
        console.log('✅ Rastreo de redirecciones activado');
    }
};

console.log('🔐 AuthClient v3.0 cargado - SIN REDIRECCIONES RARAS');
console.log('🛠️ Debug disponible en: window.debugAuth');
console.log('🕵️ Para rastrear el problema del dashboard: window.debugAuth.trackRedirects()');