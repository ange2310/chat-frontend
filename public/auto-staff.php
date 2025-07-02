<?php
// public/auto-staff.php - Entrada automática a staff con sincronización
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accediendo al Panel...</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-900 to-blue-800 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4">
        
        <!-- Loading Animation -->
        <div class="text-center">
            <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Sincronizando Sesión</h1>
            <p id="statusMessage" class="text-gray-600 mb-6">Verificando credenciales...</p>
            
            <div class="bg-gray-100 rounded-lg p-4">
                <div id="progressSteps" class="space-y-2 text-sm text-left">
                    <div id="step1" class="flex items-center">
                        <div class="w-4 h-4 border-2 border-blue-600 rounded-full mr-3 animate-pulse"></div>
                        <span>Verificando localStorage...</span>
                    </div>
                    <div id="step2" class="flex items-center opacity-50">
                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full mr-3"></div>
                        <span>Validando token...</span>
                    </div>
                    <div id="step3" class="flex items-center opacity-50">
                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full mr-3"></div>
                        <span>Sincronizando con PHP...</span>
                    </div>
                    <div id="step4" class="flex items-center opacity-50">
                        <div class="w-4 h-4 border-2 border-gray-300 rounded-full mr-3"></div>
                        <span>Redirigiendo a panel...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function syncSessionAndRedirect() {
            try {
                // Step 1: Verificar localStorage
                updateStep(1, 'checking');
                await delay(500);
                
                const token = localStorage.getItem('pToken');
                const userStr = localStorage.getItem('user');
                
                if (!token || !userStr) {
                    throw new Error('No hay datos de sesión en localStorage');
                }
                
                const user = JSON.parse(userStr);
                updateStep(1, 'success');
                console.log('✅ Datos encontrados en localStorage');
                
                // Step 2: Validar token
                updateStep(2, 'checking');
                updateStatus('Validando token con servidor...');
                await delay(500);
                
                // Step 3: Sincronizar con PHP
                updateStep(3, 'checking');
                updateStatus('Sincronizando sesión PHP...');
                
                const syncResponse = await fetch('/practicas/chat-frontend/public/sync-session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        token: token,
                        user: user
                    })
                });
                
                const syncResult = await syncResponse.json();
                console.log('📡 Sync result:', syncResult);
                
                if (!syncResult.success) {
                    throw new Error(syncResult.error || 'Error sincronizando sesión');
                }
                
                updateStep(2, 'success');
                updateStep(3, 'success');
                console.log('✅ Sesión sincronizada correctamente');
                
                // Step 4: Redirigir
                updateStep(4, 'checking');
                updateStatus('¡Sesión sincronizada! Redirigiendo...');
                await delay(1000);
                
                updateStep(4, 'success');
                console.log('🎯 Redirigiendo a staff.php');
                
                // Redirección final
                window.location.href = '/practicas/chat-frontend/public/staff.php';
                
            } catch (error) {
                console.error('❌ Error en sincronización:', error);
                
                // Marcar pasos como fallidos
                updateStep(1, 'error');
                updateStep(2, 'error');
                updateStep(3, 'error');
                updateStep(4, 'error');
                
                updateStatus('Error: ' + error.message);
                
                // Ofrecer alternativas
                setTimeout(() => {
                    if (confirm('Error sincronizando sesión. ¿Quieres ir al login para intentar de nuevo?')) {
                        // Limpiar localStorage y ir al login
                        localStorage.clear();
                        window.location.href = '/practicas/chat-frontend/public/index.php';
                    }
                }, 2000);
            }
        }
        
        function updateStep(stepNumber, status) {
            const step = document.getElementById(`step${stepNumber}`);
            const circle = step.querySelector('div');
            const text = step.querySelector('span');
            
            step.classList.remove('opacity-50');
            
            if (status === 'checking') {
                circle.className = 'w-4 h-4 border-2 border-blue-600 rounded-full mr-3 animate-pulse';
                circle.style.backgroundColor = '';
            } else if (status === 'success') {
                circle.className = 'w-4 h-4 bg-green-500 rounded-full mr-3 flex items-center justify-center';
                circle.innerHTML = '<svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>';
            } else if (status === 'error') {
                circle.className = 'w-4 h-4 bg-red-500 rounded-full mr-3 flex items-center justify-center';
                circle.innerHTML = '<svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/></svg>';
            }
        }
        
        function updateStatus(message) {
            document.getElementById('statusMessage').textContent = message;
        }
        
        function delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
        
        // Iniciar proceso automáticamente
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🚀 Iniciando sincronización automática');
            syncSessionAndRedirect();
        });
    </script>
</body>
</html>