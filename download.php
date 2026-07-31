<?php
require_once 'functions.php';
requireLogin();

if (!isset($_GET['file']) || empty($_GET['file'])) {
    die("No file specified.");
}

$file_param = $_GET['file'];

// Basic security check to prevent directory traversal
if (strpos($file_param, '..') !== false) {
    die("Invalid file path.");
}

// Check if it's a legacy path (still in the public_html/uploads directory)
if (strpos($file_param, 'uploads/') === 0) {
    // It's in the old location
    $file_path = __DIR__ . '/' . $file_param;
} else {
    // It's in the new external upload directory
    $file_path = UPLOAD_DIR . $file_param;
}

if (!file_exists($file_path)) {
    die("File not found on server.");
}

// Get the MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_path);
finfo_close($finfo);

// Set headers for download / viewing
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Stream the file
readfile($file_path);
exit;
?>
