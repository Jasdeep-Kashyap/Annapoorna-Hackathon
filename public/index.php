<?php
// public/index.php
// Landing & Impact Dashboard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'includes/header.php';
?>

<div class="container fade-in">
    <!-- Hero Section -->
    <div style="text-align: center; padding: var(--space-12) 0 var(--space-8);">
        <h1 style="font-size: 3.5rem; letter-spacing: -0.02em; margin-bottom: var(--space-4);">
            Zero Waste. <span style="color: var(--clr-primary);">Maximum Impact.</span>
        </h1>
        <p style="font-size: 1.25rem; color: var(--clr-text-muted); max-width: 600px; margin: 0 auto var(--space-6);">
            Annapoorna connects food donors with NGOs and recyclers in real-time. Join our mission to ensure no surplus food goes to landfill.
        </p>
        <?php if (!$isLoggedIn): ?>
            <a href="auth.php" class="btn btn-primary btn-lg">Join the Platform</a>
            <a href="#impact" class="btn btn-outline btn-lg" style="margin-left: var(--space-2);">View Live Impact</a>
        <?php endif; ?>
    </div>

    <!-- Impact Counters -->
    <div id="impact" style="margin-top: var(--space-8);">
        <h2 class="text-center" style="margin-bottom: var(--space-6);">Live Platform Impact</h2>
        
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-label">Meals Saved</div>
                <div class="metric-value" id="valMeals"><div class="spinner"></div></div>
            </div>
            <div class="metric-card" style="border-top-color: var(--clr-accent);">
                <div class="metric-label">Organic Waste Diverted (kg)</div>
                <div class="metric-value" id="valWaste"><div class="spinner"></div></div>
            </div>
            <div class="metric-card" style="border-top-color: #3B82F6;">
                <div class="metric-label">CO₂e Offset (kg)</div>
                <div class="metric-value" id="valCo2"><div class="spinner"></div></div>
            </div>
        </div>
    </div>

    <!-- Leaderboards -->
    <div style="display: flex; flex-direction: column; md:flex-row; gap: var(--space-6); margin-top: var(--space-8);">
        
        <div class="card" style="flex: 1;">
            <h3 style="margin-bottom: var(--space-4);">Top Donors</h3>
            <canvas id="donorsChart" height="200"></canvas>
        </div>

        <div class="card" style="flex: 1;">
            <h3 style="margin-bottom: var(--space-4);">Top Rescuers (NGOs)</h3>
            <canvas id="ngosChart" height="200"></canvas>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
async function loadDashboard() {
    try {
        // Load Metrics
        const metricsRes = await fetch(window.BASE_URL + '/api/public/metrics.php');
        const metricsData = await metricsRes.json();
        if (metricsData.success) {
            document.getElementById('valMeals').innerText = metricsData.data.meals_saved;
            document.getElementById('valWaste').innerText = metricsData.data.organic_waste_diverted_kg;
            document.getElementById('valCo2').innerText = metricsData.data.co2e_offset_kg;
        }

        // Load Donors Leaderboard
        const donorsRes = await fetch(window.BASE_URL + '/api/public/leaderboard.php?type=donors');
        const donorsData = await donorsRes.json();
        if (donorsData.success && donorsData.data.leaderboard.length > 0) {
            renderChart('donorsChart', donorsData.data.leaderboard, 'Meals Donated', '#10B981');
        } else {
            document.getElementById('donorsChart').outerHTML = '<p class="text-center text-muted">No data yet.</p>';
        }

        // Load NGOs Leaderboard
        const ngosRes = await fetch(window.BASE_URL + '/api/public/leaderboard.php?type=ngos');
        const ngosData = await ngosRes.json();
        if (ngosData.success && ngosData.data.leaderboard.length > 0) {
            renderChart('ngosChart', ngosData.data.leaderboard, 'Rescues Completed', '#0F766E');
        } else {
            document.getElementById('ngosChart').outerHTML = '<p class="text-center text-muted">No data yet.</p>';
        }

    } catch (e) {
        console.error('Failed to load public dashboard', e);
    }
}

function renderChart(canvasId, dataList, label, color) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    const labels = dataList.map(item => item.org_name);
    const data = dataList.map(item => item.score);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: color,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { beginAtZero: true }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', loadDashboard);
// Refresh public dashboard periodically
setInterval(loadDashboard, 30000);
</script>

<?php include 'includes/footer.php'; ?>
