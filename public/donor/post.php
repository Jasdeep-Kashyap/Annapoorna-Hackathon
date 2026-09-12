<?php
// public/donor/post.php
require_once '../includes/auth-guard.php';
requireAuth(['donor']);

$pdo = Database::getInstance();
$stmt = $pdo->query("SELECT id, name FROM food_categories WHERE donor_selectable = 1");
$categories = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container fade-in" style="max-width: 600px; margin-top: var(--space-8);">
    <div class="card">
        <h2>Post Surplus Food</h2>
        <p class="mb-4" style="color: var(--clr-text-muted); font-size: 0.875rem;">
            Only pre-approved, safe-to-donate categories are available. Raw meat, dairy requiring cold-chain, and cut produce cannot be donated through this platform for food safety reasons.
        </p>

        <div id="alertBox" class="alert hidden"></div>

        <form id="postForm">
            <div class="form-group">
                <label class="form-label">Food Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select a category...</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Portions Available</label>
                <input type="number" name="portions_posted" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label class="form-label">Time Prepared / Cooked At</label>
                <input type="datetime-local" name="cooked_at" id="cookedAtInput" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Pickup Location</label>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <input type="number" step="any" name="lat" id="lat" class="form-control" placeholder="Latitude" required>
                    <input type="number" step="any" name="lng" id="lng" class="form-control" placeholder="Longitude" required>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="getLocation()">Capture Current Location</button>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--space-4);">
                Post Listing
            </button>
        </form>
    </div>
</div>

<script>
// Set default datetime to now
const now = new Date();
now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
document.getElementById('cookedAtInput').value = now.toISOString().slice(0,16);

function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.textContent = msg;
    box.className = `alert alert-${type}`;
    box.classList.remove('hidden');
}

function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                document.getElementById('lat').value = position.coords.latitude;
                document.getElementById('lng').value = position.coords.longitude;
                showAlert('Location captured!', 'success');
            },
            (error) => {
                showAlert('Could not get location. Please allow location permissions.', 'danger');
            }
        );
    } else {
        showAlert('Geolocation is not supported by this browser.', 'danger');
    }
}

// Try to auto-capture on load
window.addEventListener('load', () => {
    // getLocation(); // Optionally auto-prompt. For demo, leaving as manual click to not annoy user immediately.
});

document.getElementById('postForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Ensure numbers
    data.category_id = parseInt(data.category_id);
    data.portions_posted = parseInt(data.portions_posted);
    data.lat = parseFloat(data.lat);
    data.lng = parseFloat(data.lng);
    
    try {
        const res = await fetch(window.BASE_URL + '/api/listings/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.success) {
            window.location.href = `${window.BASE_URL}/donor/status.php?id=${result.data.listing_id}`;
        } else {
            showAlert('Failed to create listing: ' + result.error, 'danger');
            btn.disabled = false;
        }
    } catch (err) {
        showAlert('Network error occurred.', 'danger');
        btn.disabled = false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
