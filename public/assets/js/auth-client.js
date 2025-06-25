class AuthClient {
    constructor() {
        // USAR TU SERVIDOR REAL
        this.baseURL = 'http://187.33.158.246/auth';
        this.token = localStorage.getItem('pToken');
        this.user = JSON.parse(localStorage.getItem('user') || '{}');
        this.init();
    }

    init() {
        console.log('🚀 Inicializando AuthClient con servidor:', this.baseURL);
        
        // Configurar eventos de formularios
        const loginForm = document.getElementById('loginFormData');
        const registerForm = document.getElementById('registerFormData');
        
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }
        
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegister(e));
        }

        // Verificar token existente
        if (this.token) {
            this.verifyToken();
        }

        // Actualizar UI inicial
        this.updateUI();
    }

    async handleLogin(e) {
        e.preventDefault();
        this.showLoading();
        
        try {
            const loginData = {
                email: document.getElementById('loginEmail').value.trim(),
                password: document.getElementById('loginPassword').value,
                remember: document.getElementById('rememberMe')?.checked || false
            };

            console.log('🔐 Intentando login:', { email: loginData.email });

            const response = await fetch(`${this.baseURL}/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(loginData)
            });
            
            const result = await response.json();
            console.log('📝 Respuesta login:', result);
            
            if (response.ok && result.success) {
                // Guardar datos de autenticación
                this.setAuth(result.data.access_token, result.data.user);
                this.showSuccess('¡Inicio de sesión exitoso!');
                this.closeAuthModal();
                
                // Redirigir según rol
                this.redirectBasedOnRole(result.data.user.role?.name || result.data.user.role);
            } else {
                this.showError(result.message || 'Error al iniciar sesión');
            }
        } catch (error) {
            console.error('❌ Error en login:', error);
            this.showError('Error de conexión. Verifica que el servidor esté funcionando.');
        } finally {
            this.hideLoading();
        }
    }

    async handleRegister(e) {
        e.preventDefault();
        this.showLoading();
        
        try {
            const password = document.getElementById('registerPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                this.showError('Las contraseñas no coinciden');
                this.hideLoading();
                return;
            }
            
            const registerData = {
                name: `${document.getElementById('firstName').value} ${document.getElementById('lastName').value}`.trim(),
                email: document.getElementById('registerEmail').value.trim(),
                password: password,
                role: 1 // Por defecto paciente
            };

            console.log('📝 Intentando registro:', { 
                name: registerData.name, 
                email: registerData.email 
            });
            
            const response = await fetch(`${this.baseURL}/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(registerData)
            });
            
            const result = await response.json();
            console.log('📝 Respuesta registro:', result);
            
            if (response.ok && result.success) {
                // Auto-login después del registro
                this.setAuth(result.data.access_token, result.data.user);
                this.showSuccess('¡Cuenta creada exitosamente!');
                this.closeAuthModal();
                
                // Limpiar formulario
                document.getElementById('registerFormData').reset();
                
                // Redirigir según rol
                this.redirectBasedOnRole(result.data.user.role?.name || result.data.user.role);
            } else {
                this.showError(result.message || 'Error al crear la cuenta');
            }
        } catch (error) {
            console.error('❌ Error en registro:', error);
            this.showError('Error de conexión. Verifica que el servidor esté funcionando.');
        } finally {
            this.hideLoading();
        }
    }

    async verifyToken() {
        if (!this.token) return false;
        
        try {
            console.log('🔍 Verificando token existente...');
            
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
                if (result.success) {
                    console.log('✅ Token válido');
                    this.user = result.data.user || this.user;
                    localStorage.setItem('user', JSON.stringify(this.user));
                    this.updateUI();
                    return true;
                }
            }
            
            console.log('❌ Token inválido, limpiando...');
            this.logout();
            return false;
        } catch (error) {
            console.error('❌ Error verificando token:', error);
            this.logout();
            return false;
        }
    }
    
    setAuth(token, user) {
        console.log('💾 Guardando autenticación:', { 
            user_id: user.id, 
            email: user.email,
            role: user.role 
        });
        
        this.token = token;
        this.user = user;
        localStorage.setItem('pToken', token);
        localStorage.setItem('user', JSON.stringify(user));
        
        // Disparar evento personalizado
        window.dispatchEvent(new CustomEvent('authStateChanged', {
            detail: { isAuthenticated: true, user: user }
        }));
        
        this.updateUI();
    }
    
    logout() {
        console.log('👋 Cerrando sesión');
        
        this.token = null;
        this.user = {};
        localStorage.removeItem('pToken');
        localStorage.removeItem('user');
        
        // Disparar evento de logout
        window.dispatchEvent(new CustomEvent('authStateChanged', {
            detail: { isAuthenticated: false, user: null }
        }));
        
        this.updateUI();
        
        // Redirigir a home
        window.location.href = '/';
    }
    
    redirectBasedOnRole(role) {
        console.log('🎯 Redirigiendo según rol:', role);
        
        // Normalizar el rol
        const normalizedRole = typeof role === 'object' ? role.name : role;
        
        switch (normalizedRole) {
            case 'admin':
            case 'supervisor': 
            case 'agent':
                window.location.href = '/staff.php';
                break;
            case 'patient':
            default:
                // Quedarse en index.php
                window.location.reload();
                break;
        }
    }

    isAuthenticated() {
        return !!this.token && !!this.user.id;
    }
    
    hasRole(role) {
        const userRole = this.user.role?.name || this.user.role;
        return userRole === role;
    }
    
    getAuthHeaders() {
        return {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
        };
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
        
        // Actualizar nombre de usuario
        const userNameElements = document.querySelectorAll('.user-name');
        userNameElements.forEach(el => {
            if (isAuth && this.user.name) {
                el.textContent = this.user.name;
            }
        });

        console.log('🔄 UI actualizada:', { isAuthenticated: isAuth, user: this.user.name });
    }
    
    // ===== MÉTODOS DE UI =====
    
    showAuthModal() {
        const modal = document.getElementById('authModal');
        if (modal) {
            modal.classList.remove('hidden');
            // Focus en email
            setTimeout(() => {
                document.getElementById('loginEmail')?.focus();
            }, 100);
        }
    }
    
    closeAuthModal() {
        const modal = document.getElementById('authModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    showLoginForm() {
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const title = document.getElementById('authModalTitle');
        
        if (loginForm) loginForm.classList.remove('hidden');
        if (registerForm) registerForm.classList.add('hidden');
        if (title) title.textContent = 'Iniciar Sesión';
    }
    
    showRegisterForm() {
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const title = document.getElementById('authModalTitle');
        
        if (loginForm) loginForm.classList.add('hidden');
        if (registerForm) registerForm.classList.remove('hidden');
        if (title) title.textContent = 'Crear Cuenta';
    }
    
    showLoading() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.classList.remove('hidden');
        }
    }
    
    hideLoading() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.classList.add('hidden');
        }
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showNotification(message, type = 'info') {
        // Crear notificación
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all transform ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${type === 'success' ? 
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>' :
                            type === 'error' ?
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>' :
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                        }
                    </svg>
                    <span>${message}</span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animación de entrada
        setTimeout(() => notification.style.transform = 'translateX(0)', 10);
        
        // Auto remover después de 5 segundos
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    // ===== MÉTODOS PARA ROOMS =====
    
    async getAvailableRooms() {
        try {
            console.log('🏥 Obteniendo salas disponibles...');
            
            const response = await fetch(`${this.baseURL}/rooms/available`, {
                headers: this.getAuthHeaders()
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Salas obtenidas:', result.data.rooms.length);
                return result.data.rooms;
            } else {
                throw new Error(result.message || 'Error obteniendo salas');
            }
        } catch (error) {
            console.error('❌ Error obteniendo salas:', error);
            this.showError('Error cargando salas: ' + error.message);
            return [];
        }
    }
    
    async selectRoom(roomId, roomName) {
        try {
            console.log('🎯 Seleccionando sala:', { roomId, roomName });
            
            this.showLoading();
            
            const response = await fetch(`${this.baseURL}/rooms/${roomId}/select`, {
                method: 'POST',
                headers: this.getAuthHeaders(),
                body: JSON.stringify({
                    user_data: {
                        source: 'patient_portal',
                        selected_at: new Date().toISOString()
                    }
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Sala seleccionada, pToken obtenido');
                this.showSuccess(`Conectado a ${roomName}`);
                return result.data;
            } else {
                throw new Error(result.message || 'Error seleccionando sala');
            }
        } catch (error) {
            console.error('❌ Error seleccionando sala:', error);
            this.showError('Error seleccionando sala: ' + error.message);
            return null;
        } finally {
            this.hideLoading();
        }
    }

    // ===== MÉTODOS DE DESARROLLO =====
    
    async testConnection() {
        try {
            console.log('🔍 Probando conexión con servidor...');
            
            const response = await fetch(`${this.baseURL}/health`, {
                method: 'GET'
            });
            
            if (response.ok) {
                const data = await response.json();
                console.log('✅ Servidor conectado:', data);
                this.showSuccess('Conexión con servidor exitosa');
                return true;
            } else {
                throw new Error(`Error ${response.status}: ${response.statusText}`);
            }
        } catch (error) {
            console.error('❌ Error de conexión:', error);
            this.showError('No se puede conectar con el servidor: ' + error.message);
            return false;
        }
    }
}

// ===== FUNCIONES GLOBALES =====

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
    if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
        window.authClient.logout();
    }
}

// ===== FUNCIONES PARA ROOMS =====

async function loadAvailableRooms() {
    try {
        const rooms = await window.authClient.getAvailableRooms();
        displayRooms(rooms);
        document.getElementById('roomsContainer').classList.remove('hidden');
    } catch (error) {
        console.error('Error cargando salas:', error);
        window.authClient.showError('Error cargando salas disponibles');
    }
}

function displayRooms(rooms) {
    const roomsList = document.getElementById('roomsList');
    if (!roomsList) return;
    
    roomsList.innerHTML = '';
    
    rooms.forEach(room => {
        const roomElement = document.createElement('div');
        roomElement.className = 'border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-colors';
        roomElement.onclick = () => selectRoom(room.id, room.name);
        
        roomElement.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        ${getRoomIcon(room.type)}
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">${room.name}</h4>
                        <p class="text-sm text-gray-500">${room.description || 'Atención especializada'}</p>
                        <p class="text-xs text-gray-400 mt-1">
                            Tiempo estimado: ${room.estimated_wait} | 
                            Agentes disponibles: ${room.agents_online}
                        </p>
                    </div>
                </div>
                <div class="flex items-center">
                    ${room.available ? 
                        '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Disponible</span>' :
                        '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">No disponible</span>'
                    }
                </div>
            </div>
        `;
        
        roomsList.appendChild(roomElement);
    });
}

function getRoomIcon(roomType) {
    const icons = {
        'medical': '<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path></svg>',
        'general': '<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path></svg>',
        'emergency': '<svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
        'support': '<svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a9.75 9.75 0 100 19.5 9.75 9.75 0 000-19.5z"></path></svg>'
    };
    return icons[roomType] || icons['general'];
}

async function selectRoom(roomId, roomName) {
    try {
        const result = await window.authClient.selectRoom(roomId, roomName);
        
        if (result && result.ptoken) {
            console.log('🎉 Sala seleccionada exitosamente');
            initializeChat(result.ptoken, roomId, roomName);
        }
    } catch (error) {
        console.error('Error seleccionando sala:', error);
        window.authClient.showError('Error seleccionando sala');
    }
}

// ===== FUNCIONES PARA CHAT =====

function initializeChat(ptoken, roomId, roomName) {
    console.log('🚀 Inicializando chat:', { roomId, roomName });
    
    // Ocultar selección de salas
    document.getElementById('roomsContainer')?.classList.add('hidden');
    
    // Mostrar widget de chat
    const chatContainer = document.getElementById('chatContainer');
    if (!chatContainer) return;
    
    chatContainer.innerHTML = `
        <div class="fixed bottom-4 right-4 w-96 h-[600px] z-40">
            ${document.querySelector('#chatWidget').outerHTML}
        </div>
    `;
    
    // Actualizar información del chat
    document.getElementById('chatRoomName').textContent = roomName;
    document.getElementById('chatStatus').textContent = 'Conectado';
    
    // Mostrar chat widget
    const chatWidget = chatContainer.querySelector('#chatWidget');
    chatWidget.classList.remove('hidden');
    
    chatContainer.classList.remove('hidden');
    
    // Aquí conectarías con el WebSocket del chat-service
    console.log('💬 Chat listo para uso con pToken:', ptoken.substring(0, 15) + '...');
}

function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    // Añadir mensaje a la UI
    addMessageToChat(message, 'user');
    
    // Limpiar input
    messageInput.value = '';
    messageInput.style.height = 'auto';
    document.getElementById('charCount').textContent = '0';
    document.getElementById('sendButton').disabled = true;
    
    // Simular respuesta (aquí conectarías con WebSocket)
    setTimeout(() => {
        addMessageToChat('Gracias por tu mensaje. Un profesional te atenderá en breve.', 'system');
    }, 1000);
    
    console.log('📤 Mensaje enviado:', message);
}

function addMessageToChat(message, sender) {
    const messagesContainer = document.getElementById('chatMessages');
    const messageElement = document.createElement('div');
    
    const isUser = sender === 'user';
    const alignClass = isUser ? 'justify-end' : 'justify-start';
    const bgClass = isUser ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200';
    const textClass = isUser ? 'text-white' : 'text-gray-900';
    
    messageElement.className = `flex ${alignClass} mb-3`;
    messageElement.innerHTML = `
        <div class="max-w-xs lg:max-w-md">
            <div class="${bgClass} rounded-2xl px-4 py-3 shadow-sm">
                <p class="text-sm ${textClass}">${message}</p>
                <p class="text-xs ${isUser ? 'text-blue-100' : 'text-gray-500'} mt-1">
                    ${new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })}
                </p>
            </div>
        </div>
    `;
    
    messagesContainer.appendChild(messageElement);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function endChat() {
    if (confirm('¿Estás seguro de que quieres terminar el chat?')) {
        document.getElementById('chatContainer')?.classList.add('hidden');
        document.getElementById('roomsContainer')?.classList.add('hidden');
        console.log('💬 Chat terminado');
    }
}

// ===== INICIALIZACIÓN =====

document.addEventListener('DOMContentLoaded', () => {
    console.log('🎬 Inicializando aplicación médica...');
    
    // Crear instancia global del cliente de autenticación
    window.authClient = new AuthClient();
    
    // Probar conexión en desarrollo
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        setTimeout(() => {
            window.authClient.testConnection();
        }, 1000);
    }
    
    // Listener para cambios de autenticación
    window.addEventListener('authStateChanged', (e) => {
        const { isAuthenticated, user } = e.detail;
        console.log('🔄 Estado de autenticación cambió:', { isAuthenticated, user: user?.name });
    });
    
    console.log('✅ Aplicación médica lista');
});

// ===== DEBUGGING (SOLO DESARROLLO) =====

if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    window.debugAuth = {
        testLogin: () => {
            document.getElementById('loginEmail').value = 'test@ejemplo.com';
            document.getElementById('loginPassword').value = 'Test123456';
            document.getElementById('loginFormData').dispatchEvent(new Event('submit'));
        },
        getState: () => ({
            token: window.authClient.token,
            user: window.authClient.user,
            isAuth: window.authClient.isAuthenticated()
        }),
        clearAuth: () => window.authClient.logout()
    };
    
    console.log('🛠️ Modo desarrollo: usa window.debugAuth para testing');
}