<?php
session_start();
require_once('includes/config.php');
require_once('includes/auth.php');
// require_once('includes/activity_logger.php');

// Redirect if not logged in or not an admin (role_id 1 or 2)
if (!isLoggedIn() || ($_SESSION['role_id'] > 2)) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Function to sanitize input
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Sanitize and validate inputs
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $contact_number = sanitize_input($_POST['contact_number'] ?? '');

        // Basic validation
        if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
            throw new Exception("All fields are required except contact number.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new Exception("Password must contain at least one uppercase letter and one number.");
        }

        // Check if username or email already exists
        $check_query = "SELECT id FROM admin_users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_query);

        if (!$check_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            throw new Exception("Username or email already exists.");
        }
        $check_stmt->close();

        // Hash password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        if (!$password_hash) {
            throw new Exception("Failed to hash password.");
        }

        // Insert new staff account (role_id 3 for regular staff)
        $query = "INSERT INTO admin_users (full_name, email, username, password, contact_number, role_id, status)
                  VALUES (?, ?, ?, ?, ?, 3, 'active')";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("sssss", $full_name, $email, $username, $password_hash, $contact_number);

        if ($stmt->execute()) {
            $success = "Staff account created successfully!";
            $new_user_id = $stmt->insert_id;

            // Log the activity
            logActivity(
                'STAFF_CREATE',
                "Created staff account: $username",
                'USER',
                $username,
                $new_user_id
            );
        } else {
            throw new Exception("Failed to create staff account: " . $stmt->error);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}
