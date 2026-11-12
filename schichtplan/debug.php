<?php
/**
 * Debug-Script für Schichtplan
 * Zeigt alle Schichten in der Datenbank an
 */
require_once 'config.php';

$db = getDbConnection();

if (!$db) {
    die("FEHLER: Datenbankverbindung fehlgeschlagen! Bitte config.php überprüfen.");
}

echo "<h1>Schichtplan Debug</h1>";
echo "<style>body { font-family: Arial; margin: 20px; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #c4000c; color: white; }</style>";

// Prüfe Aufgabenbereiche
echo "<h2>Aufgabenbereiche in der Datenbank:</h2>";
$stmt = $db->query("SELECT * FROM task_areas ORDER BY sort_order");
$aufgabenbereiche = $stmt->fetchAll();
if (empty($aufgabenbereiche)) {
    echo "<p style='color: red;'>KEINE AUFGABENBEREICHE GEFUNDEN! Bitte database.sql ausführen.</p>";
} else {
    echo "<table><tr><th>ID</th><th>Name</th><th>Farbe</th><th>Aktiv</th></tr>";
    foreach ($aufgabenbereiche as $aufgabenbereich) {
        echo "<tr><td>{$aufgabenbereich['id']}</td><td>{$aufgabenbereich['name']}</td><td style='background-color: {$aufgabenbereich['color']};'>{$aufgabenbereich['color']}</td><td>{$aufgabenbereich['active']}</td></tr>";
    }
    echo "</table>";
}

// Prüfe Gruppen
echo "<h2>Gruppen in der Datenbank:</h2>";
$stmt = $db->query("SELECT * FROM groups ORDER BY name");
$gruppen = $stmt->fetchAll();
if (empty($gruppen)) {
    echo "<p style='color: red;'>KEINE GRUPPEN GEFUNDEN!</p>";
} else {
    echo "<table><tr><th>ID</th><th>Name</th><th>Aktiv</th></tr>";
    foreach ($gruppen as $gruppe) {
        echo "<tr><td>{$gruppe['id']}</td><td>{$gruppe['name']}</td><td>{$gruppe['active']}</td></tr>";
    }
    echo "</table>";
}

// Prüfe Schichten
echo "<h2>Alle Schichten in der Datenbank:</h2>";
$stmt = $db->query("
    SELECT s.*, a.name as task_area_name 
    FROM shifts s 
    LEFT JOIN task_areas a ON s.task_area_id = a.id 
    ORDER BY s.date, s.start_time
");
$schichten = $stmt->fetchAll();
if (empty($schichten)) {
    echo "<p style='color: red;'>KEINE SCHICHTEN GEFUNDEN! Bitte database.sql ausführen.</p>";
} else {
    echo "<p>Anzahl Schichten: " . count($schichten) . "</p>";
    echo "<table><tr><th>ID</th><th>Datum</th><th>Startzeit</th><th>Aufgabenbereich</th><th>Anzahl benötigt</th><th>Verantwortlicher</th><th>Aktiv</th></tr>";
    foreach ($schichten as $schicht) {
        $datum = $schicht['date'];
        $startzeit = $schicht['start_time'] ?? 'keine Zeit';
        $aktiv = $schicht['active'] ? 'Ja' : 'Nein';
        echo "<tr><td>{$schicht['id']}</td><td>{$datum}</td><td>{$startzeit}</td><td>{$schicht['task_area_name']}</td><td>{$schicht['required_count']}</td><td>{$schicht['responsible_person']}</td><td>{$aktiv}</td></tr>";
    }
    echo "</table>";
}

// Prüfe Schichten für aktuellen Monat
echo "<h2>Schichten für aktuellen Monat (" . date('Y-m') . "):</h2>";
$startDate = date('Y-m-01');
$endDate = date('Y-m-t');
$stmt = $db->prepare("
    SELECT s.*, a.name as task_area_name 
    FROM shifts s 
    LEFT JOIN task_areas a ON s.task_area_id = a.id 
    WHERE s.date BETWEEN ? AND ? AND s.active = 1
    ORDER BY s.date, s.start_time
");
$stmt->execute([$startDate, $endDate]);
$schichtenAktuell = $stmt->fetchAll();
if (empty($schichtenAktuell)) {
    echo "<p style='color: orange;'>Keine Schichten für den aktuellen Monat gefunden. Die Schichten sind für Januar 2026 erstellt. Navigiere zum Januar 2026 im Kalender!</p>";
} else {
    echo "<p>Anzahl Schichten: " . count($schichtenAktuell) . "</p>";
    echo "<table><tr><th>ID</th><th>Datum</th><th>Startzeit</th><th>Aufgabenbereich</th><th>Anzahl benötigt</th><th>Verantwortlicher</th></tr>";
    foreach ($schichtenAktuell as $schicht) {
        echo "<tr><td>{$schicht['id']}</td><td>{$schicht['date']}</td><td>{$schicht['start_time']}</td><td>{$schicht['task_area_name']}</td><td>{$schicht['required_count']}</td><td>{$schicht['responsible_person']}</td></tr>";
    }
    echo "</table>";
}

// Prüfe Schichten für Januar 2026
echo "<h2>Schichten für Januar 2026:</h2>";
$stmt = $db->prepare("
    SELECT s.*, a.name as task_area_name 
    FROM shifts s 
    LEFT JOIN task_areas a ON s.task_area_id = a.id 
    WHERE s.date BETWEEN '2026-01-01' AND '2026-01-31' AND s.active = 1
    ORDER BY s.date, s.start_time
");
$stmt->execute();
$schichten2026 = $stmt->fetchAll();
if (empty($schichten2026)) {
    echo "<p style='color: red;'>KEINE SCHICHTEN FÜR JANUAR 2026 GEFUNDEN!</p>";
} else {
    echo "<p>Anzahl Schichten: " . count($schichten2026) . "</p>";
    echo "<table><tr><th>ID</th><th>Datum</th><th>Startzeit</th><th>Aufgabenbereich</th><th>Anzahl benötigt</th><th>Verantwortlicher</th></tr>";
    foreach ($schichten2026 as $schicht) {
        echo "<tr><td>{$schicht['id']}</td><td>{$schicht['date']}</td><td>{$schicht['start_time']}</td><td>{$schicht['task_area_name']}</td><td>{$schicht['required_count']}</td><td>{$schicht['responsible_person']}</td></tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='index.php'>Zurück zum Kalender</a></p>";
?>
