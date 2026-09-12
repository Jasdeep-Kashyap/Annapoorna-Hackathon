<?php
// public/donor/dashboard.php
require_once '../includes/auth-guard.php';
requireAuth(['donor']);

include '../includes/header.php';
?>

<div class="container fade-in" style="margin-top: var(--space-8);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
        <h1>Donor Dashboard</h1>
        <a href="post.php" class="btn btn-primary">Post Surplus Food</a>
    </div>
    
    <div class="metrics-grid" id="dashboardMetrics">
        <!-- Injected via JS -->
        <div class="metric-card">
            <div class="metric-label">Total Verified Donations</div>
            <div class="metric-value">
                <div class="spinner"></div>
            </div>
            <div class="metric-label">Meals Saved</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Active Listings</div>
            <div class="metric-value">
                <div class="spinner"></div>
            </div>
        </div>
    </div>

    <h2>Active & Recent Listings</h2>
    <div id="listingsContainer" style="display: grid; gap: var(--space-4);">
        <div class="text-center" style="padding: var(--space-8);"><div class="spinner" style="border-top-color: var(--clr-primary);"></div></div>
    </div>
</div>

<script>
async function loadDashboard() {
    try {
        const res = await fetch(window.BASE_URL + '/api/donor/dashboard.php');
        const data = await res.json();
        
        if (data.success) {
            // Update Metrics
            document.getElementById('dashboardMetrics').innerHTML = `
                <div class="metric-card">
                    <div class="metric-label">Total Verified Donations</div>
                    <div class="metric-value">${data.data.csr_score || 0}</div>
                    <div class="metric-label">Meals Saved</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Active Listings</div>
                    <div class="metric-value">${data.data.active_count}</div>
                </div>
            `;
            
            // Update Listings
            const container = document.getElementById('listingsContainer');
            if (data.data.listings.length === 0) {
                container.innerHTML = `<div class="card text-center"><p class="mb-4">You have no listings yet.</p><a href="post.php" class="btn btn-outline">Post your first surplus</a></div>`;
                return;
            }
            
            let html = '';
            data.data.listings.forEach(l => {
                const isCompleted = l.status === 'COMPLETED';
                const isAvailable = l.status === 'AVAILABLE' || l.status === 'RESERVED' || l.status === 'IN_TRANSIT';
                
                html += `
                    <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; font-size: 1.125rem;">${l.category_name}</div>
                            <div style="color: var(--clr-text-muted); font-size: 0.875rem;">
                                Posted: ${new Date(l.created_at).toLocaleString()}<br>
                                Portions: ${l.portions_posted} posted ${l.portions_verified !== null ? `(${l.portions_verified} verified)` : ''}
                            </div>
                            <div style="margin-top: var(--space-2);">
                                <span class="status-badge status-${l.status.toLowerCase()}">${l.status}</span>
                            </div>
                        </div>
                        <div>
                            ${isAvailable ? `<a href="status.php?id=${l.id}" class="btn btn-outline btn-sm">View Status</a>` : ''}
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }
    } catch (e) {
        console.error('Failed to load dashboard', e);
        document.getElementById('listingsContainer').innerHTML = '<div class="alert alert-danger">Failed to load dashboard.</div>';
    }
}

document.addEventListener('DOMContentLoaded', loadDashboard);
</script>

<?php include '../includes/footer.php'; ?>
