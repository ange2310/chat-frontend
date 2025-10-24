
        class AdminClient {
            constructor() {
                this.adminServiceUrl = 'http://187.33.158.246/admin';
                this.authServiceUrl = 'http://187.33.158.246/auth';
                this.refreshInterval = null;
                this.refreshIntervalTime = 30000;
                this.currentEditingRoom = null;
                this.currentEditingAssignment = null;
                this.currentScheduleAssignment = null;
                this.roomColors = [
                    'bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-pink-500', 
                    'bg-yellow-500', 'bg-indigo-500', 'bg-red-500', 'bg-orange-500',
                    'bg-teal-500', 'bg-cyan-500', 'bg-lime-500', 'bg-emerald-500'
                ];
                
                this.initializeNavigation();
            }
            

            initializeNavigation() {
                const navigationItems = [
                    {
                        id: 'dashboard',
                        name: 'Dashboard',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                               </svg>`,
                        active: true
                    },
                    {
                        id: 'users',
                        name: 'Usuarios',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                               </svg>`
                    },
                    {
                        id: 'rooms',
                        name: 'Salas',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                               </svg>`
                    },
                    {
                        id: 'assignments',
                        name: 'Asignaciones',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                               </svg>`
                    },
                    {
                        id: 'reports',
                        name: 'Reportes',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                               </svg>`
                    },
                    {
                        id: 'group-chat',
                        name: 'Chat Grupal',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>`
                    },
                    {
                        id: 'profile',
                        name: 'Mi Perfil',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>`
                    },
                    {
                        id: 'config',
                        name: 'Configuración',
                        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                               </svg>`
                    }
                ];

                const desktopNav = document.getElementById('desktopNav');
                if (desktopNav) {
                    desktopNav.innerHTML = navigationItems.map(item => `
                        <a href="#${item.id}" onclick="showSection('${item.id}'); closeMobileNav();" 
                           id="nav-${item.id}" class="nav-link ${item.active ? 'active' : ''}">
                            ${item.icon}
                            ${item.name}
                        </a>
                    `).join('');
                }

                const mobileNavItems = document.getElementById('mobileNavItems');
                if (mobileNavItems) {
                    mobileNavItems.innerHTML = navigationItems.map(item => `
                        <a href="#${item.id}" onclick="showSection('${item.id}'); closeMobileNav();" 
                           id="mobile-nav-${item.id}" class="nav-link ${item.active ? 'active' : ''}">
                            ${item.icon}
                            ${item.name}
                        </a>
                    `).join('');
                }
            }

            getToken() {
                const phpTokenMeta = document.querySelector('meta[name="admin-token"]')?.content;
                if (phpTokenMeta && phpTokenMeta.trim() !== '') {
                    return phpTokenMeta;
                }
                return null;
            }

            getAuthHeaders() {
                const token = this.getToken();
                if (!token) throw new Error('Token no disponible');
                
                return {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                };
            }

            getCurrentUser() {
                const userMeta = document.querySelector('meta[name="admin-user"]');
                if (userMeta && userMeta.content) {
                    try {
                        return JSON.parse(userMeta.content);
                    } catch (e) {
                        return null;
                    }
                }
                return null;
            }

            getRandomColor() {
                return this.roomColors[Math.floor(Math.random() * this.roomColors.length)];
            }

            getColorForRoom(room) {
                if (room.color && room.color.trim() !== '') {
                    return room.color;
                }
                
                if (room.id) {
                    const index = parseInt(room.id.toString().slice(-1)) % this.roomColors.length;
                    return this.roomColors[index];
                }
                
                if (room.name) {
                    let hash = 0;
                    for (let i = 0; i < room.name.length; i++) {
                        hash = room.name.charCodeAt(i) + ((hash << 5) - hash);
                    }
                    const index = Math.abs(hash) % this.roomColors.length;
                    return this.roomColors[index];
                }
                
                return this.roomColors[0];
            }

            // Añadir después de las funciones existentes
            validatePasswordMatch(password1Id, password2Id, indicatorId) {
                const password1 = document.getElementById(password1Id)?.value || '';
                const password2 = document.getElementById(password2Id)?.value || '';
                const indicator = document.getElementById(indicatorId);
                
                if (!indicator) return;
                
                if (password2 === '') {
                    indicator.innerHTML = '';
                    return;
                }
                
                if (password1 === password2) {
                    indicator.innerHTML = '<span class="text-green-600 text-xs">✓ Las contraseñas coinciden</span>';
                } else {
                    indicator.innerHTML = '<span class="text-red-600 text-xs">✗ Las contraseñas no coinciden</span>';
                }
            }

            validatePasswordStrength(passwordId, indicatorId) {
                const password = document.getElementById(passwordId)?.value || '';
                const indicator = document.getElementById(indicatorId);
                
                if (!indicator) return;
                
                if (password === '') {
                    indicator.innerHTML = '';
                    return;
                }
                
                let strength = 0;
                let messages = [];
                
                if (password.length >= 8) {
                    strength++;
                    messages.push('<span class="text-green-600">✓ Mínimo 8 caracteres</span>');
                } else {
                    messages.push('<span class="text-red-600">✗ Mínimo 8 caracteres</span>');
                }
                
                if (/[A-Z]/.test(password)) {
                    strength++;
                    messages.push('<span class="text-green-600">✓ Mayúscula</span>');
                }
                
                if (/[0-9]/.test(password)) {
                    strength++;
                    messages.push('<span class="text-green-600">✓ Número</span>');
                }
                
                if (!/^[a-zA-Z0-9@#$%^&*()_+=\-\[\]{}|\\:";'<>?,./]*$/.test(password)) {
                    messages.push('<span class="text-red-600">✗ Caracteres no válidos (evite ñ, acentos)</span>');
                }
                
                indicator.innerHTML = '<div class="text-xs space-y-1">' + messages.join('<br>') + '</div>';
            }

            // ===== GESTIÓN DE USUARIOS =====
            
            // ===== GESTIÓN DE PERFIL =====

            async loadProfile() {
                try {
                    const response = await fetch(`${this.authServiceUrl}/users/profile`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const user = result.data?.user || result.user || {};
                        
                        this.displayProfile(user);
                        return user;
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                    
                } catch (error) {
                    this.displayProfile({});
                    this.showError('Error cargando perfil: ' + error.message);
                }
            }

            displayProfile(user) {
                const container = document.getElementById('profileContainer');
                if (!container) return;

                const roleNames = {
                    1: 'Cliente',
                    2: 'Agente',
                    3: 'Supervisor',
                    4: 'Administrador'
                };
                const roleName = roleNames[user.role] || 'Desconocido';
                const statusClass = user.is_active ? 'status-active' : 'status-inactive';
                const availabilityClass = user.disponibilidad === 'presente' ? 'bg-green-100 text-green-800' : 
                                        user.disponibilidad === 'ausente' ? 'bg-red-100 text-red-800' : 
                                        'bg-gray-100 text-gray-800';

                container.innerHTML = `
                    <div class="max-w-4xl mx-auto space-y-6">
                        <!-- Información Personal -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-gray-900">Información Personal</h3>
                                <button onclick="showEditProfileModal()" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                    Editar Perfil
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center">
                                        <span class="text-xl font-semibold text-indigo-700">${this.getUserInitials(user.name)}</span>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-semibold text-gray-900">${user.name || 'Sin nombre'}</h4>
                                        <p class="text-gray-500">${user.email}</p>
                                        <div class="flex items-center space-x-2 mt-1">
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                                                ${roleName}
                                            </span>
                                            <span class="px-2 py-1 ${statusClass} text-xs font-medium rounded-full">
                                                ${user.is_active ? 'Activo' : 'Inactivo'}
                                            </span>
                                            ${user.disponibilidad ? `
                                                <span class="px-2 py-1 ${availabilityClass} text-xs font-medium rounded-full">
                                                    ${user.disponibilidad}
                                                </span>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="space-y-3">
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">ID Usuario</span>
                                        <p class="text-sm text-gray-900">${user.id}</p>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">Último Acceso</span>
                                        <p class="text-sm text-gray-900">${user.last_login ? this.formatDate(user.last_login) : 'Nunca'}</p>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">Cuenta Creada</span>
                                        <p class="text-sm text-gray-900">${this.formatDate(user.created_at)}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seguridad -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-6">Seguridad</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-gray-900">Contraseña</h4>
                                        <p class="text-sm text-gray-500">Cambiar tu contraseña actual</p>
                                    </div>
                                    <button onclick="showChangePasswordModal()" 
                                            class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 text-sm">
                                        Cambiar Contraseña
                                    </button>
                                </div>
                                
                                ${user.role >= 3 ? `
                                    <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg">
                                        <div>
                                            <h4 class="font-medium text-gray-900">Resetear Contraseñas</h4>
                                            <p class="text-sm text-gray-500">Cambiar contraseña de cualquier usuario (privilegio de administrador)</p>
                                        </div>
                                        <button onclick="showResetPasswordModal()" 
                                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                            Resetear Contraseñas
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }

            async updateProfile() {
                try {
                    const name = document.getElementById('profileName').value.trim();
                    const email = document.getElementById('profileEmail').value.trim();
                    
                    if (!name || !email) {
                        this.showError('Nombre y email son requeridos');
                        return;
                    }

                    const response = await fetch(`${this.authServiceUrl}/users/profile`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            name,
                            email
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Perfil actualizado exitosamente');
                        closeModal('editProfileModal');
                        await this.loadProfile();
                    } else {
                        throw new Error(result.message || 'Error actualizando perfil');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async changeOwnPassword() {
                try {
                    const currentPassword = document.getElementById('currentPassword').value;
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;
                    
                    // Validaciones más específicas
                    if (!currentPassword) {
                        this.showError('La contraseña actual es requerida');
                        return;
                    }

                    if (!newPassword) {
                        this.showError('La nueva contraseña es requerida');
                        return;
                    }

                    if (!confirmPassword) {
                        this.showError('Debes confirmar la nueva contraseña');
                        return;
                    }

                    if (newPassword !== confirmPassword) {
                        this.showError('Las nuevas contraseñas no coinciden');
                        return;
                    }

                    if (newPassword.length < 8) {
                        this.showError('La nueva contraseña debe tener al menos 8 caracteres');
                        return;
                    }

                    // Validar caracteres permitidos
                    if (!/^[a-zA-Z0-9@#$%^&*()_+=\-\[\]{}|\\:";'<>?,./]*$/.test(newPassword)) {
                        this.showError('La contraseña contiene caracteres no válidos. Use solo letras, números y símbolos estándar');
                        return;
                    }

                    const response = await fetch(`${this.authServiceUrl}/users/change-password`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            current_password: currentPassword,
                            new_password: newPassword,
                            confirm_password: confirmPassword
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Contraseña cambiada exitosamente');
                        closeModal('changePasswordModal');
                        document.getElementById('changePasswordForm').reset();
                    } else {
                        // Manejar errores específicos del servidor
                        if (response.status === 400) {
                            if (result.message?.includes('current_password')) {
                                this.showError('La contraseña actual es incorrecta');
                            } else if (result.message?.includes('password')) {
                                this.showError('Error de validación: ' + result.message);
                            } else {
                                this.showError('Datos inválidos: ' + (result.message || 'Verifique los campos'));
                            }
                        } else if (response.status === 401) {
                            this.showError('No autorizado para cambiar contraseña');
                        } else if (response.status === 422) {
                            this.showError('Error de validación: ' + (result.message || 'Contraseña no válida'));
                        } else {
                            this.showError(result.message || `Error del servidor (${response.status})`);
                        }
                    }
                    
                } catch (error) {
                    this.showError('Error de conexión: ' + error.message);
                }
            }

            async loadUsersForPasswordReset() {
                try {
                    const response = await fetch(`${this.authServiceUrl}/users/staff`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const users = result.data?.users || result.users || [];
                        
                        const roleNames = {
                            2: 'Agente',
                            3: 'Supervisor',
                            4: 'Administrador'
                        };

                        const select = document.getElementById('resetUserSelect');
                        if (select) {
                            select.innerHTML = '<option value="">Seleccionar usuario...</option>' +
                                users.map(user => `<option value="${user.id}">${user.name} (${user.email}) - ${roleNames[user.role] || 'Rol ' + user.role}</option>`).join('');
                        }
                        
                        return users;
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                    
                } catch (error) {
                    const select = document.getElementById('resetUserSelect');
                    if (select) {
                        select.innerHTML = '<option value="">Error cargando usuarios</option>';
                    }
                    
                    this.showError('Error cargando usuarios: ' + error.message);
                }
            }

            async resetUserPassword() {
                try {
                    const userId = document.getElementById('resetUserSelect').value;
                    const newPassword = document.getElementById('resetPassword').value;
                    const confirmResetPassword = document.getElementById('confirmResetPassword').value;
                    
                    if (!userId || userId.trim() === '') {
                        this.showError('Debes seleccionar un usuario');
                        return;
                    }

                    if (!newPassword) {
                        this.showError('La nueva contraseña es requerida');
                        return;
                    }

                    if (!confirmResetPassword) {
                        this.showError('Debes confirmar la nueva contraseña');
                        return;
                    }

                    if (newPassword !== confirmResetPassword) {
                        this.showError('Las contraseñas no coinciden');
                        return;
                    }

                    if (newPassword.length < 8) {
                        this.showError('La contraseña debe tener al menos 8 caracteres');
                        return;
                    }

                    // Validar caracteres permitidos
                    if (!/^[a-zA-Z0-9@#$%^&*()_+=\-\[\]{}|\\:";'<>?,./]*$/.test(newPassword)) {
                        this.showError('La contraseña contiene caracteres no válidos. Use solo letras, números y símbolos estándar');
                        return;
                    }

                    if (!confirm('¿Estás seguro de resetear la contraseña de este usuario? Esta acción no se puede deshacer.')) {
                        return;
                    }

                    const response = await fetch(`${this.authServiceUrl}/users/${userId}/password`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            password: newPassword
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Contraseña reseteada exitosamente');
                        closeModal('resetPasswordModal');
                        document.getElementById('resetPasswordForm').reset();
                    } else {
                        // Manejar errores específicos del servidor
                        if (response.status === 400) {
                            if (result.errors) {
                                const errorMessages = Object.values(result.errors).flat().join(', ');
                                this.showError('Errores de validación: ' + errorMessages);
                            } else {
                                this.showError('Datos inválidos: ' + (result.message || 'Verifique los campos'));
                            }
                        } else if (response.status === 401) {
                            this.showError('No autorizado para resetear contraseñas');
                        } else if (response.status === 404) {
                            this.showError('Usuario no encontrado');
                        } else if (response.status === 422) {
                            this.showError('Error de validación: ' + (result.message || 'Contraseña no válida'));
                        } else {
                            this.showError(result.message || `Error del servidor (${response.status})`);
                        }
                    }
                    
                } catch (error) {
                    this.showError('Error de conexión: ' + error.message);
                }
            }
            async loadUsers() {
                try {
                    const response = await fetch(`${this.authServiceUrl}/users/staff`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const users = result.data?.users || result.users || [];
                        
                        this.displayUsers(users);
                        return users;
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                    
                } catch (error) {
                    this.displayUsers([]);
                    this.showError('Error cargando usuarios: ' + error.message);
                }
            }

            displayUsers(users) {
                const container = document.getElementById('usersContainer');
                if (!container) return;

                if (users.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-12">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                            <p class="text-gray-500">No hay usuarios registrados</p>
                            <button onclick="showCreateUserModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                Crear Primer Usuario
                            </button>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="space-y-4">
                        ${users.map(user => this.createUserRow(user)).join('')}
                    </div>
                `;
            }

            createUserRow(user) {
                const statusClass = user.is_active ? 'status-active' : 'status-inactive';
                const roleNames = {
                    2: 'Agente',
                    3: 'Supervisor',
                    4: 'Administrador'
                };
                const roleName = roleNames[user.role] || 'Desconocido';
                
                return `
                    <div class="user-row">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center space-x-4">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs sm:text-sm font-semibold text-indigo-700">${this.getUserInitials(user.name)}</span>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900 text-sm sm:text-base">${user.name || 'Sin nombre'}</h4>
                                    <p class="text-xs sm:text-sm text-gray-500">${user.email}</p>
                                    <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-xs text-gray-500 mt-1">
                                        <span>Rol: ${roleName}</span>
                                        <span>ID: ${user.id}</span>
                                        ${user.last_login ? `<span>Último login: ${this.formatDate(user.last_login)}</span>` : '<span>Nunca ha ingresado</span>'}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-4">
                                <span class="px-2 py-1 ${statusClass} text-xs font-medium rounded-full">
                                    ${user.is_active ? 'Activo' : 'Inactivo'}
                                </span>
                                
                                <div class="flex gap-1 sm:gap-2">
                                    <button onclick="adminClient.toggleUserStatus('${user.id}')" 
                                            class="px-2 py-1 sm:px-3 sm:py-1 ${user.is_active ? 'bg-yellow-600' : 'bg-green-600'} text-white text-xs rounded hover:opacity-80 btn-responsive">
                                        ${user.is_active ? 'Desactivar' : 'Activar'}
                                    </button>
                                    <button onclick="showResetPasswordModal(); setTimeout(() => { document.getElementById('resetUserSelect').value = '${user.id}'; }, 100);"
                                            class="px-2 py-1 sm:px-3 sm:py-1 bg-orange-600 text-white text-xs rounded hover:bg-orange-700 btn-responsive">
                                        Reset Pass
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3 text-xs sm:text-sm text-gray-600">
                            <div class="flex flex-col sm:flex-row sm:space-x-4">
                                <span>Creado: ${this.formatDate(user.created_at)}</span>
                                ${user.updated_at ? `<span>Actualizado: ${this.formatDate(user.updated_at)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }

            async createUser() {
                try {
                    const name = document.getElementById('userName').value.trim();
                    const email = document.getElementById('userEmail').value.trim();
                    const password = document.getElementById('userPassword').value;
                    const passwordConfirm = document.getElementById('userPasswordConfirm').value;
                    const role = parseInt(document.getElementById('userRole').value);
                    
                    if (!name || !email || !password || !role) {
                        this.showError('Todos los campos son requeridos');
                        return;
                    }

                    if (password !== passwordConfirm) {
                        this.showError('Las contraseñas no coinciden');
                        return;
                    }

                    if (password.length < 8) {
                        this.showError('La contraseña debe tener al menos 8 caracteres');
                        return;
                    }
                    
                    const response = await fetch(`${this.authServiceUrl}/register`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            name,
                            email,
                            password,
                            role
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Usuario creado exitosamente');
                        closeModal('createUserModal');
                        await this.loadUsers();
                        document.getElementById('createUserForm').reset();
                    } else {
                        throw new Error(result.message || 'Error creando usuario');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async toggleUserStatus(userId) {
                if (!confirm('¿Cambiar estado del usuario?')) {
                    return;
                }

                try {
                    const response = await fetch(`${this.authServiceUrl}/users/${userId}/toggle`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders()
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Estado del usuario actualizado');
                        await this.loadUsers();
                    } else {
                        throw new Error(result.message || 'Error actualizando estado');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            getUserInitials(name) {
                if (!name) return 'U';
                return name.split(' ').map(part => part.charAt(0)).join('').substring(0, 2).toUpperCase();
            }

            // ===== RESTO DE FUNCIONES DEL DASHBOARD =====

            async loadDashboard() {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/reports/dashboard`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const result = await response.json();

                    let dashboardData = null;
                    
                    if (result.success && result.data) {
                        dashboardData = result.data;
                    } else if (result.data) {
                        dashboardData = result.data;
                    } else if (result.dashboard_data || result.metrics) {
                        dashboardData = result;
                    } else {
                        dashboardData = result;
                    }
                    
                    if (dashboardData) {
                        this.updateDashboardStats(dashboardData);
                        return dashboardData;
                    } else {
                        throw new Error('No se encontraron datos de dashboard válidos');
                    }
                    
                } catch (error) {
                    this.updateDashboardStats({});
                    this.showError('Error cargando dashboard: ' + error.message);
                    throw error;
                }
            }

            updateDashboardStats(data) {
                try {
                    const dashboardData = data.dashboard_data || {};
                    const metrics = data.metrics || {};
                    const summary = metrics.summary || {};
                    
                    const sessions = dashboardData.sessions || {};
                    const agents = dashboardData.agents || {};
                    const rooms = dashboardData.rooms || {};
                    
                    const updates = {
                        'stat-total-rooms': rooms.total || summary.active_rooms || 0,
                        'stat-total-agents': agents.total || summary.online_agents || 0,
                        'stat-active-sessions': sessions.active || summary.active_sessions || 0,
                        'stat-completed-sessions': sessions.completed || 0,
                        'stat-waiting-sessions': sessions.waiting || summary.waiting_sessions || 0,
                        'stat-total-sessions': sessions.total || 0,
                        'stat-active-sessions-detail': sessions.active || summary.active_sessions || 0
                    };

                    Object.entries(updates).forEach(([id, value]) => {
                        const element = document.getElementById(id);
                        if (element) {
                            element.textContent = value;
                        }
                    });
                    
                } catch (error) {
                    console.error('Error actualizando dashboard stats:', error);
                }
            }

            async refreshDashboard() {
                this.showNotification('Actualizando métricas...', 'info');
                await this.loadDashboard();
                this.showNotification('Métricas actualizadas', 'success');
            }

            async loadRooms() {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms?include_stats=true`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const rooms = result.data?.rooms || result.rooms || [];
                        
                        this.displayRooms(rooms);
                        return rooms;
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                    
                } catch (error) {
                    this.displayRooms([]);
                    this.showError('Error cargando salas: ' + error.message);
                }
            }

            displayRooms(rooms) {
                const container = document.getElementById('roomsContainer');
                if (!container) return;

                if (rooms.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-12">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                            </svg>
                            <p class="text-gray-500">No hay salas registradas</p>
                            <button onclick="showCreateRoomModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                Crear Primera Sala
                            </button>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
                        ${rooms.map(room => this.createRoomCard(room)).join('')}
                    </div>
                `;
            }

            createRoomCard(room) {
                const statusClass = this.getRoomStatusClass(room.status);
                const statusText = this.getRoomStatusText(room.status);
                const roomColor = this.getColorForRoom(room);
                
                return `
                    <div class="room-card">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 sm:w-12 sm:h-12 ${roomColor} rounded-lg"></div>
                                <div>
                                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900 truncate">${room.name}</h3>
                                    <p class="text-xs sm:text-sm text-gray-500 capitalize">${room.room_type || 'General'}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 ${statusClass} text-xs font-medium rounded-full">${statusText}</span>
                                ${room.is_active ? 
                                    '<div class="w-2 h-2 bg-green-500 rounded-full" title="Activa"></div>' : 
                                    '<div class="w-2 h-2 bg-gray-300 rounded-full" title="Inactiva"></div>'
                                }
                            </div>
                        </div>
                        
                        <p class="text-gray-600 text-xs sm:text-sm mb-4 line-clamp-2">${room.description || 'Sin descripción'}</p>
                        
                        <div class="grid grid-cols-3 gap-2 sm:gap-4 text-center mb-4">
                            <div>
                                <div class="text-sm sm:text-lg font-bold text-blue-600">${room.max_agents || 10}</div>
                                <div class="text-xs text-gray-500">Máx.</div>
                            </div>
                            <div>
                                <div class="text-sm sm:text-lg font-bold text-green-600">${room.assigned_agents || 0}</div>
                                <div class="text-xs text-gray-500">Asign.</div>
                            </div>
                            <div>
                                <div class="text-sm sm:text-lg font-bold text-purple-600">${room.active_agents || 0}</div>
                                <div class="text-xs text-gray-500">Activos</div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button onclick="adminClient.editRoom('${room.id}')" 
                                    class="flex-1 px-2 py-1 sm:px-3 sm:py-2 bg-blue-600 text-white text-xs sm:text-sm rounded hover:bg-blue-700 btn-responsive">
                                Editar
                            </button>
                            <button onclick="adminClient.toggleRoomStatus('${room.id}')" 
                                    class="flex-1 px-2 py-1 sm:px-3 sm:py-2 bg-yellow-600 text-white text-xs sm:text-sm rounded hover:bg-yellow-700 btn-responsive">
                                ${room.is_active ? 'Desact.' : 'Activar'}
                            </button>
                            <button onclick="adminClient.deleteRoom('${room.id}')" 
                                    class="px-2 py-1 sm:px-3 sm:py-2 bg-red-600 text-white text-xs sm:text-sm rounded hover:bg-red-700 btn-icon-only">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
            }

            async loadDeletedRooms() {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms/deleted`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const deletedRooms = result.data?.rooms || result.rooms || [];
                        
                        this.displayDeletedRooms(deletedRooms);
                        return deletedRooms;
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                    
                } catch (error) {
                    this.displayDeletedRooms([]);
                    this.showError('Error cargando papelera: ' + error.message);
                }
            }

            displayDeletedRooms(deletedRooms) {
                const container = document.getElementById('deletedRoomsContainer');
                if (!container) return;

                if (deletedRooms.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-12">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <p class="text-gray-500">No hay salas en la papelera</p>
                            <p class="text-sm text-gray-400 mt-2">Las salas eliminadas aparecerán aquí</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="space-y-3">
                        ${deletedRooms.map(room => this.createDeletedRoomCard(room)).join('')}
                    </div>
                `;
            }

            createDeletedRoomCard(room) {
                return `
                    <div class="deleted-room-card">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">${room.name}</h4>
                                    <p class="text-sm text-gray-500">
                                        ${room.room_type || 'General'} • 
                                        Eliminada: ${this.formatDate(room.deleted_at)}
                                    </p>
                                    <p class="text-xs text-gray-400">${room.description || 'Sin descripción'}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                    ${room.had_assignments || 0} asignaciones
                                </span>
                                <button onclick="adminClient.restoreRoom('${room.id}')" 
                                        class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 btn-responsive">
                                    <svg class="w-3 h-3 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Restaurar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            async restoreRoom(roomId) {
                if (!confirm('¿Estás seguro de restaurar esta sala? Se activará nuevamente.')) {
                    return;
                }

                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms/${roomId}/restore`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            reason: 'Restauración desde panel de administrador'
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Sala restaurada exitosamente');
                        await this.loadDeletedRooms();
                        await this.loadRooms();
                    } else {
                        throw new Error(result.message || 'Error restaurando sala');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async createRoom() {
                try {
                    const name = document.getElementById('roomName').value.trim();
                    const description = document.getElementById('roomDescription').value.trim();
                    const room_type = document.getElementById('roomType').value;
                    const max_agents = parseInt(document.getElementById('roomMaxAgents').value);
                    const priority = parseInt(document.getElementById('roomPriority').value);
                    const color = this.getRandomColor();
                    
                    if (!name) {
                        this.showError('El nombre es requerido');
                        return;
                    }
                    
                    const response = await fetch(`${this.adminServiceUrl}/rooms`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            name,
                            description,
                            room_type,
                            max_agents,
                            priority,
                            color
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Sala creada exitosamente');
                        closeModal('createRoomModal');
                        await this.loadRooms();
                        document.getElementById('createRoomForm').reset();
                    } else {
                        throw new Error(result.message || 'Error creando sala');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async editRoom(roomId) {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        const rooms = result.data?.rooms || result.rooms || [];
                        const room = rooms.find(r => r.id === roomId);
                        
                        if (room) {
                            this.currentEditingRoom = room;
                            this.populateEditRoomModal(room);
                            document.getElementById('editRoomModal').classList.remove('hidden');
                        } else {
                            this.showError('Sala no encontrada');
                        }
                    }
                    
                } catch (error) {
                    this.showError('Error cargando datos de sala');
                }
            }

            populateEditRoomModal(room) {
                document.getElementById('editRoomName').value = room.name || '';
                document.getElementById('editRoomDescription').value = room.description || '';
                document.getElementById('editRoomType').value = room.room_type || 'general';
                document.getElementById('editRoomMaxAgents').value = room.max_agents || 10;
                document.getElementById('editRoomPriority').value = room.priority || 1;
                document.getElementById('editRoomStatus').value = room.status || 'available';
                document.getElementById('editRoomIsActive').checked = room.is_active !== false;
            }

            async saveRoomChanges() {
                try {
                    if (!this.currentEditingRoom) {
                        this.showError('No hay sala seleccionada para editar');
                        return;
                    }
                    
                    const updateData = {
                        name: document.getElementById('editRoomName').value.trim(),
                        description: document.getElementById('editRoomDescription').value.trim(),
                        room_type: document.getElementById('editRoomType').value,
                        max_agents: parseInt(document.getElementById('editRoomMaxAgents').value),
                        priority: parseInt(document.getElementById('editRoomPriority').value),
                        status: document.getElementById('editRoomStatus').value,
                        is_active: document.getElementById('editRoomIsActive').checked
                    };
                    
                    if (!updateData.name) {
                        this.showError('El nombre es requerido');
                        return;
                    }
                    
                    const response = await fetch(`${this.adminServiceUrl}/rooms/${this.currentEditingRoom.id}`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify(updateData)
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Sala actualizada exitosamente');
                        closeModal('editRoomModal');
                        this.currentEditingRoom = null;
                        await this.loadRooms();
                    } else {
                        throw new Error(result.message || 'Error actualizando sala');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async toggleRoomStatus(roomId) {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms/${roomId}/toggle`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            reason: 'Cambio manual desde panel de administrador'
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Estado de sala actualizado');
                        await this.loadRooms();
                    } else {
                        throw new Error(result.message || 'Error actualizando estado');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async deleteRoom(roomId) {
                if (!confirm('¿Estás seguro de eliminar esta sala? Esta acción se puede deshacer desde la papelera.')) {
                    return;
                }
                
                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms/${roomId}`, {
                        method: 'DELETE',
                        headers: this.getAuthHeaders()
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Sala eliminada exitosamente');
                        await this.loadRooms();
                    } else {
                        throw new Error(result.message || 'Error eliminando sala');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async loadAssignments() {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/assignments?include_schedules=true`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const assignments = result.data?.assignments || result.assignments || [];
                        
                        this.displayAssignments(assignments);
                        return assignments;
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                    
                } catch (error) {
                    this.displayAssignments([]);
                    this.showError('Error cargando asignaciones: ' + error.message);
                }
            }

            displayAssignments(assignments) {
                const container = document.getElementById('assignmentsContainer');
                if (!container) return;

                if (assignments.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-12">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                            <p class="text-gray-500">No hay asignaciones registradas</p>
                            <button onclick="showAssignAgentModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                Crear Primera Asignación
                            </button>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="space-y-4">
                        ${assignments.map(assignment => this.createAssignmentRow(assignment)).join('')}
                    </div>
                `;
            }

            createAssignmentRow(assignment) {
                const statusClass = assignment.status === 'active' ? 'status-active' : 'status-inactive';
                
                return `
                    <div class="assignment-row">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center space-x-4">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs sm:text-sm font-semibold text-blue-700">${this.getAgentInitials(assignment.agent_name)}</span>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900 text-sm sm:text-base">${assignment.agent_name || 'Agente'}</h4>
                                    <p class="text-xs sm:text-sm text-gray-500">Sala: ${assignment.room_name || assignment.room_id}</p>
                                    <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-xs text-gray-500 mt-1">
                                        <span>Prior.: ${assignment.priority || 1}</span>
                                        <span>Máx.: ${assignment.max_concurrent_chats || 5}</span>
                                        ${assignment.is_primary_agent ? '<span class="text-yellow-600 font-medium">★ Principal</span>' : ''}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-4">
                                <span class="px-2 py-1 ${statusClass} text-xs font-medium rounded-full">
                                    ${assignment.status === 'active' ? 'Activo' : 'Inactivo'}
                                </span>
                                
                                <div class="flex gap-1 sm:gap-2">
                                    <button onclick="adminClient.editAssignment('${assignment.id}')" 
                                            class="px-2 py-1 sm:px-3 sm:py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 btn-responsive">
                                        Editar
                                    </button>
                                    <button onclick="adminClient.removeAssignment('${assignment.id}')" 
                                            class="px-2 py-1 sm:px-3 sm:py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 btn-responsive">
                                        Remover
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3 text-xs sm:text-sm text-gray-600">
                            <div class="flex flex-col sm:flex-row sm:space-x-4">
                                <span>Asignado: ${this.formatDate(assignment.assigned_at)}</span>
                                ${assignment.updated_at ? `<span>Actualizado: ${this.formatDate(assignment.updated_at)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }

            async loadAvailableAgents() {
                try {
                    const response = await fetch(`${this.authServiceUrl}/users/agents/available`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const agents = result.data?.agents || result.agents || [];
                        
                        const select = document.getElementById('agentSelect');
                        if (select) {
                            select.innerHTML = '<option value="">Seleccionar agente...</option>' +
                                agents.map(agent => `<option value="${agent.id}">${agent.name} (${agent.email}) - ${agent.disponibilidad || 'Sin estado'}</option>`).join('');
                        }
                        
                        return agents;
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                    
                } catch (error) {
                    const select = document.getElementById('agentSelect');
                    if (select) {
                        select.innerHTML = '<option value="">Error cargando agentes</option>';
                    }
                    
                    this.showError('Error cargando agentes: ' + error.message);
                }
            }

            async loadRoomsForSelect() {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/rooms`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const rooms = result.data?.rooms || result.rooms || [];
                        
                        const select = document.getElementById('roomSelect');
                        if (select) {
                            select.innerHTML = '<option value="">Seleccionar sala...</option>' +
                                rooms.filter(room => room.is_active).map(room => `<option value="${room.id}">${room.name}</option>`).join('');
                        }
                        
                        return rooms;
                    }
                    
                } catch (error) {
                    //
                }
            }

            async assignAgent() {
                try {
                    const agent_id = document.getElementById('agentSelect').value;
                    const room_id = document.getElementById('roomSelect').value;
                    const priority = parseInt(document.getElementById('assignmentPriority').value);
                    const max_concurrent_chats = parseInt(document.getElementById('maxConcurrentChats').value);
                    const is_primary_agent = document.getElementById('isPrimaryAgent').checked;
                    
                    if (!agent_id || !room_id) {
                        this.showError('Selecciona agente y sala');
                        return;
                    }
                    
                    const response = await fetch(`${this.adminServiceUrl}/assignments`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            agent_id,
                            room_id,
                            priority,
                            max_concurrent_chats,
                            is_primary_agent
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Agente asignado exitosamente');
                        closeModal('assignAgentModal');
                        await this.loadAssignments();
                        document.getElementById('assignAgentForm').reset();
                    } else {
                        throw new Error(result.message || 'Error asignando agente');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async editAssignment(assignmentId) {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/assignments`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        const assignments = result.data?.assignments || result.assignments || [];
                        const assignment = assignments.find(a => a.id === assignmentId);
                        
                        if (assignment) {
                            this.currentEditingAssignment = assignment;
                            this.populateEditAssignmentModal(assignment);
                            document.getElementById('editAssignmentModal').classList.remove('hidden');
                        } else {
                            this.showError('Asignación no encontrada');
                        }
                    }
                    
                } catch (error) {
                    this.showError('Error cargando datos de asignación');
                }
            }

            populateEditAssignmentModal(assignment) {
                document.getElementById('editAssignmentAgentName').textContent = assignment.agent_name || 'Sin nombre';
                document.getElementById('editAssignmentRoomName').textContent = assignment.room_name || assignment.room_id;
                document.getElementById('editAssignmentPriority').value = assignment.priority || 1;
                document.getElementById('editMaxConcurrentChats').value = assignment.max_concurrent_chats || 5;
                document.getElementById('editAssignmentStatus').value = assignment.status || 'active';
                document.getElementById('editIsPrimaryAgent').checked = assignment.is_primary_agent || false;
            }

            async saveAssignmentChanges() {
                try {
                    if (!this.currentEditingAssignment) {
                        this.showError('No hay asignación seleccionada para editar');
                        return;
                    }
                    
                    const updateData = {
                        priority: parseInt(document.getElementById('editAssignmentPriority').value),
                        max_concurrent_chats: parseInt(document.getElementById('editMaxConcurrentChats').value),
                        status: document.getElementById('editAssignmentStatus').value,
                        is_primary_agent: document.getElementById('editIsPrimaryAgent').checked
                    };
                    
                    const response = await fetch(`${this.adminServiceUrl}/assignments/${this.currentEditingAssignment.id}`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify(updateData)
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Asignación actualizada exitosamente');
                        closeModal('editAssignmentModal');
                        this.currentEditingAssignment = null;
                        await this.loadAssignments();
                    } else {
                        throw new Error(result.message || 'Error actualizando asignación');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async removeAssignment(assignmentId) {
                if (!confirm('¿Remover esta asignación?')) {
                    return;
                }
                
                try {
                    const response = await fetch(`${this.adminServiceUrl}/assignments/${assignmentId}`, {
                        method: 'DELETE',
                        headers: this.getAuthHeaders()
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Asignación removida');
                        await this.loadAssignments();
                    } else {
                        throw new Error(result.message || 'Error removiendo asignación');
                    }
                    
                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            async showScheduleModal() {
                try {
                    if (!this.currentEditingAssignment) {
                        this.showError('No hay asignación seleccionada');
                        return;
                    }
                    
                    this.currentScheduleAssignment = this.currentEditingAssignment;
                    
                    document.getElementById('scheduleAgentName').textContent = 
                        this.currentEditingAssignment.agent_name || 'Sin nombre';
                    document.getElementById('scheduleRoomName').textContent = 
                        this.currentEditingAssignment.room_name || this.currentEditingAssignment.room_id;
                    
                    await this.loadSchedulesForAssignment(this.currentEditingAssignment.id);
                    
                    document.getElementById('scheduleModal').classList.remove('hidden');
                    
                } catch (error) {
                    this.showError('Error mostrando modal de horarios');
                }
            }

            async loadSchedulesForAssignment(assignmentId) {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/assignments/${assignmentId}/schedule`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const schedules = result.data?.schedules || result.schedules || [];
                        
                        this.displaySchedules(schedules);
                        return schedules;
                    } else {
                        this.displaySchedules([]);
                    }
                    
                } catch (error) {
                    this.displaySchedules([]);
                }
            }

            displaySchedules(existingSchedules) {
                const container = document.getElementById('scheduleContainer');
                if (!container) return;

                const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                
                const schedulesByDay = {};
                existingSchedules.forEach(schedule => {
                    schedulesByDay[schedule.day_of_week] = schedule;
                });

                container.innerHTML = dayNames.map((dayName, dayIndex) => {
                    const existingSchedule = schedulesByDay[dayIndex];
                    const isActive = !!existingSchedule;
                    
                    return `
                        <div class="schedule-day ${isActive ? 'active' : ''}" data-day="${dayIndex}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="day_${dayIndex}" 
                                           class="day-checkbox" 
                                           ${isActive ? 'checked' : ''} 
                                           onchange="adminClient.toggleDay(${dayIndex})">
                                    <label for="day_${dayIndex}" class="font-medium text-gray-900 text-sm sm:text-base">${dayName}</label>
                                </div>
                                ${isActive ? '<span class="text-xs text-green-600 font-medium">Configurado</span>' : ''}
                            </div>
                            
                            <div class="schedule-times ${isActive ? '' : 'hidden'}" id="times_${dayIndex}">
                                <div class="grid grid-cols-2 gap-2 sm:gap-4">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Hora inicio</label>
                                        <input type="time" id="start_${dayIndex}" 
                                               class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                               value="${existingSchedule?.start_time || '09:00'}">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Hora fin</label>
                                        <input type="time" id="end_${dayIndex}" 
                                               class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                               value="${existingSchedule?.end_time || '17:00'}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            toggleDay(dayIndex) {
                const checkbox = document.getElementById(`day_${dayIndex}`);
                const timesContainer = document.getElementById(`times_${dayIndex}`);
                const dayContainer = document.querySelector(`[data-day="${dayIndex}"]`);
                
                if (checkbox.checked) {
                    timesContainer.classList.remove('hidden');
                    dayContainer.classList.add('active');
                } else {
                    timesContainer.classList.add('hidden');
                    dayContainer.classList.remove('active');
                }
            }

            normalizeTimeFormat(timeValue) {
                if (!timeValue) return '';
                
                const cleaned = timeValue.toString().trim();
                
                if (/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(cleaned)) {
                    return cleaned;
                }
                
                if (/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/.test(cleaned)) {
                    return cleaned.substring(0, 5);
                }
                
                const shortMatch = cleaned.match(/^(\d{1,2}):(\d{1,2})$/);
                if (shortMatch) {
                    const hour = shortMatch[1].padStart(2, '0');
                    const minute = shortMatch[2].padStart(2, '0');
                    return `${hour}:${minute}`;
                }
                
                return cleaned;
            }

            async saveScheduleChanges() {
                try {
                    if (!this.currentScheduleAssignment) {
                        this.showError('No hay asignación seleccionada');
                        return;
                    }
                    
                    const schedules = [];
                    const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    
                    for (let day = 0; day < 7; day++) {
                        const checkbox = document.getElementById(`day_${day}`);
                        
                        if (checkbox && checkbox.checked) {
                            const startTimeInput = document.getElementById(`start_${day}`);
                            const endTimeInput = document.getElementById(`end_${day}`);
                            
                            if (!startTimeInput || !endTimeInput) {
                                continue;
                            }
                            
                            const rawStartTime = startTimeInput.value;
                            const rawEndTime = endTimeInput.value;
                            
                            const startTime = this.normalizeTimeFormat(rawStartTime);
                            const endTime = this.normalizeTimeFormat(rawEndTime);
                            
                            if (!startTime || !endTime) {
                                this.showError(`Debe especificar hora de inicio y fin para ${dayNames[day]}`);
                                return;
                            }
                            
                            const timeRegex = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
                            
                            if (!timeRegex.test(startTime)) {
                                this.showError(`Formato de hora inicio inválido para ${dayNames[day]}. Use formato HH:MM`);
                                return;
                            }
                            
                            if (!timeRegex.test(endTime)) {
                                this.showError(`Formato de hora fin inválido para ${dayNames[day]}. Use formato HH:MM`);
                                return;
                            }
                            
                            const [startHour, startMinute] = startTime.split(':').map(Number);
                            const [endHour, endMinute] = endTime.split(':').map(Number);
                            
                            const startTotal = startHour * 60 + startMinute;
                            const endTotal = endHour * 60 + endMinute;
                            
                            if (startTotal >= endTotal) {
                                this.showError(`La hora de inicio debe ser menor que la hora de fin para ${dayNames[day]}`);
                                return;
                            }
                            
                            schedules.push({
                                day_of_week: parseInt(day),
                                start_time: startTime,
                                end_time: endTime,
                                timezone: 'America/Bogota',
                                is_available: true
                            });
                        }
                    }
                    
                    if (schedules.length === 0) {
                        this.showError('Debe seleccionar al menos un día con horarios');
                        return;
                    }
                    
                    const response = await fetch(`${this.adminServiceUrl}/assignments/${this.currentScheduleAssignment.id}/schedule`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({ schedules })
                    });
                    
                    let result;
                    try {
                        result = await response.json();
                    } catch (parseError) {
                        throw new Error('Error del servidor: respuesta inválida');
                    }
                    
                    if (response.ok && result.success) {
                        this.showSuccess('Horarios guardados exitosamente');
                        closeModal('scheduleModal');
                        this.currentScheduleAssignment = null;
                        await this.loadAssignments();
                    } else {
                        let errorMessage = 'Error guardando horarios';
                        if (result.message) {
                            errorMessage = result.message;
                        } else if (result.error) {
                            errorMessage = result.error;
                        } else if (result.errors && Array.isArray(result.errors)) {
                            errorMessage = result.errors.join(', ');
                        }
                        
                        throw new Error(errorMessage);
                    }
                    
                } catch (error) {
                    this.showError(error.message || 'Error guardando horarios');
                }
            }

            async loadReports() {
                try {
                    this.initializeDateRanges();
                    this.showLoadingStateForReports();
                    
                    const startDate = document.getElementById('startDate')?.value;
                    const endDate = document.getElementById('endDate')?.value;
                    
                    const [chatStats, agentStats] = await Promise.allSettled([
                        this.loadChatStatistics(startDate, endDate), 
                        this.loadAgentStatistics(startDate, endDate)
                    ]);

                    if (chatStats.status === 'fulfilled' && chatStats.value) {
                        this.displayChatStats(chatStats.value);
                    } else {
                        this.displayEmptyStats('chatStatsContainer', 'chat');
                    }

                    if (agentStats.status === 'fulfilled' && agentStats.value) {
                        this.displayAgentStats(agentStats.value);
                    } else {
                        this.displayEmptyStats('agentStatsContainer', 'agent');
                    }

                    await this.loadLiveStats(endDate);
                    this.updateDisplayedDateRange();
                    
                } catch (error) {
                    this.showErrorStateForReports();
                }
            }

            async loadChatStatistics(startDate, endDate) {
                try {
                    let url = `${this.adminServiceUrl}/reports/statistics`;
                    const params = new URLSearchParams();
                    
                    if (startDate && endDate) {
                        params.append('start_date', startDate);
                        params.append('end_date', endDate);
                        params.append('group_by', 'day');
                    }
                    
                    if (params.toString()) {
                        url += '?' + params.toString();
                    }
                    
                    const response = await fetch(url, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const result = await response.json();

                    if (result.statistics || result.data?.statistics || (result.success && result.statistics)) {
                        return result;
                    } else if (result.data) {
                        return result.data;
                    } else {
                        return result;
                    }
                    
                } catch (error) {
                    throw error;
                }
            }

            async loadAgentStatistics(startDate, endDate) {
                try {
                    let url = `${this.adminServiceUrl}/reports/agents`;
                    const params = new URLSearchParams();
                    
                    if (startDate && endDate) {
                        params.append('start_date', startDate);
                        params.append('end_date', endDate);
                    }
                    
                    if (params.toString()) {
                        url += '?' + params.toString();
                    }

                    const response = await fetch(url, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const result = await response.json();

                    if (result.agents || result.data?.agents || (result.success && result.agents)) {
                        return result;
                    } else if (result.data) {
                        return result.data;
                    } else {
                        return result;
                    }
                    
                } catch (error) {
                    throw error;
                }
            }

            displayChatStats(data) {
                try {
                    let statistics = [];
                    
                    if (data.statistics && Array.isArray(data.statistics)) {
                        statistics = data.statistics;
                    } else if (data.data && data.data.statistics && Array.isArray(data.data.statistics)) {
                        statistics = data.data.statistics;
                    } else if (Array.isArray(data)) {
                        statistics = data;
                    }
                    
                    if (statistics.length === 0) {
                        this.displayEmptyStats('chatStatsContainer', 'chat');
                        return;
                    }

                    let totalChats = 0;
                    let attendedChats = 0;
                    let abandonedChats = 0;
                    let totalResponseTime = 0;
                    let totalDuration = 0;
                    let responseTimeCount = 0;
                    let durationCount = 0;
                    let totalOpportunityRate = 0;
                    let periodCount = 0;

                    statistics.forEach((stat) => {
                        totalChats += stat.total_chats || 0;
                        attendedChats += stat.attended_chats || 0;
                        abandonedChats += stat.abandoned_chats || 0;
                        
                        if (stat.avg_response_time && stat.avg_response_time > 0) {
                            totalResponseTime += stat.avg_response_time;
                            responseTimeCount++;
                        }
                        
                        if (stat.avg_duration && stat.avg_duration > 0) {
                            totalDuration += stat.avg_duration;
                            durationCount++;
                        }

                        if (stat.opportunity_rate !== undefined) {
                            totalOpportunityRate += stat.opportunity_rate;
                            periodCount++;
                        }
                    });

                    const avgResponseTime = responseTimeCount > 0 ? 
                        Math.round(totalResponseTime / responseTimeCount * 100) / 100 : 0;
                    const avgDuration = durationCount > 0 ? 
                        Math.round(totalDuration / durationCount * 100) / 100 : 0;
                    const attendanceRate = totalChats > 0 ? 
                        Math.round((attendedChats / totalChats) * 100 * 100) / 100 : 0;
                    const opportunityRate = periodCount > 0 ?
                        Math.round(totalOpportunityRate / periodCount * 100) / 100 : 0;
                    const abandonedRate = totalChats > 0 ? 
                        Math.round((abandonedChats / totalChats) * 100) : 0;

                    this.updateElement('report-total-chats', totalChats.toLocaleString());
                    this.updateElement('report-attended-chats', attendedChats.toLocaleString());
                    this.updateElement('report-avg-duration', avgDuration > 0 ? Math.round(avgDuration) + 'm' : '0m');
                    this.updateElement('report-attendance-percentage', attendanceRate + '%');
                    
                    this.updateElement('chat-completed-count', attendedChats.toLocaleString());
                    this.updateElement('chat-completed-percent', Math.round(attendanceRate) + '%');
                    this.updateElement('chat-abandoned-count', abandonedChats.toLocaleString());
                    this.updateElement('chat-abandoned-percent', abandonedRate + '%');
                    this.updateElement('opportunity-rate-value', opportunityRate + '%');
                    
                    this.updateProgressBar('chat-completed-progress', Math.round(attendanceRate));
                    this.updateProgressBar('chat-abandoned-progress', abandonedRate);
                    this.updateProgressBar('opportunity-rate-bar', opportunityRate);
                    
                    if (avgDuration > 0) {
                        this.updateElement('quick-avg-time', Math.round(avgDuration) + 'm');
                    }
                    
                } catch (error) {
                    this.displayEmptyStats('chatStatsContainer', 'chat');
                }
            }

            displayAgentStats(data) {
                try {
                    let agentCount = 0;
                    let agents = [];
                    
                    if (data.agent_count !== undefined && data.agents) {
                        agentCount = data.agent_count;
                        agents = data.agents;
                    } else if (data.data && data.data.agent_count !== undefined) {
                        agentCount = data.data.agent_count;
                        agents = data.data.agents || [];
                    } else if (Array.isArray(data.agents)) {
                        agents = data.agents;
                        agentCount = agents.length;
                    } else if (Array.isArray(data)) {
                        agents = data;
                        agentCount = agents.length;
                    }
                    
                    if (agents.length === 0 && agentCount === 0) {
                        this.displayEmptyStats('agentStatsContainer', 'agent');
                        return;
                    }
                    
                    let totalMessages = 0;
                    let totalResponseTime = 0;
                    let responseTimeCount = 0;

                    agents.forEach((agent) => {
                        totalMessages += agent.total_messages_sent || 0;
                        
                        if (agent.avg_response_time && agent.avg_response_time > 0) {
                            totalResponseTime += agent.avg_response_time;
                            responseTimeCount++;
                        }
                    });

                    const avgResponseTime = responseTimeCount > 0 ? 
                        Math.round(totalResponseTime / responseTimeCount * 100) / 100 : 0;

                    this.updateElement('report-active-agents', agentCount);
                    this.updateElement('report-agents-online', agents.length);
                    
                    this.updateElement('agent-messages-count', totalMessages.toLocaleString());
                    this.updateElement('agent-response-time', avgResponseTime > 0 ? avgResponseTime + 'm' : '0m');
                    
                    this.displayTopAgents(agents.slice(0, 5));
                    
                } catch (error) {
                    this.displayEmptyStats('agentStatsContainer', 'agent');
                }
            }

            displayTopAgents(topAgents) {
                const container = document.getElementById('topAgentsList');
                if (!container) {
                    return;
                }
                
                if (!topAgents || topAgents.length === 0) {
                    container.innerHTML = '<p class="text-sm text-gray-500 text-center py-2">No hay datos de agentes disponibles</p>';
                    return;
                }
                
                container.innerHTML = topAgents.map((agent, index) => `
                    <div class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border">
                        <div class="flex items-center space-x-3">
                            <span class="text-sm font-bold text-gray-600">#${index + 1}</span>
                            <div>
                                <p class="font-semibold text-sm text-gray-800">${agent.agent_name || agent.name || 'Agente'}</p>
                                <p class="text-xs text-gray-500">${agent.total_chats || 0} chats</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-green-600">${agent.total_messages_sent || 0} msgs</p>
                            <p class="text-xs text-gray-500">${agent.avg_response_time || 0}m resp.</p>
                        </div>
                    </div>
                `).join('');
            }

            async loadLiveStats() {
                try {
                    const dashboardData = await this.loadDashboard();
                    
                    if (dashboardData) {
                        this.displayLiveStats(dashboardData);
                    } else {
                        this.showEmptyLiveStats();
                    }
                    
                } catch (error) {
                    this.showEmptyLiveStats();
                }
            }

            displayLiveStats(data) {
                try {
                    const dashboardData = data.dashboard_data || data;
                    const sessions = dashboardData.sessions || {};
                    const agents = dashboardData.agents || {};

                    this.updateElement('live-active-sessions', sessions.active || 0);
                    this.updateElement('live-waiting-sessions', sessions.waiting || 0);
                    this.updateElement('live-online-agents', agents.online || agents.total || 0);

                } catch (error) {
                    this.showEmptyLiveStats();
                }
            }

            showEmptyLiveStats() {
                this.updateElement('live-active-sessions', 0);
                this.updateElement('live-waiting-sessions', 0);
                this.updateElement('live-online-agents', 0);
            }

            displayEmptyStats(containerId, type) {
                if (type === 'chat') {
                    this.updateElement('report-total-chats', '0');
                    this.updateElement('report-attended-chats', '0');
                    this.updateElement('report-avg-duration', '0m');
                    this.updateElement('chat-completed-count', '0');
                    this.updateElement('chat-abandoned-count', '0');
                    this.updateElement('opportunity-rate-value', '0%');
                    this.updateProgressBar('opportunity-rate-bar', 0);
                }
                
                if (type === 'agent') {
                    this.updateElement('report-active-agents', '0');
                    this.updateElement('agent-messages-count', '0');
                    this.updateElement('agent-response-time', '0m');
                    
                    const container = document.getElementById('topAgentsList');
                    if (container) {
                        container.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No hay datos disponibles</p>';
                    }
                }
            }

            showLoadingStateForReports() {
                this.updateElement('report-total-chats', '...');
                this.updateElement('report-attended-chats', '...');
                this.updateElement('report-avg-duration', '...');
                this.updateElement('report-active-agents', '...');
                this.updateElement('live-active-sessions', '...');
                this.updateElement('live-waiting-sessions', '...');
                this.updateElement('live-online-agents', '...');
                
                const topAgentsContainer = document.getElementById('topAgentsList');
                if (topAgentsContainer) {
                    topAgentsContainer.innerHTML = `
                        <div class="text-center py-4 text-gray-500">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-green-600 mx-auto mb-2"></div>
                            <p class="text-sm">Cargando datos...</p>
                        </div>
                    `;
                }
            }

            showErrorStateForReports() {
                this.updateElement('report-total-chats', '0');
                this.updateElement('report-attended-chats', '0');
                this.updateElement('report-avg-duration', '0m');
                this.updateElement('report-active-agents', '0');
                this.updateElement('live-active-sessions', '0');
                this.updateElement('live-waiting-sessions', '0');
                this.updateElement('live-online-agents', '0');

                
                const topAgentsContainer = document.getElementById('topAgentsList');
                if (topAgentsContainer) {
                    topAgentsContainer.innerHTML = `
                        <div class="text-center py-4">
                            <p class="text-sm text-gray-500 mb-2">Error cargando datos</p>
                            <button onclick="adminClient.loadReports()" class="px-3 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600">
                                Reintentar
                            </button>
                        </div>
                    `;
                }
            }

            updateElement(id, value) {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value;
                }
            }

            updateProgressBar(id, percentage) {
                const element = document.getElementById(id);
                if (element) {
                    element.style.width = `${Math.min(Math.max(percentage, 0), 100)}%`;
                }
            }

            initializeDateRanges() {
                const startDateInput = document.getElementById('startDate');
                const endDateInput = document.getElementById('endDate');
                
                if (!startDateInput?.value || !endDateInput?.value) {
                    const today = new Date();
                    // Ajustar para zona horaria local
                    const localToday = new Date(today.getTime() - (today.getTimezoneOffset() * 60000));
                    const thirtyDaysAgo = new Date(localToday);
                    thirtyDaysAgo.setDate(localToday.getDate() - 30);
                    
                    if (startDateInput) startDateInput.value = thirtyDaysAgo.toISOString().split('T')[0];
                    if (endDateInput) endDateInput.value = localToday.toISOString().split('T')[0];
                }
            }

            setQuickDateRange(period) {
                const today = new Date();
                const localToday = new Date(today.getTime() - (today.getTimezoneOffset() * 60000));
                const startDateInput = document.getElementById('startDate');
                const endDateInput = document.getElementById('endDate');
                
                if (!startDateInput || !endDateInput) return;
                
                let startDate;
                
                switch(period) {
                    case 'today':
                        startDate = new Date(today);
                        break;
                    case 'week':
                        startDate = new Date(today);
                        startDate.setDate(today.getDate() - 7);
                        break;
                    case 'month':
                        startDate = new Date(today);
                        startDate.setDate(today.getDate() - 30);
                        break;
                    default:
                        return;
                }
                
                startDateInput.value = startDate.toISOString().split('T')[0];
                endDateInput.value = localToday.toISOString().split('T')[0];
                
                this.loadReportsWithDateRange();
            }

            async loadReportsWithDateRange() {
                const startDateInput = document.getElementById('startDate');
                const endDateInput = document.getElementById('endDate');
                
                if (!startDateInput?.value || !endDateInput?.value) {
                    this.showError('Por favor selecciona un rango de fechas válido');
                    return;
                }
                
                const startDate = startDateInput.value;
                const endDate = endDateInput.value;
                
                if (new Date(startDate) > new Date(endDate)) {
                    this.showError('La fecha de inicio debe ser menor que la fecha final');
                    return;
                }
                
                this.showNotification('Cargando reportes para el período seleccionado...', 'info');
                
                try {
                    this.showLoadingStateForReports();
                    
                    const [chatStats, agentStats] = await Promise.allSettled([
                        this.loadChatStatistics(startDate, endDate),
                        this.loadAgentStatistics(startDate, endDate)
                    ]);

                    if (chatStats.status === 'fulfilled' && chatStats.value) {
                        this.displayChatStats(chatStats.value);
                    } else {
                        this.displayEmptyStats('chatStatsContainer', 'chat');
                    }

                    if (agentStats.status === 'fulfilled' && agentStats.value) {
                        this.displayAgentStats(agentStats.value);
                    } else {
                        this.displayEmptyStats('agentStatsContainer', 'agent');
                    }

                    await this.loadLiveStats(endDate);
                    this.updateDisplayedDateRange();
                    
                    this.showNotification('Reportes actualizados exitosamente', 'success');
                    
                } catch (error) {
                    this.showNotification('Error cargando reportes', 'error');
                    this.displayEmptyStats('chatStatsContainer', 'chat');
                    this.displayEmptyStats('agentStatsContainer', 'agent');
                    this.showEmptyLiveStats();
                }
            }

            updateDisplayedDateRange() {
                const startDate = document.getElementById('startDate')?.value;
                const endDate = document.getElementById('endDate')?.value;
                
                if (startDate && endDate) {
                    const formatDate = (dateStr) => {
                        try {
                            const [year, month, day] = dateStr.split('-');
                            const date = new Date(year, month - 1, day);
                            return date.toLocaleDateString('es-ES', {
                                day: '2-digit',
                                month: 'short'
                            });
                        } catch (error) {
                            return dateStr;
                        }
                    };
                    
                    this.updateElement('selected-start-date', formatDate(startDate));
                    this.updateElement('selected-end-date', formatDate(endDate));
                } else {
                    this.updateElement('selected-start-date', '--');
                    this.updateElement('selected-end-date', '--');
                }
            }

            async refreshReports() {
                try {
                    this.showNotification('Actualizando reportes...', 'info');
                    await this.loadReports();
                    this.showNotification('Reportes actualizados exitosamente', 'success');
                } catch (error) {
                    this.showNotification('Error actualizando reportes', 'error');
                }
            }

            async loadConfig() {
                try {
                    const response = await fetch(`${this.adminServiceUrl}/config`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const config = result.data || result.config || result;
                        
                        this.displayConfig({ data: config });
                        return config;
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                    
                } catch (error) {
                    this.displayConfig({ data: {} });
                    this.showError('Error cargando configuración: ' + error.message);
                }
            }

            displayConfig(config) {
                const container = document.getElementById('configContainer');
                if (!container) return;

                const configData = config.data || config;

                container.innerHTML = `
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-4">Configuración General</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Timeout de Sesión (min)</label>
                                        <input type="number" id="sessionTimeout" value="${configData.session_timeout || 30}" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" min="1" max="480">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Máx. Transferencias</label>
                                        <input type="number" id="maxTransfers" value="${configData.max_transfers || 3}" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" min="1" max="10">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tiempo Respuesta (seg)</label>
                                        <input type="number" id="responseTime" value="${configData.max_response_time || 300}" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" min="30" max="1800">
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-4">Notificaciones</h4>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <div class="flex items-center opacity-50">
                                        <input type="checkbox" id="emailNotifications" disabled
                                            class="mr-2 h-4 w-4 text-red-600 border-gray-300 rounded">
                                        <label class="text-sm text-gray-700">Email (próximamente)</label>
                                        </div>
                                    </div>
                                    <div class="flex items-center opacity-50">
                                        <input type="checkbox" id="smsNotifications" disabled
                                            class="mr-2 h-4 w-4 text-gray-400 border-gray-300 rounded">
                                        <label class="text-sm text-gray-500">SMS (próximamente)</label>
                                    </div>
                                    <div class="flex items-center opacity-50">
                                        <input type="checkbox" id="autoEscalation" disabled
                                            class="mr-2 h-4 w-4 text-gray-400 border-gray-300 rounded">
                                        <label class="text-sm text-gray-500">Escalación automática (próximamente)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 mb-2">Información del Sistema</h4>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Versión:</span>
                                    <span class="font-medium block">${configData.system_version || '2.0.0'}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Base de Datos:</span>
                                    <span class="font-medium block">${configData.database_status || 'Conectada'}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Última Actualización:</span>
                                    <span class="font-medium block">${this.formatDate(configData.last_update)}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Uptime:</span>
                                    <span class="font-medium block">${configData.uptime || 'N/A'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            async saveConfig() {
                try {
                    const configData = {
                        session_timeout: parseInt(document.getElementById('sessionTimeout')?.value) || 30,
                        max_transfers: parseInt(document.getElementById('maxTransfers')?.value) || 3,
                        max_response_time: parseInt(document.getElementById('responseTime')?.value) || 300
                    };

                    this.showNotification('Guardando configuración...', 'info');

                    const response = await fetch(`${this.adminServiceUrl}/config`, {
                        method: 'POST',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify(configData)
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        this.showSuccess('Configuración guardada exitosamente');
                        
                        setTimeout(async () => {
                            await this.loadConfig();
                        }, 1000);
                        
                    } else {
                        const errorMessage = result.message || result.error || 'Error guardando configuración';
                        throw new Error(errorMessage);
                    }

                } catch (error) {
                    this.showError('Error: ' + error.message);
                }
            }

            getRoomStatusClass(status) {
                const classes = {
                    'available': 'bg-green-100 text-green-800',
                    'busy': 'bg-yellow-100 text-yellow-800',
                    'maintenance': 'bg-orange-100 text-orange-800',
                    'disabled': 'bg-red-100 text-red-800'
                };
                return classes[status] || 'bg-gray-100 text-gray-800';
            }

            getRoomStatusText(status) {
                const texts = {
                    'available': 'Disponible',
                    'busy': 'Ocupada',
                    'maintenance': 'Mantenimiento',
                    'disabled': 'Deshabilitada'
                };
                return texts[status] || 'Desconocido';
            }

            getAgentInitials(name) {
                if (!name) return 'A';
                return name.split(' ').map(part => part.charAt(0)).join('').substring(0, 2).toUpperCase();
            }

            formatDate(dateString) {
                if (!dateString) return 'N/A';
                try {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('es-ES', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch (error) {
                    return 'N/A';
                }
            }

            showSuccess(message) {
                this.showNotification(message, 'success');
            }

            showError(message) {
                this.showNotification(message, 'error');
            }

            showNotification(message, type = 'info', duration = 4000) {
                const colors = {
                    success: 'bg-green-500',
                    error: 'bg-red-500',
                    info: 'bg-blue-500',
                    warning: 'bg-yellow-500'
                };
                
                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 z-50 p-3 sm:p-4 rounded-lg shadow-lg max-w-xs sm:max-w-sm text-white text-sm ${colors[type]}`;
                notification.innerHTML = `
                    <div class="flex items-center justify-between">
                        <span>${message}</span>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">×</button>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, duration);
            }

            async init() {
                try {
                    await this.loadDashboard();
                    this.startAutoRefresh();
                } catch (error) {
                    this.showError('Error de inicialización');
                }
            }

            startAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
                
                this.refreshInterval = setInterval(async () => {
                    try {
                        const activeSection = document.querySelector('.section-content:not(.hidden)');
                        if (activeSection) {
                            const sectionId = activeSection.id;
                            if (sectionId === 'dashboard-section') {
                                await this.loadDashboard();
                            } else if (sectionId === 'reports-section') {
                                await this.loadReports();
                            }
                        }
                    } catch (error) {
                        // Silent error, skip refresh
                    }
                }, this.refreshIntervalTime);
            }

            stopAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                    this.refreshInterval = null;
                }
            }

            destroy() {
                this.stopAutoRefresh();
            }
            // ========== MÉTODOS PARA CHAT GRUPAL ==========

        async loadGroupRooms() {
            try {
                console.log('🏠 Cargando salas para chat grupal...');
                
                const response = await fetch(`${this.adminServiceUrl}/rooms`, {
                    method: 'GET',
                    headers: this.getAuthHeaders()
                });
                
                if (!response.ok) throw new Error('Error cargando salas');
                
                const result = await response.json();
                const rooms = result.data?.rooms || result.rooms || [];
                
                console.log('✅ Salas cargadas:', rooms.length);
                this.displayGroupRooms(rooms);
                
            } catch (error) {
                console.error('❌ Error cargando salas grupales:', error);
                this.showNotification('Error cargando salas grupales', 'error');
            }
        }

        displayGroupRooms(rooms) {
            const container = document.getElementById('groupRoomsList');
            if (!container) return;
            
            if (rooms.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center py-20">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p class="text-gray-500">No hay salas disponibles</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = rooms.map(room => `
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer"
                    onclick="adminClient.joinGroupRoom('${room.id}', '${room.name}')">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 ${this.getColorForRoom(room)} rounded-lg flex items-center justify-center">
                                <span class="text-white font-semibold text-lg">${(room.name || 'S').charAt(0)}</span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">${room.name}</h4>
                                <p class="text-sm text-gray-500">${room.description || 'Sala de chat'}</p>
                            </div>
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded ${
                            room.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                        }">
                            ${room.is_active ? 'Activa' : 'Inactiva'}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="text-center p-2 bg-blue-50 rounded">
                            <div class="text-sm font-bold text-blue-600">${room.max_agents || 0}</div>
                            <div class="text-xs text-blue-700">Máx. Agentes</div>
                        </div>
                        <div class="text-center p-2 bg-green-50 rounded">
                            <div class="text-sm font-bold text-green-600">${room.assigned_agents || 0}</div>
                            <div class="text-xs text-green-700">Asignados</div>
                        </div>
                        <div class="text-center p-2 bg-purple-50 rounded">
                            <div class="text-sm font-bold text-purple-600">${room.active_agents || 0}</div>
                            <div class="text-xs text-purple-700">Activos</div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between text-sm mt-3 pt-3 border-t">
                        <span class="text-gray-600">Click para unirse al chat grupal</span>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </div>
            `).join('');
        }

        async joinGroupRoom(roomId, roomName) {
            try {
                console.log('🚪 Administrador uniéndose a sala grupal:', roomId, roomName);
                
                currentGroupRoomId = roomId;
                currentGroupRoom = { id: roomId, name: roomName };
                
                // Ocultar lista de salas, mostrar chat activo
                document.getElementById('groupRoomsList').classList.add('hidden');
                document.getElementById('activeGroupChat').classList.remove('hidden');
                
                // Actualizar UI
                document.getElementById('groupChatRoomName').textContent = roomName;
                document.getElementById('groupChatModeIndicator').textContent = 'Modo Administrador';
                document.getElementById('groupChatModeIndicator').className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800';
                
                // Admin siempre tiene input habilitado
                document.getElementById('groupChatInputDisabled').classList.add('hidden');
                document.getElementById('groupChatInputEnabled').classList.remove('hidden');
                
                // Conectar WebSocket si no está conectado
                if (!groupChatSocket || !isGroupChatConnected) {
                    await this.connectGroupChatWebSocket();
                }
                
                // Esperar a que el socket esté conectado
                await this.waitForGroupSocketConnection();
                
                // Unirse a la sala
                this.emitJoinGroupRoom(roomId);
                
            } catch (error) {
                console.error('❌ Error uniéndose a sala grupal:', error);
                this.showNotification('Error uniéndose a sala: ' + error.message, 'error');
                this.exitGroupChat();
            }
        }

        async connectGroupChatWebSocket() {
            try {
                console.log('🔌 Conectando WebSocket para chat grupal (Admin)...');
                
                const token = this.getToken();
                const currentUser = this.getCurrentUser();
                
                groupChatSocket = io('http://187.33.158.246', {
                    transports: ['websocket', 'polling'],
                    auth: {
                        token: token,
                        user_id: currentUser.id,
                        user_type: 'admin',
                        user_name: currentUser.name
                    }
                });
                
                groupChatSocket.on('connect', () => {
                    isGroupChatConnected = true;
                    console.log('✅ WebSocket grupal conectado (Admin)');
                });
                
                groupChatSocket.on('disconnect', () => {
                    isGroupChatConnected = false;
                    groupChatJoined = false;
                    console.log('❌ WebSocket grupal desconectado');
                });
                
                groupChatSocket.on('group_room_joined', (data) => {
                    groupChatJoined = true;
                    isSilentMode = false; // Admin nunca está en modo silencioso
                    console.log('✅ Admin unido a sala grupal:', data);
                    
                    this.updateGroupChatUI(data);
                    this.loadGroupChatHistory(data.room_id);
                });
                
                groupChatSocket.on('new_group_message', (data) => {
                    console.log('💬 Nuevo mensaje grupal:', data);
                    this.handleGroupMessage(data);
                });
                
                groupChatSocket.on('participant_joined', (data) => {
                    console.log('👋 Nuevo participante:', data);
                    this.showNotification(`${data.user_name || 'Usuario'} se unió a la sala`, 'info');
                });
                
                groupChatSocket.on('participant_left', (data) => {
                    console.log('👋 Participante salió:', data);
                    this.showNotification(`${data.user_name || 'Usuario'} salió de la sala`, 'info');
                });
                
                groupChatSocket.on('silent_mode_toggled', (data) => {
                    console.log('🔇 Modo silencioso alternado:', data);
                    // Admin observa estos cambios pero no afectan su estado
                });
                
                groupChatSocket.on('error', (error) => {
                    console.error('❌ Error en socket grupal:', error);
                    this.showNotification('Error: ' + (error.message || error), 'error');
                });
                
            } catch (error) {
                console.error('❌ Error conectando WebSocket grupal:', error);
                throw error;
            }
        }

        waitForGroupSocketConnection(timeout = 5000) {
            return new Promise((resolve, reject) => {
                if (isGroupChatConnected) {
                    resolve();
                    return;
                }
                
                const startTime = Date.now();
                const checkConnection = setInterval(() => {
                    if (isGroupChatConnected) {
                        clearInterval(checkConnection);
                        resolve();
                    } else if (Date.now() - startTime > timeout) {
                        clearInterval(checkConnection);
                        reject(new Error('Timeout esperando conexión WebSocket'));
                    }
                }, 100);
            });
        }

        emitJoinGroupRoom(roomId) {
            if (!groupChatSocket || !isGroupChatConnected) {
                console.error('❌ Socket no conectado');
                return;
            }
            
            const currentUser = this.getCurrentUser();
            
            groupChatSocket.emit('join_group_room', {
                room_id: roomId,
                user_id: currentUser.id,
                user_type: 'admin'
            });
        }

        updateGroupChatUI(data) {
            // Actualizar contador de participantes
            const participants = data.participants || [];
            const participantsText = `${participants.length} participante${participants.length !== 1 ? 's' : ''}`;
            
            const counter = document.getElementById('groupChatParticipantsCount');
            if (counter) {
                counter.textContent = participantsText;
            }
            
            // Admin siempre en modo activo
            const indicator = document.getElementById('groupChatModeIndicator');
            if (indicator) {
                indicator.textContent = 'Modo Administrador';
                indicator.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800';
            }
        }

        async loadGroupChatHistory(roomId) {
            const container = document.getElementById('groupChatMessages');
            if (!container) return;
            
            container.innerHTML = '<div class="text-center text-gray-500 text-sm py-8">Cargando mensajes...</div>';
            
            try {
                // Aquí harías una llamada al backend para obtener el historial
                // Por ahora, mostrar mensaje de inicio
                setTimeout(() => {
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-3">
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <p class="text-gray-600 font-medium">Chat Grupal Administrativo</p>
                            <p class="text-sm text-gray-500 mt-1">Tienes acceso completo como administrador</p>
                        </div>
                    `;
                }, 500);
                
            } catch (error) {
                console.error('❌ Error cargando historial grupal:', error);
                container.innerHTML = '<div class="text-center text-red-500 text-sm py-8">Error cargando mensajes</div>';
            }
        }

        handleGroupMessage(data) {
            const container = document.getElementById('groupChatMessages');
            if (!container) return;
            
            // Eliminar mensaje de "no hay mensajes"
            const emptyMsg = container.querySelector('.text-center');
            if (emptyMsg && (emptyMsg.textContent.includes('No hay mensajes') || emptyMsg.textContent.includes('Chat Grupal'))) {
                emptyMsg.remove();
            }
            
            const currentUser = this.getCurrentUser();
            const isMyMessage = data.sender_id === currentUser.id;
            
            const messageEl = document.createElement('div');
            messageEl.className = `flex ${isMyMessage ? 'justify-end' : 'justify-start'} mb-4`;
            
            const senderLabel = data.sender_type === 'admin' ? 'Admin' :
                            data.sender_type === 'supervisor' ? 'Supervisor' :
                            data.sender_type === 'agent' ? 'Agente' : 'Usuario';
            
            const bubbleColor = isMyMessage ? 'bg-red-600 text-white' :
                            data.sender_type === 'supervisor' ? 'bg-purple-100 text-purple-900' :
                            data.sender_type === 'agent' ? 'bg-green-100 text-green-900' :
                            data.sender_type === 'admin' ? 'bg-red-100 text-red-900' :
                            'bg-gray-200 text-gray-900';
            
            const time = new Date().toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
            
            messageEl.innerHTML = `
                <div class="max-w-xs lg:max-w-md ${bubbleColor} rounded-lg px-4 py-2">
                    <div class="text-xs opacity-75 mb-1">${isMyMessage ? 'Tú' : senderLabel}</div>
                    <p class="text-sm">${this.escapeHtml(data.content)}</p>
                    <div class="text-xs opacity-75 mt-1">${time}</div>
                </div>
            `;
            
            container.appendChild(messageEl);
            container.scrollTop = container.scrollHeight;
        }

        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        exitGroupChat() {
            // Desconectar del chat grupal
            if (groupChatSocket && groupChatJoined) {
                groupChatSocket.disconnect();
                groupChatSocket = null;
                isGroupChatConnected = false;
                groupChatJoined = false;
            }
            
            currentGroupRoomId = null;
            currentGroupRoom = null;
            
            // Mostrar lista de salas nuevamente
            document.getElementById('groupRoomsList').classList.remove('hidden');
            document.getElementById('activeGroupChat').classList.add('hidden');
            
            // Limpiar mensajes
            const container = document.getElementById('groupChatMessages');
            if (container) container.innerHTML = '';
        }

        showGroupParticipants() {
            if (!groupChatSocket || !groupChatJoined) {
                this.showNotification('No estás en una sala', 'error');
                return;
            }
            
            // Emitir evento para obtener participantes
            groupChatSocket.emit('get_room_participants', {}, (response) => {
                if (response && response.participants) {
                    this.displayGroupParticipants(response.participants);
                }
            });
            
            document.getElementById('groupParticipantsModal').classList.remove('hidden');
        }

        displayGroupParticipants(participants) {
            const container = document.getElementById('groupParticipantsList');
            if (!container) return;
            
            if (participants.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500 py-4">No hay participantes</div>';
                return;
            }
            
            container.innerHTML = participants.map(p => {
                const roleColors = {
                    'admin': 'from-red-500 to-pink-500',
                    'supervisor': 'from-purple-500 to-blue-500',
                    'agent': 'from-green-500 to-teal-500',
                    'user': 'from-gray-500 to-gray-600'
                };
                
                const roleLabels = {
                    'admin': 'Administrador',
                    'supervisor': 'Supervisor',
                    'agent': 'Agente',
                    'user': 'Usuario'
                };
                
                return `
                    <div class="flex items-center justify-between py-3 border-b border-gray-200 hover:bg-gray-50">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br ${roleColors[p.role] || roleColors.user} flex items-center justify-center">
                                <span class="text-white font-semibold">${(p.user_name || 'U').charAt(0)}</span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">${p.user_name || 'Usuario'}</p>
                                <p class="text-xs text-gray-500">${roleLabels[p.role] || 'Usuario'}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            ${p.role === 'admin' ? 
                                '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded font-medium">Admin</span>' :
                                p.is_silent ? 
                                '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded">Observando</span>' :
                                '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">Activo</span>'
                            }
                            ${p.is_online ? 
                                '<div class="w-2 h-2 bg-green-500 rounded-full"></div>' :
                                '<div class="w-2 h-2 bg-gray-400 rounded-full"></div>'
                            }
                        </div>
                    </div>
                `;
            }).join('');
        }

        closeGroupParticipants() {
            const modal = document.getElementById('groupParticipantsModal');
            if (modal) modal.classList.add('hidden');
        }

        refreshGroupRooms() {
            this.loadGroupRooms();
            this.showNotification('Actualizando salas...', 'info', 1000);
        }
        }

        let groupChatSocket = null;
        let isGroupChatConnected = false;
        let groupChatJoined = false;
        let currentGroupRoomId = null;
        let currentGroupRoom = null;
        let isSilentMode = false;

        window.adminClient = new AdminClient();

        function showSection(sectionName) {
            closeMobileNav();
            
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
            
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            document.getElementById(`${sectionName}-section`).classList.remove('hidden');
            document.getElementById(`nav-${sectionName}`)?.classList.add('active');
            document.getElementById(`mobile-nav-${sectionName}`)?.classList.add('active');
            
            const titles = {
                'dashboard': 'Dashboard',
                'users': 'Gestión de Usuarios',
                'rooms': 'Gestión de Salas',
                'assignments': 'Asignaciones',
                'reports': 'Reportes',
                'group-chat': 'Chat Grupal',
                'profile': 'Mi Perfil', 
                'config': 'Configuración'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName];
            
            switch(sectionName) {
                case 'dashboard':
                    adminClient.loadDashboard();
                    break;
                case 'users':
                    adminClient.loadUsers();
                    break;
                case 'rooms':
                    adminClient.loadRooms();
                    break;
                case 'assignments':
                    adminClient.loadAssignments();
                    break;
                case 'reports':
                    adminClient.loadReports();
                    break;
                case 'group-chat':
                    adminClient.loadGroupRooms();
                    break;
                case 'profile':
                    adminClient.loadProfile(); 
                    break;
                case 'config':
                    adminClient.loadConfig();
                    break;
            }
        }

        function openMobileNav() {
            document.getElementById('mobileNav').classList.add('active');
            document.getElementById('mobileNavBackdrop').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileNav() {
            document.getElementById('mobileNav').classList.remove('active');
            document.getElementById('mobileNavBackdrop').classList.remove('active');
            document.body.style.overflow = '';
        }

        function showCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('hidden');
        }

        function showCreateRoomModal() {
            document.getElementById('createRoomModal').classList.remove('hidden');
        }

        function showAssignAgentModal() {
            adminClient.loadAvailableAgents();
            adminClient.loadRoomsForSelect();
            document.getElementById('assignAgentModal').classList.remove('hidden');
        }

        function showDeletedRoomsModal() {
            adminClient.loadDeletedRooms();
            document.getElementById('deletedRoomsModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            
            if (modalId === 'editRoomModal') {
                adminClient.currentEditingRoom = null;
            } else if (modalId === 'editAssignmentModal') {
                adminClient.currentEditingAssignment = null;
            } else if (modalId === 'scheduleModal') {
                adminClient.currentScheduleAssignment = null;
            }
        }

        function logout() {
            if (confirm('¿Cerrar sesión?')) {
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = 'logout.php';
            }
        }

        function updateTime() {
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = new Date().toLocaleTimeString('es-ES');
            }
        }

        function showEditProfileModal() {
            // Poblar el modal con los datos actuales del usuario
            const currentUser = adminClient.getCurrentUser();
            if (currentUser) {
                document.getElementById('profileName').value = currentUser.name || '';
                document.getElementById('profileEmail').value = currentUser.email || '';
            }
            document.getElementById('editProfileModal').classList.remove('hidden');
        }

        function showChangePasswordModal() {
            document.getElementById('changePasswordModal').classList.remove('hidden');
        }

        function showResetPasswordModal() {
            adminClient.loadUsersForPasswordReset();
            document.getElementById('resetPasswordModal').classList.remove('hidden');
        }
        // ========== FUNCIONES GLOBALES PARA CHAT GRUPAL ==========

function sendGroupMessage() {
    const input = document.getElementById('groupMessageInput');
    if (!input) return;
    
    const message = input.value.trim();
    if (!message) return;
    
    if (!groupChatSocket || !groupChatJoined) {
        adminClient.showNotification('No estás conectado a la sala', 'error');
        return;
    }
    
    const currentUser = adminClient.getCurrentUser();
    
    groupChatSocket.emit('send_group_message', {
        content: message,
        message_type: 'text'
    });
    
    input.value = '';
}

function handleGroupChatKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendGroupMessage();
    }
}

// Exponer funciones globalmente
window.sendGroupMessage = sendGroupMessage;
window.handleGroupChatKeyDown = handleGroupChatKeyDown;

        document.addEventListener('DOMContentLoaded', async () => {
            updateTime();
            setInterval(updateTime, 1000);
            
            try {
                await adminClient.init();
            } catch (error) {
                console.error('Error initializing admin client:', error);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMobileNav();
                
                document.querySelectorAll('.fixed.inset-0').forEach(modal => {
                    if (!modal.classList.contains('hidden') && modal.id !== 'mobileNavBackdrop') {
                        modal.classList.add('hidden');
                    }
                });
                
                adminClient.currentEditingRoom = null;
                adminClient.currentEditingAssignment = null;
                adminClient.currentScheduleAssignment = null;
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                closeMobileNav();
            }
        });

        document.getElementById('mobileNav')?.addEventListener('click', (e) => {
            e.stopPropagation();
        });
