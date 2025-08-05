<?php
session_start();
include('includes/config.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Email is required.";
    } else {
        // Check if the email exists in the database
        $query = "SELECT id FROM admin_users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Generate a unique token for password reset
            $token = bin2hex(random_bytes(32));

            // Store the token in the database with an expiration time
            $update_query = "UPDATE admin_users SET reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ss", $token, $email);
            $update_stmt->execute();

            // Send an email with the password reset link
            $reset_link = "http://yourdomain.com/reset_password.php?token=$token";
            $subject = "Password Reset Request";
            $message = "Hello,\n\nYou have requested a password reset. Please click the following link to reset your password:\n\n$reset_link\n\nIf you did not request this, please ignore this email.\n";
            $headers = "From: no-reply@yourdomain.com";

            if (mail($email, $subject, $message, $headers)) {
                $success = "A password reset link has been sent to your email address.";
            } else {
                $error = "Failed to send password reset email.";
            }
        } else {
            $error = "No account found with that email address.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - eFIND System</title>
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
        <h1>Forgot Password</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane me-2"></i> Send Reset Link
            </button>
        </form>
    </div>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>