<?php
// Widget de chat médico con emojis
?>

<!-- Chat Widget Container -->
<div id="chatWidget" class="hidden bg-white shadow-2xl rounded-2xl overflow-hidden border border-gray-200">
    
    <!-- Chat Header -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 21l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                    </div>
                    <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-400 border-2 border-white rounded-full"></div>
                </div>
                <div>
                    <h3 id="chatRoomName" class="text-lg font-semibold text-white">Atención Médica</h3>
                    <p id="chatStatus" class="text-sm text-blue-100">Conectando...</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <button id="minimizeChat" class="p-2 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                    </svg>
                </button>
                <button id="closeChat" onclick="endChat()" class="p-2 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Patient Info Bar (if needed) -->
    <div id="patientInfoBar" class="bg-blue-50 px-6 py-3 border-b border-gray-200 hidden">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div>
                    <p id="patientName" class="text-sm font-medium text-gray-900">Paciente</p>
                    <p id="patientId" class="text-xs text-gray-500">ID: ---</p>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                <span id="chatTimer">00:00</span>
            </div>
        </div>
    </div>

    <!-- Messages Container -->
    <div id="chatMessages" class="h-96 overflow-y-auto px-4 py-4 space-y-4 bg-gray-50">
        <!-- Welcome Message -->
        <div class="flex justify-center">
            <div class="bg-white px-4 py-2 rounded-full shadow-sm border border-gray-200">
                <p class="text-sm text-gray-600">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Chat seguro y encriptado
                </p>
            </div>
        </div>
        
        <!-- Initial System Message -->
        <div class="flex justify-start">
            <div class="max-w-xs lg:max-w-md">
                <div class="bg-white rounded-2xl px-4 py-3 shadow-sm border border-gray-200">
                    <div class="flex items-center space-x-2 mb-2">
                        <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Sistema Médico</span>
                    </div>
                    <p class="text-sm text-gray-700">
                        Hola, bienvenido a nuestro sistema de atención médica. Un profesional de la salud se conectará contigo en breve. ¿En qué podemos ayudarte hoy?
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span id="systemMessageTime">Ahora</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Typing Indicator -->
    <div id="typingIndicator" class="hidden px-4 py-2">
        <div class="flex justify-start">
            <div class="bg-white rounded-2xl px-4 py-3 shadow-sm border border-gray-200">
                <div class="flex items-center space-x-2">
                    <div class="flex space-x-1">
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                    <span class="text-sm text-gray-500">Escribiendo...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Input Area -->
    <div class="bg-white border-t border-gray-200 p-4">
        <!-- File Upload Area (hidden by default) -->
        <div id="fileUploadArea" class="hidden mb-3 p-3 border-2 border-dashed border-gray-300 rounded-lg text-center">
            <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            <p class="text-sm text-gray-600">Arrastra archivos aquí o haz clic para seleccionar</p>
            <input type="file" id="fileInput" class="hidden" multiple accept="image/*,.pdf,.doc,.docx">
        </div>

        <!-- Emoji Picker -->
        <div id="emojiPicker" class="hidden mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200 max-h-32 overflow-y-auto">
            <div class="grid grid-cols-8 gap-2">
                <!-- Emojis médicos y generales -->
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="😊">😊</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="😢">😢</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="😟">😟</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="😷">😷</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="🤒">🤒</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="🩺">🩺</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="💊">💊</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="🏥">🏥</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="👨‍⚕️">👨‍⚕️</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="👩‍⚕️">👩‍⚕️</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="❤️">❤️</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="👍">👍</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="👎">👎</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="🙏">🙏</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="💉">💉</button>
                <button class="emoji-btn p-2 hover:bg-gray-200 rounded text-lg" data-emoji="🩹">🩹</button>
            </div>
        </div>

        <!-- Input Controls -->
        <div class="flex items-end space-x-2">
            <div class="flex-1">
                <div class="relative">
                    <textarea 
                        id="messageInput" 
                        placeholder="Escribe tu mensaje aquí... (máximo 500 caracteres)"
                        maxlength="500"
                        rows="1"
                        class="block w-full px-4 py-3 pr-12 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        style="min-height: 48px; max-height: 120px;"
                    ></textarea>
                    <div class="absolute bottom-2 right-2 text-xs text-gray-400">
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
                    class="p-3 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-colors"
                    title="Agregar emoji"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>

                <!-- File Upload Button -->
                <button 
                    id="fileButton" 
                    type="button"
                    class="p-3 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-colors"
                    title="Adjuntar archivo"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                </button>

                <!-- Send Button -->
                <button 
                    id="sendButton" 
                    type="button"
                    onclick="sendMessage()"
                    disabled
                    class="p-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                    title="Enviar mensaje"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-3 flex flex-wrap gap-2">
            <button class="quick-action-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors">
                🩺 Consulta general
            </button>
            <button class="quick-action-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors">
                💊 Medicamentos
            </button>
            <button class="quick-action-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors">
                📋 Resultados
            </button>
            <button class="quick-action-btn px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition-colors">
                📅 Cita
            </button>
        </div>
    </div>

    <!-- Chat Footer -->
    <div class="bg-gray-50 px-4 py-2 border-t border-gray-200">
        <div class="flex items-center justify-between text-xs text-gray-500">
            <div class="flex items-center space-x-4">
                <span class="flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Conexión segura
                </span>
                <span id="connectionStatus" class="flex items-center">
                    <div class="w-2 h-2 bg-green-400 rounded-full mr-1"></div>
                    En línea
                </span>
            </div>
            <div class="flex items-center space-x-2">
                <button class="hover:text-gray-700 transition-colors" title="Ayuda">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>
                <button class="hover:text-gray-700 transition-colors" title="Configuración">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-resize textarea
document.getElementById('messageInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
    
    // Update character count
    const charCount = this.value.length;
    document.getElementById('charCount').textContent = charCount;
    
    // Enable/disable send button
    const sendButton = document.getElementById('sendButton');
    sendButton.disabled = charCount === 0;
});

// Emoji functionality
document.getElementById('emojiButton').addEventListener('click', function() {
    const emojiPicker = document.getElementById('emojiPicker');
    emojiPicker.classList.toggle('hidden');
});

// Emoji selection
document.querySelectorAll('.emoji-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const emoji = this.dataset.emoji;
        const messageInput = document.getElementById('messageInput');
        const cursorPos = messageInput.selectionStart;
        const textBefore = messageInput.value.substring(0, cursorPos);
        const textAfter = messageInput.value.substring(cursorPos);
        
        messageInput.value = textBefore + emoji + textAfter;
        messageInput.focus();
        messageInput.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
        
        // Trigger input event to update char count
        messageInput.dispatchEvent(new Event('input'));
        
        // Hide emoji picker
        document.getElementById('emojiPicker').classList.add('hidden');
    });
});

// File upload functionality
document.getElementById('fileButton').addEventListener('click', function() {
    document.getElementById('fileInput').click();
});

document.getElementById('fileInput').addEventListener('change', function() {
    const files = this.files;
    if (files.length > 0) {
        // Handle file upload
        for (let file of files) {
            if (file.size > 5 * 1024 * 1024) { // 5MB limit
                alert('El archivo ' + file.name + ' es demasiado grande. Máximo 5MB.');
                continue;
            }
            // Process file upload
            console.log('Uploading file:', file.name);
        }
    }
});

// Quick actions
document.querySelectorAll('.quick-action-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const messageInput = document.getElementById('messageInput');
        messageInput.value = this.textContent.trim();
        messageInput.focus();
        messageInput.dispatchEvent(new Event('input'));
    });
});

// Enter to send (Shift+Enter for new line)
document.getElementById('messageInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (!document.getElementById('sendButton').disabled) {
            sendMessage();
        }
    }
});

// Hide emoji picker when clicking outside
document.addEventListener('click', function(e) {
    const emojiPicker = document.getElementById('emojiPicker');
    const emojiButton = document.getElementById('emojiButton');
    
    if (!emojiPicker.contains(e.target) && !emojiButton.contains(e.target)) {
        emojiPicker.classList.add('hidden');
    }
});
</script>