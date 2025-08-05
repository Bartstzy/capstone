<?php
// Start session and check authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
    die('<div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>Unauthorized access. Please login first.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
         </div>');
}

// Include database connection
include 'includes/config.php';

// Fetch admin data with error handling
try {
    $admin_id = $_SESSION['admin_id'];
    $query = "SELECT id, full_name, username, email, contact_number, profile_picture, last_login, created_at, updated_at
              FROM admin_users
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if (!$admin) {
        throw new Exception("Admin profile not found.");
    }
} catch (Exception $e) {
    die('<div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>' . htmlspecialchars($e->getMessage()) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
         </div>');
}
?>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="profile-picture-container mb-3">
                <?php if (!empty($admin['profile_picture']) && file_exists("uploads/profiles/" . $admin['profile_picture'])): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($admin['profile_picture']); ?>"
                        class="img-thumbnail rounded-circle profile-picture"
                        alt="Profile Picture"
                        onerror="this.onerror=null; this.src='assets/img/default-profile.png';">
                <?php else: ?>
                    <div class="profile-picture-placeholder rounded-circle d-flex align-items-center justify-content-center">
                        <i class="fas fa-user fa-4x text-secondary"></i>
                    </div>
                <?php endif; ?>
            </div>
                <h4 class="mb-1"><?php echo htmlspecialchars($admin['full_name']); ?></h4>
                <p class="text-muted mb-3">Administrator</p>

                <!-- <div class="d-flex justify-content-center mb-3">
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="fas fa-edit me-1"></i> Edit Profile
                    </button>
                </div> -->

                <div class="card bg-light p-3">
                    <div class="d-flex justify-content-between small">
                        <span>Member since:</span>
                        <span class="text-primary"><?php echo date('M Y', strtotime($admin['created_at'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>Last active:</span>
                        <span class="text-primary">
                            <?php echo !empty($admin['last_login']) ? date('M d, Y h:i A', strtotime($admin['last_login'])) : 'Never logged in'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Personal Information</h5>
                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="small text-muted mb-1">Full Name</h6>
                        <p><?php echo htmlspecialchars($admin['full_name']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="small text-muted mb-1">Username</h6>
                        <p><?php echo htmlspecialchars($admin['username']); ?></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="small text-muted mb-1">Email Address</h6>
                        <p><?php echo htmlspecialchars($admin['email']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="small text-muted mb-1">Contact Number</h6>
                        <p><?php echo !empty($admin['contact_number']) ? htmlspecialchars($admin['contact_number']) : 'Not set'; ?></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="small text-muted mb-1">Account Created</h6>
                        <p><?php echo date('F j, Y, g:i a', strtotime($admin['created_at'])); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="small text-muted mb-1">Last Updated</h6>
                        <p><?php echo date('F j, Y, g:i a', strtotime($admin['updated_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Account Security</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center mb-3">
                    <div class="col-md-8">
                        <h6 class="mb-1">Password</h6>
                        <p class="small text-muted mb-0">Last changed:
                            <?php echo !empty($admin['password_changed_at']) ?
                                 date('M j, Y', strtotime($admin['password_changed_at'])) : 'Unknown'; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-key me-1"></i> Change Password
                        </button>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i> For security reasons, we recommend changing your password every 90 days.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="changePasswordModalLabel"><i class="fas fa-key me-2"></i>Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="passwordChangeForm" action="update_password.php" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 characters with at least one number and one special character</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .profile-picture {
        width: 180px;
        height: 180px;
        object-fit: cover;
        border: 3px solid #4361ee;
    }

    .profile-picture-placeholder {
        width: 180px;
        height: 180px;
        background-color: #f8f9fa;
        border: 3px solid #dee2e6;
    }

    .card-header h5 {
        font-weight: 600;
    }

    .toggle-password {
        border-left: none;
    }

    .toggle-password:hover {
        background-color: #e9ecef;
    }
</style>

<script>
// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const input = this.parentNode.querySelector('input');
        const icon = this.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
});

// Password change form validation
document.getElementById('passwordChangeForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    // Basic validation
    if (newPassword.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long.');
        return false;
    }

    if (!/[0-9]/.test(newPassword) || !/[^A-Za-z0-9]/.test(newPassword)) {
        e.preventDefault();
        alert('Password must contain at least one number and one special character.');
        return false;
    }

    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match.');
        return false;
    }

    return true;
});
</script>
