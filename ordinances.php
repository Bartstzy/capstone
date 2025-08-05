<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
include(__DIR__ . '/includes/auth.php');
include(__DIR__ . '/includes/config.php');
include(__DIR__ . '/includes/functions.php');

// Verify database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Authentication check
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Handle OCR operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['perform_ocr'])) {
    if (isset($_FILES['ocr_image']) && $_FILES['ocr_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = uniqid() . '_' . basename($_FILES['ocr_image']['name']);
        $target_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['ocr_image']['tmp_name'], $target_path)) {
            // Perform OCR using Tesseract
            $output_file = $upload_dir . 'output_' . uniqid();
            $tesseract_path = '"C:\Program Files\Tesseract-OCR\tesseract.exe"';
            $command = $tesseract_path . " " . escapeshellarg($target_path) . " " . escapeshellarg($output_file) . " -l eng";

            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            $output_text = implode("\n", $output);
            $output_file_path = $output_file . '.txt';

            if ($return_var !== 0 || !file_exists($output_file_path)) {
                $error = "Tesseract execution failed. Command: " . $command . "\nReturn code: " . $return_var . "\nOutput: " . $output_text . "\n";
                $_SESSION['ocr_error'] = $error;
            } else {
                // Read the output text file
                $text = file_get_contents($output_file_path);
                if ($text === false) {
                    $_SESSION['ocr_error'] = "Failed to read the output text file.";
                } else {
                    // Clean up the extracted text
                    $text = trim($text);
                    $_SESSION['ocr_result'] = !empty($text) ? $text : "No text could be extracted from the image.";
                    // Save the extracted text to a file
                    $text_file_name = $upload_dir . 'extracted_texts_' . uniqid() . '.txt';
                    file_put_contents($text_file_name, $text);
                    $_SESSION['extracted_texts_file'] = $text_file_name;
                }
                // Delete the temporary output file
                @unlink($output_file_path);
            }
            // Delete the uploaded image
            @unlink($target_path);
            // Redirect to show results
            header("Location: " . $_SERVER['PHP_SELF'] . "?show_ocr=1");
            exit();
        } else {
            $_SESSION['ocr_error'] = "Failed to upload file.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        $_SESSION['ocr_error'] = "No file uploaded or there was an error uploading the file. Error code: " . ($_FILES['ocr_image']['error'] ?? 'unknown');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_ordinance'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $content = trim($_POST['content']);
        $ordinance_number = trim($_POST['ordinance_number']);
        $date_issued = trim($_POST['date_issued']);
        $date_approved = trim($_POST['date_approved']);
        $document_type = trim($_POST['document_type']);
        $file_path = '';
        $reference_number = generateReferenceNumber();
        $updated_by = $_SESSION['user_id']; // Assuming you have user session

        // Handle file upload if present
        if (isset($_FILES['ordinance_file']) && $_FILES['ordinance_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['ordinance_file']['name']);
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['ordinance_file']['tmp_name'], $target_path)) {
                $file_path = 'uploads/' . $file_name;
            } else {
                $error = "Failed to upload file.";
            }
        }

        // Validate inputs
        if (empty($title) || empty($ordinance_number) || empty($date_issued)) {
            $error = "Title, Ordinance Number, and Date Issued are required fields.";
        } else {
            // Insert new ordinance with the reference number
            $stmt = $conn->prepare("INSERT INTO ordinances (title, description, content, ordinance_number, date_issued, date_approved, file_path, document_type, reference_number, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $title, $description, $content, $ordinance_number, $date_issued, $date_approved, $file_path, $document_type, $reference_number, $updated_by);
            if ($stmt->execute()) {
                $success = "Ordinance added successfully!";
            } else {
                $error = "Error adding ordinance: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_ordinance'])) {
        $id = (int)$_POST['ordinance_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $content = trim($_POST['content']);
        $ordinance_number = trim($_POST['ordinance_number']);
        $date_issued = trim($_POST['date_issued']);
        $date_approved = trim($_POST['date_approved']);
        $document_type = trim($_POST['document_type']);
        $file_path = $_POST['existing_file_path'] ?? '';
        $updated_by = $_SESSION['user_id']; // Assuming you have user session

        // Handle file upload if present
        if (isset($_FILES['ordinance_file']) && $_FILES['ordinance_file']['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if (!empty($file_path)) {
                @unlink(__DIR__ . '/' . $file_path);
            }
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['ordinance_file']['name']);
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['ordinance_file']['tmp_name'], $target_path)) {
                $file_path = 'uploads/' . $file_name;
            } else {
                $error = "Failed to upload file.";
            }
        }

        // Validate inputs
        if (empty($title) || empty($ordinance_number) || empty($date_issued)) {
            $error = "Title, Ordinance Number, and Date Issued are required fields.";
        } else {
            // Update existing ordinance
            $stmt = $conn->prepare("UPDATE ordinances SET title = ?, description = ?, content = ?, ordinance_number = ?, date_issued = ?, date_approved = ?, file_path = ?, document_type = ?, updated_by = ? WHERE id = ?");
            $stmt->bind_param("sssssssssi", $title, $description, $content, $ordinance_number, $date_issued, $date_approved, $file_path, $document_type, $updated_by, $id);
            if ($stmt->execute()) {
                $success = "Ordinance updated successfully!";
            } else {
                $error = "Error updating ordinance: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle DELETE request for deleting an ordinance
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // First, retrieve the file path to delete the file from the server
    $stmt = $conn->prepare("SELECT file_path FROM ordinances WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ordinance = $result->fetch_assoc();
    $stmt->close();
    if ($ordinance && !empty($ordinance['file_path'])) {
        // Delete the file from the server
        @unlink(__DIR__ . '/' . $ordinance['file_path']);
    }
    // Delete the ordinance from the database
    $stmt = $conn->prepare("DELETE FROM ordinances WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Ordinance deleted successfully!";
        // Redirect to avoid resubmission on page refresh
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = "Error deleting ordinance: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch ordinances from the database
$query = "SELECT * FROM ordinances ORDER BY date_issued DESC";
$result = $conn->query($query);
$ordinances = $result->fetch_all(MYSQLI_ASSOC);

// Function to generate reference number
function generateReferenceNumber()
{
    $prefix = "ORD-";
    $datePart = date("Ymd"); // Current date in YYYYMMDD format
    $randomPart = strtoupper(substr(uniqid(), 0, 4)); // Random alphanumeric string
    return $prefix . $datePart . "-" . $randomPart;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordinance Management - eFIND System</title>
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

        .btn-download {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            border: 1px solid rgba(13, 110, 253, 0.3);
        }

        .btn-download:hover {
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

        .highlight {
            background-color: yellow;
            font-weight: bold;
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
                <h1 class="page-title">Ordinance Management</h1>
                <div>
                    <button type="button" class="btn btn-primary-custom me-2" data-bs-toggle="modal" data-bs-target="#addOrdinanceModal">
                        <i class="fas fa-plus me-2"></i> Add New Ordinance
                    </button>
                    <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#ocrModal">
                        <i class="fas fa-file-image me-2"></i> Perform OCR
                    </button>
                    <a href="dashboard.php" class="btn btn-primary-custom ms-2">
                        <i class="fas fa-home me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search ordinances...">
                    </div>
                </div>
                <div class="col-md-4">
                    <form method="GET" action="ordinances.php" class="d-flex">
                        <select class="form-select" name="document_type" onchange="this.form.submit()">
                            <option value="">All Document Types</option>
                            <option value="ordinance" <?= (isset($_GET['document_type']) && $_GET['document_type'] === 'ordinance') ? 'selected' : '' ?>>Ordinances</option>
                            <option value="resolution" <?= (isset($_GET['document_type']) && $_GET['document_type'] === 'resolution') ? 'selected' : '' ?>>Resolutions</option>
                            <option value="meeting" <?= (isset($_GET['document_type']) && $_GET['document_type'] === 'meeting') ? 'selected' : '' ?>>Meeting Minutes</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Ordinances Table -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Ordinance No.</th>
                                <th>Date Issued</th>
                                <th>Date Approved</th>
                                <th>Reference Number</th>
                                <th>File</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordinanceTableBody">
                            <?php if (empty($ordinances)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">No ordinances found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ordinances as $ordinance): ?>
                                    <tr data-id="<?php echo $ordinance['id']; ?>">
                                        <td><?php echo htmlspecialchars($ordinance['id']); ?></td>
                                        <td class="title"><?php echo htmlspecialchars($ordinance['title']); ?></td>
                                        <td class="description"><?php echo htmlspecialchars(substr($ordinance['description'], 0, 50)) . (strlen($ordinance['description']) > 50 ? '...' : ''); ?></td>
                                        <td class="ordinance-number"><?php echo htmlspecialchars($ordinance['ordinance_number']); ?></td>
                                        <td class="date-issued"><?php echo date('M d, Y', strtotime($ordinance['date_issued'])); ?></td>
                                        <td class="date-approved"><?php echo !empty($ordinance['date_approved']) ? date('M d, Y', strtotime($ordinance['date_approved'])) : 'N/A'; ?></td>
                                        <td class="reference-number"><?php echo htmlspecialchars($ordinance['reference_number']); ?></td>
                                        <td>
                                            <?php if (!empty($ordinance['file_path'])): ?>
                                                <?php
                                                $fileExtension = strtolower(pathinfo($ordinance['file_path'], PATHINFO_EXTENSION));
                                                $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png']);
                                                ?>
                                                <?php if ($isImage): ?>
                                                    <a href="#" class="btn btn-sm action-btn btn-view image-link" data-image-src="<?php echo htmlspecialchars($ordinance['file_path']); ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo htmlspecialchars($ordinance['file_path']); ?>" target="_blank" class="btn btn-sm action-btn btn-view">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($ordinance['file_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($ordinance['file_path']); ?>" download class="btn btn-sm action-btn btn-download">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            <?php endif; ?>
                                            <a href="?action=delete&id=<?php echo $ordinance['id']; ?>" class="btn btn-sm action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this ordinance?');">
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

    <!-- Modal for Add New Ordinance -->
    <div class="modal fade" id="addOrdinanceModal" tabindex="-1" aria-labelledby="addOrdinanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Ordinance</h5>
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
                                <label for="ordinance_number" class="form-label">Ordinance Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ordinance_number" name="ordinance_number" required>
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
                            <label class="form-label">Ordinance File (PDF, JPG, PNG)</label>
                            <div class="file-upload">
                                <input type="file" class="form-control" id="ordinance_file" name="ordinance_file" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Max file size: 5MB</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_ordinance" class="btn btn-primary-custom">Add Ordinance</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for OCR -->
    <div class="modal fade" id="ocrModal" tabindex="-1" aria-labelledby="ocrModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ocrModalLabel">Perform OCR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="ordinances.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="ocrImage" class="form-label">Upload Image for OCR</label>
                            <input class="form-control" type="file" id="ocrImage" name="ocr_image" accept="image/*" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="perform_ocr" class="btn btn-primary-custom">Extract Text</button>
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

    <!-- Modal for OCR Results -->
    <div class="modal fade" id="ocrResultModal" tabindex="-1" aria-labelledby="ocrResultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ocrResultModalLabel">OCR Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($_SESSION['ocr_result'])): ?>
                        <form action="manage_extracted_files.php" method="POST">
                            <div class="mb-3">
                                <label for="extracted_texts" class="form-label">Extracted Text:</label>
                                <textarea class="form-control" id="extracted_texts" name="extracted_texts" rows="15" readonly><?php echo htmlspecialchars($_SESSION['ocr_result']); ?></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" name="save_extracted_texts" class="btn btn-primary-custom">Save Text</button>
                            </div>
                        </form>
                    <?php elseif (isset($_SESSION['ocr_error'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['ocr_error']); ?></div>
                    <?php endif; ?>
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
                const rows = document.querySelectorAll('#ordinanceTableBody tr');
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

            // Show OCR modal if there's a result or error
            <?php if (isset($_GET['show_ocr'])): ?>
                var ocrResultModal = new bootstrap.Modal(document.getElementById('ocrResultModal'));
                ocrResultModal.show();
            <?php endif; ?>
        });
    </script>
</body>

</html>
<?php include(__DIR__ . '/includes/footer.php'); ?>