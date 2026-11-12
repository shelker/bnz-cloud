<?php
require_once 'config.php';

// Logout verarbeiten BEVOR Admin-Check
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: admin_login.php');
    exit;
}

// Admin-Check
$admin = checkAdminAuth();
if (!$admin) {
    header('Location: admin_login.php');
    exit;
}

$db = getDbConnection();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex, nofollow">
    <title>Admin - Schichtplan</title>
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
    <style>
        .touch-button {
            min-height: 44px;
            min-width: 44px;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-narrenzunft text-white shadow-lg">
        <div class="container mx-auto px-3 sm:px-4 py-3 sm:py-4">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center min-w-0 flex-1">
                    <img src="../assets/bnz-logo-300.png" alt="BNZ Logo" class="h-8 sm:h-12 mr-2 sm:mr-3 flex-shrink-0">
                    <h1 class="text-lg sm:text-2xl font-bold truncate">Admin-Bereich</h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-4 flex-wrap">
                    <span class="text-xs sm:text-sm">Eingeloggt: <strong><?php echo htmlspecialchars($admin['benutzername']); ?></strong></span>
                    <a href="?logout=1" class="bg-red-800 hover:bg-red-900 active:bg-red-950 px-3 sm:px-4 py-2 rounded-lg text-xs sm:text-sm transition-colors touch-button">
                        <i class="fas fa-sign-out-alt sm:mr-2"></i>
                        <span class="hidden sm:inline">Abmelden</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-3 sm:px-4 py-4 sm:py-6">
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-4 sm:mb-6">
            <h2 class="text-lg sm:text-xl font-bold mb-3 sm:mb-4">Willkommen im Admin-Bereich</h2>
            <p class="text-sm sm:text-base text-gray-600 mb-4">
                Sie können hier Eintragungen löschen und das Schichtplan-System verwalten.
            </p>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <a href="index.php" class="bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg p-4 transition-colors">
                    <i class="fas fa-calendar-alt text-blue-600 text-2xl mb-2"></i>
                    <h3 class="font-bold text-gray-800">Zum Schichtplan</h3>
                    <p class="text-sm text-gray-600">Schichtplan anzeigen</p>
                </a>
                
                <a href="admin_eintragungen.php" class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg p-4 transition-colors">
                    <i class="fas fa-list text-green-600 text-2xl mb-2"></i>
                    <h3 class="font-bold text-gray-800">Eintragungen verwalten</h3>
                    <p class="text-sm text-gray-600">Alle Eintragungen anzeigen und löschen</p>
                </a>
                <a href="admin_schicht_teams.php" class="bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg p-4 transition-colors">
                    <i class="fas fa-users text-amber-700 text-2xl mb-2"></i>
                    <h3 class="font-bold text-gray-800">Team-Zeitfenster</h3>
                    <p class="text-sm text-gray-600">Schichten mit mehreren Team-Zeitslots (z.&nbsp;B. Wechselschicht)</p>
                </a>
            </div>
        </div>
    </main>
</body>
</html>

