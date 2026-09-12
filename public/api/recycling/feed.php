<?php
// public/api/recycling/feed.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['recycler']);

$pdo = Database::getInstance();
$user = getCurrentUser($pdo);

if ($user['approval_status'] !== 'approved') {
    sendJsonResponse(false, null, 'account_not_verified_by_admin', 403);
}

try {
    // 1. Run Sweep Query
    $pdo->exec("
        UPDATE listings
        SET status = 'EXPIRED_SPOILED'
        WHERE status = 'AVAILABLE' AND expiry_at < NOW()
    ");

    // 2. Fetch EXPIRED_SPOILED Listings
    $stmt = $pdo->prepare("
        SELECT l.id, l.portions_posted, l.cooked_at, l.expiry_at, l.lat, l.lng, c.name as category_name, u.org_name as donor_name, u.phone as donor_phone
        FROM listings l
        JOIN food_categories c ON l.category_id = c.id
        JOIN users u ON l.donor_id = u.id
        WHERE l.status = 'EXPIRED_SPOILED'
        ORDER BY l.expiry_at DESC
    ");
    
    $stmt->execute();
    $listings = $stmt->fetchAll();
    
    sendJsonResponse(true, ['listings' => $listings], null);

} catch (PDOException $e) {
    sendJsonResponse(false, null, 'database_error', 500);
}
