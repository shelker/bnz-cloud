<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Access denied');
}

// Get date parameter
$date = $_GET['date'] ?? '';
if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    die('Invalid date format');
}

$uploadDir = '../uploads/' . $date . '/';
if (!is_dir($uploadDir)) {
    http_response_code(404);
    die('No photos found for this date');
}

// Create ZIP file
$zipFilename = 'fotos_' . $date . '.zip';
$zipPath = sys_get_temp_dir() . '/' . $zipFilename;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    http_response_code(500);
    die('Cannot create ZIP file');
}

// Add all photos and videos to ZIP
$files = array_diff(scandir($uploadDir), ['.', '..']);
$fileCount = 0;

foreach ($files as $file) {
    // Accept all image and video formats (be generous)
    $imageExtensions = 'jpg|jpeg|png|gif|webp';
    $videoExtensions = 'mp4|mov|avi|webm|mpeg|mpg|m4v|3gp';
    if (!str_starts_with($file, 'thumb_') && preg_match('/\.(' . $imageExtensions . '|' . $videoExtensions . ')$/i', $file)) {
        $filePath = $uploadDir . $file;
        if (is_file($filePath)) {
            $zip->addFile($filePath, $file);
            $fileCount++;
        }
    }
}

$zip->close();

if ($fileCount === 0) {
    unlink($zipPath);
    http_response_code(404);
    die('No files found');
}

// Send ZIP file to browser
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

readfile($zipPath);

// Clean up
unlink($zipPath);
?>




