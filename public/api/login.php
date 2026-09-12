<?php
// public/api/login.php
require_once '../../config/db.php';

// Start session immediately — must happen before any output or response
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'method_not_allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    sendJsonResponse(false, null, 'missing_credentials', 422);
}

$pdo = Database::getInstance();

$stmt = $pdo->prepare("SELECT id, role, org_name, email, password_hash, approval_status FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    sendJsonResponse(false, null, 'invalid_credentials', 401);
}

if ($user['approval_status'] === 'rejected') {
    sendJsonResponse(false, null, 'account_rejected', 403);
}

// Session already started at the top

$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

// Remove password hash before sending to client
unset($user['password_hash']);

sendJsonResponse(true, [
    'user' => $user
], null);
