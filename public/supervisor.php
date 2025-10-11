<?php
session_start();

if (!isset($_SESSION['staffJWT']) || empty($_SESSION['staffJWT'])) {
    header("Location: index.php?error=no_session");
    exit;
}

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: index.php?error=no_user");
    exit;
}

$user = json_decode($_SESSION['user'], true);
if (!$user) {
    header("Location: index.php?error=invalid_user");
    exit;
}

$userRole = $user['role']['name'] ?? $user['role'] ?? 'agent';
if (is_numeric($userRole)) {
    $roleMap = [1 => 'patient', 2 => 'agent', 3 => 'supervisor', 4 => 'admin'];
    $userRole = $roleMap[$userRole] ?? 'agent';
}

if (!in_array($userRole, ['supervisor', 'admin'])) {
    header("Location: index.php?error=not_supervisor");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Supervisor - <?= htmlspecialchars($user['name'] ?? 'Supervisor') ?></title>
    
    <meta name="supervisor-token" content="<?= $_SESSION['staffJWT'] ?>">
    <meta name="supervisor-user" content='<?= json_encode($user) ?>'>
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/supervisor-styles.css" rel="stylesheet">
</head>
<body class="h-full bg-gray-50">
    <!-- Mobile Navigation Backdrop -->
    <div class="mobile-nav-backdrop lg:hidden" id="mobileNavBackdrop" onclick="closeMobileNav()"></div>
    
    <!-- Patient Info Backdrop -->
    <div class="patient-info-backdrop" id="patientInfoBackdrop" onclick="closePatientInfoSidebar()"></div>

    
    <div class="main-container min-h-full flex">
        
        <!-- Desktop Sidebar -->
        <div class="hidden lg:flex w-64 bg-white border-r border-gray-200 flex-col h-screen fixed left-0 top-0 z-30">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Supervisor</h1>
                        <p class="text-sm text-gray-500">Control de Calidad</p>
                    </div>
                </div>
            </div>
            
            <nav class="flex-1 p-4">
                <div class="space-y-1" id="desktopNav">
                    <a href="#transfers" onclick="showSection('transfers'); closeMobileNav();" 
                       id="nav-transfers" class="nav-link active">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        Transferencias (RF4)
                        <span id="transfersCount" class="ml-auto px-2 py-1 bg-red-500 text-white text-xs rounded-full hidden">0</span>
                    </a>
                    
                    <a href="#escalations" onclick="showSection('escalations'); closeMobileNav();" 
                       id="nav-escalations" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        Escalaciones (RF5)
                        <span id="escalationsCount" class="ml-auto px-2 py-1 bg-orange-500 text-white text-xs rounded-full hidden">0</span>
                    </a>
                    
                    <a href="#analysis" onclick="showSection('analysis'); closeMobileNav();" 
                       id="nav-analysis" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Análisis RF6
                    </a>
                </div>
            </nav>
                
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-semibold">
                        <?= strtoupper(substr($user['name'] ?? 'S', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($user['name'] ?? 'Supervisor') ?></p>
                        <p class="text-sm text-gray-500 capitalize"><?= htmlspecialchars($userRole) ?></p>
                    </div>
                    <button onclick="logout()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg" title="Cerrar sesión">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Sidebar -->
        <div class="mobile-nav fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col lg:hidden" id="mobileNav">
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="font-semibold text-gray-900 text-sm">Supervisor</h1>
                        </div>
                    </div>
                    <button onclick="closeMobileNav()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <nav class="flex-1 p-4">
                <div class="space-y-1" id="mobileNavItems">
                    <a href="#transfers" onclick="showSection('transfers'); closeMobileNav();" 
                       id="mobile-nav-transfers" class="nav-link active">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        Transferencias (RF4)
                        <span id="mobileTransfersCount" class="ml-auto px-2 py-1 bg-red-500 text-white text-xs rounded-full hidden">0</span>
                    </a>
                    
                    <a href="#escalations" onclick="showSection('escalations'); closeMobileNav();" 
                       id="mobile-nav-escalations" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        Escalaciones (RF5)
                        <span id="mobileEscalationsCount" class="ml-auto px-2 py-1 bg-orange-500 text-white text-xs rounded-full hidden">0</span>
                    </a>
                    
                    <a href="#analysis" onclick="showSection('analysis'); closeMobileNav();" 
                       id="mobile-nav-analysis" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Análisis RF6
                    </a>
                </div>
            </nav>
                
            <div class="p-4 border-t border-gray-200">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-semibold text-sm">
                            <?= strtoupper(substr($user['name'] ?? 'S', 0, 1)) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate text-sm"><?= htmlspecialchars($user['name'] ?? 'Supervisor') ?></p>
                            <p class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($userRole) ?></p>
                        </div>
                        <button onclick="logout()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg" title="Cerrar sesión">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>


        <div class="content-main flex-1 flex flex-col ml-64">
            
            <!-- Header -->
            <header class="bg-white border-b border-gray-200">
                <div class="px-4 sm:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <!-- Mobile Menu Button -->
                            <button onclick="openMobileNav()" class="lg:hidden p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                            </button>
                            <h2 id="sectionTitle" class="text-lg sm:text-xl font-semibold text-gray-900">Transferencias Pendientes</h2>
                        </div>
                        <div class="flex items-center gap-2 sm:gap-4">
                            <!-- Patient Info Button - Only visible during supervisor chat and in desktop -->
                            <button id="patientInfoButton" onclick="openPatientInfoSidebar()"
                                    class="flex p-2 text-gray-400 hover:text-gray-600 rounded-lg" 
                                    title="Información del Paciente">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </button>
                            <span id="currentTime" class="text-xs sm:text-sm text-gray-500"></span>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span class="text-xs sm:text-sm text-gray-600 hidden sm:inline">En línea</span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-auto">
                
                <div id="transfers-section" class="section-content p-6">
                    <div class="content-card">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Solicitudes de Transferencia (RF4)</h3>
                                    <p class="text-sm text-gray-600 mt-1">Agentes solicitan autorización para transferir a otra sala</p>
                                </div>
                                <button onclick="supervisorClient.loadTransfers()" 
                                        class="btn btn-primary">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="transfersContainer">
                                <div class="empty-state">
                                    <div class="loading-spinner mx-auto mb-4"></div>
                                    <p>Cargando transferencias...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="escalations-section" class="section-content hidden p-6">
                    <div class="content-card">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Escalaciones Automáticas (RF5)</h3>
                                    <p class="text-sm text-gray-600 mt-1">Tras 3+ transferencias fallidas, el sistema alerta al supervisor</p>
                                </div>
                                <button onclick="supervisorClient.loadEscalations()" 
                                        class="btn btn-primary">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="escalationsContainer">
                                <div class="empty-state">
                                    <div class="loading-spinner mx-auto mb-4"></div>
                                    <p>Cargando escalaciones...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="analysis-section" class="section-content hidden p-6">
                    <div class="content-card">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Análisis de Mal Direccionamiento (RF6)</h3>
                                    <p class="text-sm text-gray-600 mt-1">Detectar conversaciones mal dirigidas por estadística de saltos</p>
                                </div>
                                <button onclick="supervisorClient.runMisdirectionAnalysis()" 
                                        class="btn btn-primary">
                                    Ejecutar Análisis
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="analysisContainer">
                                <div class="empty-state">
                                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    <p>Haz clic en "Ejecutar Análisis" para iniciar el análisis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="supervisor-chat-section" class="section-content hidden">
                    <div class="chat-container">
                        <div class="flex h-full">
                            
                            <div class="flex-1 flex flex-col bg-white">
                                
                                <div class="chat-header bg-white border-b border-gray-200 px-6 py-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <button onclick="exitSupervisorChat()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                                </svg>
                                            </button>
                                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                                <span id="supervisorChatPatientInitials" class="text-lg font-semibold text-purple-700">P</span>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h2 id="supervisorChatPatientName" class="text-xl font-bold text-gray-900">Paciente</h2>
                                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded-full">
                                                        Supervisión Activa
                                                    </span>
                                                </div>
                                                <p class="text-sm text-gray-500">
                                                    <span id="supervisorChatRoomName">Sala</span> • 
                                                    <span class="text-purple-600">Tomado por Supervisor</span>
                                                    <span id="supervisorChatTimer" class="timer-display ml-2"></span>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-2">
                                            <button onclick="showSupervisorEndModal()" 
                                                    class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                                Finalizar Supervisión
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="chat-messages-container">
                                    <div class="chat-messages auto-scroll" id="supervisorChatMessages">
                                        <div class="text-center py-8 text-gray-500">
                                            Cargando historial de chat...
                                        </div>
                                    </div>
                                    
                                    <div id="supervisorTypingIndicator" class="typing-indicator-area hidden px-6 py-2">
                                        <div class="flex items-center space-x-2 text-sm text-gray-500">
                                            <span>El paciente está escribiendo</span>
                                            <div class="typing-dots">
                                                <div class="typing-dot"></div>
                                                <div class="typing-dot"></div>
                                                <div class="typing-dot"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="chat-input-area p-4">
                                    <div class="flex items-end gap-3">
                                        <div class="flex-1">
                                            <textarea 
                                                id="supervisorMessageInput" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 resize-none"
                                                rows="2"
                                                placeholder="Escribe como supervisor..."
                                                maxlength="500"
                                                onkeydown="handleSupervisorKeyDown(event)"
                                            ></textarea>
                                        </div>
                                        <button 
                                            id="supervisorSendButton"
                                            onclick="sendSupervisorMessage()" 
                                            disabled
                                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex justify-between items-center mt-2 text-xs text-gray-500">
                                        <span>Enter para enviar, Shift+Enter para nueva línea</span>
                                        <span id="supervisorChatStatus">Desconectado</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Patient Info Sidebar Optimizada -->
                            <div class="patient-info-sidebar bg-gray-50 border-l border-gray-200 overflow-y-auto patient-info-mobile" id="patientInfoSidebar">
                                <div class="patient-info-content">
                                    <!-- Mobile close button -->
                                    <div class="flex items-center justify-between mb-4 lg:hidden">
                                        <h3 class="text-lg font-semibold text-gray-900">Información del Paciente</h3>
                                        <button onclick="closePatientInfoSidebar()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    <!-- Desktop title -->
                                    <div class="hidden lg:block mb-4">
                                        <h3 class="text-lg font-semibold text-gray-900">Información del Paciente</h3>
                                    </div>
                                    
                                    <!-- Patient info content -->
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Nombre</label>
                                            <p id="supervisorPatientInfoName" class="text-sm text-gray-900 font-medium">-</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Documento</label>
                                            <p id="supervisorPatientInfoDocument" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Teléfono</label>
                                            <p id="supervisorPatientInfoPhone" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Email</label>
                                            <p id="supervisorPatientInfoEmail" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Ciudad</label>
                                            <p id="supervisorPatientInfoCity" class="text-sm text-gray-900">-</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="patient-info-content border-t border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Membresía</h3>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">EPS</label>
                                            <p id="supervisorPatientInfoEPS" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Plan</label>
                                            <p id="supervisorPatientInfoPlan" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Estado</label>
                                            <p id="supervisorPatientInfoStatus" class="text-sm text-gray-900">-</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Tomador</label>
                                            <p id="supervisorPatientInfoTomador" class="text-sm text-gray-900">-</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="patient-info-content border-t border-gray-200">
                                    <div class="bg-purple-50 rounded-lg p-3 mb-3">
                                        <div class="text-xs text-purple-600 font-medium mb-1">ID de Sesión</div>
                                        <div id="supervisorChatSessionId" class="text-xs font-mono text-purple-800">-</div>
                                    </div>
                                    
                                    <div class="bg-yellow-50 rounded-lg p-3">
                                        <div class="text-xs text-yellow-600 font-medium mb-1">Estado de Escalación</div>
                                        <div id="supervisorEscalationStatus" class="text-xs font-medium text-yellow-800">Tomado por Supervisor</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="assignAgentModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 shadow-xl max-w-md w-full mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Asignar Agente</h3>
                <button onclick="closeModal('assignAgentModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar Agente</label>
                    <div class="dropdown">
                        <button id="agentDropdownBtn" onclick="toggleAgentDropdown()" 
                                class="w-full px-4 py-2 text-left bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 flex items-center justify-between">
                            <span id="selectedAgentText" class="text-gray-500">Seleccionar agente...</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="agentDropdownContent" class="dropdown-content">
                            <div class="dropdown-item">
                                <div class="loading-spinner w-4 h-4 mr-2"></div>
                                <span>Cargando agentes...</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo (Opcional)</label>
                    <textarea id="assignmentReason" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500"
                              placeholder="Razón de la asignación..."></textarea>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('assignAgentModal')" 
                        class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Cancelar
                </button>
                <button id="confirmAssignBtn" onclick="confirmAgentAssignment()" 
                        class="flex-1 btn btn-success">
                    Asignar
                </button>
            </div>
        </div>
    </div>

    <div id="supervisorEndModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 shadow-xl max-w-md w-full mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Finalizar Supervisión</h3>
                <button onclick="closeModal('supervisorEndModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo</label>
                    <select id="supervisorEndReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="resolved_by_supervisor">Problema resuelto por supervisor</option>
                        <option value="escalation_completed">Escalación completada</option>
                        <option value="referred_to_agent">Referido a agente especializado</option>
                        <option value="patient_disconnected">Paciente desconectado</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notas de Supervisión</label>
                    <textarea id="supervisorEndNotes" class="w-full px-3 py-2 border border-gray-300 rounded-lg" rows="3" 
                              placeholder="Resumen de la supervisión y acciones tomadas..."></textarea>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('supervisorEndModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg">
                    Cancelar
                </button>
                <button onclick="executeSupervisorEnd()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Finalizar
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <script src="assets/js/supervisor-app.js"></script>
</body>
</html>