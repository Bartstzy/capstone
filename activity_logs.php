<?php
require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/includes/auth.php');
require_once(__DIR__ . '/includes/functions.php');

// Ensure database connection is available
global $conn;

// Check if the user is logged in
if (!isLoggedIn()) {
    header("Location: /index.php");
    exit();
}

// Pagination settings
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Filter parameters
$userFilter = isset($_GET['user']) ? $_GET['user'] : '';
$actionFilter = isset($_GET['action']) ? $_GET['action'] : '';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';

// Build the query with filters
$whereClauses = [];
$params = [];
$types = '';

if (!empty($userFilter)) {
    $whereClauses[] = "username LIKE ?";
    $params[] = "%$userFilter%";
    $types .= 's';
}

if (!empty($actionFilter)) {
    $whereClauses[] = "action = ?";
    $params[] = $actionFilter;
    $types .= 's';
}

if (!empty($dateFilter)) {
    $whereClauses[] = "DATE(created_at) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$where = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM activity_logs $where";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$total = $countResult->fetch_assoc()['total'];
$countStmt->close();

// Get paginated results
$query = "SELECT * FROM activity_logs $where ORDER BY created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $perPage;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$activityLogs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total pages
$totalPages = ceil($total / $perPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - eFIND System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
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
        }

        .activity-logs-container {
            padding: 20px;
            margin-top: 80px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-blue);
        }

        .page-title {
            font-family: 'Montserrat', sans-serif;
            color: var(--secondary-blue);
            font-weight: 700;
            margin: 0;
            position: relative;
        }

        .page-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -17px;
            width: 60px;
            height: 4px;
            background: var(--accent-orange);
            border-radius: 2px;
        }

        .table-container {
            background-color: var(--white);
            border-radius: 16px;
            box-shadow: var(--box-shadow);
            padding: 20px;
            margin-bottom: 30px;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: var(--primary-blue);
            color: var(--white);
            font-weight: 600;
            border: none;
        }

        .table td {
            vertical-align: middle;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .table tr:hover td {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .filter-section {
            background-color: var(--white);
            border-radius: 16px;
            box-shadow: var(--box-shadow);
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-title {
            font-weight: 600;
            color: var(--secondary-blue);
            margin-bottom: 15px;
        }

        .floating-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
            transition: all 10s linear;
        }

        .shape-1 {
            width: 150px;
            height: 150px;
            background: var(--primary-blue);
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            top: 10%;
            left: 10%;
            animation: float 15s infinite ease-in-out;
        }

        .shape-2 {
            width: 100px;
            height: 100px;
            background: var(--accent-orange);
            border-radius: 50%;
            bottom: 15%;
            right: 10%;
            animation: float 12s infinite ease-in-out reverse;
        }

        .shape-3 {
            width: 180px;
            height: 180px;
            background: var(--secondary-blue);
            border-radius: 50% 20% 50% 30%;
            top: 50%;
            right: 20%;
            animation: float 18s infinite ease-in-out;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }

        @media (max-width: 768px) {
            .activity-logs-container {
                margin-top: 70px;
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-title {
                margin-bottom: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <?php include(__DIR__ . '/includes/navbar.php'); ?>

    <div class="activity-logs-container">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Activity Logs</h1>
                <div class="text-muted">
                    Total: <?= $total ?> records
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="activity_logs.php">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="userFilter" class="form-label">Filter by User</label>
                            <input type="text" class="form-control" id="userFilter" name="user" value="<?= htmlspecialchars($userFilter) ?>" placeholder="Enter username...">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="actionFilter" class="form-label">Filter by Action</label>
                            <select class="form-select" id="actionFilter" name="action">
                                <option value="">All Actions</option>
                                <option value="login" <?= $actionFilter === 'login' ? 'selected' : '' ?>>Login</option>
                                <option value="logout" <?= $actionFilter === 'logout' ? 'selected' : '' ?>>Logout</option>
                                <option value="view" <?= $actionFilter === 'view' ? 'selected' : '' ?>>View</option>
                                <option value="download" <?= $actionFilter === 'download' ? 'selected' : '' ?>>Download</option>
                                <option value="upload" <?= $actionFilter === 'upload' ? 'selected' : '' ?>>Upload</option>
                                <option value="delete" <?= $actionFilter === 'delete' ? 'selected' : '' ?>>Delete</option>
                                <option value="ocr" <?= $actionFilter === 'ocr' ? 'selected' : '' ?>>OCR</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="dateFilter" class="form-label">Filter by Date</label>
                            <input type="date" class="form-control" id="dateFilter" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <a href="activity_logs.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Clear Filters
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Activity Logs Table -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Document</th>
                                <th>IP Address</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activityLogs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No activity logs found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activityLogs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['id']) ?></td>
                                        <td><?= htmlspecialchars($log['username']) ?></td>
                                        <td><?= htmlspecialchars($log['action']) ?></td>
                                        <td><?= htmlspecialchars($log['details']) ?></td>
                                        <td>
                                            <?php if ($log['document_name']): ?>
                                                <?= htmlspecialchars($log['document_type'] . ': ' . $log['document_name']) ?>
                                                <?php if ($log['document_id']): ?>
                                                    (ID: <?= htmlspecialchars($log['document_id']) ?>)
                                                <?php endif; ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Activity logs pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&user=<?= urlencode($userFilter) ?>&action=<?= urlencode($actionFilter) ?>&date=<?= urlencode($dateFilter) ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&user=<?= urlencode($userFilter) ?>&action=<?= urlencode($actionFilter) ?>&date=<?= urlencode($dateFilter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&user=<?= urlencode($userFilter) ?>&action=<?= urlencode($actionFilter) ?>&date=<?= urlencode($dateFilter) ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php include(__DIR__ . '/includes/footer.php'); ?>