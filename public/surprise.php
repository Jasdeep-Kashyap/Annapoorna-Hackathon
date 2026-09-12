<?php
// public/surprise.php
require_once 'includes/auth-guard.php';
include 'includes/header.php';
?>

<div class="container fade-in" style="max-width: 700px; margin-top: var(--space-8); margin-bottom: var(--space-8);">
    <div class="card">
        <h1 style="font-size: 1.75rem; font-weight: 700; margin-bottom: var(--space-2);">Surprise</h1>
        <p style="color: var(--clr-text-muted);"><img src="<?= BASE_URL ?>/../config/dcdf5a7cf1471553bf07757c90dbde09.jpg" alt="Surprise" style="max-width: 100%; border-radius: var(--radius-md);"></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>