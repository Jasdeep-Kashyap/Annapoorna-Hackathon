<?php
// public/api/claims/verify.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['ngo']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'method_not_allowed', 405);
}

// Support FormData because of file upload
$claimId = intval($_POST['claim_id'] ?? 0);
$otp = trim($_POST['otp'] ?? '');
$portions_verified = intval($_POST['portions_verified'] ?? 0);
$checklist_odor_ok = isset($_POST['checklist_odor_ok']) ? 1 : 0;
$checklist_visual_ok = isset($_POST['checklist_visual_ok']) ? 1 : 0;
$checklist_storage_ok = isset($_POST['checklist_storage_ok']) ? 1 : 0;

if (!$claimId || !$otp || !$portions_verified) {
    sendJsonResponse(false, null, 'missing_required_fields', 422);
}

if (!$checklist_odor_ok || !$checklist_visual_ok || !$checklist_storage_ok) {
    sendJsonResponse(false, null, 'checklist_failed', 422);
}

// Handle Photo Upload
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
        sendJsonResponse(false, null, 'invalid_photo_type', 422);
    }
    
    $uploadDir = __DIR__ . '/../../uploads/verification/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $fileName = uniqid('verify_') . '.' . $ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fileName)) {
        $photo_path = BASE_URL . '/uploads/verification/' . $fileName;
    }
}

$pdo = Database::getInstance();
$user = getCurrentUser($pdo);

try {
    $pdo->beginTransaction();

    // Verify claim belongs to this NGO and get listing OTP
    $stmt = $pdo->prepare("
        SELECT c.*, l.otp, l.id as listing_id, l.status as listing_status 
        FROM claims c 
        JOIN listings l ON c.listing_id = l.id 
        WHERE c.id = ? AND c.claimant_id = ?
    ");
    $stmt->execute([$claimId, $user['id']]);
    $claim = $stmt->fetch();

    if (!$claim) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'claim_not_found_or_unauthorized', 404);
    }
    
    if ($claim['listing_status'] !== 'RESERVED') {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'invalid_listing_state', 400);
    }

    if ($claim['otp'] !== $otp) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'invalid_otp', 422);
    }

    // Update Claim
    $stmtClaim = $pdo->prepare("
        UPDATE claims 
        SET otp_verified_at = NOW(), 
            checklist_odor_ok = ?, 
            checklist_visual_ok = ?, 
            checklist_storage_ok = ?,
            verification_photo_path = ?
        WHERE id = ?
    ");
    $stmtClaim->execute([$checklist_odor_ok, $checklist_visual_ok, $checklist_storage_ok, $photo_path, $claimId]);

    // Update Listing
    $stmtListing = $pdo->prepare("
        UPDATE listings 
        SET status = 'IN_TRANSIT', portions_verified = ?
        WHERE id = ?
    ");
    $stmtListing->execute([$portions_verified, $claim['listing_id']]);

    $pdo->commit();
    sendJsonResponse(true, null, null);

} catch (PDOException $e) {
    $pdo->rollBack();
    sendJsonResponse(false, null, 'database_error', 500);
}
