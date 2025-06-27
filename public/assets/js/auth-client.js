// public/assets/js/auth-client.js - CORREGIDO Y SIMPLIFICADO
class AuthClient {
    constructor() { 
        this.baseURL = 'http://187.33.158.246:8080/auth';
        this.token = localStorage.getItem('pToken');
        this.user = this.getStoredUser();
        this.userType = 'staff'; // 'staff' o 'patient'
        
        console.log('🔐 AuthClient inicializado');
        console.log('🌐 Backend:', this.baseURL);
        console.log('👤 Token disponible:', !!this.token);
        
        if (this.token) {
            this.verifyToken().then(isValid => {
                if (!isValid) this.clearAuth();
                this.updateUI();
            });
        } else {
            this.updateUI();
        }
    }

    // ====== STAFF LOGIN (JWT) ======
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

            const result = await response.json();
            console.log('📡 Login response:', result);

            if (response.ok && result.success) {
                this.userType = 'staff';
                this.setAuth(result.data.access_token, result.data.user);
                console.log('✅ Login exitoso');
                return { 
                    success: true, 
                    user: result.data.user, 
                    token: result.data.access_token
                };
            } else {
                console.log('❌ Login falló:', result.message);
                return { 
                    success: false, 
                    error: result.message || 'Credenciales inválidas' 
                };
            }
        } catch (error) {
            console.error('❌ Error login:', error);
            return { 
                success: false, 
                error: 'Error de conexión: ' + error.message 
            };
        }
    }

    // ====== VALIDAR TOKEN (JWT o pToken) ======
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
            return false;
        } catch (error) {
            console.error('❌ Error verificando token:', error);
            return false;
        }
    }

    // ====== LOGOUT ======
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
            this.updateUI();
            
            // Redirigir según el tipo de usuario
            setTimeout(() => {
                if (this.userType === 'patient') {
                    window.location.href = 'https://www.tpsalud.com';
                } else {
                    window.location.href = '/index.php';
                }
            }, 500);
            
        } catch (error) {
            console.error('❌ Error logout:', error);
            this.clearAuth();
            this.updateUI();
        }
    }

    // ====== SALAS (para pacientes) ======
    async getAvailableRooms(pToken = null) {
        console.log('🏠 Obteniendo salas...');
        
        const tokenToUse = pToken || this.token;
        if (!tokenToUse) {
            throw new Error('Token requerido');
        }

        try {
            const response = await fetch(`${this.baseURL}/rooms/available?ptoken=${encodeURIComponent(tokenToUse)}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            console.log('📡 Salas response:', response.status);

            if (response.ok) {
                const data = await response.json();
                console.log('✅ Salas recibidas:', data);
                
                // Adaptarse a diferentes formatos de respuesta
                let rooms = [];
                if (data.data?.rooms) {
                    rooms = data.data.rooms;
                } else if (data.rooms) {
                    rooms = data.rooms;
                } else if (Array.isArray(data)) {
                    rooms = data;
                } else {
                    // Crear salas por defecto si el backend no las devuelve
                    rooms = [
                        {
                            id: 'general',
                            name: 'Consultas Generales',
                            description: 'Consultas generales y información básica',
                            available: true,
                            estimated_wait: '5-10 min',
                            current_queue: 0,
                            type: 'general'
                        },
                        {
                            id: 'medical',
                            name: 'Consultas Médicas',
                            description: 'Consultas médicas especializadas',
                            available: true,
                            estimated_wait: '10-15 min',
                            current_queue: 0,
                            type: 'medical'
                        },
                        {
                            id: 'emergency',
                            name: 'Emergencias',
                            description: 'Para casos de emergencia médica',
                            available: true,
                            estimated_wait: 'Inmediato',
                            current_queue: 0,
                            type: 'emergency'
                        }
                    ];
                }
                
                console.log(`✅ ${rooms.length} salas disponibles`);
                return rooms;
            } else {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(`Error ${response.status}: ${errorData.message || 'Error obteniendo salas'}`);
            }

        } catch (error) {
            console.error('❌ Error salas:', error);
            throw error;
        }
    }

    async selectRoom(roomId, userData = {}, pToken = null) {
        try {
            console.log('🎯 Seleccionando sala:', roomId);
            
            const tokenToUse = pToken || this.token;
            if (!tokenToUse) {
                throw new Error('Token requerido');
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
            console.log('📥 Select room response:', result);

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
            console.error('❌ Error select room:', error);
            return {
                success: false,
                error: error.message || 'Error de conexión'
            };
        }
    }

    // ====== GESTIÓN LOCAL ======
    setAuth(token, user) {
        this.token = token;
        this.user = user;
        
        localStorage.setItem('pToken', token);
        localStorage.setItem('user', JSON.stringify(user));
        
        console.log('💾 Auth guardada:', user.email || user.name);
        this.updateUI();
    }

    clearAuth() {
        this.token = null;
        this.user = null;
        this.userType = 'staff';
        
        localStorage.removeItem('pToken');
        localStorage.removeItem('user');
        
        console.log('🧹 Auth limpiada');
    }

    getStoredUser() {
        try {
            const userData = localStorage.getItem('user');
            return userData ? JSON.parse(userData) : null;
        } catch (error) {
            console.error('Error parsing user:', error);
            return null;
        }
    }

    setStoredUser(user) {
        localStorage.setItem('user', JSON.stringify(user));
    }

    // ====== ESTADO ======
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
        
        // Roles numéricos: 2=agent, 3=supervisor, 4=admin
        const roleMap = { 2: 'agent', 3: 'supervisor', 4: 'admin' };
        return roleMap[userRole] === role;
    }

    getAuthHeaders(token = null) {
        const tokenToUse = token || this.token;
        return {
            'Authorization': `Bearer ${tokenToUse}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    // ====== UI ======
    updateUI() {
        const isAuth = this.isAuthenticated();
        
        console.log('🔄 Actualizando UI:', { 
            isAuthenticated: isAuth, 
            user: this.user?.name || 'none',
            userType: this.userType
        });
        
        if (this.userType === 'staff') {
            // Solo para staff
            const authRequired = document.querySelectorAll('.auth-required');
            const guestOnly = document.querySelectorAll('.guest-only');
            
            authRequired.forEach(el => {
                el.style.display = isAuth ? '' : 'none';
            });
            
            guestOnly.forEach(el => {
                el.style.display = isAuth ? 'none' : '';
            });
            
            // Actualizar info del usuario
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
        }
    }

    // ====== NOTIFICACIONES ======
    showSuccess(message, duration = 3000) {
        this.showNotification(message, 'success', duration);
    }

    showError(message, duration = 5000) {
        this.showNotification(message, 'error', duration);
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all transform ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 hover:opacity-75">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, duration);
    }
}

// ====== FUNCIONES GLOBALES ======
window.showAuthModal = function(type = 'login') {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
};

window.closeAuthModal = function() {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.classList.add('hidden');
    }
};

window.logout = function() {
    if (confirm('¿Cerrar sesión?')) {
        window.authClient.logout();
    }
};

// ====== MANEJO DE LOGIN ======
window.handleLoginSubmit = async function(event) {
    event.preventDefault();
    
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    const remember = document.getElementById('rememberMe')?.checked || false;
    
    if (!email || !password) {
        window.authClient.showError('Email y contraseña requeridos');
        return;
    }
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Iniciando sesión...';
    
    try {
        const result = await window.authClient.login(email, password, remember);
        
        if (result.success) {
            window.authClient.showSuccess('¡Bienvenido!');
            if (typeof closeAuthModal === 'function') closeAuthModal();
            
            // REDIRECCIÓN CORREGIDA
            setTimeout(() => {
                if (window.authClient.isStaff()) {
                    window.location.href = '/staff.php';
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

console.log('🔐 AuthClient v2.1 cargado - KISS Version');