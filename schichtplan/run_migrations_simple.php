<?php
/**
 * Einfaches Migration-Script
 * Führt SQL-Dateien direkt aus
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Migration</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        pre { background: #fff; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Schichtplan Migration</h1>

<?php
// Verwende mysql-Kommandozeile direkt
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

// Migration 1
echo "<h2>1. Haupt-Migration (migration.sql)</h2>";
$migration1_file = __DIR__ . '/migration.sql';
if (file_exists($migration1_file)) {
    $cmd = sprintf(
        'mysql -h %s -u %s -p%s %s < %s 2>&1',
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($dbname),
        escapeshellarg($migration1_file)
    );
    
    exec($cmd, $output1, $return1);
    
    if ($return1 === 0) {
        echo "<div class='success'>✓ Migration 1 erfolgreich ausgeführt</div>";
    } else {
        $output_str = implode("\n", $output1);
        // Ignoriere "already exists" Fehler
        if (strpos($output_str, 'already exists') !== false || strpos($output_str, 'Duplicate') !== false) {
            echo "<div class='info'>ℹ Migration 1 ausgeführt (einige Objekte existierten bereits - das ist OK)</div>";
        } else {
            echo "<div class='error'>✗ Fehler bei Migration 1:</div>";
            echo "<pre>" . htmlspecialchars($output_str) . "</pre>";
        }
    }
} else {
    echo "<div class='error'>✗ Datei migration.sql nicht gefunden</div>";
}

// Migration 2
echo "<h2>2. Admin-Migration (migration_admin.sql)</h2>";
$migration2_file = __DIR__ . '/migration_admin.sql';
if (file_exists($migration2_file)) {
    $cmd = sprintf(
        'mysql -h %s -u %s -p%s %s < %s 2>&1',
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($dbname),
        escapeshellarg($migration2_file)
    );
    
    exec($cmd, $output2, $return2);
    
    if ($return2 === 0) {
        echo "<div class='success'>✓ Migration 2 erfolgreich ausgeführt</div>";
    } else {
        $output_str = implode("\n", $output2);
        // Ignoriere "already exists" Fehler
        if (strpos($output_str, 'already exists') !== false || strpos($output_str, 'Duplicate') !== false) {
            echo "<div class='info'>ℹ Migration 2 ausgeführt (einige Objekte existierten bereits - das ist OK)</div>";
        } else {
            echo "<div class='error'>✗ Fehler bei Migration 2:</div>";
            echo "<pre>" . htmlspecialchars($output_str) . "</pre>";
        }
    }
} else {
    echo "<div class='error'>✗ Datei migration_admin.sql nicht gefunden</div>";
}

// Migration 3: Team-Zeitfenster (team_slots)
echo "<h2>3. Team-Zeitfenster (migration_shift_team_slots.sql)</h2>";
$migration3_file = __DIR__ . '/migration_shift_team_slots.sql';
if (file_exists($migration3_file)) {
    $cmd = sprintf(
        'mysql -h %s -u %s -p%s %s < %s 2>&1',
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($dbname),
        escapeshellarg($migration3_file)
    );

    exec($cmd, $output3, $return3);

    if ($return3 === 0) {
        echo "<div class='success'>✓ Migration 3 erfolgreich ausgeführt</div>";
    } else {
        $output_str = implode("\n", $output3);
        if (strpos($output_str, 'Duplicate column name') !== false || strpos($output_str, 'already exists') !== false) {
            echo "<div class='info'>ℹ Migration 3: Spalte team_slots existiert vermutlich bereits (OK)</div>";
        } else {
            echo "<div class='error'>✗ Fehler bei Migration 3:</div>";
            echo "<pre>" . htmlspecialchars($output_str) . "</pre>";
        }
    }
} else {
    echo "<div class='error'>✗ Datei migration_shift_team_slots.sql nicht gefunden</div>";
}

// Überprüfung
echo "<h2>4. Überprüfung</h2>";
try {
    $db = getDbConnection();
    if ($db) {
        $tables = ['groups', 'task_areas', 'employees', 'shifts', 'shift_assignments', 'admins', 'admin_sessions'];
        $existing = [];
        $missing = [];
        
        foreach ($tables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existing[] = $table;
            } else {
                $missing[] = $table;
            }
        }
        
        if (empty($missing)) {
            echo "<div class='success'>✓ Alle Tabellen vorhanden: " . implode(', ', $existing) . "</div>";
        } else {
            echo "<div class='error'>✗ Fehlende Tabellen: " . implode(', ', $missing) . "</div>";
            if (!empty($existing)) {
                echo "<div class='info'>Vorhandene Tabellen: " . implode(', ', $existing) . "</div>";
            }
        }
        
        // Prüfe ob Admin vorhanden
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM admins");
            $adminCount = $stmt->fetch()['count'];
            if ($adminCount > 0) {
                echo "<div class='success'>✓ Admin-Benutzer vorhanden ($adminCount)</div>";
                echo "<div class='info'>Standard-Login: Benutzername='admin', Passwort='admin123'</div>";
            } else {
                echo "<div class='error'>✗ Kein Admin-Benutzer gefunden</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Fehler beim Prüfen der Admins: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='error'>✗ Datenbankverbindung fehlgeschlagen</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Fehler bei Überprüfung: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

    <h2>Fertig!</h2>
    <div class='info'>
        <p><strong>Nächste Schritte:</strong></p>
        <ul>
            <li>Gehe zu <a href='admin_login.php'>admin_login.php</a> um dich anzumelden</li>
            <li>Standard-Login: admin / admin123</li>
            <li><strong>WICHTIG:</strong> Ändere das Passwort nach dem ersten Login!</li>
            <li>Diese Datei kann nach erfolgreicher Migration gelöscht werden</li>
        </ul>
    </div>
</body>
</html>

