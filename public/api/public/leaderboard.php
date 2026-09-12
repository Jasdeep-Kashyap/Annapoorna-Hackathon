<?php
// public/api/public/leaderboard.php
require_once '../../../config/db.php';
$pdo = Database::getInstance();

$type = $_GET['type'] ?? 'donors'; // 'donors' or 'ngos'

try {
    if ($type === 'ngos') {
        // top NGOs ranked by count of COMPLETED claims
        $stmt = $pdo->query("
            SELECT u.org_name, COUNT(c.id) as score
            FROM users u
            JOIN claims c ON u.id = c.claimant_id
            WHERE u.role = 'ngo' AND c.completed_at IS NOT NULL
            GROUP BY u.id
            ORDER BY score DESC
            LIMIT 10
        ");
    } else {
        // top Donors ranked by SUM(portions_verified)
        $stmt = $pdo->query("
            SELECT u.org_name, SUM(l.portions_verified) as score
            FROM users u
            JOIN listings l ON u.id = l.donor_id
            WHERE u.role = 'donor' AND l.status = 'COMPLETED'
            GROUP BY u.id
            ORDER BY score DESC
            LIMIT 10
        ");
    }

    $leaderboard = $stmt->fetchAll();
    
    // Ensure scores are ints
    foreach ($leaderboard as &$row) {
        $row['score'] = (int)$row['score'];
    }

    sendJsonResponse(true, ['leaderboard' => $leaderboard], null);

} catch (PDOException $e) {
    sendJsonResponse(false, null, 'database_error', 500);
}
