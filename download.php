<?php
include('includes/auth.php');
include('includes/config.php');

if (isset($_GET['id']) && isset($_GET['type']) && isset($_GET['username'])) {
    $document_id = $_GET['id'];
    $document_type = $_GET['type'];
    $username = $_GET['username'];

    // Increment the download count
    $conn->query("UPDATE {$document_type}s SET downloads = downloads + 1 WHERE id = $document_id");

    // Record the download
    $stmt = $conn->prepare("INSERT INTO document_downloads (document_id, document_type, username) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $document_id, $document_type, $username);
    $stmt->execute();

    // Fetch the document file path
    $file_info = $conn->query("SELECT file_path, title FROM {$document_type}s WHERE id = $document_id")->fetch_assoc();
    $file_path = $file_info['file_path'];
    $file_title = $file_info['title'];

    // Check if the file exists
    if (file_exists($file_path)) {
        // Set headers to force download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file_title).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));

        // Clear output buffer
        ob_clean();
        flush();

        // Read the file and output it to the browser
        readfile($file_path);
        exit;
    } else {
        echo "File not found.";
    }
} else {
    echo "Invalid request.";
}
?>
