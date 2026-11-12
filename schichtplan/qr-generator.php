<?php
// QR Code Generator für Schichtplan-System
// Bedburger Narrenzunft

$baseUrl = 'https://schichtplan.bnz1886.de/';
$adminUrl = $baseUrl . 'admin_login.php';
$schichtplanUrl = $baseUrl;

// QR Code über Google Charts API generieren
$qrSize = 300;
$qrUrl = "https://chart.googleapis.com/chart?chs={$qrSize}x{$qrSize}&cht=qr&chl=" . urlencode($schichtplanUrl);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - Schichtplan BNZ</title>
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
            <div class="bg-white rounded-xl shadow-xl p-6 sm:p-8 text-center">
                <div class="mb-6">
                    <img src="../assets/bnz-logo-300.png" alt="BNZ Logo" class="h-16 mx-auto mb-4">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2">QR Code Generator</h1>
                    <p class="text-gray-600">Schichtplan - Bedburger Narrenzunft</p>
                </div>
                
                <div class="mb-6">
                    <img src="<?php echo htmlspecialchars($qrUrl); ?>" 
                         alt="QR Code für Schichtplan" 
                         class="mx-auto border-4 border-gray-200 rounded-lg">
                </div>
                
                <div class="mb-6 space-y-3">
                    <div>
                        <p class="text-sm text-gray-600 mb-2 font-medium">Schichtplan-Link:</p>
                        <div class="bg-gray-100 rounded-lg p-3">
                            <code class="text-xs sm:text-sm text-gray-800 break-all"><?php echo htmlspecialchars($schichtplanUrl); ?></code>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-2 font-medium">Admin-Link:</p>
                        <div class="bg-gray-100 rounded-lg p-3">
                            <code class="text-xs sm:text-sm text-gray-800 break-all"><?php echo htmlspecialchars($adminUrl); ?></code>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center">
                    <button onclick="downloadQR()" 
                            class="bg-narrenzunft text-white px-6 py-3 rounded-lg hover:bg-red-800 active:bg-red-900 transition-colors touch-button">
                        <i class="fas fa-download mr-2"></i>
                        QR Code herunterladen
                    </button>
                    <button onclick="copyLink('<?php echo addslashes($schichtplanUrl); ?>')" 
                            class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 active:bg-gray-800 transition-colors touch-button">
                        <i class="fas fa-copy mr-2"></i>
                        Link kopieren
                    </button>
                </div>
                
                <div class="mt-6 text-sm text-gray-500">
                    <p><i class="fas fa-info-circle mr-1"></i> 
                    Scanne den QR-Code mit deinem Smartphone, um zum Schichtplan zu gelangen</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadQR() {
            const qrImage = document.querySelector('img[alt="QR Code für Schichtplan"]');
            const link = document.createElement('a');
            link.href = qrImage.src;
            link.download = 'bnz-schichtplan-qr-code.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function copyLink(url) {
            navigator.clipboard.writeText(url).then(() => {
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
            }).catch(() => {
                alert('Link konnte nicht kopiert werden. Bitte manuell kopieren.');
            });
        }
    </script>
</body>
</html>

