<?php
include('includes/config.php');
if(session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fetch counts for statistics cards
$ordinances_count = $conn->query("SELECT COUNT(*) FROM ordinances")->fetch_row()[0];
$resolutions_count = $conn->query("SELECT COUNT(*) FROM resolutions")->fetch_row()[0];
$announcements_count = $conn->query("SELECT COUNT(*) FROM announcements")->fetch_row()[0];

// Fetch recent announcements
$recent_announcements = $conn->query("SELECT * FROM announcements ORDER BY date_posted DESC LIMIT 3");

// Fetch recent documents (both ordinances and resolutions)
$recent_documents = $conn->query("
    (SELECT 'ordinance' as type, id, title, date_issued, file_path FROM ordinances ORDER BY date_issued DESC LIMIT 2)
    UNION ALL
    (SELECT 'resolution' as type, id, title, date_issued, file_path FROM resolutions ORDER BY date_issued DESC LIMIT 2)
    ORDER BY date_issued DESC LIMIT 4
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Barangay Poblacion South</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include('includes/navbar.php'); ?>

    <!-- Hero Section
    <header class="hero-section text-center py-5">
        <div class="container py-5">
            <h1 class="display-4 fw-bold text-white">Welcome to Barangay Poblacion South Dashboard</h1>
            <p class="lead text-white">Solano, Nueva Vizcaya</p>
        </div>
    </header> -->

  

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
