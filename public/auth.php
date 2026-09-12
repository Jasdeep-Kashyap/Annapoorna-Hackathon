<?php
// public/auth.php
require_once 'includes/auth-guard.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') header('Location: ' . BASE_URL . '/admin/dashboard.php');
    elseif ($role === 'donor') header('Location: ' . BASE_URL . '/donor/dashboard.php');
    elseif ($role === 'ngo') header('Location: ' . BASE_URL . '/ngo/feed.php');
    elseif ($role === 'recycler') header('Location: ' . BASE_URL . '/recycling/index.php');
    exit;
}

include 'includes/header.php';
?>

<div class="container fade-in" style="max-width: 500px; margin-top: var(--space-8);">
    <div class="card">
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Login</button>
            <button class="tab-btn" onclick="switchTab('register')">Register</button>
        </div>

        <!-- Alerts Container -->
        <div id="alertBox" class="alert hidden"></div>

        <!-- Login Form -->
        <form id="loginForm" class="auth-form">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <span class="btn-text">Log In</span>
            </button>
        </form>

        <!-- Registration Form -->
        <form id="registerForm" class="auth-form hidden" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" id="regRole" class="form-control" onchange="toggleRoleFields()" required>
                    <option value="donor">Donor (Hotel, Restaurant, Mess)</option>
                    <option value="ngo">NGO / Shelter</option>
                    <option value="recycler">Recycler (Biogas, Composting)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Organization Name</label>
                <input type="text" name="org_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password (Min 8 chars)</label>
                <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" required>
            </div>
            
            <div id="locationFields" class="form-group">
                <label class="form-label">Location</label>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <input type="number" step="any" name="lat" id="lat" class="form-control" placeholder="Latitude" required>
                    <input type="number" step="any" name="lng" id="lng" class="form-control" placeholder="Longitude" required>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="getLocation()">Use Current Location</button>
            </div>

            <div id="docField" class="form-group hidden">
                <label class="form-label">Verification Document (PDF/JPG/PNG)</label>
                <p style="font-size: 0.75rem; color: var(--clr-text-muted); margin-bottom: 5px;">Required for NGO and Recycler roles for admin approval.</p>
                <input type="file" name="verification_doc" id="verification_doc" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--space-4);">
                <span class="btn-text">Create Account</span>
            </button>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`).classList.add('active');
    
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registerForm').classList.add('hidden');
    document.getElementById(`${tab}Form`).classList.remove('hidden');
    hideAlert();
}

function toggleRoleFields() {
    const role = document.getElementById('regRole').value;
    const docField = document.getElementById('docField');
    const docInput = document.getElementById('verification_doc');
    
    if (role === 'ngo' || role === 'recycler') {
        docField.classList.remove('hidden');
        docInput.required = true;
    } else {
        docField.classList.add('hidden');
        docInput.required = false;
    }
}

function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                document.getElementById('lat').value = position.coords.latitude;
                document.getElementById('lng').value = position.coords.longitude;
            },
            (error) => {
                showAlert('Could not get location. Please enter manually.', 'danger');
            }
        );
    } else {
        showAlert('Geolocation is not supported by this browser.', 'danger');
    }
}

function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.textContent = msg;
    box.className = `alert alert-${type}`;
    box.classList.remove('hidden');
}

function hideAlert() {
    document.getElementById('alertBox').classList.add('hidden');
}

// Login
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const res = await fetch(window.BASE_URL + '/api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.success) {
            const role = result.data.user.role;
            if (role === 'admin') window.location.href = window.BASE_URL + '/admin/dashboard.php';
            else if (role === 'donor') window.location.href = window.BASE_URL + '/donor/dashboard.php';
            else if (role === 'ngo') window.location.href = window.BASE_URL + '/ngo/feed.php';
            else if (role === 'recycler') window.location.href = window.BASE_URL + '/recycling/index.php';
            else window.location.href = window.BASE_URL + '/index.php';
        } else {
            let errorMsg = result.error;
            if (result.error === 'invalid_credentials') errorMsg = 'Invalid email or password.';
            if (result.error === 'account_rejected') errorMsg = 'Your account has been rejected by the admin.';
            showAlert(errorMsg, 'danger');
            btn.disabled = false;
        }
    } catch (err) {
        showAlert('An error occurred. Please try again.', 'danger');
        btn.disabled = false;
    }
});

// Register
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    
    const formData = new FormData(e.target);
    
    try {
        const res = await fetch(window.BASE_URL + '/api/register.php', {
            method: 'POST',
            body: formData // sending as multipart/form-data because of file upload
        });
        
        const result = await res.json();
        
        if (result.success) {
            showAlert('Registration successful! Please login.', 'success');
            setTimeout(() => switchTab('login'), 2000);
        } else {
            const errorMsg = result.error === 'email_already_exists' ? 'Email already in use.' : result.error;
            showAlert(errorMsg, 'danger');
            btn.disabled = false;
        }
    } catch (err) {
        showAlert('An error occurred during registration.', 'danger');
        btn.disabled = false;
    }
});

// Initialize on load
toggleRoleFields();
</script>

<?php include 'includes/footer.php'; ?>
