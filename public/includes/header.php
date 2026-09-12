<?php
// public/includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure BASE_URL is available if this file is included before db.php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/db.php';
}
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annapoorna — Food Waste Redistribution</title>
    <meta name="description" content="Connecting food donors with NGOs for surplus food redistribution.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <!-- Leaflet CSS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Expose BASE_URL to all JavaScript on the page -->
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
    <header class="main-header">
        <div class="container header-container">
            <a href="<?= BASE_URL ?>/index.php" class="logo">
                <span class="logo-icon">🍲</span>
                Annapoorna
            </a>
            
            <nav class="main-nav">
                <?php if ($isLoggedIn): ?>
                    <?php if ($userRole === 'donor'): ?>
                        <a href="<?= BASE_URL ?>/donor/dashboard.php">Dashboard</a>
                        <a href="<?= BASE_URL ?>/donor/post.php" class="btn btn-primary btn-sm">Post Surplus</a>
                    <?php elseif ($userRole === 'ngo'): ?>
                        <a href="<?= BASE_URL ?>/ngo/feed.php">Feed</a>
                    <?php elseif ($userRole === 'recycler'): ?>
                        <a href="<?= BASE_URL ?>/recycling/index.php">Bio-Waste Exchange</a>
                    <?php endif; ?>
                    
                    <div class="user-menu">
                        <span class="role-badge role-<?= htmlspecialchars($userRole) ?>"><?= ucfirst(htmlspecialchars($userRole)) ?></span>
                        <button id="logoutBtn" class="btn btn-outline btn-sm">Logout</button>
                    </div>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/index.php">Impact</a>
                    <a href="<?= BASE_URL ?>/auth.php" class="btn btn-primary btn-sm">Login / Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    
    <main class="main-content">
