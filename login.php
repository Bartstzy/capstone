<?php
session_start();
include('includes/config.php');

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    // Sanitize inputs
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = "Both username and password are required.";
    } else {
        // Prepare SQL statement to prevent SQL injection
        $query = "SELECT id, username, password_hash, full_name, profile_picture, role, status FROM admin_users WHERE username = ?";
        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Check account status
                    if ($user['status'] !== 'approved') {
                        $error = "Your account is not approved yet.";
                    } else {
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);

                        // Set session variables
                        $_SESSION['admin_id'] = $user['id'];
                        $_SESSION['admin_username'] = $user['username'];
                        $_SESSION['admin_full_name'] = $user['full_name'];
                        $_SESSION['admin_profile_picture'] = $user['profile_picture'];
                        $_SESSION['admin_role'] = $user['role'];
                        $_SESSION['admin_logged_in'] = true;

                        // Update last login time
                        $update_query = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param("i", $user['id']);
                        $update_stmt->execute();
                        $update_stmt->close();

                        // Redirect to dashboard
                        header("Location: dashboard.php");
                        exit();
                    }
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Invalid username or password.";
            }

            $stmt->close();
        } else {
            $error = "Database error. Please try again later.";
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - eFIND System</title>
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

        .login-wrapper {
            display: flex;
            background-color: var(--white);
            border-radius: 24px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            max-width: 1100px;
            width: 100%;
            min-height: 650px;
            position: relative;
            z-index: 1;
            transition: transform 0.5s ease;
        }

        .login-wrapper:hover {
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

        .login-section {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
        }

        .branding {
            margin-bottom: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .efind-logo {
            height: 120px;
            width: 120px;
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
            font-size: 2.8rem;
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
            font-size: 1.1rem;
            font-style: italic;
            margin-top: 15px;
        }

        .login-form {
            width: 100%;
            max-width: 380px;
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

        .btn-login {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
            letter-spacing: 0.5px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .alert {
            margin-bottom: 20px;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            text-align: left;
            border-left: 4px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="logo-section">
            <img src="images/eFind_logo5.png" alt="Poblacion South Logo">
            <h1>Welcome to eFIND System</h1>
        </div>

        <div class="login-section">
            <div class="branding">
                <img src="images/logo_pbsth.png" alt="eFIND Logo" class="efind-logo">
                <div class="brand-name">System Login</div>
                <p class="brand-subtitle">Please enter your credentials to access the dashboard</p>
            </div>
            <div class="login-form">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form method="post" action="login.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-login" name="login">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                    <div class="mb-3">
                        <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>