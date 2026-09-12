    </main>

    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3>Annapoorna</h3>
                    <p>Connecting surplus food with those in need, safely and quickly.</p>
                </div>
                <div class="footer-links">
                    <div class="link-group">
                        <h4>Platform</h4>
                        <a href="<?= BASE_URL ?>/index.php">Impact Dashboard</a>
                        <a href="<?= BASE_URL ?>/auth.php">Join Platform</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Annapoorna Platform. All rights reserved.</p>
                <p class="metrics-note">* Impact metrics calculated using EPA WARM model equivalents (0.45 kg CO2e per kg organic waste diverted from landfill).</p>
            </div>
        </div>
    </footer>

    <!-- Global scripts -->
    <script>
        // Global logout handler
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async () => {
                try {
                    const res = await fetch(window.BASE_URL + '/api/logout.php', { method: 'POST' });
                    const data = await res.json();
                    if (data.success) {
                        window.location.href = window.BASE_URL + '/index.php';
                    }
                } catch (e) {
                    console.error('Logout failed', e);
                }
            });
        }
    </script>
</body>
</html>
