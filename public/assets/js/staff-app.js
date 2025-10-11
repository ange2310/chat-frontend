        const API_BASE = 'http://187.33.158.246';
        const CHAT_API = `${API_BASE}/chat`;
        const ADMIN_API = `${API_BASE}`;
        const AUTH_API = `${API_BASE}/auth`;
        const FILE_API = `${API_BASE}/chat/files`;

        const CONVERSATION_CONFIG = {
            EXPIRATION_TIME_MINUTES: 30,
            WARNING_TIME_MINUTES: 25,
            CRITICAL_TIME_MINUTES: 28
        };


        let previousPendingTransfers = new Set();
        let previousMyChatsTransfers = new Set();
        let transferNotificationCache = new Map();
        let previousPendingConversationIds = new Set();
        let globalPendingCheckInterval = null;
        let isFirstPendingCheck = true;
        let transferCheckInterval = null;
        let locallyRenderedFiles = new Set();
        let currentSession = null;
        let chatSocket = null;
        let isConnectedToChat = false;
        let sessionJoined = false;
        let pendingConversations = [];
        let myChats = [];
        let allChatsForSidebar = {};
        let agentIsTyping = false;
        let agentTypingTimer;
        let sessionTimer = null;
        let currentTimerInterval = null;
        let currentRoom = null;
        let rooms = [];
        let sessionsByRoom = {};
        let conversationTimers = new Map();
        let chatHistoryCache = new Map();
        let realTimeUpdateInterval = null;
        let lastUpdateTime = null;
        let sentMessages = new Set();
        let messageIdCounter = 0;
        let sessionMessageIds = new Map();
        let selectedFiles = [];
        let isFileUploadVisible = false;
        let currentUpload = null;
        let currentUserProfile = null;

        function getToken() {
            const tokenMeta = document.querySelector('meta[name="staff-token"]');
            return tokenMeta ? tokenMeta.getAttribute('content') : null;
        }

        function getCurrentUser() {
            const userMeta = document.querySelector('meta[name="staff-user"]');
            if (userMeta && userMeta.content) {
                try {
                    return JSON.parse(userMeta.content);
                } catch (e) {
                    console.warn('Error parsing user meta:', e);
                }
            }
            return { id: 'unknown', name: 'Usuario', email: 'unknown@example.com' };
        }

        function getAuthHeaders() {
            const token = getToken();
            if (!token) {
                throw new Error('Bearer token no disponible');
            }
            
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`
            };
        }

        function getFileUploadHeaders() {
            const token = getToken();
            if (!token) {
                throw new Error('Bearer token no disponible');
            }
            
            return {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            };
        }

        function formatTime(minutes) {
            const mins = Math.floor(minutes);
            const secs = Math.floor((minutes - mins) * 60);
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        function formatTimeFromDate(startDate) {
            const diff = Date.now() - new Date(startDate).getTime();
            const totalMinutes = diff / (1000 * 60);
            return formatTime(totalMinutes);
        }

        function getTimeAgo(timestamp) {
            const diff = Date.now() - new Date(timestamp).getTime();
            const minutes = Math.floor(diff / 60000);
            
            if (minutes < 1) return formatTime(0);
            return formatTime(minutes);
        }

        function isConversationExpired(createdAt) {
            if (!createdAt) return false;
            const now = new Date();
            const created = new Date(createdAt);
            const diffMinutes = (now - created) / (1000 * 60);
            return diffMinutes > CONVERSATION_CONFIG.EXPIRATION_TIME_MINUTES;
        }

        function getConversationStatus(createdAt) {
            if (!createdAt) return { status: 'normal', minutes: 0 };
            
            const now = new Date();
            const created = new Date(createdAt);
            const diffMinutes = (now - created) / (1000 * 60);
            
            if (diffMinutes > CONVERSATION_CONFIG.EXPIRATION_TIME_MINUTES) {
                return { status: 'expired', minutes: diffMinutes };
            } else if (diffMinutes > CONVERSATION_CONFIG.CRITICAL_TIME_MINUTES) {
                return { status: 'critical', minutes: diffMinutes };
            } else if (diffMinutes > CONVERSATION_CONFIG.WARNING_TIME_MINUTES) {
                return { status: 'warning', minutes: diffMinutes };
            } else {
                return { status: 'normal', minutes: diffMinutes };
            }
        }

        /*function closeChatSidebar() {
            const chatSidebar = document.querySelector('.chat-sidebar');
            const backdrop = document.getElementById('chatSidebarBackdrop');
            
            if (chatSidebar && backdrop) {
                chatSidebar.classList.remove('mobile-open');
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        }*/
        function getAdvancedUrgencyClass(createdAt) {
            const convStatus = getConversationStatus(createdAt);
            
            switch (convStatus.status) {
                case 'critical':
                    return {
                        borderClass: 'border-red-300 bg-red-50',
                        avatarClass: 'bg-red-100',
                        textClass: 'text-red-700',
                        indicatorClass: 'bg-red-500 animate-pulse',
                        statusTextClass: 'text-red-600',
                        waitTimeClass: 'text-red-600 font-bold animate-pulse',
                        buttonClass: 'bg-red-600 hover:bg-red-700'
                    };
                case 'warning':
                    return {
                        borderClass: 'border-yellow-300 bg-yellow-50',
                        avatarClass: 'bg-yellow-100',
                        textClass: 'text-yellow-700',
                        indicatorClass: 'bg-yellow-500',
                        statusTextClass: 'text-yellow-600',
                        waitTimeClass: 'text-yellow-600 font-semibold',
                        buttonClass: 'bg-yellow-600 hover:bg-yellow-700'
                    };
                default:
                    return {
                        borderClass: '',
                        avatarClass: 'bg-blue-100',
                        textClass: 'text-blue-700',
                        indicatorClass: '',
                        statusTextClass: '',
                        waitTimeClass: 'text-green-600',
                        buttonClass: 'bg-blue-600 hover:bg-blue-700'
                    };
            }
        }

        function playNewConversationSound() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                // Sonido más agradable: "ding-dong"
                oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
                oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.15);
                oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.3);
                
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.5);
            } catch (error) {
                console.log('Nueva conversación pendiente (sonido no disponible)');
            }
        }
        async function checkGlobalPendingConversations() {
            try {
                console.log('Verificando conversaciones pendientes...');
                
                const response = await fetch(`${ADMIN_API}/agent-assignments/my-sessions?status=waiting&limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (!response.ok) {
                    console.warn('Error en respuesta:', response.status);
                    return;
                }

                const result = await response.json();
                console.log('Respuesta recibida:', result);
                
                if (result.success && result.data && result.data.sessions) {
                    // Filtrado más simple - solo verificar que existan y estén esperando
                    const activeConversations = result.data.sessions.filter(session => {
                        if (!session || !session.id) return false;
                        
                        const isWaiting = session.status === 'waiting' && !session.agent_id;
                        const isNotExpired = !isConversationExpired(session.created_at);
                        
                        console.log(`Sesión ${session.id}: waiting=${isWaiting}, expired=${!isNotExpired}`);
                        return isWaiting && isNotExpired;
                    });
                    
                    console.log(`Conversaciones activas encontradas: ${activeConversations.length}`);
                    
                    // Detectar y notificar - versión corregida
                    detectAndNotifyNewConversationsFixed(activeConversations);
                } else {
                    console.warn('Respuesta sin datos válidos');
                }
                
            } catch (error) {
                console.error('Error verificando conversaciones:', error);
            }
        }

        function detectAndNotifyNewConversationsFixed(currentConversations) {
            if (!Array.isArray(currentConversations)) {
                console.warn('⚠️ currentConversations no es array');
                return;
            }
            
            const currentIds = new Set(currentConversations.map(conv => conv.id));
            console.log('🆔 IDs actuales:', Array.from(currentIds));
            console.log('🆔 IDs anteriores:', Array.from(previousPendingConversationIds));
            
            // CORRECCIÓN PRINCIPAL: No saltar en la primera verificación si hay conversaciones
            if (isFirstPendingCheck) {
                isFirstPendingCheck = false;
                
                // Si es la primera vez Y hay conversaciones, notificar todas como nuevas
                if (currentConversations.length > 0) {
                    console.log('🎯 Primera verificación con conversaciones - notificando todas');
                    currentConversations.forEach(conv => {
                        console.log(`🔔 Nueva conversación detectada: ${conv.id}`);
                        showSingleConversationNotification(conv);
                    });
                    
                    // Opcional: Mostrar resumen si hay muchas
                    if (currentConversations.length > 1) {
                        playNewConversationSound();
                        showGlobalPendingNotification(
                            `🔔 ${currentConversations.length} conversaciones pendientes encontradas\n📋 Revisa la sección de pendientes`, 
                            currentConversations.length
                        );
                    }
                }
                
                previousPendingConversationIds = new Set(currentIds);
                return;
            }
            
            // Encontrar conversaciones realmente nuevas
            const newConversations = currentConversations.filter(conv => 
                !previousPendingConversationIds.has(conv.id)
            );
            
            console.log(`🆕 Conversaciones nuevas: ${newConversations.length}`);
            
            // Si hay nuevas conversaciones, notificar
            if (newConversations.length > 0) {
                console.log('🎉 Notificando nuevas conversaciones');
                
                // Reproducir sonido
                playNewConversationSound();
                
                // Notificar cada conversación nueva
                newConversations.forEach(conv => {
                    showSingleConversationNotification(conv);
                });
                
                // Mostrar notificación global si hay varias
                if (newConversations.length > 1) {
                    showGlobalPendingNotification(
                        `🔔 ${newConversations.length} nuevas conversaciones pendientes\n📋 Revisa la sección de pendientes`, 
                        newConversations.length
                    );
                }
                
                console.log('📝 Log de nuevas conversaciones:');
                newConversations.forEach(conv => {
                    console.log(`  - ${conv.id}: ${getPatientNameFromSession(conv)}`);
                });
            }
            
            // Actualizar lista de IDs anteriores
            previousPendingConversationIds = new Set(currentIds);
        }
        function showSingleConversationNotification(conversation) {
            const patientName = getPatientNameFromSession(conversation);
            const roomName = getRoomNameFromSession(conversation);
            const waitTime = getTimeAgo(conversation.created_at);
            
            const message = `🔔 Nueva conversación pendiente\n👤 ${patientName}\n🏥 ${roomName}\n⏱️ Esperando ${waitTime}`;
            
            showGlobalPendingNotification(message, 1, conversation.id);
        }

        function showGlobalPendingNotification(message, count, conversationId = null) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-xl max-w-sm text-white bg-blue-600 border-l-4 border-blue-400';
            notification.style.minWidth = '300px';
            
            const messageLines = message.split('\n');
            const formattedMessage = messageLines.map(line => `<div class="text-sm leading-relaxed">${line}</div>`).join('');
            
            // Botones de acción mejorados
            let actionButtons = `
                <button onclick="goToPendingFromNotification()" 
                        class="flex-1 px-3 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium transition-colors flex items-center justify-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    Ver Pendientes
                </button>
            `;
            
            // Si es una conversación específica, añadir botón para tomar directamente
            if (conversationId) {
                actionButtons = `
                    <button onclick="takeConversationDirectlyFromNotification('${conversationId}')" 
                            class="flex-1 px-3 py-2 bg-white bg-opacity-30 hover:bg-opacity-40 rounded text-xs font-medium transition-colors">
                        Tomar Ahora
                    </button>
                    <button onclick="goToPendingFromNotification()" 
                            class="flex-1 px-3 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium transition-colors">
                        Ver Lista
                    </button>
                `;
            }
            
            notification.innerHTML = `
                <div class="flex flex-col gap-3">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="text-sm font-bold mb-2 flex items-center">
                                <div class="w-2 h-2 bg-white rounded-full mr-2 animate-pulse"></div>
                                Nueva${count > 1 ? 's' : ''} Conversación${count > 1 ? 'es' : ''}
                            </div>
                            ${formattedMessage}
                        </div>
                        <button onclick="this.closest('.fixed').remove()" 
                                class="ml-3 text-xl font-bold hover:bg-white hover:bg-opacity-20 rounded px-2 py-1 transition-colors">×</button>
                    </div>
                    <div class="flex gap-2 pt-2 border-t border-white border-opacity-30">
                        ${actionButtons}
                        <button onclick="this.closest('.fixed').remove()" 
                                class="px-3 py-2 bg-white bg-opacity-10 hover:bg-opacity-20 rounded text-xs font-medium transition-colors">
                            Después
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover después de 10 segundos
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 10000);
            
            // Efecto de entrada suave
            notification.style.transform = 'translateX(100%) scale(0.8)';
            notification.style.opacity = '0';
            notification.style.transition = 'all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0) scale(1)';
                notification.style.opacity = '1';
            }, 100);
        }

        async function takeConversationDirectlyFromNotification(conversationId) {
            // Cerrar notificaciones
            document.querySelectorAll('.fixed.top-4.right-4').forEach(n => n.remove());
            
            try {
                // Buscar la conversación
                const conversation = pendingConversations?.find(c => c.id === conversationId) ||
                                    allChatsForSidebar?.pending?.find(c => c.id === conversationId);
                
                if (conversation) {
                    await takeConversationWithSession(conversationId, conversation);
                } else {
                    // Si no la encontramos localmente, intentar tomarla directamente
                    showNotification('Tomando conversación...', 'info', 2000);
                    await takeConversation(conversationId);
                }
            } catch (error) {
                showNotification('Error tomando conversación: ' + error.message, 'error');
                // Fallback: ir a pendientes
                goToPendingFromNotification();
            }
        }
        function goToPendingFromNotification() {
            // Cerrar notificación
            document.querySelectorAll('.fixed.top-4.right-4').forEach(n => n.remove());
            
            // Ir a sección de pendientes
            showPendingSection();
            
            // Mostrar mensaje de confirmación
            setTimeout(() => {
                showNotification('Mostrando conversaciones pendientes', 'success', 2000);
            }, 500);
        }

        function updatePendingCountBadge(count) {
            const badge = document.getElementById('pendingCount');
            const mobileBadge = document.getElementById('mobilePendingCount');
            
            if (badge) badge.textContent = count;
            if (mobileBadge) mobileBadge.textContent = count;
        }

        function startGlobalPendingMonitoringFixed() {
            console.log('🚀 Iniciando monitoreo de conversaciones pendientes (versión corregida)');
            
            // Limpiar interval previo si existe
            if (globalPendingCheckInterval) {
                clearInterval(globalPendingCheckInterval);
            }
            
            // Reset de variables
            isFirstPendingCheck = true;
            previousPendingConversationIds.clear();
            
            // Verificar inmediatamente
            setTimeout(() => {
                console.log('⏰ Primera verificación...');
                checkGlobalPendingConversations();
            }, 2000);
            
            // Verificar más frecuentemente al inicio (cada 10 segundos por 2 minutos)
            let checkCount = 0;
            const quickCheckInterval = setInterval(() => {
                checkCount++;
                console.log(`⚡ Verificación rápida ${checkCount}/12`);
                checkGlobalPendingConversations();
                
                if (checkCount >= 12) { // 2 minutos de verificaciones cada 10 segundos
                    clearInterval(quickCheckInterval);
                    // Cambiar a verificaciones normales cada 20 segundos
                    globalPendingCheckInterval = setInterval(() => {
                        checkGlobalPendingConversations();
                    }, 20000);
                    console.log('🔄 Cambiando a monitoreo normal cada 20 segundos');
                }
            }, 10000);
            
            console.log('📡 Monitoreo iniciado - verificaciones rápidas por 2 minutos, luego cada 20 segundos');
        }

        // Función para detener el monitoreo
        function stopGlobalPendingMonitoring() {
            if (globalPendingCheckInterval) {
                clearInterval(globalPendingCheckInterval);
                globalPendingCheckInterval = null;
                console.log('🛑 Monitoreo global detenido');
            }
        }


        function getExpirationMessage(convStatus) {
            const minutesLeft = CONVERSATION_CONFIG.EXPIRATION_TIME_MINUTES - convStatus.minutes;
            
            if (convStatus.status === 'critical') {
                return `⏰ Expira en ${Math.max(0, Math.floor(minutesLeft))} min`;
            } else if (convStatus.status === 'warning') {
                return `⚠️ Expira en ${Math.floor(minutesLeft)} min`;
            }
            return '';
        }

        function showNotification(message, type = 'info', duration = 4000) {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500',
                warning: 'bg-yellow-500'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm text-white ${colors[type]}`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-xl">×</button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function formatFileSize(bytes) {
            if (!bytes) return '';
            
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }

        function normalizeTimestamp(timestamp) {
            if (!timestamp) return new Date().toISOString();
            
            let date;
            if (typeof timestamp === 'string') {
                date = new Date(timestamp);
            } else if (typeof timestamp === 'number') {
                date = new Date(timestamp);
            } else {
                date = new Date();
            }
            
            if (isNaN(date.getTime())) {
                date = new Date();
            }
            
            return date.toISOString();
        }

        function isValidUUID(str) {
            const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
            return uuidRegex.test(str);
        }

        function toggleFileUpload() {
            const uploadArea = document.getElementById('fileUploadArea');
            isFileUploadVisible = !isFileUploadVisible;
            
            if (isFileUploadVisible) {
                uploadArea.classList.remove('hidden');
                uploadArea.addEventListener('click', () => {
                    document.getElementById('fileInput').click();
                });
                setupDragAndDrop();
            } else {
                uploadArea.classList.add('hidden');
            }
        }

        function setupDragAndDrop() {
            const uploadArea = document.getElementById('fileUploadArea');
            
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('drag-over');
            });
            
            uploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                const files = Array.from(e.dataTransfer.files);
                handleFileSelection(files);
            });
        }

        function handleFileSelection(files) {
            const maxSize = 10 * 1024 * 1024;
            const allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
                'application/pdf',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain', 'text/csv',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            
            const validFiles = [];
            const errors = [];
            
            for (const file of files) {
                if (file.size > maxSize) {
                    errors.push(`${file.name}: Archivo muy grande (${(file.size / (1024 * 1024)).toFixed(2)}MB, máximo 10MB)`);
                    continue;
                }
                
                const isValidType = allowedTypes.includes(file.type) || 
                                file.name.toLowerCase().endsWith('.pdf') ||
                                file.name.toLowerCase().match(/\.(jpg|jpeg|png|gif|bmp|webp|txt|csv|json|xml|log|doc|docx|xls|xlsx)$/);
                
                if (!isValidType) {
                    errors.push(`${file.name}: Tipo de archivo no permitido (${file.type})`);
                    continue;
                }
                
                validFiles.push(file);
            }
            
            if (errors.length > 0) {
                showNotification(errors.join('\n'), 'error', 6000);
            }
            
            if (validFiles.length > 0) {
                selectedFiles = [...selectedFiles, ...validFiles];
                displaySelectedFiles();
                document.getElementById('fileUploadArea').classList.add('hidden');
                isFileUploadVisible = false;
            }
        }

        function displaySelectedFiles() {
            const previewArea = document.getElementById('filePreviewArea');
            const filesList = document.getElementById('selectedFilesList');
            
            if (selectedFiles.length === 0) {
                previewArea.classList.add('hidden');
                updateSendButton();
                return;
            }
            
            previewArea.classList.remove('hidden');
            
            filesList.innerHTML = selectedFiles.map((file, index) => `
                <div class="file-preview">
                    <svg class="w-4 h-4 flex-shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">${file.name}</p>
                        <p class="text-xs text-gray-500">${formatFileSize(file.size)}</p>
                    </div>
                    <button onclick="removeSelectedFile(${index})" class="text-red-500 hover:text-red-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `).join('');
            
            updateSendButton();
        }

        function removeSelectedFile(index) {
            selectedFiles.splice(index, 1);
            displaySelectedFiles();
        }

        function clearSelectedFiles() {
            selectedFiles = [];
            displaySelectedFiles();
        }

        async function uploadFiles() {
            if (selectedFiles.length === 0 || !currentSession) return [];
            
            const uploadArea = document.getElementById('uploadProgressArea');
            const progressBar = document.getElementById('uploadProgressBar');
            const progressText = document.getElementById('uploadProgressText');
            
            uploadArea.classList.remove('hidden');
            
            const uploadedFiles = [];
            const currentUser = getCurrentUser();
            
            try {
                for (let i = 0; i < selectedFiles.length; i++) {
                    const file = selectedFiles[i];
                    
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('session_id', currentSession.id);
                    formData.append('user_id', currentUser.id);
                    formData.append('user_type', 'agent');
                    formData.append('sender_type', 'agent');
                    
                    const response = await fetch(`${FILE_API}/upload`, {
                        method: 'POST',
                        headers: getFileUploadHeaders(),
                        body: formData
                    });
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Error subiendo ${file.name}: ${response.status} ${response.statusText}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        let fileId = null;
                        let downloadUrl = null;
                        
                        if (result.data && result.data.file && result.data.file.id) {
                            fileId = result.data.file.id;
                            downloadUrl = result.data.file.download_url;
                        } else if (result.data && result.data.file_id) {
                            fileId = result.data.file_id;
                            downloadUrl = result.data.download_url;
                        } else if (result.data && result.data.id) {
                            fileId = result.data.id;
                            downloadUrl = result.data.download_url;
                        } else if (result.file_id) {
                            fileId = result.file_id;
                            downloadUrl = result.download_url;
                        } else {
                            throw new Error('No se pudo obtener el ID del archivo del servidor');
                        }
                        
                        const fileName = file.name;
                        trackRecentAgentUpload(fileName);
                        
                        const completeFileData = {
                            id: fileId,
                            original_name: fileName,
                            file_size: file.size,
                            file_type: file.type,
                            download_url: downloadUrl || `/files/download/${fileId}`,
                            preview_url: `/files/preview/${fileId}`
                        };
                        
                        uploadedFiles.push(completeFileData);
                    } else {
                        throw new Error(`Error en resultado: ${result.message || 'Error desconocido'}`);
                    }
                    
                    const progress = ((i + 1) / selectedFiles.length) * 100;
                    progressBar.style.width = `${progress}%`;
                    progressText.textContent = `${Math.round(progress)}%`;
                }
                
                clearSelectedFiles();
                
                setTimeout(() => {
                    uploadArea.classList.add('hidden');
                }, 1000);
                
                return uploadedFiles;
                
            } catch (error) {
                showNotification('Error subiendo archivos: ' + error.message, 'error');
                uploadArea.classList.add('hidden');
                return [];
            }
        }

        function openFileInNewTab(url, fileName) {
            if (!url || url === '#') {
                showNotification('URL de archivo no válida', 'error');
                return;
            }
            
            try {
                new URL(url, window.location.origin);
            } catch (error) {
                showNotification('URL de archivo inválida: ' + url, 'error');
                return;
            }
            
            try {
                const newWindow = window.open(url, '_blank', 'noopener,noreferrer');
                
                if (newWindow) {
                    newWindow.focus();
                    showNotification(`Abriendo vista previa de ${fileName}`, 'info', 2000);
                } else {
                    const link = document.createElement('a');
                    link.href = url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    showNotification(`Vista previa de ${fileName} abierta`, 'info', 2000);
                }
                
            } catch (error) {
                showNotification('Error abriendo archivo: ' + error.message, 'error');
            }
        }

        function clearMessageDuplicationControl() {
            if (currentSession && currentSession.id) {
                if (sentMessages.size > 0) {
                    sessionMessageIds.set(currentSession.id, new Set(sentMessages));
                }
            }
            sentMessages.clear();
            messageIdCounter = 0;
        }

        function restoreMessageDuplicationControl(sessionId) {
            const session = currentSession;
            const isTransferred = session && session.transfer_info && session.transfer_info.transferred_to_me;
            const isRecovered = session && session.transfer_info && session.transfer_info.transfer_rejected;
            
            if ((!isTransferred && !isRecovered) && sessionMessageIds.has(sessionId)) {
                const sessionMessages = sessionMessageIds.get(sessionId);
                sessionMessages.forEach(id => sentMessages.add(id));
            }
        }

        function clearDuplicationForTransferredChat() {
            sentMessages.clear();
            messageIdCounter = 0;
        }

        function generateUniqueMessageId(data) {
            const userId = data.user_id || data.sender_id || 'unknown';
            const userType = data.user_type || data.sender_type || 'unknown';
            const messageType = data.message_type || 'text';
            const timestamp = Math.floor((data.timestamp || data.created_at || Date.now()) / 1000);
            
            if (messageType === 'file' && data.file_data && data.file_data.id) {
                return `${userId}_${userType}_${messageType}_${data.file_data.id}_${timestamp}`;
            }
            
            const content = (data.content || '').substring(0, 50);
            let contentHash = '';
            for (let i = 0; i < content.length; i++) {
                contentHash += content.charCodeAt(i).toString(16);
            }
            contentHash = contentHash.substring(0, 10);
            
            return `${userId}_${userType}_${messageType}_${contentHash}_${timestamp}`;
        }

        function showPendingSection() {
            hideAllSections();
            document.getElementById('pending-conversations-section').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = 'Conversaciones Pendientes';
            updateNavigation('pending');
            hidePatientInfoButton();
            loadPendingConversations();
        }

        function showMyChatsSection() {
            hideAllSections();
            document.getElementById('my-chats-section').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = 'Mis Chats';
            updateNavigation('my-chats');
            hidePatientInfoButton();
            loadMyChats();
        }

        function showRoomsSection() {
            hideAllSections();
            document.getElementById('rooms-list-section').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = 'Salas de Atención';
            updateNavigation('rooms');
            hidePatientInfoButton();
            loadRoomsFromAuthService();
        }

        function showProfileSection() {
            hideAllSections();
            document.getElementById('profile-section').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = 'Mi Perfil';
            updateNavigation('profile');
            hidePatientInfoButton();
            loadUserProfile();
        }

        function goBackToPending() {
            if (currentSession) {
                if (chatSocket) {
                    chatSocket.disconnect();
                }
                currentSession = null;
                stopSessionTimer();
                clearMessageDuplicationControl();
                clearSelectedFiles();
            }
            hidePatientInfoButton();
            showPendingSection();
        }

        function hideAllSections() {
            const sections = [
                'pending-conversations-section',
                'my-chats-section',
                'rooms-list-section',
                'room-sessions-section',
                'profile-section',
                'patient-chat-panel'
            ];
            
            sections.forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (section) {
                    section.classList.add('hidden');
                }
            });
        }

        function detectNewTransfers(currentSessions, previousSessionIds, sessionType = 'pending'){
            const newTransfers = [];
    
            currentSessions.forEach(session => {
                // Solo verificar si es una sesión nueva (no estaba en la lista anterior)
                if (!previousSessionIds.has(session.id)) {
                    const transferInfo = analyzeTransferInfo(session);
                    
                    if (transferInfo.isTransfer) {
                        newTransfers.push({
                            session: session,
                            transferType: transferInfo.type,
                            transferFrom: transferInfo.from,
                            transferReason: transferInfo.reason,
                            sessionType: sessionType
                        });
                    }
                }
            });
            
            return newTransfers;
        }

        /**
         * Analiza la información de transferencia de una sesión
         */
        function analyzeTransferInfo(session) {
            if (!session.transfer_info) {
                return { isTransfer: false };
            }
            
            const transfer = session.transfer_info;
            
            // Transferencia interna recibida
            if (transfer.transferred_to_me) {
                return {
                    isTransfer: true,
                    type: 'internal_received',
                    from: transfer.from_agent_name || transfer.from_agent_id || 'Agente desconocido',
                    reason: transfer.reason || 'Sin motivo especificado'
                };
            }
            
            // Transferencia externa rechazada (devuelta)
            if (transfer.transfer_rejected || transfer.external_transfer_rejected) {
                return {
                    isTransfer: true,
                    type: 'external_rejected',
                    from: transfer.rejected_by || 'Supervisor',
                    reason: transfer.rejection_reason || transfer.reason || 'Transferencia rechazada'
                };
            }
            
            // Transferencia externa aprobada y recibida
            if (transfer.external_transfer_approved) {
                return {
                    isTransfer: true,
                    type: 'external_received',
                    from: transfer.from_room || transfer.original_room || 'Otra sala',
                    reason: transfer.reason || 'Transferencia externa'
                };
            }
            
            return { isTransfer: false };
        }

        /**
         * Muestra notificación apropiada según el tipo de transferencia
         */
        function showTransferNotification(transferData) {
            const { session, transferType, transferFrom, transferReason, sessionType } = transferData;
            const patientName = getPatientNameFromSession(session);
            const roomName = getRoomNameFromSession(session);
            
            // Evitar notificaciones duplicadas
            const notificationKey = `${session.id}_${transferType}`;
            if (transferNotificationCache.has(notificationKey)) {
                return;
            }
            transferNotificationCache.set(notificationKey, Date.now());
            
            let message, type, duration;
            
            switch (transferType) {
                case 'internal_received':
                    message = `🔄 Nueva sesión por transferencia interna\n👤 Paciente: ${patientName}\n🏥 Sala: ${roomName}\n👨‍💼 De: ${transferFrom}`;
                    type = 'success';
                    duration = 8000;
                    break;
                    
                case 'external_received':
                    message = `📥 Nueva sesión por transferencia externa\n👤 Paciente: ${patientName}\n🏥 De sala: ${transferFrom}\n📝 Motivo: ${transferReason}`;
                    type = 'info';
                    duration = 8000;
                    break;
                    
                case 'external_rejected':
                    message = `❌ Transferencia rechazada devuelta\n👤 Paciente: ${patientName}\n🏥 Sala: ${roomName}\n📝 ${transferReason}`;
                    type = 'warning';
                    duration = 8000;
                    break;
                    
                default:
                    message = `📨 Nueva sesión transferida\n👤 Paciente: ${patientName}`;
                    type = 'info';
                    duration = 6000;
            }
            
            // Mostrar notificación mejorada
            showEnhancedTransferNotification(message, type, duration, {
                sessionId: session.id,
                patientName: patientName,
                transferType: transferType,
                action: sessionType === 'pending' ? 'take' : 'continue'
            });
            
            // Reproducir sonido de notificación (opcional)
            playTransferNotificationSound(transferType);
        }

        /**
         * Notificación mejorada con acciones
         */
        function showEnhancedTransferNotification(message, type = 'info', duration = 6000, actionData = null) {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500',
                warning: 'bg-yellow-500'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm text-white ${colors[type]} border-l-4 border-white`;
            
            const messageLines = message.split('\n');
            const formattedMessage = messageLines.map(line => `<div>${line}</div>`).join('');
            
            notification.innerHTML = `
                <div class="flex flex-col gap-2">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            ${formattedMessage}
                        </div>
                        <button onclick="this.closest('.fixed').remove()" class="ml-2 text-xl font-bold hover:bg-white hover:bg-opacity-20 rounded px-1">×</button>
                    </div>
                    ${actionData ? `
                        <div class="flex gap-2 mt-2 pt-2 border-t border-white border-opacity-30">
                            <button onclick="handleTransferNotificationAction('${actionData.sessionId}', '${actionData.action}')" 
                                    class="px-3 py-1 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium transition-colors">
                                ${actionData.action === 'take' ? '📞 Tomar Ahora' : '💬 Continuar Chat'}
                            </button>
                            <button onclick="this.closest('.fixed').remove()" 
                                    class="px-3 py-1 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium transition-colors">
                                ⏰ Después
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover después del tiempo especificado
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
            
            // Efecto de entrada
            notification.style.transform = 'translateX(100%)';
            notification.style.transition = 'transform 0.3s ease-out';
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
        }
        /**
         * Maneja las acciones de las notificaciones de transferencia
         */
        async function handleTransferNotificationAction(sessionId, action) {
            try {
                if (action === 'take') {
                    // Buscar la sesión en las listas actuales
                    let session = null;
                    
                    if (pendingConversations) {
                        session = pendingConversations.find(s => s.id === sessionId);
                    }
                    
                    if (!session && allChatsForSidebar?.pending) {
                        session = allChatsForSidebar.pending.find(s => s.id === sessionId);
                    }
                    
                    if (session) {
                        await takeConversationWithSession(sessionId, session);
                        showNotification('✅ Sesión transferida tomada exitosamente', 'success');
                    } else {
                        showNotification('❌ Sesión no encontrada, puede haber sido tomada por otro agente', 'warning');
                    }
                } else if (action === 'continue') {
                    // Buscar en mis chats activos
                    let session = null;
                    
                    if (myChats) {
                        session = myChats.find(s => s.id === sessionId);
                    }
                    
                    if (!session && allChatsForSidebar?.myChats) {
                        session = allChatsForSidebar.myChats.find(s => s.id === sessionId);
                    }
                    
                    if (session) {
                        await openChatDirectly(session);
                        showNotification('✅ Continuando chat transferido', 'success');
                    } else {
                        showNotification('❌ Sesión no encontrada en tus chats activos', 'warning');
                    }
                }
            } catch (error) {
                showNotification('❌ Error: ' + error.message, 'error');
            }
        }

        async function loadRoomsFromAuthService() {
            try {
                const token = getToken();
                if (!token) {
                    return loadRoomsFallback();
                }
                
                const currentUser = getCurrentUser();
                
                try {
                    const agentRoomsResponse = await fetch(`${ADMIN_API}/agent-assignments/my-rooms`, {
                        method: 'GET',
                        headers: getAuthHeaders()
                    });

                    if (agentRoomsResponse.ok) {
                        const agentRoomsResult = await agentRoomsResponse.json();
                        
                        if (agentRoomsResult.success && agentRoomsResult.data) {
                            const assignmentInfo = agentRoomsResult.data;
                            
                            if (assignmentInfo.rooms && assignmentInfo.rooms.length > 0) {
                                rooms = assignmentInfo.rooms.map(room => ({
                                    id: room.id,
                                    name: room.name,
                                    description: room.description || 'Sala de atención',
                                    type: room.room_type || 'general',
                                    available: true,
                                    estimated_wait: '5-10 min',
                                    current_queue: 0,
                                    is_assigned: true
                                }));
                                
                                showNotification(`Tienes acceso a ${assignmentInfo.total_rooms} sala(s) asignada(s)`, 'success', 4000);
                                displayRooms();
                                return rooms;
                            }
                        }
                    }
                } catch (adminError) {
                    console.log('Admin-service no disponible:', adminError.message);
                }
                
                if (currentUser && currentUser.id) {
                    try {
                        const agentSpecificUrl = ` /rooms/available/${currentUser.id}`;
                        const agentResponse = await fetch(agentSpecificUrl, {
                            method: 'GET',
                            headers: getAuthHeaders()
                        });

                        if (agentResponse.ok) {
                            const agentData = await agentResponse.json();
                            
                            if (agentData.success && agentData.data?.rooms) {
                                rooms = agentData.data.rooms;
                                
                                if (agentData.data.assignment_status === 'assigned_rooms_only') {
                                    showNotification(`Mostrando ${agentData.data.assigned_rooms_count} sala(s) asignada(s)`, 'info', 4000);
                                } else {
                                    showNotification('Sin asignaciones - acceso a todas las salas', 'info', 4000);
                                }
                                
                                displayRooms();
                                return rooms;
                            }
                        }
                    } catch (agentSpecificError) {
                        console.log('Auth-service específico falló:', agentSpecificError.message);
                    }
                }
                
                try {
                    const generalResponse = await fetch(`${AUTH_API}/rooms/available`, {
                        method: 'GET',
                        headers: getAuthHeaders()
                    });

                    if (generalResponse.ok) {
                        const generalData = await generalResponse.json();
                        const roomsData = generalData.data?.rooms || generalData.rooms || [];
                        
                        if (Array.isArray(roomsData) && roomsData.length > 0) {
                            rooms = roomsData;
                            showNotification('Usando lista general de salas', 'warning', 3000);
                            displayRooms();
                            return roomsData;
                        }
                    }
                } catch (generalError) {
                    console.log('Auth-service general falló:', generalError.message);
                }
                
                showNotification('Servicios no disponibles - usando salas de prueba', 'warning', 5000);
                return loadRoomsFallback();
                
            } catch (error) {
                showNotification('Error conectando - usando salas de prueba', 'error', 4000);
                return loadRoomsFallback();
            }
        }

        function loadRoomsFallback() {
            rooms = [
                {
                    id: 'general',
                    name: 'Consultas Generales',
                    description: 'Consultas generales y información básica',
                    type: 'general',
                    available: true,
                    estimated_wait: '5-10 min',
                    current_queue: 2
                },
                {
                    id: 'medical',
                    name: 'Consultas Médicas',
                    description: 'Consultas médicas especializadas',
                    type: 'medical',
                    available: true,
                    estimated_wait: '10-15 min',
                    current_queue: 1
                },
                {
                    id: 'support',
                    name: 'Soporte Técnico',
                    description: 'Soporte técnico y ayuda',
                    type: 'support',
                    available: true,
                    estimated_wait: '2-5 min',
                    current_queue: 0
                },
                {
                    id: 'emergency',
                    name: 'Emergencias',
                    description: 'Atención de emergencias médicas',
                    type: 'emergency',
                    available: true,
                    estimated_wait: '1-2 min',
                    current_queue: 0
                }
            ];
            
            displayRooms();
            return rooms;
        }

        function displayRooms() {
            const container = document.getElementById('roomsContainer');
            if (!container) return;

            if (rooms.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <p class="text-gray-500 mb-4">No hay salas disponibles</p>
                        <button onclick="loadRoomsFromAuthService()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Reintentar
                        </button>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    ${rooms.map(room => createRoomCard(room)).join('')}
                </div>
            `;
        }

        function createRoomCard(room) {
            const sessionsCount = sessionsByRoom[room.id]?.length || 0;
            const waitingCount = sessionsByRoom[room.id]?.filter(s => s.status === 'waiting').length || 0;
            
            return `
                <div class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-all cursor-pointer" 
                     onclick="selectRoom('${room.id}')">
                    
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 ${getRoomColorClass(room.type)} rounded-lg flex items-center justify-center">
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">${room.name}</h3>
                                    <p class="text-sm text-gray-500">${room.type || 'General'}</p>
                                </div>
                            </div>
                            
                            ${room.available ? 
                                '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">Disponible</span>' :
                                '<span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">No disponible</span>'
                            }
                        </div>
                    </div>

                    <div class="p-6">
                        <p class="text-gray-600 text-sm mb-4">${room.description || 'Sala de atención médica'}</p>
                        
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div>
                                <div class="text-2xl font-bold text-blue-600">${sessionsCount}</div>
                                <div class="text-xs text-gray-500">Total Sesiones</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-orange-600">${waitingCount}</div>
                                <div class="text-xs text-gray-500">En Cola</div>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-gray-500">
                            <div class="flex justify-between">
                                <span>Tiempo estimado:</span>
                                <span>${room.estimated_wait || '5-10 min'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        async function selectRoom(roomId) {
            try {
                currentRoom = roomId;
                const room = rooms.find(r => r.id === roomId);
                
                if (!room) {
                    showNotification('Sala no encontrada', 'error');
                    return;
                }
                
                document.getElementById('currentRoomName').textContent = room.name;
                
                hideAllSections();
                document.getElementById('room-sessions-section').classList.remove('hidden');
                document.getElementById('sectionTitle').textContent = `Sesiones en: ${room.name}`;
                hidePatientInfoButton();
                await loadSessionsByRoom(roomId);
                
            } catch (error) {
                showNotification('Error seleccionando sala: ' + error.message, 'error');
            }
        }

        async function loadSessionsByRoom(roomId) {
            try {
                const url = `${CHAT_API}/chats/sessions?room_id=${roomId}&include_expired=false`;
                
                const response = await fetch(url, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.sessions) {
                        const processedSessions = result.data.sessions.map(session => processSessionData(session));
                        sessionsByRoom[roomId] = processedSessions;
                        displayRoomSessions(processedSessions, roomId);
                        return processedSessions;
                    } else {
                        sessionsByRoom[roomId] = [];
                        displayRoomSessions([], roomId);
                        return [];
                    }
                } else {
                    throw new Error(`Error HTTP ${response.status}`);
                }
                
            } catch (error) {
                sessionsByRoom[roomId] = [];
                displayRoomSessions([], roomId);
                showNotification('Error cargando sesiones: ' + error.message, 'error');
                return [];
            }
        }

        function processSessionData(session) {
            return {
                id: session.id,
                room_id: session.room_id,
                status: session.status || 'waiting',
                created_at: session.created_at,
                updated_at: session.updated_at,
                user_data: session.user_data,
                user_id: session.user_id,
                agent_id: session.agent_id || null,
                patient_data: session.patient_data || {},
                ptoken: session.ptoken,
                transfer_info: session.transfer_info
            };
        }

        function displayRoomSessions(sessions, roomId) {
            const container = document.getElementById('sessionsContainer');
            if (!container) return;

            if (sessions.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay sesiones activas</h3>
                        <p class="text-gray-500">Esta sala no tiene pacientes esperando atención</p>
                    </div>
                `;
                return;
            }

            const html = sessions.map(session => createSessionCard(session)).join('');
            container.innerHTML = `<div class="space-y-4">${html}</div>`;
        }

        function createSessionCard(session) {
            const patientName = getPatientNameFromSession(session);
            const statusColor = getStatusColor(session.status);
            const timeAgo = getTimeAgo(session.created_at);
            const currentUser = getCurrentUser();
            
            const isMySession = session.agent_id === currentUser.id;
            const canTakeSession = session.status === 'waiting';
            const canContinueSession = session.status === 'active' && isMySession;
            
            return `
                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-lg font-semibold text-blue-700">
                                    ${patientName.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">${patientName}</h4>
                                <p class="text-sm text-gray-600">ID: ${session.id}</p>
                                <p class="text-xs text-gray-500 timer-display">Creado hace ${timeAgo}</p>
                                ${isMySession ? '<p class="text-xs text-blue-600 font-medium">Tu sesión</p>' : ''}
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <span class="px-3 py-1 rounded-full text-sm font-medium ${statusColor}">
                                ${getStatusText(session.status)}
                            </span>
                            <div class="mt-2">
                                ${canTakeSession ? 
                                    `<button onclick="takeSessionFromRoom('${session.id}')" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                        Tomar
                                    </button>` :
                                    canContinueSession ?
                                    `<button onclick="continueSessionFromRoom('${session.id}')" 
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                        Continuar
                                    </button>` :
                                    `<button class="px-4 py-2 bg-gray-300 text-gray-500 rounded-lg text-sm cursor-not-allowed" disabled>
                                        ${session.status === 'active' ? 'Ocupado' : 'No disponible'}
                                    </button>`
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        async function takeSessionFromRoom(sessionId) {
            try {
                const session = findSessionById(sessionId);
                if (!session) {
                    showNotification('Sesión no encontrada', 'error');
                    return;
                }
                
                await takeConversationWithSession(sessionId, session);
                
                if (currentRoom) {
                    await loadSessionsByRoom(currentRoom);
                }
            } catch (error) {
                showNotification('Error al tomar la sesión: ' + error.message, 'error');
            }
        }

        async function continueSessionFromRoom(sessionId) {
            try {
                const session = findSessionById(sessionId);
                if (!session) {
                    showNotification('Sesión no encontrada', 'error');
                    return;
                }
                
                await openChatDirectly(session);
                
            } catch (error) {
                showNotification('Error al continuar la sesión: ' + error.message, 'error');
            }
        }

        function findSessionById(sessionId) {
            for (const roomSessions of Object.values(sessionsByRoom)) {
                const session = roomSessions.find(s => s.id === sessionId);
                if (session) return session;
            }
            return null;
        }

        function getStatusColor(status) {
            const colors = {
                'waiting': 'bg-yellow-100 text-yellow-800',
                'active': 'bg-green-100 text-green-800',
                'ended': 'bg-gray-100 text-gray-800',
                'transferred': 'bg-blue-100 text-blue-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        }

        function getStatusText(status) {
            const texts = {
                'waiting': 'Esperando',
                'active': 'Activo',
                'ended': 'Finalizado',
                'transferred': 'Transferido'
            };
            return texts[status] || 'Desconocido';
        }

        function getRoomColorClass(roomType) {
            const colors = {
                'general': 'bg-blue-100',
                'medical': 'bg-green-100',
                'support': 'bg-purple-100',
                'emergency': 'bg-red-100'
            };
            return colors[roomType] || 'bg-blue-100';
        }

        function renderPendingConversationsHTML(conversations) {
            return conversations.map((conv) => {
                const waitTime = getTimeAgo(conv.created_at);
                const convStatus = getConversationStatus(conv.created_at);
                const urgencyClass = getAdvancedUrgencyClass(conv.created_at);
                const patientName = getPatientNameFromSession(conv);
                const roomName = getRoomNameFromSession(conv);
                
                return `
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors ${urgencyClass.borderClass}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 ${urgencyClass.avatarClass} rounded-full flex items-center justify-center relative">
                                    <span class="text-lg font-semibold ${urgencyClass.textClass}">
                                        ${patientName.charAt(0).toUpperCase()}
                                    </span>
                                    ${convStatus.status !== 'normal' ? `
                                        <div class="absolute -top-1 -right-1 w-4 h-4 ${urgencyClass.indicatorClass} rounded-full border-2 border-white"></div>
                                    ` : ''}
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">${patientName}</h4>
                                    <p class="text-sm text-gray-600">${roomName}</p>
                                    <p class="text-xs text-gray-500">ID: ${conv.id}</p>
                                    ${convStatus.status !== 'normal' ? `
                                        <p class="text-xs ${urgencyClass.statusTextClass} font-medium">
                                            ${getExpirationMessage(convStatus)}
                                        </p>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <p class="text-sm font-medium ${urgencyClass.waitTimeClass}">
                                            Esperando ${waitTime}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            ${new Date(conv.created_at).toLocaleTimeString('es-ES')}
                                        </p>
                                        ${convStatus.status === 'critical' ? `
                                            <p class="text-xs text-red-600 font-bold animate-pulse">
                                                ⚠️ EXPIRA PRONTO
                                            </p>
                                        ` : ''}
                                    </div>
                                    <button 
                                        onclick="takeConversation('${conv.id}')"
                                        class="px-4 py-2 ${urgencyClass.buttonClass} text-white rounded-lg hover:opacity-90 text-sm font-medium transition-all">
                                        ${convStatus.status === 'critical' ? '🚨 Tomar Urgente' : 'Tomar'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderMyChatsHTML(chats) {
            return `<div class="space-y-3">` + chats.map(chat => {
                const patientName = getPatientNameFromSession(chat);
                const roomName = getRoomNameFromSession(chat);
                const activeTime = getTimeAgo(chat.updated_at || chat.created_at);
                
                // 🆕 INDICADOR ESPECIAL si la sesión tiene nota de horario
                const scheduleNote = chat.status_note ? `
                    <p class="text-xs text-orange-600 font-medium">${chat.status_note}</p>
                ` : '';
                
                return `
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-lg font-semibold text-green-700">
                                        ${patientName.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">${patientName}</h4>
                                    <p class="text-sm text-gray-600">${roomName}</p>
                                    <p class="text-xs text-gray-500">ID: ${chat.id}</p>
                                    <p class="text-xs text-green-600 timer-display">Activo: ${activeTime}</p>
                                    ${scheduleNote}
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <button 
                                    onclick="openChatFromMyChats('${chat.id}')"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                                    Continuar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('') + `</div>`;
        }
        function updateNavigation(active) {
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                link.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            
            const navLinks = {
                'pending': 'a[href="#pending"]',
                'my-chats': 'a[href="#my-chats"]',
                'rooms': 'a[href="#rooms"]',
                'profile': 'a[href="#profile"]'
            };
            
            const activeLink = document.querySelector(navLinks[active]);
            if (activeLink) {
                activeLink.classList.add('active');
                activeLink.classList.remove('text-gray-600', 'hover:bg-gray-100');
            }
        }

        function startConversationTimer(sessionId, startTime, type = 'waiting') {
            stopConversationTimer(sessionId);
            
            const startDate = new Date(startTime);
            
            const timerInterval = setInterval(() => {
                updateConversationTimerDisplay(sessionId, startDate, type);
            }, 1000);
            
            conversationTimers.set(sessionId, {
                interval: timerInterval,
                startTime: startDate,
                type: type
            });
            
            updateConversationTimerDisplay(sessionId, startDate, type);
        }

        function stopConversationTimer(sessionId) {
            const timer = conversationTimers.get(sessionId);
            if (timer) {
                clearInterval(timer.interval);
                conversationTimers.delete(sessionId);
            }
        }

        function updateConversationTimerDisplay(sessionId, startDate, type) {
            const now = new Date();
            const diff = now - startDate;
            const totalMinutes = diff / (1000 * 60);
            const timeString = formatTime(totalMinutes);
            
            const elements = document.querySelectorAll(`.conversation-timer-${sessionId}`);
            
            elements.forEach(element => {
                if (type === 'waiting') {
                    element.textContent = `Esperando ${timeString}`;
                    element.className = element.className.replace(/text-\w+-\d+/g, '') + ' text-red-600 font-medium timer-display';
                } else if (type === 'active') {
                    element.textContent = `Activo: ${timeString}`;
                    element.className = element.className.replace(/text-\w+-\d+/g, '') + ' text-green-600 timer-display';
                }
            });
        }

        function startSessionTimer(startTime) {
            stopSessionTimer();
            
            const timerElement = document.getElementById('chatTimer');
            if (!timerElement) return;

            const startDate = new Date(startTime);
            
            function updateTimer() {
                const now = new Date();
                const diff = now - startDate;
                const totalMinutes = diff / (1000 * 60);
                
                timerElement.textContent = `• ${formatTime(totalMinutes)}`;
                timerElement.className = 'timer-display ml-2 text-blue-600';
            }
            
            updateTimer();
            currentTimerInterval = setInterval(updateTimer, 1000);
        }

        function stopSessionTimer() {
            if (currentTimerInterval) {
                clearInterval(currentTimerInterval);
                currentTimerInterval = null;
            }
            
            const timerElement = document.getElementById('chatTimer');
            if (timerElement) {
                timerElement.textContent = '';
            }
        }

        function startRealTimeUpdates() {
            realTimeUpdateInterval = setInterval(async () => {
                await updateSidebarCounts();
                updateAllConversationTimers();
            }, 10000);
        }

        function stopRealTimeUpdates() {
            if (realTimeUpdateInterval) {
                clearInterval(realTimeUpdateInterval);
                realTimeUpdateInterval = null;
            }
        }
        function cleanupTransferNotificationCache() {
            const now = Date.now();
            const maxAge = 10 * 60 * 1000; // 10 minutos
            
            transferNotificationCache.forEach((timestamp, key) => {
                if (now - timestamp > maxAge) {
                    transferNotificationCache.delete(key);
                }
            });
        }

        function startTransferMonitoring() {
            // Limpiar interval anterior si existe
            if (transferCheckInterval) {
                clearInterval(transferCheckInterval);
            }
            
            // Verificar cada 15 segundos por nuevas transferencias
            transferCheckInterval = setInterval(async () => {
                try {
                    // Solo verificar si no estamos en una sesión activa de chat
                    if (!currentSession || !isConnectedToChat) {
                        await checkForNewTransfers();
                    }
                    cleanupTransferNotificationCache();
                } catch (error) {
                    console.error('Error en monitoreo de transferencias:', error);
                }
            }, 15000);
        }

        /**
         * Verifica por nuevas transferencias sin mostrar loading
         */
        async function checkForNewTransfers() {
            try {
                // Verificar conversaciones pendientes
                const pendingResponse = await fetch(`${ADMIN_API}/agent-assignments/my-sessions?status=waiting&limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                if (pendingResponse.ok) {
                    const pendingResult = await pendingResponse.json();
                    if (pendingResult.success && pendingResult.data) {
                        const activeConversations = pendingResult.data.sessions.filter(session => {
                            return session.status === 'waiting' && !session.agent_id;
                        });
                        
                        const newPendingTransfers = detectNewTransfers(activeConversations, previousPendingTransfers, 'pending');
                        newPendingTransfers.forEach(transferData => {
                            showTransferNotification(transferData);
                        });
                        
                        // Actualizar cache
                        previousPendingTransfers.clear();
                        activeConversations.forEach(session => {
                            previousPendingTransfers.add(session.id);
                        });
                    }
                }
                
                // Verificar mis chats activos
                const activeResponse = await fetch(`${ADMIN_API}/agent-assignments/my-sessions?status=active&limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                if (activeResponse.ok) {
                    const activeResult = await activeResponse.json();
                    if (activeResult.success && activeResult.data) {
                        const currentUser = getCurrentUser();
                        const activeChats = activeResult.data.sessions.filter(session => 
                            session.status === 'active' && session.agent_id === currentUser.id
                        );
                        
                        const newActiveTransfers = detectNewTransfers(activeChats, previousMyChatsTransfers, 'active');
                        newActiveTransfers.forEach(transferData => {
                            showTransferNotification(transferData);
                        });
                        
                        // Actualizar cache
                        previousMyChatsTransfers.clear();
                        activeChats.forEach(session => {
                            previousMyChatsTransfers.add(session.id);
                        });
                    }
                }
                
            } catch (error) {
                // Silenciosamente ignorar errores del monitoreo automático
                console.error('Error en verificación automática de transferencias:', error);
            }
        }

        /**
         * Detiene el monitoreo automático de transferencias
         */
        function stopTransferMonitoring() {
            if (transferCheckInterval) {
                clearInterval(transferCheckInterval);
                transferCheckInterval = null;
            }
        }

        /**
         * Limpia el cache de notificaciones de transferencias
         */
        function clearTransferNotificationCache() {
            transferNotificationCache.clear();
            previousPendingTransfers.clear();
            previousMyChatsTransfers.clear();
        }


        async function updateSidebarCounts() {
            try {
                const currentUser = getCurrentUser();
                
                try {
                    const [pendingSessions, myActiveSessions] = await Promise.all([
                        loadSessionsWithAssignmentFilter({ waiting: true, agent_specific: true }),
                        loadSessionsWithAssignmentFilter({ waiting: false, agent_specific: true })
                    ]);
                    
                    if (pendingSessions && myActiveSessions) {
                        const activePending = pendingSessions.filter(session => 
                            session.status === 'waiting' && 
                            !session.agent_id &&
                            !isConversationExpired(session.created_at)
                        );
                        
                        const activeChats = myActiveSessions.filter(session => 
                            session.status === 'active' && session.agent_id === currentUser.id
                        );
                        
                        const pendingCountElement = document.getElementById('pendingCount');
                        if (pendingCountElement) {
                            pendingCountElement.textContent = activePending.length;
                        }
                        
                        const myChatsCountElement = document.getElementById('myChatsCount');
                        if (myChatsCountElement) {
                            myChatsCountElement.textContent = activeChats.length;
                        }
                        
                        const mobilePendingCountElement = document.getElementById('mobilePendingCount');
                        if (mobilePendingCountElement) {
                            mobilePendingCountElement.textContent = activePending.length;
                        }
                        
                        const mobileMyChatsCountElement = document.getElementById('mobileMyChatsCount');
                        if (mobileMyChatsCountElement) {
                            mobileMyChatsCountElement.textContent = activeChats.length;
                        }
                        
                        return;
                    }
                } catch (assignmentError) {
                    console.log('Actualizando contadores con método original');
                }
                
                const [pendingResponse, myChatsResponse] = await Promise.all([
                    fetch(`${CHAT_API}/chats/sessions?waiting=true`, {
                        method: 'GET',
                        headers: getAuthHeaders()
                    }),
                    fetch(`${CHAT_API}/chats/sessions?agent_id=${currentUser.id}&active=true`, {
                        method: 'GET',
                        headers: getAuthHeaders()
                    })
                ]);

                if (pendingResponse.ok && myChatsResponse.ok) {
                    const pendingResult = await pendingResponse.json();
                    const myChatsResult = await myChatsResponse.json();
                    
                    if (pendingResult.success && pendingResult.data) {
                        const activePending = pendingResult.data.sessions.filter(session => 
                            session.status === 'waiting' && 
                            !session.agent_id &&
                            !isConversationExpired(session.created_at)
                        );
                        
                        const pendingCountElement = document.getElementById('pendingCount');
                        if (pendingCountElement) {
                            pendingCountElement.textContent = activePending.length;
                        }
                        
                        const mobilePendingCountElement = document.getElementById('mobilePendingCount');
                        if (mobilePendingCountElement) {
                            mobilePendingCountElement.textContent = activePending.length;
                        }
                    }
                    
                    if (myChatsResult.success && myChatsResult.data) {
                        const activeChats = myChatsResult.data.sessions.filter(session => 
                            session.status === 'active' && session.agent_id === currentUser.id
                        );
                        
                        const myChatsCountElement = document.getElementById('myChatsCount');
                        if (myChatsCountElement) {
                            myChatsCountElement.textContent = activeChats.length;
                        }
                        
                        const mobileMyChatsCountElement = document.getElementById('mobileMyChatsCount');
                        if (mobileMyChatsCountElement) {
                            mobileMyChatsCountElement.textContent = activeChats.length;
                        }
                    }
                }
            } catch (error) {
                console.error('Error actualizando contadores:', error);
            }
        }

        function updateAllConversationTimers() {
            conversationTimers.forEach((timer, sessionId) => {
                updateConversationTimerDisplay(sessionId, timer.startTime, timer.type);
            });
        }


        async function loadPendingConversationsWithTransferDetection() {
            const container = document.getElementById('pendingConversationsContainer');
            const countBadge = document.getElementById('pendingCount');
            const mobileCountBadge = document.getElementById('mobilePendingCount');
            
            if (!container || !countBadge) return;
            
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-500">Cargando conversaciones pendientes...</p>
                </div>
            `;

            try {
                const response = await fetch(`${ADMIN_API}/agent-assignments/my-sessions?status=waiting&limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success && result.data) {
                    const { sessions, schedule_info, assignment_info, working_status } = result.data;
                    
                    // MANEJAR INFORMACIÓN DE HORARIOS
                    if (schedule_info && !schedule_info.overall_can_receive) {
                    let scheduleMessage = '';
                    let detailsHTML = '';
                    
                    if (schedule_info.rooms_status && schedule_info.rooms_status.length > 0) {
                        // Mostrar estado por sala
                        const activeRooms = schedule_info.rooms_status.filter(r => r.is_available);
                        const inactiveRooms = schedule_info.rooms_status.filter(r => !r.is_available);
                        
                        if (activeRooms.length > 0) {
                        detailsHTML += `
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-3">
                            <p class="text-sm font-medium text-green-800">Salas activas (${activeRooms.length}):</p>
                            <ul class="text-sm text-green-700 mt-1">
                                ${activeRooms.map(room => `<li>• ${room.room_name}</li>`).join('')}
                            </ul>
                            </div>
                        `;
                        }
                        
                        if (inactiveRooms.length > 0) {
                        detailsHTML += `
                            <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 mb-3">
                            <p class="text-sm font-medium text-orange-800">Salas fuera de horario (${inactiveRooms.length}):</p>
                            <ul class="text-sm text-orange-700 mt-1">
                                ${inactiveRooms.map(room => `<li>• ${room.room_name}</li>`).join('')}
                            </ul>
                            </div>
                        `;
                        }
                        
                        scheduleMessage = activeRooms.length > 0 
                        ? 'Algunas salas están disponibles según tu horario' 
                        : 'Todas tus salas asignadas están fuera de horario';
                    } else {
                        scheduleMessage = 'Fuera del horario de trabajo';
                    }

                    container.innerHTML = `
                        <div class="text-center py-12">
                        <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Estado de Horarios</h3>
                        <p class="text-gray-600 mb-4">${scheduleMessage}</p>
                        ${detailsHTML}
                        <p class="text-sm text-gray-500">
                            ${schedule_info.overall_can_receive 
                            ? 'Puedes recibir conversaciones en salas activas' 
                            : 'No puedes recibir conversaciones nuevas en este momento'}
                        </p>
                        </div>
                    `;
                    
                    countBadge.textContent = schedule_info.active_rooms_count || '0';
                    if (mobileCountBadge) mobileCountBadge.textContent = schedule_info.active_rooms_count || '0';
                    return;
                    }
                    
                    // Comportamiento normal cuando está en horario
                    const activeConversations = sessions.filter(session => {
                        const isWaiting = session.status === 'waiting' && !session.agent_id;
                        return isWaiting;
                    });
                    
                    // Detectar transferencias (código existente)
                    const newTransfers = detectNewTransfers(activeConversations, previousPendingTransfers, 'pending');
                    newTransfers.forEach(transferData => {
                        showTransferNotification(transferData);
                    });
                    
                    previousPendingTransfers.clear();
                    activeConversations.forEach(session => {
                        previousPendingTransfers.add(session.id);
                    });
                    
                    pendingConversations = activeConversations;
                    countBadge.textContent = activeConversations.length;
                    if (mobileCountBadge) {
                        mobileCountBadge.textContent = activeConversations.length;
                    }
                    
                    // 🆕 MOSTRAR INFORMACIÓN DE ASIGNACIONES si aplica
                    let assignmentMessage = '';
                    if (assignment_info && assignment_info.room_filter_applied) {
                        assignmentMessage = `
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-3 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">
                                            Mostrando sesiones de tus ${assignment_info.active_rooms} sala(s) asignada(s)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    
                    // Renderizar la lista
                    if (activeConversations.length === 0) {
                        container.innerHTML = assignmentMessage + `
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">¡Todo al día!</h3>
                                <p class="text-gray-500 mb-2">No hay conversaciones pendientes en tus salas asignadas</p>
                                ${working_status && working_status.message ? `<p class="text-sm text-blue-600">${working_status.message}</p>` : ''}
                            </div>
                        `;
                    } else {
                        container.innerHTML = assignmentMessage + `<div class="space-y-3">${renderPendingConversationsHTML(activeConversations)}</div>`;
                    }
                    
                } else {
                    throw new Error('Formato de respuesta inválido');
                }
                
            } catch (error) {
                countBadge.textContent = '!';
                if (mobileCountBadge) {
                    mobileCountBadge.textContent = '!';
                }
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Error al cargar</h3>
                        <p class="text-gray-500 mb-4">Error: ${error.message}</p>
                        <button onclick="loadPendingConversations()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Reintentar
                        </button>
                    </div>
                `;
            }
        }

        async function loadSessionsWithAssignmentFilter(options = {}) {
            const { waiting = false, agent_specific = true } = options;
            
            try {
                if (agent_specific) {
                    try {
                        const agentSessionsUrl = `${CHAT_API}/chats/my-sessions`;
                        const agentParams = new URLSearchParams();
                        
                        if (waiting) {
                            agentParams.append('status', 'waiting');
                        } else {
                            agentParams.append('status', 'active');
                        }
                        
                        const agentResponse = await fetch(`${agentSessionsUrl}?${agentParams.toString()}`, {
                            method: 'GET',
                            headers: getAuthHeaders()
                        });
                        
                        if (agentResponse.ok) {
                            const agentResult = await agentResponse.json();
                            
                            if (agentResult.success && agentResult.data) {
                                return agentResult.data.sessions || [];
                            }
                        }
                    } catch (agentError) {
                        console.log('Endpoint específico de agente falló:', agentError.message);
                    }
                }
                
                let url = `${CHAT_API}/chats/sessions`;
                const params = new URLSearchParams();
                
                if (waiting) {
                    params.append('waiting', 'true');
                } else {
                    const currentUser = getCurrentUser();
                    params.append('agent_id', currentUser.id);
                    params.append('active', 'true');
                }
                
                if (params.toString()) {
                    url += '?' + params.toString();
                }
                
                const response = await fetch(url, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success && result.data && result.data.sessions) {
                        return result.data.sessions;
                    }
                }
                
                return [];
                
            } catch (error) {
                return [];
            }
        }

        /*function renderPendingConversations(conversations) {
            const container = document.getElementById('pendingConversationsContainer');
            
            const html = conversations.map((conv) => {
                const waitTime = getTimeAgo(conv.created_at);
                const convStatus = getConversationStatus(conv.created_at);
                const urgencyClass = getAdvancedUrgencyClass(conv.created_at);
                const patientName = getPatientNameFromSession(conv);
                const roomName = getRoomNameFromSession(conv);
                
                return `
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors ${urgencyClass.borderClass}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 ${urgencyClass.avatarClass} rounded-full flex items-center justify-center relative">
                                    <span class="text-lg font-semibold ${urgencyClass.textClass}">
                                        ${patientName.charAt(0).toUpperCase()}
                                    </span>
                                    ${convStatus.status !== 'normal' ? `
                                        <div class="absolute -top-1 -right-1 w-4 h-4 ${urgencyClass.indicatorClass} rounded-full border-2 border-white"></div>
                                    ` : ''}
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">${patientName}</h4>
                                    <p class="text-sm text-gray-600">${roomName}</p>
                                    <p class="text-xs text-gray-500">ID: ${conv.id}</p>
                                    ${convStatus.status !== 'normal' ? `
                                        <p class="text-xs ${urgencyClass.statusTextClass} font-medium">
                                            ${getExpirationMessage(convStatus)}
                                        </p>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <p class="text-sm font-medium ${urgencyClass.waitTimeClass}">
                                            Esperando ${waitTime}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            ${new Date(conv.created_at).toLocaleTimeString('es-ES')}
                                        </p>
                                        ${convStatus.status === 'critical' ? `
                                            <p class="text-xs text-red-600 font-bold animate-pulse">
                                                ⚠️ EXPIRA PRONTO
                                            </p>
                                        ` : ''}
                                    </div>
                                    <button 
                                        onclick="takeConversation('${conv.id}')"
                                        class="px-4 py-2 ${urgencyClass.buttonClass} text-white rounded-lg hover:opacity-90 text-sm font-medium transition-all">
                                        ${convStatus.status === 'critical' ? '🚨 Tomar Urgente' : 'Tomar'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = `<div class="space-y-3">${html}</div>`;
        }*/

        async function loadMyChatsWithTransferDetection() {
            const container = document.getElementById('myChatsContainer');
            const countBadge = document.getElementById('myChatsCount');
            const mobileCountBadge = document.getElementById('mobileMyChatsCount');
            
            if (!container || !countBadge) return;
            
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-500">Cargando mis chats...</p>
                </div>
            `;

            try {
                const response = await fetch(`${ADMIN_API}/agent-assignments/my-sessions?status=active&limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success && result.data) {
                    const { sessions, schedule_info, assignment_info, working_status } = result.data;
                    
                    const currentUser = getCurrentUser();
                    const activeChats = sessions.filter(session => 
                        session.status === 'active' && session.agent_id === currentUser.id
                    );
                    
                    // Detectar transferencias (código existente)
                    const newTransfers = detectNewTransfers(activeChats, previousMyChatsTransfers, 'active');
                    newTransfers.forEach(transferData => {
                        showTransferNotification(transferData);
                    });
                    
                    previousMyChatsTransfers.clear();
                    activeChats.forEach(session => {
                        previousMyChatsTransfers.add(session.id);
                    });
                    
                    myChats = activeChats;
                    countBadge.textContent = activeChats.length;
                    if (mobileCountBadge) {
                        mobileCountBadge.textContent = activeChats.length;
                    }
                    
                    // 🆕 MENSAJE DE ESTADO DE HORARIOS
                    let scheduleMessage = '';
                    if (schedule_info && schedule_info.schedule_message) {
                        const scheduleIcon = schedule_info.can_receive_sessions ? '✅' : '⏰';
                        const scheduleColor = schedule_info.can_receive_sessions ? 'blue' : 'orange';
                        
                        scheduleMessage = `
                            <div class="bg-${scheduleColor}-50 border-l-4 border-${scheduleColor}-400 p-3 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <span class="text-${scheduleColor}-600">${scheduleIcon}</span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-${scheduleColor}-700">
                                            ${schedule_info.schedule_message}
                                        </p>
                                        ${schedule_info.can_receive_sessions ? '' : `
                                            <p class="text-xs text-${scheduleColor}-600 mt-1">
                                                Solo puedes ver y continuar chats activos
                                            </p>
                                        `}
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    
                    // Renderizar la lista
                    if (activeChats.length === 0) {
                        container.innerHTML = scheduleMessage + `
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Sin chats activos</h3>
                                <p class="text-gray-500">No tienes conversaciones activas en este momento</p>
                                ${working_status && working_status.message ? `
                                    <p class="text-sm text-gray-600 mt-2">${working_status.message}</p>
                                ` : ''}
                            </div>
                        `;
                    } else {
                        container.innerHTML = scheduleMessage + renderMyChatsHTML(activeChats);
                    }
                } else {
                    throw new Error('Formato de respuesta inválido');
                }
                
            } catch (error) {
                countBadge.textContent = '!';
                if (mobileCountBadge) {
                    mobileCountBadge.textContent = '!';
                }
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Error al cargar</h3>
                        <p class="text-gray-500 mb-4">Error: ${error.message}</p>
                        <button onclick="loadMyChats()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Reintentar
                        </button>
                    </div>
                `;
            }
        }

        /*function renderMyChats(chats) {
            const container = document.getElementById('myChatsContainer');
            
            const html = chats.map(chat => {
                const patientName = getPatientNameFromSession(chat);
                const roomName = getRoomNameFromSession(chat);
                const activeTime = getTimeAgo(chat.updated_at || chat.created_at);
                
                return `
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-lg font-semibold text-green-700">
                                        ${patientName.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">${patientName}</h4>
                                    <p class="text-sm text-gray-600">${roomName}</p>
                                    <p class="text-xs text-gray-500">ID: ${chat.id}</p>
                                    <p class="text-xs text-green-600 timer-display">Activo: ${activeTime}</p>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <button 
                                    onclick="openChatFromMyChats('${chat.id}')"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                                    Continuar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = `<div class="space-y-3">${html}</div>`;
        }*/

        async function loadChatsSidebar() {
            const container = document.getElementById('chatsSidebarContainer');
            
            if (!container) return;

            try {
                const currentUser = getCurrentUser();
                if (!currentUser || !currentUser.id) {
                    throw new Error('Usuario actual no válido');
                }
                
                let myActiveChats = [];
                let allPendingChats = [];
                
                try {
                    myActiveChats = await loadSessionsWithAssignmentFilter({ 
                        waiting: false, 
                        agent_specific: true 
                    });
                    
                    allPendingChats = await loadSessionsWithAssignmentFilter({ 
                        waiting: true, 
                        agent_specific: true 
                    });
                    
                    myActiveChats = (myActiveChats || []).filter(s => 
                        s && s.status === 'active' && s.agent_id === currentUser.id
                    );
                    
                    allPendingChats = (allPendingChats || []).filter(session => 
                        session && session.status === 'waiting' && 
                        !session.agent_id && 
                        !isConversationExpired(session.created_at)
                    );
                    
                } catch (assignmentError) {
                    const [myChatsResponse, pendingResponse] = await Promise.all([
                        fetch(`${CHAT_API}/chats/sessions?agent_id=${currentUser.id}&active=true`, {
                            method: 'GET',
                            headers: getAuthHeaders()
                        }),
                        fetch(`${CHAT_API}/chats/sessions?waiting=true`, {
                            method: 'GET',
                            headers: getAuthHeaders()
                        })
                    ]);

                    if (myChatsResponse.ok && pendingResponse.ok) {
                        const myChatsResult = await myChatsResponse.json();
                        const pendingResult = await pendingResponse.json();
                        
                        myActiveChats = (myChatsResult.success && myChatsResult.data && myChatsResult.data.sessions) ? 
                            myChatsResult.data.sessions.filter(s => s && s.status === 'active' && s.agent_id === currentUser.id) : [];
                        
                        const allPendingFromServer = (pendingResult.success && pendingResult.data && pendingResult.data.sessions) ? 
                            pendingResult.data.sessions.filter(s => s && s.status === 'waiting' && !s.agent_id) : [];
                            
                        allPendingChats = allPendingFromServer.filter(session => 
                            !isConversationExpired(session.created_at)
                        );
                    } else {
                        throw new Error('Error en respuestas del sidebar');
                    }
                }
                
                allChatsForSidebar = {
                    myChats: myActiveChats,
                    pending: allPendingChats
                };
                
                renderChatsSidebar(allChatsForSidebar);
                
            } catch (error) {
                container.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="text-sm">Error cargando chats</p>
                        <button onclick="loadChatsSidebar()" class="text-blue-600 text-xs mt-2 hover:underline">Reintentar</button>
                    </div>
                `;
            }
        }

        function renderChatsSidebar(chatsData) {
            const container = document.getElementById('chatsSidebarContainer');
            
            if (!container) return;
            
            if (!chatsData) {
                chatsData = { myChats: [], pending: [] };
            }
            
            let html = '';
            
            if (chatsData.myChats && Array.isArray(chatsData.myChats) && chatsData.myChats.length > 0) {
                html += `<div class="section-divider" data-title="Mis Chats"></div>`;
                chatsData.myChats.forEach(chat => {
                    if (!chat || !chat.id) return;
                    
                    const patientName = getPatientNameFromSession(chat);
                    const roomName = getRoomNameFromSession(chat);
                    const isActive = currentSession && currentSession.id === chat.id;
                    const activeTime = getTimeAgo(chat.updated_at || chat.created_at);
                    
                    if (!conversationTimers.has(chat.id)) {
                        startConversationTimer(chat.id, chat.updated_at || chat.created_at, 'active');
                    }
                    
                    html += `
                        <div class="chat-item ${isActive ? 'active' : ''} p-3 border-b border-gray-100 cursor-pointer" 
                             onclick="selectChatFromSidebar('${chat.id}')" data-chat-id="${chat.id}">
                            <div class="status-indicator status-mine"></div>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-sm font-semibold text-blue-700">
                                        ${patientName.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 text-sm truncate">${patientName}</h4>
                                    <p class="text-xs text-gray-500 truncate">${roomName}</p>
                                    <p class="conversation-timer-${chat.id} text-xs text-green-600 timer-display">Activo: ${activeTime}</p>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            if (chatsData.pending && Array.isArray(chatsData.pending) && chatsData.pending.length > 0) {
                html += `<div class="section-divider" data-title="Pendientes"></div>`;
                chatsData.pending.forEach(chat => {
                    if (!chat || !chat.id) return;
                    
                    const patientName = getPatientNameFromSession(chat);
                    const roomName = getRoomNameFromSession(chat);
                    const waitTime = getTimeAgo(chat.created_at || new Date().toISOString());
                    const convStatus = getConversationStatus(chat.created_at);
                    const urgencyClasses = getAdvancedUrgencyClass(chat.created_at);
                    
                    if (!conversationTimers.has(chat.id)) {
                        startConversationTimer(chat.id, chat.created_at, 'waiting');
                    }
                    
                    html += `
                        <div class="chat-item p-3 border-b border-gray-100 cursor-pointer ${urgencyClasses.borderClass}" 
                             onclick="takeConversationFromSidebar('${chat.id}')" data-chat-id="${chat.id}">
                            <div class="status-indicator ${convStatus.status === 'critical' ? 'status-critical' : 'status-unattended'}"></div>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 ${urgencyClasses.avatarClass} rounded-full flex items-center justify-center flex-shrink-0 relative">
                                    <span class="text-sm font-semibold ${urgencyClasses.textClass}">
                                        ${patientName.charAt(0).toUpperCase()}
                                    </span>
                                    ${convStatus.status !== 'normal' ? `
                                        <div class="absolute -top-1 -right-1 w-3 h-3 ${urgencyClasses.indicatorClass} rounded-full border border-white"></div>
                                    ` : ''}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 text-sm truncate">${patientName}</h4>
                                    <p class="text-xs text-gray-500 truncate">${roomName}</p>
                                    <p class="conversation-timer-${chat.id} text-xs text-red-600 timer-display">
                                        Esperando ${waitTime}
                                        ${convStatus.status === 'critical' ? ' ⚠️' : ''}
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            if (!html) {
                html = `
                    <div class="text-center py-8 text-gray-500">
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <p class="text-sm">No hay chats disponibles</p>
                        <p class="text-xs text-gray-400 mt-1">Los chats aparecerán aquí</p>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }

        function getPatientNameFromSession(session) {
            if (!session) return 'Paciente';
            
            const patientInfo = extractPatientInfo(session);
            
            if (patientInfo.nombreCompleto) {
                return patientInfo.nombreCompleto;
            }
            
            const fullName = `${patientInfo.primer_nombre} ${patientInfo.segundo_nombre} ${patientInfo.primer_apellido} ${patientInfo.segundo_apellido}`
                .replace(/\s+/g, ' ').trim();
                
            if (fullName) {
                return fullName;
            }
            
            if (session.user_data) {
                try {
                    const userData = typeof session.user_data === 'string' 
                        ? JSON.parse(session.user_data) 
                        : session.user_data;
                    
                    if (userData && userData.nombreCompleto) return userData.nombreCompleto;
                    if (userData && userData.name) return userData.name;
                } catch (e) {
                    console.warn('Error parseando user_data:', e);
                }
            }
            
            return 'Paciente';
        }

        function getRoomNameFromSession(session) {
            if (!session) return 'Sala General';
            
            if (session.room_name && session.room_name.trim()) {
                return session.room_name.trim();
            }
            
            if (session.user_data) {
                try {
                    const userData = typeof session.user_data === 'string' 
                        ? JSON.parse(session.user_data) 
                        : session.user_data;
                    
                    if (userData && userData.room_name && userData.room_name.trim()) {
                        return userData.room_name.trim();
                    }
                } catch (e) {
                    console.warn('Error parseando user_data para room_name:', e);
                }
            }
            
            let roomId = session.room_id || session.roomId || session.room || session.type;
            
            if (roomId) {
                const roomNames = {
                    '1': 'Consultas Generales',
                    '2': 'Consultas Médicas',
                    '3': 'Soporte Técnico', 
                    '4': 'Emergencias',
                    'general': 'Consultas Generales',
                    'medical': 'Consultas Médicas', 
                    'support': 'Soporte Técnico',
                    'emergency': 'Emergencias',
                    'emergencias': 'Emergencias',
                    'consulta_general': 'Consultas Generales',
                    'consultas_generales': 'Consultas Generales',
                    'consulta_medica': 'Consultas Médicas',
                    'consultas_medicas': 'Consultas Médicas',
                    'soporte_tecnico': 'Soporte Técnico',
                    'GENERAL': 'Consultas Generales',
                    'MEDICAL': 'Consultas Médicas',
                    'SUPPORT': 'Soporte Técnico',
                    'EMERGENCY': 'Emergencias',
                    'Consultas Generales': 'Consultas Generales',
                    'Consultas Médicas': 'Consultas Médicas',
                    'Soporte Técnico': 'Soporte Técnico',
                    'Emergencias': 'Emergencias'
                };
                
                const roomIdString = String(roomId).trim();
                
                if (roomNames[roomId]) {
                    return roomNames[roomId];
                }
                
                const roomIdLower = roomIdString.toLowerCase();
                if (roomNames[roomIdLower]) {
                    return roomNames[roomIdLower];
                }
                
                for (const [key, value] of Object.entries(roomNames)) {
                    if (key.toLowerCase().includes(roomIdLower) || roomIdLower.includes(key.toLowerCase())) {
                        return value;
                    }
                }
                
                if (isValidUUID(roomIdString)) {
                    return 'Sala Especializada';
                }
                
                const formattedName = roomIdString
                    .replace(/_/g, ' ')
                    .replace(/-/g, ' ')
                    .split(' ')
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                    .join(' ');
                
                return `Sala ${formattedName}`;
            }
            
            return 'Sala General';
        }

        function extractPatientInfo(session) {
            let patientData = {};
            
            if (session.patient_data && Object.keys(session.patient_data).length > 0) {
                patientData = session.patient_data;
            } else if (session.user_data) {
                try {
                    const userData = typeof session.user_data === 'string' 
                        ? JSON.parse(session.user_data) 
                        : session.user_data;
                    
                    if (userData && typeof userData === 'object') {
                        patientData = userData;
                    }
                } catch (e) {
                    console.warn('Error parseando user_data:', e);
                }
            }

            return {
                primer_nombre: patientData.primer_nombre || patientData.firstName || patientData.nombre || '',
                segundo_nombre: patientData.segundo_nombre || patientData.middleName || '',
                primer_apellido: patientData.primer_apellido || patientData.lastName || patientData.apellido || '',
                segundo_apellido: patientData.segundo_apellido || patientData.secondLastName || '',
                nombreCompleto: patientData.nombreCompleto || patientData.fullName || patientData.name || '',
                id: patientData.id || patientData.document || patientData.documento || patientData.cedula || '',
                tipo_documento: patientData.tipo_documento || patientData.documentType || 'CC',
                telefono: patientData.telefono || patientData.phone || patientData.celular || '',
                email: patientData.email || patientData.correo || '',
                ciudad: patientData.ciudad || patientData.city || patientData.municipio || '',
                departamento: patientData.departamento || patientData.state || '',
                direccion: patientData.direccion || patientData.address || '',
                eps: patientData.eps || patientData.insurance || patientData.aseguradora || '',
                plan: patientData.plan || patientData.planType || patientData.tipoplan || '',
                habilitado: patientData.habilitado || patientData.status || patientData.estado || '',
                nomTomador: patientData.nomTomador || patientData.policyHolder || patientData.tomador || '',
                edad: patientData.edad || patientData.age || '',
                fecha_nacimiento: patientData.fecha_nacimiento || patientData.birthDate || patientData.fechaNacimiento || '',
                genero: patientData.genero || patientData.gender || patientData.sexo || ''
            };
        }

        async function fetchPatientDataFromPToken(ptoken) {
            try {
                const response = await fetch(`${AUTH_API}/validate-token?ptoken=${encodeURIComponent(ptoken)}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                
                if (!result.success || !result.data?.data?.membresias?.[0]?.beneficiarios) {
                    throw new Error('Formato de respuesta inválido');
                }

                const beneficiarios = result.data.data.membresias[0].beneficiarios;
                const beneficiarioPrincipal = beneficiarios.find(b => b.tipo_ben === 'PPAL') || beneficiarios[0];
                
                if (!beneficiarioPrincipal) {
                    throw new Error('No se encontró beneficiario principal');
                }

                const membresia = result.data.data.membresias[0];

                return {
                    primer_nombre: beneficiarioPrincipal.primer_nombre || '',
                    segundo_nombre: beneficiarioPrincipal.segundo_nombre || '',
                    primer_apellido: beneficiarioPrincipal.primer_apellido || '',
                    segundo_apellido: beneficiarioPrincipal.segundo_apellido || '',
                    nombreCompleto: `${beneficiarioPrincipal.primer_nombre} ${beneficiarioPrincipal.segundo_nombre} ${beneficiarioPrincipal.primer_apellido} ${beneficiarioPrincipal.segundo_apellido}`.replace(/\s+/g, ' ').trim(),
                    id: beneficiarioPrincipal.id || '',
                    tipo_documento: beneficiarioPrincipal.tipo_id || 'CC',
                    telefono: beneficiarioPrincipal.telefono || '',
                    email: beneficiarioPrincipal.email || '',
                    ciudad: beneficiarioPrincipal.ciudad || '',
                    direccion: beneficiarioPrincipal.direccion || '',
                    eps: beneficiarioPrincipal.eps || '',
                    plan: membresia.plan || '',
                    habilitado: membresia.habilitado || beneficiarioPrincipal.estado || '',
                    nomTomador: membresia.nomTomador || '',
                    edad: beneficiarioPrincipal.edad || '',
                    fecha_nacimiento: beneficiarioPrincipal.nacimiento || '',
                    genero: beneficiarioPrincipal.genero || ''
                };

            } catch (error) {
                throw error;
            }
        }

        async function getPatientInfoWithPToken(session) {
            let patientInfo = extractPatientInfo(session);
            
            if (isPatientInfoEmpty(patientInfo) && session.ptoken) {
                try {
                    const ptokenData = await fetchPatientDataFromPToken(session.ptoken);
                    if (ptokenData) {
                        patientInfo = ptokenData;
                    }
                } catch (error) {
                    console.error('Error obteniendo datos del ptoken:', error);
                }
            }
            
            return patientInfo;
        }

        function isPatientInfoEmpty(patientInfo) {
            const essentialFields = ['primer_nombre', 'primer_apellido', 'nombreCompleto', 'id', 'email'];
            return essentialFields.every(field => !patientInfo[field]);
        }

        function updatePatientInfoUI(patientInfo, session) {
            const fullName = patientInfo.nombreCompleto || 
                `${patientInfo.primer_nombre} ${patientInfo.segundo_nombre} ${patientInfo.primer_apellido} ${patientInfo.segundo_apellido}`
                .replace(/\s+/g, ' ').trim() || 
                getPatientNameFromSession(session);

            const chatPatientName = document.getElementById('chatPatientName');
            if (chatPatientName) chatPatientName.textContent = fullName;

            const chatPatientInitials = document.getElementById('chatPatientInitials');
            if (chatPatientInitials) {
                const initials = ((patientInfo.primer_nombre?.[0] || '') + (patientInfo.primer_apellido?.[0] || '')).toUpperCase() || 
                               fullName.charAt(0).toUpperCase();
                chatPatientInitials.textContent = initials;
            }

            const chatPatientId = document.getElementById('chatPatientId');
            if (chatPatientId) chatPatientId.textContent = session.id;

            const chatRoomName = document.getElementById('chatRoomName');
            if (chatRoomName) chatRoomName.textContent = getRoomNameFromSession(session);

            const chatSessionStatus = document.getElementById('chatSessionStatus');
            if (chatSessionStatus) chatSessionStatus.textContent = 'Activo';

            updatePatientInfoSidebar(patientInfo, fullName);
        }

        function updatePatientInfoSidebar(patientInfo, fullName) {
            const updates = [
                { id: 'patientInfoName', value: fullName },
                { id: 'patientInfoDocument', value: patientInfo.id || '-' },
                { id: 'patientInfoPhone', value: patientInfo.telefono || '-' },
                { id: 'patientInfoEmail', value: patientInfo.email || '-' },
                { id: 'patientInfoCity', value: patientInfo.ciudad || '-' },
                { id: 'patientInfoEPS', value: patientInfo.eps || '-' },
                { id: 'patientInfoPlan', value: patientInfo.plan || '-' },
                { 
                    id: 'patientInfoStatus', 
                    value: patientInfo.habilitado === 'S' || patientInfo.habilitado === 'Activo' || patientInfo.habilitado === 'activo' 
                        ? 'Vigente' 
                        : patientInfo.habilitado === 'N' || patientInfo.habilitado === 'Inactivo' || patientInfo.habilitado === 'inactivo'
                        ? 'Inactivo'
                        : patientInfo.habilitado || 'No especificado'
                },
                { id: 'patientInfoTomador', value: patientInfo.nomTomador || '-' }
            ];

            updates.forEach(update => {
                const element = document.getElementById(update.id);
                if (element) {
                    element.textContent = update.value;
                }
            });
        }

        async function takeConversation(sessionId) {
            try {
                const conversation = pendingConversations.find(c => c.id === sessionId);
                if (!conversation) {
                    throw new Error('Conversación no encontrada en la lista local');
                }
                
                await takeConversationWithSession(sessionId, conversation);
                
            } catch (error) {
                showNotification('Error al tomar la conversación: ' + error.message, 'error');
            }
        }

        async function takeConversationWithSession(sessionId, conversation) {
            try {
                const response = await fetch(`${CHAT_API}/chats/sessions/${sessionId}/assign/me`, {
                    method: 'PUT',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({
                        agent_id: getCurrentUser().id,
                        agent_data: {
                            name: getCurrentUser().name,
                            email: getCurrentUser().email
                        }
                    })
                });
                
                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success) {
                        currentSession = conversation;
                        
                        // NUEVA FUNCIONALIDAD: Detectar si es transferencia interna
                        if (conversation.transfer_info && conversation.transfer_info.transferred_to_me) {
                            showNotification('Nueva sesión recibida por transferencia interna', 'success', 6000);
                        } else {
                            showNotification('Sesión asignada exitosamente', 'success');
                        }
                        
                        setTimeout(() => {
                            openChatDirectly(conversation);
                        }, 1000);
                    } else {
                        throw new Error(result.message || 'Error asignando sesión');
                    }
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `Error HTTP ${response.status}`);
                }
                
            } catch (error) {
                throw error;
            }
        }

        async function takeConversationFromSidebar(sessionId) {
            try {
                let conversation = null;
                
                if (allChatsForSidebar.pending) {
                    conversation = allChatsForSidebar.pending.find(chat => chat.id === sessionId);
                }
                
                if (!conversation && pendingConversations) {
                    conversation = pendingConversations.find(chat => chat.id === sessionId);
                }
                
                if (!conversation) {
                    showNotification('Conversación no encontrada', 'error');
                    return;
                }
                
                await takeConversationWithSession(sessionId, conversation);
                
                setTimeout(() => {
                    loadChatsSidebar();
                }, 1000);
                
            } catch (error) {
                showNotification('Error tomando conversación: ' + error.message, 'error');
            }
        }

        async function openChatFromMyChats(sessionId) {
            try {
                let session = null;
                
                if (myChats && myChats.length > 0) {
                    session = myChats.find(chat => chat.id === sessionId);
                }
                
                if (!session) {
                    showNotification('Sesión no encontrada', 'error');
                    return;
                }
                
                await openChatDirectly(session);
            } catch (error) {
                showNotification('Error abriendo chat: ' + error.message, 'error');
            }
        }

        async function selectChatFromSidebar(sessionId) {
            try {
                let session = null;
                
                if (allChatsForSidebar.myChats) {
                    session = allChatsForSidebar.myChats.find(chat => chat.id === sessionId);
                }
                
                if (!session && myChats) {
                    session = myChats.find(chat => chat.id === sessionId);
                }
                
                if (!session) {
                    showNotification('Sesión no encontrada', 'error');
                    return;
                }
                
                await openChatDirectly(session);
            } catch (error) {
                showNotification('Error abriendo chat: ' + error.message, 'error');
            }
        }

        async function openChatDirectly(session) {
            try {
                if (!session || !session.id) {
                    throw new Error('Sesión no válida');
                }
                
                const isTransferred = session.transfer_info && session.transfer_info.transferred_to_me;
                const isRecovered = session.transfer_info && session.transfer_info.transfer_rejected;
                
                if (currentSession && currentSession.id !== session.id) {
                    clearMessageDuplicationControl();
                }
                
                if (isTransferred || isRecovered) {
                    clearDuplicationForTransferredChat();
                    sessionMessageIds.delete(session.id);
                    if (isRecovered) {
                        chatHistoryCache.delete(session.id);
                    }
                } else {
                    restoreMessageDuplicationControl(session.id);
                }
                
                currentSession = session;
                
                hideAllSections();
                document.getElementById('patient-chat-panel').classList.remove('hidden');

                const enrichedSession = await openPatientChat(session);
                const sessionToUse = enrichedSession || session;
                
                const patientName = getPatientNameFromSession(sessionToUse);
                document.getElementById('sectionTitle').textContent = `Chat con ${patientName}`;

                await loadChatsSidebar();
                
            } catch (error) {
                showNotification('Error abriendo chat: ' + error.message, 'error');
                showPendingSection();
            }
        }

        async function openPatientChat(session) {
            try {
                currentSession = session;

                hideAllSections();
                document.getElementById('patient-chat-panel').classList.remove('hidden');

                const patientInfo = await getPatientInfoWithPToken(session);
                updatePatientInfoUI(patientInfo, session);

                const msgCont = document.getElementById('patientChatMessages');
                if (msgCont) msgCont.innerHTML = '';

                const chatInput = document.getElementById('agentMessageInput');
                const chatButton = document.getElementById('agentSendButton');
                if (chatInput) {
                    chatInput.disabled = false;
                    chatInput.placeholder = 'Escribe tu respuesta...';
                }
                if (chatButton) {
                    chatButton.disabled = false;
                }

                startSessionTimer(session.updated_at || session.created_at);

                await connectToChatWebSocket();
                await loadChatHistory();
                
                setupFileUploadEvents();
                updateSendButton();

                showPatientInfoButton();

                return session;

            } catch (error) {
                showNotification('Error al abrir chat: ' + error.message, 'error');
                throw error;
            }
        }

        async function connectToChatWebSocket() {
            try {
                if (chatSocket) {
                    chatSocket.disconnect();
                    isConnectedToChat = false;
                    sessionJoined = false;
                }
                
                const token = getToken();
                const currentUser = getCurrentUser();
                
                chatSocket = io(API_BASE, {
                    transports: ['websocket', 'polling'],
                    auth: {
                        token: token,
                        user_id: currentUser.id,
                        user_type: 'agent',
                        user_name: currentUser.name,
                        session_id: currentSession.id
                    }
                });
                
                chatSocket.on('connect', () => {
                    isConnectedToChat = true;
                    updateChatStatus('Conectado');
                    
                    // AGREGAR ESTO:
                    setTimeout(() => {
                        joinChatSession();
                    }, 1000);
                });
                
                chatSocket.on('disconnect', () => {
                    isConnectedToChat = false;
                    sessionJoined = false;
                    updateChatStatus('Desconectado');
                });
                
                chatSocket.on('chat_joined', (data) => {
                    sessionJoined = true;
                    updateChatStatus('En chat');
                });
                
                chatSocket.on('new_message', (data) => {
                    handleNewChatMessage(data);
                });
                
                chatSocket.on('user_typing', (data) => {
                    if (data.user_type === 'patient' && data.user_id !== getCurrentUser().id) {
                        showPatientTyping();
                    }
                });
                
                chatSocket.on('user_stop_typing', (data) => {
                    if (data.user_type === 'patient' && data.user_id !== getCurrentUser().id) {
                        hidePatientTyping();
                    }
                });

                chatSocket.on('file_uploaded', (data) => {
                    handleFileUploaded(data);
                });

                chatSocket.on('session_terminated', (data) => {
                    handleSessionTerminated(data);
                });

                chatSocket.on('patient_disconnected', (data) => {
                    handlePatientDisconnected(data);
                });
                
                chatSocket.on('transfer_notification', (data) => {
                    try {
                        handleWebSocketTransferNotification(data);
                    } catch (error) {
                        console.error('Error procesando notificación de transferencia:', error);
                        // Fallback: mostrar notificación básica
                        showNotification('Nueva transferencia recibida', 'info');
                    }
                });
                
                chatSocket.on('error', (error) => {
                    showNotification('Error en chat: ' + (error.message || error), 'error');
                });
                
            } catch (error) {
                throw error;
            }
        }

        function handleSessionTerminated(data) {
            console.log('🔚 Sesión terminada recibida por agente:', data);
            
            if (data.session_id === currentSession?.id) {
                const terminatedBy = data.terminated_by || 'unknown';
                let notificationMessage = '';
                let systemMessage = '';
                
                if (terminatedBy === 'patient') {
                    if (data.patient_initiated) {
                        notificationMessage = 'El paciente ha finalizado la conversación';
                        systemMessage = 'El paciente finalizó la conversación';
                    } else {
                        notificationMessage = 'El paciente se desconectó';
                        systemMessage = 'El paciente se desconectó del chat';
                    }
                } else if (terminatedBy === 'system') {
                    notificationMessage = 'La conversación fue finalizada por el sistema';
                    systemMessage = 'Sesión finalizada por el sistema';
                } else {
                    notificationMessage = 'La conversación ha sido finalizada';
                    systemMessage = 'Conversación finalizada';
                }
                
                showNotification(notificationMessage, 'info', 6000);
                addSystemMessageToChat(systemMessage);
                
                disableAgentChatControls();
                
                setTimeout(() => {
                    disconnectFromCurrentSession();
                }, 3000);
            }
            
            // Actualizar listas sin importar si es la sesión actual
            updateListsAfterSessionTermination(data.session_id);
        }

        function updateListsAfterSessionTermination(sessionId) {
            console.log('Actualizando listas después de terminación:', sessionId);
            
            // Remover de conversaciones pendientes
            if (pendingConversations && Array.isArray(pendingConversations)) {
                const originalLength = pendingConversations.length;
                pendingConversations = pendingConversations.filter(conv => conv.id !== sessionId);
                
                if (pendingConversations.length < originalLength) {
                    console.log('Sesión removida de pendientes');
                }
            }
            
            // Remover de mis chats
            if (myChats && Array.isArray(myChats)) {
                const originalLength = myChats.length;
                myChats = myChats.filter(chat => chat.id !== sessionId);
                
                if (myChats.length < originalLength) {
                    console.log('Sesión removida de mis chats');
                }
            }
            
            // Remover del sidebar de chats
            if (allChatsForSidebar) {
                if (allChatsForSidebar.pending) {
                    allChatsForSidebar.pending = allChatsForSidebar.pending.filter(conv => conv.id !== sessionId);
                }
                if (allChatsForSidebar.myChats) {
                    allChatsForSidebar.myChats = allChatsForSidebar.myChats.filter(chat => chat.id !== sessionId);
                }
            }
            
            // Detener timer de la conversación
            stopConversationTimer(sessionId);
            
            // Actualizar UI inmediatamente
            const pendingSection = document.getElementById('pending-conversations-section');
            const myChatsSection = document.getElementById('my-chats-section');
            
            if (pendingSection && !pendingSection.classList.contains('hidden')) {
                renderPendingConversationsHTML(pendingConversations || []);
                const container = document.getElementById('pendingConversationsContainer');
                if (container) {
                    container.innerHTML = `<div class="space-y-3">${renderPendingConversationsHTML(pendingConversations || [])}</div>`;
                }
            }
            
            if (myChatsSection && !myChatsSection.classList.contains('hidden')) {
                renderMyChatsHTML(myChats || []);
                const container = document.getElementById('myChatsContainer');
                if (container) {
                    container.innerHTML = renderMyChatsHTML(myChats || []);
                }
            }
            
            // Actualizar sidebar y contadores
            loadChatsSidebar();
            updateSidebarCounts();
            
            console.log('Actualización de listas completada');
        }
        function handlePatientDisconnected(data) {
            console.log('Paciente desconectado:', data);
            
            if (data.session_id === currentSession?.id) {
                const reason = data.reason || 'unknown';
                let message = '';
                
                if (reason === 'page_closed' || reason === 'browser_closed') {
                    message = 'El paciente cerró la ventana del navegador';
                } else if (reason === 'connection_lost') {
                    message = 'El paciente perdió la conexión';
                } else {
                    message = 'El paciente se desconectó del chat';
                }
                
                showNotification(message, 'warning', 5000);
                addSystemMessageToChat('El paciente se desconectó');
                
                // Esperar un momento por si se reconecta
                setTimeout(() => {
                    if (currentSession?.id === data.session_id) {
                        showNotification('El paciente no se ha reconectado. Puedes finalizar la sesión.', 'info', 8000);
                    }
                }, 10000);
            }
            
            // Actualizar listas también en desconexiones
            updateListsAfterSessionTermination(data.session_id);
        }

        function disableAgentChatControls() {
            const chatInput = document.getElementById('agentMessageInput');
            const sendButton = document.getElementById('agentSendButton');
            const transferButton = document.querySelector('button[onclick*="showTransferModal"]');
            const endButton = document.querySelector('button[onclick*="showEndSessionModal"]');
            const fileUploadArea = document.getElementById('fileUploadArea');
            
            if (chatInput) {
                chatInput.disabled = true;
                chatInput.placeholder = 'La conversación ha finalizado';
                chatInput.style.backgroundColor = '#f3f4f6';
            }
            
            if (sendButton) {
                sendButton.disabled = true;
                sendButton.innerHTML = 'Finalizada';
                sendButton.className = sendButton.className.replace(/bg-blue-\d+/, 'bg-gray-400');
            }
            
            // Deshabilitar botones de acción
            [transferButton, endButton].forEach(btn => {
                if (btn) {
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                }
            });
            
            // Ocultar área de subida de archivos
            if (fileUploadArea) {
                fileUploadArea.classList.add('hidden');
            }
            
            // Limpiar archivos seleccionados
            clearSelectedFiles();
            
            console.log('Controles del chat deshabilitados');
        }

        function addSystemMessageToChat(message) {
            const messagesContainer = document.getElementById('patientChatMessages');
            if (!messagesContainer) return;

            const wrapper = document.createElement('div');
            wrapper.className = 'mb-4';
            wrapper.innerHTML = `
                <div class="flex justify-center">
                    <div class="bg-yellow-100 text-yellow-800 rounded-lg px-4 py-2 text-sm">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            ${message}
                        </div>
                    </div>
                </div>
            `;

            messagesContainer.appendChild(wrapper);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        function setupSocketEventHandlers() {
            if (!chatSocket) return;
            
            chatSocket.on('chat_joined', (data) => handleChatJoined(data));
            chatSocket.on('error', (data) => handleError(data));
            chatSocket.on('new_message', (data) => handleNewMessage(data));
            chatSocket.on('message_sent', (data) => handleMessageSent(data));
            chatSocket.on('user_typing', (data) => handleUserTyping(data));
            chatSocket.on('user_stop_typing', (data) => handleUserStopTyping(data));
            chatSocket.on('file_uploaded', (data) => handleFileUploaded(data));
            chatSocket.on('session_terminated', (data) => handleSessionTerminated(data));
            chatSocket.on('terminate_session', (data) => handleSessionTerminated(data));
            chatSocket.on('patient_disconnected', (data) => handlePatientDisconnected(data));
            
            // NUEVO: Manejar terminación específica por paciente
            chatSocket.on('session_ended_by_patient', (data) => handleSessionEndedByPatient(data));
        }

        // Nueva función para manejar terminación específica por paciente
        function handleSessionEndedByPatient(data) {
            console.log('👤 Sesión terminada por paciente:', data);
            
            if (data.session_id === currentSession?.id) {
                // Mostrar notificación específica
                const actionMessage = data.action === 'page_closed' ? 
                    'El paciente cerró la ventana del navegador' : 
                    'El paciente finalizó la conversación voluntariamente';
                    
                showNotification(`👋 ${actionMessage}`, 'info', 6000);
                addSystemMessageToChat(actionMessage);
                
                // Deshabilitar controles inmediatamente
                disableAgentChatControls();
                
                // Desconectar después de un momento
                setTimeout(() => {
                    disconnectFromCurrentSession();
                }, 2000);
            }
            
            // Actualizar listas inmediatamente, sin importar si es la sesión actual
            updateListsAfterSessionTermination(data.session_id);
            
            // Refrescar listas para asegurar sincronización
            setTimeout(() => {
                loadPendingConversations();
                loadMyChats();
                loadChatsSidebar();
                updateSidebarCounts();
            }, 1000);
        }

        /*function setupEnhancedTransferWebSocket() {
            // Esta función se integra con connectToChatWebSocket existente
            if (chatSocket) {
                // Listener mejorado para notificaciones de transferencias
                chatSocket.on('transfer_notification', (data) => {
                    handleWebSocketTransferNotification(data);
                });
                
                // Listener para cuando una sesión es transferida exitosamente
                chatSocket.on('session_transferred', (data) => {
                    if (data.session_id === currentSession?.id) {
                        showNotification('✅ Sesión transferida exitosamente', 'success');
                        disconnectFromCurrentSession();
                    }
                });
                
                // Listener para cuando recibimos una nueva sesión por transferencia
                chatSocket.on('session_received_by_transfer', (data) => {
                    const transferType = data.transfer_type || 'unknown';
                    const patientName = data.patient_name || 'Paciente';
                    const fromAgent = data.from_agent || 'Agente';
                    
                    let message = '';
                    switch (transferType) {
                        case 'internal':
                            message = `🔄 Nueva sesión por transferencia interna\n👤 ${patientName}\n👨‍💼 De: ${fromAgent}`;
                            break;
                        case 'external':
                            message = `📥 Nueva sesión por transferencia externa\n👤 ${patientName}\n🏥 De: ${fromAgent}`;
                            break;
                        default:
                            message = `📨 Nueva sesión transferida\n👤 ${patientName}`;
                    }
                    
                    showEnhancedTransferNotification(message, 'success', 8000, {
                        sessionId: data.session_id,
                        patientName: patientName,
                        transferType: transferType,
                        action: 'take'
                    });
                });
            }
        }*/

        function handleWebSocketTransferNotification(data) {
            let message, type, duration;
            
            switch (data.type) {
                case 'internal_transfer_received':
                    message = `Nueva sesión por transferencia interna\n👤 ${data.patient_name || 'Paciente'}\n👨‍💼 De: ${data.from_agent || 'Agente'}`;
                    type = 'success';
                    duration = 8000;
                    break;
                    
                case 'external_transfer_received':
                    message = `Nueva sesión por transferencia externa\n👤 ${data.patient_name || 'Paciente'}\n🏥 De: ${data.from_room || 'Otra sala'}`;
                    type = 'info';
                    duration = 8000;
                    break;
                    
                case 'external_transfer_rejected':
                    message = `Transferencia externa rechazada\n👤 ${data.patient_name || 'Paciente'}\n📝 ${data.reason || 'Transferencia rechazada por supervisor'}`;
                    type = 'warning';
                    duration = 8000;
                    break;
                    
                case 'transfer_request_approved':
                    message = `Tu solicitud de transferencia fue aprobada\n👤 ${data.patient_name || 'Paciente'}`;
                    type = 'success';
                    duration = 6000;
                    break;
                    
                default:
                    message = `Notificación de transferencia\n${data.message || 'Nueva actualización'}`;
                    type = 'info';
                    duration = 6000;
            }
            
            showEnhancedTransferNotification(message, type, duration, data.session_id ? {
                sessionId: data.session_id,
                patientName: data.patient_name || 'Paciente',
                transferType: data.type,
                action: data.action || 'view'
            } : null);
        }


        function joinChatSession() {
            if (!chatSocket || !currentSession || !currentSession.id || !isConnectedToChat) {
                return;
            }
            
            const currentUser = getCurrentUser();
            
            chatSocket.emit('join_chat', {
                session_id: currentSession.id,
                user_id: currentUser.id,
                user_type: 'agent',
                user_name: currentUser.name
            });
        }
        
        async function sendMessage() {
            const input = document.getElementById('agentMessageInput');
            if (!input) return;

            const message = input.value.trim();
            const hasFiles = selectedFiles.length > 0;
            
            if (!message && !hasFiles) return;

            if (!isConnectedToChat || !sessionJoined) {
                showNotification('No conectado al chat. Intentando reconectar...', 'warning');
                connectToChatWebSocket();
                return;
            }

            const currentUser = getCurrentUser();
            
            input.disabled = true;
            const sendButton = document.getElementById('agentSendButton');
            if (sendButton) sendButton.disabled = true;

            try {
                if (message) {
                    renderMyTextMessage(message);
                    
                    const textPayload = {
                        session_id: currentSession.id,
                        user_id: currentUser.id,
                        user_type: 'agent',
                        user_name: currentUser.name,
                        message_type: 'text',
                        content: message,
                        sender_id: currentUser.id,
                        sender_type: 'agent',
                        sender_name: currentUser.name,
                        local_agent_message: true
                    };

                    chatSocket.emit('send_message', textPayload);
                }

                if (hasFiles) {
                    const uploadedFiles = await uploadFiles();
                    
                    if (uploadedFiles.length > 0) {
                        for (const file of uploadedFiles) {
                            const uniqueFileId = `local_agent_file_${Date.now()}_${Math.random().toString(36).substr(2, 9)}_${file.id}`;
                            
                            if (!file.id) {
                                showNotification(`Error: Archivo ${file.original_name} no tiene ID válido`, 'error');
                                continue;
                            }
                            
                            locallyRenderedFiles.add(uniqueFileId);
                            locallyRenderedFiles.add(file.id);
                            locallyRenderedFiles.add(`agent_${file.id}`);
                            
                            addFileMessageToChat(file.original_name, file, true, uniqueFileId);
                            
                            const filePayload = {
                                session_id: currentSession.id,
                                user_id: currentUser.id,
                                user_type: 'agent',
                                user_name: currentUser.name,
                                message_type: 'file',
                                content: `📎 ${file.original_name}`,
                                file_data: file,
                                sender_id: currentUser.id,
                                sender_type: 'agent',
                                sender_name: currentUser.name,
                                local_agent_file: true,
                                unique_file_id: uniqueFileId,
                                agent_rendered_locally: true
                            };

                            chatSocket.emit('send_message', filePayload);
                        }
                    }
                }
                
                input.value = '';
                
            } catch (error) {
                showNotification('Error enviando: ' + error.message, 'error');
            } finally {
                input.disabled = false;
                if (sendButton) sendButton.disabled = false;
                updateSendButton();
            }
        }

        function handleNewChatMessage(data) {
            const messagesContainer = document.getElementById('patientChatMessages');
            if (!messagesContainer) return;

            const currentUser = getCurrentUser();
            
            const isMyMessage = (
                data.user_id === currentUser.id ||
                data.sender_id === currentUser.id ||
                data.local_agent_message === true ||
                data.local_agent_file === true ||
                data.agent_rendered_locally === true ||
                (data.unique_file_id && locallyRenderedFiles.has(data.unique_file_id))
            );

            if (isMyMessage) {
                return;
            }

            if (data.message_type === 'file' || data.file_data?.id) {
                if (data.file_data) {
                    const fileData = {
                        id: data.file_data.id,
                        original_name: data.file_data.original_name || data.file_data.name,
                        file_size: data.file_data.file_size || data.file_data.size,
                        file_type: data.file_data.file_type || data.file_data.type,
                        download_url: data.file_data.download_url || `${FILE_API}/preview/${data.file_data.id}`
                    };
                    
                    const isFromOtherAgent = (
                        (data.user_type === 'agent' || data.sender_type === 'agent') &&
                        data.user_id !== currentUser.id
                    );
                    
                    addFileMessageToChat(fileData.original_name, fileData, isFromOtherAgent);
                    return;
                }
            }

            const normalizedMessage = {
                user_id: data.user_id || data.sender_id,
                user_type: data.user_type || data.sender_type,
                user_name: data.user_name || data.sender_name || 'Usuario',
                content: data.content || '',
                timestamp: data.timestamp || data.created_at || Date.now(),
                message_type: data.message_type || 'text'
            };

            const messageId = generateUniqueMessageId(normalizedMessage);
            
            const isTransferred = currentSession?.transfer_info?.transferred_to_me;
            const isRecovered = currentSession?.transfer_info?.transfer_rejected;
            
            if ((!isTransferred && !isRecovered) && sentMessages.has(messageId)) {
                return;
            }
            
            sentMessages.add(messageId);

            let timestamp = new Date(normalizedMessage.timestamp);
            if (isNaN(timestamp.getTime())) {
                timestamp = new Date();
            }

            const time = timestamp.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });

            const wrapper = document.createElement('div');
            wrapper.className = 'mb-4';
            wrapper.dataset.messageId = messageId;

            const isFromOtherAgent = (
                normalizedMessage.user_type === 'agent' && 
                normalizedMessage.user_id !== currentUser.id
            );

            if (isFromOtherAgent) {
                wrapper.innerHTML = `
                    <div class="flex justify-end">
                        <div class="max-w-xs lg:max-w-md bg-indigo-600 text-white rounded-lg px-4 py-2">
                            <div class="text-xs opacity-75 mb-1">Otro Agente</div>
                            <p>${escapeHtml(normalizedMessage.content)}</p>
                            <div class="text-xs opacity-75 mt-1 text-right">${time}</div>
                        </div>
                    </div>`;
            } else {
                wrapper.innerHTML = `
                    <div class="flex justify-start">
                        <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
                            <div class="text-xs font-medium text-gray-600 mb-1">Paciente</div>
                            <p>${escapeHtml(normalizedMessage.content)}</p>
                            <div class="text-xs text-gray-500 mt-1">${time}</div>
                        </div>
                    </div>`;
            }

            messagesContainer.appendChild(wrapper);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            updateChatHistoryCache(normalizedMessage, messageId, timestamp);
        }

        window.recentAgentUploads = window.recentAgentUploads || new Set();

        function trackRecentAgentUpload(fileName) {
            window.recentAgentUploads.add(fileName);
            
            setTimeout(() => {
                window.recentAgentUploads.delete(fileName);
            }, 15000);
        }

        /*function clearFileTracking() {
            if (window.recentAgentUploads) {
                window.recentAgentUploads.clear();
            }
            locallyRenderedFiles.clear();
            selectedFiles = [];
        }*/

        function renderMyTextMessage(message) {
            const messagesContainer = document.getElementById('patientChatMessages');
            if (!messagesContainer) return;

            const currentTime = new Date();
            const time = currentTime.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });

            const messageId = generateUniqueMessageId({
                user_id: getCurrentUser().id,
                user_type: 'agent',
                content: message,
                timestamp: currentTime.getTime(),
                message_type: 'text'
            });

            sentMessages.add(messageId);

            const wrapper = document.createElement('div');
            wrapper.className = 'mb-4';
            wrapper.dataset.messageId = messageId;
            
            wrapper.innerHTML = `
                <div class="flex justify-end">
                    <div class="max-w-xs lg:max-w-md bg-blue-600 text-white rounded-lg px-4 py-2">
                        <div class="text-xs opacity-75 mb-1">Yo (Agente)</div>
                        <p>${escapeHtml(message)}</p>
                        <div class="text-xs opacity-75 mt-1 text-right">${time}</div>
                    </div>
                </div>`;

            messagesContainer.appendChild(wrapper);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            updateChatHistoryCache({
                user_id: getCurrentUser().id,
                user_type: 'agent',
                user_name: getCurrentUser().name,
                content: message,
                message_type: 'text'
            }, messageId, currentTime);
        }
                
        function handleFileUploaded(data) {
            try {
                if (data.session_id !== currentSession.id) {
                    return;
                }
                
                const currentUser = getCurrentUser();
                
                const isMyAgentFile = (
                    (data.user_id === currentUser.id && data.user_type === 'agent') ||
                    (data.uploaded_by === currentUser.id && data.uploader_type === 'agent') ||
                    (data.sender_id === currentUser.id && data.sender_type === 'agent') ||
                    (data.uploader_id === currentUser.id && data.user_type === 'agent') ||
                    data.local_agent_file === true ||
                    data.agent_rendered_locally === true ||
                    (data.unique_file_id && locallyRenderedFiles.has(data.unique_file_id)) ||
                    (data.file_id && locallyRenderedFiles.has(data.file_id)) ||
                    (window.recentAgentUploads && window.recentAgentUploads.has(data.file_name))
                );
                
                if (isMyAgentFile) {
                    return;
                }
                
                const fileData = {
                    id: data.file_id,
                    original_name: data.file_name,
                    file_size: data.file_size,
                    file_type: data.file_type,
                    download_url: data.preview_url || data.download_url || `${FILE_API}/preview/${data.file_id}`
                };
                
                const isFromOtherAgent = (
                    (data.user_type === 'agent' || data.uploader_type === 'agent' || data.sender_type === 'agent') &&
                    data.user_id !== currentUser.id &&
                    data.uploaded_by !== currentUser.id &&
                    data.sender_id !== currentUser.id
                );
                
                addFileMessageToChat(data.file_name, fileData, isFromOtherAgent);
                
                const senderName = isFromOtherAgent ? 'Otro Agente' : 'Paciente';
                showNotification(`${senderName} envió: ${data.file_name}`, 'info');
                
            } catch (error) {
                console.error('Error procesando archivo recibido:', error);
            }
        }

        function addFileMessageToChat(fileName, fileData, isAgentMessage = false, providedMessageId = null) {
            const messagesContainer = document.getElementById('patientChatMessages');
            if (!messagesContainer) return;

            const currentUser = getCurrentUser();
            const userId = isAgentMessage ? currentUser.id : 'patient';
            const timestamp = Date.now();
            
            const messageId = providedMessageId || generateUniqueMessageId({
                user_id: userId,
                user_type: isAgentMessage ? 'agent' : 'patient',
                message_type: 'file',
                file_data: fileData,
                timestamp: timestamp
            });

            const currentTime = new Date();
            const time = currentTime.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'mb-4';
            messageDiv.dataset.messageId = messageId;

            let previewUrl = '#';
            let canPreview = false;
            
            if (fileData?.id && fileData.id !== 'undefined') {
                previewUrl = `${FILE_API}/preview/${fileData.id}`;
                canPreview = canPreviewFile(fileName, fileData?.file_type);
            } else if (fileData?.preview_url && fileData.preview_url !== 'undefined') {
                previewUrl = fileData.preview_url;
                canPreview = canPreviewFile(fileName, fileData?.file_type);
            } else if (fileData?.download_url && fileData.download_url !== 'undefined') {
                if (fileData.download_url.includes('/download/')) {
                    previewUrl = fileData.download_url.replace('/download/', '/preview/');
                } else {
                    previewUrl = fileData.download_url;
                }
                canPreview = canPreviewFile(fileName, fileData?.file_type);
            }

            const hasValidPreviewUrl = previewUrl !== '#' && !previewUrl.includes('undefined');
            const showPreviewButton = canPreview && hasValidPreviewUrl;

            const previewButton = showPreviewButton ? `
                <button onclick="openFileInNewTab('${previewUrl}', '${escapeHtml(fileName)}')" 
                        class="inline-flex items-center text-xs bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded mt-2 transition-colors"
                        title="Abrir vista previa de ${escapeHtml(fileName)}">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Ver
                </button>
            ` : `
                <span class="inline-flex items-center text-xs bg-gray-600 text-white px-3 py-1.5 rounded mt-2"
                    title="Vista previa no disponible${!hasValidPreviewUrl ? ' (URL inválida)' : ''}">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    ${!hasValidPreviewUrl ? 'URL inválida' : 'No disponible'}
                </span>
            `;

            if (isAgentMessage) {
                messageDiv.innerHTML = `
                    <div class="flex justify-end">
                        <div class="max-w-xs lg:max-w-md bg-blue-600 text-white rounded-lg px-4 py-2">
                            <div class="text-xs opacity-75 mb-1">Yo (Agente)</div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm truncate">${escapeHtml(fileName)}</p>
                                    ${fileData?.file_size ? `<p class="text-xs opacity-75">${formatFileSize(fileData.file_size)}</p>` : ''}
                                </div>
                            </div>
                            <div class="mt-2">
                                ${previewButton}
                            </div>
                            <div class="text-xs opacity-75 mt-1 text-right">${time}</div>
                        </div>
                    </div>
                `;
            } else {
                messageDiv.innerHTML = `
                    <div class="flex justify-start">
                        <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
                            <div class="text-xs font-medium text-gray-600 mb-1">Paciente</div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4 flex-shrink-0 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm truncate">${escapeHtml(fileName)}</p>
                                    ${fileData?.file_size ? `<p class="text-xs text-gray-500">${formatFileSize(fileData.file_size)}</p>` : ''}
                                </div>
                            </div>
                            <div class="mt-2">
                                ${previewButton}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">${time}</div>
                        </div>
                    </div>
                `;
            }

            messagesContainer.appendChild(messageDiv);
            updateFileChatHistoryCache(fileName, fileData, isAgentMessage, currentTime, messageId);
            
            setTimeout(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 100);
        }

        async function loadChatHistory() {
            if (!currentSession || !currentSession.id) return;
            
            const messagesContainer = document.getElementById('patientChatMessages');
            if (!messagesContainer) return;

            messagesContainer.innerHTML = '';
            
            const isTransferred = currentSession.transfer_info && currentSession.transfer_info.transferred_to_me;
            const isRecovered = currentSession.transfer_info && currentSession.transfer_info.transfer_rejected;
            
            if (isTransferred || isRecovered) {
                clearDuplicationForTransferredChat();
                
                try {
                    const response = await fetch(`${CHAT_API}/messages/${currentSession.id}?limit=100`, {
                        headers: getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        
                        if (result.success && result.data && result.data.messages) {
                            chatHistoryCache.delete(currentSession.id);
                            sentMessages.clear();
                            
                            chatHistoryCache.set(currentSession.id, []);
                            const cache = chatHistoryCache.get(currentSession.id);
                            
                            let renderedCount = 0;
                            result.data.messages.forEach((msg) => {
                                try {
                                    const currentUser = getCurrentUser();
                                    
                                    if (msg.message_type === 'file' || 
                                        msg.message_type === 'file_upload' || 
                                        (msg.content && (msg.content.includes('📎') || msg.content.includes('archivo'))) ||
                                        (msg.file_data && msg.file_data.id)) {
                                        
                                        if (msg.file_data) {
                                            const fileData = {
                                                id: msg.file_data.id,
                                                original_name: msg.file_data.original_name || msg.file_data.name,
                                                file_size: msg.file_data.file_size || msg.file_data.size,
                                                file_type: msg.file_data.file_type || msg.file_data.type,
                                                download_url: msg.file_data.download_url || `${FILE_API}/download/${msg.file_data.id}`
                                            };
                                            
                                            const isAgentMessage = (msg.sender_type || msg.user_type) === 'agent';
                                            
                                            const fileMessageId = generateUniqueMessageId({
                                                user_id: msg.sender_id || msg.user_id,
                                                user_type: msg.sender_type || msg.user_type,
                                                message_type: 'file',
                                                file_data: fileData,
                                                timestamp: msg.timestamp || msg.created_at
                                            });
                                            
                                            cache.push({
                                                type: 'file',
                                                fileName: fileData.original_name,
                                                fileData: fileData,
                                                isMine: isAgentMessage,
                                                timestamp: normalizeTimestamp(msg.timestamp || msg.created_at),
                                                messageId: fileMessageId
                                            });
                                            
                                            addFileMessageToChatFromHistory(fileData.original_name, fileData, isAgentMessage, msg.timestamp || msg.created_at);
                                            renderedCount++;
                                        }
                                    } else {
                                        const textMessageId = generateUniqueMessageId({
                                            user_id: msg.sender_id || msg.user_id,
                                            user_type: msg.sender_type || msg.user_type,
                                            content: msg.content,
                                            timestamp: msg.timestamp || msg.created_at,
                                            message_type: msg.message_type || 'text'
                                        });
                                        
                                        const historyItem = {
                                            type: 'text',
                                            content: msg.content,
                                            user_type: msg.sender_type || msg.user_type,
                                            user_id: msg.sender_id || msg.user_id,
                                            user_name: msg.sender_name || msg.user_name || 'Usuario',
                                            timestamp: normalizeTimestamp(msg.timestamp || msg.created_at),
                                            message_type: msg.message_type || 'text',
                                            messageId: textMessageId
                                        };
                                        
                                        cache.push(historyItem);
                                        renderTextMessageFromHistory(historyItem);
                                        renderedCount++;
                                    }
                                } catch (error) {
                                    console.error('Error procesando mensaje del servidor:', error, msg);
                                }
                            });
                            
                            setTimeout(() => {
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            }, 100);
                        }
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                } catch (error) {
                    showNotification('Error cargando historial de chat', 'warning');
                }
                return;
            }
            
            if (chatHistoryCache.has(currentSession.id)) {
                const cachedHistory = chatHistoryCache.get(currentSession.id);
                
                sentMessages.clear();
                
                let renderedCount = 0;
                let errorCount = 0;
                
                cachedHistory.forEach((item, index) => {
                    try {
                        if (item.type === 'file') {
                            if (item.messageId) {
                                sentMessages.add(item.messageId);
                            }
                            addFileMessageToChatFromHistory(item.fileName, item.fileData, item.isMine, item.timestamp);
                            renderedCount++;
                        } else {
                            if (item.messageId) {
                                sentMessages.add(item.messageId);
                            }
                            renderTextMessageFromHistory(item);
                            renderedCount++;
                        }
                    } catch (error) {
                        errorCount++;
                        console.error(`Error renderizando mensaje ${index + 1}:`, error, item);
                    }
                });
                
                setTimeout(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }, 100);
                
                return;
            }
            
            try {
                const response = await fetch(`${CHAT_API}/messages/${currentSession.id}?limit=50`, {
                    headers: getAuthHeaders()
                });
                
                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.messages) {
                        sentMessages.clear();
                        
                        chatHistoryCache.set(currentSession.id, []);
                        const cache = chatHistoryCache.get(currentSession.id);
                        
                        let renderedCount = 0;
                        result.data.messages.forEach((msg) => {
                            try {
                                const currentUser = getCurrentUser();
                                
                                if (msg.message_type === 'file' || 
                                    msg.message_type === 'file_upload' || 
                                    (msg.content && (msg.content.includes('📎') || msg.content.includes('archivo'))) ||
                                    (msg.file_data && msg.file_data.id)) {
                                    
                                    if (msg.file_data) {
                                        const fileData = {
                                            id: msg.file_data.id,
                                            original_name: msg.file_data.original_name || msg.file_data.name,
                                            file_size: msg.file_data.file_size || msg.file_data.size,
                                            file_type: msg.file_data.file_type || msg.file_data.type,
                                            download_url: msg.file_data.download_url || `${FILE_API}/download/${msg.file_data.id}`
                                        };
                                        
                                        const isAgentMessage = (msg.sender_type || msg.user_type) === 'agent';
                                        
                                        const fileMessageId = generateUniqueMessageId({
                                            user_id: msg.sender_id || msg.user_id,
                                            user_type: msg.sender_type || msg.user_type,
                                            message_type: 'file',
                                            file_data: fileData,
                                            timestamp: msg.timestamp || msg.created_at
                                        });
                                        
                                        cache.push({
                                            type: 'file',
                                            fileName: fileData.original_name,
                                            fileData: fileData,
                                            isMine: isAgentMessage,
                                            timestamp: normalizeTimestamp(msg.timestamp || msg.created_at),
                                            messageId: fileMessageId
                                        });
                                        
                                        sentMessages.add(fileMessageId);
                                        addFileMessageToChatFromHistory(fileData.original_name, fileData, isAgentMessage, msg.timestamp || msg.created_at);
                                        renderedCount++;
                                    }
                                } else {
                                    const textMessageId = generateUniqueMessageId({
                                        user_id: msg.sender_id || msg.user_id,
                                        user_type: msg.sender_type || msg.user_type,
                                        content: msg.content,
                                        timestamp: msg.timestamp || msg.created_at,
                                        message_type: msg.message_type || 'text'
                                    });
                                    
                                    const historyItem = {
                                        type: 'text',
                                        content: msg.content,
                                        user_type: msg.sender_type || msg.user_type,
                                        user_id: msg.sender_id || msg.user_id,
                                        user_name: msg.sender_name || msg.user_name || 'Usuario',
                                        timestamp: normalizeTimestamp(msg.timestamp || msg.created_at),
                                        message_type: msg.message_type || 'text',
                                        messageId: textMessageId
                                    };
                                    
                                    cache.push(historyItem);
                                    
                                    sentMessages.add(textMessageId);
                                    renderTextMessageFromHistory(historyItem);
                                    renderedCount++;
                                }
                            } catch (error) {
                                console.error('Error procesando mensaje del servidor:', error, msg);
                            }
                        });
                        
                        setTimeout(() => {
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        }, 100);
                    }
                } else {
                    throw new Error(`Error HTTP ${response.status}`);
                }
            } catch (error) {
                showNotification('Error cargando historial de chat', 'warning');
            }
        }

        function renderTextMessageFromHistory(item) {
            const messagesContainer = document.getElementById('patientChatMessages');
            if (!messagesContainer) return;

            const currentUser = getCurrentUser();
            const isAgentMessage = item.user_type === 'agent';

            let timestamp = item.timestamp;
            if (typeof timestamp === 'string') {
                timestamp = new Date(timestamp);
            } else if (typeof timestamp === 'number') {
                timestamp = new Date(timestamp);
            } else {
                timestamp = new Date();
            }

            if (isNaN(timestamp.getTime())) {
                timestamp = new Date();
            }

            const time = timestamp.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });

            const wrapper = document.createElement('div');
            wrapper.className = 'mb-4';
            wrapper.dataset.messageId = item.messageId;

            if (isAgentMessage) {
                wrapper.innerHTML = `
                    <div class="flex justify-end">
                        <div class="max-w-xs lg:max-w-md bg-blue-600 text-white rounded-lg px-4 py-2">
                            <div class="text-xs opacity-75 mb-1">Agente</div>
                            <p>${escapeHtml(item.content)}</p>
                            <div class="text-xs opacity-75 mt-1 text-right">${time}</div>
                        </div>
                    </div>`;
            } else {
                wrapper.innerHTML = `
                    <div class="flex justify-start">
                        <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
                            <div class="text-xs font-medium text-gray-600 mb-1">Paciente</div>
                            <p>${escapeHtml(item.content)}</p>
                            <div class="text-xs text-gray-500 mt-1">${time}</div>
                        </div>
                    </div>`;
            }

            messagesContainer.appendChild(wrapper);
        }

        function addFileMessageToChatFromHistory(fileName, fileData, isAgentMessage, timestamp) {
            const messagesContainer = document.getElementById('patientChatMessages');
            if (!messagesContainer) return;

            let parsedTimestamp = timestamp;
            if (typeof timestamp === 'string') {
                parsedTimestamp = new Date(timestamp);
            } else if (typeof timestamp === 'number') {
                parsedTimestamp = new Date(timestamp);
            } else {
                parsedTimestamp = new Date();
            }

            if (isNaN(parsedTimestamp.getTime())) {
                parsedTimestamp = new Date();
            }

            const time = parsedTimestamp.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'mb-4';

            let previewUrl = '#';
            if (fileData && fileData.id) {
                previewUrl = `${FILE_API}/preview/${fileData.id}`;
            } else if (fileData && fileData.download_url) {
                previewUrl = fileData.download_url.replace('/download/', '/preview/');
            }

            const fileType = fileData?.file_type || '';
            const canPreview = fileType.startsWith('image/') || 
                              fileType === 'application/pdf' || 
                              fileType.startsWith('text/') ||
                              fileName.toLowerCase().endsWith('.pdf') ||
                              fileName.toLowerCase().match(/\.(jpg|jpeg|png|gif|bmp|webp|txt|csv|json|xml|log)$/);

            const previewButton = canPreview ? `
                <button onclick="openFileInNewTab('${previewUrl}', '${fileName}')" 
                        class="inline-flex items-center text-xs ${isAgentMessage ? 'bg-blue-500 hover:bg-blue-400' : 'bg-blue-600 hover:bg-blue-500'} text-white px-3 py-1.5 rounded mt-2 transition-colors">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Ver
                </button>
            ` : `
                <span class="inline-flex items-center text-xs ${isAgentMessage ? 'bg-gray-500' : 'bg-gray-600'} text-white px-3 py-1.5 rounded mt-2">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    No disponible
                </span>
            `;

            if (isAgentMessage) {
                messageDiv.innerHTML = `
                    <div class="flex justify-end">
                        <div class="max-w-xs lg:max-w-md bg-blue-600 text-white rounded-lg px-4 py-2">
                            <div class="text-xs opacity-75 mb-1">Agente</div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm truncate">${fileName}</p>
                                    ${fileData && fileData.file_size ? 
                                        `<p class="text-xs opacity-75">${formatFileSize(fileData.file_size)}</p>` : 
                                        ''
                                    }
                                </div>
                            </div>
                            <div class="mt-2">
                                ${previewButton}
                            </div>
                            <div class="text-xs opacity-75 mt-1 text-right">${time}</div>
                        </div>
                    </div>
                `;
            } else {
                messageDiv.innerHTML = `
                    <div class="flex justify-start">
                        <div class="max-w-xs lg:max-w-md bg-gray-200 text-gray-900 rounded-lg px-4 py-2">
                            <div class="text-xs font-medium text-gray-600 mb-1">Paciente</div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4 flex-shrink-0 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm truncate">${fileName}</p>
                                    ${fileData && fileData.file_size ? 
                                        `<p class="text-xs text-gray-500">${formatFileSize(fileData.file_size)}</p>` : 
                                        ''
                                    }
                                </div>
                            </div>
                            <div class="mt-2">
                                ${previewButton}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">${time}</div>
                        </div>
                    </div>
                `;
            }

            messagesContainer.appendChild(messageDiv);
        }

        function updateChatStatus(status) {
            const statusElement = document.getElementById('chatStatus');
            if (statusElement) {
                statusElement.textContent = status;
                
                statusElement.className = 'text-sm font-medium ';
                if (status === 'En chat') {
                    statusElement.className += 'text-green-600';
                } else if (status === 'Conectado') {
                    statusElement.className += 'text-blue-600';
                } else {
                    statusElement.className += 'text-gray-500';
                }
            }
        }

        function updateChatHistoryCache(normalizedMessage, messageId, timestamp) {
            if (currentSession && currentSession.id) {
                if (!chatHistoryCache.has(currentSession.id)) {
                    chatHistoryCache.set(currentSession.id, []);
                }
                
                const cache = chatHistoryCache.get(currentSession.id);
                const existsInCache = cache.some(item => item.messageId === messageId);
                
                if (!existsInCache) {
                    cache.push({
                        type: 'text',
                        content: normalizedMessage.content,
                        user_type: normalizedMessage.user_type,
                        user_id: normalizedMessage.user_id,
                        user_name: normalizedMessage.user_name,
                        timestamp: timestamp.toISOString(),
                        message_type: normalizedMessage.message_type,
                        messageId: messageId
                    });
                }
            }
        }

        function updateFileChatHistoryCache(fileName, fileData, isAgentMessage, timestamp, messageId) {
            if (currentSession?.id) {
                if (!chatHistoryCache.has(currentSession.id)) {
                    chatHistoryCache.set(currentSession.id, []);
                }
                
                const cache = chatHistoryCache.get(currentSession.id);
                const existsInCache = cache.some(item => item.messageId === messageId);
                
                if (!existsInCache) {
                    cache.push({
                        type: 'file',
                        fileName: fileName,
                        fileData: fileData,
                        isMine: isAgentMessage,
                        timestamp: timestamp.toISOString(),
                        messageId: messageId
                    });
                }
            }
        }

        function canPreviewFile(fileName, fileType) {
            if (!fileName) return false;
            
            const fileName_lower = fileName.toLowerCase();
            
            if (fileName_lower.match(/\.(pdf|jpg|jpeg|png|gif|bmp|webp|txt|csv|json|xml|log|html|md)$/)) {
                return true;
            }
            
            if (fileType) {
                const previewableTypes = [
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
                    'application/pdf',
                    'text/plain', 'text/csv', 'text/html', 'text/markdown',
                    'application/json', 'application/xml'
                ];
                
                return previewableTypes.includes(fileType.toLowerCase());
            }
            
            return false;
        }

        function showPatientTyping() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) {
                indicator.classList.remove('hidden');
                indicator.innerHTML = `
                    <div class="flex items-center space-x-2 text-gray-500 text-sm">
                        <div class="flex space-x-1">
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s;"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
                        </div>
                        <span>El paciente está escribiendo...</span>
                    </div>
                `;
            }
        }

        function hidePatientTyping() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) {
                indicator.classList.add('hidden');
            }
        }

        async function showTransferModal() {
            document.getElementById('transferModal').classList.remove('hidden');
            await loadAvailableAgentsForTransfer();
            await loadAvailableRooms();
        }

        function showEndSessionModal() {
            document.getElementById('endSessionModal').classList.remove('hidden');
        }

        function showReturnModal() {
            document.getElementById('returnModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function showEscalationModal() {
            if (!currentSession) {
                showNotification('No hay sesión activa para escalar', 'error');
                return;
            }
            document.getElementById('escalationModal').classList.remove('hidden');
        }

        async function executeEscalation() {
            const reason = document.getElementById('escalationReason').value;
            const description = document.getElementById('escalationDescription').value.trim();
            const priority = document.getElementById('escalationPriority').value;
            
            if (!description) {
                alert('Por favor describe el motivo de la escalación');
                return;
            }
            
            try {
                await escalateToSupervisor(reason, description, priority);
                closeModal('escalationModal');
                hidePatientInfoButton();
                showEmptyChat();
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        async function escalateToSupervisor(reason, description, priority) {
            if (!currentSession || !currentSession.id) {
                throw new Error('No hay sesión activa');
            }

            const currentUser = getCurrentUser();

            const response = await fetch(`${CHAT_API}/escalations/manual`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify({
                    session_id: currentSession.id,
                    escalated_by_agent: currentUser.id,
                    escalation_type: 'manual',
                    reason: reason,
                    description: description,
                    priority: priority,
                    patient_data: {
                        name: getPatientNameFromSession(currentSession),
                        room: getRoomNameFromSession(currentSession)
                    },
                    agent_data: {
                        id: currentUser.id,
                        name: currentUser.name,
                        email: currentUser.email
                    }
                })
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }

            const result = await response.json();
            if (result.success) {
                showNotification('Escalación enviada exitosamente - Un supervisor se hará cargo pronto', 'success');
                disconnectFromCurrentSession();
                return result;
            } else {
                throw new Error(result.message || 'Error en escalación');
            }
        }

        function toggleTransferFields() {
            const transferType = document.getElementById('transferType').value;
            const internalFields = document.getElementById('internalTransferFields');
            const externalFields = document.getElementById('externalTransferFields');
            
            if (transferType === 'internal') {
                internalFields.classList.remove('hidden');
                externalFields.classList.add('hidden');
                
                const contextArea = document.getElementById('transferContextInfo');
                if (contextArea) {
                    contextArea.remove();
                }
                
                const agentSelect = document.getElementById('targetAgentSelect');
                if (agentSelect.children.length <= 1) {
                    loadAvailableAgentsForTransfer();
                }
            } else {
                internalFields.classList.add('hidden');
                externalFields.classList.remove('hidden');
                
                const contextArea = document.getElementById('transferContextInfo');
                if (contextArea) {
                    contextArea.remove();
                }
                
                // AGREGAR ESTAS LÍNEAS:
                const roomSelect = document.getElementById('targetRoom');
                if (roomSelect.children.length <= 1) {
                    loadAvailableRooms();
                }
            }
        }

        async function loadAvailableAgentsForTransfer() {
            const agentSelect = document.getElementById('targetAgentSelect');
            
            if (!currentSession || !currentSession.id) {
                agentSelect.innerHTML = '<option value="">Selecciona una sesión primero</option>';
                agentSelect.disabled = true;
                return;
            }
            
            try {
                agentSelect.innerHTML = '<option value="">Cargando agentes...</option>';
                agentSelect.disabled = true;
                
                const response = await fetch(`${CHAT_API}/chats/available-agents/transfer?session_id=${currentSession.id}&exclude_agent_id=${getCurrentUser().id}`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success && result.data) {
                        populateAgentSelect(result.data.agents, result.data.session);
                        agentSelect.disabled = false;
                        return;
                    } else {
                        throw new Error(result.message || 'Respuesta inválida');
                    }
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `Error HTTP ${response.status}`);
                }
                
            } catch (error) {
                agentSelect.innerHTML = '<option value="">Error cargando agentes</option>';
                agentSelect.disabled = false;
                showNotification('Error cargando agentes: ' + error.message, 'error');
            }
        }

        async function loadAvailableRooms() {
            const roomSelect = document.getElementById('targetRoom');
            
            try {

                console.log('🔍 Iniciando loadAvailableRooms');
                console.log('🔗 URL:', `${AUTH_API}/rooms/available`);
                
                const headers = getAuthHeaders();
                console.log('📋 Headers:', headers);
                roomSelect.innerHTML = '<option value="">Cargando salas...</option>';
                roomSelect.disabled = true;
                
                const response = await fetch(`${AUTH_API}/rooms/available`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                console.log('📡 Response status:', response.status);
                console.log('📡 Response headers:', response.headers);

                if (response.ok) {
                    const result = await response.json();
                    console.log('✅ Response data:', result);
                    
                    if (result.success && result.data && result.data.rooms) {
                        populateRoomSelect(result.data.rooms);
                        roomSelect.disabled = false;
                        return;
                    } else {
                        throw new Error(result.message || 'Respuesta inválida');
                    }
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `Error HTTP ${response.status}`);
                }
                
            } catch (error) {
                console.error('💥 Error completo:', error);
                roomSelect.innerHTML = '<option value="">Error cargando salas</option>';
                roomSelect.disabled = false;
                showNotification('Error cargando salas: ' + error.message, 'error');
            }
        }

        function populateRoomSelect(rooms) {
            const roomSelect = document.getElementById('targetRoom');
            
            roomSelect.innerHTML = '';
            
            if (!rooms || rooms.length === 0) {
                roomSelect.innerHTML = '<option value="">No hay salas disponibles</option>';
                showNotification('No se encontraron salas disponibles', 'warning');
                return;
            }
            
            roomSelect.innerHTML = '<option value="">Selecciona una sala...</option>';
            
            rooms.forEach(room => {
                const option = document.createElement('option');
                option.value = room.id;
                option.textContent = `${room.name}${room.description ? ' - ' + room.description : ''}`;
                
                // Agregar información adicional si está disponible
                if (!room.available) {
                    option.disabled = true;
                    option.textContent += ' (No disponible)';
                }
                
                roomSelect.appendChild(option);
            });
            
            showNotification(`${rooms.length} sala(s) cargada(s)`, 'success', 2000);
        }

        function populateAgentSelect(agents, sessionInfo) {
            const agentSelect = document.getElementById('targetAgentSelect');
            
            agentSelect.innerHTML = '';
            
            if (!agents || agents.length === 0) {
                agentSelect.innerHTML = '<option value="">No hay agentes disponibles en esta sala</option>';
                showNotification(`No hay otros agentes en la sala "${sessionInfo.room_name}"`, 'warning');
                return;
            }
            
            agentSelect.innerHTML = `<option value="">Selecciona un agente...</option>`;
            
            const availableAgents = agents.filter(a => a.status === 'available');
            if (availableAgents.length > 0) {
                availableAgents.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.id;
                    
                    const sessionInfo = `(${agent.current_sessions}/${agent.max_concurrent_chats} sesiones)`;
                    const primaryBadge = agent.is_primary_agent ? ' - Primario' : '';
                    
                    option.textContent = `${agent.name}${primaryBadge} ${sessionInfo}`;
                    agentSelect.appendChild(option);
                });
            }
            
            const busyAgents = agents.filter(a => a.status === 'at_capacity');
            if (busyAgents.length > 0) {
                busyAgents.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.id;
                    option.disabled = true;
                    option.textContent = `${agent.name} - Sin capacidad (${agent.current_sessions}/${agent.max_concurrent_chats})`;
                    agentSelect.appendChild(option);
                });
            }
            
            const availableCount = availableAgents.length;
            if (availableCount > 0) {
                showNotification(`${availableCount} agente(s) disponible(s) en "${sessionInfo.room_name}"`, 'success', 3000);
            } else {
                showNotification(`No hay agentes disponibles en "${sessionInfo.room_name}" en este momento`, 'warning', 4000);
            }
        }

        function disconnectFromCurrentSession() {
            if (chatSocket) {
                chatSocket.disconnect();
                isConnectedToChat = false;
                sessionJoined = false;
            }
            stopSessionTimer();
            
            if (currentSession && currentSession.id && sentMessages.size > 0) {
                sessionMessageIds.set(currentSession.id, new Set(sentMessages));
            }
            
            clearMessageDuplicationControl();
            clearSelectedFiles();
            currentSession = null;
            
            showPendingSection();
        }

        async function executeEndSession() {
            const reason = document.getElementById('endReason').value;
            const notes = document.getElementById('endNotes').value.trim();
            
            try {
                await endSession(reason, notes);
                closeModal('endSessionModal');
                hidePatientInfoButton();
                showEmptyChat();
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        async function executeTransfer() {
            const transferType = document.getElementById('transferType').value;
            const reason = document.getElementById('transferReason').value.trim();
            
            if (!reason) {
                alert('Ingresa el motivo');
                return;
            }
            
            try {
                if (transferType === 'internal') {
                    const targetAgentId = document.getElementById('targetAgentSelect').value;
                    if (!targetAgentId) {
                        alert('Selecciona un agente');
                        return;
                    }
                    await transferInternal(targetAgentId, reason);
                } else {
                    const targetRoom = document.getElementById('targetRoom').value;
                    await requestExternalTransfer(targetRoom, reason);
                }
                
                closeModal('transferModal');
                hidePatientInfoButton();
                showEmptyChat();
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        async function executeReturn() {
            const reason = document.getElementById('returnReason').value;
            
            try {
                await returnToQueue(reason);
                closeModal('returnModal');
                hidePatientInfoButton();
                showEmptyChat();
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        async function endSession(reason, notes) {
            if (!currentSession || !currentSession.id) {
                throw new Error('No hay sesión activa');
            }

            console.log('Agente finalizando sesión:', { sessionId: currentSession.id, reason, notes });

            // Notificar al paciente ANTES de finalizar
            if (chatSocket && chatSocket.connected) {
                console.log('Enviando notificación de terminación al paciente...');
                chatSocket.emit('terminate_session', {
                    session_id: currentSession.id,
                    terminated_by: 'agent',
                    reason: reason,
                    notes: notes,
                    message: 'El agente ha finalizado la conversación',
                    agent_initiated: true,
                    timestamp: new Date().toISOString()
                });
                
                await new Promise(resolve => setTimeout(resolve, 500));
            }

            const response = await fetch(`${CHAT_API}/chats/sessions/${currentSession.id}/end`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify({
                    reason: reason,
                    notes: notes,
                    terminated_by: 'agent'
                })
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }

            const result = await response.json();
            if (result.success) {
                showNotification('Sesión finalizada exitosamente', 'success');
                updateListsAfterSessionTermination(currentSession.id);
                disconnectFromCurrentSession();
            }
            return result;
        }
        async function returnToQueue(reason) {
            if (!currentSession || !currentSession.id) {
                throw new Error('No hay sesión activa');
            }

            console.log('Agente devolviendo a cola:', { sessionId: currentSession.id, reason });

            // Notificar al paciente
            if (chatSocket && chatSocket.connected) {
                console.log('Notificando devolución a cola...');
                chatSocket.emit('terminate_session', {
                    session_id: currentSession.id,
                    terminated_by: 'agent',
                    reason: reason,
                    message: 'La conversación ha sido devuelta a la cola de espera'
                });
                
                await new Promise(resolve => setTimeout(resolve, 500));
            }

            try {
                const response = await fetch(`${CHAT_API}/chats/sessions/${currentSession.id}/return`, {
                    method: 'PUT',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({
                        reason: reason,
                        terminated_by: 'agent'
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `Error HTTP ${response.status}`);
                }

                const result = await response.json();
                if (result.success) {
                    showNotification('Sesión devuelta a cola exitosamente', 'success');
                    
                    // Actualizar listas
                    updateListsAfterSessionTermination(currentSession.id);
                    
                    disconnectFromCurrentSession();
                }
                return result;

            } catch (error) {
                throw error;
            }
        }

        async function requestExternalTransfer(targetRoom, reason) {
            if (!currentSession || !currentSession.id) {
                throw new Error('No hay sesión activa');
            }

            const response = await fetch(`${CHAT_API}/transfers/request`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify({
                    session_id: currentSession.id,
                    from_agent_id: getCurrentUser().id,
                    to_room: targetRoom,
                    reason: reason,
                    priority: 'medium'
                })
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }

            const result = await response.json();
            if (result.success) {
                showNotification('Solicitud de transferencia enviada', 'success');
                disconnectFromCurrentSession();
            }
            return result;
        }

        async function transferInternal(targetAgentId, reason) {
            if (!currentSession || !currentSession.id) {
                throw new Error('No hay sesión activa');
            }

            const response = await fetch(`${CHAT_API}/transfers/internal`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify({
                    session_id: currentSession.id,
                    from_agent_id: getCurrentUser().id,
                    to_agent_id: targetAgentId,
                    reason: reason
                })
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }

            const result = await response.json();
            if (result.success) {
                showNotification('Transferencia interna exitosa', 'success');
                disconnectFromCurrentSession();
            }
            return result;
        }

        function showEmptyChat() {
            currentSession = null;
            
            if (chatSocket) {
                chatSocket.disconnect();
                isConnectedToChat = false;
                sessionJoined = false;
            }
            
            stopSessionTimer();
            clearMessageDuplicationControl();
            clearSelectedFiles();
            
            hideAllSections();
            document.getElementById('patient-chat-panel').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = 'Panel de Chat';
            
            document.getElementById('chatPatientName').textContent = 'Selecciona una conversación';
            document.getElementById('chatPatientInitials').textContent = '?';
            document.getElementById('chatPatientId').textContent = '';
            document.getElementById('chatRoomName').textContent = '';
            document.getElementById('chatSessionStatus').textContent = '';
            document.getElementById('chatTimer').textContent = '';
            
            const messagesContainer = document.getElementById('patientChatMessages');
            if (messagesContainer) {
                messagesContainer.innerHTML = `
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Listo para nueva conversación</h3>
                            <p class="text-gray-500 mb-6">Selecciona una conversación pendiente o toma un nuevo chat desde el sidebar</p>
                            <div class="space-y-2">
                                <button onclick="showPendingSection()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                                    Ver Conversaciones Pendientes
                                </button>
                                <br>
                                <button onclick="loadChatsSidebar()" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 font-medium">
                                    Actualizar Lista de Chats
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            const chatInput = document.getElementById('agentMessageInput');
            const chatButton = document.getElementById('agentSendButton');
            if (chatInput) {
                chatInput.disabled = true;
                chatInput.placeholder = 'Selecciona una conversación para comenzar...';
                chatInput.value = '';
            }
            if (chatButton) {
                chatButton.disabled = true;
            }
            
            const typingIndicator = document.getElementById('typingIndicator');
            if (typingIndicator) typingIndicator.classList.add('hidden');
            
            const fileUploadArea = document.getElementById('fileUploadArea');
            const filePreviewArea = document.getElementById('filePreviewArea');
            const uploadProgressArea = document.getElementById('uploadProgressArea');
            if (fileUploadArea) fileUploadArea.classList.add('hidden');
            if (filePreviewArea) filePreviewArea.classList.add('hidden');
            if (uploadProgressArea) uploadProgressArea.classList.add('hidden');
            
            updateChatStatus('Sin conexión');
            updatePatientInfoSidebar({}, 'Selecciona una conversación');
            hidePatientInfoButton();
            setTimeout(() => {
                loadChatsSidebar();
            }, 1000);
        }

        function handleAgentKeyDown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                
                if (agentIsTyping && chatSocket && currentSession) {
                    chatSocket.emit('stop_typing', {
                        session_id: currentSession.id,
                        sender_type: 'agent',
                        user_type: 'agent'
                    });
                    agentIsTyping = false;
                }
                clearTimeout(agentTypingTimer);
                
                const input = document.getElementById('agentMessageInput');
                if (input && (input.value.trim() || selectedFiles.length > 0)) {
                    sendMessage();
                }
            }
            updateSendButton();
        }

        function updateSendButton() {
            const input = document.getElementById('agentMessageInput');
            const button = document.getElementById('agentSendButton');
            
            if (input && button) {
                const hasText = input.value.trim().length > 0;
                const hasFiles = selectedFiles && selectedFiles.length > 0;
                const isConnected = currentSession && isConnectedToChat;
                
                const shouldEnable = (hasText || hasFiles) && isConnected;
                button.disabled = !shouldEnable;
                
                if (hasFiles && !hasText) {
                    button.innerHTML = `
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                        </svg>`;
                    button.title = `Enviar ${hasFiles} archivo(s)`;
                    button.className = button.className.replace(/bg-\w+-\d+/g, '') + ' bg-green-600 hover:bg-green-700';
                } else if (hasText && hasFiles) {
                    button.innerHTML = `
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>`;
                    button.title = `Enviar mensaje y ${hasFiles} archivo(s)`;
                    button.className = button.className.replace(/bg-\w+-\d+/g, '') + ' bg-blue-600 hover:bg-blue-700';
                } else {
                    button.innerHTML = `
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>`;
                    button.title = 'Enviar mensaje';
                    button.className = button.className.replace(/bg-\w+-\d+/g, '') + ' bg-blue-600 hover:bg-blue-700';
                }
            }
        }

        function setupAgentTyping() {
            const agentInput = document.getElementById('agentMessageInput');
            if (!agentInput) return;
            
            agentInput.addEventListener('input', () => {
                if (!isConnectedToChat || !chatSocket || !currentSession) {
                    updateSendButton();
                    return;
                }
                
                if (!agentIsTyping) {
                    chatSocket.emit('start_typing', {
                        session_id: currentSession.id,
                        sender_type: 'agent',
                        user_type: 'agent'
                    });
                    agentIsTyping = true;
                }
                
                clearTimeout(agentTypingTimer);
                agentTypingTimer = setTimeout(() => {
                    if (chatSocket && currentSession) {
                        chatSocket.emit('stop_typing', {
                            session_id: currentSession.id,
                            sender_type: 'agent',
                            user_type: 'agent'
                        });
                    }
                    agentIsTyping = false;
                }, 1000);
                
                updateSendButton();
            });
            
            agentInput.addEventListener('blur', () => {
                if (agentIsTyping && chatSocket && currentSession) {
                    chatSocket.emit('stop_typing', {
                        session_id: currentSession.id,
                        sender_type: 'agent',
                        user_type: 'agent'
                    });
                    agentIsTyping = false;
                }
                clearTimeout(agentTypingTimer);
            });
        }

        function setupFileUploadEvents() {
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                fileInput.addEventListener('change', (e) => {
                    const files = Array.from(e.target.files);
                    handleFileSelection(files);
                    e.target.value = '';
                });
            }
            
            updateSendButton();
        }

        function updateTime() {
            document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('es-ES');
        }

        function logout() {
            if (confirm('¿Cerrar sesión?')) {
                if (chatSocket) {
                    chatSocket.disconnect();
                }
                clearSelectedFiles();
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = 'logout.php';
            }
        }

        function startAutoRefresh() {
            setInterval(() => {
                const pendingSection = document.getElementById('pending-conversations-section');
                const myChatsSection = document.getElementById('my-chats-section');
                const roomsSection = document.getElementById('rooms-list-section');
                const sessionsSection = document.getElementById('room-sessions-section');
                const chatPanel = document.getElementById('patient-chat-panel');
                
                if (pendingSection && !pendingSection.classList.contains('hidden')) {
                    loadPendingConversations();
                }
                
                if (myChatsSection && !myChatsSection.classList.contains('hidden')) {
                    loadMyChats();
                }
                
                if (roomsSection && !roomsSection.classList.contains('hidden')) {
                    loadRoomsFromAuthService();
                }
                
                if (sessionsSection && !sessionsSection.classList.contains('hidden') && currentRoom) {
                    loadSessionsByRoom(currentRoom);
                }
                
                if (chatPanel && !chatPanel.classList.contains('hidden')) {
                    loadChatsSidebar();
                }
            }, 30000);
        }

        function openPatientInfoSidebar() {
            const sidebar = document.getElementById('patientInfoSidebar');
            const backdrop = document.getElementById('patientInfoBackdrop');
            
            if (sidebar && backdrop) {
                sidebar.classList.add('active');
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closePatientInfoSidebar() {
            const sidebar = document.getElementById('patientInfoSidebar');
            const backdrop = document.getElementById('patientInfoBackdrop');
            
            if (sidebar && backdrop) {
                sidebar.classList.remove('active');
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        function showPatientInfoButton() {
            const button = document.getElementById('patientInfoButton');
            if (button) {
                button.classList.remove('hidden');
            }
        }

        function hidePatientInfoButton() {
            const button = document.getElementById('patientInfoButton');
            if (button) {
                button.classList.add('hidden');
            }
            closePatientInfoSidebar();
        }

        function togglePatientInfoSidebar() {
            const sidebar = document.getElementById('patientInfoSidebar');
            const backdrop = document.getElementById('patientInfoBackdrop');
            const floatingBtn = document.getElementById('patientInfoFloatingBtn');
            
            if (sidebar && backdrop) {
                const isOpen = sidebar.classList.contains('active');
                
                if (isOpen) {
                    sidebar.classList.remove('active');
                    backdrop.classList.remove('active');
                    document.body.style.overflow = '';
                    
                    if (floatingBtn) {
                        floatingBtn.innerHTML = `
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <div class="indicator">
                                <span>i</span>
                            </div>
                        `;
                        floatingBtn.title = "Mostrar Información del Paciente";
                    }
                } else {
                    sidebar.classList.add('active');
                    backdrop.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    
                    if (floatingBtn) {
                        floatingBtn.innerHTML = `
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <div class="indicator">
                                <span>×</span>
                            </div>
                        `;
                        floatingBtn.title = "Cerrar Información del Paciente";
                    }
                }
            }
        }

        function openMobileNav() {
            const mobileNav = document.getElementById('mobileNav');
            const backdrop = document.getElementById('mobileNavBackdrop');
            
            if (mobileNav && backdrop && window.innerWidth < 1024) {
                mobileNav.classList.add('mobile-open');
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMobileNav() {
            const mobileNav = document.getElementById('mobileNav');
            const backdrop = document.getElementById('mobileNavBackdrop');
            
            if (mobileNav && backdrop) {
                mobileNav.classList.remove('mobile-open');
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        function toggleChatSidebar() {
            const chatSidebar = document.querySelector('.chat-sidebar');
            
            if (chatSidebar) {
                chatSidebar.classList.toggle('mobile-open');
            }
        }


        function handleSessionExpiry() {
            console.log('Sesión expirada - cerrando automáticamente');
            
            // Mostrar mensaje profesional
            showNotification('Tu sesión ha expirado por inactividad. Serás redirigido al login.', 'warning', 3000);
            
            // Desconectar chat si está activo
            if (chatSocket) {
                chatSocket.disconnect();
                chatSocket = null;
            }
            
            // Limpiar datos de sesión
            stopRealTimeUpdates();
            stopTransferMonitoring();
            clearSelectedFiles();
            
            // Redirect después de 3 segundos
            setTimeout(() => {
                logout();
            }, 3000);
        }

        // FUNCIONES DE PERFIL Y DISPONIBILIDAD

        async function loadUserProfile() {
            const container = document.getElementById('profileContainer');
            
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-500">Cargando perfil...</p>
                </div>
            `;
            
            try {
                const response = await fetch(`${AUTH_API}/users/profile`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        currentUserProfile = result.data.user;
                        displayUserProfile(result.data);
                        
                        updateAvailabilityUI(currentUserProfile.disponibilidad || 'ausente');
                    } else {
                        throw new Error(result.message || 'Error cargando perfil');
                    }
                } else {
                    throw new Error(`Error HTTP ${response.status}`);
                }
            } catch (error) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Error al cargar</h3>
                        <p class="text-gray-500 mb-4">Error: ${error.message}</p>
                        <button onclick="loadUserProfile()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Reintentar
                        </button>
                    </div>
                `;
            }
        }

        function displayUserProfile(data) {
            const { user, capabilities } = data;
            const container = document.getElementById('profileContainer');
            
            container.innerHTML = `
                <div class="space-y-6">
                    <!-- Información Personal -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Información Personal</h4>
                            <button onclick="showEditProfileModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                Editar
                            </button>
                        </div>
                                                
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Nombre</label>
                                <p class="text-gray-900 font-medium">${user.name || 'No especificado'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Email</label>
                                <p class="text-gray-900">${user.email || 'No especificado'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Rol</label>
                                <p class="text-gray-900 capitalize">${getRoleName(user.role)}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Estado</label>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                    ${user.is_active ? 'Activo' : 'Inactivo'}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Horarios de Trabajo mejorados -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Mis Horarios de Trabajo</h4>
                        <div id="agentSchedulesContainer">
                            <div class="text-center py-4">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto mb-2"></div>
                                <p class="text-sm text-gray-500">Cargando horarios...</p>
                            </div>
                        </div>
                    </div>
                    
                    ${capabilities.can_change_availability ? `
                        <!-- Disponibilidad -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Disponibilidad para Chats</h4>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-700">Estado actual: 
                                        <span id="profileAvailabilityStatus" class="font-semibold ${user.disponibilidad === 'presente' ? 'text-green-600' : 'text-red-600'}">
                                            ${user.disponibilidad === 'presente' ? 'Presente' : 'Ausente'}
                                        </span>
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        ${user.disponibilidad === 'presente' ? 'Puedes recibir nuevas sesiones de chat' : 'No recibirás nuevas sesiones de chat'}
                                    </p>
                                </div>
                                <button onclick="toggleAvailability()" 
                                        class="px-4 py-2 ${user.disponibilidad === 'presente' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'} text-white rounded-lg text-sm">
                                    ${user.disponibilidad === 'presente' ? 'Marcar Ausente' : 'Marcar Presente'}
                                </button>
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Seguridad -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Seguridad</h4>
                            <button onclick="showChangePasswordModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                Cambiar Contraseña
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Última conexión</label>
                                <p class="text-gray-700">${user.last_login ? new Date(user.last_login).toLocaleString('es-ES') : 'Nunca'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Cuenta creada</label>
                                <p class="text-gray-700">${new Date(user.created_at).toLocaleString('es-ES')}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Cargar horarios automáticamente
            setTimeout(() => {
                loadAgentSchedules();
            }, 500);
        }

        function getRoleName(role) {
            const roles = {
                1: 'Paciente',
                2: 'Agente',
                3: 'Supervisor',
                4: 'Administrador'
            };
            return roles[role] || 'Desconocido';
        }

        function showEditProfileModal() {
            if (!currentUserProfile) return;
            
            document.getElementById('editName').value = currentUserProfile.name || '';
            document.getElementById('editEmail').value = currentUserProfile.email || '';
            
            document.getElementById('editProfileModal').classList.remove('hidden');
        }

        function showChangePasswordModal() {
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            
            document.getElementById('changePasswordModal').classList.remove('hidden');
        }

        // DISPONIBILIDAD - Función mejorada que funciona desde cualquier sección
        async function toggleAvailability() {
            // Primero obtener el perfil actual si no lo tenemos
            if (!currentUserProfile) {
                try {
                    const response = await fetch(`${AUTH_API}/users/profile`, {
                        method: 'GET',
                        headers: getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            currentUserProfile = result.data.user;
                        }
                    }
                } catch (error) {
                    showNotification('Error obteniendo perfil del usuario', 'error');
                    return;
                }
            }
            
            if (!currentUserProfile) {
                showNotification('Error: No se pudo cargar el perfil del usuario', 'error');
                return;
            }
            
            const currentStatus = currentUserProfile.disponibilidad || 'ausente';
            const newStatus = currentStatus === 'presente' ? 'ausente' : 'presente';
            
            try {
                const response = await fetch(`${AUTH_API}/users/availability`, {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ status: newStatus })
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        currentUserProfile.disponibilidad = newStatus;
                        updateAvailabilityUI(newStatus);
                        showNotification(result.message, 'success');
                        
                        // Actualizar también en la vista de perfil si está activa
                        const profileSection = document.getElementById('profile-section');
                        if (profileSection && !profileSection.classList.contains('hidden')) {
                            setTimeout(() => loadUserProfile(), 500);
                        }
                    } else {
                        throw new Error(result.message || 'Error cambiando disponibilidad');
                    }
                } else {
                    throw new Error(`Error HTTP ${response.status}`);
                }
            } catch (error) {
                showNotification('Error cambiando disponibilidad: ' + error.message, 'error');
            }
        }

        function updateAvailabilityUI(status) {
            // Toggle desktop
            const toggle = document.getElementById('availabilityToggle');
            const text = document.getElementById('availabilityText');
            const slider = toggle?.querySelector('.toggle-slider');
            
            if (toggle && text) {
                toggle.setAttribute('data-status', status);
                text.textContent = status === 'presente' ? 'Presente' : 'Ausente';
                text.className = `ml-2 text-xs font-medium ${status === 'presente' ? 'text-green-600' : 'text-red-600'}`;
                
                if (slider) {
                    if (status === 'presente') {
                        slider.classList.add('active');
                    } else {
                        slider.classList.remove('active');
                    }
                }
            }
            
            // Toggle mobile
            const mobileToggle = document.getElementById('mobileAvailabilityToggle');
            const mobileText = document.getElementById('mobileAvailabilityText');
            const mobileSlider = mobileToggle?.querySelector('.toggle-slider');
            
            if (mobileToggle && mobileText) {
                mobileToggle.setAttribute('data-status', status);
                mobileText.textContent = status === 'presente' ? 'Presente' : 'Ausente';
                mobileText.className = `ml-2 text-xs font-medium ${status === 'presente' ? 'text-green-600' : 'text-red-600'}`;
                
                if (mobileSlider) {
                    if (status === 'presente') {
                        mobileSlider.classList.add('active');
                    } else {
                        mobileSlider.classList.remove('active');
                    }
                }
            }
        }


        // Mostrar información de horarios en la UI
        function updateScheduleStatusUI(scheduleInfo) {
            // Actualizar el estado en la sidebar
            const availabilityText = document.getElementById('availabilityText');
            const availabilityToggle = document.getElementById('availabilityToggle');
            
            if (scheduleInfo && availabilityText && availabilityToggle) {
                if (!scheduleInfo.can_receive_sessions) {
                    // Fuera de horario - mostrar estado especial
                    availabilityText.textContent = 'Fuera de horario';
                    availabilityText.className = 'ml-2 text-xs font-medium text-orange-600';
                    
                    // Agregar tooltip con información
                    availabilityToggle.title = scheduleInfo.schedule_message || 'Fuera del horario de trabajo';
                    
                    // Cambiar visualmente el toggle (mantener funcional pero con indicador visual)
                    const slider = availabilityToggle.querySelector('.toggle-slider');
                    if (slider) {
                        slider.style.opacity = '0.6';
                        slider.style.border = '2px solid orange';
                    }
                } else {
                    // Dentro de horario - estado normal
                    const slider = availabilityToggle.querySelector('.toggle-slider');
                    if (slider) {
                        slider.style.opacity = '1';
                        slider.style.border = 'none';
                    }
                    availabilityToggle.title = 'Cambiar disponibilidad';
                }
            }
            
            // Actualizar header si existe
            const headerStatus = document.querySelector('.flex.items-center.gap-2:has(.w-2.h-2.bg-green-500)');
            if (headerStatus && scheduleInfo && !scheduleInfo.can_receive_sessions) {
                const statusDot = headerStatus.querySelector('.w-2.h-2');
                const statusText = headerStatus.querySelector('.text-xs.sm\\:text-sm');
                
                if (statusDot) {
                    statusDot.className = 'w-2 h-2 bg-orange-500 rounded-full';
                }
                if (statusText) {
                    statusText.textContent = 'Fuera de horario';
                }
            }
        }

        function playTransferNotificationSound(transferType) {
            try {
                // Solo reproducir si el usuario no ha deshabilitado los sonidos
                if (window.notificationSoundsEnabled !== false) {
                    const audio = new Audio();
                    
                    // Diferentes tonos para diferentes tipos de transferencia
                    switch (transferType) {
                        case 'internal_received':
                            // Tono corto y agradable
                            audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmUUBjuG0+7Bhz0IHm++8+SZVAY...'; // Tono suave
                            break;
                        case 'external_received':
                            // Tono más llamativo
                            audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmUUBjuG0+7Bhz0IHm++8+SZVAY...'; // Tono diferente
                            break;
                        default:
                            // Tono por defecto
                            audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmUUBjuG0+7Bhz0IHm++8+SZVAY...';
                    }
                    
                    audio.volume = 0.3; // Volumen bajo
                    audio.play().catch(() => {
                        // Ignorar errores de audio (puede estar bloqueado por el navegador)
                    });
                }
            } catch (error) {
                // Ignorar errores de audio
            }
        }

        function setupGlobalErrorHandler() {
            // Interceptar errores de fetch
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                return originalFetch.apply(this, args)
                    .catch(error => {
                        console.error('Network error:', error);
                        
                        // Si es error de conexión o 404, podría ser sesión expirada
                        if (error.message.includes('Failed to fetch') || 
                            error.message.includes('NetworkError') ||
                            error.message.includes('ERR_CONNECTION_REFUSED')) {
                            
                            // Verificar si es error relacionado con autenticación
                            const token = getToken();
                            if (!token) {
                                handleSessionExpiry();
                                return Promise.reject(error);
                            }
                            
                            // Intentar verificar si la sesión está válida
                            testSessionValidity()
                                .then(isValid => {
                                    if (!isValid) {
                                        handleSessionExpiry();
                                    }
                                })
                                .catch(() => {
                                    // Si no podemos verificar, asumir que expiró
                                    handleSessionExpiry();
                                });
                        }
                        
                        return Promise.reject(error);
                    });
            };
        }

        async function testSessionValidity() {
            try {
                const response = await originalFetch(`${AUTH_API}/users/profile`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                return response.ok;
            } catch (error) {
                return false;
            }
        }

        function startSessionValidityCheck() {
            // Verificar cada 5 minutos si la sesión sigue válida
            setInterval(async () => {
                const isValid = await testSessionValidity();
                if (!isValid) {
                    handleSessionExpiry();
                }
            }, 5 * 60 * 1000); // 5 minutos
        }

        // Inicialización del sistema
        document.addEventListener('DOMContentLoaded', async () => {

            startGlobalPendingMonitoringFixed();

            setupGlobalErrorHandler();
            startSessionValidityCheck();

            updateTime();
            setInterval(updateTime, 1000);
            
            setupAgentTyping();
            setupFileUploadEvents();
            showPendingSection();
            startAutoRefresh();
            
            startRealTimeUpdates();

            startTransferMonitoringImproved();
            
            setTimeout(() => {
                updateSidebarCounts();
            }, 2000);
            
            // Eventos para sistema responsivo
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) {
                    // Forzar cierre de elementos móviles en desktop
                    closeMobileNav();
                    closePatientInfoSidebar();
                    
                    // Asegurar que no hay clases móviles activas
                    const mobileNav = document.getElementById('mobileNav');
                    if (mobileNav) {
                        mobileNav.classList.remove('mobile-open');
                    }
                    
                    // Restablecer overflow del body
                    document.body.style.overflow = '';
                }
                
                const chatPanel = document.getElementById('patient-chat-panel');
                if (chatPanel && !chatPanel.classList.contains('hidden') && currentSession && window.innerWidth <= 1024) {
                    const floatingBtn = document.getElementById('patientInfoFloatingBtn');
                    if (floatingBtn) floatingBtn.classList.add('show');
                } else {
                    const floatingBtn = document.getElementById('patientInfoFloatingBtn');
                    if (floatingBtn) floatingBtn.classList.remove('show');
                }
            });
            
            // Event listeners para los formularios
            document.getElementById('editProfileForm')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const name = document.getElementById('editName').value.trim();
                const email = document.getElementById('editEmail').value.trim();
                
                if (!name || !email) {
                    showNotification('Nombre y email son requeridos', 'error');
                    return;
                }
                
                try {
                    const response = await fetch(`${AUTH_API}/users/profile`, {
                        method: 'PUT',
                        headers: getAuthHeaders(),
                        body: JSON.stringify({ name, email })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            showNotification('Perfil actualizado exitosamente', 'success');
                            closeModal('editProfileModal');
                            loadUserProfile();
                        } else {
                            throw new Error(result.message || 'Error actualizando perfil');
                        }
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                } catch (error) {
                    showNotification('Error actualizando perfil: ' + error.message, 'error');
                }
            });
            
            document.getElementById('changePasswordForm')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const current_password = document.getElementById('currentPassword').value;
                const new_password = document.getElementById('newPassword').value;
                const confirm_password = document.getElementById('confirmPassword').value;
                
                if (!current_password || !new_password || !confirm_password) {
                    showNotification('Todos los campos son requeridos', 'error');
                    return;
                }
                
                if (new_password !== confirm_password) {
                    showNotification('Las contraseñas no coinciden', 'error');
                    return;
                }
                
                if (new_password.length < 8) {
                    showNotification('La nueva contraseña debe tener al menos 8 caracteres', 'error');
                    return;
                }
                
                try {
                    const response = await fetch(`${AUTH_API}/users/change-password`, {
                        method: 'POST',
                        headers: getAuthHeaders(),
                        body: JSON.stringify({ current_password, new_password, confirm_password })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            showNotification('Contraseña cambiada exitosamente', 'success');
                            closeModal('changePasswordModal');
                        } else {
                            throw new Error(result.message || 'Error cambiando contraseña');
                        }
                    } else {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.message || `Error HTTP ${response.status}`);
                    }
                } catch (error) {
                    showNotification('Error cambiando contraseña: ' + error.message, 'error');
                }
            });
        });

        // Eventos globales
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.fixed.inset-0').forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        modal.classList.add('hidden');
                    }
                });
                
                closeMobileNav();
                closePatientInfoSidebar();
            }
        });

        // Limpiar al salir
        window.addEventListener('beforeunload', () => {
            stopRealTimeUpdates();
            conversationTimers.forEach((timer, sessionId) => {
                stopConversationTimer(sessionId);
            });
            if (chatSocket) {
                chatSocket.disconnect();
            }
            clearSelectedFiles();
        });

        // Funciones globales accesibles
        window.showPatientInfoButton = showPatientInfoButton;
        window.hidePatientInfoButton = hidePatientInfoButton;
        window.openPatientInfoSidebar = openPatientInfoSidebar;
        window.closePatientInfoSidebar = closePatientInfoSidebar;
        window.togglePatientInfoSidebar = togglePatientInfoSidebar;
        window.openMobileNav = openMobileNav;
        window.closeMobileNav = closeMobileNav;
        window.toggleChatSidebar = toggleChatSidebar;
        window.showPendingSection = showPendingSection;
        window.showMyChatsSection = showMyChatsSection;
        window.showRoomsSection = showRoomsSection;
        window.showProfileSection = showProfileSection;
        window.goBackToPending = goBackToPending;
        window.loadPendingConversations = loadPendingConversationsWithTransferDetection;
        window.loadMyChats = loadMyChatsWithTransferDetection;
        window.loadChatsSidebar = loadChatsSidebar;
        window.updateScheduleStatusUI = updateScheduleStatusUI;
        window.renderPendingConversationsHTML = renderPendingConversationsHTML;
        window.renderMyChatsHTML = renderMyChatsHTML;
        window.loadRoomsFromAuthService = loadRoomsFromAuthService;
        window.selectRoom = selectRoom;
        window.loadSessionsByRoom = loadSessionsByRoom;
        window.takeSessionFromRoom = takeSessionFromRoom;
        window.continueSessionFromRoom = continueSessionFromRoom;
        window.takeConversation = takeConversation;
        window.takeConversationFromSidebar = takeConversationFromSidebar;
        window.openChatFromMyChats = openChatFromMyChats;
        window.selectChatFromSidebar = selectChatFromSidebar;
        window.sendMessage = sendMessage;
        window.handleAgentKeyDown = handleAgentKeyDown;
        window.showTransferModal = showTransferModal;
        window.showEndSessionModal = showEndSessionModal;
        window.showReturnModal = showReturnModal;
        window.showEscalationModal = showEscalationModal;
        window.closeModal = closeModal;
        window.toggleTransferFields = toggleTransferFields;
        window.executeTransfer = executeTransfer;
        window.executeEndSession = executeEndSession;
        window.executeReturn = executeReturn;
        window.executeEscalation = executeEscalation;
        window.showEmptyChat = showEmptyChat;
        window.openFileInNewTab = openFileInNewTab;
        window.logout = logout;
        window.clearDuplicationForTransferredChat = clearDuplicationForTransferredChat;
        window.showProfileSection = showProfileSection;
        window.loadUserProfile = loadUserProfile;
        window.showEditProfileModal = showEditProfileModal;
        window.showChangePasswordModal = showChangePasswordModal;
        window.toggleAvailability = toggleAvailability;
        window.handleTransferNotificationAction = handleTransferNotificationAction;
        window.startTransferMonitoring = startTransferMonitoring;
        window.stopTransferMonitoring = stopTransferMonitoring;
        window.clearTransferNotificationCache = clearTransferNotificationCache;
        window.loadAvailableRooms = loadAvailableRooms;
        window.populateRoomSelect = populateRoomSelect;
        // RF7: Funciones globales de archivos
        window.toggleFileUpload = toggleFileUpload;
        window.removeSelectedFile = removeSelectedFile;
        window.clearSelectedFiles = clearSelectedFiles;
        window.stopGlobalPendingMonitoring = stopGlobalPendingMonitoring;
        window.handleSessionTerminated = handleSessionTerminated;
        window.handlePatientDisconnected = handlePatientDisconnected;
        window.disableAgentChatControls = disableAgentChatControls;
        window.updateListsAfterSessionTermination = updateListsAfterSessionTermination;
        window.checkGlobalPendingConversations = checkGlobalPendingConversations;
        window.detectAndNotifyNewConversationsFixed = detectAndNotifyNewConversationsFixed;
        window.startGlobalPendingMonitoringFixed = startGlobalPendingMonitoringFixed;
        window.showSingleConversationNotification = showSingleConversationNotification;
        window.takeConversationDirectlyFromNotification = takeConversationDirectlyFromNotification;
        window.handleSessionEndedByPatient = handleSessionEndedByPatient;
        window.setupSocketEventHandlers = setupSocketEventHandlers;



        window.transferDebug = {
            enabled: true,
            logs: [],
            lastCheck: null,
            counters: {
                pendingChecks: 0,
                activeChecks: 0,
                notificationsShown: 0,
                errors: 0
            }
        };

        function debugLog(message, data = null) {
            if (!window.transferDebug.enabled) return;
            
            const timestamp = new Date().toISOString();
            const logEntry = {
                timestamp,
                message,
                data: data ? JSON.stringify(data, null, 2) : null
            };
            
            window.transferDebug.logs.push(logEntry);
            console.log(`[TRANSFER DEBUG ${timestamp}] ${message}`, data || '');
            
            if (window.transferDebug.logs.length > 100) {
                window.transferDebug.logs = window.transferDebug.logs.slice(-100);
            }
        }

        function showTransferDebugInfoImproved() {
            console.group('=== TRANSFER SYSTEM DEBUG INFO ===');
            console.log('Counters:', window.transferDebug.counters);
            console.log('Last Check:', window.transferDebug.lastCheck);
            console.log('Cache Sizes:', {
                previousPending: previousPendingTransfers.size,
                previousActive: previousMyChatsTransfers.size,
                notificationCache: transferNotificationCache.size
            });
            console.log('Recent Logs:');
            window.transferDebug.logs.slice(-10).forEach(log => {
                console.log(`  ${log.timestamp}: ${log.message}`);
            });
            console.groupEnd();
        }

        async function checkForNewTransfersImproved() {
            window.transferDebug.lastCheck = new Date();
            debugLog('Iniciando verificación de transferencias');
            
            try {
                if (currentSession && isConnectedToChat) {
                    debugLog('Saltando verificación - sesión activa en chat');
                    return;
                }
                
                // NUEVO: Usar endpoint directo de transferencias recientes
                await checkRecentTransfers();
                cleanupTransferNotificationCache();
                
                debugLog('Verificación completada exitosamente');
                
            } catch (error) {
                window.transferDebug.counters.errors++;
                debugLog('Error en verificación', { error: error.message });
            }
        }

        // NUEVA FUNCIÓN: Verificar transferencias recientes directamente
        async function checkRecentTransfers() {
            debugLog('Verificando transferencias recientes');
            window.transferDebug.counters.pendingChecks++;
            
            try {
                const currentUser = getCurrentUser();
                
                // Llamar al nuevo endpoint de transferencias recientes
                const response = await fetch(`${CHAT_API}/transfers/recent?agent_id=${currentUser.id}&minutes=10`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const result = await response.json();
                debugLog('Respuesta transferencias recientes', { success: result.success, total: result.data?.total || 0 });
                
                if (result.success && result.data && result.data.transfers) {
                    const recentTransfers = result.data.transfers;
                    
                    debugLog(`Transferencias recientes: ${recentTransfers.length}`);
                    
                    // Filtrar transferencias que no hemos notificado antes
                    const newTransfers = recentTransfers.filter(transfer => {
                        const notificationKey = `${transfer.transfer_id}_${transfer.transfer_type}`;
                        return !transferNotificationCache.has(notificationKey);
                    });
                    
                    debugLog(`Nuevas transferencias: ${newTransfers.length}`);
                    
                    // Mostrar notificaciones para transferencias nuevas
                    for (const transfer of newTransfers) {
                        showRecentTransferNotification(transfer);
                        window.transferDebug.counters.notificationsShown++;
                        
                        // Marcar como notificada
                        const notificationKey = `${transfer.transfer_id}_${transfer.transfer_type}`;
                        transferNotificationCache.set(notificationKey, Date.now());
                    }
                }
                
            } catch (error) {
                debugLog('Error verificando transferencias recientes', { error: error.message });
            }
        }

        // Mostrar notificación de transferencia reciente
        function showRecentTransferNotification(transfer) {
            debugLog('Mostrando notificación de transferencia reciente', {
                transferId: transfer.transfer_id,
                sessionId: transfer.session_id,
                patientName: transfer.patient_name,
                transferType: transfer.transfer_type
            });
            
            let message, type, duration;
            let actionButton = '';
            
            switch (transfer.transfer_type) {
                case 'internal':
                    message = `Transferencia Interna Recibida\n👤 Paciente: ${transfer.patient_name}\n👨‍💼 De: ${transfer.from_agent.name}\n📝 Motivo: ${transfer.reason}`;
                    type = 'success';
                    duration = 8000;
                    actionButton = `
                        <button onclick="handleRecentTransferAction('${transfer.session_id}', 'continue')" 
                                class="px-3 py-1 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium">
                            Continuar Chat
                        </button>
                    `;
                    break;
                    
                case 'external':
                    message = `Transferencia Externa Recibida\n👤 Paciente: ${transfer.patient_name}\n🏥 De: ${transfer.from_room.name}\n📝 Motivo: ${transfer.reason}`;
                    type = 'info';
                    duration = 8000;
                    actionButton = `
                        <button onclick="handleRecentTransferAction('${transfer.session_id}', 'continue')" 
                                class="px-3 py-1 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium">
                            Continuar Chat
                        </button>
                    `;
                    break;
                    
                default:
                    message = `Nueva Transferencia\n👤 Paciente: ${transfer.patient_name}\n📝 ${transfer.reason}`;
                    type = 'info';
                    duration = 6000;
                    actionButton = `
                        <button onclick="handleRecentTransferAction('${transfer.session_id}', 'continue')" 
                                class="px-3 py-1 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium">
                             Ver Chat
                        </button>
                    `;
            }
            
            // Crear notificación visual mejorada
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-xl max-w-sm text-white ${getNotificationColorClass(type)} border-l-4 border-white`;
            
            const messageLines = message.split('\n');
            const formattedMessage = messageLines.map(line => `<div class="text-sm">${line}</div>`).join('');
            
            notification.innerHTML = `
                <div class="flex flex-col gap-2">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="text-sm font-bold mb-1">✨ Nueva Transferencia (${Math.round(transfer.minutes_ago)} min ago)</div>
                            ${formattedMessage}
                        </div>
                        <button onclick="this.closest('.fixed').remove()" 
                                class="ml-2 text-xl font-bold hover:bg-white hover:bg-opacity-20 rounded px-1">×</button>
                    </div>
                    ${actionButton ? `
                        <div class="flex gap-2 mt-2 pt-2 border-t border-white border-opacity-30">
                            ${actionButton}
                            <button onclick="this.closest('.fixed').remove()" 
                                    class="px-3 py-1 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium">
                                 Después
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover después del tiempo especificado
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
            
            // Efecto de entrada
            notification.style.transform = 'translateX(100%)';
            notification.style.transition = 'transform 0.3s ease-out';
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            debugLog('Notificación de transferencia reciente mostrada');
        }

        function getNotificationColorClass(type) {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500',
                warning: 'bg-yellow-500'
            };
            return colors[type] || 'bg-blue-500';
        }

        async function handleRecentTransferAction(sessionId, action) {
            debugLog('Ejecutando acción de transferencia reciente', { sessionId, action });
            
            try {
                // Buscar la sesión en mis chats activos
                await loadMyChats(); // Asegurar que tenemos la lista actualizada
                
                let session = null;
                if (myChats) {
                    session = myChats.find(s => s.id === sessionId);
                }
                
                if (!session && allChatsForSidebar?.myChats) {
                    session = allChatsForSidebar.myChats.find(s => s.id === sessionId);
                }
                
                if (session) {
                    await openChatDirectly(session);
                    showNotification('✅ Abriendo chat transferido', 'success');
                } else {
                    showNotification('❌ Sesión no encontrada en tus chats activos', 'warning');
                    // Intentar cargar listas actualizadas
                    setTimeout(() => {
                        loadMyChats();
                        loadChatsSidebar();
                    }, 1000);
                }
            } catch (error) {
                showNotification('❌ Error: ' + error.message, 'error');
                debugLog('Error en acción de transferencia reciente', { error: error.message });
            }
        }

        async function checkPendingTransfersImproved() {
            debugLog('Verificando pendientes');
            window.transferDebug.counters.pendingChecks++;
            
            try {
                const response = await fetch(`${ADMIN_API}/agent-assignments/my-sessions?status=waiting&limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const result = await response.json();
                debugLog('Respuesta pendientes', { success: result.success, dataExists: !!result.data });
                
                if (result.success && result.data && result.data.sessions) {
                    const waitingSessions = result.data.sessions.filter(session => {
                        return session && session.status === 'waiting' && !session.agent_id;
                    });
                    
                    debugLog(`Sesiones en espera: ${waitingSessions.length}`);
                    
                    const newTransfers = [];
                    waitingSessions.forEach(session => {
                        if (!previousPendingTransfers.has(session.id)) {
                            debugLog(`Nueva sesión detectada: ${session.id}`, { transfer_info: session.transfer_info });
                            
                            if (session.transfer_info && 
                                (session.transfer_info.transferred_to_me || 
                                 session.transfer_info.external_transfer_approved ||
                                 session.transfer_info.transfer_rejected)) {
                                
                                newTransfers.push({
                                    session: session,
                                    transferType: 'received',
                                    transferFrom: session.transfer_info.from_agent_name || 'Agente',
                                    transferReason: session.transfer_info.reason || 'Transferencia',
                                    sessionType: 'pending'
                                });
                            }
                        }
                    });
                    
                    debugLog(`Nuevas transferencias: ${newTransfers.length}`);
                    
                    for (const transferData of newTransfers) {
                        showTransferNotificationImproved(transferData);
                        window.transferDebug.counters.notificationsShown++;
                    }
                    
                    previousPendingTransfers.clear();
                    waitingSessions.forEach(session => {
                        previousPendingTransfers.add(session.id);
                    });
                }
            } catch (error) {
                debugLog('Error verificando pendientes', { error: error.message });
            }
        }

        async function checkActiveTransfersImproved() {
            debugLog('Verificando activos');
            window.transferDebug.counters.activeChecks++;
            
            try {
                const response = await fetch(`${ADMIN_API}/agent-assignments/my-sessions?status=active&limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const result = await response.json();
                debugLog('Respuesta activos', { success: result.success, dataExists: !!result.data });
                
                if (result.success && result.data && result.data.sessions) {
                    const currentUser = getCurrentUser();
                    const activeSessions = result.data.sessions.filter(session => {
                        return session && session.status === 'active' && session.agent_id === currentUser.id;
                    });
                    
                    debugLog(`Sesiones activas: ${activeSessions.length}`);
                    
                    const newTransfers = [];
                    activeSessions.forEach(session => {
                        if (!previousMyChatsTransfers.has(session.id)) {
                            debugLog(`Nueva sesión activa: ${session.id}`, { transfer_info: session.transfer_info });
                            
                            if (session.transfer_info && 
                                (session.transfer_info.transferred_to_me || 
                                 session.transfer_info.external_transfer_approved ||
                                 session.transfer_info.transfer_rejected)) {
                                
                                newTransfers.push({
                                    session: session,
                                    transferType: 'received',
                                    transferFrom: session.transfer_info.from_agent_name || 'Agente',
                                    transferReason: session.transfer_info.reason || 'Transferencia',
                                    sessionType: 'active'
                                });
                            }
                        }
                    });
                    
                    debugLog(`Nuevas transferencias activas: ${newTransfers.length}`);
                    
                    for (const transferData of newTransfers) {
                        showTransferNotificationImproved(transferData);
                        window.transferDebug.counters.notificationsShown++;
                    }
                    
                    previousMyChatsTransfers.clear();
                    activeSessions.forEach(session => {
                        previousMyChatsTransfers.add(session.id);
                    });
                }
            } catch (error) {
                debugLog('Error verificando activos', { error: error.message });
            }
        }

        function showTransferNotificationImproved(transferData) {
            const { session, transferType, transferFrom, transferReason, sessionType } = transferData;
            const patientName = getPatientNameFromSession(session);
            const roomName = getRoomNameFromSession(session);
            
            debugLog('Mostrando notificación', {
                sessionId: session.id,
                patientName,
                roomName
            });
            
            // Crear notificación visual
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-xl max-w-sm text-white bg-green-500 border-l-4 border-green-600';
            
            notification.innerHTML = `
                <div class="flex flex-col gap-2">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="text-sm font-bold">🔄 Nueva Transferencia</div>
                            <div class="text-sm">👤 ${patientName}</div>
                            <div class="text-sm">🏥 ${roomName}</div>
                            <div class="text-sm">👨‍💼 De: ${transferFrom}</div>
                        </div>
                        <button onclick="this.closest('.fixed').remove()" 
                                class="ml-2 text-xl font-bold hover:bg-white hover:bg-opacity-20 rounded px-1">×</button>
                    </div>
                    <div class="flex gap-2 mt-2 pt-2 border-t border-white border-opacity-30">
                        <button onclick="handleTransferActionImproved('${session.id}', '${sessionType}')" 
                                class="px-3 py-1 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium">
                            ${sessionType === 'pending' ? '📞 Tomar Ahora' : '💬 Continuar Chat'}
                        </button>
                        <button onclick="this.closest('.fixed').remove()" 
                                class="px-3 py-1 bg-white bg-opacity-20 hover:bg-opacity-30 rounded text-xs font-medium">
                            ⏰ Después
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 10000);
            
            debugLog('Notificación mostrada');
        }

        async function handleTransferActionImproved(sessionId, sessionType) {
            debugLog('Ejecutando acción', { sessionId, sessionType });
            
            try {
                if (sessionType === 'pending') {
                    await takeConversation(sessionId);
                    showNotification('✅ Sesión tomada exitosamente', 'success');
                } else {
                    await openChatFromMyChats(sessionId);
                    showNotification('✅ Continuando chat', 'success');
                }
            } catch (error) {
                showNotification('❌ Error: ' + error.message, 'error');
                debugLog('Error en acción', { error: error.message });
            }
        }

        async function loadAgentSchedules() {
            const container = document.getElementById('agentSchedulesContainer');
            if (!container) return;

            try {
                const currentUser = getCurrentUser();
                const response = await fetch(`${ADMIN_API}/agent-assignments/my-schedules`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success && result.data) {
                    displayAgentSchedules(result.data);
                } else {
                    throw new Error('No se pudieron cargar los horarios');
                }

            } catch (error) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <p class="text-sm text-red-600">Error cargando horarios: ${error.message}</p>
                        <button onclick="loadAgentSchedules()" class="mt-2 text-blue-600 text-xs hover:underline">
                            Reintentar
                        </button>
                    </div>
                `;
            }
        }

        // ===== FUNCIONES DE VALIDACIÓN DE CONTRASEÑAS =====

        // Función para validar la fortaleza de la contraseña
        function validatePasswordStrength(passwordId, indicatorId) {
            const password = document.getElementById(passwordId)?.value || '';
            const indicator = document.getElementById(indicatorId);
            
            if (!indicator) return;
            
            if (password === '') {
                indicator.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let messages = [];
            
            // Verificar longitud mínima
            if (password.length >= 8) {
                strength++;
                messages.push('<span class="text-green-600">✓ Mínimo 8 caracteres</span>');
            } else {
                messages.push('<span class="text-red-600">✗ Mínimo 8 caracteres</span>');
            }
            
            // Verificar mayúscula
            if (/[A-Z]/.test(password)) {
                strength++;
                messages.push('<span class="text-green-600">✓ Mayúscula</span>');
            } else {
                messages.push('<span class="text-red-600">✗ 1 letra mayúscula</span>');
            }
            
            // Verificar minúscula  
            if (/[a-z]/.test(password)) {
                strength++;
                messages.push('<span class="text-green-600">✓ Minúscula</span>');
            } else {
                messages.push('<span class="text-red-600">✗ 1 letra minúscula</span>');
            }
            
            // Verificar número
            if (/[0-9]/.test(password)) {
                strength++;
                messages.push('<span class="text-green-600">✓ Número</span>');
            } else {
                messages.push('<span class="text-red-600">✗ 1 número</span>');
            }
            
            // Verificar caracteres problemáticos
            if (!/^[a-zA-Z0-9@#$%^&*()_+=\-\[\]{}|\\:";'<>?,./]*$/.test(password)) {
                messages.push('<span class="text-red-600">✗ Caracteres no válidos (evite ñ, acentos)</span>');
            }
            
            // Mostrar el indicador
            indicator.innerHTML = '<div class="text-xs space-y-1">' + messages.join('<br>') + '</div>';
            
            // Agregar barra de progreso
            const progressWidth = Math.max(0, Math.min(100, (strength / 4) * 100));
            let progressColor = '#dc2626'; // rojo
            if (strength >= 3) progressColor = '#059669'; // verde
            else if (strength >= 2) progressColor = '#d97706'; // naranja
            
            indicator.innerHTML += `
                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                    <div class="h-2 rounded-full transition-all duration-300" 
                        style="width: ${progressWidth}%; background-color: ${progressColor}"></div>
                </div>
            `;
        }

        // Función para validar coincidencia de contraseñas
        function validatePasswordMatch(password1Id, password2Id, indicatorId) {
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

        // Función para cambiar contraseña
        async function changePassword() {
            try {
                const currentPassword = document.getElementById('currentPassword').value;
                const newPassword = document.getElementById('newPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                
                // Validaciones
                if (!currentPassword) {
                    showStaffError('La contraseña actual es requerida');
                    return;
                }

                if (!newPassword) {
                    showStaffError('La nueva contraseña es requerida');
                    return;
                }

                if (!confirmPassword) {
                    showStaffError('Debes confirmar la nueva contraseña');
                    return;
                }

                if (newPassword !== confirmPassword) {
                    showStaffError('Las nuevas contraseñas no coinciden');
                    return;
                }

                if (newPassword.length < 8) {
                    showStaffError('La nueva contraseña debe tener al menos 8 caracteres');
                    return;
                }

                // Validar caracteres permitidos
                if (!/^[a-zA-Z0-9@#$%^&*()_+=\-\[\]{}|\\:";'<>?,./]*$/.test(newPassword)) {
                    showStaffError('La contraseña contiene caracteres no válidos. Use solo letras, números y símbolos estándar');
                    return;
                }

                // CAMBIAR ESTA URL POR LA CORRECTA DE TU SERVICIO
                const response = await fetch('http://187.33.158.246/auth/users/change-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${getStaffToken()}`
                    },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    })
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    showStaffSuccess('Contraseña cambiada exitosamente');
                    closeStaffModal('changePasswordModal');
                    document.getElementById('changePasswordForm').reset();
                    // Limpiar indicadores
                    document.getElementById('newPasswordStrength').innerHTML = '';
                    document.getElementById('passwordMatchIndicator').innerHTML = '';
                } else {
                    if (response.status === 400 && result.message?.includes('current_password')) {
                        showStaffError('La contraseña actual es incorrecta');
                    } else {
                        showStaffError(result.message || 'Error cambiando contraseña');
                    }
                }
                
            } catch (error) {
                showStaffError('Error de conexión: ' + error.message);
            }
        }

        // Función para obtener el token del staff
        function getStaffToken() {
            const phpTokenMeta = document.querySelector('meta[name="staff-token"]')?.content;
            if (phpTokenMeta && phpTokenMeta.trim() !== '') {
                return phpTokenMeta;
            }
            return null;
        }

        // Funciones de utilidad para mostrar mensajes
        function showStaffSuccess(message) {
            showStaffNotification(message, 'success');
        }

        function showStaffError(message) {
            showStaffNotification(message, 'error');
        }

        function showStaffNotification(message, type = 'info', duration = 4000) {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
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

        // Función para mostrar el modal de cambiar contraseña
        function showChangePasswordModal() {
            document.getElementById('changePasswordModal').classList.remove('hidden');
        }

        // Función para cerrar modales (mejorada para no conflictuar)
        function closeStaffModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                
                // Limpiar formulario si es el modal de contraseña
                if (modalId === 'changePasswordModal') {
                    const form = document.getElementById('changePasswordForm');
                    if (form) {
                        form.reset();
                        const strengthIndicator = document.getElementById('newPasswordStrength');
                        const matchIndicator = document.getElementById('passwordMatchIndicator');
                        if (strengthIndicator) strengthIndicator.innerHTML = '';
                        if (matchIndicator) matchIndicator.innerHTML = '';
                    }
                }
            }
        }

        // Función genérica para cerrar modales (si ya existe, la reemplaza)
        function closeModal(modalId) {
            closeStaffModal(modalId);
        }

        function displayAgentSchedules(data) {
            const container = document.getElementById('agentSchedulesContainer');
            if (!container) return;

            if (!data.schedules_by_room || data.schedules_by_room.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="text-gray-500">No tienes horarios configurados</p>
                    </div>
                `;
                return;
            }

            const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            
            const schedulesHTML = data.schedules_by_room.map(roomData => {
                const schedules = roomData.schedules.sort((a, b) => a.day_of_week - b.day_of_week);
                
                return `
                    <div class="mb-4 last:mb-0">
                        <h5 class="font-medium text-gray-900 mb-3 flex items-center text-sm">
                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                            ${roomData.room_name}
                        </h5>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                            ${schedules.map(schedule => `
                                <div class="schedule-card p-3">
                                    <div class="flex flex-col">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="schedule-day">${dayNames[schedule.day_of_week]}</span>
                                            ${schedule.is_available ? 
                                                '<span class="schedule-status bg-green-100 text-green-800">✓</span>' : 
                                                '<span class="schedule-status bg-gray-100 text-gray-600">—</span>'
                                            }
                                        </div>
                                        <div class="schedule-time text-gray-900">
                                            ${schedule.start_time.slice(0,5)} - ${schedule.end_time.slice(0,5)}
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }).join('');

            container.innerHTML = `
                <div class="space-y-4">
                    ${schedulesHTML}
                    <div class="border-t border-gray-200 pt-3 mt-4">
                        <div class="flex justify-between items-center text-xs text-gray-500">
                            <span>Salas asignadas: ${data.total_assignments}</span>
                            <span>Total horarios: ${data.total_schedules}</span>
                        </div>
                    </div>
                </div>
            `;
        }

        // Función global para exponer
        window.loadAgentSchedules = loadAgentSchedules;

        function testTransferSystemImproved() {
            console.log('🧪 INICIANDO TEST DEL SISTEMA DE TRANSFERENCIAS MEJORADO');
            
            // Resetear contadores
            window.transferDebug.counters = {
                pendingChecks: 0,
                activeChecks: 0,
                notificationsShown: 0,
                errors: 0
            };
    
            // Test de conectividad al nuevo endpoint
            const currentUser = getCurrentUser();
            fetch(`${CHAT_API}/transfers/recent?agent_id=${currentUser.id}&minutes=60`, {
                method: 'GET',
                headers: getAuthHeaders()
            })
            .then(response => {
                console.log('✅ Conectividad nuevo endpoint:', response.ok ? 'OK' : 'ERROR ' + response.status);
                return response.json();
            })
            .then(data => {
                console.log('📦 Respuesta de endpoint reciente:', data);
                console.log('📊 Transferencias encontradas:', data.data?.total || 0);
                
                // Mostrar notificación de prueba si hay transferencias
                if (data.data?.transfers?.length > 0) {
                    const testTransfer = data.data.transfers[0];
                    console.log('🧪 Mostrando notificación de prueba...');
                    showRecentTransferNotification(testTransfer);
                }
            })
            .catch(error => {
                console.log('❌ Error de conectividad:', error.message);
            });
            
            // Ejecutar verificación
            checkForNewTransfersImproved();
            
            setTimeout(() => {
                console.log('📊 RESULTADOS DEL TEST MEJORADO:');
                showTransferDebugInfoImproved();
            }, 3000);
        }
        function startTransferMonitoringImproved() {
            debugLog('Iniciando monitoreo mejorado con endpoint directo');
            
            // Limpiar interval anterior
            if (transferCheckInterval) {
                clearInterval(transferCheckInterval);
            }
            
            // Verificar inmediatamente
            setTimeout(() => {
                checkForNewTransfersImproved();
            }, 2000);
            
            // Verificar cada 15 segundos usando el nuevo sistema
            transferCheckInterval = setInterval(() => {
                checkForNewTransfersImproved();
            }, 15000);
            
            console.log('🚀 Monitoreo de transferencias mejorado iniciado cada 15 segundos');
            console.log('📡 Usando endpoint directo: /transfers/recent');
        }

        // Exponer funciones globales
        window.testTransferSystemImproved = testTransferSystemImproved;
        window.showTransferDebugInfoImproved = showTransferDebugInfoImproved;
        window.startTransferMonitoringImproved = startTransferMonitoringImproved;
        window.handleTransferActionImproved = handleTransferActionImproved;

        window.handleRecentTransferAction = handleRecentTransferAction;           // NUEVA
        window.checkRecentTransfers = checkRecentTransfers;                       // NUEVA
        window.showRecentTransferNotification = showRecentTransferNotification; 
