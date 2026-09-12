<?php
// public/recycling/index.php
require_once '../includes/auth-guard.php';
requireAuth(['recycler']);

$pdo = Database::getInstance();
$user = getCurrentUser($pdo);

include '../includes/header.php';
?>

<div class="container fade-in" style="max-width: 800px; margin-top: var(--space-8);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
        <h2>Bio-Waste Exchange</h2>
        <button onclick="loadFeed()" class="btn btn-outline btn-sm">Refresh Feed</button>
    </div>

    <?php if ($user['approval_status'] !== 'approved'): ?>
        <div class="alert alert-warning">
            <strong>Account pending approval.</strong> You cannot claim spoiled listings until an administrator verifies your registration document.
        </div>
    <?php endif; ?>

    <p style="color: var(--clr-text-muted); margin-bottom: var(--space-4);">
        These listings have passed their safe consumption window and are available for biogas/composting collection.
    </p>

    <div id="alertBox" class="alert hidden"></div>

    <div id="feedContainer" style="display: grid; gap: var(--space-4);">
        <div class="card text-center"><div class="spinner" style="border-top-color: var(--clr-primary);"></div></div>
    </div>
</div>

<script>
const isVerified = <?= $user['approval_status'] === 'approved' ? 'true' : 'false' ?>;

function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.textContent = msg;
    box.className = `alert alert-${type}`;
    box.classList.remove('hidden');
}

async function loadFeed() {
    try {
        const res = await fetch(window.BASE_URL + '/api/recycling/feed.php');
        const data = await res.json();
        
        if (!data.success) {
            document.getElementById('feedContainer').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            return;
        }
        
        const container = document.getElementById('feedContainer');
        if (data.data.listings.length === 0) {
            container.innerHTML = `<div class="card text-center"><p>No spoiled food available for recycling currently.</p></div>`;
            return;
        }
        
        let html = '';
        data.data.listings.forEach(l => {
            html += `
                <div class="card" style="border-left: 4px solid var(--clr-accent-dark);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="font-size: 1.125rem;">${l.category_name} (Inedible)</h3>
                            <p style="color: var(--clr-text-muted); font-size: 0.875rem;">Donor: ${l.donor_name} • Expired: ${new Date(l.expiry_at).toLocaleString()}</p>
                            <p style="margin-top: 5px;"><strong>Est. ${l.portions_posted} portions</strong></p>
                        </div>
                        <div>
                            ${isVerified ? `<button onclick="claimListing(${l.id})" class="btn btn-primary btn-sm" style="background: var(--clr-accent-dark);">Claim for Recycling</button>` : `<button disabled class="btn btn-outline btn-sm">Unverified</button>`}
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (e) {
        document.getElementById('feedContainer').innerHTML = '<div class="alert alert-danger">Network error.</div>';
    }
}

async function claimListing(listingId) {
    if (!confirm('Are you sure you want to claim this for recycling collection?')) return;
    
    try {
        const res = await fetch(window.BASE_URL + '/api/recycling/claim.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ listing_id: listingId })
        });
        const data = await res.json();
        
        if (data.success) {
            window.location.href = `${window.BASE_URL}/recycling/claim.php?claim_id=${data.data.claim_id}`;
        } else {
            showAlert('Failed to claim: ' + data.error, 'danger');
            loadFeed();
        }
    } catch (e) {
        showAlert('Network error.', 'danger');
    }
}

// Initial Load
loadFeed();
</script>

<?php include '../includes/footer.php'; ?>
