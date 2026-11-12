<?php
/**
 * Korrigiert das Admin-Passwort
 */
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Passwort korrigieren</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Admin Passwort korrigieren</h1>

<?php
$db = getDbConnection();
if (!$db) {
    echo "<div class='error'>FEHLER: Datenbankverbindung fehlgeschlagen!</div>";
    exit;
}

// Korrekter Hash für 'admin123'
$correct_hash = '$2y$10$nSaDa9q7FbkCYuX.bigaN.WZsufydnYsA7m6JkJs6WwZPI7FMNCtG';

try {
    // Prüfe aktuellen Hash
    $stmt = $db->query("SELECT benutzername, passwort_hash FROM admins WHERE benutzername = 'admin'");
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "<div class='error'>✗ Admin-Benutzer 'admin' nicht gefunden!</div>";
    } else {
        echo "<div class='info'>";
        echo "<strong>Aktueller Hash:</strong> " . substr($admin['passwort_hash'], 0, 30) . "...<br>";
        echo "</div>";
        
        // Teste ob aktuelles Passwort funktioniert
        $current_works = password_verify('admin123', $admin['passwort_hash']);
        
        if ($current_works) {
            echo "<div class='success'>✓ Das Passwort funktioniert bereits! Keine Änderung nötig.</div>";
        } else {
            // Update Passwort
            $stmt = $db->prepare("UPDATE admins SET passwort_hash = ? WHERE benutzername = 'admin'");
            $stmt->execute([$correct_hash]);
            
            echo "<div class='success'>✓ Passwort erfolgreich aktualisiert!</div>";
            
            // Teste neues Passwort
            $test_stmt = $db->query("SELECT passwort_hash FROM admins WHERE benutzername = 'admin'");
            $new_admin = $test_stmt->fetch();
            $test_result = password_verify('admin123', $new_admin['passwort_hash']);
            
            if ($test_result) {
                echo "<div class='success'>✓ Verifizierung erfolgreich! Das Passwort 'admin123' funktioniert jetzt.</div>";
            } else {
                echo "<div class='error'>✗ Verifizierung fehlgeschlagen! Bitte manuell prüfen.</div>";
            }
        }
    }
    
    echo "<div class='info'>";
    echo "<h3>Login-Daten:</h3>";
    echo "<ul>";
    echo "<li><strong>Benutzername:</strong> admin</li>";
    echo "<li><strong>Passwort:</strong> admin123</li>";
    echo "</ul>";
    echo "<p><a href='admin_login.php'>Zum Login</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Fehler: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

</body>
</html>

