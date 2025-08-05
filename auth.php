<?php
// auth.php

// Define the base directory
define('BASE_DIR', __DIR__);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify files exist before requiring them
$required_files = [
    BASE_DIR . '/config.php',
    BASE_DIR . '/functions.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Required file missing: " . basename($file));
    }
    require_once($file);
}


function getUsernameById($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user ? $user['username'] : 'Unknown';
}

function getUserRoleName($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return 'Unknown';
    }

    $roles = [
        1 => 'Super Admin',
        2 => 'Admin',
        3 => 'User'
    ];
    return $roles[$user['role_id']] ?? 'Unknown';
}

function login($username, $password)
{
    global $conn;

    $stmt = $conn->prepare("SELECT id, username, password, role_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];

            // Log the login activity
            logActivity('login', "User logged in", null, null, $user['id']);
            return true;
        }
    }
    return false;
}

function logout()
{
    if (isset($_SESSION['user_id'])) {
        logActivity('logout', "User logged out", null, null, $_SESSION['user_id']);
    }
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin()
{
    requireLogin();
    if ($_SESSION['role_id'] > 2) { // Assuming 1=Super Admin, 2=Admin
        header("Location: unauthorized.php");
        exit();
    }
}
