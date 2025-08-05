<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
include(__DIR__ . '/includes/auth.php');
include(__DIR__ . '/includes/config.php');

// Verify database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Authentication check
if (!isLoggedIn()) {
    header("Location: /index.php");
    exit();
}

// Function to generate reference number
function generateReferenceNumber() {
    $prefix = "ANN-";
    $datePart = date("Ymd"); // Current date in YYYYMMDD format
    $randomPart = strtoupper(substr(uniqid(), 0, 4)); // Random alphanumeric string
    return $prefix . $datePart . "-" . $randomPart;
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = "Error deleting announcement: " . $stmt->error;
    }
    $stmt->close();
}

// Initialize variables
$error = '';
$success = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_announcement'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $date_posted = trim($_POST['date_posted']);
        $image_path = '';
        $reference_number = generateReferenceNumber(); // Generate the reference number

        // Handle file upload if present
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['image_file']['name']);
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_path)) {
                $image_path = 'uploads/' . $file_name;
            } else {
                $error = "Failed to upload file.";
            }
        }

        // Validate inputs
        if (empty($title) || empty($content) || empty($date_posted)) {
            $error = "Title, Content, and Date Posted are required fields.";
        } else {
            // Insert new announcement with the reference number
            $stmt = $conn->prepare("INSERT INTO announcements (title, content, image_path, date_posted, reference_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $title, $content, $image_path, $date_posted, $reference_number);
            if ($stmt->execute()) {
                $success = "Announcement added successfully!";
            } else {
                $error = "Error adding announcement: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_announcement'])) {
        $id = (int)$_POST['announcement_id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $date_posted = trim($_POST['date_posted']);
        $image_path = $_POST['existing_image_path'] ?? '';
        $reference_number = generateReferenceNumber(); // Generate the reference number

        // Handle file upload if present
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if (!empty($image_path)) {
                @unlink(__DIR__ . '/' . $image_path);
            }
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['image_file']['name']);
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_path)) {
                $image_path = 'uploads/' . $file_name;
            } else {
                $error = "Failed to upload file.";
            }
        }

        // Validate inputs
        if (empty($title) || empty($content) || empty($date_posted)) {
            $error = "Title, Content, and Date Posted are required fields.";
        } else {
            // Update existing announcement with the reference number
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, image_path = ?, date_posted = ?, reference_number = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $title, $content, $image_path, $date_posted, $reference_number, $id);
            if ($stmt->execute()) {
                $success = "Announcement updated successfully!";
            } else {
                $error = "Error updating announcement: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle GET request for fetching announcement data
if (isset($_GET['action']) && $_GET['action'] === 'get_announcement' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $announcement = $result->fetch_assoc();
    $stmt->close();
    if ($announcement) {
        header('Content-Type: application/json');
        echo json_encode($announcement);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Announcement not found']);
        exit();
    }
}

// Fetch announcements from the database
$query = "SELECT * FROM announcements ORDER BY date_posted DESC";
$result = $conn->query($query);
$announcements = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements Management - eFIND System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-blue: #4361ee;
            --secondary-blue: #3a0ca3;
            --light-blue: #e8f0fe;
            --accent-orange: #ff6d00;
            --dark-gray: #2b2d42;
            --medium-gray: #8d99ae;
            --light-gray: #f8f9fa;
            --white: #ffffff;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa, #e4e8f0);
            color: var(--dark-gray);
            min-height: 100vh;
        }
        .management-container {
            padding: 20px;
            margin-top: 80px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-blue);
        }
        .page-title {
            font-family: 'Montserrat', sans-serif;
            color: var(--secondary-blue);
            font-weight: 700;
            margin: 0;
            position: relative;
        }
        .page-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -17px;
            width: 60px;
            height: 4px;
            background: var(--accent-orange);
            border-radius: 2px;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        .search-box input {
            padding-left: 40px;
            border-radius: 8px;
            border: 2px solid var(--light-blue);
            box-shadow: none;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 12px;
            color: var(--medium-gray);
        }
        .table-container {
            background-color: var(--white);
            border-radius: 16px;
            box-shadow: var(--box-shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: var(--primary-blue);
            color: var(--white);
            font-weight: 600;
            border: none;
        }
        .table td {
            vertical-align: middle;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        .table tr:hover td {
            background-color: rgba(67, 97, 238, 0.05);
        }
        .action-btn {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .action-btn i {
            margin-right: 5px;
        }
        .btn-view {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        .btn-view:hover {
            background-color: rgba(40, 167, 69, 0.2);
        }
        .btn-edit {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            border: 1px solid rgba(13, 110, 253, 0.3);
        }
        .btn-edit:hover {
            background-color: rgba(13, 110, 253, 0.2);
        }
        .btn-delete {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        .btn-delete:hover {
            background-color: rgba(220, 53, 69, 0.2);
        }
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: var(--box-shadow);
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: var(--white);
            border-top-left-radius: 16px !important;
            border-top-right-radius: 16px !important;
            border-bottom: none;
        }
        .modal-title {
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid var(--light-blue);
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
        }
        .file-upload {
            border: 2px dashed var(--light-blue);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background-color: rgba(67, 97, 238, 0.05);
        }
        .file-upload:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }
        .current-file {
            margin-top: 10px;
            font-size: 0.9rem;
            color: var(--medium-gray);
        }
        .current-file a {
            color: var(--primary-blue);
            text-decoration: none;
        }
        .current-file a:hover {
            text-decoration: underline;
        }
        .floating-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        .shape {
            position: absolute;
            opacity: 0.1;
            transition: all 10s linear;
        }
        .shape-1 {
            width: 150px;
            height: 150px;
            background: var(--primary-blue);
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            top: 10%;
            left: 10%;
            animation: float 15s infinite ease-in-out;
        }
        .shape-2 {
            width: 100px;
            height: 100px;
            background: var(--accent-orange);
            border-radius: 50%;
            bottom: 15%;
            right: 10%;
            animation: float 12s infinite ease-in-out reverse;
        }
        .shape-3 {
            width: 180px;
            height: 180px;
            background: var(--secondary-blue);
            border-radius: 50% 20% 50% 30%;
            top: 50%;
            right: 20%;
            animation: float 18s infinite ease-in-out;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: var(--box-shadow);
            border-radius: 8px;
            overflow: hidden;
        }
        .alert-success {
            background-color: rgba(40, 167, 69, 0.9);
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.9);
            border-left: 4px solid #dc3545;
        }
        @media (max-width: 768px) {
            .management-container {
                margin-top: 70px;
                padding: 15px;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .page-title {
                margin-bottom: 15px;
            }
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>
    <?php include(__DIR__ . '/includes/navbar.php'); ?>
    <div class="management-container">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Announcements Management</h1>
                <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                    <i class="fas fa-plus me-2"></i> Add New Announcement
                </button>
            </div>
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search announcements...">
            </div>
            <!-- Announcements Table -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Content</th>
                                <th>Date Posted</th>
                                <th>Reference Number</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="announcementsTableBody">
                            <?php if (empty($announcements)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No announcements found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $announcement): ?>
                                    <tr data-id="<?php echo $announcement['id']; ?>">
                                        <td><?php echo htmlspecialchars($announcement['id']); ?></td>
                                        <td class="title"><?php echo htmlspecialchars($announcement['title']); ?></td>
                                        <td class="content"><?php echo htmlspecialchars(substr($announcement['content'], 0, 50)) . (strlen($announcement['content']) > 50 ? '...' : ''); ?></td>
                                        <td class="date-posted"><?php echo date('M d, Y', strtotime($announcement['date_posted'])); ?></td>
                                        <td class="reference-number"><?php echo htmlspecialchars($announcement['reference_number']); ?></td>
                                        <td>
                                            <?php if (!empty($announcement['image_path'])): ?>
                                                <a href="#" class="btn btn-sm action-btn btn-view image-link" data-image-src="<?php echo htmlspecialchars($announcement['image_path']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm action-btn btn-edit edit-btn" data-id="<?php echo $announcement['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?action=delete&id=<?php echo $announcement['id']; ?>" class="btn btn-sm action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this announcement?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Alert Messages -->
    <?php if (!empty($success)): ?>
        <div class="alert-message alert-success alert-dismissible fade show">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-2"></i>
                <div><?php echo $success; ?></div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert-message alert-danger alert-dismissible fade show">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div><?php echo $error; ?></div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
    <!-- Modal for Add New Announcement -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-labelledby="addAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Announcement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="date_posted" class="form-label">Date Posted <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_posted" name="date_posted" required>
                        </div>
                        <div class="mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" value="<?php echo generateReferenceNumber(); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image File (JPG, PNG)</label>
                            <div class="file-upload">
                                <input type="file" class="form-control" id="image_file" name="image_file" accept=".jpg,.jpeg,.png">
                                <small class="text-muted">Max file size: 5MB</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_announcement" class="btn btn-primary-custom">Add Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal for Edit Announcement -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Announcement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAnnouncementForm" method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="announcement_id" id="editAnnouncementId">
                        <input type="hidden" name="existing_image_path" id="editExistingImagePath">
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="editContent" class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="editContent" name="content" rows="5" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editDatePosted" class="form-label">Date Posted <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="editDatePosted" name="date_posted" required>
                        </div>
                        <div class="mb-3">
                            <label for="editReferenceNumber" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="editReferenceNumber" name="reference_number" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image File (JPG, PNG)</label>
                            <div class="file-upload">
                                <input type="file" class="form-control" id="editImageFile" name="image_file" accept=".jpg,.jpeg,.png">
                                <small class="text-muted">Max file size: 5MB</small>
                            </div>
                            <div id="currentImageInfo" class="current-file"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_announcement" class="btn btn-primary-custom">Update Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-image me-2"></i> Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid rounded" alt="Image Preview" style="max-height: 70vh;">
                </div>
                <div class="modal-footer">
                    <a id="downloadImage" href="#" class="btn btn-primary-custom" download>
                        <i class="fas fa-download me-2"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-message');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });

            // Handle image link clicks
            document.querySelectorAll('.image-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const imageSrc = this.getAttribute('data-image-src');
                    const modalImage = document.getElementById('modalImage');
                    const downloadLink = document.getElementById('downloadImage');
                    modalImage.src = imageSrc;
                    downloadLink.href = imageSrc;
                    downloadLink.download = imageSrc.split('/').pop();
                    // Show the modal
                    const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
                    imageModal.show();
                });
            });

            // Search functionality
            document.getElementById('searchInput').addEventListener('keyup', function() {
                const searchQuery = this.value.toLowerCase();
                const rows = document.querySelectorAll('#announcementsTableBody tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    let matches = false;
                    cells.forEach(cell => {
                        if (cell.textContent.toLowerCase().includes(searchQuery)) {
                            matches = true;
                        }
                    });
                    row.style.display = matches ? '' : 'none';
                });
            });

            // Edit button functionality
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const row = this.closest('tr');
                    const title = row.querySelector('.title').textContent;
                    const content = row.querySelector('.content').textContent;
                    const datePosted = row.querySelector('.date-posted').textContent;
                    const referenceNumber = row.querySelector('.reference-number').textContent;
                    const imagePath = row.querySelector('a.btn-view') ?
                        row.querySelector('a.btn-view').getAttribute('data-image-src') : '';

                    // Populate the edit modal fields
                    document.getElementById('editAnnouncementId').value = id;
                    document.getElementById('editTitle').value = title;
                    document.getElementById('editContent').value = content;
                    document.getElementById('editDatePosted').value = datePosted;
                    document.getElementById('editReferenceNumber').value = referenceNumber;
                    document.getElementById('editExistingImagePath').value = imagePath;

                    // Update current file info
                    const currentFileInfo = document.getElementById('currentImageInfo');
                    if (imagePath) {
                        currentFileInfo.innerHTML = `
                            <strong>Current Image:</strong>
                            <a href="${imagePath}" target="_blank">View Image</a>
                        `;
                    } else {
                        currentFileInfo.innerHTML = '<strong>No image uploaded</strong>';
                    }

                    // Show the modal
                    const editModal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
                    editModal.show();
                });
            });

            // Floating shapes animation
            const shapes = document.querySelectorAll('.shape');
            shapes.forEach(shape => {
                const randomX = Math.random() * 20 - 10;
                const randomY = Math.random() * 20 - 10;
                const randomDelay = Math.random() * 5;
                shape.style.transform = `translate(${randomX}px, ${randomY}px)`;
                shape.style.animationDelay = `${randomDelay}s`;
            });
        });
    </script>
</body>
</html>
<?php include(__DIR__ . '/includes/footer.php'); ?>
