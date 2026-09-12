<?php
// public/api/register.php
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'method_not_allowed', 405);
}

$pdo = Database::getInstance();

$role = $_POST['role'] ?? '';
$org_name = trim($_POST['org_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? floatval($_POST['lat']) : null;
$lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? floatval($_POST['lng']) : null;

// Validate
if (!in_array($role, ['donor', 'ngo', 'recycler'])) {
    sendJsonResponse(false, null, 'invalid_role', 422);
}

if (empty($org_name) || empty($email) || strlen($password) < 8 || empty($phone)) {
    sendJsonResponse(false, null, 'missing_required_fields', 422);
}

// Donors are auto-approved; NGOs and Recyclers require admin verification
$approval_status = ($role === 'donor') ? 'approved' : 'pending';
$doc_path = null;

// Handle file upload for ngo and recycler
if (in_array($role, ['ngo', 'recycler'])) {
    if (!isset($_FILES['verification_doc']) || $_FILES['verification_doc']['error'] !== UPLOAD_ERR_OK) {
        sendJsonResponse(false, null, 'missing_verification_doc', 422);
    }
    
    $file = $_FILES['verification_doc'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'])) {
        sendJsonResponse(false, null, 'invalid_file_type', 422);
    }
    
    $uploadDir = __DIR__ . '/../uploads/docs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = uniqid('doc_') . '.' . $ext;
    $targetPath = $uploadDir . $fileName;
    
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        sendJsonResponse(false, null, 'upload_failed', 500);
    }
    
    $doc_path = BASE_URL . '/uploads/docs/' . $fileName;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (role, org_name, email, password_hash, phone, lat, lng, verification_doc_path, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $role, $org_name, $email, $password_hash, $phone, $lat, $lng, $doc_path, $approval_status
    ]);
    
    $userId = $pdo->lastInsertId();
    
    sendJsonResponse(true, [
        'user_id' => $userId,
        'role' => $role
    ], null, 201);
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate email)
        sendJsonResponse(false, null, 'email_already_exists', 409);
    }
    sendJsonResponse(false, null, 'database_error', 500);
}
