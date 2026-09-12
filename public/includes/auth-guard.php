<?php
// public/includes/auth-guard.php
require_once __DIR__ . '/../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth($allowedRoles = []) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        if (isApiRequest()) {
            sendJsonResponse(false, null, 'unauthorized', 401);
        } else {
            header('Location: ' . BASE_URL . '/auth.php');
            exit;
        }
    }

    $userRole = $_SESSION['role'];

    // Check if user has an allowed role
    if (!empty($allowedRoles) && !in_array($userRole, $allowedRoles)) {
        if (isApiRequest()) {
            sendJsonResponse(false, null, 'forbidden', 403);
        } else {
            // Redirect to their respective dashboard
            if ($userRole === 'donor') header('Location: ' . BASE_URL . '/donor/dashboard.php');
            elseif ($userRole === 'ngo') header('Location: ' . BASE_URL . '/ngo/feed.php');
            elseif ($userRole === 'recycler') header('Location: ' . BASE_URL . '/recycling/index.php');
            else header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}

function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) return null;
    
    $stmt = $pdo->prepare("SELECT id, role, org_name, email, phone, lat, lng, verification_doc_path, approval_status, trust_score, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function isApiRequest() {
    return strpos($_SERVER['REQUEST_URI'], '/api/') !== false || 
           (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}
