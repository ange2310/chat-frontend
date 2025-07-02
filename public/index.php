<?php
// public/index.php - Login simple
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$auth = auth();

// Si ya está autenticado, redirigir
if ($auth->isAuthenticated() && $auth->isStaff()) {
    header("Location: /practicas/chat-frontend/public/staff.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Médico - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-900">
    
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto h-12 w-12 bg-white rounded-xl flex items-center justify-center mb-4 shadow-lg">
                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-white">Portal Médico</h2>
                <p class="text-blue-100 mt-2">Acceso al sistema</p>
            </div>

            <!-- Login Form -->
            <div class="bg-white rounded-xl shadow-xl p-8">
                <form id="loginForm" class="space-y-6">
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                            Email
                        </label>
                        <input 
                            id="email" 
                            type="email" 
                            required
                            autocomplete="email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="tu@email.com"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            Contraseña
                        </label>
                        <input 
                            id="password" 
                            type="password" 
                            required
                            autocomplete="current-password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Tu contraseña"
                        >
                    </div>

                    <button 
                        type="submit" 
                        id="submitBtn"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium transition-colors disabled:opacity-50"
                    >
                        <span id="normalText">Iniciar Sesión</span>
                        <span id="loadingText" class="hidden">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Iniciando...
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Simple AuthClient
        class SimpleAuth {
            constructor() {
                this.baseURL = 'http://187.33.158.246:8080/auth';
            }

            async login(email, password) {
                try {
                    const response = await fetch(`${this.baseURL}/login`, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ email, password })
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        localStorage.setItem('pToken', result.data.access_token);
                        localStorage.setItem('user', JSON.stringify(result.data.user));
                        return { success: true, data: result.data };
                    } else {
                        return { success: false, error: result.message || 'Error en login' };
                    }
                } catch (error) {
                    return { success: false, error: 'Error de conexión' };
                }
            }
        }

        // Initialize
        const auth = new SimpleAuth();

        // Form handler
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                alert('Complete todos los campos');
                return;
            }
            
            // Show loading
            const submitBtn = document.getElementById('submitBtn');
            const normalText = document.getElementById('normalText');
            const loadingText = document.getElementById('loadingText');
            
            submitBtn.disabled = true;
            normalText.classList.add('hidden');
            loadingText.classList.remove('hidden');
            
            try {
                const result = await auth.login(email, password);
                
                if (result.success) {
                    alert('Login exitoso');
                    window.location.href = '/practicas/chat-frontend/public/staff.php';
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Error de conexión');
            } finally {
                submitBtn.disabled = false;
                normalText.classList.remove('hidden');
                loadingText.classList.add('hidden');
            }
        });

        // Redirect if already logged in
        if (localStorage.getItem('pToken') && localStorage.getItem('user')) {
            window.location.href = '/practicas/chat-frontend/public/staff.php';
        }
    </script>
</body>
</html>