<?php
require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/includes/auth.php');
require_once(__DIR__ . '/includes/functions.php');

if (!isset($_SESSION['username'])) {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $document_type = $_POST['document_type'] ?? null;
    $document_id = $_POST['document_id'] ?? null;
    $document_name = $_POST['document_name'] ?? null;

    $details = '';
    switch ($action) {
        case 'view':
            $details = "Viewed document";
            break;
        case 'download':
            $details = "Downloaded document";
            break;
        default:
            $details = "Performed action: $action";
    }

    logActivity($action, $details, $document_type, $document_name, $document_id);
    echo "Activity logged";
}
