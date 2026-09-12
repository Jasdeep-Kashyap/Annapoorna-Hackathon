<?php
// public/api/recycling/claim.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['recycler']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'method_not_allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$listingId = intval($data['listing_id'] ?? 0);

if (!$listingId) {
    sendJsonResponse(false, null, 'missing_listing_id', 422);
}

$pdo = Database::getInstance();
$user = getCurrentUser($pdo);

if ($user['approval_status'] !== 'approved') {
    sendJsonResponse(false, null, 'account_not_verified_by_admin', 403);
}

try {
    $pdo->beginTransaction();

    // Atomic claim update EXPIRED_SPOILED -> RECYCLE_CLAIMED
    $stmt = $pdo->prepare("UPDATE listings SET status = 'RECYCLE_CLAIMED' WHERE id = ? AND status = 'EXPIRED_SPOILED'");
    $stmt->execute([$listingId]);
    
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'already_claimed_or_unavailable', 409);
    }
    
    // Insert claim record
    $stmtClaim = $pdo->prepare("INSERT INTO claims (listing_id, claimant_id, claimant_role) VALUES (?, ?, 'recycler')");
    $stmtClaim->execute([$listingId, $user['id']]);
    $claimId = $pdo->lastInsertId();
    
    $pdo->commit();
    
    sendJsonResponse(true, ['claim_id' => $claimId], null);

} catch (PDOException $e) {
    $pdo->rollBack();
    sendJsonResponse(false, null, 'database_error', 500);
}
