<?php
// public/api/listings/feed.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['ngo']);

$pdo = Database::getInstance();

$lat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) && is_numeric($_GET['lng']) ? floatval($_GET['lng']) : null;

if ($lat === null || $lng === null) {
    sendJsonResponse(false, null, 'missing_location', 400);
}

// Fetch current user for context (used below)
$user = getCurrentUser($pdo);

try {
    // 1. Run Sweep Queries (Radius expansion and Expiry)

    // Expand radius for listings unclaimed past their category's window
    $pdo->exec("
        UPDATE listings l
        JOIN food_categories fc ON l.category_id = fc.id
        SET l.radius_km = 10.0
        WHERE l.status = 'AVAILABLE'
          AND l.radius_km = 5.0
          AND TIMESTAMPDIFF(MINUTE, l.created_at, NOW()) > fc.radius_expand_minutes
    ");

    // Expire listings past their expiry window
    $pdo->exec("
        UPDATE listings
        SET status = 'EXPIRED_SPOILED'
        WHERE status = 'AVAILABLE' AND expiry_at < NOW()
    ");

    // 2. Fetch Feed with Haversine distance
    // Wrap in a subquery so HAVING can safely reference the computed distance_km alias.
    // LEAST(1, ...) clamps the ACOS argument to avoid domain errors from floating-point
    // rounding on very close coordinates.
    $stmt = $pdo->prepare("
        SELECT * FROM (
            SELECT
                l.id, l.portions_posted, l.cooked_at, l.expiry_at,
                l.lat, l.lng, l.radius_km,
                c.name AS category_name,
                u.org_name AS donor_name,
                (6371 * ACOS(
                    LEAST(1, COS(RADIANS(:ngo_lat1)) * COS(RADIANS(l.lat)) *
                    COS(RADIANS(l.lng) - RADIANS(:ngo_lng1)) +
                    SIN(RADIANS(:ngo_lat2)) * SIN(RADIANS(l.lat)))
                )) AS distance_km
            FROM listings l
            JOIN food_categories c ON l.category_id = c.id
            JOIN users u ON l.donor_id = u.id
            WHERE l.status = 'AVAILABLE'
        ) AS feed
        WHERE distance_km <= radius_km
        ORDER BY expiry_at ASC
    ");

    $stmt->bindValue(':ngo_lat1', $lat, PDO::PARAM_STR);
    $stmt->bindValue(':ngo_lat2', $lat, PDO::PARAM_STR);
    $stmt->bindValue(':ngo_lng1', $lng, PDO::PARAM_STR);
    $stmt->execute();

    $listings = $stmt->fetchAll();

    // Return distance as formatted string for UI convenience
    foreach ($listings as &$l) {
        $l['distance_km_formatted'] = number_format($l['distance_km'], 1);
    }

    sendJsonResponse(true, ['listings' => $listings], null);

} catch (PDOException $e) {
    sendJsonResponse(false, null, 'database_error: ' . $e->getMessage(), 500);
}
