<?php
// public/api/recycling/log-intake.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['recycler']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'method_not_allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$claimId = intval($data['claim_id'] ?? 0);
$bulk_weight_kg = floatval($data['bulk_weight_kg'] ?? 0);

if (!$claimId || !$bulk_weight_kg) {
    sendJsonResponse(false, null, 'missing_required_fields', 422);
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
    
    if ($claim['listing_status'] !== 'RECYCLE_CLAIMED') {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'invalid_listing_state', 400);
    }

    // Update Claim
    $stmtClaim = $pdo->prepare("UPDATE claims SET bulk_weight_kg = ?, completed_at = NOW() WHERE id = ?");
    $stmtClaim->execute([$bulk_weight_kg, $claimId]);

    // Update Listing
    $stmtListing = $pdo->prepare("UPDATE listings SET status = 'RECYCLED' WHERE id = ?");
    $stmtListing->execute([$claim['listing_id']]);

    $pdo->commit();
    sendJsonResponse(true, null, null);

} catch (PDOException $e) {
    $pdo->rollBack();
    sendJsonResponse(false, null, 'database_error', 500);
}
