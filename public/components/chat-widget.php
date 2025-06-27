<?php

?>

<!-- Chat Widget Container -->
<div id="chatWidget" class="hidden bg-white shadow-2xl rounded-2xl overflow-hidden border border-gray-200 max-w-md w-full">
    
    <!-- Chat Header -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                    </div>
                    <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-400 border-2 border-white rounded-full"></div>
                </div>
                <div>
                    <h3 id="chatRoomName" class="text-sm font-semibold text-white">Atención Médica</h3>
                    <p id="chatStatus" class="text-xs text-blue-100">Conectando...</p>
                </div>
            </div>
            <div class="flex items-center space-x-1">
                <button id="minimizeChat" class="p-1.5 hover:bg-white hover:bg-opacity-10 rounded transition-colors">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                    </svg>
                </button>
                <button id="closeChat" onclick="requestEndChat()" class="p-1.5 hover:bg-white hover:bg-opacity-10 rounded transition-colors">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Messages Container -->
    <div id="chatMessages" class="h-80 overflow-y-auto px-3 py-3 space-y-3 bg-gray-50">
        <!-- Welcome Message -->
        <div class="flex justify-center">
            <div class="bg-white px-3 py-2 rounded-full shadow-sm border border-gray-200">
                <p class="text-xs text-gray-600 flex items-center">
                    <svg class="w-3 h-3 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    Chat seguro y encriptado
                </p>
            </div>
        </div>
        
        <!-- Initial System Message -->
        <div class="flex justify-start">
            <div class="max-w-xs">
                <div class="bg-white rounded-lg px-3 py-2 shadow-sm border border-gray-200">
                    <div class="flex items-center space-x-2 mb-1">
                        <div class="w-5 h-5 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-gray-700">Sistema Médico</span>
                    </div>
                    <p class="text-sm text-gray-700">
                        Hola, bienvenido a nuestro sistema de atención médica. Un profesional de la salud se conectará contigo en breve.
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        <span id="systemMessageTime">Ahora</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Typing Indicator -->
    <div id="typingIndicator" class="hidden px-3 py-2">
        <div class="flex justify-start">
            <div class="bg-white rounded-lg px-3 py-2 shadow-sm border border-gray-200">
                <div class="flex items-center space-x-2">
                    <div class="flex space-x-1">
                        <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></div>
                        <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                    <span class="text-xs text-gray-500">Escribiendo...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Input Area -->
    <div class="bg-white border-t border-gray-200 p-3">
        <!-- Emoji Picker -->
        <div id="emojiPicker" class="hidden mb-2 p-2 bg-gray-50 rounded-lg border border-gray-200 max-h-24 overflow-y-auto">
            <div class="grid grid-cols-8 gap-1">
                <!-- Emojis médicos y generales -->
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="😊">😊</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="😢">😢</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="😟">😟</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="😷">😷</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="🤒">🤒</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="🩺">🩺</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="💊">💊</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="🏥">🏥</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="👨‍⚕️">👨‍⚕️</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="👩‍⚕️">👩‍⚕️</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="❤️">❤️</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="👍">👍</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="👎">👎</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="🙏">🙏</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="💉">💉</button>
                <button class="emoji-btn p-1 hover:bg-gray-200 rounded text-sm" data-emoji="🩹">🩹</button>
            </div>
        </div>

        <!-- Input Controls -->
        <div class="flex items-end space-x-2">
            <div class="flex-1">
                <div class="relative">
                    <textarea 
                        id="messageInput" 
                        placeholder="Escribe tu mensaje..."
                        maxlength="500"
                        rows="1"
                        class="block w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none text-sm"
                        style="min-height: 36px; max-height: 80px;"
                    ></textarea>
                    <div class="absolute bottom-1 right-1 text-xs text-gray-400">
                        <span id="charCount">0</span>/500
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex space-x-1">
                <!-- Emoji Button -->
                <button 
                    id="emojiButton" 
                    type="button"
                    class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                    title="Agregar emoji"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>

                <!-- Send Button -->
                <button 
                    id="sendButton" 
                    type="button"
                    onclick="sendMessage()"
                    disabled
                    class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                    title="Enviar mensaje"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-2 flex flex-wrap gap-1">
            <button class="quick-action-btn px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-xs text-gray-700 transition-colors">
                🩺 Consulta general
            </button>
            <button class="quick-action-btn px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-xs text-gray-700 transition-colors">
                💊 Medicamentos
            </button>
            <button class="quick-action-btn px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-xs text-gray-700 transition-colors">
                🤒 Síntomas
            </button>
        </div>
    </div>

    <!-- Chat Footer -->
    <div class="bg-gray-50 px-3 py-2 border-t border-gray-200">
        <div class="flex items-center justify-between text-xs text-gray-500">
            <div class="flex items-center space-x-3">
                <span class="flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Seguro
                </span>
                <span id="connectionStatus" class="flex items-center">
                    <div class="w-1.5 h-1.5 bg-green-400 rounded-full mr-1"></div>
                    En línea
                </span>
            </div>
            <button onclick="requestHelpChat()" class="hover:text-gray-700 transition-colors" title="Ayuda">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </button>
        </div>
    </div>
</div>

<!-- Notifications Container -->
<div id="notificationsContainer" class="fixed top-4 right-4 z-50 space-y-2 max-w-sm w-full pointer-events-none">
    <!-- Las notificaciones se insertan aquí dinámicamente -->
</div>

<!-- Confirmation Modal -->
<div id="confirmationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
        <h3 id="confirmTitle" class="text-lg font-semibold text-gray-900 mb-2">Confirmar acción</h3>
        <p id="confirmMessage" class="text-gray-600 mb-4">¿Estás seguro de que quieres realizar esta acción?</p>
        <div class="flex space-x-3">
            <button id="confirmCancel" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors">
                Cancelar
            </button>
            <button id="confirmAccept" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors">
                Confirmar
            </button>
        </div>
    </div>
</div>

<script>
// Sistema de notificaciones mejorado
function showChatNotification(message, type = 'info', duration = 5000) {
    const container = document.getElementById('notificationsContainer');
    
    const notification = document.createElement('div');
    notification.className = `pointer-events-auto transform transition-all duration-300 translate-x-full`;
    
    const colors = {
        success: 'bg-green-500 border-green-600',
        error: 'bg-red-500 border-red-600',
        warning: 'bg-yellow-500 border-yellow-600',
        info: 'bg-blue-500 border-blue-600'
    };
    
    const icons = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>',
        error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>',
        warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>',
        info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
    };
    
    notification.innerHTML = `
        <div class="${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg border-l-4 max-w-sm">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${icons[type]}
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-medium">${message}</p>
                </div>
                <button onclick="this.closest('.pointer-events-auto').remove()" class="ml-3 hover:opacity-75">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(notification);
    
    // Animar entrada
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto-remover
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, duration);
}

// Sistema de confirmación modal
function showConfirmation(title, message, onConfirm, confirmText = 'Confirmar') {
    const modal = document.getElementById('confirmationModal');
    const titleEl = document.getElementById('confirmTitle');
    const messageEl = document.getElementById('confirmMessage');
    const cancelBtn = document.getElementById('confirmCancel');
    const acceptBtn = document.getElementById('confirmAccept');
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    acceptBtn.textContent = confirmText;
    
    // Limpiar eventos previos
    const newCancelBtn = cancelBtn.cloneNode(true);
    const newAcceptBtn = acceptBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    acceptBtn.parentNode.replaceChild(newAcceptBtn, acceptBtn);
    
    // Agregar nuevos eventos
    newCancelBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });
    
    newAcceptBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        if (onConfirm) onConfirm();
    });
    
    modal.classList.remove('hidden');
}

// Funciones de chat mejoradas
function requestEndChat() {
    showConfirmation(
        'Finalizar Consulta',
        '¿Estás seguro de que quieres terminar la consulta médica?',
        () => {
            endChat();
        },
        'Finalizar'
    );
}

function requestHelpChat() {
    showChatNotification('Para ayuda, escribe "ayuda" en el chat o contacta a tu administrador.', 'info');
}

function endChat() {
    if (window.chatClient) {
        window.chatClient.disconnect();
    }
    
    const chatContainer = document.getElementById('chatWidget');
    if (chatContainer) {
        chatContainer.classList.add('hidden');
    }
    
    showChatNotification('Consulta finalizada correctamente. ¡Que tengas un buen día!', 'success');
    
    setTimeout(() => {
        window.location.href = 'https://www.tpsalud.com';
    }, 3000);
}

// Auto-resize textarea mejorado
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            // Reset height to calculate new height
            this.style.height = 'auto';
            
            // Set new height
            const newHeight = Math.min(this.scrollHeight, 80);
            this.style.height = newHeight + 'px';
            
            // Update character count
            const charCount = this.value.length;
            document.getElementById('charCount').textContent = charCount;
            
            // Enable/disable send button
            const sendButton = document.getElementById('sendButton');
            if (sendButton) {
                sendButton.disabled = charCount === 0;
            }
        });

        // Enter to send (Shift+Enter for new line)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const sendButton = document.getElementById('sendButton');
                if (sendButton && !sendButton.disabled) {
                    sendMessage();
                }
            }
        });
    }

    // Emoji functionality
    const emojiButton = document.getElementById('emojiButton');
    if (emojiButton) {
        emojiButton.addEventListener('click', function() {
            const emojiPicker = document.getElementById('emojiPicker');
            if (emojiPicker) {
                emojiPicker.classList.toggle('hidden');
            }
        });
    }

    // Emoji selection
    document.querySelectorAll('.emoji-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const emoji = this.dataset.emoji;
            const messageInput = document.getElementById('messageInput');
            
            if (messageInput && emoji) {
                const cursorPos = messageInput.selectionStart;
                const textBefore = messageInput.value.substring(0, cursorPos);
                const textAfter = messageInput.value.substring(cursorPos);
                
                messageInput.value = textBefore + emoji + textAfter;
                messageInput.focus();
                messageInput.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
                
                // Trigger input event
                messageInput.dispatchEvent(new Event('input'));
                
                // Hide emoji picker
                document.getElementById('emojiPicker').classList.add('hidden');
            }
        });
    });

    // Quick actions
    document.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.value = this.textContent.trim();
                messageInput.focus();
                messageInput.dispatchEvent(new Event('input'));
            }
        });
    });

    // Hide emoji picker when clicking outside
    document.addEventListener('click', function(e) {
        const emojiPicker = document.getElementById('emojiPicker');
        const emojiButton = document.getElementById('emojiButton');
        
        if (emojiPicker && emojiButton && 
            !emojiPicker.contains(e.target) && 
            !emojiButton.contains(e.target)) {
            emojiPicker.classList.add('hidden');
        }
    });
});

console.log('💬 Chat widget mejorado cargado');
</script>