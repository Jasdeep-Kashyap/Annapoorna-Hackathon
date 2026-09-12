<?php
// public/ngo/feed.php
require_once '../includes/auth-guard.php';
requireAuth(['ngo']);

$pdo = Database::getInstance();
$user = getCurrentUser($pdo);

include '../includes/header.php';
?>

<div class="container fade-in" style="margin-top: var(--space-4);">
    <?php if ($user['approval_status'] !== 'approved'): ?>
        <div class="alert alert-warning">
            <strong>Account pending approval.</strong> You cannot claim listings until an administrator verifies your registration document. You can still browse the feed.
        </div>
    <?php endif; ?>

    <div style="display: flex; flex-direction: column; md:flex-row; gap: var(--space-4);">
        <!-- Feed Column -->
        <div style="flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                <h2>Surplus Feed</h2>
                <button onclick="initFeed()" class="btn btn-outline btn-sm">Refresh</button>
            </div>
            
            <div id="feedContainer" style="display: grid; gap: var(--space-4);">
                <div class="card text-center"><div class="spinner" style="border-top-color: var(--clr-primary);"></div><p class="mt-4">Locating nearby surplus...</p></div>
            </div>
        </div>
        
        <!-- Map Column -->
        <div style="flex: 1; display: none;" id="mapColumn">
            <!-- Map will be added if Leaflet is initialized -->
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let currentLat = null;
let currentLng = null;
const isVerified = <?= $user['approval_status'] === 'approved' ? 'true' : 'false' ?>;

function initFeed() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                currentLat = position.coords.latitude;
                currentLng = position.coords.longitude;
                loadFeed();
            },
            (error) => {
                document.getElementById('feedContainer').innerHTML = '<div class="alert alert-danger">Please allow location access to see nearby food.</div>';
            }
        );
    } else {
        document.getElementById('feedContainer').innerHTML = '<div class="alert alert-danger">Geolocation not supported.</div>';
    }
}

async function loadFeed() {
    try {
        const res = await fetch(`${window.BASE_URL}/api/listings/feed.php?lat=${currentLat}&lng=${currentLng}`);
        const data = await res.json();
        
        if (!data.success) {
            document.getElementById('feedContainer').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            return;
        }
        
        const container = document.getElementById('feedContainer');
        if (data.data.listings.length === 0) {
            container.innerHTML = `<div class="card text-center"><p>No available surplus food nearby.</p></div>`;
            return;
        }
        
        let html = '';
        data.data.listings.forEach(l => {
            const expiryTime = new Date(l.expiry_at).getTime();
            const now = new Date().getTime();
            const timeLeftMins = Math.floor(Math.max(0, expiryTime - now) / 60000);
            
            html += `
                <div class="card" style="border-left: 4px solid var(--clr-primary);">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h3 style="font-size: 1.125rem;">${l.category_name}</h3>
                            <p style="color: var(--clr-text-muted); font-size: 0.875rem;">From: ${l.donor_name} • ${l.distance_km_formatted} km away</p>
                            <p style="margin-top: 5px;"><strong>${l.portions_posted} portions</strong></p>
                            <p style="color: var(--clr-accent-dark); font-size: 0.875rem; font-weight: bold; margin-top: 5px;">⏳ ${timeLeftMins} mins left</p>
                        </div>
                        <div>
                            ${isVerified ? `<button onclick="claimListing(${l.id})" class="btn btn-primary btn-sm">Claim</button>` : `<button disabled class="btn btn-outline btn-sm">Unverified</button>`}
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (e) {
        console.error(e);
        document.getElementById('feedContainer').innerHTML = '<div class="alert alert-danger">Network error.</div>';
    }
}

async function claimListing(listingId) {
    if (!confirm('Are you sure you want to claim this listing? You must pick it up immediately.')) return;
    
    try {
        const res = await fetch(window.BASE_URL + '/api/listings/claim.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ listing_id: listingId })
        });
        const data = await res.json();
        
        if (data.success) {
            window.location.href = `${window.BASE_URL}/ngo/verify.php?claim_id=${data.data.claim_id}`;
        } else {
            alert('Failed to claim: ' + data.error);
            loadFeed(); // Refresh feed
        }
    } catch (e) {
        alert('Network error.');
    }
}

// Initial Load
initFeed();
// Poll every 15 seconds
setInterval(() => {
    if (currentLat && currentLng) loadFeed();
}, 15000);

</script>

<?php include '../includes/footer.php'; ?>
