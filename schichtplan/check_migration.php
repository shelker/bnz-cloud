<?php
/**
 * Überprüft ob alle Migrationen erfolgreich waren
 */
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Migration Status</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; background: white; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #c4000c; color: white; }
    </style>
</head>
<body>
    <h1>Migration Status Check</h1>

<?php
$db = getDbConnection();
if (!$db) {
    echo "<div class='error'>FEHLER: Datenbankverbindung fehlgeschlagen!</div>";
    exit;
}

echo "<div class='success'>✓ Datenbankverbindung erfolgreich</div>";

// Prüfe Tabellen
$required_tables = [
    'groups' => 'Gruppen/Abteilungen',
    'task_areas' => 'Aufgabenbereiche',
    'employees' => 'Mitarbeiter',
    'shifts' => 'Schichten',
    'shift_assignments' => 'Schichtzuweisungen',
    'admins' => 'Administratoren',
    'admin_sessions' => 'Admin-Sessions'
];

echo "<h2>Tabellen-Status</h2>";
echo "<table><tr><th>Tabelle</th><th>Beschreibung</th><th>Status</th></tr>";

$all_ok = true;
foreach ($required_tables as $table => $desc) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            // Prüfe Anzahl Einträge
            $count_stmt = $db->query("SELECT COUNT(*) as cnt FROM `$table`");
            $count = $count_stmt->fetch()['cnt'];
            echo "<tr><td><strong>$table</strong></td><td>$desc</td><td class='success'>✓ Vorhanden ($count Einträge)</td></tr>";
        } else {
            echo "<tr><td><strong>$table</strong></td><td>$desc</td><td class='error'>✗ Fehlt</td></tr>";
            $all_ok = false;
        }
    } catch (Exception $e) {
        echo "<tr><td><strong>$table</strong></td><td>$desc</td><td class='error'>✗ Fehler: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
        $all_ok = false;
    }
}
echo "</table>";

// Spalte team_slots (optional)
echo "<h2>Schichten: Spalte team_slots</h2>";
try {
    $stmt = $db->query("SHOW COLUMNS FROM shifts LIKE 'team_slots'");
    if ($stmt && $stmt->rowCount() > 0) {
        echo "<div class='success'>✓ Spalte <code>shifts.team_slots</code> vorhanden (Team-Zeitfenster)</div>";
    } else {
        echo "<div class='info'>ℹ Spalte <code>shifts.team_slots</code> fehlt — bitte <code>migration_shift_team_slots.sql</code> ausführen</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Konnte shifts nicht prüfen: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Prüfe Admin
echo "<h2>Admin-Status</h2>";
try {
    $stmt = $db->query("SELECT id, benutzername, email, aktiv, zuletzt_angemeldet FROM admins");
    $admins = $stmt->fetchAll();
    
    if (empty($admins)) {
        echo "<div class='error'>✗ Keine Administratoren gefunden</div>";
        echo "<div class='info'>Führe migration_admin.sql aus, um einen Standard-Admin zu erstellen.</div>";
    } else {
        echo "<div class='success'>✓ " . count($admins) . " Administrator(en) gefunden:</div>";
        echo "<table><tr><th>ID</th><th>Benutzername</th><th>Email</th><th>Aktiv</th><th>Zuletzt angemeldet</th></tr>";
        foreach ($admins as $admin) {
            $aktiv = $admin['aktiv'] ? '✓ Ja' : '✗ Nein';
            $last_login = $admin['zuletzt_angemeldet'] ? date('d.m.Y H:i', strtotime($admin['zuletzt_angemeldet'])) : 'Nie';
            echo "<tr>";
            echo "<td>{$admin['id']}</td>";
            echo "<td><strong>{$admin['benutzername']}</strong></td>";
            echo "<td>" . ($admin['email'] ?: '-') . "</td>";
            echo "<td>$aktiv</td>";
            echo "<td>$last_login</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Fehler beim Prüfen der Admins: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Zusammenfassung
echo "<h2>Zusammenfassung</h2>";
if ($all_ok && !empty($admins)) {
    echo "<div class='success'>";
    echo "<h3>✓ Alle Migrationen erfolgreich!</h3>";
    echo "<p>Das System ist bereit zur Verwendung.</p>";
    echo "<ul>";
    echo "<li><a href='admin_login.php'>Admin-Login</a> (Standard: admin / admin123)</li>";
    echo "<li><a href='index.php'>Schichtplan anzeigen</a></li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>✗ Migration noch nicht vollständig</h3>";
    echo "<p>Bitte führe die Migrationen aus:</p>";
    echo "<ul>";
    echo "<li>migration.sql</li>";
    echo "<li>migration_admin.sql</li>";
    echo "</ul>";
    echo "</div>";
}
?>

</body>
</html>

