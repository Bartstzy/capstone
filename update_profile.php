<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login first.']);
    exit;
}

require_once 'includes/config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $admin_id = $_SESSION['admin_id'];
        $profile_picture = $_POST['current_profile_picture'] ?? null;

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (in_array($_FILES['profile_picture']['type'], $allowed_types) && $_FILES['profile_picture']['size'] <= $max_size) {
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $file_extension;
                $destination = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                    $profile_picture = $filename;
                } else {
                    throw new Exception("Failed to upload profile picture.");
                }
            } else {
                throw new Exception("Invalid file type or size. Only JPEG or PNG, max 2MB allowed.");
            }
        }

        $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);

        if (!$full_name || !$username || !$email) {
            throw new Exception("Full name, username, and email are required.");
        }

        if ($profile_picture) {
            $query = "UPDATE admin_users SET full_name = ?, username = ?, email = ?, contact_number = ?, profile_picture = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssi", $full_name, $username, $email, $contact_number, $profile_picture, $admin_id);
        } else {
            $query = "UPDATE admin_users SET full_name = ?, username = ?, email = ?, contact_number = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $full_name, $username, $email, $contact_number, $admin_id);
        }

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Profile updated successfully!';
        } else {
            throw new Exception("Failed to update profile.");
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
