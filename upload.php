<?php
// Upload handler for photos
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Disable error reporting for clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Configuration
$uploadDir = 'uploads/';
$maxFileSize = 200 * 1024 * 1024; // 200MB

// Increase PHP limits for large file uploads
ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '210M');
ini_set('max_execution_time', '300'); // 5 minutes for large uploads
ini_set('max_input_time', '300');
$allowedTypes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/mpeg', 'video/3gpp',
    'application/octet-stream'  // iOS sometimes sends this for videos
];

$response = ['success' => false, 'message' => '', 'files' => []];

try {
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Upload-Verzeichnis konnte nicht erstellt werden');
        }
    }

    // Create date subdirectory
    $dateDir = $uploadDir . date('Y-m-d') . '/';
    if (!file_exists($dateDir)) {
        if (!mkdir($dateDir, 0755, true)) {
            throw new Exception('Tages-Verzeichnis konnte nicht erstellt werden');
        }
    }
    if (!isset($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
        throw new Exception('Keine Dateien ausgewählt!');
    }

    $files = $_FILES['photos'];
    $uploadedFiles = [];

    // Process each file
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            throw new Exception('Fehler beim Upload der Datei: ' . $files['name'][$i]);
        }

        // Validate file size
        if ($files['size'][$i] > $maxFileSize) {
            throw new Exception('Datei zu groß: ' . $files['name'][$i]);
        }

        // Extract extension and detect file type - be generous (case-insensitive)
        $fileName = $files['name'][$i];
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        // Fallback: extract extension manually if pathinfo fails
        if (empty($extension)) {
            $lastDot = strrpos($fileName, '.');
            if ($lastDot !== false) {
                $extension = substr($fileName, $lastDot + 1);
            }
        }
        // Always convert to lowercase for case-insensitive comparison
        $extension = strtolower($extension);
        $extensionLower = $extension; // Keep for consistency
        
        // Try to detect MIME type, but don't be strict about it
        $fileType = mime_content_type($files['tmp_name'][$i]);
        if ($fileType === false || empty($fileType)) {
            // If MIME detection fails, try to guess from extension
            $extensionToMime = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'webp' => 'image/webp',
                'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo',
                'webm' => 'video/webm', 'mpeg' => 'video/mpeg', 'mpg' => 'video/mpeg',
                'm4v' => 'video/mp4', '3gp' => 'video/3gpp'
            ];
            $fileType = $extensionToMime[$extensionLower] ?? 'application/octet-stream';
        }
        
        // Accept all files - we're being generous here
        // Only really problematic file types would be rejected, but we allow most things

        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $dateDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($files['tmp_name'][$i], $filepath)) {
            throw new Exception('Fehler beim Speichern der Datei: ' . $files['name'][$i]);
        }

        // Create thumbnail only for images
        if (str_starts_with($fileType, 'image/')) {
            createThumbnail($filepath, $dateDir . 'thumb_' . $filename);
        }

        $uploadedFiles[] = [
            'original_name' => $files['name'][$i],
            'filename' => $filename,
            'size' => $files['size'][$i],
            'type' => $fileType,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
    }

    $response['success'] = true;
    $fileCount = count($uploadedFiles);
    $response['message'] = $fileCount . ' Datei(en) erfolgreich hochgeladen!';
    $response['files'] = $uploadedFiles;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} catch (Error $e) {
    $response['message'] = 'PHP-Fehler: ' . $e->getMessage();
} catch (Throwable $e) {
    $response['message'] = 'Unbekannter Fehler: ' . $e->getMessage();
}

// Ensure we always output valid JSON
$json = json_encode($response, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = json_encode(['success' => false, 'message' => 'JSON-Encoding-Fehler', 'files' => []]);
}

echo $json;

/**
 * Create thumbnail for uploaded image
 */
function createThumbnail($sourcePath, $thumbPath, $maxWidth = 300, $maxHeight = 300) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;

    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];

    // Calculate thumbnail dimensions
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $thumbWidth = intval($sourceWidth * $ratio);
    $thumbHeight = intval($sourceHeight * $ratio);

    // Create source image resource
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if (!$sourceImage) return false;

    // Create thumbnail
    $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
    
    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);
        $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
        imagefilledrectangle($thumbImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
    }

    imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);

    // Save thumbnail
    $success = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $success = imagejpeg($thumbImage, $thumbPath, 85);
            break;
        case 'image/png':
            $success = imagepng($thumbImage, $thumbPath, 8);
            break;
        case 'image/gif':
            $success = imagegif($thumbImage, $thumbPath);
            break;
        case 'image/webp':
            $success = imagewebp($thumbImage, $thumbPath, 85);
            break;
    }

    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($thumbImage);

    return $success;
}
?>
