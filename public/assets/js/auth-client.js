class AuthClient{
    constructor(){
        this.baseURL='http://187.33.158.246/auth';
        this.token = localStorage.getItem('pToken');
        this.user = JSON.parse(localStorage.getItem('user')||'{}');
        this.init();
    }
    init(){
        document.getElementById('loginFormData')?.addEventListener('submit', (e)=> this.handleLogin(e));
        document.getElementById('registerFormData')?.addEventListener('submit', (e) => this.handleRegister(e));

        if(this.token){
            this.verifyToken();
        }
    }

    async handleLogin(e){
        e.preventDefault();
        this.showLoading();
        
        const formData = new FormData(e.target);
        const loginData = {
            email: document.getElementById('loginEmail').value,
            password: document.getElementById('loginPassword').value,
            remember: document.getElementById('rememberMe').checked
    };


    try {
            const response = await fetch(`${this.baseURL}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(loginData)
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.setAuth(result.data.token, result.data.user);
                this.showSuccess('¡Inicio de sesión exitoso!');
                this.closeAuthModal();
                this.redirectBasedOnRole(result.data.user.role);
            } else {
                this.showError(result.message || 'Error al iniciar sesión');
            }
        } catch (error) {
            console.error('Login error:', error);
            this.showError('Error de conexión. Intenta nuevamente.');
        } finally {
            this.hideLoading();
        }
    }

    async handleRegister(e) {
        e.preventDefault();
        this.showLoading();
        
        const password = document.getElementById('registerPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (password !== confirmPassword) {
            this.showError('Las contraseñas no coinciden');
            this.hideLoading();
            return;
        }
        
        const registerData = {
            firstName: document.getElementById('firstName').value,
            lastName: document.getElementById('lastName').value,
            email: document.getElementById('registerEmail').value,
            phone: document.getElementById('phone').value,
            password: password,
            role: 'patient' // Por defecto paciente
        };
        
        try {
            const response = await fetch(`${this.baseURL}/auth/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(registerData)
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess('¡Cuenta creada exitosamente! Por favor inicia sesión.');
                this.showLoginForm();
                // Limpiar formulario
                document.getElementById('registerFormData').reset();
            } else {
                this.showError(result.message || 'Error al crear la cuenta');
            }
        } catch (error) {
            console.error('Register error:', error);
            this.showError('Error de conexión. Intenta nuevamente.');
        } finally {
            this.hideLoading();
        }
    }

    async verifyToken() {
        if (!this.token) return false;
        
        try {
            const response = await fetch(`${this.baseURL}/auth/verify`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                }
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.user = result.data.user;
                    localStorage.setItem('user', JSON.stringify(this.user));
                    return true;
                }
            }
            
            // Token inválido
            this.logout();
            return false;
        } catch (error) {
            console.error('Token verification error:', error);
            this.logout();
            return false;
        }
    }
    
    setAuth(token, user) {
        this.token = token;
        this.user = user;
        localStorage.setItem('pToken', token);
        localStorage.setItem('user', JSON.stringify(user));
        
        // Disparar evento personalizado para actualizar UI
        window.dispatchEvent(new CustomEvent('authStateChanged', {
            detail: { isAuthenticated: true, user: user }
        }));
    }
    
    logout() {
        this.token = null;
        this.user = {};
        localStorage.removeItem('pToken');
        localStorage.removeItem('user');
        
        // Disparar evento de logout
        window.dispatchEvent(new CustomEvent('authStateChanged', {
            detail: { isAuthenticated: false, user: null }
        }));
        
        // Redirigir a home
        window.location.href = '/';
    }
    
    redirectBasedOnRole(role) {
        switch (role) {
            case 'admin':
            case 'supervisor':
            case 'agent':
                window.location.href = '/staff.php';
                break;
            case 'patient':
            default:
                window.location.href = '/index.php';
                break;
        }
    }

    isAuthenticated() {
        return !!this.token;
    }
    
    hasRole(role) {
        return this.user.role === role;
    }
    
    getAuthHeaders() {
        return {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
        };
    }
    
    // UI Helper methods
    showAuthModal() {
        document.getElementById('authModal').classList.remove('hidden');
    }
    
    closeAuthModal() {
        document.getElementById('authModal').classList.add('hidden');
    }
    
    showLoginForm() {
        document.getElementById('loginForm').classList.remove('hidden');
        document.getElementById('registerForm').classList.add('hidden');
    }
    
    showRegisterForm() {
        document.getElementById('loginForm').classList.add('hidden');
        document.getElementById('registerForm').classList.remove('hidden');
    }
    
    showLoading() {
        document.getElementById('loadingSpinner').classList.remove('hidden');
    }
    
    hideLoading() {
        document.getElementById('loadingSpinner').classList.add('hidden');
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showNotification(message, type = 'info') {
        // Crear elemento de notificación
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg max-w-sm ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove después de 5 segundos
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

// Funciones globales para eventos onclick
function showAuthModal() {
    window.authClient.showAuthModal();
}

function closeAuthModal() {
    window.authClient.closeAuthModal();
}

function showLoginForm() {
    window.authClient.showLoginForm();
}

function showRegisterForm() {
    window.authClient.showRegisterForm();
}

function showForgotPassword() {
    window.authClient.showError('Funcionalidad próximamente disponible');
}

function logout() {
    window.authClient.logout();
}

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', () => {
    window.authClient = new AuthClient();
    
    // Listener para cambios de autenticación
    window.addEventListener('authStateChanged', (e) => {
        const { isAuthenticated, user } = e.detail;
        
        // Actualizar UI basado en estado de autenticación
        const authButtons = document.querySelectorAll('.auth-required');
        const guestButtons = document.querySelectorAll('.guest-only');
        
        authButtons.forEach(btn => {
            btn.style.display = isAuthenticated ? 'block' : 'none';
        });
        
        guestButtons.forEach(btn => {
            btn.style.display = isAuthenticated ? 'none' : 'block';
        });
        
        // Actualizar información del usuario en UI
        if (isAuthenticated && user) {
            const userNameElements = document.querySelectorAll('.user-name');
            userNameElements.forEach(el => {
                el.textContent = `${user.firstName} ${user.lastName}`;
            });
        }
    });
});