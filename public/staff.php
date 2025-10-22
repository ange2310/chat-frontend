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
        
        .nav-link.active { 
            background: #2563eb; 
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

        .content-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            border: 1px solid #e5e7eb;
        }

        .countdown-urgent { 
            animation: pulse-red 1s infinite; 
        }

        @keyframes pulse-red { 
            0%, 100% { color: #dc2626; } 
            50% { color: #ef4444; } 
        }

        .chat-container {
            height: 100vh;
            display: flex;
            flex-direction: row;
            position: relative;
            overflow: hidden;
        }

        .chat-sidebar { 
            width: 300px; 
            min-width: 300px; 
            background: white;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            z-index: 20;
            flex-shrink: 0;
            position: relative;
        }

        .chat-main { 
            flex: 1; 
            min-width: 0; 
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .patient-info-sidebar { 
            width: 280px;
            min-width: 280px;
            max-width: 280px;
            background: #f9fafb;
            border-left: 1px solid #e5e7eb;
            overflow-y: auto;
            z-index: 10;
            flex-shrink: 0;
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

        @keyframes pulse-typing { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes pulse-critical { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(1.1); } }

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

        .timer-display {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

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

        .availability-toggle {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        .toggle-slider {
            position: relative;
            width: 44px;
            height: 24px;
            background: #ef4444;
            border-radius: 12px;
            transition: background 0.3s ease;
        }

        .toggle-slider.active {
            background: #10b981;
        }

        .toggle-indicator {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .toggle-slider.active .toggle-indicator {
            transform: translateX(20px);
        }

        .availability-toggle[data-status="presente"] .toggle-slider {
            background: #10b981;
        }

        .availability-toggle[data-status="presente"] .toggle-indicator {
            transform: translateX(20px);
        }

        .availability-toggle[data-status="ausente"] .toggle-slider {
            background: #ef4444;
        }

        .availability-toggle[data-status="ausente"] .toggle-indicator {
            transform: translateX(0);
        }

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

        .chat-sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 25;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .chat-sidebar-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

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
        
        /* Sidebar fija con altura específica */
        /* Sidebar Desktop */
        .sidebar-desktop {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 16rem;
            min-width: 16rem;
            max-width: 16rem;
            height: 100vh;
            background: white;
            border-right: 1px solid #e5e7eb;
            flex-direction: column;
            flex-shrink: 0;
            z-index: 30;
        }

        /* Sidebar Mobile */
        .sidebar-mobile {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 16rem;
            min-width: 16rem;
            max-width: 16rem;
            height: 100vh;
            background: white;
            border-right: 1px solid #e5e7eb;
            flex-direction: column;
            flex-shrink: 0;
            z-index: 50;
            transition: transform 0.3s ease;
            transform: translateX(-100%);
            box-shadow: 4px 0 6px -1px rgba(0, 0, 0, 0.1);
        }

        .sidebar-mobile.mobile-open {
            transform: translateX(0);
        }

        @media (min-width: 1024px) {
            .sidebar-desktop {
                display: flex;
            }
            .sidebar-mobile {
                display: none;
            }
        }

        @media (max-width: 1024px) {
            .sidebar-desktop {
                display: none;
            }
            .sidebar-mobile {
                display: flex;
            }
        }
            
            @media (min-width: 1024px) {
                .content-with-sidebar {
                    margin-left: 16rem;
                }
            }
            
            @media (max-width: 1024px) {
                .content-with-sidebar {
                    margin-left: 0;
                }
            }
        
        .min-h-full.flex {
            display: flex;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
        }

        .flex-1.flex.flex-col {
            flex: 1;
            min-width: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header.bg-white.border-b {
            flex-shrink: 0;
            height: 80px;
            border-bottom: 1px solid #e5e7eb;
        }

        main.flex-1.overflow-auto {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }

        /* Área de navegación que puede hacer scroll */
        .sidebar-nav-area {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }

        /* Área de usuario fija en la parte inferior */
        .sidebar-user-area {
            flex-shrink: 0;
            border-top: 1px solid #e5e7eb;
            background: white;
        }

        /* Estilos mejorados para horarios */
        .schedule-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .schedule-card:hover {
            border-color: #d1d5db;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        .schedule-day {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }
        
        .schedule-time {
            font-size: 0.75rem;
            font-weight: 600;
            font-family: 'Monaco', 'Menlo', monospace;
        }
        
        .schedule-status {
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }

        @media (min-width: 1024px) {
            .sidebar-main {
                display: flex;
            }
        }
        @media (max-width: 1024px) {
            .patient-info-sidebar {
                position: fixed;
                right: -300px;
                top: 0;
                height: 100vh;
                z-index: 50;
                width: 300px;
                min-width: 300px;
                max-width: 300px;
                box-shadow: -4px 0 6px -1px rgba(0, 0, 0, 0.1);
                transition: transform 0.3s ease;
            }
            
            .patient-info-sidebar.active {
                transform: translateX(-300px);
            }
            
            .chat-header {
                padding-right: 5rem;
            }
        }

        @media (max-width: 480px) {
            .sidebar-mobile {
                width: 300px;
                min-width: 300px;
                max-width: 300px;
            }
            
            /* En pantallas muy pequeñas, área de usuario aún más compacta */
            .sidebar-mobile > div:last-child {
                min-height: 120px;
                max-height: 120px;
                padding: 0.5rem;
            }
            
            /* Navegación con más altura disponible */
            .sidebar-mobile nav {
                max-height: calc(100vh - 180px);
            }
            
            /* Avatar aún más pequeño */
            .sidebar-mobile .w-10.h-10 {
                width: 2rem;
                height: 2rem;
                font-size: 0.7rem;
            }
            
            /* Switch aún más compacto */
            .sidebar-mobile .toggle-slider {
                width: 32px;
                height: 18px;
            }
            
            .sidebar-mobile .toggle-indicator {
                width: 14px;
                height: 14px;
            }
            
            .sidebar-mobile .toggle-slider.active .toggle-indicator {
                transform: translateX(14px);
            }
        }

        @media (max-width: 768px) {
            .sidebar-mobile {
                width: 20rem;
                min-width: 20rem;
                max-width: 20rem;
                height: 100vh;
                height: -webkit-fill-available;
            }
            
            /* Ajustar altura del contenedor de navegación para dar más espacio al área de usuario */
            .sidebar-mobile nav {
                flex: 1;
                overflow-y: auto;
                max-height: calc(100vh - 200px); /* Reducido de altura automática */
                padding-bottom: 0.5rem;
            }
            
            /* Área de usuario más compacta y siempre visible */
            .sidebar-mobile > div:last-child {
                flex-shrink: 0;
                margin-top: auto;
                min-height: 140px; /* Altura mínima garantizada */
                max-height: 140px;
                padding: 0.75rem;
                border-top: 2px solid #e5e7eb;
                background: white;
                position: relative;
                bottom: 0;
            }
            
            /* Hacer el área de usuario más compacta */
            .sidebar-mobile .flex.items-center.gap-3 {
                gap: 0.5rem;
                margin-bottom: 0.5rem;
            }
            
            /* Avatar más pequeño en móvil */
            .sidebar-mobile .w-10.h-10 {
                width: 2.25rem;
                height: 2.25rem;
                font-size: 0.75rem;
            }
            
            /* Texto de usuario más compacto */
            .sidebar-mobile .font-medium {
                font-size: 0.8rem;
                line-height: 1rem;
            }
            
            .sidebar-mobile .text-sm {
                font-size: 0.7rem;
            }
            
            /* Botón logout más pequeño */
            .sidebar-mobile button[onclick="logout()"] {
                padding: 0.375rem;
                min-width: 2rem;
                min-height: 2rem;
            }
            
            /* Área de disponibilidad más compacta */
            .sidebar-mobile .mt-3.pt-3 {
                margin-top: 0.5rem;
                padding-top: 0.5rem;
            }
            
            /* Switch de disponibilidad más pequeño pero funcional */
            .sidebar-mobile .toggle-slider {
                width: 36px;
                height: 20px;
            }
            
            .sidebar-mobile .toggle-indicator {
                width: 16px;
                height: 16px;
                top: 2px;
                left: 2px;
            }
            
            .sidebar-mobile .toggle-slider.active .toggle-indicator {
                transform: translateX(16px);
            }
            
            /* Texto del switch más pequeño */
            .sidebar-mobile .availability-toggle span {
                font-size: 0.65rem;
                margin-left: 0.25rem;
            }
            
            /* Descripción más pequeña */
            .sidebar-mobile .text-xs.text-gray-400 {
                font-size: 0.6rem;
                margin-top: 0.25rem;
            }
        }

        /* Soporte para dispositivos con notch (iPhone X+) */
        @supports (padding: max(0px)) {
            @media (max-width: 768px) {
                .sidebar-mobile {
                    height: 100vh;
                    height: -webkit-fill-available;
                    padding-bottom: env(safe-area-inset-bottom, 0px);
                }
                
                .sidebar-mobile > div:last-child {
                    padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0px));
                }
            }
        }

        @media (min-width: 769px) {
            .chat-sidebar {
                position: relative;
                transform: none;
                box-shadow: none;
                left: 0;
                top: 0;
            }
        }
            
            .chat-header {
                padding: 1rem 0.75rem;
                padding-left: 4rem;
                padding-right: 4rem;
                min-height: 70px;
            }
            
            /* Mejoras para chat header en móviles */
            .chat-header h2 {
                font-size: 1.1rem;
                line-height: 1.3rem;
            }
            
            .chat-header .text-sm {
                font-size: 0.8rem;
            }
            
            /* Avatar más pequeño en tablet/móvil */
            .chat-header .w-12.h-12 {
                width: 2.75rem;
                height: 2.75rem;
            }
            
            /* Botones de acción más compactos */
            .chat-header .flex-wrap button {
                padding: 4px 8px;
                font-size: 11px;
            }
            
            .chat-container {
                height: calc(100vh - 70px);
            }
            
            .chat-sidebar {
                position: relative;
                left: 0;
                top: 0;
                height: 100%;
                z-index: 30;
                box-shadow: none;
                transition: transform 0.3s ease;
            }

            @media (max-width: 768px) {
                .chat-sidebar {
                    position: fixed;
                    left: -300px;
                    top: 70px;
                    height: calc(100vh - 70px);
                    z-index: 30;
                    box-shadow: 4px 0 6px -1px rgba(0, 0, 0, 0.1);
                }
            }
            
            .chat-sidebar.mobile-open {
                transform: translateX(300px);
            }
            
            .chat-messages {
                padding: 1rem 0.75rem;
                max-height: none;
                height: auto;
                flex: 1;
            }
            
            .chat-input-area {
                padding: 0.75rem;
                min-height: 110px;
                background: white;
                border-top: 2px solid #e5e7eb;
            }

            @media (max-width: 768px) {
                .chat-messages {
                    max-height: calc(100vh - 220px);
                    padding-bottom: 1rem;
                }
                
                .chat-input-area {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    z-index: 40;
                    border-top: 2px solid #e5e7eb;
                    background: white;
                    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                }
                
                .chat-messages-container {
                    padding-bottom: 140px;
                }
            }
            
            .patient-info-sidebar {
                width: 90vw;
                max-width: 280px;
            }

        @media (max-width: 480px) {
            .sidebar-main {
                left: -300px;
                width: 300px;
            }
            
            .sidebar-main.mobile-open {
                transform: translateX(300px);
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
            
            .patient-info-sidebar {
                width: 100vw;
                right: -100vw;
            }
            
            .patient-info-sidebar.active {
                transform: translateX(-100vw);
            }
        }

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

        /* Mejoras adicionales para móviles */
        @media (max-width: 768px) {
            /* Scroll mejorado para sidebar móvil */
            .sidebar-main::-webkit-scrollbar {
                width: 4px;
            }
            .sidebar-main::-webkit-scrollbar-track {
                background: #f1f5f9;
            }
            .sidebar-main::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 2px;
            }
            
            /* Mejorar botones en móvil */
            .nav-link {
                min-height: 48px;
                padding: 0.875rem 1rem;
                font-size: 0.9rem;
            }
            
            /* Mejor espaciado en la sidebar móvil */
            .sidebar-main .p-6 {
                padding: 1rem;
            }
            
            .sidebar-main .p-4:last-child {
                padding: 1rem;
                border-top: 1px solid #e5e7eb;
            }
        }

        /* Mejoras específicas para iPhones y pantallas pequeñas */
        @media (max-width: 414px) {
            .sidebar-main {
                left: -320px;
                width: 320px;
            }
            
            .sidebar-main.mobile-open {
                transform: translateX(320px);
            }
            
            /* Asegurar que el usuario info sea visible */
            .sidebar-main > div:last-child {
                background: white;
                border-top: 2px solid #e5e7eb;
                min-height: 120px;
            }
            
            /* Mejorar el botón de logout */
            .sidebar-main button[onclick="logout()"] {
                min-width: 44px;
                min-height: 44px;
                padding: 0.75rem;
            }
            
            /* Mejorar chat header en móviles */
            .chat-header {
                padding: 0.75rem 0.5rem;
            }
            
            .chat-header h2 {
                font-size: 0.95rem;
                line-height: 1.2rem;
            }
            
            .chat-header .text-sm {
                font-size: 0.7rem;
            }
            
            /* Botones de acción más compactos */
            .chat-header .flex-wrap button {
                padding: 3px 6px;
                font-size: 10px;
                min-height: 32px;
            }
            
            /* Avatar más pequeño en móvil */
            .chat-header .w-12.h-12 {
                width: 2.25rem;
                height: 2.25rem;
            }
            
            /* Espaciado más compacto en el header del chat */
            .chat-header .flex.items-center.gap-4 {
                gap: 0.75rem;
            }
            
            .chat-header .flex.items-center.gap-1 {
                gap: 0.25rem;
            }
        }

        /* Ajustes para Safari iOS */
        @supports (-webkit-touch-callout: none) {
            .sidebar-main {
                height: 100vh;
                height: -webkit-fill-available;
            }
            
            .chat-container {
                height: calc(100vh - 70px);
                height: calc(-webkit-fill-available - 70px);
            }
        }

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
            
            .chat-item:hover {
                background-color: transparent;
                transform: none;
            }
            
            .chat-item:active {
                background-color: #f9fafb;
            }
        }

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
        }

        /* Mejoras adicionales para touch devices */
        @media (hover: none) and (pointer: coarse) {
            .nav-link {
                min-height: 52px;
                padding: 0.875rem;
            }
            
            button, .cursor-pointer {
                min-height: 48px;
                min-width: 48px;
            }
        }
        
        /* Ajustes para Safari iOS */
        @supports (-webkit-touch-callout: none) {
            .sidebar-main {
                height: 100vh;
                height: -webkit-fill-available;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50">

    <div class="mobile-nav-backdrop" id="mobileNavBackdrop" onclick="closeMobileNav()"></div>
    
    <div class="patient-info-backdrop" id="patientInfoBackdrop" onclick="closePatientInfoSidebar()"></div>
    
    <div class="chat-sidebar-backdrop lg:hidden" id="chatSidebarBackdrop" onclick="closeChatSidebar()"></div>

    <div class="min-h-full flex">
        
        <!-- Sidebar Desktop con estructura corregida -->
        <div class="sidebar-desktop bg-white border-r border-gray-200">
            <div class="p-6 border-b border-gray-200 flex-shrink-0">
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
            
            <nav class="sidebar-nav-area p-4">
                <div class="space-y-1">
                    <a href="#pending" onclick="showPendingSection()" 
                       id="nav-pending" class="nav-link active">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Conversaciones Pendientes
                        <span id="pendingCount" class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">0</span>
                    </a>
                    
                    <a href="#my-chats" onclick="showMyChatsSection()" 
                       id="nav-my-chats" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8V4a2 2 0 012 2v2z"></path>
                        </svg>
                        Mis Chats
                        <span id="myChatsCount" class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full">0</span>
                    </a>
                    
                    <a href="#rooms" onclick="showRoomsSection()" 
                       id="nav-rooms" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                        Ver por Salas
                    </a>
                    
                    <a href="#profile" onclick="showProfileSection()" 
                       id="nav-profile" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Mi Perfil
                    </a>

                    <a href="#group-chat" onclick="showSection('group-chat'); closeMobileNav();" 
                        id="nav-group-chat" class="nav-link">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>
                            </svg>
                            Chat Grupal
                            <span id="groupChatUnread" class="ml-auto px-2 py-1 bg-blue-500 text-white text-xs rounded-full hidden">0</span>
                    </a>
                </div>
            </nav>
                
            <div class="sidebar-user-area p-4">
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
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-600">Estado:</span>
                        <button id="availabilityToggle" 
                                onclick="toggleAvailability()" 
                                class="availability-toggle"
                                data-status="presente"
                                title="Cambiar disponibilidad">
                            <div class="toggle-slider">
                                <div class="toggle-indicator"></div>
                            </div>
                            <span id="availabilityText" class="ml-2 text-xs font-medium">Presente</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Indica si puedes recibir chats</p>
                </div>
            </div>
        </div>

        <!-- Mobile Sidebar corregida -->
        <div class="sidebar-mobile" id="mobileNav">
            <div class="p-4 border-b border-gray-200 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="font-semibold text-gray-900 text-sm">Panel de Agente</h1>
                        </div>
                    </div>
                    <button onclick="closeMobileNav()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <nav class="flex-1 p-4 overflow-y-auto">
                <div class="space-y-1">
                    <a href="#pending" onclick="showPendingSection(); closeMobileNav();" 
                    id="mobile-nav-pending" class="nav-link active">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Pendientes
                        <span id="mobilePendingCount" class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">0</span>
                    </a>
                    
                    <a href="#my-chats" onclick="showMyChatsSection(); closeMobileNav();" 
                    id="mobile-nav-my-chats" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8V4a2 2 0 012 2v2z"></path>
                        </svg>
                        Mis Chats
                        <span id="mobileMyChatsCount" class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full">0</span>
                    </a>
                    
                    <a href="#rooms" onclick="showRoomsSection(); closeMobileNav();" 
                    id="mobile-nav-rooms" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m0 0H5m2 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                        Salas
                    </a>
                    
                    <a href="#profile" onclick="showProfileSection(); closeMobileNav();" 
                    id="mobile-nav-profile" class="nav-link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Perfil
                    </a>

                    <a href="#group-chat" onclick="showSection('group-chat'); closeMobileNav();" 
                        id="nav-group-chat" class="nav-link">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>
                            </svg>
                            Chat Grupal
                            <span id="groupChatUnread" class="ml-auto px-2 py-1 bg-blue-500 text-white text-xs rounded-full hidden">0</span>
                    </a>
                </div>
            </nav>
                
            <div class="p-4 border-t border-gray-200 flex-shrink-0 bg-white">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold text-sm">
                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate text-sm"><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></p>
                        <p class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($userRole) ?></p>
                    </div>
                    <button onclick="logout()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg flex-shrink-0" title="Cerrar sesión">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="pt-3 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-600">Estado:</span>
                        <button id="mobileAvailabilityToggle" 
                                onclick="toggleAvailability()" 
                                class="availability-toggle"
                                data-status="presente"
                                title="Cambiar disponibilidad">
                            <div class="toggle-slider">
                                <div class="toggle-indicator"></div>
                            </div>
                            <span id="mobileAvailabilityText" class="ml-2 text-xs font-medium">Presente</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Indica si puedes recibir chats</p>
                </div>
            </div>
        </div>

        <div class="flex-1 flex flex-col content-with-sidebar">
            
            <header class="bg-white border-b border-gray-200">
                <div class="px-4 sm:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <button onclick="openMobileNav()" class="lg:hidden p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                </svg>
                            </button>
                            <h2 id="sectionTitle" class="text-lg sm:text-xl font-semibold text-gray-900">Conversaciones Pendientes</h2>
                        </div>
                        <div class="flex items-center gap-2 sm:gap-4">
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
                
                <div id="pending-conversations-section" class="section-content p-6">
                    <div class="content-card">
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

                <div id="my-chats-section" class="section-content hidden p-6">
                    <div class="content-card">
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

                <div id="rooms-list-section" class="section-content hidden p-6">
                    <div class="content-card">
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

                <div id="room-sessions-section" class="section-content hidden p-6">
                    <div class="content-card">
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

                <!-- Sección de Perfil mejorada -->
                <div id="profile-section" class="section-content hidden p-6">
                    <div class="content-card">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">Mi Perfil</h3>
                                    <p class="text-sm text-gray-600 mt-1">Administra tu información personal y configuración</p>
                                </div>
                                <button onclick="loadUserProfile()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div id="profileContainer">
                                <div class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p class="text-gray-500">Cargando perfil...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="patient-chat-panel" class="section-content hidden">
                    <div class="chat-container">
                        <div class="flex h-full">
                            
                            <div class="chat-sidebar bg-white border-r border-gray-200">
                                <div class="chat-sidebar-header p-4 border-b border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-semibold text-gray-900">Conversaciones</h3>
                                        <div class="flex items-center gap-2">
                                            <button onclick="loadChatsSidebar()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                            </button>
                                            <button onclick="closeChatSidebar()" class="lg:hidden p-1.5 text-gray-400 hover:text-gray-600 rounded" title="Cerrar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="chatsSidebarContainer" class="chat-sidebar-content">
                                    <div class="text-center py-8 text-gray-500">
                                        <p class="text-sm">Cargando chats...</p>
                                    </div>
                                </div>
                            </div>

                            <div class="chat-main flex flex-col bg-white">
                                
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
                                                    <h2 id="chatPatientName" class="text-xl lg:text-xl md:text-lg sm:text-base font-bold text-gray-900">Paciente</h2>
                                                </div>
                                                <p class="text-sm text-gray-500">
                                                    <span id="chatRoomName">Sala</span> • 
                                                    <span id="chatSessionStatus" class="text-green-600">Activo</span>
                                                    <span id="chatTimer" class="timer-display ml-2"></span>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-1 flex-wrap">
                                            <button onclick="openPatientInfoSidebar()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg lg:hidden" title="Información del Paciente">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            </button>
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

                                <div class="chat-messages-container">
                                    <div class="chat-messages auto-scroll" id="patientChatMessages">
                                        <div class="text-center py-8 text-gray-500">
                                            Selecciona una conversación para comenzar
                                        </div>
                                    </div>
                                    
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

                                <div class="chat-input-area p-4">
                                    <div id="fileUploadArea" class="file-upload-area hidden">
                                        <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                        <p class="text-sm text-gray-600 mb-2">Arrastra archivos aquí o haz clic para seleccionar</p>
                                        <p class="text-xs text-gray-500">Máximo 10MB - Imágenes, PDF, documentos</p>
                                        <input type="file" id="fileInput" multiple accept="image/*,.pdf,.doc,.docx,.txt,.csv,.xlsx,.xls" class="hidden">
                                    </div>
                                    
                                    <div id="filePreviewArea" class="hidden mb-3">
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-medium text-blue-800">Archivos seleccionados:</span>
                                                <button onclick="clearSelectedFiles()" class="text-blue-600 hover:text-blue-800 text-xs">Limpiar</button>
                                            </div>
                                            <div id="selectedFilesList" class="space-y-2"></div>
                                        </div>
                                    </div>
                                    
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

                            <div class="patient-info-sidebar" id="patientInfoSidebar">
                                <div class="p-6">
                                    
                                    <div class="flex items-center justify-between mb-4 lg:hidden">
                                        <h3 class="text-lg font-semibold text-gray-900">Información del Paciente</h3>
                                        <button onclick="closePatientInfoSidebar()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    <div class="hidden lg:block mb-6">
                                        <h3 class="text-lg font-semibold text-gray-900">Información del Paciente</h3>
                                    </div>
                                    
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

                                <div class="p-6 border-t border-gray-200">
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

                                <div class="p-6 border-t border-gray-200">
                                    <div class="bg-gray-100 rounded-lg p-3">
                                        <div class="text-xs text-gray-500 mb-1">ID de Sesión</div>
                                        <div id="chatPatientId" class="text-xs font-mono text-gray-700">-</div>
                                    </div>
                                </div>

                                <!-- Group Chat Section -->
                                <div id="group-chat-section" class="section-content hidden p-6">
                                    <div class="mb-6">
                                        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                                <div>
                                                    <h3 class="text-xl font-semibold text-gray-900">Salas de Chat Grupal</h3>
                                                    <p class="text-sm text-gray-600 mt-1">Únete a salas para colaboración en equipo</p>
                                                </div>
                                                <button onclick="supervisorClient.refreshGroupRooms()" 
                                                        class="btn btn-primary flex items-center gap-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                    </svg>
                                                    Actualizar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Rooms List -->
                                    <div id="groupRoomsList" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                                        <div class="col-span-full flex items-center justify-center py-20">
                                            <div class="text-center">
                                                <div class="loading-spinner mx-auto mb-4"></div>
                                                <p class="text-gray-500">Cargando salas...</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Active Group Chat -->
                                    <div id="activeGroupChat" class="hidden">
                                        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                                            <!-- Chat Header -->
                                            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                                                <div class="flex items-center gap-3">
                                                    <button onclick="supervisorClient.exitGroupChat()" 
                                                            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                                        </svg>
                                                    </button>
                                                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-blue-500 rounded-lg flex items-center justify-center">
                                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h3 id="groupChatRoomName" class="text-lg font-semibold text-gray-900">Sala</h3>
                                                        <div class="flex items-center gap-2">
                                                            <span id="groupChatParticipantsCount" class="text-sm text-gray-500">0 participantes</span>
                                                            <span id="groupChatModeIndicator" class="px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                                                Modo Observador
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex items-center gap-2">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                                                        </svg>
                                                        Activar Voz
                                                    </button>
                                                    
                                                    <button onclick="supervisorClient.showGroupParticipants()" 
                                                            class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Messages Container -->
                                            <div id="groupChatMessages" class="h-96 overflow-y-auto p-4 space-y-3 bg-gray-50">
                                                <div class="text-center text-gray-500 text-sm py-8">
                                                    Cargando mensajes...
                                                </div>
                                            </div>
                                            
                                            <!-- Input Area -->
                                            <div class="p-4 border-t border-gray-200 bg-white">
                                                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                                    </svg>
                                                    Estás en modo observador. Activa tu voz para enviar mensajes.
                                                </div>
                                                
                                                <div id="groupChatInputEnabled" class="flex items-end gap-3">
                                                    <div class="flex-1">
                                                        <textarea id="groupMessageInput" 
                                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                                                rows="2"
                                                                placeholder="Escribe tu mensaje..."
                                                                onkeydown="handleGroupChatKeyDown(event)"></textarea>
                                                    </div>
                                                    <button onclick="sendGroupMessage()" 
                                                            id="groupSendButton"
                                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Participants Sidebar Modal -->
                                <div id="groupParticipantsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
                                    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                                        <div class="flex items-center justify-between p-4 border-b border-gray-200">
                                            <h3 class="text-lg font-semibold text-gray-900">Participantes</h3>
                                            <button onclick="supervisorClient.closeGroupParticipants()" 
                                                    class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <div id="groupParticipantsList" class="p-4 max-h-96 overflow-y-auto">
                                            <!-- Participants will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modals -->
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
                        <option value="">Selecciona una sala...</option>
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

    <div id="editProfileModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar Perfil</h3>
            
            <form id="editProfileForm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                        <input type="text" id="editName" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="editEmail" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button type="button" onclick="closeModal('editProfileModal')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="changePasswordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-2xl max-w-md w-full mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Cambiar Contraseña</h3>
                <button onclick="closeModal('changePasswordModal')" 
                        class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="changePasswordForm" onsubmit="event.preventDefault(); changePassword();" class="space-y-4">
                <div>
                    <label for="currentPassword" class="block text-sm font-medium text-gray-700 mb-1">Contraseña Actual</label>
                    <input type="password" id="currentPassword" name="currentPassword" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Tu contraseña actual">
                </div>
                
                <div>
                    <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">Nueva Contraseña</label>
                    <input type="password" id="newPassword" name="newPassword" required
                        oninput="validatePasswordStrength('newPassword', 'newPasswordStrength')"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Mínimo 8 caracteres">
                    <div id="newPasswordStrength" class="mt-1"></div>
                </div>
                
                <div>
                    <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirmar Nueva Contraseña</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required
                        oninput="validatePasswordMatch('newPassword', 'confirmPassword', 'passwordMatchIndicator')"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Repite la nueva contraseña">
                    <div id="passwordMatchIndicator" class="mt-1"></div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <div class="flex">
                        <svg class="w-5 h-5 text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <p class="text-sm text-yellow-800">
                            Evita usar caracteres especiales como ñ, acentos o símbolos no estándar.
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                        Cambiar Contraseña
                    </button>
                    <button type="button" onclick="closeStaffModal('changePasswordModal')" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <script src="assets/js/staff-app.js"></script>
</body>
</html>