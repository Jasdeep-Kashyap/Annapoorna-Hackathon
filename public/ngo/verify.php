<?php
// public/ngo/verify.php
require_once '../includes/auth-guard.php';
requireAuth(['ngo']);

$claimId = isset($_GET['claim_id']) ? intval($_GET['claim_id']) : 0;

$pdo = Database::getInstance();
$user = getCurrentUser($pdo);

// Fetch claim details
$stmt = $pdo->prepare("
    SELECT c.*, l.status as listing_status, l.portions_posted, cat.name as category_name, u.org_name as donor_name, u.phone as donor_phone, l.otp
    FROM claims c
    JOIN listings l ON c.listing_id = l.id
    JOIN food_categories cat ON l.category_id = cat.id
    JOIN users u ON l.donor_id = u.id
    WHERE c.id = ? AND c.claimant_id = ?
");
$stmt->execute([$claimId, $user['id']]);
$claim = $stmt->fetch();

if (!$claim) {
    header('Location: feed.php');
    exit;
}

include '../includes/header.php';
?>

<div class="container fade-in" style="max-width: 600px; margin-top: var(--space-8);">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
        <h2>Claim Verification</h2>
        <span class="status-badge status-<?= strtolower($claim['listing_status']) ?>"><?= $claim['listing_status'] ?></span>
    </div>

    <div class="card mb-4" style="background: var(--clr-primary-light); border: none;">
        <h3 style="font-size: 1.125rem;"><?= htmlspecialchars($claim['category_name']) ?></h3>
        <p style="color: var(--clr-primary-dark); font-weight: bold; margin-top: 5px;">Donor: <?= htmlspecialchars($claim['donor_name']) ?></p>
        <p style="margin-top: 5px;">📞 <a href="tel:<?= $claim['donor_phone'] ?>" style="color: var(--clr-primary-dark);"><?= $claim['donor_phone'] ?></a></p>
        <p style="margin-top: 5px; font-size: 0.875rem;">Expected: <?= $claim['portions_posted'] ?> portions</p>
    </div>

    <div id="alertBox" class="alert hidden"></div>

    <?php if ($claim['listing_status'] === 'RESERVED'): ?>
        <div class="card">
            <h3 style="margin-bottom: var(--space-4);">Step 1: On-site Verification</h3>
            <form id="verifyForm" enctype="multipart/form-data">
                <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                
                <div class="form-group" style="background: var(--clr-bg); padding: var(--space-4); border-radius: var(--radius-md);">
                    <label class="form-label" style="font-size: 1rem;">Donor OTP</label>
                    <p style="font-size: 0.875rem; color: var(--clr-text-muted); margin-bottom: var(--space-2);">Ask the donor for their 4-digit code.</p>
                    <input type="text" name="otp" class="form-control" placeholder="0000" maxlength="4" style="font-size: 2rem; letter-spacing: 0.5em; text-align: center; font-weight: bold;" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Safety Checklist</label>
                    <div style="margin-bottom: 10px;">
                        <label style="display: flex; gap: 10px; align-items: center; font-weight: 400;">
                            <input type="checkbox" name="checklist_odor_ok" required> Odor is normal (no spoilage smell)
                        </label>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label style="display: flex; gap: 10px; align-items: center; font-weight: 400;">
                            <input type="checkbox" name="checklist_visual_ok" required> Visual integrity is good (no mold/slime)
                        </label>
                    </div>
                    <div>
                        <label style="display: flex; gap: 10px; align-items: center; font-weight: 400;">
                            <input type="checkbox" name="checklist_storage_ok" required> Storage condition is safe
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Actual Portions Received</label>
                    <input type="number" name="portions_verified" class="form-control" value="<?= $claim['portions_posted'] ?>" min="1" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Verification Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*" capture="environment" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Verify & Accept Transfer</button>
            </form>
            <div style="margin-top: var(--space-4); text-align: center;">
                <button onclick="reportNoShow()" class="btn btn-outline btn-sm" style="color: var(--clr-danger); border-color: var(--clr-danger);">Donor didn't show up?</button>
            </div>
        </div>
    <?php elseif ($claim['listing_status'] === 'IN_TRANSIT'): ?>
        <div class="card text-center">
            <h3 style="margin-bottom: var(--space-4);">Step 2: Delivery</h3>
            <div style="font-size: 4rem; margin-bottom: var(--space-4);">🚚</div>
            <p class="mb-4">You have successfully collected the food. Please deliver it to the beneficiary shelter.</p>
            
            <button id="completeBtn" class="btn btn-success btn-lg" style="width: 100%; background: var(--clr-success); color: white; border: none; padding: var(--space-3); border-radius: var(--radius-md); cursor: pointer;">
                Confirm Drop-off
            </button>
        </div>
    <?php elseif ($claim['listing_status'] === 'COMPLETED'): ?>
        <div class="card text-center" style="border: 2px solid var(--clr-success);">
            <div style="font-size: 4rem; color: var(--clr-success); margin-bottom: var(--space-4);">✅</div>
            <h3 style="margin-bottom: var(--space-2);">Delivery Completed!</h3>
            <p>Thank you for redistributing surplus food.</p>
            <a href="feed.php" class="btn btn-primary mt-4">Return to Feed</a>
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

const verifyForm = document.getElementById('verifyForm');
if (verifyForm) {
    verifyForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button');
        btn.disabled = true;
        btn.innerText = "Verifying...";
        
        try {
            const formData = new FormData(e.target);
            const res = await fetch(window.BASE_URL + '/api/claims/verify.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                window.location.reload();
            } else {
                showAlert('Verification failed: ' + data.error, 'danger');
                btn.disabled = false;
                btn.innerText = "Verify & Accept Transfer";
            }
        } catch (e) {
            showAlert('Network error.', 'danger');
            btn.disabled = false;
            btn.innerText = "Verify & Accept Transfer";
        }
    });
}

const completeBtn = document.getElementById('completeBtn');
if (completeBtn) {
    completeBtn.addEventListener('click', async () => {
        completeBtn.disabled = true;
        completeBtn.innerText = "Completing...";
        try {
            const res = await fetch(window.BASE_URL + '/api/claims/complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ claim_id: <?= $claimId ?> })
            });
            const data = await res.json();
            
            if (data.success) {
                window.location.reload();
            } else {
                showAlert('Failed to complete: ' + data.error, 'danger');
                completeBtn.disabled = false;
                completeBtn.innerText = "Confirm Drop-off";
            }
        } catch (e) {
            showAlert('Network error.', 'danger');
            completeBtn.disabled = false;
            completeBtn.innerText = "Confirm Drop-off";
        }
    });
}

async function reportNoShow() {
    if (!confirm('Are you sure? Only do this if you waited at least 15 minutes and the donor did not show up.')) return;
    try {
        const res = await fetch(window.BASE_URL + '/api/claims/no-show.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ claim_id: <?= $claimId ?> })
        });
        const data = await res.json();
        
        if (data.success) {
            alert('No-show reported. The listing has been released.');
            window.location.href = 'feed.php';
        } else {
            showAlert('Failed to report: ' + data.error, 'danger');
        }
    } catch (e) {
        showAlert('Network error.', 'danger');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
