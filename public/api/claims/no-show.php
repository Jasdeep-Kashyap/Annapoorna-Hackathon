<?php
// public/api/claims/no-show.php
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

    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT c.*, l.status as listing_status, l.donor_id
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
    
    // Check grace period (15 mins)
    $claimedAt = new DateTime($claim['claimed_at']);
    $now = new DateTime();
    $interval = $now->diff($claimedAt);
    $minutesPassed = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    
    if ($minutesPassed < 15) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'grace_period_not_elapsed', 400);
    }

    // Update Claim to no-show
    $stmtClaim = $pdo->prepare("UPDATE claims SET no_show_reported = 1 WHERE id = ?");
    $stmtClaim->execute([$claimId]);

    // Revert Listing to AVAILABLE
    $stmtListing = $pdo->prepare("UPDATE listings SET status = 'AVAILABLE' WHERE id = ?");
    $stmtListing->execute([$claim['listing_id']]);
    
    // Decrement donor trust score
    $stmtDonor = $pdo->prepare("UPDATE users SET trust_score = GREATEST(0, trust_score - 10) WHERE id = ?");
    $stmtDonor->execute([$claim['donor_id']]);

    $pdo->commit();
    sendJsonResponse(true, null, null);

} catch (PDOException $e) {
    $pdo->rollBack();
    sendJsonResponse(false, null, 'database_error', 500);
}
