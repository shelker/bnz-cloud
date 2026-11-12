<?php
session_start();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex, nofollow">
    <title>Bedburger Narrenzunft - Foto Upload</title>
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
                        'scale-in': 'scaleIn 0.2s ease-out',
                        'confetti': 'confetti 2s ease-out',
                        'success-bounce': 'successBounce 0.6s ease-out'
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
                        },
                        confetti: {
                            '0%': { transform: 'translateY(0) rotate(0deg)', opacity: '1' },
                            '100%': { transform: 'translateY(-100px) rotate(360deg)', opacity: '0' }
                        },
                        successBounce: {
                            '0%': { transform: 'scale(1)' },
                            '50%': { transform: 'scale(1.1)' },
                            '100%': { transform: 'scale(1)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .upload-area {
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(196, 0, 12, 0.1);
        }
        .touch-button {
            min-height: 44px;
            min-width: 44px;
        }
        .confetti {
            position: fixed;
            width: 8px;
            height: 8px;
            background: #c4000c;
            pointer-events: none;
            z-index: 9999;
            border-radius: 2px;
        }
        @media (max-width: 768px) {
            .mobile-padding {
                padding: 1rem;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-narrenzunft text-white shadow-lg">
        <div class="container mx-auto px-4 py-2 mobile-padding">
            <div class="flex items-center justify-center">
                <img src="assets/bnz-logo-300.png" alt="BNZ Logo" class="h-8 sm:h-12 mr-2 sm:mr-3 animate-bounce-slow">
                <h1 class="text-xl sm:text-3xl font-bold">Bedburger Narrenzunft</h1>
            </div>
            <p class="text-center mt-1 sm:mt-2 text-red-100 text-sm sm:text-base">Teile deine Karnevalsmomente mit uns!</p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-4 sm:py-4 mobile-padding">
        <!-- Upload Section -->
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-xl shadow-xl p-4 sm:p-4 animate-fade-in relative">
                <div class="text-center mb-6 sm:mb-8 hidden">
                    <h2 class="text-lg sm:text-2xl font-bold text-gray-800 mb-2">Foto hochladen</h2>
                    <p class="text-gray-600 text-sm sm:text-base">Wähle Fotos aus deiner Bibliothek oder nimm neue auf</p>
                </div>

                <!-- Upload Area -->
                <div id="uploadArea" class="upload-area border-2 border-dashed border-gray-300 rounded-lg p-6 sm:p-8 text-center hover:border-narrenzunft transition-colors duration-300 cursor-pointer">
                    <div id="uploadContent">
                        <i class="fas fa-cloud-upload-alt text-4xl sm:text-6xl text-gray-400 mb-3 sm:mb-4 animate-pulse-slow"></i>
                        <p class="text-lg sm:text-xl text-gray-600 mb-2">Fotos & Videos hierher ziehen oder klicken</p>
                        <p class="text-xs sm:text-sm text-gray-500">Unterstützt JPG, PNG, GIF, MP4, MOV (max. 200MB pro Datei)</p>
                    </div>
                </div>

                <!-- Camera/Bibliothek Buttons -->
                <div class="flex gap-3 sm:gap-4 mt-4 sm:mt-6">
                    <button id="cameraBtn" class="flex-1 bg-narrenzunft text-white py-3 px-4 sm:px-6 rounded-lg hover:bg-red-800 transition-colors duration-300 flex items-center justify-center touch-button">
                        <i class="fas fa-camera mr-2"></i>
                        <span class="text-sm sm:text-base">Kamera</span>
                    </button>
                    <button id="galleryBtn" class="flex-1 bg-gray-600 text-white py-3 px-4 sm:px-6 rounded-lg hover:bg-gray-700 transition-colors duration-300 flex items-center justify-center touch-button">
                        <i class="fas fa-images mr-2"></i>
                        <span class="text-sm sm:text-base">Bibliothek</span>
                    </button>
                </div>

                <!-- Consent Text -->
                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mr-2 mt-0.5 text-sm"></i>
                        <p class="text-xs text-blue-800 leading-relaxed">
                            <strong>Einverständnis:</strong> Mit dem Hochladen der Fotos und Videos erklärst du dich einverstanden, dass die Bedburger Narrenzunft diese für Werbezwecke, die Vereinswebseite, Social Media und andere Vereinsaktivitäten im Rahmen der BNZ verwenden darf.
                        </p>
                    </div>
                </div>

                <!-- Hidden File Input -->
                <input type="file" id="fileInput" multiple accept="image/*,video/*" class="hidden">
                <input type="file" id="cameraInput" accept="image/*,video/*" capture="camera" class="hidden">

                <!-- Upload Progress -->
                <div id="uploadProgress" class="hidden mt-4 sm:mt-6">
                    <div class="bg-gray-200 rounded-full h-2 sm:h-3 mb-2">
                        <div id="progressBar" class="bg-narrenzunft h-2 sm:h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <p id="progressText" class="text-center text-xs sm:text-sm text-gray-600">Upload läuft...</p>
                </div>

                <!-- Success Overlay -->
                <div id="successOverlay" class="hidden absolute inset-0 bg-green-500 bg-opacity-95 rounded-xl flex items-center justify-center z-10 animate-success-bounce">
                    <div class="text-center text-white">
                        <i class="fas fa-check-circle text-6xl sm:text-8xl mb-4 animate-pulse"></i>
                        <h3 class="text-xl sm:text-3xl font-bold mb-2">Erfolgreich hochgeladen!</h3>
                        <p class="text-sm sm:text-lg text-green-100">Vielen Dank für dein Engagement!</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="mt-8 sm:mt-16 bg-gray-800 text-white py-4 sm:py-8">
        <div class="container mx-auto px-4 text-center">
            <p class="text-xs sm:text-sm">&copy; <span id="currentYear"><?php echo date('Y'); ?></span> Bedburger Narrenzunft. Alle Rechte vorbehalten.</p>
        </div>
    </footer>

    <script src="js/upload.js"></script>
</body>
</html>
