<?php
// public/api/admin/users.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['admin']);

$pdo = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch users (exclude admin)
    $stmt = $pdo->query("
        SELECT id, role, org_name, email, phone, verification_doc_path, approval_status, created_at 
        FROM users 
        WHERE role != 'admin' 
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll();
    
    sendJsonResponse(true, ['users' => $users], null);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update user status
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? null;
    $status = $data['status'] ?? null;
    
    if (!$user_id || !in_array($status, ['pending', 'approved', 'rejected'])) {
        sendJsonResponse(false, null, 'invalid_input', 422);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET approval_status = ? WHERE id = ? AND role != 'admin'");
        $stmt->execute([$status, $user_id]);
        
        sendJsonResponse(true, null, null);
    } catch (PDOException $e) {
        sendJsonResponse(false, null, 'database_error', 500);
    }
} else {
    sendJsonResponse(false, null, 'method_not_allowed', 405);
}
