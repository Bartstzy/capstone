<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if the user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }
}

// Function to generate a reference number
if (!function_exists('generateReferenceNumber')) {
    function generateReferenceNumber($prefix = 'REF')
    {
        return $prefix . '-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

// Function to log activity
if (!function_exists('logActivity')) {
    function logActivity($conn, $username, $action, $details, $documentType = null, $documentName = null, $documentId = null)
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $stmt = $conn->prepare("INSERT INTO activity_logs (username, action, details, document_type, document_name, document_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $username, $action, $details, $documentType, $documentName, $documentId, $ipAddress);
        $stmt->execute();
        $stmt->close();
    }
}

// Function to increment view count
if (!function_exists('incrementViewCount')) {
    function incrementViewCount($conn, $id, $type)
    {
        $table = $type === 'ordinance' ? 'ordinances' : ($type === 'resolution' ? 'resolutions' : 'meeting_minutes');
        $stmt = $conn->prepare("UPDATE $table SET views = views + 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Function to get full text
if (!function_exists('getFullText')) {
    function getFullText($conn, $id, $type)
    {
        $table = $type === 'ordinance' ? 'ordinances' : ($type === 'resolution' ? 'resolutions' : 'meeting_minutes');
        $column = $type === 'meeting' ? 'content' : 'extracted_texts';
        $stmt = $conn->prepare("SELECT $column FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row[$column] : '';
    }
}

// Function to sanitize input data
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data)
    {
        return htmlspecialchars(trim($data));
    }
}

// Function to handle file uploads
if (!function_exists('handleFileUpload')) {
    function handleFileUpload($file, $uploadDir)
    {
        // Check if file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error'];
        }

        // Validate file size (10MB limit)
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxFileSize) {
            return ['success' => false, 'message' => 'File too large. Maximum size is 10MB'];
        }

        // Get file extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Define allowed file types
        $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }

        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'Cannot create upload directory'];
            }
        }

        // Generate unique filename
        $fileName = uniqid() . '_' . basename($file['name']);
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => true, 'path' => $targetPath, 'filename' => $fileName];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }
}

// Function to log download activity
if (!function_exists('logDownload')) {
    function logDownload($conn, $id, $type, $title)
    {
        $username = $_SESSION['username'] ?? 'Guest';
        logActivity($conn, $username, 'download', "Downloaded $type: $title", $type, $title, $id);
    }
}

// Function to get user information
if (!function_exists('getUserInfo')) {
    function getUserInfo($conn, $userId)
    {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
}

// Function to validate file type by content
if (!function_exists('validateFileType')) {
    function validateFileType($filePath, $allowedTypes)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return in_array($mimeType, $allowedTypes);
    }
}

// Function to delete file safely
if (!function_exists('deleteFile')) {
    function deleteFile($filePath)
    {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return true; // File doesn't exist, consider it deleted
    }
}
