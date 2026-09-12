<?php
// public/api/claims/complete.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['ngo']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'method_not_allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$claimId = intval($data['claim_id'] ?? 0);

if (!$claimId) {
    sendJsonResponse(false, null, 'missing_claim_id', 422);
}

$pdo = Database::getInstance();
$user = getCurrentUser($pdo);

try {
    $pdo->beginTransaction();

    // Verify ownership and state
    $stmt = $pdo->prepare("
        SELECT c.*, l.status as listing_status 
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
    
    if ($claim['listing_status'] !== 'IN_TRANSIT') {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'invalid_listing_state', 400);
    }

    // Update Claim
    $stmtClaim = $pdo->prepare("UPDATE claims SET completed_at = NOW() WHERE id = ?");
    $stmtClaim->execute([$claimId]);

    // Update Listing
    $stmtListing = $pdo->prepare("UPDATE listings SET status = 'COMPLETED' WHERE id = ?");
    $stmtListing->execute([$claim['listing_id']]);

    $pdo->commit();
    sendJsonResponse(true, null, null);

} catch (PDOException $e) {
    $pdo->rollBack();
    sendJsonResponse(false, null, 'database_error', 500);
}
