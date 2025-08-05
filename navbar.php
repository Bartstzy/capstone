<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <!-- Logo and Address -->
        <div class="d-flex align-items-center">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo_pbsth.png" alt="Logo" height="60" class="d-inline-block align-top me-2">
            </a>
            <div class="text-white ms-2">
                <span class="d-block">Poblacion South</span>
                <small>Solano, Nueva Vizcaya</small>
            </div>
        </div>
        <!-- Navbar Toggler Button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <!-- Navbar Links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto" id="nav">
                <!-- In your navbar.php file -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php
                                                        $current_page = basename($_SERVER['PHP_SELF']);
                                                        $documents_pages = ['ordinances.php', 'resolutions.php', 'minutes_of_meeting.php'];
                                                        echo (in_array($current_page, $documents_pages)) ? 'active' : '';
                                                        ?>" href="#" id="documentsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Documents
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="documentsDropdown">
                        <li>
                            <a class="dropdown-item <?php echo $current_page == 'ordinances.php' ? 'active' : ''; ?>" href="ordinances.php">Ordinances</a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page == 'resolutions.php' ? 'active' : ''; ?>" href="resolutions.php">Resolutions</a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page == 'minutes_of_meeting.php' ? 'active' : ''; ?>" href="minutes_of_meeting.php">Minutes of the Meeting</a>
                        </li>
                    </ul>
                </li>
            </ul>
            <!-- Profile Dropdown -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php
                        // Check if session variables exist before using them
                        $profile_picture = $_SESSION['profile_picture'] ?? '';
                        $username = $_SESSION['username'] ?? 'User'; // Use 'User' as a fallback if username is not set
                        if (!empty($profile_picture)) {
                            $profile_path = "uploads/profiles/" . htmlspecialchars($profile_picture);
                            if (file_exists($profile_path)) {
                                echo '<img src="' . $profile_path . '" alt="Profile Picture" class="rounded-circle me-2" width="30" height="30">';
                            } else {
                                echo '<i class="fas fa-user-circle me-2"></i>';
                            }
                        } else {
                            echo '<i class="fas fa-user-circle me-2"></i>';
                        }
                        ?>
                        <span><?php echo htmlspecialchars($username); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">Profile</a></li>
                        <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                            <li><a class="dropdown-item" href="add_staff.php">Add Staff</a></li>
                            <li><a class="dropdown-item" href="activity_logs.php">Activity Logs</a></li>
                        <?php endif; ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Profile View Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">Admin Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="profileModalBody">
                <!-- Content will be loaded via AJAX -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading profile...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal" data-bs-dismiss="modal">Edit Profile</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="editProfileModalBody">
                <!-- Content will be loaded via AJAX -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading edit form...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveProfileChanges">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Load profile content when profile modal is shown
        $('#profileModal').on('show.bs.modal', function() {
            $.ajax({
                url: 'admin_profile_content.php',
                type: 'GET',
                success: function(response) {
                    $('#profileModalBody').html(response);
                },
                error: function() {
                    $('#profileModalBody').html('<div class="alert alert-danger">Error loading profile. Please try again.</div>');
                }
            });
        });

        // Load edit form when edit profile modal is shown
        $('#editProfileModal').on('show.bs.modal', function() {
            $.ajax({
                url: 'edit_profile_content.php',
                type: 'GET',
                success: function(response) {
                    $('#editProfileModalBody').html(response);
                },
                error: function() {
                    $('#editProfileModalBody').html('<div class="alert alert-danger">Error loading edit form. Please try again.</div>');
                }
            });
        });

        // Handle save button click
        $('#saveProfileChanges').on('click', function() {
            // Get form data
            var formData = new FormData(document.getElementById('editProfileForm'));

            // Submit via AJAX
            $.ajax({
                url: 'update_profile.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Close edit modal
                        $('#editProfileModal').modal('hide');
                        // Refresh profile view
                        $('#profileModalBody').load('admin_profile_content.php');
                        // Show success message
                        alert(response.message);
                        // Reload the page to update profile picture if changed
                        location.reload();
                    } else {
                        // Show error message
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error saving changes: ' + error);
                }
            });
        });

        // Handle edit profile button click
        $('#profileModal').on('click', '.btn-primary', function() {
            $('#profileModal').modal('hide');
            $('#editProfileModal').modal('show');
        });
    });
</script>