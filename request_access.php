<?php
include('includes/auth.php');
include('includes/config.php');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $reason = trim($_POST['reason']);

    if (empty($full_name) || empty($email) || empty($reason)) {
        $error = "Please fill all required fields";
    } else {
        // In a real application, you would:
        // 1. Send an email to the super admin
        // 2. Store the request in database
        // 3. Send confirmation to the requester

        $success = "Your access request has been submitted. The super admin will review your request shortly.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Access - eFIND System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa, #e4e8f0);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .request-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
        }
    </style>
</head>

<body>
    <div class="request-container">
        <h2 class="text-center mb-4">Request Admin Access</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="post">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" class="form-control" name="contact_number">
                </div>

                <div class="mb-4">
                    <label class="form-label">Reason for Access</label>
                    <textarea class="form-control" name="reason" rows="4" required></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i> Submit Request
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="login.php" class="text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i> Back to Login
            </a>
        </div>
    </div>
</body>

</html>