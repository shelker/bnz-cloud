<?php
// QR Code Generator für Bedburger Narrenzunft
// Einfaches Script zum Generieren von QR-Codes für den Foto-Upload-Link

$baseUrl = 'https://deine-domain.de/'; // Hier deine Domain eintragen
$adminUrl = $baseUrl . 'admin/';
$uploadUrl = $baseUrl . 'index.php';

// QR Code über Google Charts API generieren (einfachste Lösung)
$qrSize = 300;
$qrUrl = "https://chart.googleapis.com/chart?chs={$qrSize}x{$qrSize}&cht=qr&chl=" . urlencode($uploadUrl);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - Bedburger Narrenzunft</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'narrenzunft': '#c4000c'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-xl shadow-xl p-8 text-center">
                <div class="mb-6">
                    <div class="bg-narrenzunft text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-qrcode text-2xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">QR Code Generator</h1>
                    <p class="text-gray-600">Bedburger Narrenzunft</p>
                </div>
                
                <div class="mb-6">
                    <img src="<?php echo htmlspecialchars($qrUrl); ?>" 
                         alt="QR Code für Foto-Upload" 
                         class="mx-auto border-4 border-gray-200 rounded-lg">
                </div>
                
                <div class="mb-6">
                    <p class="text-sm text-gray-600 mb-2">Upload-Link:</p>
                    <div class="bg-gray-100 rounded-lg p-3 mb-3">
                        <code class="text-sm text-gray-800 break-all"><?php echo htmlspecialchars($uploadUrl); ?></code>
                    </div>
                    <p class="text-sm text-gray-600 mb-2">Admin-Link:</p>
                    <div class="bg-gray-100 rounded-lg p-3">
                        <code class="text-sm text-gray-800 break-all"><?php echo htmlspecialchars($adminUrl); ?></code>
                    </div>
                </div>
                
                <div class="flex gap-4 justify-center">
                    <button onclick="downloadQR()" 
                            class="bg-narrenzunft text-white px-6 py-3 rounded-lg hover:bg-red-800 transition-colors duration-300">
                        <i class="fas fa-download mr-2"></i>
                        QR Code herunterladen
                    </button>
                    <button onclick="copyLink()" 
                            class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors duration-300">
                        <i class="fas fa-copy mr-2"></i>
                        Link kopieren
                    </button>
                </div>
                
                <div class="mt-8 text-sm text-gray-500">
                    <p><i class="fas fa-info-circle mr-1"></i> 
                    Scanne den QR-Code mit deinem Smartphone, um Fotos hochzuladen</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-8 bg-gray-800 text-white py-4">
        <div class="container mx-auto px-4 text-center">
            <p class="text-xs">&copy; <span id="currentYear"><?php echo date('Y'); ?></span> Bedburger Narrenzunft. Alle Rechte vorbehalten.</p>
        </div>
    </footer>

    <script>
        function downloadQR() {
            const qrImage = document.querySelector('img');
            const link = document.createElement('a');
            link.href = qrImage.src;
            link.download = 'bedburger-narrenzunft-qr-code.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function copyLink() {
            const linkText = '<?php echo addslashes($uploadUrl); ?>';
            navigator.clipboard.writeText(linkText).then(() => {
                // Show success message
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check mr-2"></i>Kopiert!';
                button.classList.remove('bg-gray-600', 'hover:bg-gray-700');
                button.classList.add('bg-green-600', 'hover:bg-green-700');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('bg-green-600', 'hover:bg-green-700');
                    button.classList.add('bg-gray-600', 'hover:bg-gray-700');
                }, 2000);
            });
        }

        // Update year in footer
        document.addEventListener('DOMContentLoaded', () => {
            const yearElement = document.getElementById('currentYear');
            if (yearElement) {
                yearElement.textContent = new Date().getFullYear();
            }
        });
    </script>
</body>
</html>
