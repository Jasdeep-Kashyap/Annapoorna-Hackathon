<?php
// public/admin/dashboard.php
require_once '../includes/auth-guard.php';
requireAuth(['admin']);

include '../includes/header.php';
?>

<div class="container fade-in" style="margin-top: var(--space-6);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
        <h2 style="font-size: 1.5rem; font-weight: 600;">Admin Dashboard: User Management</h2>
    </div>

    <div class="card">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--clr-border);">
                        <th style="padding: var(--space-3); font-weight: 600;">Organization</th>
                        <th style="padding: var(--space-3); font-weight: 600;">Role</th>
                        <th style="padding: var(--space-3); font-weight: 600;">Contact</th>
                        <th style="padding: var(--space-3); font-weight: 600;">Status</th>
                        <th style="padding: var(--space-3); font-weight: 600;">Document</th>
                        <th style="padding: var(--space-3); font-weight: 600; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <!-- Users will be populated here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function loadUsers() {
    try {
        const res = await fetch(window.BASE_URL + '/api/admin/users.php');
        const result = await res.json();
        
        if (result.success) {
            renderUsers(result.data.users);
        } else {
            alert('Failed to load users: ' + result.error);
        }
    } catch (e) {
        alert('Error loading users.');
    }
}

function renderUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    if (users.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="padding: var(--space-4); text-align: center; color: var(--clr-text-muted);">No users found.</td></tr>`;
        return;
    }
    
    let html = '';
    users.forEach(u => {
        let statusBadge = '';
        if (u.approval_status === 'approved') statusBadge = `<span style="color: var(--clr-success); font-weight: bold;">Approved</span>`;
        else if (u.approval_status === 'rejected') statusBadge = `<span style="color: var(--clr-danger); font-weight: bold;">Rejected</span>`;
        else statusBadge = `<span style="color: var(--clr-warning); font-weight: bold;">Pending</span>`;
        
        let docLink = u.verification_doc_path 
            ? `<a href="${u.verification_doc_path}" target="_blank" style="color: var(--clr-accent);">View Doc</a>` 
            : `<span style="color: var(--clr-text-muted);">N/A</span>`;
            
        let actionBtns = '';
        if (u.approval_status === 'pending' || u.approval_status === 'rejected') {
            actionBtns += `<button onclick="updateStatus(${u.id}, 'approved')" class="btn btn-primary btn-sm" style="margin-right: 5px;">Approve</button>`;
        }
        if (u.approval_status === 'pending' || u.approval_status === 'approved') {
            actionBtns += `<button onclick="updateStatus(${u.id}, 'rejected')" class="btn btn-outline btn-sm" style="color: var(--clr-danger); border-color: var(--clr-danger);">Reject</button>`;
        }
        
        html += `
            <tr style="border-bottom: 1px solid var(--clr-border);">
                <td style="padding: var(--space-3);">${u.org_name}</td>
                <td style="padding: var(--space-3); text-transform: capitalize;">${u.role}</td>
                <td style="padding: var(--space-3);">${u.email}<br><small>${u.phone}</small></td>
                <td style="padding: var(--space-3);">${statusBadge}</td>
                <td style="padding: var(--space-3);">${docLink}</td>
                <td style="padding: var(--space-3); text-align: right;">${actionBtns}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

async function updateStatus(userId, status) {
    if (!confirm(`Are you sure you want to mark this user as ${status}?`)) return;
    
    try {
        const res = await fetch(window.BASE_URL + '/api/admin/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, status: status })
        });
        
        const result = await res.json();
        
        if (result.success) {
            loadUsers(); // refresh
        } else {
            alert('Failed to update status: ' + result.error);
        }
    } catch (e) {
        alert('Error updating status.');
    }
}

// Load users on init
loadUsers();
</script>

<?php include '../includes/footer.php'; ?>
