<?php
// public/api/listings/create.php
require_once '../../../config/db.php';
require_once '../../includes/auth-guard.php';

requireAuth(['donor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'method_not_allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data && !empty($_POST)) {
    // Fallback for FormData (if we upload photo later)
    $data = $_POST;
}

$category_id = intval($data['category_id'] ?? 0);
$portions_posted = intval($data['portions_posted'] ?? 0);
$cooked_at = $data['cooked_at'] ?? '';
$lat = floatval($data['lat'] ?? 0);
$lng = floatval($data['lng'] ?? 0);

if (!$category_id || !$portions_posted || !$cooked_at || !$lat || !$lng) {
    sendJsonResponse(false, null, 'missing_required_fields', 422);
}

$pdo = Database::getInstance();

try {
    // Get category to calculate expiry
    $stmt = $pdo->prepare("SELECT default_expiry_minutes, donor_selectable FROM food_categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();

    if (!$category || !$category['donor_selectable']) {
        sendJsonResponse(false, null, 'invalid_category', 422);
    }

    // Calculate expiry
    $expiry_at = date('Y-m-d H:i:s', strtotime($cooked_at) + ($category['default_expiry_minutes'] * 60));
    
    // Generate 4-digit OTP
    $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Insert Listing
    $stmt = $pdo->prepare("
        INSERT INTO listings (donor_id, category_id, portions_posted, cooked_at, expiry_at, lat, lng, otp) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'], $category_id, $portions_posted, $cooked_at, $expiry_at, $lat, $lng, $otp
    ]);
    
    $listingId = $pdo->lastInsertId();
    
    sendJsonResponse(true, [
        'listing_id' => $listingId,
        'otp' => $otp,
        'expiry_at' => $expiry_at
    ], null, 201);

} catch (PDOException $e) {
    sendJsonResponse(false, null, 'database_error', 500);
}
