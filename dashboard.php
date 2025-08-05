<?php
include('includes/auth.php');
include('includes/config.php');
include('includes/functions.php');

// Fetch counts for statistics cards
$ordinances_count = $conn->query("SELECT COUNT(*), SUM(downloads) as total_downloads, SUM(views) as total_views FROM ordinances")->fetch_assoc();
$resolutions_count = $conn->query("SELECT COUNT(*), SUM(downloads) as total_downloads, SUM(views) as total_views FROM resolutions")->fetch_assoc();
$meeting_minutes_count = $conn->query("SELECT COUNT(*) FROM meeting_minutes")->fetch_row()[0];

// Fetch distinct years from the database
$years_query = $conn->query("
    SELECT DISTINCT YEAR(date_issued) as year FROM ordinances
    UNION
    SELECT DISTINCT YEAR(date_issued) as year FROM resolutions
    UNION
    SELECT DISTINCT YEAR(date_posted) as year FROM meeting_minutes
    ORDER BY year DESC
");
$available_years = $years_query->fetch_all(MYSQLI_ASSOC);

// Handle search functionality
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';
$document_type = isset($_GET['document_type']) ? $_GET['document_type'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : '';

// Build the query for latest records with search functionality
$query_parts = [];
$params = [];
$types = '';

// Base conditions
$query_parts[] = "(SELECT 'ordinance' as type, id, title, date_issued as date, reference_number, updated_by, description, file_path, downloads, views, extracted_texts
                  FROM ordinances WHERE 1=1";
if ($document_type === 'ordinance' || $document_type === '') {
    if ($search_query !== '') {
        $tags = explode(' ', $search_query);
        $tag_conditions = [];
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                $tag_conditions[] = "(title LIKE ? OR reference_number LIKE ? OR description LIKE ? OR extracted_texts LIKE ?)";
                array_push($params, "%$tag%", "%$tag%", "%$tag%", "%$tag%");
                $types .= 'ssss';
            }
        }
        if (!empty($tag_conditions)) {
            $query_parts[] = "AND (" . implode(" OR ", $tag_conditions) . ")";
        }
    }
} else {
    $query_parts[] = "AND 1=0"; // Exclude if not selected
}
if ($year !== '') {
    $query_parts[] = "AND YEAR(date_issued) = ?";
    array_push($params, $year);
    $types .= 'i';
}
$query_parts[] = "ORDER BY date_issued DESC LIMIT 3)";
$query_parts[] = "UNION (SELECT 'resolution' as type, id, title, date_issued as date, reference_number, updated_by, description, file_path, downloads, views, extracted_texts as extracted_textss
                  FROM resolutions WHERE 1=1";
if ($document_type === 'resolution' || $document_type === '') {
    if ($search_query !== '') {
        $tags = explode(' ', $search_query);
        $tag_conditions = [];
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                $tag_conditions[] = "(title LIKE ? OR reference_number LIKE ? OR description LIKE ? OR extracted_texts LIKE ?)";
                array_push($params, "%$tag%", "%$tag%", "%$tag%", "%$tag%");
                $types .= 'ssss';
            }
        }
        if (!empty($tag_conditions)) {
            $query_parts[] = "AND (" . implode(" OR ", $tag_conditions) . ")";
        }
    }
} else {
    $query_parts[] = "AND 1=0";
}
if ($year !== '') {
    $query_parts[] = "AND YEAR(date_issued) = ?";
    array_push($params, $year);
    $types .= 'i';
}
$query_parts[] = "ORDER BY date_issued DESC LIMIT 3)";
$query_parts[] = "UNION (SELECT 'meeting' as type, id, title, date_posted as date, '' as reference_number, updated_by, content as description, image_path as file_path, 0 as downloads, 0 as views, content as extracted_textss
                  FROM meeting_minutes WHERE 1=1";
if ($document_type === 'meeting' || $document_type === '') {
    if ($search_query !== '') {
        $tags = explode(' ', $search_query);
        $tag_conditions = [];
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                $tag_conditions[] = "(title LIKE ? OR content LIKE ?)";
                array_push($params, "%$tag%", "%$tag%");
                $types .= 'ss';
            }
        }
        if (!empty($tag_conditions)) {
            $query_parts[] = "AND (" . implode(" OR ", $tag_conditions) . ")";
        }
    }
} else {
    $query_parts[] = "AND 1=0";
}
if ($year !== '') {
    $query_parts[] = "AND YEAR(date_posted) = ?";
    array_push($params, $year);
    $types .= 'i';
}
$query_parts[] = "ORDER BY date_posted DESC LIMIT 3)";
$query_parts[] = "ORDER BY date DESC LIMIT 5";
$query = implode(' ', $query_parts);

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$latest_records = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - eFIND System</title>
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
        }

        .dashboard-container {
            padding: 20px;
            margin-top: 80px;
        }

        .search-container {
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .search-input {
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 12px 20px;
        }

        .search-btn {
            background-color: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 500;
        }

        .filter-card {
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .filter-title {
            color: var(--secondary-blue);
            font-weight: 600;
            margin-bottom: 15px;
            font-family: 'Montserrat', sans-serif;
        }

        .filter-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-item:hover,
        .filter-item.active {
            background-color: var(--light-blue);
            color: var(--primary-blue);
        }

        .tag-item {
            display: inline-block;
            background-color: var(--light-blue);
            color: var(--primary-blue);
            padding: 5px 15px;
            border-radius: 20px;
            margin-right: 10px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
        }

        .tag-item:hover,
        .tag-item.active {
            background-color: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }

        .document-card {
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .document-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .document-type {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--white);
            background-color: var(--primary-blue);
            padding: 3px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .document-type.ordinance {
            background-color: #7209b7;
        }

        .document-type.resolution {
            background-color: #4361ee;
        }

        .document-type.meeting {
            background-color: #4895ef;
        }

        .document-type.announcement {
            background-color: #f72585;
        }

        .document-ref {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-bottom: 5px;
        }

        .document-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-gray);
        }

        .document-date {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-bottom: 15px;
        }

        .document-updated {
            font-size: 0.8rem;
            color: var(--medium-gray);
        }

        .document-actions a {
            margin-right: 10px;
        }

        .pagination {
            justify-content: center;
            margin-top: 30px;
        }

        .page-item.active .page-link {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .page-link {
            color: var(--primary-blue);
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

        /* Chatbot Styles */
        .chatbot-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s;
        }

        .chatbot-btn:hover {
            transform: scale(1.1);
            background-color: var(--secondary-blue);
        }

        .chatbot-container {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 350px;
            height: 500px;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            display: none;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden;
        }

        .chatbot-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chatbot-body {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background-color: #f9f9f9;
        }

        .chatbot-footer {
            padding: 15px;
            border-top: 1px solid #eee;
            background-color: white;
        }

        .chat-message {
            margin-bottom: 10px;
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 18px;
            font-size: 0.9rem;
        }

        .bot-message {
            background-color: #f0f0f0;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }

        .user-message {
            background-color: var(--primary-blue);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        .announcement-image {
            width: 100%;
            height: auto;
            max-height: 150px;
            object-fit: contain;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .extracted-text {
            background-color: var(--light-gray);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            max-height: 150px;
            overflow-y: auto;
            font-size: 0.9rem;
            line-height: 1.5;
            border-left: 4px solid var(--primary-blue);
        }

        .extracted-text-title {
            font-weight: 600;
            color: var(--secondary-blue);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin-top: 70px;
                padding: 15px;
            }

            .chatbot-container {
                width: 90%;
                right: 5%;
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
    <div class="dashboard-container">
        <div class="container">
            <!-- Search Section with Tags Below -->
            <div class="search-container">
                <h2 class="mb-4" style="color: var(--secondary-blue); font-family: 'Montserrat', sans-serif;">Electronic Full-text Integrated Navigation for Documents</h2>
                <form method="GET" action="dashboard.php">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="input-group mb-3">
                                <input type="text" class="form-control search-input" name="search_query"
                                    placeholder="Search for documents by title, content, or reference number..."
                                    value="<?= htmlspecialchars($search_query) ?>">
                                <button class="btn search-btn" type="submit">
                                    <i class="fas fa-search me-1"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select search-input" name="document_type">
                                <option value="">All Document Types</option>
                                <option value="ordinance" <?= $document_type === 'ordinance' ? 'selected' : '' ?>>Ordinances</option>
                                <option value="resolution" <?= $document_type === 'resolution' ? 'selected' : '' ?>>Resolutions</option>
                                <option value="meeting" <?= $document_type === 'meeting' ? 'selected' : '' ?>>Meeting Minutes</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select search-input" name="year">
                                <option value="">All Years</option>
                                <?php foreach ($available_years as $year_option): ?>
                                    <option value="<?= $year_option['year'] ?>" <?= $year_option['year'] == $year ? 'selected' : '' ?>>
                                        <?= $year_option['year'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Popular Tags Section Below Search -->
                    <div class="mt-3">
                        <h6 class="mb-2">Popular Tags:</h6>
                        <div>
                            <button type="button" class="tag-item" data-tag="budget">Budget</button>
                            <button type="button" class="tag-item" data-tag="health">Health</button>
                            <button type="button" class="tag-item" data-tag="education">Education</button>
                            <button type="button" class="tag-item" data-tag="security">Security</button>
                            <button type="button" class="tag-item" data-tag="infrastructure">Infrastructure</button>
                            <button type="button" class="tag-item" data-tag="tax">Tax</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="row">
                <!-- Quick Filters Column -->
                <div class="col-md-3">
                    <div class="filter-card">
                        <h5 class="filter-title">Quick Filters</h5>
                        <div class="filter-item <?= !$search_query && !$document_type && !$year ? 'active' : '' ?>">
                            <a href="dashboard.php" class="text-decoration-none d-block">
                                <i class="fas fa-clock me-2"></i> Recent Documents
                            </a>
                        </div>
                        <div class="filter-item">
                            <a href="dashboard.php?filter=starred" class="text-decoration-none d-block">
                                <i class="fas fa-star me-2"></i> Starred Documents
                            </a>
                        </div>
                        <div class="filter-item">
                            <a href="dashboard.php?filter=pending" class="text-decoration-none d-block">
                                <i class="fas fa-hourglass-half me-2"></i> Pending Approval
                            </a>
                        </div>
                        <div class="filter-item">
                            <a href="ordinances.php" class="text-decoration-none d-block">
                                <i class="fas fa-file-alt me-2"></i> Manage Ordinances
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Documents Column -->
                <div class="col-md-9">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="filter-title">Recent Documents</h5>
                        <?php if ($search_query || $document_type || $year): ?>
                            <div class="text-muted small">
                                Showing results for:
                                <?php if ($search_query) echo "'" . htmlspecialchars($search_query) . "' "; ?>
                                <?php if ($document_type) echo "(" . ucfirst($document_type) . "s)"; ?>
                                <?php if ($year) echo "Year: $year"; ?>
                                <a href="dashboard.php" class="ms-2">Clear filters</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($latest_records)): ?>
                        <div class="alert alert-info">No documents found matching your search criteria</div>
                    <?php else: ?>
                        <?php foreach ($latest_records as $record): ?>
                            <div class="document-card">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="document-type <?= $record['type'] ?>">
                                            <?= ucfirst($record['type']) ?>
                                        </span>
                                        <?php if (!empty($record['reference_number'])): ?>
                                            <span class="text-muted small ms-2">Ref: <?= htmlspecialchars($record['reference_number']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small">
                                        <?= date('M j, Y', strtotime($record['date'])) ?>
                                    </div>
                                </div>
                                <h5 class="mt-2"><?= htmlspecialchars($record['title']) ?></h5>
                                <?php if (!empty($record['description'])): ?>
                                    <p class="small text-muted"><?= nl2br(htmlspecialchars(substr($record['description'], 0, 150))) ?>...</p>
                                <?php endif; ?>

                                <!-- Extracted Text Section -->
                                <?php if (!empty($record['extracted_textss'])): ?>
                                    <div class="extracted-text">
                                        <div class="extracted-text-title">
                                            <span>Extracted Text:</span>
                                            <button class="btn btn-sm btn-link toggle-text" data-target="#extractedText<?= $record['id'] ?>">
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                        </div>
                                        <div id="extractedText<?= $record['id'] ?>" class="extracted-text-content">
                                            <?= nl2br(htmlspecialchars(substr($record['extracted_textss'], 0, 500))) ?>
                                            <?php if (strlen($record['extracted_textss']) > 500): ?>
                                                ... <a href="#" class="view-full-text" data-id="<?= $record['id'] ?>" data-type="<?= $record['type'] ?>">View Full Text</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted small">
                                        <span class="me-3"><i class="fas fa-download me-1"></i> <?= $record['downloads'] ?> downloads</span>
                                        <span><i class="fas fa-eye me-1"></i> <?= $record['views'] ?> views</span>
                                    </div>
                                    <div class="document-actions">
                                        <a href="download.php?id=<?= $record['id'] ?>&type=<?= $record['type'] ?>&username=<?= urlencode($username) ?>"
                                            class="btn btn-sm btn-outline-primary download-button"
                                            data-id="<?= $record['id'] ?>"
                                            data-type="<?= $record['type'] ?>"
                                            onclick="logDownload(<?= $record['id'] ?>, '<?= $record['type'] ?>', '<?= addslashes($record['title']) ?>')">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <a href="#" class="btn btn-sm btn-primary view-button" data-bs-toggle="modal" data-bs-target="#imageModal" data-image-path="<?= htmlspecialchars($record['file_path']) ?>" data-id="<?= $record['id'] ?>" data-type="<?= $record['type'] ?>">
                                            <i class="fas fa-file-alt"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <li class="page-item"><a class="page-link" href="#">Previous</a></li>
                            <li class="page-item"><a class="page-link" href="#">1</a></li>
                            <li class="page-item active"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Next</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for displaying the document image -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Document View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" alt="Document Image">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Full Text Modal -->
    <div class="modal fade" id="fullTextModal" tabindex="-1" aria-labelledby="fullTextModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fullTextModalLabel">Full Extracted Text</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="fullTextContent" class="extracted-text" style="max-height: 70vh; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Chatbot Button -->
    <div class="chatbot-btn" id="chatbotToggle">
        <i class="fas fa-robot fa-lg"></i>
    </div>

    <!-- Chatbot Container -->
    <div class="chatbot-container" id="chatbotContainer">
        <div class="chatbot-header">
            <span>eFIND Assistant</span>
            <button class="btn btn-sm btn-light" id="closeChatbot">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chatbot-body" id="chatbotMessages">
            <div class="d-flex flex-column">
                <div class="chat-message bot-message">
                    Hello! I'm your eFIND assistant. How can I help you today?
                </div>
                <div class="chat-message bot-message">
                    You can ask me about:
                    <ul>
                        <li>Document search tips</li>
                        <li>System features</li>
                        <li>How to upload documents</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="chatbot-footer">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Type your message..." id="chatbotInput">
                <button class="btn btn-primary" id="sendMessage">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle click on announcement image
            $('.announcement-image').click(function() {
                var fullImageSrc = $(this).data('full-image');
                $('#fullAnnouncementImage').attr('src', fullImageSrc);
            });

            // Handle the view button click
            $('.view-button').click(function() {
                var imagePath = $(this).data('image-path');
                $('#modalImage').attr('src', imagePath);
                var id = $(this).data('id');
                var type = $(this).data('type');
                var title = $(this).closest('.document-card').find('h5').text();

                // Make an AJAX call to increment the view count
                $.ajax({
                    url: 'increment_view.php',
                    type: 'POST',
                    data: {
                        id: id,
                        type: type
                    },
                    success: function(response) {
                        console.log('View count updated successfully.');
                        // Log the view activity
                        $.post('log_activity.php', {
                            action: 'view',
                            document_type: type,
                            document_id: id,
                            document_name: title
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating view count:', error);
                    }
                });
            });

            // Toggle extracted text visibility
            $('.toggle-text').click(function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $(target).slideToggle();
                $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
            });

            // View full extracted text
            $('.view-full-text').click(function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var type = $(this).data('type');

                // Fetch the full text via AJAX
                $.ajax({
                    url: 'get_full_text.php',
                    type: 'GET',
                    data: {
                        id: id,
                        type: type
                    },
                    success: function(response) {
                        $('#fullTextContent').html(nl2br(response));
                        $('#fullTextModal').modal('show');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching full text:', error);
                        $('#fullTextContent').html('Error loading full text. Please try again.');
                        $('#fullTextModal').modal('show');
                    }
                });
            });

            // Function to log downloads
            window.logDownload = function(id, type, title) {
                $.post('log_activity.php', {
                    action: 'download',
                    document_type: type,
                    document_id: id,
                    document_name: title
                });
            };

            // Array to store selected tags
            let selectedTags = [];

            // Tag item click
            $('.tag-item').click(function() {
                const tag = $(this).data('tag');
                // Toggle the tag selection
                if (selectedTags.includes(tag)) {
                    selectedTags = selectedTags.filter(item => item !== tag);
                    $(this).removeClass('active');
                } else {
                    selectedTags.push(tag);
                    $(this).addClass('active');
                }
                // Update the search query
                updateSearchQuery();
            });

            // Function to update the search query based on selected tags
            function updateSearchQuery() {
                let searchQuery = selectedTags.join(' ');
                $('input[name="search_query"]').val(searchQuery);
                // Submit the form to update the search results
                $('form').submit();
            }

            // Filter item click
            $('.filter-item').click(function() {
                $('.filter-item').removeClass('active');
                $(this).addClass('active');
            });

            // Chatbot Toggle
            $('#chatbotToggle').click(function() {
                $('#chatbotContainer').fadeToggle();
            });

            $('#closeChatbot').click(function() {
                $('#chatbotContainer').fadeOut();
            });

            // Send message
            $('#sendMessage').click(function() {
                sendMessage();
            });

            $('#chatbotInput').keypress(function(e) {
                if (e.which == 13) {
                    sendMessage();
                }
            });

            function sendMessage() {
                const message = $('#chatbotInput').val().trim();
                if (message !== '') {
                    // Add user message
                    $('#chatbotMessages').append(`
                        <div class="d-flex justify-content-end mb-2">
                            <div class="chat-message user-message">
                                ${message}
                            </div>
                        </div>
                    `);
                    // Clear input
                    $('#chatbotInput').val('');
                    // Scroll to bottom
                    $('#chatbotMessages').scrollTop($('#chatbotMessages')[0].scrollHeight);
                    // Process message and generate response
                    processMessage(message);
                }
            }

            function processMessage(message) {
                let response = "I'm a simple chatbot. In a real implementation, I would analyze your question and provide a proper response.";
                // Simple keyword matching
                if (message.toLowerCase().includes('search')) {
                    response = "To search for documents, use the search box at the top of the page. You can search by title, content, or reference number.";
                } else if (message.toLowerCase().includes('upload') || message.toLowerCase().includes('add')) {
                    response = "To upload a new document, go to the Documents menu and select 'Add Document'. You'll need to provide the document details and upload the file.";
                } else if (message.toLowerCase().includes('ordinance') || message.toLowerCase().includes('resolution')) {
                    response = "Ordinances and resolutions can be found in the Documents section. Use the filters to narrow down your search.";
                } else if (message.toLowerCase().includes('extracted text') || message.toLowerCase().includes('content')) {
                    response = "The extracted text from documents is displayed in the document cards. You can click 'View Full Text' to see the complete content.";
                }
                // Simulate typing delay
                setTimeout(function() {
                    $('#chatbotMessages').append(`
                        <div class="d-flex justify-content-start mb-2">
                            <div class="chat-message bot-message">
                                ${response}
                            </div>
                        </div>
                    `);
                    $('#chatbotMessages').scrollTop($('#chatbotMessages')[0].scrollHeight);
                }, 1000);
            }
        });

        // Helper function to convert newlines to <br> tags
        function nl2br(str) {
            return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
        }

        function redirectToDocumentType(type) {
            switch (type) {
                case 'ordinance':
                    window.location.href = 'ordinances.php';
                    break;
                case 'resolution':
                    window.location.href = 'resolutions.php';
                    break;
                case 'meeting':
                    window.location.href = 'minutes_of_meeting.php';
                    break;
                default:
                    // Stay on current page for "All Document Types"
                    break;
            }
        }
    </script>
</body>

</html>
<?php include(__DIR__ . '/includes/footer.php'); ?>