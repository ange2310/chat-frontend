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

$validStaffRoles = ['agent', 'supervisor', 'admin'];
if (!in_array($userRole, $validStaffRoles)) {
    header("Location: index.php?error=not_staff");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Agente - <?= htmlspecialchars($user['name'] ?? 'Staff') ?></title>
    
    <meta name="staff-token" content="<?= htmlspecialchars($_SESSION['staffJWT']) ?>">
    <meta name="staff-user" content='<?= htmlspecialchars(json_encode($user)) ?>'>
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
<style>
    body { font-family: 'Inter', sans-serif; }
    .nav-link.active { background: #2563eb; color: white; }
    .countdown-urgent { animation: pulse-red 1s infinite; }
    @keyframes pulse-red { 0%, 100% { color: #dc2626; } 50% { color: #ef4444; } }
    
    /* ============== LAYOUT RESPONSIVO BASE ============== */
    .main-container {
        display: flex;
        min-height: 100vh;
        position: relative;
    }
    
    .sidebar-main {
        width: 256px;
        min-width: 256px;
        background: white;
        border-right: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease;
        z-index: 30;
    }
    
    .content-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    
    /* ============== CHAT LAYOUT RESPONSIVO ============== */
    .chat-sidebar { 
        width: 300px; 
        min-width: 300px; 
        background: white;
        border-right: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease;
        z-index: 20;
    }
    
    .chat-main { 
        flex: 1; 
        min-width: 0; 
        display: flex;
        flex-direction: column;
    }
    
    .patient-info-sidebar { 
        width: 320px; 
        min-width: 320px; 
        background: #f9fafb;
        border-left: 1px solid #e5e7eb;
        overflow-y: auto;
        transition: transform 0.3s ease;
        z-index: 10;
    }
    
    /* ============== ELEMENTOS DE CHAT ============== */
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
        max-height: calc(100vh - 280px);
    }
    
    .chat-input-area {
        flex-shrink: 0;
        min-height: 140px;
        background: white;
        border-top: 1px solid #e5e7eb;
        padding: 1rem;
    }
    
    .typing-indicator-area {
        flex-shrink: 0;
        background-color: #f9fafb;
        padding: 0.5rem 1.5rem;
    }
    
    /* ============== SCROLLBARS ============== */
    .chat-messages::-webkit-scrollbar, .chat-sidebar-content::-webkit-scrollbar {
        width: 8px;
    }
    .chat-messages::-webkit-scrollbar-track, .chat-sidebar-content::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    .chat-messages::-webkit-scrollbar-thumb, .chat-sidebar-content::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .chat-messages::-webkit-scrollbar-thumb:hover, .chat-sidebar-content::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    /* ============== ELEMENTOS DE UI ============== */
    .chat-item {
        position: relative;
        transition: all 0.2s ease;
        flex-shrink: 0;
        padding: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
    }
    .chat-item:hover {
        background-color: #f9fafb;
        transform: translateX(2px);
    }
    .chat-item.active {
        background-color: #eff6ff;
        border-left: 3px solid #2563eb;
    }
    
    .status-indicator {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .status-unattended { background-color: #ef4444; }
    .status-mine { background-color: #2563eb; }
    .status-typing { 
        background-color: #10b981; 
        animation: pulse-typing 1.5s infinite;
    }
    .status-critical {
        background-color: #ef4444 !important;
        animation: pulse-critical 1s infinite;
    }
    .status-transferred {
        background-color: #f59e0b;
        animation: pulse-transfer 1.5s infinite;
    }
    
    @keyframes pulse-typing { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    @keyframes pulse-critical { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(1.1); } }
    @keyframes pulse-transfer { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
    
    /* ============== BOTONES MOBILE-FRIENDLY ============== */
    .mobile-toggle-btn {
        display: none;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 50;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 0.5rem;
        width: 3rem;
        height: 3rem;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        cursor: pointer;
    }
    
    .mobile-chat-toggle {
        display: none;
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 50;
        background: #059669;
        color: white;
        border: none;
        border-radius: 0.5rem;
        width: 3rem;
        height: 3rem;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        cursor: pointer;
    }
    
    .mobile-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 25;
    }
    
    /* ============== OTROS ESTILOS EXISTENTES ============== */
    .chat-preview {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .section-divider {
        border-top: 1px solid #e5e7eb;
        margin: 12px 0;
        position: relative;
        flex-shrink: 0;
    }
    .section-divider::before {
        content: attr(data-title);
        position: absolute;
        top: -8px;
        left: 12px;
        background: white;
        padding: 0 8px;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .chat-message {
        margin-bottom: 1rem;
        animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .chat-messages.auto-scroll {
        scroll-behavior: smooth;
    }
    
    .conversation-warning {
        border-left: 4px solid #f59e0b !important;
        background-color: #fef3c7 !important;
    }
    
    .conversation-critical {
        border-left: 4px solid #ef4444 !important;
        background-color: #fee2e2 !important;
        animation: glow-red 2s infinite;
    }
    
    @keyframes glow-red {
        0%, 100% { box-shadow: 0 0 5px rgba(239, 68, 68, 0.3); }
        50% { box-shadow: 0 0 20px rgba(239, 68, 68, 0.6); }
    }
    
    .conversation-recovered {
        border-left: 4px solid #10b981 !important;
        background-color: #d1fae5 !important;
    }
    
    .transfer-badge, .transfer-rejected-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        padding: 2px 6px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .transfer-badge {
        background: #f59e0b;
    }
    
    .transfer-rejected-badge {
        background: #dc2626;
    }
    
    .timer-display {
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
    
    /* RF7: Estilos para transferencia de archivos */
    .file-upload-area {
        border: 2px dashed #d1d5db;
        border-radius: 0.5rem;
        padding: 1rem;
        text-align: center;
        transition: all 0.2s ease;
        cursor: pointer;
        margin-bottom: 0.75rem;
    }
    
    .file-upload-area:hover {
        border-color: #2563eb;
        background-color: #f8fafc;
    }
    
    .file-upload-area.drag-over {
        border-color: #2563eb;
        background-color: #eff6ff;
    }
    
    .file-preview {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        background: #f3f4f6;
        border-radius: 0.375rem;
        margin-top: 0.5rem;
    }
    
    .file-progress {
        width: 100%;
        height: 4px;
        background: #e5e7eb;
        border-radius: 2px;
        overflow: hidden;
    }
    
    .file-progress-bar {
        height: 100%;
        background: #2563eb;
        transition: width 0.3s ease;
    }
    
    .attachment-button {
        position: relative;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem;
        border-radius: 0.375rem;
        transition: all 0.2s ease;
        background: #f3f4f6;
        color: #6b7280;
        border: none;
        cursor: pointer;
        min-width: 48px;
        min-height: 48px;
    }
    
    .attachment-button:hover {
        background: #e5e7eb;
        color: #374151;
    }
    
    .attachment-button input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    
    /* ============== MEDIA QUERIES RESPONSIVAS MEJORADAS ============== */
    
    /* Tablet Portrait y Mobile Landscape */
    @media (max-width: 1024px) {
        .patient-info-sidebar {
            position: fixed;
            right: -320px;
            top: 0;
            height: 100vh;
            z-index: 40;
            box-shadow: -4px 0 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .patient-info-sidebar.mobile-open {
            transform: translateX(-320px);
        }
        
        .mobile-chat-toggle.show-in-chat {
            display: flex;
        }
        
        .chat-header {
            padding-right: 5rem;
        }
        
        .chat-header .flex.items-center.gap-1 {
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        
        .chat-header .flex.items-center.gap-1 button {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
            min-width: auto;
        }
    }
    
    /* Mobile Portrait - MEJORADO */
    @media (max-width: 768px) {
        .main-container {
            flex-direction: column;
        }
        
        .sidebar-main {
            position: fixed;
            left: -256px;
            top: 0;
            height: 100vh;
            z-index: 40;
            box-shadow: 4px 0 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-main.mobile-open {
            transform: translateX(256px);
        }
        
        .content-main {
            width: 100%;
            padding-top: 0;
        }
        
        .mobile-toggle-btn {
            display: flex;
        }
        
        .mobile-overlay.active {
            display: block;
        }
        
        /* HEADER CHAT MEJORADO PARA MÓVIL */
        .chat-header {
            padding: 1rem 0.75rem;
            padding-left: 4rem; /* Espacio para botón de menú */
            padding-right: 4rem; /* Espacio para botón de info */
            min-height: 70px;
        }
        
        /* Ocultar información detallada del paciente en mobile */
        .chat-header .w-12.h-12.bg-green-100 {
            width: 2.5rem;
            height: 2.5rem;
        }
        
        .chat-header h2 {
            font-size: 1rem;
            line-height: 1.2;
        }
        
        .chat-header .text-sm {
            font-size: 0.75rem;
        }
        
        /* Botones de acción más compactos */
        .chat-header .flex.items-center.gap-1.flex-wrap {
            gap: 0.125rem;
            justify-content: flex-end;
        }
        
        .chat-header .flex.items-center.gap-1.flex-wrap button {
            padding: 0.25rem 0.375rem;
            font-size: 0.625rem;
            white-space: nowrap;
            min-width: auto;
            border-radius: 0.25rem;
        }
        
        /* CHAT LAYOUT MEJORADO PARA MÓVIL */
        .chat-container {
            flex-direction: column;
            height: calc(100vh - 70px);
        }
        
        .chat-sidebar {
            position: fixed;
            left: -300px;
            top: 70px;
            height: calc(100vh - 70px);
            z-index: 30;
            box-shadow: 4px 0 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .chat-sidebar.mobile-open {
            transform: translateX(300px);
        }
        
        .chat-main {
            width: 100%;
        }
        
        /* MENSAJES MEJORADOS PARA MÓVIL */
        .chat-messages {
            padding: 1rem 0.75rem;
            max-height: none;
            height: auto;
            flex: 1;
        }
        
        .chat-messages .max-w-xs {
            max-width: calc(100vw - 4rem);
        }
        
        .chat-messages .lg\\:max-w-md {
            max-width: calc(100vw - 4rem);
        }
        
        /* INPUT AREA MEJORADA PARA MÓVIL */
        .chat-input-area {
            padding: 0.75rem;
            min-height: 110px;
            background: white;
            border-top: 2px solid #e5e7eb;
        }
        
        .chat-input-area .flex.items-end.gap-3 {
            gap: 0.5rem;
            align-items: flex-end;
        }
        
        .chat-input-area textarea {
            min-height: 44px;
            font-size: 16px;
            padding: 0.75rem;
            border-radius: 0.5rem;
        }
        
        .chat-input-area button {
            min-width: 48px;
            min-height: 48px;
            padding: 0.75rem;
            border-radius: 0.5rem;
        }
        
        .attachment-button {
            min-width: 44px;
            min-height: 44px;
            padding: 0.625rem;
        }
        
        /* File upload responsive */
        .file-upload-area {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .file-preview-area {
            margin-bottom: 0.5rem;
        }
        
        /* MENSAJES MÁS LEGIBLES EN MÓVIL */
        .chat-message {
            margin-bottom: 0.875rem;
        }
        
        .chat-message .px-4 {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        
        .chat-message .py-2 {
            padding-top: 0.625rem;
            padding-bottom: 0.625rem;
        }
        
        /* Cards más espaciadas en móvil */
        .grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3 {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        /* Conversaciones pendientes más cómodas en móvil */
        .space-y-3 > div {
            padding: 1rem;
            border-radius: 0.75rem;
        }
        
        .space-y-3 .flex.items-center.justify-between {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .space-y-3 .flex.items-center.gap-4 {
            gap: 0.75rem;
        }
        
        .space-y-3 .text-right {
            text-align: left;
        }
        
        .space-y-3 button {
            width: 100%;
            justify-content: center;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 0.5rem;
        }
        
        /* Sidebar items más cómodos */
        .chat-item {
            min-height: 60px;
            padding: 0.875rem;
        }
        
        .chat-item .w-10.h-10 {
            width: 2.75rem;
            height: 2.75rem;
        }
    }
    
    /* Small Mobile - MEJORADO */
    @media (max-width: 480px) {
        .mobile-toggle-btn, .mobile-chat-toggle {
            width: 2.75rem;
            height: 2.75rem;
            top: 0.75rem;
        }
        
        .mobile-toggle-btn {
            left: 0.75rem;
        }
        
        .mobile-chat-toggle {
            right: 0.75rem;
        }
        
        .chat-header {
            padding: 0.75rem 0.5rem;
            padding-left: 3.75rem;
            padding-right: 3.75rem;
            min-height: 65px;
        }
        
        .chat-container {
            height: calc(100vh - 65px);
        }
        
        .chat-sidebar {
            top: 65px;
            height: calc(100vh - 65px);
            width: 90vw;
            max-width: 280px;
        }
        
        .chat-sidebar.mobile-open {
            transform: translateX(90vw);
        }
        
        .chat-header .flex.items-center.gap-1.flex-wrap button {
            padding: 0.125rem 0.25rem;
            font-size: 0.5rem;
        }
        
        .chat-messages {
            padding: 0.75rem 0.5rem;
        }
        
        .chat-input-area {
            padding: 0.625rem;
            min-height: 100px;
        }
        
        .chat-input-area textarea {
            min-height: 40px;
            font-size: 16px;
        }
        
        .chat-input-area .flex.items-end.gap-3 {
            gap: 0.375rem;
        }
        
        /* Modales responsive */
        .fixed.inset-0.bg-black.bg-opacity-50 .bg-white {
            margin: 1rem;
            max-width: calc(100vw - 2rem);
            max-height: calc(100vh - 4rem);
            overflow-y: auto;
        }
        
        /* Patient info sidebar en small mobile */
        .patient-info-sidebar {
            width: 100vw;
            right: -100vw;
        }
        
        .patient-info-sidebar.mobile-open {
            transform: translateX(-100vw);
        }
        
        /* Mensajes aún más legibles */
        .chat-messages .max-w-xs, .chat-messages .lg\\:max-w-md {
            max-width: calc(100vw - 2rem);
        }
    }
    
    /* Touch improvements */
    @media (hover: none) and (pointer: coarse) {
        .chat-item {
            min-height: 56px;
            padding: 1rem;
        }
        
        button, .cursor-pointer {
            min-height: 48px;
            min-width: 48px;
        }
        
        .nav-link {
            min-height: 52px;
            padding: 0.875rem;
        }
        
        /* Remove hover effects on touch devices */
        .chat-item:hover {
            background-color: transparent;
            transform: none;
        }
        
        .chat-item:active {
            background-color: #f9fafb;
        }
        
        /* Botones más grandes para touch */
        .chat-header .flex.items-center.gap-1.flex-wrap button {
            min-height: 36px;
            min-width: 60px;
        }
    }
    
    /* Landscape orientation fixes */
    @media (max-width: 896px) and (orientation: landscape) {
        .chat-messages {
            max-height: calc(100vh - 180px);
        }
        
        .chat-input-area {
            min-height: 90px;
        }
        
        .chat-header {
            min-height: 60px;
            padding: 0.5rem 1rem;
            padding-left: 3.5rem;
            padding-right: 3.5rem;
        }
        
        .mobile-toggle-btn, .mobile-chat-toggle {
            width: 2.5rem;
            height: 2.5rem;
            top: 0.5rem;
        }
    }
</style>
</head>
<body class="h-full bg-gray-50">
    <div class="main-container">
        
        <!-- Sidebar Principal -->
        <div class="sidebar-main">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-semibold text-gray-900">Panel de Agente</h1>
                        <p class="text-sm text-gray-500">Sistema Médico</p>
                    </div>
                </div>
            </div>
            
            <nav class="flex-1 p-4">
                <div class="space-y-1">
                    <a href="#pending" onclick="showPendingSection()" 
                       class="nav-link active flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Conversaciones Pendientes
                        <span id="pendingCount" class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">0</span>
                    </a>
                    
                    <a href="#my-chats" onclick="showMyChatsSection()" 
                       class="nav-link flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8V4a2 2 0 012 2v2z"></path>
                        </svg>
                        Mis Chats
                        <span id="myChatsCount" class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full">0</span>
                    </a>
                    
                    <a href="#rooms" onclick="showRoomsSection()" 
                       class="nav-link flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                        Ver por Salas
                    </a>
                </div>
            </nav>
                
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></p>
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

        <!-- Contenido Principal -->
        <div class="content-main">
            
            <!-- Header -->
            <header class="bg-white border-b border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h2 id="sectionTitle" class="text-xl font-semibold text-gray-900">Conversaciones Pendientes</h2>
                        <div class="flex items-center gap-4">
                            <span id="currentTime" class="text-sm text-gray-500"></span>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span class="text-sm text-gray-600">En línea</span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-auto">
                
                <!-- Conversaciones Pendientes -->
                <div id="pending-conversations-section" class="section-content p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Pacientes Esperando Atención</h3>
                                    <p class="text-sm text-gray-600 mt-1">Toma una conversación para comenzar a atender</p>
                                </div>
                                <button onclick="loadPendingConversations()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="pendingConversationsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando conversaciones pendientes...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mis Chats -->
                <div id="my-chats-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Mis Conversaciones Activas</h3>
                                    <p class="text-sm text-gray-600 mt-1">Chats que tienes asignados</p>
                                </div>
                                <button onclick="loadMyChats()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="myChatsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando mis chats...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Salas -->
                <div id="rooms-list-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Salas Disponibles</h3>
                                    <p class="text-sm text-gray-600 mt-1">Selecciona una sala para ver las sesiones</p>
                                </div>
                                <button onclick="loadRoomsFromAuthService()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="roomsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando salas...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sesiones de una Sala -->
                <div id="room-sessions-section" class="section-content hidden p-6">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <button onclick="showRoomsSection()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                        </svg>
                                    </button>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">
                                            Sesiones en: <span id="currentRoomName">Sala</span>
                                        </h3>
                                        <p class="text-sm text-gray-600">Pacientes en esta sala</p>
                                    </div>
                                </div>
                                <button onclick="loadSessionsByRoom(currentRoom)" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="sessionsContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando sesiones...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat con Paciente -->
                <div id="patient-chat-panel" class="section-content hidden">
                    <div class="chat-container">
                        <div class="flex h-full">
                            
                            <!-- Sidebar de Chats -->
                            <div class="chat-sidebar bg-white border-r border-gray-200">
                                <div class="chat-sidebar-header p-4 border-b border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-semibold text-gray-900">Conversaciones</h3>
                                        <button onclick="loadChatsSidebar()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="chatsSidebarContainer" class="chat-sidebar-content">
                                    <div class="text-center py-8 text-gray-500">
                                        <p class="text-sm">Cargando chats...</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Chat Principal -->
                            <div class="chat-main flex flex-col bg-white">
                                
                                <!-- Header del Chat -->
                                <div class="chat-header bg-white border-b border-gray-200 px-6 py-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <button onclick="window.innerWidth <= 768 ? toggleChatSidebar() : goBackToPending()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                                </svg>
                                            </button>
                                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                                <span id="chatPatientInitials" class="text-lg font-semibold text-green-700">P</span>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h2 id="chatPatientName" class="text-xl font-bold text-gray-900">Paciente</h2>
                                                    <span id="transferBadge" class="transfer-badge hidden">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                                        </svg>
                                                        Transferido
                                                    </span>
                                                    <span id="transferRejectedBadge" class="transfer-rejected-badge hidden">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                        Transferencia Rechazada
                                                    </span>
                                                </div>
                                                <p class="text-sm text-gray-500">
                                                    <span id="chatRoomName">Sala</span> • 
                                                    <span id="chatSessionStatus" class="text-green-600">Activo</span>
                                                    <span id="chatTimer" class="timer-display ml-2"></span>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- Botones de Acción -->
                                        <div class="flex items-center gap-1 flex-wrap">
                                            <button onclick="showTransferModal()" 
                                                    class="px-2 py-1.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-xs font-medium whitespace-nowrap">
                                                Transferir
                                            </button>
                                            <button onclick="showReturnModal()" 
                                                    class="px-2 py-1.5 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 text-xs font-medium whitespace-nowrap">
                                                Devolver
                                            </button>
                                            <button onclick="showEndSessionModal()" 
                                                    class="px-2 py-1.5 bg-red-600 text-white rounded-md hover:bg-red-700 text-xs font-medium whitespace-nowrap">
                                                Finalizar
                                            </button>
                                            <button onclick="showEscalationModal()" 
                                                    style="background-color: #ea580c; color: white; padding: 6px 8px; border-radius: 6px; font-size: 12px; font-weight: 500; white-space: nowrap; border: none; cursor: pointer;"
                                                    onmouseover="this.style.backgroundColor='#c2410c'" 
                                                    onmouseout="this.style.backgroundColor='#ea580c'">
                                                🚨 Escalar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contenedor de mensajes -->
                                <div class="chat-messages-container">
                                    <div class="chat-messages auto-scroll" id="patientChatMessages">
                                        <div class="text-center py-8 text-gray-500">
                                            Selecciona una conversación para comenzar
                                        </div>
                                    </div>
                                    
                                    <!-- Indicador de typing -->
                                    <div id="typingIndicator" class="typing-indicator-area hidden px-6 py-2">
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

                                <!-- Input del Chat con RF7: Upload de archivos -->
                                <div class="chat-input-area p-4">
                                    <!-- RF7: Área de carga de archivos -->
                                    <div id="fileUploadArea" class="file-upload-area hidden">
                                        <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                        <p class="text-sm text-gray-600 mb-2">Arrastra archivos aquí o haz clic para seleccionar</p>
                                        <p class="text-xs text-gray-500">Máximo 10MB - Imágenes, PDF, documentos</p>
                                        <input type="file" id="fileInput" multiple accept="image/*,.pdf,.doc,.docx,.txt,.csv,.xlsx,.xls" class="hidden">
                                    </div>
                                    
                                    <!-- RF7: Preview de archivos seleccionados -->
                                    <div id="filePreviewArea" class="hidden mb-3">
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-medium text-blue-800">Archivos seleccionados:</span>
                                                <button onclick="clearSelectedFiles()" class="text-blue-600 hover:text-blue-800 text-xs">Limpiar</button>
                                            </div>
                                            <div id="selectedFilesList" class="space-y-2"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- RF7: Barra de progreso de upload -->
                                    <div id="uploadProgressArea" class="hidden mb-3">
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-medium text-yellow-800">Subiendo archivos...</span>
                                                <span id="uploadProgressText" class="text-xs text-yellow-600">0%</span>
                                            </div>
                                            <div class="file-progress">
                                                <div id="uploadProgressBar" class="file-progress-bar" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-end gap-3">
                                        <!-- RF7: Botón de adjuntar archivos -->
                                        <button class="attachment-button" onclick="toggleFileUpload()" title="Adjuntar archivo">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13">
                                                </path>
                                            </svg>
                                        </button>
                                        
                                        <div class="flex-1">
                                            <textarea 
                                                id="agentMessageInput" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                                rows="2"
                                                placeholder="Escribe tu respuesta..."
                                                maxlength="500"
                                                onkeydown="handleAgentKeyDown(event)"
                                            ></textarea>
                                        </div>
                                        <button 
                                            id="agentSendButton"
                                            onclick="sendMessage()" 
                                            disabled
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex justify-between items-center mt-2 text-xs text-gray-500">
                                        <span>Enter para enviar, Shift+Enter para nueva línea</span>
                                        <span id="chatStatus">Desconectado</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Información del Paciente -->
                            <div class="patient-info-sidebar">
                                <div class="p-6">
                                    
                                    <!-- Información Personal -->
                                    <div class="mb-6">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información del Paciente</h3>
                                        
                                        <div class="space-y-3">
                                            <div>
                                                <label class="text-sm font-medium text-gray-500">Nombre</label>
                                                <p id="patientInfoName" class="text-sm text-gray-900">-</p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium text-gray-500">Documento</label>
                                                <p id="patientInfoDocument" class="text-sm text-gray-900">-</p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium text-gray-500">Teléfono</label>
                                                <p id="patientInfoPhone" class="text-sm text-gray-900">-</p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium text-gray-500">Email</label>
                                                <p id="patientInfoEmail" class="text-sm text-gray-900">-</p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium text-gray-500">Ciudad</label>
                                                <p id="patientInfoCity" class="text-sm text-gray-900">-</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Información de Membresía -->
                                    <div class="mb-6">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Membresía</h3>
                                        
                                        <div class="space-y-3">
                                            <div>
                                                <label class="text-sm font-medium text-gray-500">EPS</label>
                                                <p id="patientInfoEPS" class="text-sm text-gray-900">-</p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium text-gray-500">Plan</label>
                                                <p id="patientInfoPlan" class="text-sm text-gray-900">-</p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium text-gray-500">Estado</label>
                                                <p id="patientInfoStatus" class="text-sm text-gray-900">-</p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium text-gray-500">Tomador</label>
                                                <p id="patientInfoTomador" class="text-sm text-gray-900">-</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ID de Sesión -->
                                    <div class="bg-gray-100 rounded-lg p-3">
                                        <div class="text-xs text-gray-500 mb-1">ID de Sesión</div>
                                        <div id="chatPatientId" class="text-xs font-mono text-gray-700">-</div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Transferencia -->
    <div id="transferModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Transferir Sesión</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                    <select id="transferType" class="w-full px-3 py-2 border border-gray-300 rounded-lg" onchange="toggleTransferFields()">
                        <option value="external">Transferencia Externa</option>
                        <option value="internal">Transferencia Interna</option>
                    </select>
                </div>
                
                <div id="externalTransferFields">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sala Destino</label>
                    <select id="targetRoom" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="general">Consultas Generales</option>
                        <option value="medical">Consultas Médicas</option>
                        <option value="support">Soporte Técnico</option>
                        <option value="emergency">Emergencias</option>
                    </select>
                </div>
                
                <div id="internalTransferFields" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Agente Destino</label>
                    <select id="targetAgentSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Cargando agentes...</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo</label>
                    <textarea id="transferReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg" rows="3" placeholder="Motivo de la transferencia..." required></textarea>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('transferModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Cancelar
                </button>
                <button onclick="executeTransfer()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Transferir
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Finalizar Sesión -->
    <div id="endSessionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Finalizar Sesión</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo</label>
                    <select id="endReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="completed_by_agent">Consulta completada</option>
                        <option value="patient_resolved">Problema resuelto</option>
                        <option value="patient_disconnected">Paciente desconectado</option>
                        <option value="technical_issues">Problemas técnicos</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notas (opcional)</label>
                    <textarea id="endNotes" class="w-full px-3 py-2 border border-gray-300 rounded-lg" rows="3" placeholder="Resumen de la consulta..."></textarea>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('endSessionModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg">
                    Cancelar
                </button>
                <button onclick="executeEndSession()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Finalizar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Devolver -->
    <div id="returnModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Devolver a Cola</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo</label>
                    <select id="returnReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="need_specialist">Necesita especialista</option>
                        <option value="technical_issues">Problemas técnicos</option>
                        <option value="patient_unavailable">Paciente no disponible</option>
                        <option value="other">Otro motivo</option>
                    </select>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('returnModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg">
                    Cancelar
                </button>
                <button onclick="executeReturn()" class="flex-1 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                    Devolver
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Escalación -->
    <div id="escalationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">🚨 Escalar a Supervisor</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo de Escalación</label>
                    <select id="escalationReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="complex_case">Caso médico complejo</option>
                        <option value="patient_complaint">Queja del paciente</option>
                        <option value="multiple_transfers">Múltiples transferencias fallidas</option>
                        <option value="technical_issue">Problema técnico grave</option>
                        <option value="urgent_decision">Decisión urgente requerida</option>
                        <option value="other">Otro motivo</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción Detallada</label>
                    <textarea id="escalationDescription" class="w-full px-3 py-2 border border-gray-300 rounded-lg" rows="4" 
                            placeholder="Describe por qué necesitas que un supervisor intervenga..." required></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                    <select id="escalationPriority" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="medium">Media - Supervisor puede revisar cuando esté disponible</option>
                        <option value="high">Alta - Supervisor debe revisar pronto</option>
                        <option value="urgent">Urgente - Supervisor debe intervenir inmediatamente</option>
                    </select>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModal('escalationModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Cancelar
                </button>
                <button onclick="executeEscalation()" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                    🚨 Escalar Ahora
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    
    <script>
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

        // Variables globales
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
        
        // Control de duplicación mejorado con persistencia por sesión
        let sentMessages = new Set();
        let messageIdCounter = 0;
        let sessionMessageIds = new Map();

        // RF7: Variables para manejo de archivos
        let selectedFiles = [];
        let isFileUploadVisible = false;
        let currentUpload = null;

        // Funciones para mostrar/ocultar botón de info del paciente en móvil
        function showPatientInfoButton() {
            const chatToggle = document.getElementById('mobileChatToggle');
            if (chatToggle && window.innerWidth <= 1024) {
                chatToggle.classList.add('show-in-chat');
            }
        }

        function hidePatientInfoButton() {
            const chatToggle = document.getElementById('mobileChatToggle');
            if (chatToggle) {
                chatToggle.classList.remove('show-in-chat');
                // También cerrar el sidebar si está abierto
                const patientSidebar = document.querySelector('.patient-info-sidebar');
                if (patientSidebar) {
                    patientSidebar.classList.remove('mobile-open');
                }
            }
        }
        // Funciones de utilidad
        function getToken() {
            return '<?= $_SESSION['staffJWT'] ?>';
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

        // RF7: Funciones para manejo de archivos
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
            const maxSize = 10 * 1024 * 1024; // 10MB
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
                console.log('Validando archivo:', {
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    sizeInMB: (file.size / (1024 * 1024)).toFixed(2)
                });
                
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
                
                console.log('Archivo válido:', file.name);
                validFiles.push(file);
            }
            
            if (errors.length > 0) {
                console.error('Errores de validación:', errors);
                showNotification(errors.join('\n'), 'error', 6000);
            }
            
            if (validFiles.length > 0) {
                console.log(`${validFiles.length} archivos válidos seleccionados`);
                selectedFiles = [...selectedFiles, ...validFiles];
                displaySelectedFiles(); // Esto llama updateSendButton()
                document.getElementById('fileUploadArea').classList.add('hidden');
                isFileUploadVisible = false;
            }
        }

        function displaySelectedFiles() {
            const previewArea = document.getElementById('filePreviewArea');
            const filesList = document.getElementById('selectedFilesList');
            
            if (selectedFiles.length === 0) {
                previewArea.classList.add('hidden');
                updateSendButton(); // ✅ Actualizar cuando no hay archivos
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
            
            updateSendButton(); // ✅ Actualizar cuando hay archivos
        }

        function removeSelectedFile(index) {
            selectedFiles.splice(index, 1);
            displaySelectedFiles(); // Esto ya llama updateSendButton()
        }

        function clearSelectedFiles() {
            selectedFiles = [];
            displaySelectedFiles(); // Esto ya llama updateSendButton()
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
                    console.log(`Subiendo archivo ${i + 1}/${selectedFiles.length}:`, file.name);
                    
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
                    
                    console.log('Respuesta del servidor:', response.status, response.statusText);
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('Error del servidor:', errorText);
                        throw new Error(`Error subiendo ${file.name}: ${response.status} ${response.statusText}`);
                    }
                    
                    const result = await response.json();
                    console.log('📋 Resultado COMPLETO del upload:', result);
                    
                    if (result.success) {
                        // ✅ EXTRACCIÓN ROBUSTA DEL FILE_ID
                        let fileId = null;
                        let downloadUrl = null;
                        
                        // Buscar file_id en múltiples lugares posibles
                        if (result.data && result.data.file && result.data.file.id) {
                            fileId = result.data.file.id;
                            downloadUrl = result.data.file.download_url;
                            console.log('✅ File ID encontrado en result.data.file.id:', fileId);
                        } else if (result.data && result.data.file_id) {
                            fileId = result.data.file_id;
                            downloadUrl = result.data.download_url;
                            console.log('✅ File ID encontrado en result.data.file_id:', fileId);
                        } else if (result.data && result.data.id) {
                            fileId = result.data.id;
                            downloadUrl = result.data.download_url;
                            console.log('✅ File ID encontrado en result.data.id:', fileId);
                        } else if (result.file_id) {
                            fileId = result.file_id;
                            downloadUrl = result.download_url;
                            console.log('✅ File ID encontrado en result.file_id:', fileId);
                        } else {
                            console.error('❌ NO SE ENCONTRÓ FILE_ID en la respuesta:', result);
                            console.log('🔍 Estructura de result.data:', result.data);
                            if (result.data && result.data.file) {
                                console.log('🔍 Estructura de result.data.file:', result.data.file);
                            }
                            throw new Error('No se pudo obtener el ID del archivo del servidor');
                        }
                        
                        const fileName = file.name;
                        
                        // ✅ TRACKING TEMPORAL
                        trackRecentAgentUpload(fileName);
                        
                        // ✅ CREAR OBJETO CON DATOS COMPLETOS
                        const completeFileData = {
                            id: fileId,                    // ✅ ID real del servidor
                            original_name: fileName,
                            file_size: file.size,
                            file_type: file.type,
                            download_url: downloadUrl || `/files/download/${fileId}`,
                            preview_url: `/files/preview/${fileId}`
                        };
                        
                        uploadedFiles.push(completeFileData);
                        
                        console.log('✅ Archivo procesado exitosamente:', {
                            fileName: fileName,
                            fileId: fileId,
                            downloadUrl: completeFileData.download_url,
                            previewUrl: completeFileData.preview_url,
                            tracked: window.recentAgentUploads.has(fileName)
                        });
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
                
                console.log(`✅ Upload completado: ${uploadedFiles.length} archivos subidos exitosamente`);
                console.log('📄 Archivos finales:', uploadedFiles);
                return uploadedFiles;
                
            } catch (error) {
                console.error('❌ Error en upload:', error);
                showNotification('Error subiendo archivos: ' + error.message, 'error');
                uploadArea.classList.add('hidden');
                return [];
            }
        }

        function openFileInNewTab(url, fileName) {
            console.log('🔍 Abriendo archivo en vista previa:', { url, fileName });
            
            if (!url || url === '#') {
                showNotification('URL de archivo no válida', 'error');
                return;
            }
            
            // ✅ VERIFICAR QUE LA URL SEA VÁLIDA
            try {
                new URL(url, window.location.origin); // Validar URL
            } catch (error) {
                console.error('URL inválida:', url, error);
                showNotification('URL de archivo inválida: ' + url, 'error');
                return;
            }
            
            try {
                console.log('🚀 Abriendo ventana para:', url);
                
                // Abrir en nueva pestaña para previsualización
                const newWindow = window.open(url, '_blank', 'noopener,noreferrer');
                
                if (newWindow) {
                    newWindow.focus();
                    showNotification(`Abriendo vista previa de ${fileName}`, 'info', 2000);
                } else {
                    // Fallback si el popup fue bloqueado
                    console.log('📎 Popup bloqueado, usando fallback');
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
                console.error('Error abriendo archivo:', error);
                showNotification('Error abriendo archivo: ' + error.message, 'error');
            }
        }

        // Control de duplicación mejorado
        function clearMessageDuplicationControl() {
            if (currentSession && currentSession.id) {
                if (sentMessages.size > 0) {
                    sessionMessageIds.set(currentSession.id, new Set(sentMessages));
                    console.log(`Guardados ${sentMessages.size} IDs de mensajes para sesión ${currentSession.id}`);
                }
            }
            sentMessages.clear();
            messageIdCounter = 0;
            console.log('Control de duplicación limpiado');
        }

        function restoreMessageDuplicationControl(sessionId) {
            const session = currentSession;
            const isTransferred = session && session.transfer_info && session.transfer_info.transferred_to_me;
            const isRecovered = session && session.transfer_info && session.transfer_info.transfer_rejected;
            
            if ((!isTransferred && !isRecovered) && sessionMessageIds.has(sessionId)) {
                const sessionMessages = sessionMessageIds.get(sessionId);
                sessionMessages.forEach(id => sentMessages.add(id));
                console.log(`Restaurados ${sessionMessages.size} IDs de mensajes para sesión ${sessionId}`);
            } else if (isTransferred || isRecovered) {
                console.log(`Sesión ${isTransferred ? 'transferida' : 'recuperada'} ${sessionId} - permitiendo renderizado completo del historial`);
            } else {
                console.log(`No hay IDs guardados para sesión ${sessionId} - empezando limpio`);
            }
        }

        function clearDuplicationForTransferredChat() {
            sentMessages.clear();
            messageIdCounter = 0;
            console.log('Control de duplicación limpiado para chat transferido/recuperado');
        }

        // Función mejorada para generar ID único que funciona para todos los tipos de mensaje
        function generateUniqueMessageId(data) {
            const userId = data.user_id || data.sender_id || 'unknown';
            const userType = data.user_type || data.sender_type || 'unknown';
            const messageType = data.message_type || 'text';
            const timestamp = Math.floor((data.timestamp || data.created_at || Date.now()) / 1000);
            
            // Para archivos, incluir file_id si está disponible para mayor unicidad
            if (messageType === 'file' && data.file_data && data.file_data.id) {
                return `${userId}_${userType}_${messageType}_${data.file_data.id}_${timestamp}`;
            }
            
            // Para mensajes de texto, incluir hash del contenido (sin btoa para evitar errores con caracteres especiales)
            const content = (data.content || '').substring(0, 50);
            let contentHash = '';
            for (let i = 0; i < content.length; i++) {
                contentHash += content.charCodeAt(i).toString(16);
            }
            contentHash = contentHash.substring(0, 10);
            
            return `${userId}_${userType}_${messageType}_${contentHash}_${timestamp}`;
        }

        // Funciones de navegación
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

        function goBackToPending() {
            if (currentSession) {
                if (chatSocket) {
                    chatSocket.disconnect();
                }
                currentSession = null;
                stopSessionTimer();
                clearMessageDuplicationControl();
                clearSelectedFiles(); // RF7: Limpiar archivos seleccionados
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
                'patient-chat-panel'
            ];
            
            sections.forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (section) {
                    section.classList.add('hidden');
                }
            });
        }

        // Funciones de salas
        async function loadRoomsFromAuthService() {
    try {
        const token = getToken();
        if (!token) {
            console.warn('No hay token disponible, usando salas de prueba');
            return loadRoomsFallback();
        }
        
        const currentUser = getCurrentUser();
        
        // PASO 1: Intentar Admin-Service para salas asignadas
        try {
            console.log('🔍 Intentando obtener salas asignadas del admin-service...');
            
            const agentRoomsResponse = await fetch(`${ADMIN_API}/agent-assignments/my-rooms`, {
                method: 'GET',
                headers: getAuthHeaders()
            });

            if (agentRoomsResponse.ok) {
                const agentRoomsResult = await agentRoomsResponse.json();
                
                if (agentRoomsResult.success && agentRoomsResult.data) {
                    console.log('✅ Admin-service: Salas asignadas obtenidas exitosamente');
                    
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
                        
                        showNotification(`✅ Tienes acceso a ${assignmentInfo.total_rooms} sala(s) asignada(s)`, 'success', 4000);
                        displayRooms();
                        return rooms;
                    }
                }
            } else {
                console.log(`❌ Admin-service no disponible (${agentRoomsResponse.status})`);
            }
        } catch (adminError) {
            console.log('❌ Admin-service no disponible:', adminError.message);
        }
        
        // PASO 2: Intentar Auth-Service con ID de agente
        if (currentUser && currentUser.id) {
            try {
                console.log('🔍 Intentando auth-service con ID de agente...');
                
                const agentSpecificUrl = `${AUTH_API}/rooms/available/${currentUser.id}`;
                const agentResponse = await fetch(agentSpecificUrl, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (agentResponse.ok) {
                    const agentData = await agentResponse.json();
                    
                    if (agentData.success && agentData.data?.rooms) {
                        console.log('✅ Auth-service específico: Salas obtenidas');
                        
                        rooms = agentData.data.rooms;
                        
                        if (agentData.data.assignment_status === 'assigned_rooms_only') {
                            showNotification(`📋 Mostrando ${agentData.data.assigned_rooms_count} sala(s) asignada(s)`, 'info', 4000);
                        } else {
                            showNotification('🏢 Sin asignaciones - acceso a todas las salas', 'info', 4000);
                        }
                        
                        displayRooms();
                        return rooms;
                    }
                } else {
                    console.log(`❌ Auth-service específico falló (${agentResponse.status})`);
                }
            } catch (agentSpecificError) {
                console.log('❌ Auth-service específico falló:', agentSpecificError.message);
            }
        }
        
        // PASO 3: Intentar Auth-Service general
        try {
            console.log('🔍 Intentando auth-service general...');
            
            const generalResponse = await fetch(`${AUTH_API}/rooms/available`, {
                method: 'GET',
                headers: getAuthHeaders()
            });

            if (generalResponse.ok) {
                const generalData = await generalResponse.json();
                const roomsData = generalData.data?.rooms || generalData.rooms || [];
                
                if (Array.isArray(roomsData) && roomsData.length > 0) {
                    console.log('✅ Auth-service general: Salas obtenidas');
                    
                    rooms = roomsData;
                    showNotification('🔄 Usando lista general de salas', 'warning', 3000);
                    displayRooms();
                    return roomsData;
                }
            } else {
                console.log(`❌ Auth-service general falló (${generalResponse.status})`);
            }
        } catch (generalError) {
            console.log('❌ Auth-service general falló:', generalError.message);
        }
        
        // PASO 4: Fallback completo
        console.log('🔄 Usando fallback - salas de prueba');
        showNotification('⚠️ Servicios no disponibles - usando salas de prueba', 'warning', 5000);
        return loadRoomsFallback();
        
    } catch (error) {
        console.error('❌ Error general cargando salas:', error);
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
                console.error('Error seleccionando sala:', error);
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
                console.error(`Error cargando sesiones para ${roomId}:`, error);
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
                console.error('Error tomando sesión:', error);
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
                console.error('Error continuando sesión:', error);
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

        function updateNavigation(active) {
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                link.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            
            let activeLink;
            switch(active) {
                case 'pending':
                    activeLink = document.querySelector('a[href="#pending"]');
                    break;
                case 'my-chats':
                    activeLink = document.querySelector('a[href="#my-chats"]');
                    break;
                case 'rooms':
                    activeLink = document.querySelector('a[href="#rooms"]');
                    break;
            }
            
            if (activeLink) {
                activeLink.classList.add('active');
                activeLink.classList.remove('text-gray-600', 'hover:bg-gray-100');
            }
        }

        // Funciones de timer
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

        // Funciones de actualización en tiempo real
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

        async function updateSidebarCounts() {
    try {
        const currentUser = getCurrentUser();
        
        // INTENTAR: Usar filtros de asignación primero
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
                
                // Actualizar contadores
                const pendingCountElement = document.getElementById('pendingCount');
                if (pendingCountElement) {
                    pendingCountElement.textContent = activePending.length;
                }
                
                const myChatsCountElement = document.getElementById('myChatsCount');
                if (myChatsCountElement) {
                    myChatsCountElement.textContent = activeChats.length;
                }
                
                return; // Salir si funcionó
            }
        } catch (assignmentError) {
            console.log('🔄 Actualizando contadores con método original');
        }
        
        // FALLBACK: método original
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
            }
            
            if (myChatsResult.success && myChatsResult.data) {
                const activeChats = myChatsResult.data.sessions.filter(session => 
                    session.status === 'active' && session.agent_id === currentUser.id
                );
                
                const myChatsCountElement = document.getElementById('myChatsCount');
                if (myChatsCountElement) {
                    myChatsCountElement.textContent = activeChats.length;
                }
            }
        }
    } catch (error) {
        console.error('❌ Error actualizando contadores:', error);
    }
}

        function updateAllConversationTimers() {
            conversationTimers.forEach((timer, sessionId) => {
                updateConversationTimerDisplay(sessionId, timer.startTime, timer.type);
            });
        }

        // Funciones de carga de datos
        async function loadPendingConversations() {
            const container = document.getElementById('pendingConversationsContainer');
            const countBadge = document.getElementById('pendingCount');
            
            if (!container || !countBadge) return;
            
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-500">Cargando conversaciones pendientes...</p>
                </div>
            `;

            try {
                console.log('🔍 Cargando conversaciones pendientes con filtros de asignación...');
                
                // 🔧 USAR NUEVA RUTA CON FILTROS DE ASIGNACIÓN
                const response = await fetch(`${ADMIN_API}/agent-assignments/my-sessions?status=waiting&limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success && result.data) {
                    const { sessions, assignment_info, agent } = result.data;
                    
                    console.log('✅ Sesiones filtradas cargadas:', {
                        total: sessions.length,
                        access_type: assignment_info.access_type,
                        assigned_rooms: assignment_info.assigned_rooms,
                        room_filter_applied: assignment_info.room_filter_applied
                    });

                    // Mostrar información de filtrado al usuario
                    if (assignment_info.room_filter_applied) {
                        showNotification(
                            `📋 Mostrando sesiones de tus ${assignment_info.active_rooms} sala(s) asignada(s)`, 
                            'info', 
                            4000
                        );
                    } else if (assignment_info.access_type === 'all_rooms_access') {
                        showNotification(
                            '🏢 Sin asignaciones específicas - mostrando todas las sesiones', 
                            'info', 
                            3000
                        );
                    }
                    
                    // Filtrar sesiones realmente pendientes (por si acaso)
                    const activeConversations = sessions.filter(session => {
                        const isWaiting = session.status === 'waiting' && !session.agent_id;
                        return isWaiting;
                    });
                    
                    pendingConversations = activeConversations;
                    countBadge.textContent = activeConversations.length;
                    
                    if (activeConversations.length === 0) {
                        container.innerHTML = `
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">¡Todo al día!</h3>
                                <p class="text-gray-500 mb-2">No hay conversaciones pendientes en tus salas asignadas</p>
                                ${assignment_info.room_filter_applied ? 
                                    `<p class="text-xs text-gray-400">Salas asignadas: ${assignment_info.active_rooms}</p>` :
                                    `<p class="text-xs text-gray-400">Acceso a todas las salas disponibles</p>`
                                }
                            </div>
                        `;
                    } else {
                        renderPendingConversations(activeConversations);
                    }
                } else {
                    throw new Error('Formato de respuesta inválido');
                }
                
            } catch (error) {
                console.error('❌ Error cargando conversaciones pendientes:', error);
                countBadge.textContent = '!';
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
        async function loadAgentAssignmentInfo() {
    try {
        console.log('Intentando obtener información de asignaciones...');
        
        const response = await fetch(`${ADMIN_API}/agent-assignments/my-info`, {
            method: 'GET',
            headers: getAuthHeaders()
        });

        if (response.ok) {
            const result = await response.json();
            if (result.success && result.data) {
                console.log('✓ Información de asignaciones del agente obtenida');
                return result.data;
            }
        } else {
            console.log(`Endpoint de asignaciones no disponible (${response.status})`);
        }
    } catch (error) {
        console.log('Admin-service no disponible para asignaciones:', error.message);
    }
    
    // FALLBACK: Retornar que no tiene asignaciones específicas
    return {
        agent: getCurrentUser(),
        assignments: {
            has_assignments: false,
            total_assignments: 0,
            rooms: []
        },
        access_level: 'all_rooms_access'
    };
}
        async function checkAgentRoomAccess(roomId) {
    try {
        const response = await fetch(`${ADMIN_API}/agent-assignments/check-room-access/${roomId}`, {
            method: 'GET',
            headers: getAuthHeaders()
        });

        if (response.ok) {
            const result = await response.json();
            if (result.success && result.data) {
                return result.data;
            }
        }
    } catch (error) {
        console.log('Error verificando acceso a sala:', error.message);
    }
    
    // FALLBACK: Permitir acceso si no se puede verificar
    return { has_access: true, access_type: 'fallback_allow_all' };
}

        // Función mejorada para cargar sesiones con filtros de asignación
        async function loadSessionsWithAssignmentFilter(options = {}) {
    const { waiting = false, agent_specific = true } = options;
    
    try {
        // INTENTAR: Usar endpoint específico de agente si está disponible
        if (agent_specific) {
            try {
                const agentSessionsUrl = `${CHAT_API}/chats/my-sessions`;
                const agentParams = new URLSearchParams();
                
                if (waiting) {
                    agentParams.append('status', 'waiting');
                } else {
                    agentParams.append('status', 'active');
                }
                
                console.log('Intentando cargar sesiones específicas del agente...');
                
                const agentResponse = await fetch(`${agentSessionsUrl}?${agentParams.toString()}`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });
                
                if (agentResponse.ok) {
                    const agentResult = await agentResponse.json();
                    
                    if (agentResult.success && agentResult.data) {
                        console.log('✓ Sesiones cargadas con filtros de asignación');
                        return agentResult.data.sessions || [];
                    }
                } else if (agentResponse.status === 403 || agentResponse.status === 404) {
                    console.log('Endpoint específico de agente no disponible, usando método general');
                } else {
                    console.warn('Error en endpoint específico:', agentResponse.status);
                }
            } catch (agentError) {
                console.log('Endpoint específico de agente falló:', agentError.message);
            }
        }
        
        // FALLBACK: Usar método original
        console.log('Usando método general para cargar sesiones');
        
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
                console.log('✓ Sesiones cargadas (método general):', result.data.sessions.length);
                return result.data.sessions;
            }
        } else {
            console.error('Error en método general:', response.status);
        }
        
        return [];
        
    } catch (error) {
        console.error('Error cargando sesiones:', error);
        return [];
    }
}
        function renderPendingConversations(conversations) {
            const container = document.getElementById('pendingConversationsContainer');
            
            const html = conversations.map((conv) => {
                const waitTime = getTimeAgo(conv.created_at);
                const convStatus = getConversationStatus(conv.created_at);
                const urgencyClass = getAdvancedUrgencyClass(conv.created_at);
                const patientName = getPatientNameFromSession(conv);
                const roomName = getRoomNameFromSession(conv);
                
                // Verificar si es una conversación recuperada por transferencia rechazada
                const isRecovered = conv.transfer_info && conv.transfer_info.transfer_rejected;
                const recoveredClass = isRecovered ? 'conversation-recovered' : '';
                
                return `
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors ${urgencyClass.borderClass} ${recoveredClass}">
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
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-semibold text-gray-900">${patientName}</h4>
                                        ${isRecovered ? `
                                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                                                Recuperado
                                            </span>
                                        ` : ''}
                                    </div>
                                    <p class="text-sm text-gray-600">${roomName}</p>
                                    <p class="text-xs text-gray-500">ID: ${conv.id}</p>
                                    ${convStatus.status !== 'normal' ? `
                                        <p class="text-xs ${urgencyClass.statusTextClass} font-medium">
                                            ${getExpirationMessage(convStatus)}
                                        </p>
                                    ` : ''}
                                    ${isRecovered ? `
                                        <p class="text-xs text-green-600 font-medium">
                                            ✅ Transferencia rechazada - Conversación recuperada
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
                                        ${convStatus.status === 'critical' ? '🚨 Tomar Urgente' : isRecovered ? '🔄 Retomar' : 'Tomar'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = `<div class="space-y-3">${html}</div>`;
        }

        async function loadMyChats() {
            const container = document.getElementById('myChatsContainer');
            const countBadge = document.getElementById('myChatsCount');
            
            if (!container || !countBadge) return;
            
            container.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-500">Cargando mis chats...</p>
                </div>
            `;

            try {
                console.log('🔍 Cargando mis chats con filtros de asignación...');
                
                // 🔧 USAR NUEVA RUTA CON FILTROS DE ASIGNACIÓN
                const response = await fetch(`${ADMIN_API}/agent-assignments/my-sessions?status=active&limit=50`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success && result.data) {
                    const { sessions, assignment_info } = result.data;
                    
                    console.log('✅ Mis chats filtrados cargados:', {
                        total: sessions.length,
                        access_type: assignment_info.access_type
                    });
                    
                    // Filtrar solo chats activos propios
                    const currentUser = getCurrentUser();
                    myChats = sessions.filter(session => 
                        session.status === 'active' && session.agent_id === currentUser.id
                    );
                    
                    countBadge.textContent = myChats.length;
                    
                    if (myChats.length === 0) {
                        container.innerHTML = `
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Sin chats activos</h3>
                                <p class="text-gray-500">No tienes conversaciones activas en este momento</p>
                            </div>
                        `;
                    } else {
                        renderMyChats(myChats);
                    }
                } else {
                    throw new Error('Formato de respuesta inválido');
                }
                
            } catch (error) {
                console.error('❌ Error cargando mis chats:', error);
                countBadge.textContent = '!';
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

        function renderMyChats(chats) {
            const container = document.getElementById('myChatsContainer');
            
            const html = chats.map(chat => {
                const patientName = getPatientNameFromSession(chat);
                const roomName = getRoomNameFromSession(chat);
                const activeTime = getTimeAgo(chat.updated_at || chat.created_at);
                
                const isTransferred = chat.transfer_info && chat.transfer_info.transferred_to_me;
                const isRecovered = chat.transfer_info && chat.transfer_info.transfer_rejected;
                const transferInfo = chat.transfer_info || {};
                
                return `
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors ${isRecovered ? 'conversation-recovered' : ''}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-lg font-semibold text-green-700">
                                        ${patientName.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-semibold text-gray-900">${patientName}</h4>
                                        ${isTransferred ? `
                                            <span class="transfer-badge">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                                </svg>
                                                Transferido
                                            </span>
                                        ` : ''}
                                        ${isRecovered ? `
                                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                                                Recuperado
                                            </span>
                                        ` : ''}
                                    </div>
                                    <p class="text-sm text-gray-600">${roomName}</p>
                                    <p class="text-xs text-gray-500">ID: ${chat.id}</p>
                                    <p class="text-xs text-green-600 timer-display">Activo: ${activeTime}</p>
                                    ${isTransferred ? `
                                        <p class="text-xs text-orange-600">
                                            Transferido desde: ${transferInfo.from_agent_name || 'Agente'} • ${transferInfo.reason || 'Sin motivo'}
                                        </p>
                                    ` : ''}
                                    ${isRecovered ? `
                                        <p class="text-xs text-green-600">
                                            ✅ Transferencia rechazada - Chat recuperado con historial completo
                                        </p>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <button 
                                    onclick="openChatFromMyChats('${chat.id}')"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                                    ${isRecovered ? 'Continuar' : 'Continuar'}
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = `<div class="space-y-3">${html}</div>`;
        }

        async function loadChatsSidebar() {
    const container = document.getElementById('chatsSidebarContainer');
    
    if (!container) return;

    try {
        const currentUser = getCurrentUser();
        if (!currentUser || !currentUser.id) {
            throw new Error('Usuario actual no válido');
        }
        
        console.log('🔍 Cargando sidebar...');
        
        // INTENTAR: Usar funciones con filtros de asignación
        let myActiveChats = [];
        let allPendingChats = [];
        
        try {
            // Cargar con filtros
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
            
            console.log('✅ Sidebar cargado con filtros');
            
        } catch (assignmentError) {
            console.log('🔄 Fallback: usando método original para sidebar');
            
            // Fallback al método original
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
                
                console.log('✅ Sidebar cargado con método original');
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
        console.error('❌ Error loading chats sidebar:', error);
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
                    
                    const isTransferred = chat.transfer_info && chat.transfer_info.transferred_to_me;
                    const isRecovered = chat.transfer_info && chat.transfer_info.transfer_rejected;
                    
                    if (!conversationTimers.has(chat.id)) {
                        startConversationTimer(chat.id, chat.updated_at || chat.created_at, 'active');
                    }
                    
                    html += `
                        <div class="chat-item ${isActive ? 'active' : ''} p-3 border-b border-gray-100 cursor-pointer ${isRecovered ? 'conversation-recovered' : ''}" 
                             onclick="selectChatFromSidebar('${chat.id}')" data-chat-id="${chat.id}">
                            <div class="status-indicator ${isTransferred ? 'status-transferred' : isRecovered ? 'status-mine' : 'status-mine'}"></div>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-sm font-semibold text-blue-700">
                                        ${patientName.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1">
                                        <h4 class="font-medium text-gray-900 text-sm truncate">${patientName}</h4>
                                        ${isTransferred ? `
                                            <span class="text-xs bg-orange-100 text-orange-700 px-1 rounded">T</span>
                                        ` : ''}
                                        ${isRecovered ? `
                                            <span class="text-xs bg-green-100 text-green-700 px-1 rounded">R</span>
                                        ` : ''}
                                    </div>
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
                    const isRecovered = chat.transfer_info && chat.transfer_info.transfer_rejected;
                    
                    if (!conversationTimers.has(chat.id)) {
                        startConversationTimer(chat.id, chat.created_at, 'waiting');
                    }
                    
                    html += `
                        <div class="chat-item p-3 border-b border-gray-100 cursor-pointer ${urgencyClasses.borderClass} ${isRecovered ? 'conversation-recovered' : ''}" 
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
                                    <div class="flex items-center gap-1">
                                        <h4 class="font-medium text-gray-900 text-sm truncate">${patientName}</h4>
                                        ${isRecovered ? `
                                            <span class="text-xs bg-green-100 text-green-700 px-1 rounded">R</span>
                                        ` : ''}
                                    </div>
                                    <p class="text-xs text-gray-500 truncate">${roomName}</p>
                                    <p class="conversation-timer-${chat.id} text-xs text-red-600 timer-display">
                                        Esperando ${waitTime}
                                        ${convStatus.status === 'critical' ? ' ⚠️' : ''}
                                        ${isRecovered ? ' 🔄' : ''}
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

        // Funciones de obtención de datos del paciente
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
            
            // 🔧 NUEVO: Primero intentar usar el room_name que viene del backend (JOIN con chat_rooms)
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
            
            // 🔧 MEJORADO: Como fallback, usar el mapeo existente pero mejorado
            let roomId = session.room_id || session.roomId || session.room || session.type;
            
            if (roomId) {
                // Mapeo estático mejorado (mantener compatibilidad con room_types antiguos)
                const roomNames = {
                    // IDs numéricos antiguos
                    '1': 'Consultas Generales',
                    '2': 'Consultas Médicas',
                    '3': 'Soporte Técnico', 
                    '4': 'Emergencias',
                    
                    // Room types antiguos
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
                    
                    // Variaciones en mayúsculas
                    'GENERAL': 'Consultas Generales',
                    'MEDICAL': 'Consultas Médicas',
                    'SUPPORT': 'Soporte Técnico',
                    'EMERGENCY': 'Emergencias',
                    
                    // Nombres completos
                    'Consultas Generales': 'Consultas Generales',
                    'Consultas Médicas': 'Consultas Médicas',
                    'Soporte Técnico': 'Soporte Técnico',
                    'Emergencias': 'Emergencias'
                };
                
                const roomIdString = String(roomId).trim();
                
                // Buscar coincidencia exacta
                if (roomNames[roomId]) {
                    return roomNames[roomId];
                }
                
                // Buscar coincidencia insensible a mayúsculas/minúsculas
                const roomIdLower = roomIdString.toLowerCase();
                if (roomNames[roomIdLower]) {
                    return roomNames[roomIdLower];
                }
                
                // Buscar coincidencias parciales
                for (const [key, value] of Object.entries(roomNames)) {
                    if (key.toLowerCase().includes(roomIdLower) || roomIdLower.includes(key.toLowerCase())) {
                        return value;
                    }
                }
                
                // 🔧 NUEVO: Para UUIDs, generar nombre descriptivo en lugar de genérico
                if (isValidUUID(roomIdString)) {
                    console.warn(`UUID de sala sin mapeo: ${roomIdString}. Usando nombre genérico.`);
                    return 'Sala Especializada';
                }
                
                // Formatear room_type como último recurso
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
                console.error('Error fetchPatientDataFromPToken:', error);
                throw error;
            }
        }

        async function getPatientInfoWithPToken(session) {
            let patientInfo = extractPatientInfo(session);
            
            if (isPatientInfoEmpty(patientInfo) && session.ptoken) {
                try {
                    console.log('Consultando información del paciente con ptoken:', session.ptoken);
                    const ptokenData = await fetchPatientDataFromPToken(session.ptoken);
                    if (ptokenData) {
                        patientInfo = ptokenData;
                        console.log('Información del paciente obtenida desde ptoken');
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

            // Mostrar badges de transferencia o recuperación solo cuando corresponde
            const transferBadge = document.getElementById('transferBadge');
            const transferRejectedBadge = document.getElementById('transferRejectedBadge');
            
            if (session.transfer_info) {
                if (session.transfer_info.transferred_to_me) {
                    if (transferBadge) transferBadge.classList.remove('hidden');
                    if (transferRejectedBadge) transferRejectedBadge.classList.add('hidden');
                } else if (session.transfer_info.transfer_rejected) {
                    if (transferBadge) transferBadge.classList.add('hidden');
                    if (transferRejectedBadge) transferRejectedBadge.classList.remove('hidden');
                } else {
                    if (transferBadge) transferBadge.classList.add('hidden');
                    if (transferRejectedBadge) transferRejectedBadge.classList.add('hidden');
                }
            } else {
                if (transferBadge) transferBadge.classList.add('hidden');
                if (transferRejectedBadge) transferRejectedBadge.classList.add('hidden');
            }

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

        // Funciones de chat principales
        async function takeConversation(sessionId) {
            try {
                const conversation = pendingConversations.find(c => c.id === sessionId);
                if (!conversation) {
                    throw new Error('Conversación no encontrada en la lista local');
                }
                
                await takeConversationWithSession(sessionId, conversation);
                
            } catch (error) {
                console.error('Error tomando conversación:', error);
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
                        
                        // Verificar si es una conversación recuperada
                        if (conversation.transfer_info && conversation.transfer_info.transfer_rejected) {
                            showNotification('Conversación recuperada exitosamente - Historial completo disponible', 'success');
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
                console.error('Error tomando conversación:', error);
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
                    console.error('Conversación no encontrada:', sessionId);
                    showNotification('Conversación no encontrada', 'error');
                    return;
                }
                
                await takeConversationWithSession(sessionId, conversation);
                
                setTimeout(() => {
                    loadChatsSidebar();
                }, 1000);
                
            } catch (error) {
                console.error('Error tomando conversación desde sidebar:', error);
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
                    console.error('Sesión no encontrada en mis chats:', sessionId);
                    showNotification('Sesión no encontrada', 'error');
                    return;
                }
                
                await openChatDirectly(session);
            } catch (error) {
                console.error('Error abriendo chat desde mis chats:', error);
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
                    console.error('Sesión no encontrada en mis chats:', sessionId);
                    showNotification('Sesión no encontrada', 'error');
                    return;
                }
                
                await openChatDirectly(session);
            } catch (error) {
                console.error('Error seleccionando chat desde sidebar:', error);
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
                
                console.log('Abriendo chat:', session.id, 'Transferido:', isTransferred, 'Recuperado:', isRecovered);
                
                // Si cambiamos de sesión, limpiar control de duplicación
                if (currentSession && currentSession.id !== session.id) {
                    clearMessageDuplicationControl();
                    console.log('Cambiando de chat - control de duplicación limpiado');
                }
                
                if (isTransferred || isRecovered) {
                    clearDuplicationForTransferredChat();
                    sessionMessageIds.delete(session.id);
                    // Para chats recuperados, también limpiar el cache para forzar recarga desde servidor
                    if (isRecovered) {
                        chatHistoryCache.delete(session.id);
                        console.log(`Chat recuperado ${session.id} - cache eliminado para recarga completa`);
                    }
                    console.log(`Chat ${isRecovered ? 'recuperado' : 'transferido'} detectado - control de duplicación limpiado`);
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
                
                // Mostrar notificación especial para chats recuperados
                if (isRecovered) {
                    setTimeout(() => {
                        showNotification('💬 Conversación recuperada: Historial completo con mensajes y archivos disponible', 'success', 6000);
                    }, 1000);
                }
                
            } catch (error) {
                console.error('Error abriendo chat directamente:', error);
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
                
                // ✅ CONFIGURAR EVENTOS Y ACTUALIZAR BOTÓN
                setupFileUploadEvents();
                updateSendButton();

                // AGREGAR ESTA LÍNEA:
                showPatientInfoButton();

                return session;

            } catch (error) {
                console.error('Error abriendo chat:', error);
                showNotification('Error al abrir chat: ' + error.message, 'error');
                throw error;
            }
        }

        // Funciones de WebSocket
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
                    
                    setTimeout(() => {
                        joinChatSession();
                    }, 500);
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
                
                chatSocket.on('error', (error) => {
                    console.error('Error en socket de chat:', error);
                    showNotification('Error en chat: ' + (error.message || error), 'error');
                });
                
            } catch (error) {
                console.error('Error conectando WebSocket de chat:', error);
                throw error;
            }
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
                // ✅ MENSAJES DE TEXTO
                if (message) {
                    console.log('💬 Enviando mensaje de texto del agente:', message);
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

                // ✅ ARCHIVOS - CON DATOS COMPLETOS DEL SERVIDOR
                if (hasFiles) {
                    console.log(`📁 Procesando ${selectedFiles.length} archivos del agente...`);
                    const uploadedFiles = await uploadFiles(); // ← Esto ya devuelve datos completos
                    
                    if (uploadedFiles.length > 0) {
                        for (const file of uploadedFiles) {
                            const uniqueFileId = `local_agent_file_${Date.now()}_${Math.random().toString(36).substr(2, 9)}_${file.id}`;
                            
                            console.log('📤 Renderizando archivo del agente con datos del servidor:', {
                                fileName: file.original_name,
                                fileId: file.id,           // ✅ Ahora tiene ID real
                                downloadUrl: file.download_url,
                                previewUrl: file.preview_url,
                                uniqueFileId: uniqueFileId
                            });
                            
                            // Validar que tenemos datos completos
                            if (!file.id) {
                                console.error('❌ ARCHIVO SIN ID:', file);
                                showNotification(`Error: Archivo ${file.original_name} no tiene ID válido`, 'error');
                                continue;
                            }
                            
                            // Marcar como renderizado localmente
                            locallyRenderedFiles.add(uniqueFileId);
                            locallyRenderedFiles.add(file.id);
                            locallyRenderedFiles.add(`agent_${file.id}`);
                            
                            // ✅ RENDERIZAR CON DATOS COMPLETOS
                            addFileMessageToChat(file.original_name, file, true, uniqueFileId);
                            
                            // Enviar por WebSocket
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
                    } else {
                        console.warn('⚠️ No se subieron archivos exitosamente');
                    }
                }
                
                input.value = '';
                
            } catch (error) {
                console.error('❌ Error en sendMessage:', error);
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
            
            console.log('🔍 WebSocket mensaje recibido:', {
                messageType: data.message_type,
                user_id: data.user_id,
                sender_id: data.sender_id,
                user_type: data.user_type,
                sender_type: data.sender_type,
                currentUserId: currentUser.id,
                local_agent_message: data.local_agent_message,
                local_agent_file: data.local_agent_file
            });

            // ✅ PROTECCIÓN PARA MIS MENSAJES
            const isMyMessage = (
                data.user_id === currentUser.id ||
                data.sender_id === currentUser.id ||
                data.local_agent_message === true ||
                data.local_agent_file === true ||
                data.agent_rendered_locally === true ||
                (data.unique_file_id && locallyRenderedFiles.has(data.unique_file_id))
            );

            if (isMyMessage) {
                console.log('✅ IGNORANDO mi propio mensaje');
                return;
            }

            // ✅ PROCESAR ARCHIVOS
            if (data.message_type === 'file' || data.file_data?.id) {
                console.log('📥 Procesando archivo externo (no mío)');
                
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

            // ✅ PROCESAR MENSAJES DE TEXTO
            const normalizedMessage = {
                user_id: data.user_id || data.sender_id,
                user_type: data.user_type || data.sender_type,
                user_name: data.user_name || data.sender_name || 'Usuario',
                content: data.content || '',
                timestamp: data.timestamp || data.created_at || Date.now(),
                message_type: data.message_type || 'text'
            };

            const messageId = generateUniqueMessageId(normalizedMessage);
            
            // Control de duplicación
            const isTransferred = currentSession?.transfer_info?.transferred_to_me;
            const isRecovered = currentSession?.transfer_info?.transfer_rejected;
            
            if ((!isTransferred && !isRecovered) && sentMessages.has(messageId)) {
                console.log('Mensaje duplicado ignorado:', messageId);
                return;
            }
            
            sentMessages.add(messageId);

            // Renderizar mensaje
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
            console.log('🕒 Tracking archivo del agente:', fileName);
            
            // Remover después de 15 segundos (más tiempo por seguridad)
            setTimeout(() => {
                window.recentAgentUploads.delete(fileName);
                console.log('🕒 Removido del tracking:', fileName);
            }, 15000);
        }

        // ✅ Función auxiliar para limpiar tracking al cambiar de sesión
        function clearFileTracking() {
            if (window.recentAgentUploads) {
                window.recentAgentUploads.clear();
            }
            locallyRenderedFiles.clear();
            selectedFiles = [];
            console.log('🧹 File tracking limpiado');
        }

        // ✅ Llamar clearFileTracking cuando se cambie de sesión
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
            clearFileTracking(); 
            currentSession = null;
            hidePatientInfoButton();
            showPendingSection();
        }

        function renderMyTextMessage(message) {
            const messagesContainer = document.getElementById('patientChatMessages');
            if (!messagesContainer) return;

            const currentTime = new Date();
            const time = currentTime.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });

            // Generar ID único para evitar duplicaciones posteriores
            const messageId = generateUniqueMessageId({
                user_id: getCurrentUser().id,
                user_type: 'agent',
                content: message,
                timestamp: currentTime.getTime(),
                message_type: 'text'
            });

            // Marcar como procesado para evitar duplicación
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
            
            // Actualizar cache
            updateChatHistoryCache({
                user_id: getCurrentUser().id,
                user_type: 'agent',
                user_name: getCurrentUser().name,
                content: message,
                message_type: 'text'
            }, messageId, currentTime);

            console.log('✅ Mensaje de texto del agente renderizado localmente');
        }
                
        function handleFileUploaded(data) {
            try {
                if (data.session_id !== currentSession.id) {
                    console.log('❌ Archivo de sesión diferente ignorado');
                    return;
                }
                
                const currentUser = getCurrentUser();
                
                console.log('📁 handleFileUploaded - Datos recibidos:', {
                    fileName: data.file_name,
                    fileId: data.file_id,
                    
                    // ✅ BACKEND CORREGIDO: Ahora envía datos correctos
                    user_id: data.user_id,
                    uploaded_by: data.uploaded_by,
                    uploader_id: data.uploader_id,
                    sender_id: data.sender_id,
                    
                    user_type: data.user_type,
                    uploader_type: data.uploader_type,
                    sender_type: data.sender_type,
                    
                    currentUserId: currentUser.id,
                    
                    // Marcadores del frontend
                    local_agent_file: data.local_agent_file,
                    unique_file_id: data.unique_file_id,
                    
                    // Tracking temporal
                    isInRecentUploads: window.recentAgentUploads && window.recentAgentUploads.has(data.file_name),
                    
                    // Debug info del backend
                    debug_info: data.debug_info
                });
                
                // ✅ DETECCIÓN SIMPLIFICADA - El backend ahora envía datos correctos
                const isMyAgentFile = (
                    // 1. Verificación directa por IDs (backend corregido)
                    (data.user_id === currentUser.id && data.user_type === 'agent') ||
                    (data.uploaded_by === currentUser.id && data.uploader_type === 'agent') ||
                    (data.sender_id === currentUser.id && data.sender_type === 'agent') ||
                    (data.uploader_id === currentUser.id && data.user_type === 'agent') ||
                    
                    // 2. Marcadores del frontend
                    data.local_agent_file === true ||
                    data.agent_rendered_locally === true ||
                    (data.unique_file_id && locallyRenderedFiles.has(data.unique_file_id)) ||
                    (data.file_id && locallyRenderedFiles.has(data.file_id)) ||
                    
                    // 3. Tracking temporal como respaldo
                    (window.recentAgentUploads && window.recentAgentUploads.has(data.file_name))
                );
                
                if (isMyAgentFile) {
                    console.log('✅ IGNORANDO mi propio archivo de agente');
                    return;
                }
                
                // ✅ PROCESAR ARCHIVO EXTERNO
                console.log('📥 Procesando archivo externo:', {
                    fileName: data.file_name,
                    from: data.user_type === 'agent' ? 'Otro Agente' : 'Paciente',
                    user_type: data.user_type,
                    uploader_type: data.uploader_type,
                    sender_type: data.sender_type
                });
                
                const fileData = {
                    id: data.file_id,
                    original_name: data.file_name,
                    file_size: data.file_size,
                    file_type: data.file_type,
                    download_url: data.preview_url || data.download_url || `${FILE_API}/preview/${data.file_id}`
                };
                
                // ✅ DETERMINACIÓN SIMPLE DEL REMITENTE (backend corregido)
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
                console.error('❌ Error procesando archivo recibido:', error);
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

            console.log('✅ Renderizando archivo:', {
                fileName: fileName,
                isAgentMessage: isAgentMessage,
                messageId: messageId,
                fileData: fileData,
                hasFileId: !!fileData?.id
            });

            const currentTime = new Date();
            const time = currentTime.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'mb-4';
            messageDiv.dataset.messageId = messageId;

            // ✅ CONSTRUIR URL DE PREVIEW CON VALIDACIÓN
            let previewUrl = '#';
            let canPreview = false;
            
            if (fileData?.id && fileData.id !== 'undefined') {
                // ✅ VALIDAR QUE EL ID NO SEA 'undefined' como string
                previewUrl = `${FILE_API}/preview/${fileData.id}`;
                canPreview = canPreviewFile(fileName, fileData?.file_type);
                console.log('📎 URL de preview construida:', previewUrl);
            } else if (fileData?.preview_url && fileData.preview_url !== 'undefined') {
                previewUrl = fileData.preview_url;
                canPreview = canPreviewFile(fileName, fileData?.file_type);
                console.log('📎 URL de preview desde preview_url:', previewUrl);
            } else if (fileData?.download_url && fileData.download_url !== 'undefined') {
                if (fileData.download_url.includes('/download/')) {
                    previewUrl = fileData.download_url.replace('/download/', '/preview/');
                } else {
                    previewUrl = fileData.download_url;
                }
                canPreview = canPreviewFile(fileName, fileData?.file_type);
                console.log('📎 URL de preview desde download_url:', previewUrl);
            } else {
                console.warn('⚠️ No se pudo construir URL de preview para:', {
                    fileName: fileName,
                    fileId: fileData?.id,
                    previewUrl: fileData?.preview_url,
                    downloadUrl: fileData?.download_url
                });
            }

            // ✅ VALIDAR URL ANTES DE CREAR BOTÓN
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

        // Función para cargar historial con soporte completo de archivos
        async function loadChatHistory() {
            if (!currentSession || !currentSession.id) return;
            
            const messagesContainer = document.getElementById('patientChatMessages');
            if (!messagesContainer) return;

            messagesContainer.innerHTML = '';
            
            const isTransferred = currentSession.transfer_info && currentSession.transfer_info.transferred_to_me;
            const isRecovered = currentSession.transfer_info && currentSession.transfer_info.transfer_rejected;
            
            // Para chats transferidos o recuperados, siempre cargar historial completo desde servidor
            if (isTransferred || isRecovered) {
                console.log(`Chat ${isRecovered ? 'recuperado' : 'transferido'} detectado - cargando historial completo desde servidor`);
                clearDuplicationForTransferredChat();
                
                try {
                    const response = await fetch(`${CHAT_API}/messages/${currentSession.id}?limit=100`, {
                        headers: getAuthHeaders()
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        
                        if (result.success && result.data && result.data.messages) {
                            console.log(`Historial del servidor: ${result.data.messages.length} mensajes para chat ${isRecovered ? 'recuperado' : 'transferido'}`);
                            
                            // Limpiar cache y mensajes duplicados para renderizado completo
                            chatHistoryCache.delete(currentSession.id);
                            sentMessages.clear();
                            
                            // Crear nuevo cache
                            chatHistoryCache.set(currentSession.id, []);
                            const cache = chatHistoryCache.get(currentSession.id);
                            
                            let renderedCount = 0;
                            result.data.messages.forEach((msg) => {
                                try {
                                    const currentUser = getCurrentUser();
                                    
                                    // RF7: Manejar archivos
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
                            
                            console.log(`Historial completo renderizado para chat ${isRecovered ? 'recuperado' : 'transferido'}: ${renderedCount} mensajes`);
                            
                            setTimeout(() => {
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            }, 100);
                        }
                    } else {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                } catch (error) {
                    console.error('Error cargando historial desde servidor:', error);
                    showNotification('Error cargando historial de chat', 'warning');
                }
                return;
            }
            
            // Para chats normales, usar cache si existe
            if (chatHistoryCache.has(currentSession.id)) {
                console.log('Cargando historial desde cache para sesión:', currentSession.id);
                const cachedHistory = chatHistoryCache.get(currentSession.id);
                
                // Limpiar sentMessages para evitar duplicaciones al cambiar de chat
                sentMessages.clear();
                
                let renderedCount = 0;
                let errorCount = 0;
                
                cachedHistory.forEach((item, index) => {
                    try {
                        console.log(`Procesando mensaje ${index + 1}/${cachedHistory.length}:`, {
                            type: item.type,
                            messageId: item.messageId,
                            content: item.content ? item.content.substring(0, 30) + '...' : 'archivo'
                        });
                        
                        if (item.type === 'file') {
                            // Para archivos, siempre renderizar desde cache
                            if (item.messageId) {
                                sentMessages.add(item.messageId);
                            }
                            addFileMessageToChatFromHistory(item.fileName, item.fileData, item.isMine, item.timestamp);
                            renderedCount++;
                            console.log(`✓ Archivo renderizado: ${item.fileName}`);
                        } else {
                            // Para mensajes de texto, siempre renderizar desde cache
                            if (item.messageId) {
                                sentMessages.add(item.messageId);
                            }
                            renderTextMessageFromHistory(item);
                            renderedCount++;
                            console.log(`✓ Mensaje de texto renderizado: ${item.content.substring(0, 50)}...`);
                        }
                    } catch (error) {
                        errorCount++;
                        console.error(`Error renderizando mensaje ${index + 1}:`, error, item);
                    }
                });
                
                console.log(`Historial cargado desde cache: ${cachedHistory.length} mensajes totales, ${renderedCount} renderizados, ${errorCount} errores`);
                
                setTimeout(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }, 100);
                
                return;
            }
            
            // Cargar desde servidor para chats normales sin cache
            try {
                console.log('Cargando historial desde servidor para sesión:', currentSession.id);
                
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
                                
                                // RF7: Mejorar detección de archivos
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
                        
                        console.log(`Historial cargado desde servidor: ${result.data.messages.length} mensajes totales, ${renderedCount} renderizados`);
                        
                        setTimeout(() => {
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        }, 100);
                    }
                } else {
                    throw new Error(`Error HTTP ${response.status}`);
                }
            } catch (error) {
                console.error('Error cargando historial:', error);
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

            // ✅ USAR ENDPOINT DE PREVIEW EN LUGAR DE DOWNLOAD
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
            
            // Verificar por extensión de archivo
            if (fileName_lower.match(/\.(pdf|jpg|jpeg|png|gif|bmp|webp|txt|csv|json|xml|log|html|md)$/)) {
                return true;
            }
            
            // Verificar por tipo MIME
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

        // Funciones de modales y acciones
        async function showTransferModal() {
            document.getElementById('transferModal').classList.remove('hidden');
            await loadAvailableAgentsForTransfer();
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

            console.log('🚨 Escalando a supervisor:', {
                session_id: currentSession.id,
                reason: reason,
                priority: priority
            });

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
                
                // Limpiar información previa
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
                
                // Limpiar información previa
                const contextArea = document.getElementById('transferContextInfo');
                if (contextArea) {
                    contextArea.remove();
                }
            }
        }

        function populateAgentSelectOptimized(data) {
            const agentSelect = document.getElementById('targetAgentSelect');
            const { agents, stats, session, transfer_info } = data;
            
            // Limpiar select
            agentSelect.innerHTML = '';
            
            if (!agents || agents.length === 0) {
                agentSelect.innerHTML = `
                    <option value="">❌ No hay agentes en la sala "${session.room_name}"</option>
                `;
                showNotification(`No hay otros agentes disponibles en la sala "${session.room_name}"`, 'warning', 4000);
                return;
            }
            
            // Opción por defecto
            agentSelect.innerHTML = `
                <option value="">📋 Selecciona un agente de "${session.room_name}" (${stats.available}/${stats.total} disponibles)</option>
            `;
            
            // Agrupar agentes por estado
            const agentsByStatus = {
                available: agents.filter(a => a.status === 'available'),
                at_capacity: agents.filter(a => a.status === 'at_capacity'),
                out_of_schedule: agents.filter(a => a.status === 'out_of_schedule'),
                error: agents.filter(a => a.status === 'error')
            };
            
            // 🔧 AGENTES DISPONIBLES (prioritarios)
            if (agentsByStatus.available.length > 0) {
                const availableGroup = document.createElement('optgroup');
                availableGroup.label = `✅ Disponibles Ahora (${agentsByStatus.available.length})`;
                
                agentsByStatus.available.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.id;
                    
                    const capacityInfo = `${agent.current_sessions}/${agent.max_concurrent_chats}`;
                    const primaryBadge = agent.is_primary_agent ? ' 👑' : '';
                    const recentBadge = agent.is_recently_active ? ' 🟢' : ' 🔘';
                    
                    option.textContent = `${agent.name}${primaryBadge}${recentBadge} (${capacityInfo} sesiones)`;
                    option.title = `${agent.name} - ${agent.availability_reason}\nCapacidad: ${capacityInfo}\nPrioridad: ${agent.priority}`;
                    
                    availableGroup.appendChild(option);
                });
                
                agentSelect.appendChild(availableGroup);
            }
            
            // 🔧 AGENTES CON CAPACIDAD LIMITADA
            if (agentsByStatus.at_capacity.length > 0) {
                const capacityGroup = document.createElement('optgroup');
                capacityGroup.label = `⚠️ Sin Capacidad (${agentsByStatus.at_capacity.length})`;
                
                agentsByStatus.at_capacity.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.id;
                    option.disabled = true; // Deshabilitado porque no tiene capacidad
                    
                    const capacityInfo = `${agent.current_sessions}/${agent.max_concurrent_chats}`;
                    const primaryBadge = agent.is_primary_agent ? ' 👑' : '';
                    
                    option.textContent = `${agent.name}${primaryBadge} (${capacityInfo} - LLENO)`;
                    option.title = `${agent.name} - ${agent.availability_reason}`;
                    
                    capacityGroup.appendChild(option);
                });
                
                agentSelect.appendChild(capacityGroup);
            }
            
            // 🔧 AGENTES FUERA DE HORARIO
            if (agentsByStatus.out_of_schedule.length > 0) {
                const scheduleGroup = document.createElement('optgroup');
                scheduleGroup.label = `⏰ Fuera de Horario (${agentsByStatus.out_of_schedule.length})`;
                
                agentsByStatus.out_of_schedule.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.id;
                    option.disabled = true; // Deshabilitado porque está fuera de horario
                    
                    const capacityInfo = agent.current_sessions !== undefined ? ` (${agent.current_sessions}/${agent.max_concurrent_chats})` : '';
                    const primaryBadge = agent.is_primary_agent ? ' 👑' : '';
                    
                    option.textContent = `${agent.name}${primaryBadge}${capacityInfo} - FUERA DE HORARIO`;
                    option.title = `${agent.name} - ${agent.availability_reason}`;
                    
                    scheduleGroup.appendChild(option);
                });
                
                agentSelect.appendChild(scheduleGroup);
            }
            
            // 🔧 AGENTES CON ERROR
            if (agentsByStatus.error.length > 0) {
                const errorGroup = document.createElement('optgroup');
                errorGroup.label = `❌ Error (${agentsByStatus.error.length})`;
                
                agentsByStatus.error.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.id;
                    option.disabled = true;
                    
                    option.textContent = `${agent.name} - ERROR`;
                    option.title = `${agent.name} - ${agent.availability_reason}`;
                    
                    errorGroup.appendChild(option);
                });
                
                agentSelect.appendChild(errorGroup);
            }
            
            // Notificación de éxito
            const availableCount = stats.available;
            if (availableCount > 0) {
                showNotification(`✅ ${availableCount} agente(s) disponible(s) en "${session.room_name}"`, 'success', 3000);
            } else {
                showNotification(`⚠️ No hay agentes disponibles en "${session.room_name}" en este momento`, 'warning', 4000);
            }
        }

        // 🔧 NUEVA FUNCIÓN: Mostrar información contextual de la transferencia
        function showTransferContextInfo(data) {
            const { session, stats, room_context, transfer_info } = data;
            
            // Buscar o crear área de información contextual
            let contextArea = document.getElementById('transferContextInfo');
            if (!contextArea) {
                contextArea = document.createElement('div');
                contextArea.id = 'transferContextInfo';
                contextArea.className = 'bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4';
                
                // Insertar antes del select de agentes
                const transferTypeDiv = document.getElementById('internalTransferFields');
                if (transferTypeDiv) {
                    transferTypeDiv.insertBefore(contextArea, transferTypeDiv.firstChild);
                }
            }
            
            contextArea.innerHTML = `
                <div class="text-sm">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="font-medium text-blue-800">🏢 Transferencia Interna en:</span>
                        <span class="font-semibold">${session.room_name}</span>
                        <span class="text-blue-600">(${session.room_id})</span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <div>
                            <span class="text-blue-600">📊 Disponibilidad:</span>
                            <div class="ml-2">
                                <div class="flex justify-between">
                                    <span>✅ Disponibles:</span>
                                    <span class="font-medium text-green-600">${stats.available}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>⚠️ Sin capacidad:</span>
                                    <span class="font-medium text-yellow-600">${stats.at_capacity}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>⏰ Fuera de horario:</span>
                                    <span class="font-medium text-orange-600">${stats.out_of_schedule}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <span class="text-blue-600">👥 Agentes en sala:</span>
                            <div class="ml-2">
                                <div class="flex justify-between">
                                    <span>👑 Primarios:</span>
                                    <span class="font-medium">${stats.primary_agents}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>🟢 Activos recientes:</span>
                                    <span class="font-medium">${stats.recently_active}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>📋 Total en sala:</span>
                                    <span class="font-medium">${room_context.total_agents_in_room}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${stats.available === 0 ? `
                        <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs">
                            <span class="text-yellow-800">💡 Tip: No hay agentes disponibles ahora. Considera usar transferencia externa o esperar.</span>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        // 🔧 NUEVA FUNCIÓN: Mostrar errores de transferencia
        function showTransferError(message) {
            const agentSelect = document.getElementById('targetAgentSelect');
            agentSelect.innerHTML = `<option value="">❌ ${message}</option>`;
            agentSelect.disabled = true;
            
            // Mostrar información de error
            let contextArea = document.getElementById('transferContextInfo');
            if (!contextArea) {
                contextArea = document.createElement('div');
                contextArea.id = 'transferContextInfo';
                contextArea.className = 'bg-red-50 border border-red-200 rounded-lg p-3 mb-4';
                
                const transferTypeDiv = document.getElementById('internalTransferFields');
                if (transferTypeDiv) {
                    transferTypeDiv.insertBefore(contextArea, transferTypeDiv.firstChild);
                }
            }
            
            contextArea.innerHTML = `
                <div class="text-sm text-red-800">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-red-600">❌</span>
                        <span class="font-medium">Error en Transferencia Interna</span>
                    </div>
                    <p class="text-xs ml-6">${message}</p>
                </div>
            `;
            
            showNotification(`Error: ${message}`, 'error', 5000);
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
                
                console.log('Cargando agentes para transferencia interna...', {
                    session_id: currentSession.id,
                    current_agent: getCurrentUser().id
                });
                
                const response = await fetch(`${CHAT_API}/chats/available-agents/transfer?session_id=${currentSession.id}&exclude_agent_id=${getCurrentUser().id}`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success && result.data) {
                        console.log('Agentes obtenidos exitosamente', {
                            total: result.data.total,
                            available: result.data.available_now,
                            room: result.data.session.room_name
                        });
                        
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
                console.error('Error cargando agentes:', error);
                agentSelect.innerHTML = '<option value="">Error cargando agentes</option>';
                agentSelect.disabled = false;
                showNotification('Error cargando agentes: ' + error.message, 'error');
            }
        }
        // Función de fallback simple
        async function loadAgentsFallback() {
            const agentSelect = document.getElementById('targetAgentSelect');
            
            try {
                // Usar endpoint general de agentes
                const response = await fetch(`${CHAT_API}/chats/agents/available`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (response.ok) {
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.agents) {
                        const currentUser = getCurrentUser();
                        const availableAgents = result.data.agents
                            .filter(agent => agent.id !== currentUser.id)
                            .map(agent => ({
                                id: agent.id,
                                name: agent.name,
                                status: 'available'
                            }));
                        
                        populateAgentSelect(availableAgents);
                        agentSelect.disabled = false;
                        showNotification('ℹ️ Lista básica de agentes cargada', 'info', 3000);
                        return;
                    }
                }
                
                // Fallback final: lista estática
                agentSelect.innerHTML = `
                    <option value="">Selecciona un agente...</option>
                    <option value="escalate">🚨 Escalar a Supervisor</option>
                `;
                agentSelect.disabled = false;
                showNotification('⚠️ No se pudieron cargar agentes - usa escalación', 'warning', 4000);
                
            } catch (error) {
                console.error('Error en fallback:', error);
                agentSelect.innerHTML = '<option value="">Error - Intenta de nuevo</option>';
                agentSelect.disabled = false;
            }
        }

        function populateAgentSelect(agents, sessionInfo) {
            const agentSelect = document.getElementById('targetAgentSelect');
            
            agentSelect.innerHTML = '';
            
            if (!agents || agents.length === 0) {
                agentSelect.innerHTML = '<option value="">No hay agentes disponibles en esta sala</option>';
                showNotification(`No hay otros agentes en la sala "${sessionInfo.room_name}"`, 'warning');
                return;
            }
            
            // Opción por defecto
            agentSelect.innerHTML = `<option value="">Selecciona un agente...</option>`;
            
            // Añadir agentes disponibles
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
            
            // Añadir agentes sin capacidad (deshabilitados)
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
        async function loadAvailableAgents() {
            try {
                const response = await fetch(`${CHAT_API}/chats/agents/available`, {
                    method: 'GET',
                    headers: getAuthHeaders()
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}`);
                }

                const result = await response.json();
                if (result.success && result.data && result.data.agents) {
                    const currentUser = getCurrentUser();
                    return result.data.agents.filter(agent => agent.id !== currentUser.id);
                } else {
                    throw new Error('No se pudieron cargar los agentes');
                }

            } catch (error) {
                console.error('Error cargando agentes:', error);
                showNotification('Error cargando lista de agentes', 'warning');
                return [];
            }
        }

        async function getAgentsForInternalTransfer(sessionId, currentAgentId) {
            try {
                const response = await fetch(`/api/chat/available-agents/transfer?session_id=${sessionId}&exclude_agent_id=${currentAgentId}`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`,
                    'Content-Type': 'application/json'
                }
                });

                const result = await response.json();
                
                if (result.success) {
                return result.data;
                } else {
                throw new Error(result.message);
                }
            } catch (error) {
                console.error('Error obteniendo agentes para transferencia:', error);
                throw error;
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
            clearSelectedFiles(); // RF7: Limpiar archivos seleccionados
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

            const response = await fetch(`${CHAT_API}/chats/sessions/${currentSession.id}/end`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify({
                    reason: reason,
                    notes: notes
                })
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Error HTTP ${response.status}`);
            }

            const result = await response.json();
            if (result.success) {
                showNotification('Sesión finalizada exitosamente', 'success');
                disconnectFromCurrentSession();
            }
            return result;
        }

        async function returnToQueue(reason) {
            if (!currentSession || !currentSession.id) {
                throw new Error('No hay sesión activa');
            }

            try {
                const response = await fetch(`${CHAT_API}/chats/sessions/${currentSession.id}/return`, {
                    method: 'PUT',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({
                        reason: reason
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `Error HTTP ${response.status}`);
                }

                const result = await response.json();
                if (result.success) {
                    showNotification('Sesión devuelta a cola', 'success');
                    disconnectFromCurrentSession();
                }
                return result;

            } catch (error) {
                console.error('Error devolviendo a cola:', error);
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
            clearSelectedFiles(); // RF7: Limpiar archivos
            
            hideAllSections();
            document.getElementById('patient-chat-panel').classList.remove('hidden');
            document.getElementById('sectionTitle').textContent = 'Panel de Chat';
            
            document.getElementById('chatPatientName').textContent = 'Selecciona una conversación';
            document.getElementById('chatPatientInitials').textContent = '?';
            document.getElementById('chatPatientId').textContent = '';
            document.getElementById('chatRoomName').textContent = '';
            document.getElementById('chatSessionStatus').textContent = '';
            document.getElementById('chatTimer').textContent = '';
            
            const transferBadge = document.getElementById('transferBadge');
            const transferRejectedBadge = document.getElementById('transferRejectedBadge');
            if (transferBadge) transferBadge.classList.add('hidden');
            if (transferRejectedBadge) transferRejectedBadge.classList.add('hidden');
            
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
            
            // RF7: Ocultar areas de upload
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
                
                console.log('🔄 Actualizando botón de envío:', {
                    hasText: hasText,
                    hasFiles: hasFiles,
                    selectedFilesCount: selectedFiles ? selectedFiles.length : 0,
                    isConnected: isConnected,
                    currentSession: !!currentSession
                });
                
                // ✅ HABILITAR si hay texto O archivos (o ambos) Y está conectado
                const shouldEnable = (hasText || hasFiles) && isConnected;
                button.disabled = !shouldEnable;
                
                // Cambiar ícono y color según el contenido
                if (hasFiles && !hasText) {
                    // Solo archivos - Ícono de adjunto
                    button.innerHTML = `
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                        </svg>`;
                    button.title = `Enviar ${hasFiles} archivo(s)`;
                    button.className = button.className.replace(/bg-\w+-\d+/g, '') + ' bg-green-600 hover:bg-green-700';
                } else if (hasText && hasFiles) {
                    // Texto + archivos - Ícono mixto
                    button.innerHTML = `
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>`;
                    button.title = `Enviar mensaje y ${hasFiles} archivo(s)`;
                    button.className = button.className.replace(/bg-\w+-\d+/g, '') + ' bg-blue-600 hover:bg-blue-700';
                } else {
                    // Solo texto - Ícono de envío normal
                    button.innerHTML = `
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>`;
                    button.title = 'Enviar mensaje';
                    button.className = button.className.replace(/bg-\w+-\d+/g, '') + ' bg-blue-600 hover:bg-blue-700';
                }
                
                console.log('✅ Botón actualizado:', {
                    disabled: button.disabled,
                    shouldEnable: shouldEnable,
                    title: button.title
                });
            }
        }
        function setupAgentTyping() {
            const agentInput = document.getElementById('agentMessageInput');
            if (!agentInput) return;
            
            agentInput.addEventListener('input', () => {
                if (!isConnectedToChat || !chatSocket || !currentSession) {
                    updateSendButton(); // ✅ Actualizar botón aunque no esté conectado
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
                
                updateSendButton(); // ✅ Actualizar botón
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

        // RF7: Setup de eventos para upload de archivos
        function setupFileUploadEvents() {
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                fileInput.addEventListener('change', (e) => {
                    const files = Array.from(e.target.files);
                    handleFileSelection(files);
                    e.target.value = ''; // Limpiar input
                });
            }
            
            // ✅ SETUP INICIAL DEL BOTÓN
            updateSendButton();
            
            console.log('✅ Eventos de archivo configurados');
        }

        function updateTime() {
            document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('es-ES');
        }

        function logout() {
            if (confirm('¿Cerrar sesión?')) {
                if (chatSocket) {
                    chatSocket.disconnect();
                }
                clearSelectedFiles(); // RF7: Limpiar archivos
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
        
        function initializeMobileControls() {
            // Crear botones de toggle móvil
            createMobileToggleButtons();
            
            // Event listeners para botones móviles
            setupMobileEventListeners();
            
            // Detectar cambios de orientación
            window.addEventListener('orientationchange', handleOrientationChange);
            window.addEventListener('resize', handleResize);
        }

        function createMobileToggleButtons() {
            // Botón para sidebar principal
            const mainToggle = document.createElement('button');
            mainToggle.className = 'mobile-toggle-btn';
            mainToggle.id = 'mobileMainToggle';
            mainToggle.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            `;
            
            // Botón para patient info sidebar
            const chatToggle = document.createElement('button');
            chatToggle.className = 'mobile-chat-toggle';
            chatToggle.id = 'mobileChatToggle';
            chatToggle.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            `;
            
            // Overlay
            const overlay = document.createElement('div');
            overlay.className = 'mobile-overlay';
            overlay.id = 'mobileOverlay';
            
            document.body.appendChild(mainToggle);
            document.body.appendChild(chatToggle);
            document.body.appendChild(overlay);
        }

        function setupMobileEventListeners() {
            const mainToggle = document.getElementById('mobileMainToggle');
            const chatToggle = document.getElementById('mobileChatToggle');
            const overlay = document.getElementById('mobileOverlay');
            
            if (mainToggle) {
                mainToggle.addEventListener('click', toggleMainSidebar);
            }
            
            if (chatToggle) {
                chatToggle.addEventListener('click', togglePatientInfoSidebar);
            }
            
            if (overlay) {
                overlay.addEventListener('click', closeAllMobileSidebars);
            }
            
            // Cerrar sidebars con Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeAllMobileSidebars();
                }
            });
        }

        function toggleMainSidebar() {
            const sidebar = document.querySelector('.sidebar-main');
            const overlay = document.getElementById('mobileOverlay');
            
            if (sidebar && overlay) {
                const isOpen = sidebar.classList.contains('mobile-open');
                
                if (isOpen) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                } else {
                    // Cerrar otros sidebars primero
                    closeAllMobileSidebars();
                    sidebar.classList.add('mobile-open');
                    overlay.classList.add('active');
                }
            }
        }

        function toggleChatSidebar() {
            const sidebar = document.querySelector('.chat-sidebar');
            const overlay = document.getElementById('mobileOverlay');
            
            if (sidebar && overlay) {
                const isOpen = sidebar.classList.contains('mobile-open');
                
                if (isOpen) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                } else {
                    // Cerrar otros sidebars primero
                    closeAllMobileSidebars();
                    sidebar.classList.add('mobile-open');
                    overlay.classList.add('active');
                }
            }
        }

        function togglePatientInfoSidebar() {
            const sidebar = document.querySelector('.patient-info-sidebar');
            const overlay = document.getElementById('mobileOverlay');
            
            if (sidebar && overlay) {
                const isOpen = sidebar.classList.contains('mobile-open');
                
                if (isOpen) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                } else {
                    // Cerrar otros sidebars primero
                    closeAllMobileSidebars();
                    sidebar.classList.add('mobile-open');
                    overlay.classList.add('active');
                }
            }
        }

        function closeAllMobileSidebars() {
            const sidebars = document.querySelectorAll('.sidebar-main, .chat-sidebar, .patient-info-sidebar');
            const overlay = document.getElementById('mobileOverlay');
            
            sidebars.forEach(sidebar => {
                sidebar.classList.remove('mobile-open');
            });
            
            if (overlay) {
                overlay.classList.remove('active');
            }
        }

        function handleOrientationChange() {
            setTimeout(() => {
                handleResize();
                closeAllMobileSidebars();
            }, 100);
        }

        function handleResize() {
            const isMobile = window.innerWidth <= 768;
            
            if (!isMobile) {
                closeAllMobileSidebars();
            }
            
            // Ajustar altura de chat en mobile
            if (isMobile) {
                const chatContainer = document.querySelector('.chat-container');
                const headerHeight = document.querySelector('.chat-header')?.offsetHeight || 70;
                
                if (chatContainer) {
                    chatContainer.style.height = `calc(100vh - ${headerHeight}px)`;
                }
            }
            const chatToggle = document.getElementById('mobileChatToggle');
            if (chatToggle) {
                if (currentSession && window.innerWidth <= 1024) {
                    chatToggle.classList.add('show-in-chat');
                } else {
                    chatToggle.classList.remove('show-in-chat');
                }
            }
        }

        // Mejorar función para abrir chat sidebar en mobile
        function openChatSidebarInMobile() {
            if (window.innerWidth <= 768) {
                toggleChatSidebar();
            }
        }

        // Inicializar controles móviles cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', () => {
            initializeMobileControls();
        });

        // Inicialización del sistema
        document.addEventListener('DOMContentLoaded', async () => {
            updateTime();
            setInterval(updateTime, 1000);
            
            setupAgentTyping();
            setupFileUploadEvents(); // RF7: Setup de eventos de archivos
            showPendingSection();
            startAutoRefresh();
            
            startRealTimeUpdates();
            
            setTimeout(() => {
                updateSidebarCounts();
            }, 2000);
        });

        // Eventos globales
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.fixed.inset-0').forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        modal.classList.add('hidden');
                    }
                });
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
            clearSelectedFiles(); // RF7: Limpiar archivos
        });

        // Funciones globales accesibles
        window.showPatientInfoButton = showPatientInfoButton;
        window.hidePatientInfoButton = hidePatientInfoButton;
        window.toggleMainSidebar = toggleMainSidebar;
        window.toggleChatSidebar = toggleChatSidebar;
        window.togglePatientInfoSidebar = togglePatientInfoSidebar;
        window.closeAllMobileSidebars = closeAllMobileSidebars;
        window.openChatSidebarInMobile = openChatSidebarInMobile;
        window.showPendingSection = showPendingSection;
        window.showMyChatsSection = showMyChatsSection;
        window.showRoomsSection = showRoomsSection;
        window.goBackToPending = goBackToPending;
        window.loadPendingConversations = loadPendingConversations;
        window.loadMyChats = loadMyChats;
        window.loadChatsSidebar = loadChatsSidebar;
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
        window.currentRoom = currentRoom;
        window.clearDuplicationForTransferredChat = clearDuplicationForTransferredChat;
        
        // RF7: Funciones globales de archivos
        window.toggleFileUpload = toggleFileUpload;
        window.removeSelectedFile = removeSelectedFile;
        window.clearSelectedFiles = clearSelectedFiles;
    </script>
</body>
</html>