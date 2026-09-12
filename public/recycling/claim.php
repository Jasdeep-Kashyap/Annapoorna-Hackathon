<?php
// public/recycling/claim.php
require_once '../includes/auth-guard.php';
requireAuth(['recycler']);

$claimId = isset($_GET['claim_id']) ? intval($_GET['claim_id']) : 0;

$pdo = Database::getInstance();
$user = getCurrentUser($pdo);

// Fetch claim details
$stmt = $pdo->prepare("
    SELECT c.*, l.status as listing_status, cat.name as category_name, u.org_name as donor_name, u.phone as donor_phone, l.lat, l.lng
    FROM claims c
    JOIN listings l ON c.listing_id = l.id
    JOIN food_categories cat ON l.category_id = cat.id
    JOIN users u ON l.donor_id = u.id
    WHERE c.id = ? AND c.claimant_id = ?
");
$stmt->execute([$claimId, $user['id']]);
$claim = $stmt->fetch();

if (!$claim) {
    header('Location: index.php');
    exit;
}

include '../includes/header.php';
?>

<div class="container fade-in" style="max-width: 600px; margin-top: var(--space-8);">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
        <h2>Recycling Collection</h2>
        <span class="status-badge status-<?= strtolower($claim['listing_status']) ?>"><?= $claim['listing_status'] ?></span>
    </div>

    <div class="card mb-4" style="background: var(--clr-accent-light); border: none;">
        <h3 style="font-size: 1.125rem; color: var(--clr-accent-dark);"><?= htmlspecialchars($claim['category_name']) ?></h3>
        <p style="font-weight: bold; margin-top: 5px;">Donor: <?= htmlspecialchars($claim['donor_name']) ?></p>
        <p style="margin-top: 5px;">📞 <a href="tel:<?= $claim['donor_phone'] ?>" style="color: var(--clr-accent-dark);"><?= $claim['donor_phone'] ?></a></p>
    </div>

    <div id="alertBox" class="alert hidden"></div>

    <?php if ($claim['listing_status'] === 'RECYCLE_CLAIMED'): ?>
        <div class="card">
            <h3 style="margin-bottom: var(--space-4);">Log Collection Intake</h3>
            <p style="color: var(--clr-text-muted); font-size: 0.875rem; margin-bottom: var(--space-4);">
                Once you collect the waste, please weigh it and enter the bulk weight in kilograms. This directly contributes to the platform's waste diversion metrics.
            </p>

            <form id="logForm">
                <div class="form-group">
                    <label class="form-label">Bulk Weight (kg)</label>
                    <input type="number" step="0.01" name="bulk_weight_kg" class="form-control" min="0.1" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; background: var(--clr-accent-dark);">Log Intake & Complete</button>
            </form>
        </div>
    <?php elseif ($claim['listing_status'] === 'RECYCLED'): ?>
        <div class="card text-center" style="border: 2px solid var(--clr-success);">
            <div style="font-size: 4rem; color: var(--clr-success); margin-bottom: var(--space-4);">✅</div>
            <h3 style="margin-bottom: var(--space-2);">Intake Logged</h3>
            <p>You collected <?= $claim['bulk_weight_kg'] ?> kg of organic waste.</p>
            <p style="color: var(--clr-text-muted); font-size: 0.875rem;">This has been added to our public metrics.</p>
            <a href="index.php" class="btn btn-primary mt-4">Return to Exchange</a>
        </div>
    <?php endif; ?>
</div>

<script>
function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.textContent = msg;
    box.className = `alert alert-${type}`;
    box.classList.remove('hidden');
}

const logForm = document.getElementById('logForm');
if (logForm) {
    logForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button');
        btn.disabled = true;
        btn.innerText = "Logging...";
        
        try {
            const formData = new FormData(e.target);
            const data = {
                claim_id: <?= $claimId ?>,
                bulk_weight_kg: parseFloat(formData.get('bulk_weight_kg'))
            };
            
            const res = await fetch(window.BASE_URL + '/api/recycling/log-intake.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            
            if (result.success) {
                window.location.reload();
            } else {
                showAlert('Failed to log intake: ' + result.error, 'danger');
                btn.disabled = false;
                btn.innerText = "Log Intake & Complete";
            }
        } catch (e) {
            showAlert('Network error.', 'danger');
            btn.disabled = false;
            btn.innerText = "Log Intake & Complete";
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
