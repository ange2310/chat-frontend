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
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* ============== NAVEGACIÓN BASE ============== */
        .nav-link.active { 
            background: #7c3aed; 
            color: white; 
        }
        .nav-link { 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
            padding: 0.75rem 1rem; 
            font-size: 0.875rem; 
            font-weight: 500; 
            color: #6b7280; 
            text-decoration: none; 
            border-radius: 0.5rem; 
            transition: all 0.15s ease-in-out; 
        }
        .nav-link:hover { 
            background: #f3f4f6; 
            color: #1f2937; 
        }
        
        /* ============== ELEMENTOS DE SUPERVISOR ============== */
        .content-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .transfer-card, .escalation-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            border: 1px solid #e5e7eb;
            transition: transform 0.15s ease;
        }
        .transfer-card:hover, .escalation-card:hover {
            transform: translateY(-1px);
        }
        
        .priority-high { border-left: 4px solid #dc2626; }
        .priority-medium { border-left: 4px solid #f59e0b; }
        .priority-low { border-left: 4px solid #10b981; }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 0.875rem;
        }
        
        .btn-primary { background: #7c3aed; color: white; }
        .btn-primary:hover { background: #6d28d9; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        
        .loading-spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #7c3aed;
            border-radius: 50%;
            width: 2rem;
            height: 2rem;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        /* ============== CHAT ELEMENTS ============== */
        .chat-container {
            height: 100vh;
            display: flex;
            flex-direction: row;
            position: relative;
        }
        
        .chat-header {
            flex-shrink: 0;
            min-height: 80px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
        }
        
        .chat-messages-container {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background-color: #f9fafb;
            min-height: 300px;
            max-height: calc(100vh - 250px);
        }
        
        .chat-input-area {
            flex-shrink: 0;
            min-height: 120px;
            background: white;
            border-top: 1px solid #e5e7eb;
            padding: 1rem;
        }
        
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .chat-messages::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        .chat-message {
            margin-bottom: 1rem;
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .typing-dots { display: flex; gap: 4px; }
        .typing-dot {
            width: 6px; height: 6px; background: #9ca3af; border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }

        .timer-display {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-taken { background: #dbeafe; color: #1e40af; }
        .status-urgent { background: #fecaca; color: #991b1b; }
        .status-high { background: #fecaca; color: #991b1b; }
        .status-medium { background: #fef3c7; color: #92400e; }
        .status-low { background: #d1fae5; color: #065f46; }
        
        .mobile-nav-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-nav-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .mobile-nav {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .mobile-nav.active {
            transform: translateX(0);
        }

        /* Patient Info Sidebar Móvil */
        .patient-info-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .patient-info-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .patient-info-mobile {
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }

        .patient-info-mobile.active {
            transform: translateX(0);
        }

        /* ============== BOTÓN FLOTANTE REPOSICIONADO ============== */
        .patient-info-floating-btn {
            position: fixed;
            bottom: 120px; /* Cambiado de 20px a 120px para que no estorbe el input del chat */
            left: 20px;    /* Cambiado de right a left para moverlo al lado izquierdo */
            z-index: 45;
            background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            box-shadow: 0 4px 16px rgba(124, 58, 237, 0.4), 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: none;
            align-items: center;
            justify-content: center;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        .patient-info-floating-btn:hover {
            background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%);
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.5), 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .patient-info-floating-btn:active {
            transform: scale(0.95);
            transition: all 0.1s ease;
        }

        .patient-info-floating-btn.show {
            display: flex;
            animation: slideInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .patient-info-floating-btn svg {
            width: 24px;
            height: 24px;
        }

        /* Indicador/badge del botón */
        .patient-info-floating-btn .indicator {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 20px;
            height: 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            color: white;
            animation: pulse 2s infinite;
        }

        /* Animaciones */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(100px) scale(0.8);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }

        /* ============== SIDEBAR DE INFORMACIÓN DEL PACIENTE OPTIMIZADA ============== */
        .patient-info-sidebar {
            width: 280px;        /* Reducido de 320px a 280px para dar más espacio al chat */
            min-width: 280px;    /* Reducido de 320px a 280px */
            max-width: 280px;    /* Nuevo: Fijar el ancho máximo */
        }

        /* Responsive Layout Mejorado */
        @media (max-width: 1024px) {
            .patient-info-sidebar {
                position: fixed;
                right: 0;
                top: 0;
                height: 100vh;
                z-index: 50;
                width: 300px;  /* En móvil mantener un poco más de ancho */
                min-width: 300px;
                max-width: 300px;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }
            
            .sidebar-main {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 50;
            }
            
            .content-main {
                width: 100%;
            }
            
            .chat-header {
                padding: 1rem;
                min-height: 70px;
            }
            
            .chat-messages {
                padding: 1rem;
            }
            
            .chat-input-area {
                padding: 1rem;
                min-height: 100px;
            }
            
            .transfer-card, .escalation-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .btn {
                padding: 0.625rem 1rem;
                font-size: 0.875rem;
            }
            
            .patient-info-sidebar {
                width: 100vw;
                min-width: 100vw;
                max-width: 100vw;
            }

            /* Ajustes para el botón flotante en móvil */
            .patient-info-floating-btn {
                bottom: 110px;  /* Ajustado para móvil */
                left: 16px;     /* Ajustado para móvil */
                width: 52px;
                height: 52px;
            }
            
            .patient-info-floating-btn svg {
                width: 22px;
                height: 22px;
            }
            
            .patient-info-floating-btn .indicator {
                width: 18px;
                height: 18px;
                font-size: 9px;
            }
        }

        @media (max-width: 640px) {
            .stats-card {
                padding: 1rem;
            }
            
            .btn {
                font-size: 0.75rem;
                padding: 0.5rem 0.75rem;
            }
            
            .btn-responsive {
                font-size: 0.75rem !important;
                padding: 0.375rem 0.75rem !important;
            }
            
            .btn-icon-only {
                padding: 0.375rem !important;
            }

            /* Botón flotante aún más pequeño en pantallas muy pequeñas */
            .patient-info-floating-btn {
                width: 48px;
                height: 48px;
                bottom: 100px;
                left: 12px;
            }
            
            .patient-info-floating-btn svg {
                width: 20px;
                height: 20px;
            }
        }

        @media (max-width: 480px) {
            .chat-header {
                padding: 0.75rem;
                min-height: 65px;
            }
            
            .chat-header h2 {
                font-size: 1rem;
            }
            
            .transfer-card, .escalation-card {
                padding: 0.875rem;
            }

            /* Botón flotante en pantallas muy pequeñas */
            .patient-info-floating-btn {
                bottom: 90px;
                left: 10px;
            }
        }

        /* Evitar que el botón interfiera con el sidebar en desktop */
        @media (min-width: 1025px) {
            .patient-info-floating-btn {
                display: none !important; /* Ocultar en desktop ya que hay botón en header */
            }
        }

        /* Estados especiales del botón */
        .patient-info-floating-btn.closing {
            animation: fadeOut 0.3s ease-out forwards;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: scale(1);
            }
            to {
                opacity: 0;
                transform: scale(0.8) translateY(20px);
            }
        }

        /* Mejorar la legibilidad del botón sobre diferentes fondos */
        .patient-info-floating-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: inherit;
            z-index: -1;
            filter: blur(8px);
            opacity: 0.3;
        }

        /* ============== OPTIMIZACIÓN DEL CONTENIDO DE LA SIDEBAR ============== */
        .patient-info-content {
            padding: 1.25rem; /* Reducido de 1.5rem a 1.25rem */
        }

        .patient-info-content .space-y-3 > div {
            margin-bottom: 0.75rem; /* Espaciado más compacto */
        }

        .patient-info-content h3 {
            margin-bottom: 1rem; /* Reducido el margen inferior de los títulos */
        }

        .patient-info-content label {
            font-size: 0.75rem; /* Etiquetas más pequeñas */
            margin-bottom: 0.25rem;
        }

        .patient-info-content p {
            font-size: 0.875rem; /* Texto más compacto */
            line-height: 1.25;
        }

        /* Dropdown para agentes */
        .dropdown {
            position: relative;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.15s ease;
        }

        .dropdown-item:hover {
            background-color: #f9fafb;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body class="h-full bg-gray-50">
    <!-- Mobile Navigation Backdrop -->
    <div class="mobile-nav-backdrop lg:hidden" id="mobileNavBackdrop" onclick="closeMobileNav()"></div>
    
    <!-- Patient Info Backdrop -->
    <div class="patient-info-backdrop" id="patientInfoBackdrop" onclick="closePatientInfoSidebar()"></div>
    
    <!-- Patient Info Floating Button -->
    <button id="patientInfoFloatingBtn" 
            class="patient-info-floating-btn" 
            onclick="togglePatientInfoSidebar()"
            title="Información del Paciente">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
        </svg>
        <div class="indicator">
            <span>i</span>
        </div>
    </button>
    
    <div class="main-container min-h-full flex">
        
        <!-- Desktop Sidebar -->
        <div class="hidden lg:flex w-64 bg-white border-r border-gray-200 flex-col">
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
                </div>
            </div>
        </div>

        <div class="content-main flex-1 flex flex-col">
            
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
                                    class="hidden lg:flex p-2 text-gray-400 hover:text-gray-600 rounded-lg" 
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
    
    <script>
        const API_BASE = 'http://187.33.158.246';
        const CHAT_API = `${API_BASE}/chat`;
        const AUTH_API = `${API_BASE}/auth`;
        const FILE_API = `${API_BASE}/chat/files`;
        const SUPERVISOR_API = `${API_BASE}/supervisor`;

        let supervisorChatSocket = null;
        let currentSupervisorSession = null;
        let isSupervisorConnected = false;
        let supervisorSessionJoined = false;
        let supervisorIsTyping = false;
        let supervisorTypingTimer;
        let supervisorChatTimer = null;
        let supervisorTimerInterval = null;
        let sentMessages = new Set();
        let messageIdCounter = 0;
        let chatHistoryCache = new Map();

        class SupervisorClient {
            constructor() {
                this.supervisorServiceUrl = SUPERVISOR_API;
                this.currentSession = null;
                this.refreshInterval = null;
                this.refreshIntervalTime = 30000;
                this.currentTransfer = null;
                this.currentEscalation = null;
                this.selectedAgent = null;
            }

            getToken() {
                const phpTokenMeta = document.querySelector('meta[name="supervisor-token"]')?.content;
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
                    'Authorization': `Bearer ${token}`,
                    'x-supervisor-id': this.getCurrentUser()?.id || 'supervisor'
                };
            }

            getCurrentUser() {
                const userMeta = document.querySelector('meta[name="supervisor-user"]');
                if (userMeta && userMeta.getAttribute('content')) {
                    try {
                        return JSON.parse(userMeta.getAttribute('content'));
                    } catch (e) {
                        console.warn('Error parsing user data:', e);
                        return null;
                    }
                }
                return null;
            }

            getRoomDisplayName(roomId) {
                if (!roomId) return 'Sala General';
                
                const roomNames = {
                    'general': 'Consultas Generales',
                    'medical': 'Consultas Médicas',
                    'emergency': 'Emergencias',
                    'support': 'Soporte Técnico',
                    'billing': 'Facturación',
                    'pharmacy': 'Farmacia'
                };
                
                return roomNames[roomId] || roomId || 'Sala General';
            }

            
            // ✅ FUNCIONES DE EXTRACCIÓN DE DATOS DEL PACIENTE (CON DEBUGGING MEJORADO)
            extractPatientInfo(session) {
                console.log('🔍 === EXTRAYENDO INFORMACIÓN DEL PACIENTE ===');
                console.log('📊 Sesión para extraer:', session);
                
                let patientData = {};
                
                // 🔍 BÚSQUEDA EN MÚLTIPLES UBICACIONES
                if (session.patient_data && Object.keys(session.patient_data).length > 0) {
                    console.log('✅ Usando session.patient_data:', session.patient_data);
                    patientData = session.patient_data;
                } else if (session.user_data) {
                    try {
                        console.log('🔄 Intentando usar session.user_data:', session.user_data);
                        const userData = typeof session.user_data === 'string' 
                            ? JSON.parse(session.user_data) 
                            : session.user_data;
                        
                        if (userData && typeof userData === 'object') {
                            console.log('✅ userData parseado exitosamente:', userData);
                            patientData = userData;
                        } else {
                            console.log('⚠️ userData no es un objeto válido:', userData);
                        }
                    } catch (e) {
                        console.warn('❌ Error parseando user_data:', e);
                    }
                } else {
                    console.log('⚠️ No se encontraron patient_data ni user_data');
                }

                const extractedInfo = {
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

                console.log('📋 Información extraída:', extractedInfo);
                console.log('✅ === FIN EXTRACCIÓN DE INFORMACIÓN DEL PACIENTE ===');
                
                return extractedInfo;
            }

            async fetchPatientDataFromPToken(ptoken) {
                try {
                    console.log('🔍 Consultando información del paciente con ptoken (supervisor):', ptoken);
                    
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

                    const patientData = {
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
                    
                    console.log('✅ Información del paciente obtenida desde ptoken (supervisor):', patientData);
                    return patientData;

                } catch (error) {
                    console.error('❌ Error fetchPatientDataFromPToken (supervisor):', error);
                    throw error;
                }
            }

            async getPatientInfoWithPToken(session) {
                console.log('🔍 === INICIANDO OBTENCIÓN DE DATOS DEL PACIENTE ===');
                console.log('📊 Sesión recibida:', session);
                
                // 🥇 PRIMER INTENTO: Datos locales de la sesión
                console.log('1️⃣ Intentando extraer datos locales...');
                let patientInfo = this.extractPatientInfo(session);
                console.log('📊 Datos extraídos localmente:', patientInfo);
                console.log('❓ ¿Datos locales están vacíos?', this.isPatientInfoEmpty(patientInfo));
                
                // 🥈 SEGUNDO INTENTO: Consulta por ptoken (solo si datos están vacíos)
                if (this.isPatientInfoEmpty(patientInfo) && session.ptoken) {
                    try {
                        console.log('2️⃣ Datos locales vacíos, consultando con ptoken:', session.ptoken);
                        const ptokenData = await this.fetchPatientDataFromPToken(session.ptoken);
                        if (ptokenData) {
                            patientInfo = ptokenData;
                            console.log('✅ Información del paciente obtenida desde ptoken:', ptokenData);
                        }
                    } catch (error) {
                        console.error('❌ Error obteniendo datos del ptoken:', error);
                    }
                } else if (this.isPatientInfoEmpty(patientInfo) && !session.ptoken) {
                    console.log('⚠️ Datos locales vacíos Y no hay ptoken disponible');
                } else if (!this.isPatientInfoEmpty(patientInfo)) {
                    console.log('✅ Usando datos locales (no vacíos)');
                } else {
                    console.log('🔄 Sin ptoken, manteniendo datos locales');
                }
                
                console.log('📋 === INFORMACIÓN FINAL DEL PACIENTE ===');
                console.log('👤 Datos finales:', patientInfo);
                console.log('❓ ¿Datos finales están vacíos?', this.isPatientInfoEmpty(patientInfo));
                console.log('✅ === FIN OBTENCIÓN DE DATOS DEL PACIENTE ===');
                
                return patientInfo;
            }

            isPatientInfoEmpty(patientInfo) {
                const essentialFields = ['primer_nombre', 'primer_apellido', 'nombreCompleto', 'id', 'email'];
                const fieldValues = essentialFields.map(field => ({
                    field: field,
                    value: patientInfo[field],
                    isEmpty: !patientInfo[field]
                }));
                
                const isEmpty = essentialFields.every(field => !patientInfo[field]);
                
                console.log('🔍 Análisis de campos esenciales:', fieldValues);
                console.log('📊 ¿Información está vacía?', isEmpty);
                
                return isEmpty;
            }

            getPatientNameFromSession(session) {
                if (!session) return 'Paciente';
                
                const patientInfo = this.extractPatientInfo(session);
                
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

            getRoomNameFromSession(session) {
                if (!session) return 'Sala General';
                
                // 🔧 NUEVO: Primero intentar usar el room_name que viene del backend
                if (session.room_name && session.room_name.trim()) {
                    return session.room_name.trim();
                }
                
                // 🔧 NUEVO: Segundo intento, buscar en user_data si hay room_name guardado
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
                        'soporte_tecnico': 'Soporte Técnico'
                    };
                    
                    const roomIdString = String(roomId).toLowerCase().trim();
                    
                    if (roomNames[roomId]) {
                        return roomNames[roomId];
                    }
                    
                    if (roomNames[roomIdString]) {
                        return roomNames[roomIdString];
                    }
                    
                    for (const [key, value] of Object.entries(roomNames)) {
                        if (key.toLowerCase().includes(roomIdString) || roomIdString.includes(key.toLowerCase())) {
                            return value;
                        }
                    }
                    
                    // Para UUIDs, generar nombre descriptivo
                    if (this.isValidUUID(roomIdString)) {
                        console.warn(`UUID de sala sin mapeo: ${roomIdString}. Usando nombre genérico.`);
                        return 'Sala Especializada';
                    }
                    
                    const formattedName = String(roomId)
                        .replace(/_/g, ' ')
                        .replace(/-/g, ' ')
                        .split(' ')
                        .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                        .join(' ');
                    
                    return `Sala ${formattedName}`;
                }
                
                return 'Sala General';
            }

            isValidUUID(str) {
                const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
                return uuidRegex.test(str);
            }

            checkForNewItems(currentTransfers, currentEscalations) {
                if (this.lastTransferCount !== undefined && currentTransfers.length > this.lastTransferCount) {
                    const newCount = currentTransfers.length - this.lastTransferCount;
                    this.showNotification(`${newCount} nueva(s) transferencia(s) recibida(s)`, 'info');
                    this.playNotificationSound();
                }
                
                if (this.lastEscalationCount !== undefined && currentEscalations.length > this.lastEscalationCount) {
                    const newCount = currentEscalations.length - this.lastEscalationCount;
                    this.showNotification(`${newCount} nueva(s) escalación(es) recibida(s)`, 'warning');
                    this.playAlertSound();
                }
                
                this.lastTransferCount = currentTransfers.length;
                this.lastEscalationCount = currentEscalations.length;
            }

            playNotificationSound() {
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmklCEGH0fPRdSECCwAA');
                    audio.volume = 0.3;
                    audio.play().catch(() => {});
                } catch (e) {}
            }

            playAlertSound() {
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmklCEGH0fPRdSECCwAA');
                    audio.volume = 0.5;
                    audio.play().catch(() => {});
                } catch (e) {}
            }

            async loadTransfers() {
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/transfers/pending`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });

                    if (response.ok) {
                        const result = await response.json();
                        const transfers = result.data?.transfers || [];
                        
                        this.checkForNewItems(transfers, this.lastEscalationCount || 0);
                        
                        this.displayTransfers(transfers);
                        this.updateNavCounter('transfersCount', transfers.length);
                        return transfers;
                    } else {
                        throw new Error(`Error ${response.status}: ${response.statusText}`);
                    }
                } catch (error) {
                    console.error('Error loading transfers:', error);
                    this.showNotification('Error cargando transferencias: ' + error.message, 'error');
                    this.displayTransfers([]);
                    this.updateNavCounter('transfersCount', 0);
                }
            }

            async loadEscalations() {
                console.log('Cargando escalaciones desde:', `${this.supervisorServiceUrl}/escalations`);
                
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/escalations`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        console.log('Escalaciones recibidas:', result);
                        
                        const escalations = result.data?.escalations || [];
                        console.log('Escalaciones encontradas:', escalations.length);
                        
                        this.checkForNewItems(this.lastTransferCount || 0, escalations);
                        
                        this.displayEscalations(escalations);
                        this.updateNavCounter('escalationsCount', escalations.length);
                        return escalations;
                    } else {
                        throw new Error(`Error ${response.status}: ${response.statusText}`);
                    }
                } catch (error) {
                    console.error('Error loading escalations:', error);
                    this.showNotification('Error cargando escalaciones: ' + error.message, 'error');
                    this.displayEscalations([]);
                    this.updateNavCounter('escalationsCount', 0);
                }
            }

            displayTransfers(transfers) {
                const container = document.getElementById('transfersContainer');
                if (!container) return;

                if (transfers.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"></path>
                            </svg>
                            <p>No hay transferencias pendientes</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="space-y-4">
                        ${transfers.map(transfer => this.createTransferCard(transfer)).join('')}
                    </div>
                `;
            }

            createTransferCard(transfer) {
                const priority = transfer.priority || 'medium';
                const timeAgo = this.getTimeAgo(transfer.created_at || transfer.timestamp);
                const minutesPending = transfer.minutes_pending || 0;
                const isUrgent = transfer.is_urgent || minutesPending > 5;
                
                // Extraer información del paciente del objeto transfer con múltiples fallbacks
                let patientPhone = '';
                let patientEps = '';
                
                // Función para limpiar datos vacíos o con solo espacios
                const cleanData = (value) => {
                    if (!value || typeof value !== 'string') return null;
                    const cleaned = value.trim();
                    return cleaned.length > 0 ? cleaned : null;
                };
                
                // Teléfono y EPS con limpieza
                const rawPhone = cleanData(transfer.patient_phone) || 
                                cleanData(transfer.patient_data?.telefono) || 
                                cleanData(transfer.patient_data?.phone);
                if (rawPhone) patientPhone = rawPhone;
                              
                const rawEps = cleanData(transfer.patient_eps) || 
                              cleanData(transfer.patient_data?.eps) || 
                              cleanData(transfer.patient_data?.insurance);
                if (rawEps) patientEps = rawEps;
                
                console.log('Transfer data for card (cleaned):', {
                    transfer,
                    sessionId: transfer.session_id
                });
                
                return `
                    <div class="transfer-card priority-${priority} ${isUrgent ? 'border-red-400 bg-red-50' : ''}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <h4 class="font-semibold text-gray-900">Transfer #${transfer.id.substring(0, 8)}...</h4>
                                    <span class="status-badge status-${priority}">${priority.toUpperCase()}</span>
                                    ${isUrgent ? '<span class="status-badge status-urgent">URGENTE</span>' : ''}
                                </div>
                                
                                ${(patientPhone || patientEps) ? `
                                    <div class="bg-blue-50 rounded-lg p-3 mb-3">
                                        <div class="text-xs font-medium text-blue-700 mb-1">INFORMACIÓN ADICIONAL</div>
                                        <div class="grid grid-cols-2 gap-2 text-sm">
                                            ${patientPhone ? `<div><span class="font-medium text-blue-800">Teléfono:</span> ${patientPhone}</div>` : ''}
                                            ${patientEps ? `<div><span class="font-medium text-blue-800">EPS:</span> ${patientEps}</div>` : ''}
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                                    <div class="col-span-2">
                                        <span class="font-medium">Transferencia:</span> 
                                        <span class="text-purple-600 font-medium">${transfer.transfer_direction || (transfer.from_room_name + ' → ' + transfer.to_room_name)}</span>
                                    </div>
                                    <div><span class="font-medium">Solicitado por:</span> ${transfer.from_agent_name || 'Agente desconocido'}</div>
                                    <div><span class="font-medium">Tiempo pendiente:</span> 
                                        <span class="${minutesPending > 5 ? 'text-red-600 font-semibold' : 'text-gray-600'}">${minutesPending} min</span>
                                    </div>
                                </div>
                                
                                ${transfer.reason ? `
                                    <div class="bg-gray-50 rounded p-2 mt-2">
                                        <span class="font-medium text-sm text-gray-600">Motivo:</span>
                                        <p class="text-sm text-gray-700 mt-1">${transfer.reason}</p>
                                    </div>
                                ` : ''}
                                
                                ${transfer.session_id ? `
                                    <div class="bg-blue-50 rounded p-2 mt-2">
                                        <span class="font-medium text-xs text-blue-700">ID Sesión:</span>
                                        <p class="text-xs text-blue-800 font-mono">${transfer.session_id}</p>
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div class="flex flex-col gap-2 ml-4">
                                <button onclick="supervisorClient.approveTransfer('${transfer.id}')" 
                                        class="btn btn-success whitespace-nowrap">
                                    Aprobar
                                </button>
                                <button onclick="supervisorClient.rejectTransfer('${transfer.id}')" 
                                        class="btn btn-danger whitespace-nowrap">
                                    Rechazar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            displayEscalations(escalations) {
                const container = document.getElementById('escalationsContainer');
                if (!container) return;

                if (escalations.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <p>No hay escalaciones activas</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="space-y-4">
                        ${escalations.map(escalation => this.createEscalationCard(escalation)).join('')}
                    </div>
                `;
            }

            createEscalationCard(escalation) {
                const timeAgo = this.getTimeAgo(escalation.created_at || escalation.timestamp);
                const isUrgent = escalation.urgency_indicator || escalation.failed_transfer_count >= 4;
                const priority = escalation.priority || 'high';
                const minutesWaiting = escalation.minutes_waiting || 0;
                
                // Extraer información del paciente del objeto escalation con múltiples fallbacks
                let patientPhone = '';
                let patientEps = '';
                
                // Teléfono y EPS con fallbacks similares
                patientPhone = escalation.patient_phone || 
                              escalation.patient_data?.telefono || 
                              escalation.patient_data?.phone ||
                              escalation.session?.patient_data?.telefono ||
                              escalation.session?.patient_data?.phone || '';
                              
                patientEps = escalation.patient_eps || 
                            escalation.patient_data?.eps || 
                            escalation.patient_data?.insurance ||
                            escalation.session?.patient_data?.eps ||
                            escalation.session?.patient_data?.insurance || '';
                
                console.log('Escalation data for card:', {
                    escalation
                });
                
                return `
                    <div class="escalation-card priority-${priority} ${isUrgent ? 'border-red-400 bg-red-50' : ''}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <h4 class="font-semibold text-gray-900">Escalación #${escalation.session_id.substring(0, 12)}...</h4>
                                    <span class="status-badge status-${priority}">${escalation.failed_transfer_count} Intentos</span>
                                    ${isUrgent ? '<span class="status-badge status-urgent">CRÍTICO</span>' : ''}
                                    ${escalation.assigned_supervisor ? '<span class="status-badge status-active">ASIGNADO</span>' : ''}
                                </div>
                                
                                ${(patientPhone || patientEps) ? `
                                    <div class="bg-purple-50 rounded-lg p-3 mb-3">
                                        <div class="text-xs font-medium text-purple-700 mb-1">INFORMACIÓN ADICIONAL</div>
                                        <div class="grid grid-cols-2 gap-2 text-sm">
                                            ${patientPhone ? `<div><span class="font-medium text-purple-800">Teléfono:</span> ${patientPhone}</div>` : ''}
                                            ${patientEps ? `<div><span class="font-medium text-purple-800">EPS:</span> ${patientEps}</div>` : ''}
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                                    <div><span class="font-medium">Sala Actual:</span> 
                                        <span class="text-blue-600 font-medium">${escalation.room_name || this.getRoomDisplayName(escalation.current_room)}</span>
                                    </div>
                                    <div><span class="font-medium">Estado:</span> ${escalation.session_status || 'N/A'}</div>
                                    <div><span class="font-medium">Esperando:</span> 
                                        <span class="${minutesWaiting > 20 ? 'text-red-600 font-semibold' : 'text-orange-600'}">${minutesWaiting} min</span>
                                    </div>
                                    <div><span class="font-medium">Prioridad:</span> 
                                        <span class="capitalize ${priority === 'urgent' || priority === 'critical' ? 'text-red-600 font-semibold' : 'text-gray-600'}">${priority}</span>
                                    </div>
                                </div>
                                
                                ${escalation.supervisor_name ? `
                                    <div class="bg-yellow-50 rounded p-2 mt-2">
                                        <span class="text-xs font-medium text-yellow-700">ASIGNADO A:</span>
                                        <p class="text-sm text-yellow-800">${escalation.supervisor_name}</p>
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div class="flex flex-col gap-2 ml-4">
                                ${!escalation.assigned_supervisor ? `
                                    <button onclick="supervisorClient.takeEscalation('${escalation.session_id}')" 
                                            class="btn btn-warning whitespace-nowrap">
                                        Tomar Control
                                    </button>
                                ` : escalation.assigned_supervisor === this.getCurrentUser()?.id ? `
                                    <button onclick="supervisorClient.openExistingSupervisionChat('${escalation.session_id}')" 
                                            class="btn btn-primary whitespace-nowrap">
                                        Abrir Chat
                                    </button>
                                ` : `
                                    <div class="text-xs text-gray-500 text-center p-2">
                                        Tomado por<br>otro supervisor
                                    </div>
                                `}
                                
                                <button onclick="supervisorClient.assignEscalation('${escalation.session_id}')" 
                                        class="btn btn-success whitespace-nowrap">
                                    Asignar Agente
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            async approveTransfer(transferId) {
                this.currentTransfer = transferId;
                
                if (confirm('¿Aprobar esta transferencia? La sesión se moverá a la sala destino.')) {
                    try {
                        const response = await fetch(`${this.supervisorServiceUrl}/transfers/${transferId}/approve`, {
                            method: 'PUT',
                            headers: this.getAuthHeaders(),
                            body: JSON.stringify({
                                notes: null,
                            })
                        });
                        
                        if (response.ok) {
                            const result = await response.json();
                            this.showNotification('Transferencia aprobada - Sesión movida a nueva sala', 'success');
                            setTimeout(() => this.loadTransfers(), 1000);
                        } else {
                            throw new Error('Error del servidor');
                        }
                    } catch (error) {
                        console.error('Error aprobando transferencia:', error);
                        this.showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                this.currentTransfer = null;
            }

            async rejectTransfer(transferId) {
                const reason = prompt('Motivo del rechazo?');
                if (!reason) return;
                
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/transfers/${transferId}/reject`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            reason: reason,
                            supervisor_id: this.getCurrentUser()?.id
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        this.showNotification(`Transferencia ${transferId} rechazada`, 'info');
                        
                        // 🔧 CORREGIDO: Mejor detección de escalaciones automáticas
                        if (result.data?.escalation_triggered || result.escalation_triggered) {
                            this.showNotification('Se ha creado una escalación automática', 'warning');
                            
                            // 🔧 CORREGIDO: Actualizar ambas secciones con mejor timing
                            setTimeout(async () => {
                                await this.loadTransfers();
                                await this.loadEscalations();
                                
                                // 🔧 NUEVO: Segunda verificación para asegurar que se cargue la nueva escalación
                                setTimeout(async () => {
                                    await this.loadEscalations();
                                }, 3000);
                            }, 2000);
                        } else {
                            setTimeout(() => this.loadTransfers(), 1000);
                        }
                    } else {
                        throw new Error('Error del servidor');
                    }
                } catch (error) {
                    console.error('Error rechazando transferencia:', error);
                    this.showNotification('Error: ' + error.message, 'error');
                }
            }

            async assignEscalation(sessionId) {
                this.currentEscalation = sessionId;
                await this.loadAvailableAgents();
                showModal('assignAgentModal');
            }

            async assignEscalationToAgent(sessionId, agentId, reason) {
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/escalations/${sessionId}/assign`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            agent_id: agentId,
                            supervisor_id: this.getCurrentUser()?.id,
                            reason: reason
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        this.showNotification('Escalación asignada exitosamente', 'success');
                        
                        if (result.data?.assigned_agent?.name) {
                            this.showNotification(`Asignado a: ${result.data.assigned_agent.name}`, 'info');
                        }
                        
                        await this.loadEscalations();
                    } else {
                        throw new Error('Error del servidor');
                    }
                } catch (error) {
                    console.error('Error asignando escalación:', error);
                    this.showNotification('Error: ' + error.message, 'error');
                }
            }

            async takeEscalation(sessionId) {
                try {
                    this.showNotification('Tomando control de la escalación...', 'info');
                    
                    const response = await fetch(`${this.supervisorServiceUrl}/escalations/${sessionId}/take`, {
                        method: 'PUT',
                        headers: this.getAuthHeaders(),
                        body: JSON.stringify({
                            supervisor_id: this.getCurrentUser()?.id
                        })
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success && result.data) {
                            console.log('✅ Escalación tomada, datos iniciales:', result.data);
                            
                            // 🔧 MEJORADO: Cargar datos completos de la sesión incluyendo ptoken
                            const completeSessionData = await this.loadCompleteSessionData(sessionId, result.data);
                            
                            console.log('📋 Datos completos de la sesión:', completeSessionData);
                            await this.openSupervisorChat(completeSessionData);
                            this.showNotification('Escalación tomada exitosamente', 'success');
                            await this.loadEscalations();
                        } else {
                            throw new Error('Respuesta del servidor inválida');
                        }
                    } else {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Error del servidor');
                    }
                } catch (error) {
                    console.error('Error tomando escalación:', error);
                    this.showNotification('Error tomando escalación: ' + error.message, 'error');
                }
            }

            // 🆕 FUNCIÓN: Abrir chat de supervisión existente
            async openExistingSupervisionChat(sessionId) {
                try {
                    console.log('🔍 === ABRIENDO CHAT DE SUPERVISIÓN EXISTENTE ===');
                    console.log('📋 Session ID:', sessionId);
                    
                    // Crear datos iniciales básicos
                    const initialData = {
                        session_data: {
                            session_id: sessionId,
                            id: sessionId
                        }
                    };
                    
                    // Cargar datos completos de la sesión
                    console.log('📊 Cargando datos completos...');
                    const completeSessionData = await this.loadCompleteSessionData(sessionId, initialData);
                    
                    console.log('✅ Datos completos obtenidos:', completeSessionData);
                    await this.openSupervisorChat(completeSessionData);
                    
                } catch (error) {
                    console.error('❌ Error abriendo chat de supervisión existente:', error);
                    this.showNotification('Error abriendo chat: ' + error.message, 'error');
                }
            }

            // 🆕 NUEVA FUNCIÓN: Cargar datos completos de la sesión
            async loadCompleteSessionData(sessionId, initialData) {
                try {
                    console.log('🔍 Cargando datos completos para sesión:', sessionId);
                    
                    // Intentar cargar desde el endpoint de chat sessions (que SÍ funciona)
                    const response = await fetch(`${CHAT_API}/chats/sessions?session_id=${sessionId}`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        console.log('📊 Respuesta de sessions completas:', result);
                        
                        if (result.success && result.data && result.data.sessions && result.data.sessions.length > 0) {
                            const completeSession = result.data.sessions[0];
                            console.log('✅ Sesión completa encontrada:', completeSession);
                            
                            // Combinar datos iniciales con datos completos
                            const mergedData = {
                                ...initialData,
                                session_data: {
                                    ...initialData.session_data,
                                    ...completeSession,
                                    // Asegurar que el ptoken esté disponible
                                    ptoken: completeSession.ptoken || initialData.session_data?.ptoken,
                                    patient_data: completeSession.patient_data || completeSession.user_data || initialData.session_data?.patient_data,
                                    user_data: completeSession.user_data || completeSession.patient_data || initialData.session_data?.user_data
                                }
                            };
                            
                            console.log('🔧 Datos combinados:', mergedData);
                            return mergedData;
                        }
                    }
                    
                    console.log('⚠️ No se pudieron cargar datos completos, usando datos iniciales');
                    return initialData;
                    
                } catch (error) {
                    console.error('❌ Error cargando datos completos:', error);
                    console.log('🔄 Usando datos iniciales por error:', initialData);
                    return initialData;
                }
            }

            async loadAvailableAgents() {
                try {
                    const response = await fetch(`${CHAT_API}/chats/agents/available`, {
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        const agents = result.data?.agents || [];
                        this.populateAgentDropdown(agents);
                    } else {
                        throw new Error('Error cargando agentes');
                    }
                } catch (error) {
                    console.error('Error loading agents:', error);
                    this.populateAgentDropdown([]);
                }
            }

            populateAgentDropdown(agents) {
                const dropdown = document.getElementById('agentDropdownContent');
                if (!dropdown) return;
                
                if (agents.length === 0) {
                    dropdown.innerHTML = `
                        <div class="dropdown-item text-gray-500">
                            No hay agentes disponibles
                        </div>
                    `;
                    return;
                }
                
                dropdown.innerHTML = agents.map(agent => `
                    <div class="dropdown-item" onclick="selectAgent('${agent.id}', '${agent.name}')">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">${agent.name}</div>
                                <div class="text-xs text-gray-500">${agent.room || 'Sala General'}</div>
                            </div>
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                        </div>
                    </div>
                `).join('');
            }

            async runMisdirectionAnalysis() {
                const container = document.getElementById('analysisContainer');
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="loading-spinner mx-auto mb-4"></div>
                        <p>Analizando patrones de transferencia...</p>
                    </div>
                `;
                
                try {
                    const response = await fetch(`${this.supervisorServiceUrl}/misdirection/analyze`, {
                        method: 'GET',
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        this.displayAnalysisResults(result.data || {});
                    } else {
                        throw new Error('Error ejecutando análisis');
                    }
                } catch (error) {
                    console.error('Error en análisis RF6:', error);
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg class="w-12 h-12 text-red-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>Error ejecutando análisis: ${error.message}</p>
                        </div>
                    `;
                }
            }

            displayAnalysisResults(data) {
                const container = document.getElementById('analysisContainer');
                
                console.log('📊 Datos recibidos para análisis:', data);
                
                // ✅ USAR LA ESTRUCTURA REAL DE DATOS DEL BACKEND
                const results = data.results || {};
                const diagnostics = results.diagnostics || {};
                const patterns = results.patterns || [];
                
                const analysisData = {
                    total_transfers: diagnostics.total_transfers_in_db || 0,
                    transfers_analyzed: diagnostics.transfers_in_period || 0,
                    patterns_found: data.patterns_found || 0,
                    problematic_patterns: data.problematic_patterns || 0,
                    time_period: data.time_period || '24 horas',
                    analysis_expanded: diagnostics.analysis_expanded || false
                };
                
                // Calcular tasa de éxito
                const problemRate = analysisData.transfers_analyzed > 0 
                    ? Math.round((analysisData.problematic_patterns / analysisData.transfers_analyzed) * 100)
                    : 0;
                const successRate = Math.max(0, 100 - problemRate);

                console.log('📈 Datos procesados:', analysisData);

                container.innerHTML = `
                    <div class="space-y-6">
                        <!-- Métricas principales -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Base de Datos</p>
                                        <p class="text-xl font-semibold text-gray-900">${analysisData.total_transfers.toLocaleString()}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Analizadas</p>
                                        <p class="text-xl font-semibold text-gray-900">${analysisData.transfers_analyzed.toLocaleString()}</p>
                                        <p class="text-xs text-gray-500">${analysisData.time_period}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Patrones</p>
                                        <p class="text-xl font-semibold text-gray-900">${analysisData.patterns_found}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white p-4 rounded-lg border ${analysisData.problematic_patterns > 0 ? 'border-red-200 bg-red-50' : 'border-gray-200'} hover:shadow-md transition-shadow">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 ${analysisData.problematic_patterns > 0 ? 'bg-red-100' : 'bg-green-100'} rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 ${analysisData.problematic_patterns > 0 ? 'text-red-600' : 'text-green-600'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${analysisData.problematic_patterns > 0 ? 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'}"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Problemáticos</p>
                                        <p class="text-xl font-semibold ${analysisData.problematic_patterns > 0 ? 'text-red-700' : 'text-green-700'}">${analysisData.problematic_patterns}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estado general del análisis -->
                        <div class="bg-white rounded-lg border ${successRate >= 80 ? 'border-green-200' : successRate >= 60 ? 'border-yellow-200' : 'border-red-200'} p-6">
                            <div class="text-center">
                                <div class="w-16 h-16 mx-auto mb-4 ${successRate >= 80 ? 'bg-green-100' : successRate >= 60 ? 'bg-yellow-100' : 'bg-red-100'} rounded-full flex items-center justify-center">
                                    <svg class="w-8 h-8 ${successRate >= 80 ? 'text-green-600' : successRate >= 60 ? 'text-yellow-600' : 'text-red-600'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${successRate >= 80 ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : successRate >= 60 ? 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'}"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold ${successRate >= 80 ? 'text-green-800' : successRate >= 60 ? 'text-yellow-800' : 'text-red-800'} mb-2">
                                    ${successRate >= 80 ? 'Rendimiento Excelente' : successRate >= 60 ? 'Atención Requerida' : 'Acción Necesaria'}
                                </h3>
                                <p class="text-gray-600 mb-3">
                                    ${analysisData.problematic_patterns} patrones problemáticos de ${analysisData.patterns_found} detectados
                                </p>
                                <div class="inline-flex items-center px-4 py-2 ${successRate >= 80 ? 'bg-green-100 text-green-800' : successRate >= 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'} rounded-full">
                                    <span class="font-medium">Tasa de éxito: ${successRate}%</span>
                                </div>
                                ${analysisData.analysis_expanded ? `
                                    <div class="mt-3">
                                        <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                                            ℹ️ Análisis expandido a ${analysisData.time_period}
                                        </span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <!-- Lista de patrones problemáticos -->
                        ${patterns.length > 0 ? `
                            <div class="bg-white rounded-lg border border-gray-200">
                                <div class="p-4 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900">Patrones Detectados</h3>
                                    <p class="text-sm text-gray-600">Rutas de transferencia que requieren revisión</p>
                                </div>
                                <div class="divide-y divide-gray-200">
                                    ${patterns.map((pattern, index) => `
                                        <div class="p-4 hover:bg-gray-50">
                                            <div class="flex items-center justify-between mb-3">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 ${
                                                        pattern.risk_level === 'high' ? 'bg-red-100 text-red-600' : 
                                                        pattern.risk_level === 'medium' ? 'bg-yellow-100 text-yellow-600' : 
                                                        'bg-green-100 text-green-600'
                                                    } rounded-full flex items-center justify-center">
                                                        <span class="text-sm font-medium">${index + 1}</span>
                                                    </div>
                                                    <div>
                                                        <h4 class="font-medium text-gray-900">
                                                            ${pattern.from_room_name || pattern.from_room} → ${pattern.to_room_name || pattern.to_room}
                                                        </h4>
                                                        <p class="text-sm text-gray-500">Ruta de transferencia</p>
                                                    </div>
                                                </div>
                                                <div class="flex space-x-2">
                                                    <span class="px-2 py-1 text-xs font-medium rounded ${
                                                        pattern.risk_level === 'high' ? 'bg-red-100 text-red-800' : 
                                                        pattern.risk_level === 'medium' ? 'bg-yellow-100 text-yellow-800' : 
                                                        'bg-green-100 text-green-800'
                                                    }">
                                                        ${pattern.risk_level === 'high' ? 'Alto' : pattern.risk_level === 'medium' ? 'Medio' : 'Bajo'}
                                                    </span>
                                                    ${pattern.is_problematic ? '<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded">Problemático</span>' : ''}
                                                </div>
                                            </div>
                                            
                                            <div class="grid grid-cols-4 gap-4 text-sm">
                                                <div class="text-center p-2 bg-gray-50 rounded">
                                                    <div class="font-medium text-gray-900">${pattern.transfer_count || 0}</div>
                                                    <div class="text-gray-600">Total</div>
                                                </div>
                                                <div class="text-center p-2 bg-red-50 rounded">
                                                    <div class="font-medium text-red-700">${pattern.rejected_count || 0}</div>
                                                    <div class="text-red-600">Rechazadas</div>
                                                </div>
                                                <div class="text-center p-2 bg-green-50 rounded">
                                                    <div class="font-medium text-green-700">${pattern.approved_count || 0}</div>
                                                    <div class="text-green-600">Aprobadas</div>
                                                </div>
                                                <div class="text-center p-2 ${(pattern.rejection_rate || 0) >= 30 ? 'bg-red-50' : (pattern.rejection_rate || 0) >= 10 ? 'bg-yellow-50' : 'bg-green-50'} rounded">
                                                    <div class="font-medium ${(pattern.rejection_rate || 0) >= 30 ? 'text-red-700' : (pattern.rejection_rate || 0) >= 10 ? 'text-yellow-700' : 'text-green-700'}">
                                                        ${pattern.rejection_rate || 0}%
                                                    </div>
                                                    <div class="${(pattern.rejection_rate || 0) >= 30 ? 'text-red-600' : (pattern.rejection_rate || 0) >= 10 ? 'text-yellow-600' : 'text-green-600'}">Rechazo</div>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : `
                            <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 mx-auto mb-3 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="font-medium text-gray-900 mb-1">Sin Patrones Problemáticos</h3>
                                <p class="text-gray-500">No se detectaron rutas problemáticas en el período analizado.</p>
                            </div>
                        `}
                        
                        <!-- Información de diagnóstico -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-3">Información de Diagnóstico</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Período:</span>
                                    <div class="font-medium text-gray-900">${analysisData.time_period}</div>
                                </div>
                                <div>
                                    <span class="text-gray-600">Antes del filtro:</span>
                                    <div class="font-medium text-gray-900">${diagnostics.patterns_before_filter || 0}</div>
                                </div>
                                <div>
                                    <span class="text-gray-600">Después del filtro:</span>
                                    <div class="font-medium text-gray-900">${diagnostics.patterns_after_filter || 0}</div>
                                </div>
                                <div>
                                    <span class="text-gray-600">Expandido:</span>
                                    <div class="font-medium text-gray-900">${analysisData.analysis_expanded ? 'Sí' : 'No'}</div>
                                </div>
                            </div>
                            <div class="mt-3 text-xs text-gray-500">
                                Última actualización: ${new Date().toLocaleString('es-ES')}
                            </div>
                        </div>
                    </div>
                `;
            }

            // ✅ FUNCIÓN PRINCIPAL PARA ABRIR CHAT DE SUPERVISOR (MEJORADA CON MEJOR DEBUGGING)
            async openSupervisorChat(sessionData) {
                try {
                    console.log('🔍 === INICIANDO APERTURA DE CHAT SUPERVISOR ===');
                    console.log('📊 sessionData recibido:', sessionData);
                    
                    const session = sessionData.session_data || sessionData;
                    console.log('📋 session extraído:', session);
                    console.log('🔑 ptoken disponible:', session.ptoken ? 'SÍ' : 'NO');
                    console.log('👤 patient_data disponible:', session.patient_data ? 'SÍ' : 'NO');
                    console.log('📄 user_data disponible:', session.user_data ? 'SÍ' : 'NO');
                    
                    currentSupervisorSession = sessionData;
                    
                    hideAllSections();
                    document.getElementById('supervisor-chat-section').classList.remove('hidden');
                    document.getElementById('sectionTitle').textContent = 'Chat de Supervisión';
                    
                    // ✅ USAR LA LÓGICA DE PTOKEN CON MEJOR DEBUGGING
                    console.log('🔍 Obteniendo información del paciente...');
                    let patientInfo = await this.getPatientInfoWithPToken(session);
                    console.log('👤 Información del paciente obtenida:', patientInfo);
                    
                    // Si los datos del paciente están vacíos, crear información básica con el session_id
                    if (this.isPatientInfoEmpty(patientInfo)) {
                        const sessionId = session.session_id || session.id;
                        console.log('⚠️ Datos del paciente vacíos, creando información básica para session:', sessionId);
                        
                        patientInfo = {
                            nombreCompleto: sessionId ? `Paciente #${sessionId.substring(0, 8)}...` : 'Paciente',
                            id: sessionId ? `Sesión: ${sessionId.substring(0, 12)}...` : 'N/A',
                            primer_nombre: 'Paciente',
                            primer_apellido: 'Sin datos',
                            telefono: '',
                            email: '',
                            ciudad: '',
                            eps: '',
                            plan: '',
                            habilitado: 'Desconocido',
                            nomTomador: ''
                        };
                        
                        console.log('📝 Información básica creada:', patientInfo);
                    } else {
                        console.log('✅ Usando información completa del paciente');
                    }
                    
                    console.log('🎨 Actualizando interfaz...');
                    this.updateSupervisorChatUI(patientInfo, session);
                    
                    const msgContainer = document.getElementById('supervisorChatMessages');
                    if (msgContainer) msgContainer.innerHTML = '';
                    
                    const chatInput = document.getElementById('supervisorMessageInput');
                    const chatButton = document.getElementById('supervisorSendButton');
                    if (chatInput) {
                        chatInput.disabled = false;
                        chatInput.placeholder = 'Escribe como supervisor...';
                    }
                    if (chatButton) {
                        chatButton.disabled = false;
                    }
                    
                    console.log('⏰ Iniciando timer...');
                    this.startSupervisorTimer(session.created_at || new Date().toISOString());
                    
                    console.log('🔌 Conectando WebSocket...');
                    await this.connectSupervisorWebSocket();
                    
                    console.log('📜 Cargando historial...');
                    await this.loadSupervisorChatHistory();
                    
                    console.log('✅ === CHAT SUPERVISOR ABIERTO EXITOSAMENTE ===');
                    
                } catch (error) {
                    console.error('❌ Error abriendo chat de supervisor:', error);
                    this.showNotification('Error al abrir chat: ' + error.message, 'error');
                }
                
                // 🆕 MOSTRAR BOTÓN FLOTANTE EN MÓVIL
                if (window.innerWidth <= 1024) {
                    showChatToggleButton();
                }
            }

            updateSupervisorChatUI(patientInfo, session) {
                const fullName = patientInfo.nombreCompleto || 
                    `${patientInfo.primer_nombre} ${patientInfo.segundo_nombre} ${patientInfo.primer_apellido} ${patientInfo.segundo_apellido}`
                    .replace(/\s+/g, ' ').trim() || 
                    this.getPatientNameFromSession(session);

                const chatPatientName = document.getElementById('supervisorChatPatientName');
                if (chatPatientName) chatPatientName.textContent = fullName;

                const chatPatientInitials = document.getElementById('supervisorChatPatientInitials');
                if (chatPatientInitials) {
                    const initials = ((patientInfo.primer_nombre?.[0] || '') + (patientInfo.primer_apellido?.[0] || '')).toUpperCase() || 
                                   fullName.charAt(0).toUpperCase();
                    chatPatientInitials.textContent = initials;
                }

                const chatSessionId = document.getElementById('supervisorChatSessionId');
                if (chatSessionId) chatSessionId.textContent = session?.session_id || session?.id || 'N/A';

                const chatRoomName = document.getElementById('supervisorChatRoomName');
                if (chatRoomName) chatRoomName.textContent = session?.room_name || this.getRoomNameFromSession(session);

                this.updateSupervisorPatientInfoSidebar(patientInfo, fullName);
            }

            updateSupervisorPatientInfoSidebar(patientInfo, fullName) {
                const updates = [
                    { id: 'supervisorPatientInfoName', value: fullName },
                    { id: 'supervisorPatientInfoDocument', value: patientInfo.id || '-' },
                    { id: 'supervisorPatientInfoPhone', value: patientInfo.telefono || '-' },
                    { id: 'supervisorPatientInfoEmail', value: patientInfo.email || '-' },
                    { id: 'supervisorPatientInfoCity', value: patientInfo.ciudad || '-' },
                    { id: 'supervisorPatientInfoEPS', value: patientInfo.eps || '-' },
                    { id: 'supervisorPatientInfoPlan', value: patientInfo.plan || '-' },
                    { 
                        id: 'supervisorPatientInfoStatus', 
                        value: patientInfo.habilitado === 'S' || patientInfo.habilitado === 'Activo' || patientInfo.habilitado === 'activo' 
                            ? 'Vigente' 
                            : patientInfo.habilitado === 'N' || patientInfo.habilitado === 'Inactivo' || patientInfo.habilitado === 'inactivo'
                            ? 'Inactivo'
                            : patientInfo.habilitado || 'No especificado'
                    },
                    { id: 'supervisorPatientInfoTomador', value: patientInfo.nomTomador || '-' }
                ];

                updates.forEach(update => {
                    const element = document.getElementById(update.id);
                    if (element) {
                        element.textContent = update.value;
                    }
                });
            }

            async connectSupervisorWebSocket() {
                try {
                    if (supervisorChatSocket) {
                        supervisorChatSocket.disconnect();
                        isSupervisorConnected = false;
                        supervisorSessionJoined = false;
                    }
                    
                    const token = this.getToken();
                    const currentUser = this.getCurrentUser();
                    
                    console.log('🔌 Conectando WebSocket como supervisor:', {
                        user_id: currentUser.id,
                        user_type: 'supervisor',
                        user_name: currentUser.name,
                        session_id: currentSupervisorSession?.session_data?.session_id || currentSupervisorSession?.session_data?.id
                    });
                    
                    supervisorChatSocket = io(API_BASE, {
                        transports: ['websocket', 'polling'],
                        auth: {
                            token: token,
                            user_id: currentUser.id,
                            user_type: 'supervisor',
                            user_name: currentUser.name,
                            session_id: currentSupervisorSession?.session_data?.session_id || currentSupervisorSession?.session_data?.id
                        }
                    });
                    
                    supervisorChatSocket.on('connect', () => {
                        isSupervisorConnected = true;
                        this.updateSupervisorChatStatus('Conectado');
                        console.log('✅ WebSocket supervisor conectado exitosamente');
                        
                        setTimeout(() => {
                            this.joinSupervisorChatSession();
                        }, 500);
                    });
                    
                    supervisorChatSocket.on('disconnect', () => {
                        isSupervisorConnected = false;
                        supervisorSessionJoined = false;
                        this.updateSupervisorChatStatus('Desconectado');
                        console.log('❌ WebSocket supervisor desconectado');

                        if (!currentSupervisorSession) {
                            hideChatToggleButton();
                            closeAllMobileSidebars();
                        }
                    });
                    
                    supervisorChatSocket.on('chat_joined', (data) => {
                        supervisorSessionJoined = true;
                        this.updateSupervisorChatStatus('En supervisión');
                        console.log('✅ Supervisor se unió al chat exitosamente:', data);
                    });
                    
                    supervisorChatSocket.on('new_message', (data) => {
                        console.log('💬 Nuevo mensaje recibido en supervisor:', data);
                        this.handleSupervisorNewMessage(data);
                    });
                    
                    supervisorChatSocket.on('user_typing', (data) => {
                        if (data.user_type === 'patient' && data.user_id !== currentUser.id) {
                            this.showSupervisorPatientTyping();
                        }
                    });
                    
                    supervisorChatSocket.on('user_stop_typing', (data) => {
                        if (data.user_type === 'patient' && data.user_id !== currentUser.id) {
                            this.hideSupervisorPatientTyping();
                        }
                    });
                    
                    supervisorChatSocket.on('error', (error) => {
                        console.error('❌ Error en socket de supervisor:', error);
                        this.showNotification('Error en chat: ' + (error.message || error), 'error');
                    });
                    
                } catch (error) {
                    console.error('❌ Error conectando WebSocket de supervisor:', error);
                    this.updateSupervisorChatStatus('Sin WebSocket');
                }
            }

            joinSupervisorChatSession() {
                if (!supervisorChatSocket || !currentSupervisorSession || !isSupervisorConnected) {
                    console.warn('⚠️ No se puede unir al chat - faltan condiciones:', {
                        hasSocket: !!supervisorChatSocket,
                        hasSession: !!currentSupervisorSession,
                        isConnected: isSupervisorConnected
                    });
                    return;
                }
                
                const currentUser = this.getCurrentUser();
                const sessionId = currentSupervisorSession.session_data?.session_id || currentSupervisorSession.session_data?.id;
                
                const joinData = {
                    session_id: sessionId,
                    user_id: currentUser.id,
                    user_type: 'supervisor',
                    user_name: currentUser.name
                };
                
                console.log('🤝 Supervisor uniéndose al chat:', joinData);
                
                supervisorChatSocket.emit('join_chat', joinData);
            }

            updateSupervisorChatStatus(status) {
                const statusElement = document.getElementById('supervisorChatStatus');
                if (statusElement) {
                    statusElement.textContent = status;
                    
                    statusElement.className = 'text-sm font-medium ';
                    if (status === 'En supervisión') {
                        statusElement.className += 'text-purple-600';
                    } else if (status === 'Conectado') {
                        statusElement.className += 'text-blue-600';
                    } else {
                        statusElement.className += 'text-gray-500';
                    }
                }
            }

            handleSupervisorNewMessage(data) {
                const messagesContainer = document.getElementById('supervisorChatMessages');
                if (!messagesContainer) return;

                const currentUser = this.getCurrentUser();
                
                // Mejorar la lógica de identificación del remitente
                const isMyMessage = (data.user_type === 'supervisor' && data.user_id === currentUser.id) ||
                                   (data.sender_type === 'supervisor' && data.sender_id === currentUser.id);

                const messageId = `${data.user_id || data.sender_id}_${data.user_type || data.sender_type}_${data.content.substring(0, 20)}_${Date.now()}`;
                
                console.log('🔍 Supervisor message received:', {
                    data,
                    currentUserId: currentUser.id,
                    dataUserId: data.user_id || data.sender_id,
                    dataUserType: data.user_type || data.sender_type,
                    isMyMessage: isMyMessage
                });
                
                if (sentMessages.has(messageId)) {
                    return;
                }
                sentMessages.add(messageId);

                let timestamp = data.timestamp || data.created_at || Date.now();
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

                // Determinar el tipo de remitente y la etiqueta a mostrar
                let senderLabel = 'Paciente';
                let messageClass = 'bg-gray-200 text-gray-900';
                let justifyClass = 'justify-start';
                
                if (isMyMessage) {
                    senderLabel = 'Supervisor';
                    messageClass = 'bg-purple-600 text-white';
                    justifyClass = 'justify-end';
                } else if ((data.user_type || data.sender_type) === 'agent') {
                    senderLabel = 'Agente';
                    messageClass = 'bg-blue-200 text-blue-900';
                    justifyClass = 'justify-end';
                }

                wrapper.innerHTML = `
                    <div class="flex ${justifyClass}">
                        <div class="max-w-xs lg:max-w-md ${messageClass} rounded-lg px-4 py-2">
                            <div class="text-xs ${isMyMessage ? 'opacity-75' : 'font-medium text-gray-600'} mb-1">${senderLabel}</div>
                            <p>${this.escapeHtml(data.content)}</p>
                            <div class="text-xs ${isMyMessage ? 'opacity-75' : 'text-gray-500'} mt-1 ${isMyMessage ? 'text-right' : ''}">${time}</div>
                        </div>
                    </div>`;

                messagesContainer.appendChild(wrapper);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }

            async loadSupervisorChatHistory() {
                if (!currentSupervisorSession?.session_data) return;
                
                const messagesContainer = document.getElementById('supervisorChatMessages');
                if (!messagesContainer) return;

                messagesContainer.innerHTML = '';
                
                try {
                    const sessionId = currentSupervisorSession.session_data.session_id || currentSupervisorSession.session_data.id;
                    
                    console.log('📜 Cargando historial del chat para sesión:', sessionId);
                    
                    const response = await fetch(`${CHAT_API}/messages/${sessionId}?limit=50`, {
                        headers: this.getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        
                        if (result.success && result.data && result.data.messages) {
                            console.log('📋 Historial cargado:', result.data.messages.length, 'mensajes');
                            
                            result.data.messages.forEach((msg) => {
                                this.renderSupervisorMessageFromHistory(msg);
                            });
                            
                            setTimeout(() => {
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            }, 100);
                            return;
                        }
                    }
                    
                    console.log('📭 No hay mensajes en el historial');
                    messagesContainer.innerHTML = '<div class="text-center py-8 text-gray-500">No hay mensajes en el historial</div>';
                    
                } catch (error) {
                    console.error('❌ Error cargando historial del supervisor:', error);
                    this.showNotification('Error cargando historial de chat', 'warning');
                    messagesContainer.innerHTML = '<div class="text-center py-8 text-gray-500">Error cargando historial</div>';
                }
            }

            renderSupervisorMessageFromHistory(msg) {
                const messagesContainer = document.getElementById('supervisorChatMessages');
                if (!messagesContainer) return;

                const currentUser = this.getCurrentUser();
                
                // Mejorar la lógica de identificación del remitente para historial
                const isMyMessage = (msg.sender_type === 'supervisor' && msg.sender_id === currentUser.id) ||
                                   (msg.user_type === 'supervisor' && msg.user_id === currentUser.id);

                let timestamp = msg.timestamp || msg.created_at;
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

                // Determinar el tipo de remitente y los estilos
                let senderLabel = 'Paciente';
                let messageClass = 'bg-gray-200 text-gray-900';
                let justifyClass = 'justify-start';
                let labelClass = 'font-medium text-gray-600';
                let timeClass = 'text-gray-500';
                
                if (isMyMessage) {
                    senderLabel = 'Supervisor';
                    messageClass = 'bg-purple-600 text-white';
                    justifyClass = 'justify-end';
                    labelClass = 'opacity-75';
                    timeClass = 'opacity-75';
                } else if ((msg.sender_type || msg.user_type) === 'agent') {
                    senderLabel = 'Agente';
                    messageClass = 'bg-blue-200 text-blue-900';
                    justifyClass = 'justify-end';
                    labelClass = 'opacity-75';
                    timeClass = 'opacity-75';
                }

                wrapper.innerHTML = `
                    <div class="flex ${justifyClass}">
                        <div class="max-w-xs lg:max-w-md ${messageClass} rounded-lg px-4 py-2">
                            <div class="text-xs ${labelClass} mb-1">${senderLabel}</div>
                            <p>${this.escapeHtml(msg.content)}</p>
                            <div class="text-xs ${timeClass} mt-1 ${isMyMessage || (msg.sender_type || msg.user_type) === 'agent' ? 'text-right' : ''}">${time}</div>
                        </div>
                    </div>`;

                messagesContainer.appendChild(wrapper);
            }

            showSupervisorPatientTyping() {
                const indicator = document.getElementById('supervisorTypingIndicator');
                if (indicator) {
                    indicator.classList.remove('hidden');
                }
            }

            hideSupervisorPatientTyping() {
                const indicator = document.getElementById('supervisorTypingIndicator');
                if (indicator) {
                    indicator.classList.add('hidden');
                }
            }

            startSupervisorTimer(startTime) {
                this.stopSupervisorTimer();
                
                const timerElement = document.getElementById('supervisorChatTimer');
                if (!timerElement) return;

                const startDate = new Date(startTime);
                
                function updateTimer() {
                    const now = new Date();
                    const diff = now - startDate;
                    const totalMinutes = diff / (1000 * 60);
                    
                    timerElement.textContent = `• ${supervisorClient.formatTime(totalMinutes)}`;
                    timerElement.className = 'timer-display ml-2 text-purple-600';
                }
                
                updateTimer();
                supervisorTimerInterval = setInterval(updateTimer, 1000);
            }

            stopSupervisorTimer() {
                if (supervisorTimerInterval) {
                    clearInterval(supervisorTimerInterval);
                    supervisorTimerInterval = null;
                }
                
                const timerElement = document.getElementById('supervisorChatTimer');
                if (timerElement) {
                    timerElement.textContent = '';
                }
            }

            formatTime(minutes) {
                const mins = Math.floor(minutes);
                const secs = Math.floor((minutes - mins) * 60);
                return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
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

            updateNavCounter(elementId, count) {
                const element = document.getElementById(elementId);
                const mobileElement = document.getElementById('mobile' + elementId.charAt(0).toUpperCase() + elementId.slice(1));
                
                [element, mobileElement].forEach(el => {
                    if (el) {
                        el.textContent = count;
                        if (count > 0) {
                            el.classList.remove('hidden');
                            el.classList.add('animate-pulse');
                            setTimeout(() => {
                                el.classList.remove('animate-pulse');
                            }, 2000);
                        } else {
                            el.classList.add('hidden');
                        }
                    }
                });
            }

            getTimeAgo(timestamp) {
                try {
                    const now = new Date();
                    const time = new Date(timestamp);
                    const diffMs = now - time;
                    const diffMins = Math.floor(diffMs / 60000);
                    
                    if (diffMins < 1) return 'Ahora';
                    if (diffMins < 60) return `${diffMins} min`;
                    
                    const diffHours = Math.floor(diffMins / 60);
                    if (diffHours < 24) return `${diffHours}h`;
                    
                    const diffDays = Math.floor(diffHours / 24);
                    return `${diffDays}d`;
                } catch (error) {
                    return 'N/A';
                }
            }

            showNotification(message, type = 'info', duration = 4000) {
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
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-4">×</button>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, duration);
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
                            if (sectionId === 'transfers-section') {
                                await this.loadTransfers();
                            } else if (sectionId === 'escalations-section') {
                                await this.loadEscalations();
                            }
                        }
                        
                        // 🔧 NUEVO: Siempre actualizar escalaciones en background para detectar nuevas
                        if (activeSection?.id !== 'escalations-section') {
                            console.log('🔄 Actualizando escalaciones en background...');
                            await this.loadEscalations();
                        }
                        
                    } catch (error) {
                        console.warn('⚠️ Error en auto-refresh:', error);
                    }
                }, this.refreshIntervalTime);
            }

            stopAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                    this.refreshInterval = null;
                }
            }

            async init() {
                try {
                    await this.loadTransfers();
                    await this.loadEscalations();
                    this.startAutoRefresh();
                    console.log('✅ SupervisorClient inicializado correctamente');
                } catch (error) {
                    console.error('❌ Error de inicialización:', error);
                    this.showNotification('Error de inicialización del sistema', 'error');
                }
            }

            destroy() {
                this.stopAutoRefresh();
                this.stopSupervisorTimer();
                if (supervisorChatSocket) {
                    supervisorChatSocket.disconnect();
                }

                hideChatToggleButton();
                closeAllMobileSidebars();
            }
        }

        // ✅ HACER FUNCIÓN GLOBAL ACCESIBLE
        window.supervisorClient = new SupervisorClient();
        window.openExistingSupervisionChat = (sessionId) => supervisorClient.openExistingSupervisionChat(sessionId);

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
        
        // Patient Info Sidebar Functions
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
        
        // Show/Hide patient info button during supervisor chat
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
        
        // Section management
        function showSection(sectionName) {
            hideAllSections();
            document.getElementById(`${sectionName}-section`).classList.remove('hidden');
            
            // Update navigation active states (both desktop and mobile)
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            document.getElementById(`nav-${sectionName}`)?.classList.add('active');
            document.getElementById(`mobile-nav-${sectionName}`)?.classList.add('active');
            
            // Update title
            const titles = {
                'transfers': 'Transferencias Pendientes (RF4)',
                'escalations': 'Escalaciones Activas (RF5)', 
                'analysis': 'Análisis de Mal Direccionamiento (RF6)',
                'supervisor-chat': 'Chat de Supervisión'
            };
            document.getElementById('sectionTitle').textContent = titles[sectionName];
            
            // Handle patient info button visibility
            if (sectionName === 'supervisor-chat') {
                showPatientInfoButton();
                // También mostrar el botón flotante en móvil
                if (window.innerWidth <= 1024) {
                    showChatToggleButton();
                }
            } else {
                hidePatientInfoButton();
                hideChatToggleButton();
            }

            // Cerrar navegación móvil al cambiar sección
            closeMobileNav();
            
            // Load section data
            switch(sectionName) {
                case 'transfers':
                    supervisorClient.loadTransfers();
                    break;
                case 'escalations':
                    supervisorClient.loadEscalations();
                    break;
            }
        }

        function hideAllSections() {
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.add('hidden');
            });
        }
        
        // Handle window resize for mobile nav
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) { // lg breakpoint
                closeMobileNav();
                closePatientInfoSidebar();
            }
        });

        // Prevent mobile nav closing when clicking inside it
        document.getElementById('mobileNav')?.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        // Prevent patient info sidebar closing when clicking inside it
        document.getElementById('patientInfoSidebar')?.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        // Close sidebars on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMobileNav();
                closePatientInfoSidebar();
            }
        });

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function toggleAgentDropdown() {
            const dropdown = document.getElementById('agentDropdownContent');
            dropdown.classList.toggle('show');
        }

        function selectAgent(agentId, agentName) {
            supervisorClient.selectedAgent = agentId;
            document.getElementById('selectedAgentText').textContent = agentName;
            document.getElementById('agentDropdownContent').classList.remove('show');
        }

        function confirmAgentAssignment() {
            if (!supervisorClient.selectedAgent) {
                supervisorClient.showNotification('Por favor selecciona un agente', 'warning');
                return;
            }
            
            const reason = document.getElementById('assignmentReason').value;
            
            if (supervisorClient.currentTransfer) {
                supervisorClient.approveTransferWithAgent(supervisorClient.currentTransfer, supervisorClient.selectedAgent);
            } else if (supervisorClient.currentEscalation) {
                supervisorClient.assignEscalationToAgent(supervisorClient.currentEscalation, supervisorClient.selectedAgent, reason);
            }
            
            supervisorClient.selectedAgent = null;
            supervisorClient.currentTransfer = null;
            supervisorClient.currentEscalation = null;
            document.getElementById('selectedAgentText').textContent = 'Seleccionar agente...';
            document.getElementById('assignmentReason').value = '';
            
            closeModal('assignAgentModal');
        }

        function sendSupervisorMessage() {
            const input = document.getElementById('supervisorMessageInput');
            if (!input) return;

            const message = input.value.trim();
            if (!message) return;

            const currentUser = supervisorClient.getCurrentUser();

            if (isSupervisorConnected && supervisorSessionJoined && supervisorChatSocket) {
                const payload = {
                    session_id: currentSupervisorSession.session_data?.session_id || currentSupervisorSession.session_data?.id,
                    user_id: currentUser.id,
                    user_type: 'supervisor',
                    user_name: currentUser.name,
                    sender_id: currentUser.id,      // Agregar sender_id también
                    sender_type: 'supervisor',      // Agregar sender_type también
                    message_type: 'text',
                    content: message
                };

                console.log('📤 Enviando mensaje como supervisor:', payload);

                supervisorChatSocket.emit('send_message', payload, (response) => {
                    if (response && !response.success) {
                        console.error('❌ Error enviando mensaje:', response?.message || 'Error desconocido');
                        supervisorClient.showNotification('Error enviando mensaje: ' + (response?.message || 'Error desconocido'), 'error');
                        
                        // Restaurar el mensaje en caso de error
                        if (input) input.value = message;
                    }
                });
                
                input.value = '';
                updateSupervisorSendButton();
            } else {
                supervisorClient.showNotification('Error: Chat no conectado', 'error');
            }
        }

        function handleSupervisorKeyDown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                
                const input = document.getElementById('supervisorMessageInput');
                if (input && input.value.trim()) {
                    sendSupervisorMessage();
                }
            }
            updateSupervisorSendButton();
        }

        function updateSupervisorSendButton() {
            const input = document.getElementById('supervisorMessageInput');
            const button = document.getElementById('supervisorSendButton');
            
            if (input && button) {
                button.disabled = !input.value.trim() || !currentSupervisorSession;
            }
        }

        function showSupervisorEndModal() {
            document.getElementById('supervisorEndModal').classList.remove('hidden');
        }

        function executeSupervisorEnd() {
            const reason = document.getElementById('supervisorEndReason').value;
            const notes = document.getElementById('supervisorEndNotes').value.trim();
            
            supervisorClient.showNotification('Sesión de supervisión finalizada', 'success');

            hideChatToggleButton();
            closeAllMobileSidebars();
            exitSupervisorChat();
            closeModal('supervisorEndModal');
        }

        function exitSupervisorChat() {
            if (currentSupervisorSession) {
                if (supervisorChatSocket) {
                    supervisorChatSocket.disconnect();
                }
                currentSupervisorSession = null;
                supervisorClient.stopSupervisorTimer();
            }
            hideChatToggleButton();
            closeAllMobileSidebars();
            showSection('escalations');
        }

        function logout() {
            if (confirm('Cerrar sesión?')) {
                supervisorClient.destroy();
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = 'logout.php';
            }
        }

        function updateTime() {
            document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('es-ES');
        }

       // =============== SISTEMA DE NAVEGACIÓN MÓVIL MEJORADO ===============

        // Inicialización de controles móviles
        function initializeMobileControls() {
            setupMobileEventListeners();
            window.addEventListener('orientationchange', handleOrientationChange);
            window.addEventListener('resize', handleResize);
        }

        // Event Listeners principales
        function setupMobileEventListeners() {
            // Cerrar con tecla ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeMobileNav();
                    closePatientInfoSidebar();
                }
            });
        }

        // Funciones de manejo de eventos
        function handleOrientationChange() {
            setTimeout(() => {
                handleResize();
                closeMobileNav();
                closePatientInfoSidebar();
            }, 100);
        }

        function handleResize() {
            const isMobile = window.innerWidth <= 768;
            const isTablet = window.innerWidth <= 1024;
            
            // En desktop - cerrar todo
            if (!isMobile && !isTablet) {
                closeMobileNav();
                closePatientInfoSidebar();
                hideChatToggleButton(); // Ocultar botón flotante en desktop
            }
            
            // Ajustes específicos para móvil/tablet
            if (isTablet) {
                const chatContainer = document.querySelector('.chat-container');
                const headerHeight = document.querySelector('.chat-header')?.offsetHeight || 70;
                
                if (chatContainer) {
                    chatContainer.style.height = `calc(100vh - ${headerHeight}px)`;
                }

                // Mostrar botón flotante solo en supervisor chat y móvil/tablet
                const supervisorChatSection = document.getElementById('supervisor-chat-section');
                if (supervisorChatSection && !supervisorChatSection.classList.contains('hidden')) {
                    showChatToggleButton();
                }
            }
        }

        // =============== FUNCIONES DEL BOTÓN FLOTANTE ===============
        
        // Función para alternar la sidebar de información del paciente
        function togglePatientInfoSidebar() {
            const sidebar = document.getElementById('patientInfoSidebar');
            const backdrop = document.getElementById('patientInfoBackdrop');
            const floatingBtn = document.getElementById('patientInfoFloatingBtn');
            
            if (sidebar && backdrop) {
                const isOpen = sidebar.classList.contains('active');
                
                if (isOpen) {
                    // Cerrar sidebar
                    sidebar.classList.remove('active');
                    backdrop.classList.remove('active');
                    document.body.style.overflow = '';
                    
                    // Cambiar icono del botón a "mostrar"
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
                    // Abrir sidebar
                    sidebar.classList.add('active');
                    backdrop.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    
                    // Cambiar icono del botón a "cerrar"
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

        function closeAllMobileSidebars() {
            closeMobileNav();
            closePatientInfoSidebar();
            document.body.style.overflow = '';
        }

        function showChatToggleButton() {
            // Esta función muestra el botón flotante de información del paciente
            const floatingBtn = document.getElementById('patientInfoFloatingBtn');
            if (floatingBtn && window.innerWidth <= 1024) {
                floatingBtn.classList.add('show');
                console.log('📱 Botón flotante de información del paciente mostrado');
            }
        }

        function hideChatToggleButton() {
            // Esta función oculta el botón flotante de información del paciente
            const floatingBtn = document.getElementById('patientInfoFloatingBtn');
            if (floatingBtn) {
                floatingBtn.classList.remove('show');
                console.log('📱 Botón flotante de información del paciente ocultado');
            }
            // También cerrar el sidebar si está abierto
            closePatientInfoSidebar();
        }

        // Prevenir cierre al hacer click dentro de sidebars
        function initializeSidebarClickPrevention() {
            // Navegación móvil
            document.getElementById('mobileNav')?.addEventListener('click', (e) => {
                e.stopPropagation();
            });
            
            // Patient info sidebar
            document.getElementById('patientInfoSidebar')?.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }

        // Funciones globales
        window.openMobileNav = openMobileNav;
        window.closeMobileNav = closeMobileNav;
        window.openPatientInfoSidebar = openPatientInfoSidebar;
        window.closePatientInfoSidebar = closePatientInfoSidebar;
        window.handleResize = handleResize;
        window.handleOrientationChange = handleOrientationChange;
        window.togglePatientInfoSidebar = togglePatientInfoSidebar;
        window.closeAllMobileSidebars = closeAllMobileSidebars;
        window.showChatToggleButton = showChatToggleButton;
        window.hideChatToggleButton = hideChatToggleButton;

        document.addEventListener('DOMContentLoaded', async () => {
            console.log('🚀 Panel de supervisor cargado');
            
            updateTime();
            setInterval(updateTime, 1000);
            
            // Inicializar controles móviles
            initializeMobileControls();
            initializeSidebarClickPrevention();
            
            try {
                await supervisorClient.init();
                console.log('✅ SupervisorClient inicializado');
            } catch (error) {
                console.error('❌ Error inicializando:', error);
            }
        });

        window.addEventListener('beforeunload', () => {
            supervisorClient.destroy();
        });
    </script>
</body>
</html>