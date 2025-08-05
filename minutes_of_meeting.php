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
    header("Location: ../admin/minutes_of_meeting.php");
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
            header("Location: resolutions.php");
            exit();
        case 'meeting':
            // Already on minutes_of_meeting.php
            break;
        default:
            // Stay on current page
            break;
    }
}

// Function to generate reference number
function generateReferenceNumber()
{
    $prefix = "MOM-";
    $datePart = date("Ymd"); // Current date in YYYYMMDD format
    $randomPart = strtoupper(substr(uniqid(), 0, 4)); // Random alphanumeric string
    return $prefix . $datePart . "-" . $randomPart;
}

// Initialize variables
$error = '';
$success = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_meeting'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $meeting_date = trim($_POST['meeting_date']);
        $document_type = trim($_POST['document_type']);
        $image_path = '';
        $reference_number = generateReferenceNumber();
        $updated_by = $_SESSION['user_id'];

        // Handle file upload
        if (isset($_FILES['meeting_file']) && $_FILES['meeting_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['meeting_file']['name']);
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['meeting_file']['tmp_name'], $target_path)) {
                $image_path = 'uploads/' . $file_name;
            } else {
                $error = "Failed to upload file.";
            }
        }

        // Validate inputs
        if (empty($title) || empty($meeting_date)) {
            $error = "Title and Meeting Date are required fields.";
        } else {
            // Insert new meeting minutes
            $stmt = $conn->prepare("INSERT INTO meeting_minutes (title, content, meeting_date, image_path, document_type, reference_number, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $title, $content, $meeting_date, $image_path, $document_type, $reference_number, $updated_by);
            if ($stmt->execute()) {
                $success = "Meeting minutes added successfully!";
            } else {
                $error = "Error adding meeting minutes: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_meeting'])) {
        $id = (int)$_POST['meeting_id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $meeting_date = trim($_POST['meeting_date']);
        $document_type = trim($_POST['document_type']);
        $image_path = $_POST['existing_image_path'] ?? '';
        $updated_by = $_SESSION['user_id'];

        // Handle file upload
        if (isset($_FILES['meeting_file']) && $_FILES['meeting_file']['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if (!empty($image_path)) {
                @unlink(__DIR__ . '/' . $image_path);
            }
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['meeting_file']['name']);
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['meeting_file']['tmp_name'], $target_path)) {
                $image_path = 'uploads/' . $file_name;
            } else {
                $error = "Failed to upload file.";
            }
        }

        // Validate inputs
        if (empty($title) || empty($meeting_date)) {
            $error = "Title and Meeting Date are required fields.";
        } else {
            // Update meeting minutes
            $stmt = $conn->prepare("UPDATE meeting_minutes SET title = ?, content = ?, meeting_date = ?, image_path = ?, document_type = ?, updated_by = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $title, $content, $meeting_date, $image_path, $document_type, $updated_by, $id);
            if ($stmt->execute()) {
                $success = "Meeting minutes updated successfully!";
            } else {
                $error = "Error updating meeting minutes: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // First, retrieve the image path to delete the file from the server
    $stmt = $conn->prepare("SELECT image_path FROM meeting_minutes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $meeting = $result->fetch_assoc();
    $stmt->close();
    if ($meeting && !empty($meeting['image_path'])) {
        @unlink(__DIR__ . '/' . $meeting['image_path']);
    }
    // Delete from database
    $stmt = $conn->prepare("DELETE FROM meeting_minutes WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Meeting minutes deleted successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = "Error deleting meeting minutes: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch meeting minutes from database
$query = "SELECT * FROM meeting_minutes ORDER BY meeting_date DESC";
$result = $conn->query($query);
$meetings = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Minutes Management - eFIND System</title>
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
                <h1 class="page-title">Meeting Minutes Management</h1>
                <div>
                    <button type="button" class="btn btn-primary-custom me-2" data-bs-toggle="modal" data-bs-target="#addMeetingModal">
                        <i class="fas fa-plus me-2"></i> Add New Meeting Minutes
                    </button>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search meeting minutes...">
                    </div>
                </div>
                <div class="col-md-4">
                    <form method="GET" action="minutes_of_meeting.php" class="d-flex">
                        <select class="form-select" name="document_type" onchange="this.form.submit()">
                            <option value="">All Document Types</option>
                            <option value="ordinance" <?= (isset($_GET['document_type']) && $_GET['document_type'] === 'ordinance') ? 'selected' : '' ?>>Ordinances</option>
                            <option value="resolution" <?= (isset($_GET['document_type']) && $_GET['document_type'] === 'resolution') ? 'selected' : '' ?>>Resolutions</option>
                            <option value="meeting" <?= (isset($_GET['document_type']) && $_GET['document_type'] === 'meeting') ? 'selected' : '' ?>>Meeting Minutes</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Meeting Minutes Table -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Content</th>
                                <th>Meeting Date</th>
                                <th>Reference Number</th>
                                <th>File</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="meetingTableBody">
                            <?php if (empty($meetings)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No meeting minutes found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($meetings as $meeting): ?>
                                    <tr data-id="<?php echo $meeting['id']; ?>">
                                        <td><?php echo htmlspecialchars($meeting['id']); ?></td>
                                        <td class="title"><?php echo htmlspecialchars($meeting['title']); ?></td>
                                        <td class="content"><?php echo htmlspecialchars(substr($meeting['content'], 0, 50)) . (strlen($meeting['content']) > 50 ? '...' : ''); ?></td>
                                        <td class="meeting-date"><?php echo date('M d, Y', strtotime($meeting['meeting_date'])); ?></td>
                                        <td class="reference-number"><?php echo htmlspecialchars($meeting['reference_number']); ?></td>
                                        <td>
                                            <?php if (!empty($meeting['image_path'])): ?>
                                                <a href="#" class="btn btn-sm action-btn btn-view image-link" data-image-src="<?php echo htmlspecialchars($meeting['image_path']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($meeting['image_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($meeting['image_path']); ?>" download class="btn btn-sm action-btn btn-download">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            <?php endif; ?>
                                            <a href="?action=delete&id=<?php echo $meeting['id']; ?>" class="btn btn-sm action-btn btn-delete" onclick="return confirm('Are you sure you want to delete these meeting minutes?');">
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

    <!-- Modal for Add New Meeting Minutes -->
    <div class="modal fade" id="addMeetingModal" tabindex="-1" aria-labelledby="addMeetingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Meeting Minutes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="5"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="meeting_date" class="form-label">Meeting Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="meeting_date" name="meeting_date" required>
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
                            <label class="form-label">Meeting Minutes File (PDF, JPG, PNG)</label>
                            <div class="file-upload">
                                <input type="file" class="form-control" id="meeting_file" name="meeting_file" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Max file size: 5MB</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_meeting" class="btn btn-primary-custom">Add Meeting Minutes</button>
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
                const rows = document.querySelectorAll('#meetingTableBody tr');
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