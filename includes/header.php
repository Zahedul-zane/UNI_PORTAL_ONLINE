<?php
require_once __DIR__ . '/../config/auth.php';
require_login();
$sessionUser = get_session_user();
$BASE = BASE_URL;

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$isProfilePage = ($currentScript === 'profile.php' || (isset($pageTitle) && stripos($pageTitle, 'profile') !== false));
$bodyBgClass = $isProfilePage ? 'portal-profile-bg' : 'portal-inside-bg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'EWU University Portal') ?></title>
    <link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="<?= $bodyBgClass ?>">
    <div class="app-container">
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <div class="app-main">
            <!-- Top Navigation Bar -->
            <header class="app-navbar">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <button id="sidebar_toggle" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--primary); display: none;">☰</button>
                    <h2 class="navbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h2>
                </div>

                <div class="navbar-actions">
                    <div class="semester-badge">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Summer 2026 Academic Term
                    </div>
                    <a href="<?= $BASE ?>/logout.php" class="logout-btn">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Sign Out
                    </a>
                </div>
            </header>

            <div class="content-body">
