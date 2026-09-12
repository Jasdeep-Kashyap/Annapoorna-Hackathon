<?php
// public/api/public/metrics.php
require_once '../../../config/db.php';
$pdo = Database::getInstance();

try {
    // meals_saved = SUM(verified_portions) across COMPLETED listings
    $stmt = $pdo->query("SELECT SUM(portions_verified) as meals_saved FROM listings WHERE status = 'COMPLETED'");
    $mealsSaved = (int)$stmt->fetchColumn() ?: 0;

    // organic_waste_diverted_kg = SUM(portions) * 0.45 across COMPLETED + RECYCLED
    // Actually, RECYCLED has bulk_weight_kg directly via claims, but PRD §9 says SUM(portions) * 0.45,
    // let's do exactly what PRD §9 says for simplicity, but wait, RECYCLED might not have verified portions.
    // Let's sum posted portions for RECYCLED, and verified for COMPLETED.
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN status = 'COMPLETED' THEN portions_verified ELSE portions_posted END) as total_portions
        FROM listings 
        WHERE status IN ('COMPLETED', 'RECYCLED')
    ");
    $totalPortions = (int)$stmt->fetchColumn() ?: 0;
    
    $divertedKg = $totalPortions * 0.45;
    $co2eOffset = $divertedKg * 0.45;

    sendJsonResponse(true, [
        'meals_saved' => $mealsSaved,
        'organic_waste_diverted_kg' => round($divertedKg, 2),
        'co2e_offset_kg' => round($co2eOffset, 2)
    ], null);

} catch (PDOException $e) {
    sendJsonResponse(false, null, 'database_error', 500);
}
