class AuthClient {
    constructor(authServiceUrl = null) { 
        this.baseURL = authServiceUrl || 'http://localhost:3010';
        this.authServiceUrl = this.baseURL;  
        
        this.token = null;
        this.user = null;
        this.userType = 'staff';
        
        console.log('AuthClient simplificado inicializado');
        console.log('Servidor:', this.baseURL);
    }

    async login(email, password, remember = false) {
        try {
            console.log('Login staff:', email);

            const response = await fetch(`${this.baseURL}/login`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password, remember })
            });

            console.log('Respuesta login:', response.status);
            
            const result = await response.json();
            console.log('Datos login:', result);

            if (response.ok && result.success) {
                this.userType = 'staff';
                this.setAuth(result.data.access_token, result.data.user);
                console.log('Login exitoso');
                return { 
                    success: true, 
                    user: result.data.user, 
                    token: result.data.access_token,
                    data: result.data 
                };
            } else {
                console.log('Login fallido:', result.message);
                return { 
                    success: false, 
                    error: result.message || 'Credenciales inválidas' 
                };
            }
        } catch (error) {
            console.error('Error en login:', error);
            return { 
                success: false, 
                error: 'Error de conexión: ' + error.message 
            };
        }
    }

    async register(userData) {
        try {
            console.log('Registrando usuario:', userData.email);

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

            console.log('Respuesta registro:', response.status);
            
            const result = await response.json();

            if (response.ok && result.success) {
                this.userType = 'staff';
                this.setAuth(result.data.access_token, result.data.user);
                console.log('Registro exitoso');
                return { 
                    success: true, 
                    user: result.data.user, 
                    token: result.data.access_token,
                    data: result.data 
                };
            } else {
                console.log('Registro fallido:', result.message);
                return { 
                    success: false, 
                    error: result.message || 'Error en el registro' 
                };
            }
        } catch (error) {
            console.error('Error en registro:', error);
            return { 
                success: false, 
                error: 'Error de conexión: ' + error.message 
            };
        }
    }

    async verifyToken(token = null) {
        const toVerify = token || this.token;
        if (!toVerify) {
            return { success: false, error: 'Token requerido' };
        }

        const isJwt = toVerify.includes('.');
        const qs = isJwt
            ? `token=${encodeURIComponent(toVerify)}`
            : `ptoken=${encodeURIComponent(toVerify)}`;

        try {
            const res = await fetch(`${this.baseURL}/auth/validate-token?${qs}`, {
                method : 'GET',
                headers: {
                    'Accept': 'application/json',
                    ...(isJwt && { 'Authorization': `Bearer ${toVerify}` })
                }
            });

            const json = await res.json();

            if (res.ok && json.success) {
                this.userType = json.data.token_type === 'jwt' ? 'staff' : 'patient';
                if (!this.token) this.token = toVerify;
                if (json.data.payload) this.user = json.data.payload;

                return { success: true, data: json.data };
            }

            console.warn('verifyToken: inválido ->', json.message);
            return { success: false, error: json.message || 'Token inválido' };

        } catch (err) {
            console.error('verifyToken: error ->', err);
            return { success: false, error: err.message || 'Error de conexión' };
        }
    }

    async validatePToken(pToken) {
        return this.verifyToken(pToken);
    }

    async getAvailableRooms(token = null) {
        try {
            const tokenToUse = token || this.token;
            if (!tokenToUse) throw new Error('Token requerido para obtener salas');

            const response = await fetch(`${this.baseURL}/rooms/available`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${tokenToUse}`,
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            if (response.ok && result.success) {
                console.log('Salas disponibles:', result.data);
                return result.data?.rooms || result.rooms || [];
            } else {
                console.warn('Error al obtener salas:', result.message);
                return { success: false, error: result.message };
            }

        } catch (error) {
            console.error('Error getAvailableRooms:', error);
            return { success: false, error: error.message || 'Error de conexión' };
        }
    }

    async selectRoom(roomId, userData = {}, pToken = null) {
        try {
            console.log('Seleccionando sala:', roomId);
            
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
            console.log('Respuesta de sala:', result);

            if (result.success) {
                console.log('Sala seleccionada');
                return {
                    success: true,
                    ptoken: result.data?.ptoken || tokenToUse,
                    room_data: result.data,
                    room: result.data?.room
                };
            }

            throw new Error(result.message || 'Error seleccionando sala');
        } catch (error) {
            console.error('Error seleccionando sala:', error);
            return {
                success: false,
                error: error.message || 'Error de conexión'
            };
        }
    }

    setAuth(token, user) {
        this.token = token;
        this.user = user;

        if (this.userType === 'patient') {
            localStorage.setItem('pToken', token);
        } else {
            sessionStorage.setItem('staffJWT', token);
        }
        localStorage.setItem('user', JSON.stringify(user));
    }

    clearAuth() {
        this.token = null;
        this.user = null;
        this.userType = 'staff';
        
        if (localStorage.getItem('pToken')) {
            localStorage.removeItem('pToken');
            localStorage.removeItem('user');
        }
        
        console.log('Auth limpiada');
    }

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
            console.warn('No hay token para headers');
        }
        return {
            'Authorization': `Bearer ${tokenToUse}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
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

    async testConnection() {
        try {
            console.log('Probando conexión...');
            
            const response = await fetch(`${this.baseURL}/health`);
            
            if (response.ok) {
                const data = await response.json();
                console.log('Servidor OK:', data);
                this.showSuccess('Conexión exitosa');
                return true;
            } else {
                throw new Error(`Error ${response.status}`);
            }
        } catch (error) {
            console.error('Error conexión:', error);
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
            window.location.href = 'logout.php';
        }
    }
};

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
            window.authClient.showSuccess('Login exitoso');
            if (typeof closeAuthModal === 'function') closeAuthModal();
            
            setTimeout(() => {
                const userRole = window.authClient.user?.role?.name || window.authClient.user?.role;
                let normalizedRole = userRole;
                
                if (typeof userRole === 'number') {
                    const roleMap = {1: 'patient', 2: 'agent', 3: 'supervisor', 4: 'admin'};
                    normalizedRole = roleMap[userRole] || 'agent';
                }
                
                console.log('Redirigiendo según rol:', normalizedRole);
                
                if (window.authClient.isStaff()) {
                    if (normalizedRole === 'supervisor' || normalizedRole === 'admin') {
                        window.location.href = 'supervisor.php';
                    } else {
                        window.location.href = 'staff.php';
                    }
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

console.log('AuthClient v3.0 simplificado cargado');