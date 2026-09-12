<?php
// public/donor/status.php
require_once '../includes/auth-guard.php';
requireAuth(['donor']);

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}
$listingId = intval($_GET['id']);

include '../includes/header.php';
?>

<div class="container fade-in" style="max-width: 600px; margin-top: var(--space-8);">
    <div class="card text-center" id="statusCard">
        <div class="spinner" style="border-top-color: var(--clr-primary);"></div>
        <p class="mt-4">Loading status...</p>
    </div>
</div>

<script>
const listingId = <?= $listingId ?>;
let pollInterval;

async function loadStatus() {
    try {
        const res = await fetch(`${window.BASE_URL}/api/listings/status.php?id=${listingId}`);
        const data = await res.json();
        
        if (!data.success) {
            document.getElementById('statusCard').innerHTML = `<div class="alert alert-danger">Error: ${data.error}</div>`;
            clearInterval(pollInterval);
            return;
        }
        
        const { listing, claimant } = data.data;
        
        let html = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                <a href="dashboard.php" class="btn btn-outline btn-sm">← Back</a>
                <span class="status-badge status-${listing.status.toLowerCase()}">${listing.status}</span>
            </div>
            
            <h2 style="margin-bottom: var(--space-1);">${listing.category_name}</h2>
            <p style="color: var(--clr-text-muted); margin-bottom: var(--space-4);">Posted: ${new Date(listing.created_at).toLocaleString()}</p>
        `;
        
        // Show OTP block
        html += `
            <div style="background: var(--clr-bg); border-radius: var(--radius-md); padding: var(--space-4); margin-bottom: var(--space-4);">
                <p style="font-size: 0.875rem; color: var(--clr-text-muted); margin-bottom: var(--space-2); text-transform: uppercase; letter-spacing: 0.05em;">Your OTP Code</p>
                <div style="font-size: 3rem; font-weight: 700; letter-spacing: 0.2em; color: var(--clr-text-main); line-height: 1;">
                    ${listing.otp}
                </div>
                <p style="font-size: 0.875rem; color: var(--clr-text-muted); margin-top: var(--space-2);">Share this only when the NGO/Recycler arrives for pickup.</p>
            </div>
        `;
        
        // Countdown
        if (listing.status === 'AVAILABLE') {
            const expiryTime = new Date(listing.expiry_at).getTime();
            const now = new Date().getTime();
            const timeLeft = Math.max(0, expiryTime - now);
            
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            
            html += `
                <div class="alert alert-warning" style="margin-bottom: var(--space-4);">
                    <strong>⏳ Expires in ${hours}h ${minutes}m</strong>
                    <p style="font-size: 0.875rem; margin-top: 5px;">Waiting for a claim within ${listing.radius_km} km...</p>
                </div>
            `;
        } else if (claimant) {
            html += `
                <div style="text-align: left; border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: var(--space-4);">
                    <h3 style="font-size: 1rem; margin-bottom: var(--space-2);">Claimed by ${claimant.org_name}</h3>
                    <p><strong>Role:</strong> <span class="role-badge role-${claimant.claimant_role.toLowerCase()}">${claimant.claimant_role}</span></p>
                    <p><strong>Phone:</strong> <a href="tel:${claimant.phone}">${claimant.phone}</a></p>
                    <p><strong>Claimed At:</strong> ${new Date(claimant.claimed_at).toLocaleTimeString()}</p>
                    ${claimant.otp_verified_at ? `<p style="color: var(--clr-success); margin-top: 10px;">✓ OTP Verified (In Transit)</p>` : ''}
                    ${claimant.completed_at ? `<p style="color: var(--clr-success); font-weight: bold; margin-top: 10px;">✓ Donation Completed</p>` : ''}
                </div>
            `;
        }
        
        document.getElementById('statusCard').innerHTML = html;
        
        // Stop polling if completed or expired
        if (['COMPLETED', 'EXPIRED_SPOILED', 'RECYCLED'].includes(listing.status)) {
            clearInterval(pollInterval);
        }
        
    } catch (e) {
        console.error('Failed to poll status', e);
    }
}

// Initial load
loadStatus();

// Poll every 10 seconds (as per techstack.md)
pollInterval = setInterval(loadStatus, 10000);

// Cleanup on unmount
window.addEventListener('beforeunload', () => clearInterval(pollInterval));
</script>

<?php include '../includes/footer.php'; ?>
