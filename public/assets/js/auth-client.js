class AuthClient {
    constructor(authServiceUrl = null) { 
        this.baseURL = authServiceUrl || 'http://187.33.158.246:8080/auth';
        this.authServiceUrl = this.baseURL;  
        
        this.token = this.getStoredToken();
        this.user = this.getStoredUser();
        
        console.log('🔐 AuthClient inicializado con servidor:', this.baseURL);
        console.log('🔐 authServiceUrl establecido:', this.authServiceUrl);
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


    async login(email, password, remember = false) {
        try {
            console.log('🔐 Iniciando login para:', email);
            console.log('🌐 URL:', `${this.baseURL}/login`);

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
                this.setAuth(result.data.access_token, result.data.user);
                console.log('✅ Login exitoso');
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
                    error: result.message || 'Error de autenticación' 
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
            console.log('📝 Registrando usuario:', userData.email);
            console.log('🌐 URL:', `${this.baseURL}/register`);

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
                    role: userData.role || 1 
                })
            });

            console.log('📡 Respuesta registro:', response.status);
            
            const result = await response.json();
            console.log('📋 Datos registro:', result);

            if (response.ok && result.success) {
                // Auto-login después del registro
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

    async verifyToken() {
        if (!this.token) return false;

        try {
            console.log('🔍 Verificando token...');
            
            const response = await fetch(`${this.baseURL}/validate-token`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ token: this.token })
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success && result.data.user) {
                    this.user = result.data.user;
                    this.setStoredUser(this.user);
                    console.log('✅ Token válido');
                    return true;
                }
            }

            console.log('❌ Token inválido');
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
        
        // Redirigir a home
        setTimeout(() => {
            window.location.href = '/';
        }, 500);
    }

    async getAvailableRooms() {
    console.log('📡 Obteniendo salas disponibles...');
    console.log('🔍 Token disponible:', !!this.token);
    console.log('🔍 Usuario autenticado:', this.isAuthenticated());
    
    if (!this.isAuthenticated()) {
        throw new Error('Usuario no autenticado');
    }

    try {
        // SOLO UNA URL - LA CORRECTA
        const endpoint = `${this.baseURL}/rooms/available`;
        
        console.log(`🔄 Llamando a: ${endpoint}`);
        
        const response = await fetch(endpoint, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            }
        });

        console.log(`📡 Respuesta:`, response.status, response.statusText);

        if (response.ok) {
            const data = await response.json();
            console.log('✅ Datos de salas recibidos:', data);
            
            // Extraer salas del response
            const rooms = data.rooms || data.data?.rooms || data;
            
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
            throw new Error(`Error HTTP ${response.status}: ${JSON.stringify(errorData)}`);
        }

    } catch (error) {
        console.error('❌ Error obteniendo salas:', error);
        throw error;
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
                    room_data: result.data,
                    room: result.data.room
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
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    dispatchAuthEvent(isAuthenticated, user) {
        window.dispatchEvent(new CustomEvent('authStateChanged', {
            detail: { isAuthenticated, user }
        }));
    }

    updateUI() {
        const isAuth = this.isAuthenticated();
        
        console.log('🔄 Actualizando UI:', { 
            isAuthenticated: isAuth, 
            user: this.user?.name || 'none',
            token: this.token ? 'disponible' : 'no disponible'
        });
        
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

        console.log('✅ UI actualizada correctamente');
    }

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

    async testConnection() {
        try {
            console.log('🔍 Probando conexión con servidor...');
            
            const response = await fetch(`${this.baseURL}/../health`);
            
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
            isAuthenticated: this.isAuthenticated(),
            user: this.user,
            hasToken: !!this.token,
            tokenLength: this.token?.length || 0,
            userRole: this.user?.role?.name || this.user?.role || 'none'
        };
    }
}

// Event listener para el estado de autenticación
window.addEventListener('authStateChanged', (event) => {
    const { isAuthenticated, user } = event.detail;
    console.log('🔄 Estado de auth cambió:', { 
        isAuthenticated, 
        userName: user?.name || 'none' 
    });
});


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


// Manejo de formulario de login
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
            closeAuthModal();
            
            // Redireccionar según el rol
            setTimeout(() => {
                if (window.authClient.isStaff()) {
                    window.location.href = '/staff.php';
                } else {
                    window.location.reload();
                }
            }, 1000);
        } else {
            window.authClient.showError(result.error || 'Error en el login');
        }
    } catch (error) {
        window.authClient.showError('Error de conexión');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
};

// Manejo de formulario de registro
window.handleRegisterSubmit = async function(event) {
    event.preventDefault();
    
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const email = document.getElementById('registerEmail').value.trim();
    const password = document.getElementById('registerPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const acceptTerms = document.getElementById('acceptTerms')?.checked;
    
    // Validaciones frontend
    if (!firstName || !lastName || !email || !password || !confirmPassword) {
        window.authClient.showError('Todos los campos son requeridos');
        return;
    }
    
    if (!window.authClient.validateName(firstName) || !window.authClient.validateName(lastName)) {
        window.authClient.showError('Nombres deben tener entre 2 y 50 caracteres');
        return;
    }
    
    if (!window.authClient.validateEmail(email)) {
        window.authClient.showError('Formato de email inválido');
        return;
    }
    
    if (!window.authClient.validatePassword(password)) {
        window.authClient.showError('La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número');
        return;
    }
    
    if (password !== confirmPassword) {
        window.authClient.showError('Las contraseñas no coinciden');
        return;
    }
    
    if (!acceptTerms) {
        window.authClient.showError('Debes aceptar los términos y condiciones');
        return;
    }
    
    // Mostrar loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>Creando cuenta...';
    
    try {
        const result = await window.authClient.register({
            name: `${firstName} ${lastName}`,
            email,
            password,
            role: 1 // Paciente por defecto
        });
        
        if (result.success) {
            window.authClient.showSuccess('¡Cuenta creada exitosamente! Bienvenido!');
            closeAuthModal();
            
            // Redireccionar
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            window.authClient.showError(result.error || 'Error en el registro');
        }
    } catch (error) {
        window.authClient.showError('Error de conexión');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
};


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
    
    // Test de registro
    testRegister: async () => {
        const result = await window.authClient.register({
            name: 'Usuario Prueba',
            email: 'test' + Date.now() + '@test.com',
            password: 'Password123',
            role: 1
        });
        console.log('📝 Test register result:', result);
        return result;
    },
    
    // Test de salas
    testRooms: async () => {
        if (!window.authClient.isAuthenticated()) {
            console.error('❌ Debes estar autenticado para probar salas');
            return;
        }
        const rooms = await window.authClient.getAvailableRooms();
        console.log('🏠 Salas disponibles:', rooms);
        return rooms;
    },
    
    // Test de selección de sala
    testSelectRoom: async (roomId = 'general') => {
        if (!window.authClient.isAuthenticated()) {
            console.error('❌ Debes estar autenticado para seleccionar salas');
            return;
        }
        const result = await window.authClient.selectRoom(roomId);
        console.log('🎯 Resultado selección:', result);
        return result;
    },
};
