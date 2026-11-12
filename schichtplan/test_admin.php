<?php
/**
 * Test-Script für Admin-Login
 */
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Test</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        pre { background: #fff; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Admin Login Test</h1>

<?php
$db = getDbConnection();
if (!$db) {
    echo "<div class='error'>FEHLER: Datenbankverbindung fehlgeschlagen!</div>";
    exit;
}

echo "<div class='success'>✓ Datenbankverbindung erfolgreich</div>";

// Prüfe Admin-Benutzer
echo "<h2>Admin-Benutzer in Datenbank:</h2>";
try {
    $stmt = $db->query("SELECT id, benutzername, passwort_hash, aktiv FROM admins");
    $admins = $stmt->fetchAll();
    
    if (empty($admins)) {
        echo "<div class='error'>✗ Keine Admin-Benutzer gefunden!</div>";
        echo "<div class='info'>Führe migration_admin.sql aus, um einen Admin zu erstellen.</div>";
    } else {
        echo "<div class='success'>✓ " . count($admins) . " Admin-Benutzer gefunden:</div>";
        foreach ($admins as $admin) {
            echo "<div class='info'>";
            echo "<strong>ID:</strong> {$admin['id']}<br>";
            echo "<strong>Benutzername:</strong> {$admin['benutzername']}<br>";
            echo "<strong>Aktiv:</strong> " . ($admin['aktiv'] ? 'Ja' : 'Nein') . "<br>";
            echo "<strong>Passwort-Hash:</strong> " . substr($admin['passwort_hash'], 0, 30) . "...<br>";
            echo "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Fehler: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Teste Passwort-Verifizierung
echo "<h2>Passwort-Test:</h2>";
$test_passwords = ['admin123', 'admin', 'password'];
$test_username = 'admin';

try {
    $stmt = $db->prepare("SELECT id, benutzername, passwort_hash FROM admins WHERE benutzername = ?");
    $stmt->execute([$test_username]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<div class='info'>Teste Passwörter für Benutzer '{$admin['benutzername']}':</div>";
        echo "<pre>";
        foreach ($test_passwords as $test_pw) {
            $result = password_verify($test_pw, $admin['passwort_hash']);
            $status = $result ? '✓ KORREKT' : '✗ FALSCH';
            echo "Passwort '$test_pw': $status\n";
        }
        echo "</pre>";
        
        // Zeige Hash-Info
        echo "<div class='info'>";
        echo "<strong>Gespeicherter Hash:</strong> {$admin['passwort_hash']}<br>";
        echo "<strong>Hash-Länge:</strong> " . strlen($admin['passwort_hash']) . " Zeichen<br>";
        echo "<strong>Hash-Info:</strong> ";
        $info = password_get_info($admin['passwort_hash']);
        print_r($info);
        echo "</div>";
    } else {
        echo "<div class='error'>✗ Benutzer '$test_username' nicht gefunden!</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Fehler: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Erstelle neuen Hash für admin123
echo "<h2>Neuer Hash für 'admin123':</h2>";
$new_hash = password_hash('admin123', PASSWORD_DEFAULT);
echo "<div class='info'>";
echo "<strong>Neuer Hash:</strong> <code>$new_hash</code><br>";
echo "<strong>SQL zum Aktualisieren:</strong><br>";
echo "<pre>UPDATE admins SET passwort_hash = '$new_hash' WHERE benutzername = 'admin';</pre>";
echo "</div>";

// Teste Login-Funktion
echo "<h2>Login-Funktion Test:</h2>";
try {
    $result = adminLogin('admin', 'admin123');
    if ($result) {
        echo "<div class='success'>✓ Login erfolgreich!</div>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
    } else {
        echo "<div class='error'>✗ Login fehlgeschlagen!</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Fehler bei Login-Test: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

</body>
</html>

