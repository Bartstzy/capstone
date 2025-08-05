<?php
session_start();
include('includes/config.php');

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate inputs
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $contact_number = trim($_POST['contact_number'] ?? '');

    // Basic validation
    if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
        $error = "All fields are required except contact number.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check if username or email already exists
        $check_query = "SELECT id FROM admin_users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Insert new admin with pending status
            $query = "INSERT INTO admin_users (full_name, email, username, password_hash, contact_number, status)
                      VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssss", $full_name, $email, $username, $password_hash, $contact_number);

            if ($stmt->execute()) {
                $success = "Registration successful! Your account is pending approval.";
            } else {
                $error = "Registration failed: " . $stmt->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - eFIND System</title>
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

        .registration-wrapper {
            background-color: var(--white);
            border-radius: 24px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            min-height: 600px;
            position: relative;
            z-index: 1;
            transition: transform 0.5s ease;
            display: flex;
        }

        .registration-wrapper:hover {
            transform: translateY(-5px);
        }

        .logo-section {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: var(--white);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .logo-section::before {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }

        .logo-section::after {
            content: "";
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
        }

        .logo-section img {
            max-width: 80%;
            height: auto;
            margin-bottom: 30px;
            z-index: 2;
            transition: transform 0.5s ease;
        }

        .logo-section:hover img {
            transform: scale(1.05);
        }

        .logo-section h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 2.5rem;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .register-section {
            flex: 1.5;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .branding {
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .efind-logo {
            height: 80px;
            width: 80px;
            margin-bottom: 20px;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
            transition: all 0.3s ease;
        }

        .efind-logo:hover {
            transform: rotate(5deg) scale(1.1);
            filter: drop-shadow(0 6px 8px rgba(0, 0, 0, 0.15));
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--secondary-blue);
            font-size: 2rem;
            margin-bottom: 5px;
            letter-spacing: 1px;
            position: relative;
            display: inline-block;
        }

        .brand-name::after {
            content: "";
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--accent-orange);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .brand-name:hover::after {
            width: 100px;
        }

        .brand-subtitle {
            color: var(--medium-gray);
            font-size: 0.9rem;
            font-style: italic;
            margin-top: 10px;
        }

        .register-form {
            width: 100%;
            max-width: 500px;
            position: relative;
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

        .btn-register {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
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

        .btn-register:hover {
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
    <div class="registration-wrapper">
        <div class="logo-section">
            <img src="images/eFind_logo5.png" alt="Poblacion South Logo">
            <h1>Admin Registration</h1>
            <p>Join our eFIND System</p>
        </div>

        <div class="register-section">
            <div class="branding">
                <img src="images/logo_pbsth.png" alt="eFIND Logo" class="efind-logo">
                <div class="brand-name">Create Admin Account</div>
                <p class="brand-subtitle">Fill in your details to register</p>
            </div>
            <div class="register-form">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required
                            value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="tel" class="form-control" id="contact_number" name="contact_number"
                            value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus me-2"></i>Register Account
                    </button>
                </form>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>