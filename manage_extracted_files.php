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

// Directory where extracted text files will be stored
$upload_dir = __DIR__ . '/uploads/extracted_textss/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle saving extracted text
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_extracted_texts'])) {
    $text = $_POST['extracted_texts'] ?? '';
    if (!empty($text)) {
        $file_name = 'extracted_texts_' . uniqid() . '.txt';
        $file_path = $upload_dir . $file_name;
        file_put_contents($file_path, $text);

        // Save to database
        $stmt = $conn->prepare("INSERT INTO extracted_textss (file_name, file_path) VALUES (?, ?)");
        $stmt->bind_param("ss", $file_name, $file_path);
        $stmt->execute();
        $stmt->close();

        $_SESSION['extracted_texts_file'] = $file_path;
        header("Location: " . $_SERVER['PHP_SELF'] . "?show_ocr=1");
        exit();
    } else {
        $error = "No text to save.";
    }
}


// Handle updating extracted text
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_text'])) {
    $originalFileName = $_POST['original_file_name'] ?? '';
    $newFileName = $_POST['new_file_name'] ?? '';
    $textContent = $_POST['text_content'] ?? '';
    $originalFilePath = $upload_dir . $originalFileName;
    $newFilePath = $upload_dir . $newFileName;

    if (!empty($originalFileName) && file_exists($originalFilePath)) {
        file_put_contents($originalFilePath, $textContent);
        if (!empty($newFileName) && $newFileName !== $originalFileName) {
            rename($originalFilePath, $newFilePath);
        }
        $success = "Text updated successfully!";
    } else {
        $error = "File not found or no content to update.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle file deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['file'])) {
    $file_path = $upload_dir . basename($_GET['file']);
    if (file_exists($file_path)) {
        unlink($file_path);
        $success = "File deleted successfully!";
    } else {
        $error = "File not found.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Check for session variables on page load
if (isset($_GET['show_ocr'])) {
    if (isset($_SESSION['ocr_result'])) {
        $ocrResult = $_SESSION['ocr_result'];
        unset($_SESSION['ocr_result']);
    }
    if (isset($_SESSION['ocr_error'])) {
        $error = $_SESSION['ocr_error'];
        unset($_SESSION['ocr_error']);
    }
    if (isset($_SESSION['extracted_texts_file'])) {
        unset($_SESSION['extracted_texts_file']);
    }
}

// Get list of extracted text files
$extracted_texts_files = glob($upload_dir . '*.txt');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Extracted Files - eFIND System</title>
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
            margin-right: 5px;
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

        .btn-download {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            border: 1px solid rgba(13, 110, 253, 0.3);
        }

        .btn-download:hover {
            background-color: rgba(13, 110, 253, 0.2);
        }

        .btn-edit {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .btn-edit:hover {
            background-color: rgba(255, 193, 7, 0.2);
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

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 2px solid var(--light-blue);
            padding: 10px 15px;
        }

        .form-control:focus,
        .form-select:focus {
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

            0%,
            100% {
                transform: translateY(0) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(5deg);
            }
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
                <h1 class="page-title">Manage Extracted Texts</h1>
            </div>
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search extracted texts...">
            </div>

            <!-- Extracted Texts Table -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($extracted_texts_files)): ?>
                                <tr>
                                    <td colspan="2" class="text-center py-4">No extracted texts found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($extracted_texts_files as $file): ?>
                                    <tr>
                                        <td><?php echo basename($file); ?></td>
                                        <td>
                                            <a href="#" class="btn btn-sm action-btn btn-view view-link" data-file="<?php echo basename($file); ?>">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="uploads/extracted_textss/<?php echo basename($file); ?>" download class="btn btn-sm action-btn btn-download">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button class="btn btn-sm action-btn btn-edit edit-btn" data-file="<?php echo basename($file); ?>" data-bs-toggle="modal" data-bs-target="#editTextModal">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?action=delete&file=<?php echo basename($file); ?>" class="btn btn-sm action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this file?');">
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
    <!-- Modal for View Text -->
    <div class="modal fade" id="viewTextModal" tabindex="-1" aria-labelledby="viewTextModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTextModalLabel">View Text</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre id="viewTextContent"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Edit Text -->
    <div class="modal fade" id="editTextModal" tabindex="-1" aria-labelledby="editTextModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTextModalLabel">Edit Text</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editTextForm" method="POST" action="manage_extracted_files.php">
                        <input type="hidden" name="original_file_name" id="originalFileName">
                        <div class="mb-3">
                            <label for="editFileNameInput" class="form-label">File Name</label>
                            <input type="text" class="form-control" id="editFileNameInput" name="new_file_name">
                        </div>
                        <div class="mb-3">
                            <label for="editTextContent" class="form-label">Text Content</label>
                            <textarea class="form-control" id="editTextContent" name="text_content" rows="15"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="update_text" class="btn btn-primary-custom">Save Changes</button>
                        </div>
                    </form>
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

            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                const searchQuery = this.value.toLowerCase();
                const rows = document.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const fileName = row.querySelector('td:first-child').textContent.toLowerCase();
                    if (fileName.includes(searchQuery)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // View button functionality
            document.querySelectorAll('.view-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const fileName = this.getAttribute('data-file');
                    console.log('View button clicked for file:', fileName); // Debugging log

                    fetch('uploads/extracted_textss/' + fileName)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text();
                        })
                        .then(text => {
                            document.getElementById('viewTextContent').textContent = text;
                            const viewModal = new bootstrap.Modal(document.getElementById('viewTextModal'));
                            viewModal.show();
                        })
                        .catch(error => console.error('Error fetching the file:', error));
                });
            });

            // Edit button functionality
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const fileName = this.getAttribute('data-file');
                    console.log('Edit button clicked for file:', fileName); // Debugging log

                    fetch('uploads/extracted_textss/' + fileName)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text();
                        })
                        .then(text => {
                            document.getElementById('originalFileName').value = fileName;
                            document.getElementById('editFileNameInput').value = fileName;
                            document.getElementById('editTextContent').value = text;
                        })
                        .catch(error => console.error('Error fetching the file:', error));
                });
            });
        });
    </script>
</body>

</html>
<?php include(__DIR__ . '/includes/footer.php'); ?>