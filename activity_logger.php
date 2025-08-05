<?php
function logActivity($conn, $userId, $username, $action, $details = '')
{
    // Get the user's IP address
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    // Get the user agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'];

    // Prepare the SQL statement to insert the activity log
    $query = "INSERT INTO activity_logs (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);

    // Bind parameters and execute the statement
    $stmt->bind_param("isssss", $userId, $username, $action, $details, $ipAddress, $userAgent);
    $stmt->execute();

    // Close the statement
    $stmt->close();
}
