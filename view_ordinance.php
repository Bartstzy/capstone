<?php
include(__DIR__ . '/includes/config.php');
include(__DIR__ . '/includes/auth.php');

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Missing or invalid ordinance ID.");
}

$id = intval($_GET['id']);

// Update view count using prepared statement
$stmt = $conn->prepare("UPDATE ordinances SET views = views + 1 WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

// Fetch ordinance
$stmt = $conn->prepare("SELECT * FROM ordinances WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ordinance not found.");
}

$ordinance = $result->fetch_assoc();
$filePath = htmlspecialchars($ordinance['file_path']);
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
?>

<?php include('includes/header.php'); ?>
<div class="container my-4">
    <h2><?= htmlspecialchars($ordinance['title']) ?></h2>
    <p><strong>No:</strong> <?= htmlspecialchars($ordinance['ordinance_number']) ?></p>
    <p><strong>Issued:</strong> <?= date('F j, Y', strtotime($ordinance['date_issued'])) ?></p>
    <p><strong>Description:</strong></p>
    <p><?= nl2br(htmlspecialchars($ordinance['description'])) ?></p>

    <p class="text-muted">
        <small><?= $ordinance['views'] ?> views • <?= $ordinance['downloads'] ?> downloads</small>
    </p>

    <div class="mb-4">
        <?php if ($extension === 'pdf'): ?>
            <iframe src="<?= $filePath ?>" width="100%" height="600px" style="border:1px solid #ccc;"></iframe>
        <?php elseif (in_array($extension, ['jpg', 'jpeg', 'png'])): ?>
            <img src="<?= $filePath ?>" alt="Ordinance Image" class="img-fluid" style="max-height: 600px;">
        <?php else: ?>
            <p>Unsupported file format.</p>
        <?php endif; ?>
    </div>

    <a href="<?= $filePath ?>" download onclick="recordDownload(<?= $id ?>)" class="btn btn-success">
        <i class="fas fa-download"></i> Download
    </a>
</div>

<script>
// Optional: AJAX download count
function recordDownload(id) {
    fetch('record_download.php?id=' + id)
        .catch(err => console.error("Download tracking failed", err));
}

document.getElementById('quickUpload').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Show loading state
    document.getElementById('quickUploadArea').style.display = 'none';
    document.getElementById('ocrLoading').style.display = 'block';
    
    try {
        // Upload to n8n OCR endpoint
        const ocrResult = await processFileWithN8n(file);
        
        // Handle the OCR result (save to DB or display)
        await saveOrdinanceData({
            file_name: file.name,
            file_type: file.type,
            file_size: file.size,
            text_content: ocrResult.text,
            extracted_data: ocrResult.data
        });
        
        // Redirect or show success message
        window.location.href = '/ordinance/view/' + ocrResult.document_id;
    } catch (error) {
        alert('OCR processing failed: ' + error.message);
    } finally {
        // Reset UI
        document.getElementById('quickUploadArea').style.display = 'block';
        document.getElementById('ocrLoading').style.display = 'none';
    }
});

async function processFileWithN8n(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    const response = await fetch('https://your-n8n-instance.com/webhook/ocr-process', {
        method: 'POST',
        body: formData
        // Add authentication headers if needed
    });
    
    if (!response.ok) throw new Error('OCR processing failed');
    return await response.json();
}
</script>

<?php include('includes/footer.php'); ?>
