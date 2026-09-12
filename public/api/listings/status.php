<?php
// public/api/listings/status.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['donor']);

if (!isset($_GET['id'])) {
    sendJsonResponse(false, null, 'missing_id', 400);
}

$listingId = intval($_GET['id']);
$pdo = Database::getInstance();

try {
    // Get listing details and verify ownership
    $stmt = $pdo->prepare("
        SELECT l.*, c.name as category_name
        FROM listings l
        JOIN food_categories c ON l.category_id = c.id
        WHERE l.id = ? AND l.donor_id = ?
    ");
    $stmt->execute([$listingId, $_SESSION['user_id']]);
    $listing = $stmt->fetch();

    if (!$listing) {
        sendJsonResponse(false, null, 'not_found_or_unauthorized', 404);
    }

    // Get active claim info if reserved or in_transit
    $claimantInfo = null;
    if (in_array($listing['status'], ['RESERVED', 'IN_TRANSIT', 'COMPLETED', 'RECYCLE_CLAIMED', 'RECYCLED'])) {
        $stmt = $pdo->prepare("
            SELECT u.org_name, u.phone, cl.claimant_role, cl.claimed_at, cl.otp_verified_at, cl.completed_at
            FROM claims cl
            JOIN users u ON cl.claimant_id = u.id
            WHERE cl.listing_id = ? AND cl.no_show_reported = 0
            ORDER BY cl.claimed_at DESC LIMIT 1
        ");
        $stmt->execute([$listingId]);
        $claimantInfo = $stmt->fetch();
    }

    sendJsonResponse(true, [
        'listing' => $listing,
        'claimant' => $claimantInfo
    ], null);

} catch (PDOException $e) {
    sendJsonResponse(false, null, 'database_error', 500);
}
