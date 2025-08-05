<?php
session_start();
include('includes/config.php');

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: forgot_password.php");
    exit();
}

// Check if the token is valid and not expired
$query = "SELECT id FROM admin_users WHERE reset_token = ? AND reset_token_expires > NOW()";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = "Invalid or expired token.";
} else {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($password) || empty($confirm_password)) {
            $error = "Both password and confirm password are required.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            // Hash the new password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Update the password and clear the reset token
            $update_query = "UPDATE admin_users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ss", $password_hash, $token);
            $update_stmt->execute();

            $success = "Your password has been reset successfully!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - eFIND System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-blue: #4361ee;
            --secondary-blue: #3a0ca3;
            --light-blue: #e8f0fe;
            --accent-orange: #ff6d00;
            --dark-gray: #2b2d42;
            --medium-gray: #8d99ae;
            --light-gray: #f8f9fa;
            --white: #ffffff;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa, #e4e8f0);
            color: var(--dark-gray);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-x: hidden;
        }

        .container {
            background-color: var(--white);
            border-radius: 24px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
            min-height: 400px;
            position: relative;
            z-index: 1;
            transition: transform 0.5s ease;
            padding: 40px;
        }

        .container:hover {
            transform: translateY(-5px);
        }

        h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin-bottom: 30px;
            font-size: 2.5rem;
            color: var(--dark-gray);
            text-align: center;
        }

        .form-control {
            height: 50px;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 20px;
            font-size: 1rem;
            transition: all 0.3s;
            background-color: var(--light-gray);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            background-color: var(--white);
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark-gray);
            font-size: 0.95rem;
            text-align: left;
            display: block;
            position: relative;
            padding-left: 12px;
        }

        .form-label::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: var(--primary-blue);
            border-radius: 50%;
        }

        .btn {
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .alert {
            margin-bottom: 20px;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            text-align: left;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .alert-success {
            border-left: 4px solid #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }

        .alert-danger {
            border-left: 4px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Reset Password</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="post">
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Reset Password
                </button>
            </form>
        <?php endif; ?>
    </div>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
r