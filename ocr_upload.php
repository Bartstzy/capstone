<?php
// ocr_upload.php
// Example API endpoint for OCR document upload

include('includes/config.php');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date_issued = trim($_POST['date_issued'] ?? date('Y-m-d'));
    $document_type = trim($_POST['document_type'] ?? 'ordinance'); // default to 'ordinance'

    // Validate required fields
    if (empty($title) || empty($_FILES['document_file']['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and file are required.']);
        exit;
    }

    // Handle file upload
    $target_dir = "uploads/documents/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $file_name = basename($_FILES["document_file"]["name"]);
    $target_file = $target_dir . uniqid() . '_' . $file_name;

    if (!move_uploaded_file($_FILES["document_file"]["tmp_name"], $target_file)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
        exit;
    }

    // Insert into the appropriate table (example: ordinances)
    try {
        if ($document_type === 'ordinance') {
            $stmt = $conn->prepare("INSERT INTO ordinances (title, description, date_issued, file_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $title, $description, $date_issued, $target_file);
        } elseif ($document_type === 'resolution') {
            $stmt = $conn->prepare("INSERT INTO resolutions (title, description, date_issued, file_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $title, $description, $date_issued, $target_file);
        } else {
            // You can add more types as needed
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unsupported document type.']);
            exit;
        }
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Document uploaded and saved!']);
        } else {
            unlink($target_file);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        if (file_exists($target_file)) unlink($target_file);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
