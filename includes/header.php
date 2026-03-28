<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : 'SafeTrack';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
    :root {
        --sidebar-width: 260px;
        --topbar-height: 64px;
        --primary: #0b1f3a;
        --secondary: #163a63;
        --accent: #f97316;
        --danger: #dc2626;
        --success: #16a34a;
        --bg: #f4f7fb;
        --text: #0f172a;
        --muted: #64748b;
        --border: rgba(255, 255, 255, 0.08);
    }

    * {
        box-sizing: border-box;
    }

    html,
    body {
        height: 100%;
    }

    body {
        margin: 0;
        background: var(--bg);
        font-family: 'Segoe UI', sans-serif;
        color: var(--text);
        overflow-x: hidden;
    }

    .topbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--topbar-height);
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 18px;
        z-index: 1100;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
    }

    .topbar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.3px;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .topbar-user {
        font-size: 14px;
        color: #e2e8f0;
    }

    .menu-toggle {
        display: none;
        border: none;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        font-size: 20px;
        cursor: pointer;
    }

    .sidebar {
        position: fixed;
        top: var(--topbar-height);
        left: 0;
        width: var(--sidebar-width);
        height: calc(100vh - var(--topbar-height));
        background: linear-gradient(180deg, #06172c 0%, #0b1f3a 48%, #163a63 100%);
        color: #fff;
        overflow-y: auto;
        z-index: 1050;
        padding: 18px 14px 20px;
        transition: transform 0.28s ease;
        box-shadow: 8px 0 24px rgba(2, 6, 23, 0.12);
    }

    .sidebar::-webkit-scrollbar {
        width: 8px;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.12);
        border-radius: 10px;
    }

    .sidebar-head {
        padding: 10px 12px 18px;
        border-bottom: 1px solid var(--border);
        margin-bottom: 16px;
    }

    .sidebar-title {
        font-size: 18px;
        font-weight: 700;
        margin: 0;
    }

    .sidebar-subtitle {
        font-size: 13px;
        color: #cbd5e1;
        margin-top: 4px;
    }

    .menu-section-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #94a3b8;
        padding: 10px 12px 8px;
        margin-top: 4px;
    }

    .sidebar .nav-link {
        color: #dbe7f3;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 14px;
        margin-bottom: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.22s ease;
        position: relative;
        overflow: hidden;
    }

    .sidebar .nav-link i {
        font-size: 18px;
        min-width: 20px;
        text-align: center;
    }

    .sidebar .nav-link:hover {
        background: rgba(255, 255, 255, 0.10);
        color: #fff;
        transform: translateX(4px);
    }

    .sidebar .nav-link.active {
        background: linear-gradient(90deg, rgba(249, 115, 22, 0.95), rgba(249, 115, 22, 0.75));
        color: #fff;
        box-shadow: 0 8px 18px rgba(249, 115, 22, 0.22);
    }

    .sidebar .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 10px;
        bottom: 10px;
        width: 4px;
        border-radius: 10px;
        background: #fff;
    }

    .sidebar-badge {
        margin-left: auto;
        background: #dc2626;
        color: #fff;
        border-radius: 999px;
        font-size: 11px;
        min-width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 6px;
        font-weight: 700;
    }

    .sidebar-footer {
        margin-top: 16px;
        padding: 14px 12px 0;
        border-top: 1px solid var(--border);
        color: #cbd5e1;
        font-size: 12px;
    }

    .main-content {
        margin-left: var(--sidebar-width);
        margin-top: var(--topbar-height);
        min-height: calc(100vh - var(--topbar-height));
        padding: 24px;
        transition: margin-left 0.28s ease;
    }

    .page-card {
        background: #fff;
        border: none;
        border-radius: 22px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    }

    .stat-card {
        border: none;
        border-radius: 20px;
        color: #fff;
        padding: 22px;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14);
        height: 100%;
    }

    .bg-blue {
        background: linear-gradient(135deg, #163a63, #204d82);
    }

    .bg-green {
        background: linear-gradient(135deg, #15803d, #16a34a);
    }

    .bg-orange {
        background: linear-gradient(135deg, #ea580c, #f97316);
    }

    .bg-red {
        background: linear-gradient(135deg, #b91c1c, #dc2626);
    }

    .auth-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            linear-gradient(rgba(11, 31, 58, 0.80), rgba(22, 58, 99, 0.88)),
            url('https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1400&q=80') center/cover no-repeat;
        padding: 24px;
    }

    .auth-card {
        width: 100%;
        max-width: 460px;
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
    }

    .auth-header {
        background: linear-gradient(120deg, #0b1f3a, #163a63);
        color: #fff;
        padding: 26px;
        text-align: center;
    }

    .auth-body {
        background: #fff;
        padding: 28px;
    }

    .btn-main {
        background: linear-gradient(120deg, #0b1f3a, #163a63);
        border: none;
        color: #fff;
        font-weight: 600;
        border-radius: 12px;
        padding: 11px 16px;
    }

    .btn-main:hover {
        color: #fff;
        opacity: 0.95;
    }

    .form-control,
    .form-select,
    textarea.form-control {
        border-radius: 12px;
        padding: 12px 14px;
    }

    .small-muted {
        color: #64748b;
        font-size: 14px;
    }

    .sidebar-overlay {
        display: none;
    }

    @media (max-width: 991.98px) {
        .menu-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.45);
            z-index: 1040;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        .main-content {
            margin-left: 0;
            padding: 18px;
        }

        .topbar-user {
            display: none;
        }
    }
    </style>
</head>

<body>