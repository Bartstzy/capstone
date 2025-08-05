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
    header("Location: ../admin/resolutions.php");
    exit();
}

// Handle document type redirects
if (isset($_GET['document_type'])) {
    $type = $_GET['document_type'];
    switch ($type) {
        case 'ordinance':
            header("Location: ordinances.php");
            exit();
        case 'resolution':
            // Already on resolutions.php
            break;
        case 'meeting':
            header("Location: minutes_of_meeting.php");
            exit();
        default:
            // Stay on current page
            break;
    }
}

// Function to generate reference number
function generateReferenceNumber()
{
    $prefix = "RES-";
    $datePart = date("Ymd"); // Current date in YYYYMMDD format
    $randomPart = strtoupper(substr(uniqid(), 0, 4)); // Random alphanumeric string
    return $prefix . $datePart . "-" . $randomPart;
}

// Initialize variables
$error = '';
$success = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_resolution'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $content = trim($_POST['content']);
        $resolution_number = trim($_POST['resolution_number']);
        $date_issued = trim($_POST['date_issued']);
        $date_approved = trim($_POST['date_approved']);
        $document_type = trim($_POST['document_type']);
        $file_path = '';
        $reference_number = generateReferenceNumber();
        $updated_by = $_SESSION['user_id'];

        // Handle file upload
        if (isset($_FILES['resolution_file']) && $_FILES['resolution_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['resolution_file']['name']);
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['resolution_file']['tmp_name'], $target_path)) {
                $file_path = 'uploads/' . $file_name;
            } else {
                $error = "Failed to upload file.";
            }
        }

        // Validate inputs
        if (empty($title) || empty($resolution_number) || empty($date_issued)) {
            $error = "Title, Resolution Number, and Date Issued are required fields.";
        } else {
            // Insert new resolution
            $stmt = $conn->prepare("INSERT INTO resolutions (title, description, content, resolution_number, date_issued, date_approved, file_path, document_type, reference_number, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $title, $description, $content, $resolution_number, $date_issued, $date_approved, $file_path, $document_type, $reference_number, $updated_by);
            if ($stmt->execute()) {
                $success = "Resolution added successfully!";
            } else {
                $error = "Error adding resolution: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_resolution'])) {
        $id = (int)$_POST['resolution_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $content = trim($_POST['content']);
        $resolution_number = trim($_POST['resolution_number']);
        $date_issued = trim($_POST['date_issued']);
        $date_approved = trim($_POST['date_approved']);
        $document_type = trim($_POST['document_type']);
        $file_path = $_POST['existing_file_path'] ?? '';
        $updated_by = $_SESSION['user_id'];

        // Handle file upload
        if (isset($_FILES['resolution_file']) && $_FILES['resolution_file']['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if (!empty($file_path)) {
                @unlink(__DIR__ . '/' . $file_path);
            }
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['resolution_file']['name']);
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['resolution_file']['tmp_name'], $target_path)) {
                $file_path = 'uploads/' . $file_name;
            } else {
                $error = "Failed to upload file.";
            }
        }

        // Validate inputs
        if (empty($title) || empty($resolution_number) || empty($date_issued)) {
            $error = "Title, Resolution Number, and Date Issued are required fields.";
        } else {
            // Update resolution
            $stmt = $conn->prepare("UPDATE resolutions SET title = ?, description = ?, content = ?, resolution_number = ?, date_issued = ?, date_approved = ?, file_path = ?, document_type = ?, updated_by = ? WHERE id = ?");
            $stmt->bind_param("sssssssssi", $title, $description, $content, $resolution_number, $date_issued, $date_approved, $file_path, $document_type, $updated_by, $id);
            if ($stmt->execute()) {
                $success = "Resolution updated successfully!";
            } else {
                $error = "Error updating resolution: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // First, retrieve the file path to delete the file from the server
    $stmt = $conn->prepare("SELECT file_path FROM resolutions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $resolution = $result->fetch_assoc();
    $stmt->close();
    if ($resolution && !empty($resolution['file_path'])) {
        @unlink(__DIR__ . '/' . $resolution['file_path']);
    }
    // Delete from database
    $stmt = $conn->prepare("DELETE FROM resolutions WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Resolution deleted successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = "Error deleting resolution: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch resolutions from database
$query = "SELECT * FROM resolutions ORDER BY date_issued DESC";
$result = $conn->query($query);
$resolutions = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resolution Management - eFIND System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        /* Same styles as ordinances.php */
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

        /* Copy all the CSS styles from ordinances.php */
        /* ... */
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
                <h1 class="page-title">Resolution Management</h1>
                <div>
                    <button type="button" class="btn btn-primary-custom me-2" data-bs-toggle="modal" data-bs-target="#addResolutionModal">
                        <i class="fas fa-plus me-2"></i> Add New Resolution
                    </button>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search resolutions...">
                    </div>
                </div>
                <div class="col-md-4">
                    <form method="GET" action="resolutions.php" class="d-flex">
                        <select class="form-select" name="document_type" onchange="this.form.submit()">
                            <option value="">All Document Types</option>
                            <option value="ordinance" <?= (isset($_GET['document_type']) && $_GET['document_type'] === 'ordinance') ? 'selected' : '' ?>>Ordinances</option>
                            <option value="resolution" <?= (isset($_GET['document_type']) && $_GET['document_type'] === 'resolution') ? 'selected' : '' ?>>Resolutions</option>
                            <option value="meeting" <?= (isset($_GET['document_type']) && $_GET['document_type'] === 'meeting') ? 'selected' : '' ?>>Meeting Minutes</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Resolutions Table -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Resolution No.</th>
                                <th>Date Issued</th>
                                <th>Date Approved</th>
                                <th>Reference Number</th>
                                <th>File</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="resolutionTableBody">
                            <?php if (empty($resolutions)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">No resolutions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($resolutions as $resolution): ?>
                                    <tr data-id="<?php echo $resolution['id']; ?>">
                                        <td><?php echo htmlspecialchars($resolution['id']); ?></td>
                                        <td class="title"><?php echo htmlspecialchars($resolution['title']); ?></td>
                                        <td class="description"><?php echo htmlspecialchars(substr($resolution['description'], 0, 50)) . (strlen($resolution['description']) > 50 ? '...' : ''); ?></td>
                                        <td class="resolution-number"><?php echo htmlspecialchars($resolution['resolution_number']); ?></td>
                                        <td class="date-issued"><?php echo date('M d, Y', strtotime($resolution['date_issued'])); ?></td>
                                        <td class="date-approved"><?php echo !empty($resolution['date_approved']) ? date('M d, Y', strtotime($resolution['date_approved'])) : 'N/A'; ?></td>
                                        <td class="reference-number"><?php echo htmlspecialchars($resolution['reference_number']); ?></td>
                                        <td>
                                            <?php if (!empty($resolution['file_path'])): ?>
                                                <?php
                                                $fileExtension = strtolower(pathinfo($resolution['file_path'], PATHINFO_EXTENSION));
                                                $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png']);
                                                ?>
                                                <?php if ($isImage): ?>
                                                    <a href="#" class="btn btn-sm action-btn btn-view image-link" data-image-src="<?php echo htmlspecialchars($resolution['file_path']); ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo htmlspecialchars($resolution['file_path']); ?>" target="_blank" class="btn btn-sm action-btn btn-view">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($resolution['file_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($resolution['file_path']); ?>" download class="btn btn-sm action-btn btn-download">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            <?php endif; ?>
                                            <a href="?action=delete&id=<?php echo $resolution['id']; ?>" class="btn btn-sm action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this resolution?');">
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

    <!-- Modal for Add New Resolution -->
    <div class="modal fade" id="addResolutionModal" tabindex="-1" aria-labelledby="addResolutionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Resolution</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="5"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="resolution_number" class="form-label">Resolution Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="resolution_number" name="resolution_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="date_issued" class="form-label">Date Issued <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_issued" name="date_issued" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="date_approved" class="form-label">Date Approved</label>
                            <input type="date" class="form-control" id="date_approved" name="date_approved">
                        </div>
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Document Type</label>
                            <input type="text" class="form-control" id="document_type" name="document_type">
                        </div>
                        <div class="mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" value="<?php echo generateReferenceNumber(); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Resolution File (PDF, JPG, PNG)</label>
                            <div class="file-upload">
                                <input type="file" class="form-control" id="resolution_file" name="resolution_file" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Max file size: 5MB</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_resolution" class="btn btn-primary-custom">Add Resolution</button>
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
                const rows = document.querySelectorAll('#resolutionTableBody tr');
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

            // Floating shapes animation
            const shapes = document.querySelectorAll('.shape');
            shapes.forEach(shape => {
                const randomX = Math.random() * 20 - 10;
                const randomY = Math.random() * 20 - 10;
                const randomDelay = Math.random() * 5;
                shape.style.transform = `translate(${randomX}px, ${randomY}px)`;
                shape.style.animationDelay = `${randomDelay}s`;
            });

            // Handle document type selection
            const docTypeSelect = document.querySelector('select[name="document_type"]');
            if (docTypeSelect) {
                docTypeSelect.addEventListener('change', function() {
                    const selectedType = this.value;
                    if (selectedType) {
                        window.location.href = selectedType + '.php';
                    }
                });
            }
        });
    </script>
</body>

</html>
<?php include(__DIR__ . '/includes/footer.php'); ?>