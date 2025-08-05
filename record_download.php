<?php
include(__DIR__ . '/includes/config.php');
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("UPDATE ordinances SET downloads = downloads + 1 WHERE id = $id");
}
?>
