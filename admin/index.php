<?php
session_start();

// Simple authentication check
$adminPassword = 'meinezunft'; // Change this to a secure password
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle login
if (isset($_POST['password']) && !$isLoggedIn) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
        $isLoggedIn = true;
    } else {
        $loginError = 'Falsches Passwort!';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

// Handle file deletion (AJAX)
if (isset($_POST['delete_file']) && $isLoggedIn) {
    header('Content-Type: application/json');
    $filePath = $_POST['file_path'] ?? '';
    
    // Security: Only allow deletion of files in uploads directory
    $realUploadsPath = realpath('../uploads/');
    $realFilePath = realpath($filePath);
    
    if ($realFilePath && str_starts_with($realFilePath, $realUploadsPath)) {
        // Also delete thumbnail if it exists
        $dir = dirname($realFilePath);
        $filename = basename($realFilePath);
        $thumbPath = $dir . '/thumb_' . $filename;
        
        $deleted = @unlink($realFilePath);
        if ($deleted) {
            // Try to delete thumbnail
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
            }
            echo json_encode(['success' => true, 'message' => 'Datei gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Ungültiger Pfad']);
    }
    exit;
}

// Get all uploaded photos grouped by date
function getPhotosByDate() {
    $uploadDir = '../uploads/';
    $photos = [];
    
    if (!is_dir($uploadDir)) {
        return $photos;
    }
    
    $dates = array_diff(scandir($uploadDir), ['.', '..']);
    rsort($dates); // Sort by date descending
    
    foreach ($dates as $date) {
        $datePath = $uploadDir . $date;
        if (is_dir($datePath)) {
            $files = array_diff(scandir($datePath), ['.', '..']);
            $datePhotos = [];
            
            foreach ($files as $file) {
                // Skip thumbnails and directories
                if (str_starts_with($file, 'thumb_') || is_dir($datePath . '/' . $file)) {
                    continue;
                }
                
                $filePath = $datePath . '/' . $file;
                // Skip if not a file
                if (!is_file($filePath)) {
                    continue;
                }
                
                // Get file extension (case-insensitive)
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                
                // Accept all image and video formats (be generous)
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $videoExtensions = ['mp4', 'mov', 'avi', 'webm', 'mpeg', 'mpg', 'm4v', '3gp'];
                
                $isImage = in_array($extension, $imageExtensions);
                $isVideo = in_array($extension, $videoExtensions);
                
                // Accept files with known extensions OR check MIME type as fallback
                if ($isImage || $isVideo) {
                    $thumbFile = 'thumb_' . $file;
                    $thumbPath = $datePath . '/' . $thumbFile;
                    
                    // Store paths relative to root for display (absolute path from root)
                    // Convert ../uploads/2024-01-01/file.mov to /uploads/2024-01-01/file.mov
                    $relativePath = '/' . str_replace('../', '', $filePath);
                    $relativeThumbPath = file_exists($thumbPath) ? '/' . str_replace('../', '', $thumbPath) : ($isVideo ? null : $relativePath);
                    
                    $datePhotos[] = [
                        'filename' => $file,
                        'thumb' => $relativeThumbPath,
                        'full' => $relativePath,
                        'fullPath' => $filePath, // Keep absolute path for file operations
                        'size' => filesize($filePath),
                        'modified' => filemtime($filePath),
                        'isVideo' => $isVideo
                    ];
                } else {
                    // Fallback: check MIME type for unknown extensions
                    $mimeType = mime_content_type($filePath);
                    if ($mimeType && (str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/'))) {
                        $isVideo = str_starts_with($mimeType, 'video/');
                        $thumbFile = 'thumb_' . $file;
                        $thumbPath = $datePath . '/' . $thumbFile;
                        
                        // Store paths relative to root for display (absolute path from root)
                        // Convert ../uploads/2024-01-01/file.mov to /uploads/2024-01-01/file.mov
                        $relativePath = '/' . str_replace('../', '', $filePath);
                        $relativeThumbPath = file_exists($thumbPath) ? '/' . str_replace('../', '', $thumbPath) : ($isVideo ? null : $relativePath);
                        
                        $datePhotos[] = [
                            'filename' => $file,
                            'thumb' => $relativeThumbPath,
                            'full' => $relativePath,
                            'fullPath' => $filePath, // Keep absolute path for file operations
                            'size' => filesize($filePath),
                            'modified' => filemtime($filePath),
                            'isVideo' => $isVideo
                        ];
                    }
                }
            }
            
            if (!empty($datePhotos)) {
                // Sort photos by modification time (newest first)
                usort($datePhotos, function($a, $b) {
                    return $b['modified'] - $a['modified'];
                });
                
                $photos[$date] = $datePhotos;
            }
        }
    }
    
    return $photos;
}

$photosByDate = getPhotosByDate();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Bedburger Narrenzunft</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'narrenzunft': '#c4000c'
                    },
                    animation: {
                        'bounce-slow': 'bounce 2s infinite',
                        'pulse-slow': 'pulse 3s infinite',
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'scale-in': 'scaleIn 0.2s ease-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        scaleIn: {
                            '0%': { transform: 'scale(0.9)', opacity: '0' },
                            '100%': { transform: 'scale(1)', opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .photo-card {
            transition: all 0.3s ease;
        }
        .photo-card:hover {
            transform: translateY(-2px);
        }
        .touch-button {
            min-height: 44px;
            min-width: 44px;
        }
        @media (max-width: 768px) {
            .mobile-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
        }
        @media (max-width: 480px) {
            .mobile-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php if (!$isLoggedIn): ?>
        <!-- Login Form -->
        <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 p-4">
            <div class="w-full max-w-sm bg-white rounded-2xl shadow-xl p-6 animate-fade-in">
                <div class="text-center mb-6">
                    <div class="bg-narrenzunft text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 animate-pulse-slow">
                        <i class="fas fa-lock text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Admin Login</h1>
                    <p class="text-gray-600 mt-1 text-sm">Bedburger Narrenzunft</p>
                </div>
                
                <?php if (isset($loginError)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 animate-slide-up">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span class="text-sm"><?php echo htmlspecialchars($loginError); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Passwort</label>
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-narrenzunft focus:border-transparent touch-button">
                    </div>
                    <button type="submit" 
                            class="w-full bg-narrenzunft text-white py-3 px-6 rounded-lg hover:bg-red-800 transition-colors duration-300 font-medium touch-button">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Anmelden
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Admin Dashboard -->
        <header class="bg-narrenzunft text-white shadow-lg sticky top-0 z-40">
            <div class="container mx-auto px-4 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <img src="../assets/bnz-logo-300.png" alt="BNZ Logo" class="h-8 mr-3">
                        <div>
                            <h1 class="text-xl font-bold">Admin Galerie</h1>
                            <p class="text-red-100 text-sm">Bedburger Narrenzunft</p>
                        </div>
                    </div>
                    <a href="?logout=1" class="bg-red-800 hover:bg-red-900 px-3 py-2 rounded-lg transition-colors duration-300 touch-button text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i>
                        Abmelden
                    </a>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-4 py-4">
            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-lg p-4 animate-scale-in">
                    <div class="flex items-center">
                        <div class="bg-blue-100 rounded-full p-2 mr-3">
                            <i class="fas fa-calendar text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600">Tage</p>
                            <p class="text-lg font-bold text-gray-800"><?php echo count($photosByDate); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-4 animate-scale-in" style="animation-delay: 0.1s">
                    <div class="flex items-center">
                        <div class="bg-green-100 rounded-full p-2 mr-3">
                            <i class="fas fa-images text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600">Fotos & Videos</p>
                            <p class="text-lg font-bold text-gray-800">
                                <?php echo array_sum(array_map('count', $photosByDate)); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-4 animate-scale-in" style="animation-delay: 0.2s">
                    <div class="flex items-center">
                        <div class="bg-purple-100 rounded-full p-2 mr-3">
                            <i class="fas fa-hdd text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600">Speicher</p>
                            <p class="text-lg font-bold text-gray-800">
                                <?php 
                                $totalSize = 0;
                                foreach ($photosByDate as $photos) {
                                    foreach ($photos as $photo) {
                                        $totalSize += $photo['size'];
                                    }
                                }
                                echo number_format($totalSize / (1024 * 1024), 1) . ' MB';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photo Gallery -->
            <?php if (empty($photosByDate)): ?>
                <div class="bg-white rounded-xl shadow-lg p-8 text-center animate-fade-in">
                    <i class="fas fa-images text-4xl text-gray-300 mb-3"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Noch keine Fotos</h3>
                    <p class="text-gray-500 text-sm">Es wurden noch keine Fotos hochgeladen.</p>
                </div>
            <?php else: ?>
                <?php foreach ($photosByDate as $date => $photos): ?>
                    <div class="bg-white rounded-xl shadow-lg p-4 mb-4 animate-slide-up">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-800">
                                <i class="fas fa-calendar-day text-narrenzunft mr-2"></i>
                                <?php echo date('d.m.Y', strtotime($date)); ?>
                            </h2>
                            <div class="flex items-center space-x-2">
                                <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-xs">
                                    <?php echo count($photos); ?> Datei(en)
                                </span>
                                <button onclick="downloadAllPhotos('<?php echo $date; ?>')" 
                                        class="bg-narrenzunft text-white px-3 py-2 rounded-lg hover:bg-red-800 transition-colors duration-300 touch-button text-sm">
                                    <i class="fas fa-download mr-1"></i>
                                    Alle
                                </button>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 mobile-grid">
                            <?php foreach ($photos as $photo): ?>
                                <div class="photo-card group relative bg-gray-100 rounded-lg overflow-hidden">
                                    <?php if ($photo['isVideo'] ?? false): ?>
                                        <div class="w-full h-24 sm:h-28 bg-gray-800 flex items-center justify-center cursor-pointer relative" onclick="event.stopPropagation(); openLightbox('<?php echo htmlspecialchars($photo['full']); ?>')">
                                            <!-- Play button - shown by default, hidden on hover -->
                                            <div class="absolute inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 group-hover:opacity-0 transition-opacity duration-300 z-10 pointer-events-none">
                                                <i class="fas fa-play-circle text-white text-4xl"></i>
                                            </div>
                                            <!-- Filename - shown at bottom -->
                                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-1 z-10 pointer-events-none">
                                                <p class="text-white text-xs truncate px-1"><?php echo htmlspecialchars($photo['filename']); ?></p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($photo['thumb'] ?? '../uploads/placeholder.jpg'); ?>" 
                                             alt="Foto" 
                                             class="w-full h-24 sm:h-28 object-cover cursor-pointer"
                                             onclick="openLightbox('<?php echo htmlspecialchars($photo['full']); ?>')"
                                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect fill=\'%23ddd\' width=\'100\' height=\'100\'/%3E%3C/svg%3E'">
                                    <?php endif; ?>
                                    
                                    <!-- Desktop hover buttons - hidden on mobile, shown on hover -->
                                    <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 hidden sm:flex z-20">
                                        <div class="flex space-x-1">
                                            <button onclick="event.stopPropagation(); openLightbox('<?php echo htmlspecialchars($photo['full']); ?>')"
                                                    class="bg-white text-gray-800 p-2 rounded-full hover:bg-gray-100 transition-colors duration-300 touch-button"
                                                    title="Ansehen">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <button onclick="event.stopPropagation(); downloadPhoto('<?php echo htmlspecialchars($photo['full'] ?? $photo['fullPath'] ?? ''); ?>', '<?php echo htmlspecialchars($photo['filename']); ?>')"
                                                    class="bg-narrenzunft text-white p-2 rounded-full hover:bg-red-800 transition-colors duration-300 touch-button"
                                                    title="Download">
                                                <i class="fas fa-download text-xs"></i>
                                            </button>
                                            <button onclick="event.stopPropagation(); deletePhoto('<?php echo htmlspecialchars($photo['fullPath'] ?? $photo['full']); ?>', '<?php echo htmlspecialchars($photo['filename']); ?>', this)"
                                                    class="bg-red-600 text-white p-2 rounded-full hover:bg-red-700 transition-colors duration-300 touch-button"
                                                    title="Löschen">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Mobile touch buttons - always visible on mobile -->
                                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-2 sm:hidden z-20">
                                        <div class="flex justify-center space-x-1">
                                            <button onclick="event.stopPropagation(); openLightbox('<?php echo htmlspecialchars($photo['full']); ?>')"
                                                    class="bg-white bg-opacity-90 text-gray-800 p-2 rounded-full touch-button min-w-[44px] min-h-[44px] flex items-center justify-center"
                                                    title="Ansehen">
                                                <i class="fas fa-eye text-sm"></i>
                                            </button>
                                            <button onclick="event.stopPropagation(); downloadPhoto('<?php echo htmlspecialchars($photo['full'] ?? $photo['fullPath'] ?? ''); ?>', '<?php echo htmlspecialchars($photo['filename']); ?>')"
                                                    class="bg-narrenzunft bg-opacity-90 text-white p-2 rounded-full touch-button min-w-[44px] min-h-[44px] flex items-center justify-center"
                                                    title="Download">
                                                <i class="fas fa-download text-sm"></i>
                                            </button>
                                            <button onclick="event.stopPropagation(); deletePhoto('<?php echo htmlspecialchars($photo['fullPath'] ?? $photo['full']); ?>', '<?php echo htmlspecialchars($photo['filename']); ?>', this)"
                                                    class="bg-red-600 bg-opacity-90 text-white p-2 rounded-full touch-button min-w-[44px] min-h-[44px] flex items-center justify-center"
                                                    title="Löschen">
                                                <i class="fas fa-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <!-- Lightbox Modal -->
        <div id="lightbox" class="fixed inset-0 bg-black bg-opacity-95 z-50 hidden flex items-center justify-center p-4">
            <div class="relative max-w-4xl max-h-full">
                <button onclick="closeLightbox()" 
                        class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300 transition-colors duration-300 z-10 touch-button">
                    <i class="fas fa-times"></i>
                </button>
                <img id="lightboxImage" src="" alt="Foto" class="max-w-full max-h-full object-contain rounded-lg hidden">
                <video id="lightboxVideo" controls preload="metadata" class="max-w-full max-h-full object-contain rounded-lg hidden" style="max-height: 90vh;">
                    Dein Browser unterstützt das Video-Tag nicht.
                </video>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Lightbox functionality
        function openLightbox(mediaSrc) {
            console.log('Opening lightbox with:', mediaSrc);
            const isVideo = /\.(mp4|mov|avi|webm|mpeg|mpg|m4v|3gp)$/i.test(mediaSrc);
            const imageEl = document.getElementById('lightboxImage');
            const videoEl = document.getElementById('lightboxVideo');
            
            if (isVideo) {
                // Hide image, show video
                imageEl.classList.add('hidden');
                videoEl.classList.remove('hidden');
                
                // Clear previous source and set new one
                videoEl.pause();
                videoEl.src = '';
                videoEl.load();
                
                // Set video source directly (browser will determine type)
                videoEl.src = mediaSrc;
                
                // Load the video
                videoEl.load();
                
                // Try to play, handle autoplay restrictions
                const playPromise = videoEl.play();
                if (playPromise !== undefined) {
                    playPromise.catch(error => {
                        console.log('Autoplay prevented:', error);
                        // User will need to click play button
                    });
                }
            } else {
                // Hide video, show image
                videoEl.classList.add('hidden');
                videoEl.pause();
                videoEl.src = '';
                imageEl.classList.remove('hidden');
                imageEl.src = mediaSrc;
            }
            
            document.getElementById('lightbox').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        // Helper function to determine video MIME type from extension
        function getVideoType(src) {
            const ext = src.toLowerCase().split('.').pop();
            const types = {
                'mp4': 'video/mp4',
                'mov': 'video/quicktime',
                'avi': 'video/x-msvideo',
                'webm': 'video/webm',
                'mpeg': 'video/mpeg',
                'mpg': 'video/mpeg',
                'm4v': 'video/mp4',
                '3gp': 'video/3gpp'
            };
            return types[ext] || 'video/mp4';
        }

        function closeLightbox() {
            const videoEl = document.getElementById('lightboxVideo');
            videoEl.pause();
            videoEl.src = '';
            videoEl.load(); // Reset video element
            document.getElementById('lightbox').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close lightbox on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });

        // Close lightbox on background click
        document.getElementById('lightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });

        // Download single photo
        function downloadPhoto(imageSrc, filename) {
            const link = document.createElement('a');
            link.href = imageSrc;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Download all photos for a specific date
        function downloadAllPhotos(date) {
            const link = document.createElement('a');
            link.href = '../download.php?date=' + date;
            link.download = 'fotos_' + date + '.zip';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Delete photo/video
        function deletePhoto(filePath, filename, buttonElement) {
            if (!confirm('Möchtest du "' + filename + '" wirklich löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden.')) {
                return;
            }

            // Disable button during deletion
            const originalButton = buttonElement;
            originalButton.disabled = true;
            originalButton.style.opacity = '0.5';

            // Create form data
            const formData = new FormData();
            formData.append('delete_file', '1');
            formData.append('file_path', filePath);

            // Send delete request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Find and remove the photo card
                    const photoCard = originalButton.closest('.photo-card');
                    if (photoCard) {
                        // Animate out
                        photoCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        photoCard.style.opacity = '0';
                        photoCard.style.transform = 'scale(0.8)';
                        
                        setTimeout(() => {
                            photoCard.remove();
                            
                            // Reload page after a short delay to update stats
                            setTimeout(() => {
                                window.location.reload();
                            }, 300);
                        }, 300);
                    } else {
                        // Fallback: reload page
                        window.location.reload();
                    }
                } else {
                    alert('Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'));
                    originalButton.disabled = false;
                    originalButton.style.opacity = '1';
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert('Fehler beim Löschen der Datei.');
                originalButton.disabled = false;
                originalButton.style.opacity = '1';
            });
        }
    </script>
</body>
</html>

