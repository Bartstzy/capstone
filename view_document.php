<?php
include('includes/auth.php');
include('includes/config.php');

if (isset($_GET['id']) && isset($_GET['type'])) {
    $document_id = $_GET['id'];
    $document_type = $_GET['type'];

    // Fetch the document details
    $document = $conn->query("SELECT * FROM {$document_type}s WHERE id = $document_id")->fetch_assoc();

    // Display the document details
    echo "<div class='container mt-5'>";
    echo "<h1 class='mb-4'>" . htmlspecialchars($document['title']) . "</h1>";

    // Check if the document has an image and display it
    if (!empty($document['image_path'])) {
        echo "<div class='mb-4'>";
        echo "<img src='" . htmlspecialchars($document['image_path']) . "' class='img-fluid' alt='Document Image' style='max-height: 500px;'>";
        echo "</div>";
    }

    // Display other document details
    echo "<p class='lead'>" . nl2br(htmlspecialchars($document['description'] ?? $document['content'])) . "</p>";

    // Display additional fields as necessary
    if (!empty($document['reference_number'])) {
        echo "<p><strong>Reference Number:</strong> " . htmlspecialchars($document['reference_number']) . "</p>";
    }

    if (!empty($document['date_issued']) || !empty($document['date_posted'])) {
        $date = !empty($document['date_issued']) ? $document['date_issued'] : $document['date_posted'];
        echo "<p><strong>Date:</strong> " . date('M j, Y', strtotime($date)) . "</p>";
    }

    echo "</div>";
} else {
    echo "Invalid request.";
}
?>
