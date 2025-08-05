<?php
include('includes/auth.php');
include('includes/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $type = isset($_POST['type']) ? $_POST['type'] : '';

    $validTypes = ['ordinance', 'resolution', 'meeting'];
    if (!in_array($type, $validTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid document type.']);
        exit;
    }

    $table = $type === 'meeting' ? 'meeting_minutes' : $type . 's';
    $query = "UPDATE $table SET views = views + 1 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update view count.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
