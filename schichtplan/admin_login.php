<?php
require_once 'config.php';

// Session starten (falls noch nicht gestartet)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wenn bereits eingeloggt, weiterleiten
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit;
}

$error = '';
$success = '';

// Login verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = $_POST['benutzername'] ?? '';
    $passwort = $_POST['passwort'] ?? '';
    
    if (empty($benutzername) || empty($passwort)) {
        $error = 'Bitte geben Sie Benutzername und Passwort ein.';
    } else {
        $admin = adminLogin($benutzername, $passwort);
        if ($admin) {
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Ungültiger Benutzername oder Passwort.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex, nofollow">
    <title>Admin Login - Schichtplan</title>
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
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 sm:p-8">
        <div class="text-center mb-6">
            <img src="../assets/bnz-logo-300.png" alt="BNZ Logo" class="h-16 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-gray-800">Admin Login</h1>
            <p class="text-gray-600 text-sm mt-2">Schichtplan-Verwaltung</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-2"></i>Benutzername
                </label>
                <input type="text" name="benutzername" required autofocus
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-narrenzunft focus:border-transparent"
                       placeholder="Benutzername eingeben">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-2"></i>Passwort
                </label>
                <input type="password" name="passwort" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-narrenzunft focus:border-transparent"
                       placeholder="Passwort eingeben">
            </div>
            
            <button type="submit" 
                    class="w-full bg-narrenzunft text-white py-3 rounded-lg hover:bg-red-800 active:bg-red-900 transition-colors font-medium touch-button text-base">
                <i class="fas fa-sign-in-alt mr-2"></i>Anmelden
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="index.php" class="text-sm text-gray-600 hover:text-narrenzunft">
                <i class="fas fa-arrow-left mr-1"></i>Zurück zum Schichtplan
            </a>
        </div>
    </div>
</body>
</html>

