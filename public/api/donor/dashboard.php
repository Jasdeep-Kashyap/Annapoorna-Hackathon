<?php
// public/api/donor/dashboard.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['donor']);
$pdo = Database::getInstance();
$userId = $_SESSION['user_id'];

try {
    // Get listings
    $stmt = $pdo->prepare("
        SELECT l.*, c.name as category_name 
        FROM listings l 
        JOIN food_categories c ON l.category_id = c.id 
        WHERE l.donor_id = ? 
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$userId]);
    $listings = $stmt->fetchAll();

    // Get CSR score (Total verified portions completed)
    $stmt = $pdo->prepare("SELECT SUM(portions_verified) as csr_score FROM listings WHERE donor_id = ? AND status = 'COMPLETED'");
    $stmt->execute([$userId]);
    $scoreRow = $stmt->fetch();
    $csrScore = $scoreRow['csr_score'] ?: 0;

    $activeCount = 0;
    foreach ($listings as $l) {
        if (in_array($l['status'], ['AVAILABLE', 'RESERVED', 'IN_TRANSIT'])) {
            $activeCount++;
        }
    }

    sendJsonResponse(true, [
        'csr_score' => (int) $csrScore,
        'active_count' => $activeCount,
        'listings' => $listings
    ], null);

} catch (PDOException $e) {
    sendJsonResponse(false, null, 'database_error', 500);
}
