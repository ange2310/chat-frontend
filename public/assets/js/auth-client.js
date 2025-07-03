class AuthClient {
    constructor(authServiceUrl = null) { 
        // URL del backend via nginx
        this.baseURL = authServiceUrl || 'http://187.33.158.246:8080/auth';
        this.authServiceUrl = this.baseURL;  
        
        this.token = null;
        this.user = null;
        this.userType = 'staff'; // 'staff' o 'patient'
        
        console.log('🔐 AuthClient simplificado inicializado');
        console.log('🌐 Servidor:', this.baseURL);
    }

    // ====== AUTENTICACIÓN STAFF (JWT) ======
    async login(email, password, remember = false) {
        try {
            console.log('🔐 Login staff:', email);

            const response = await fetch(`${this.baseURL}/login`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password, remember })
            });

            console.log('📡 Respuesta login:', response.status);
            
            const result = await response.json();
            console.log('📋 Datos login:', result);

            if (response.ok && result.success) {
                this.userType = 'staff';
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
                    error: result.message || 'Credenciales inválidas' 
                };
            }
        } catch (error) {
            console.error('❌ Error en login:', error);
            return { 
                success: false, 
                error: 'Error de conexión: ' + error.message 
            };
        }
    }

    async register(userData) {
        try {
            console.log('📝 Registrando usuario:', userData.email);

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
                    role: userData.role || 2
                })
            });

            console.log('📡 Respuesta registro:', response.status);
            
            const result = await response.json();

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
                error: 'Error de conexión: ' + error.message 
            };
        }
    }

    // ====== VALIDACIÓN DE TOKENS ======
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
                    if (result.data.token_type === 'jwt' && result.data.payload) {
                        this.userType = 'staff';
                        if (!token) {
                            this.user = result.data.payload;
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

    // ====== VALIDACIÓN PTOKEN PARA PACIENTES ======
    async validatePToken(pToken) {
        try {
            console.log('🔍 Validando pToken...');
            
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
            console.log('👋 Cerrando sesión');
            
            if (this.token && this.userType === 'staff') {
                await fetch(`${this.baseURL}/logout`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Content-Type': 'application/json'
                    }
                });
            }
            
            this.clearAuth();
            
            setTimeout(() => {
                window.location.href = '/practicas/chat-frontend/public/logout.php';
            }, 500);
            
        } catch (error) {
            console.error('❌ Error en logout:', error);
            this.clearAuth();
        }
    }

    // ====== GESTIÓN DE SALAS ======
    async getAvailableRooms(pToken = null) {
        console.log('📡 Obteniendo salas...');
        
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

            console.log(`📡 Respuesta salas:`, response.status);

            if (response.ok) {
                const data = await response.json();
                console.log('✅ Datos de salas:', data);
                
                const rooms = data.data?.rooms || data.rooms || [];
                
                if (Array.isArray(rooms)) {
                    console.log(`✅ ${rooms.length} salas encontradas`);
                    return rooms;
                } else {
                    console.log('⚠️ Respuesta no contiene salas:', data);
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
                console.log('✅ Sala seleccionada');
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

    // ====== GESTIÓN LOCAL - SIMPLIFICADA ======
    setAuth(token, user) {
        this.token = token;
        this.user = user;
        
        // SOLO guardar en localStorage si es para pacientes
        if (this.userType === 'patient') {
            localStorage.setItem('pToken', token);
            localStorage.setItem('user', JSON.stringify(user));
        }
        
        console.log('💾 Auth guardada:', user.email || user.name);
    }

    clearAuth() {
        this.token = null;
        this.user = null;
        this.userType = 'staff';
        
        // Limpiar localStorage solo si es necesario
        if (localStorage.getItem('pToken')) {
            localStorage.removeItem('pToken');
            localStorage.removeItem('user');
        }
        
        console.log('🧹 Auth limpiada');
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
        
        const roleMap = { 1: 'patient', 2: 'agent', 3: 'supervisor', 4: 'admin' };
        return roleMap[userRole] === role;
    }

    getAuthHeaders(token = null) {
        const tokenToUse = token || this.token;
        if (!tokenToUse) {
            console.warn('⚠️ No hay token para headers');
        }
        return {
            'Authorization': `Bearer ${tokenToUse}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    // ====== NOTIFICACIONES SIMPLES ======
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
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all transform ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            type === 'warning' ? 'bg-yellow-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 hover:opacity-75">×</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    }

    // ====== VALIDACIONES ======
    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    validatePassword(password) {
        const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;
        return strongPasswordRegex.test(password);
    }

    validateName(name) {
        return name && name.trim().length >= 2 && name.trim().length <= 50;
    }

    // ====== DEBUG ======
    async testConnection() {
        try {
            console.log('🔍 Probando conexión...');
            
            const response = await fetch(`http://187.33.158.246:8080/health`);
            
            if (response.ok) {
                const data = await response.json();
                console.log('✅ Servidor OK:', data);
                this.showSuccess('Conexión exitosa');
                return true;
            } else {
                throw new Error(`Error ${response.status}`);
            }
        } catch (error) {
            console.error('❌ Error conexión:', error);
            this.showError('Error de conexión');
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

// ====== FUNCIONES GLOBALES PARA HTML ======
window.showAuthModal = function(type = 'login') {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.classList.remove('hidden');
        if (type === 'login') showLoginForm();
    }
};

window.closeAuthModal = function() {
    const modal = document.getElementById('authModal');
    if (modal) modal.classList.add('hidden');
};

window.showLoginForm = function() {
    const loginForm = document.getElementById('loginForm');
    const title = document.getElementById('authModalTitle');
    
    if (loginForm) loginForm.classList.remove('hidden');
    if (title) title.textContent = 'Iniciar Sesión';
};

window.logout = function() {
    if (confirm('¿Cerrar sesión?')) {
        if (window.authClient) {
            window.authClient.logout();
        } else {
            window.location.href = '/practicas/chat-frontend/public/logout.php';
        }
    }
};

// SOLO para páginas que lo necesiten
window.handleLoginSubmit = async function(event) {
    event.preventDefault();
    
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    const remember = document.getElementById('rememberMe')?.checked || false;
    
    if (!email || !password) {
        window.authClient.showError('Email y contraseña requeridos');
        return;
    }
    
    if (!window.authClient.validateEmail(email)) {
        window.authClient.showError('Email inválido');
        return;
    }
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>Iniciando...';
    
    try {
        const result = await window.authClient.login(email, password, remember);
        
        if (result.success) {
            window.authClient.showSuccess('¡Login exitoso!');
            if (typeof closeAuthModal === 'function') closeAuthModal();
            
            setTimeout(() => {
                if (window.authClient.isStaff()) {
                    window.location.href = '/practicas/chat-frontend/public/staff.php';
                } else {
                    window.location.reload();
                }
            }, 1000);
        } else {
            window.authClient.showError(result.error || 'Error en login');
        }
    } catch (error) {
        window.authClient.showError('Error de conexión');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
};

// ====== DEBUG GLOBAL ======
window.debugAuth = {
    testConnection: () => window.authClient?.testConnection(),
    getInfo: () => window.authClient?.getDebugInfo(),
    getState: () => ({
        isAuthenticated: window.authClient?.isAuthenticated(),
        userType: window.authClient?.getUserType(),
        user: window.authClient?.getUser(),
        token: window.authClient?.getToken()?.substring(0, 20) + '...'
    }),
    clearAuth: () => {
        window.authClient?.clearAuth();
        console.log('🧹 Auth limpiada');
    },
    testLogin: async (email = 'test@test.com', password = 'Password123') => {
        const result = await window.authClient?.login(email, password);
        console.log('🔐 Test login:', result);
        return result;
    },
    testPToken: async (pToken = 'CC678AVEZVKADBT') => {
        const result = await window.authClient?.validatePToken(pToken);
        console.log('🔑 Test pToken:', result);
        return result;
    }
};

console.log('🔐 AuthClient v3.0 simplificado cargado');