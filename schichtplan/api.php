<?php
/**
 * API-Endpunkte für Schichtplan-System
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDbConnection();
if (!$db) {
    sendErrorResponse('Datenbankverbindung fehlgeschlagen', 500);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_day_shifts':
        getDayShifts($db);
        break;
    
    case 'signup':
        signupForShift($db);
        break;
    
    case 'cancel':
        cancelShift($db);
        break;
    
    case 'delete_entry':
        deleteEntry($db);
        break;
    
    case 'get_my_shifts':
        getMyShifts($db);
        break;
    
    default:
        sendErrorResponse('Ungültige Aktion', 400);
}

/**
 * Schichten für einen bestimmten Tag abrufen
 */
function getDayShifts($db) {
    $date = $_GET['date'] ?? '';
    
    if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        sendErrorResponse('Ungültiges Datum', 400);
    }
    
    $stmt = $db->prepare("
        SELECT s.id, s.date, s.start_time, s.end_time, s.team_slots, s.task_area_id, s.required_count, 
               s.responsible_person, s.description, s.location, s.active, s.created_at, s.updated_at,
               a.name as task_area_name, a.color as task_area_color,
               COUNT(sz.id) as registered_count
        FROM shifts s
        LEFT JOIN task_areas a ON s.task_area_id = a.id
        LEFT JOIN shift_assignments sz ON s.id = sz.shift_id AND sz.status != 'cancelled'
        WHERE s.date = ? AND s.active = 1
        GROUP BY s.id, s.date, s.start_time, s.end_time, s.team_slots, s.task_area_id, s.required_count, 
                 s.responsible_person, s.description, s.location, s.active, s.created_at, s.updated_at,
                 a.name, a.color
        ORDER BY COALESCE(s.start_time, '00:00:00')
    ");
    $stmt->execute([$date]);
    $schichten = $stmt->fetchAll();
    
    // Für jede Schicht prüfen, welche Mitarbeiter/Gruppen eingetragen sind
    foreach ($schichten as &$schicht) {
        $stmt2 = $db->prepare("
            SELECT sz.*, 
                   e.id as employee_id,
                   e.first_name, e.last_name, e.email, e.is_group,
                   g.name as group_name, g.name as department_name
            FROM shift_assignments sz
            JOIN employees e ON sz.employee_id = e.id
            LEFT JOIN groups g ON e.group_id = g.id
            WHERE sz.shift_id = ? AND sz.status != 'cancelled'
            ORDER BY e.last_name, e.first_name
        ");
        $stmt2->execute([$schicht['id']]);
        $schicht['employees'] = $stmt2->fetchAll();
        $schicht['team_slots'] = parse_shift_team_slots($schicht['team_slots'] ?? null);
    }
    unset($schicht);
    
    sendSuccessResponse(['schichten' => $schichten]);
}

/**
 * Für eine Schicht eintragen
 */
function signupForShift($db) {
    $schichtId = $_POST['schicht_id'] ?? 0;
    $vorname = trim($_POST['vorname'] ?? '');
    $nachname = trim($_POST['nachname'] ?? '');
    $abteilungId = $_POST['abteilung_id'] ?? 0;
    $bemerkung = trim($_POST['bemerkung'] ?? '');
    
    // Validierung
    if (empty($schichtId)) {
        sendErrorResponse('Schicht-ID ist erforderlich', 400);
    }
    if (empty($vorname)) {
        sendErrorResponse('Vorname ist erforderlich', 400);
    }
    if (empty($nachname)) {
        sendErrorResponse('Nachname ist erforderlich', 400);
    }
    if (empty($abteilungId)) {
        sendErrorResponse('Abteilung ist erforderlich', 400);
    }
    
    // Prüfen ob Schicht existiert und noch Platz hat
    $stmt = $db->prepare("
        SELECT s.*, COUNT(sz.id) as registered_count
        FROM shifts s
        LEFT JOIN shift_assignments sz ON s.id = sz.shift_id AND sz.status != 'cancelled'
        WHERE s.id = ? AND s.active = 1
        GROUP BY s.id
    ");
    $stmt->execute([$schichtId]);
    $schicht = $stmt->fetch();
    
    if (!$schicht) {
        sendErrorResponse('Schicht nicht gefunden', 404);
    }
    
    // Wenn required_count = 0, dann unbegrenzte Eintragungen erlauben
    if ($schicht['required_count'] > 0 && $schicht['registered_count'] >= $schicht['required_count']) {
        sendErrorResponse('Schicht ist bereits voll belegt', 400);
    }
    
    // Prüfen ob Abteilung existiert
    $stmt = $db->prepare("SELECT id FROM groups WHERE id = ? AND active = 1");
    $stmt->execute([$abteilungId]);
    $abteilung = $stmt->fetch();
    if (!$abteilung) {
        sendErrorResponse('Abteilung nicht gefunden', 400);
    }
    
    // Mitarbeiter finden oder erstellen
    // Zuerst prüfen ob bereits ein Mitarbeiter mit diesem Namen und dieser Abteilung existiert
    $stmt = $db->prepare("
        SELECT id FROM employees 
        WHERE first_name = ? AND last_name = ? AND group_id = ? AND is_group = 0
        LIMIT 1
    ");
    $stmt->execute([$vorname, $nachname, $abteilungId]);
    $mitarbeiter = $stmt->fetch();
    
    if ($mitarbeiter) {
        $mitarbeiterId = $mitarbeiter['id'];
    } else {
        // Neuen Mitarbeiter erstellen
        $stmt = $db->prepare("
            INSERT INTO employees (first_name, last_name, group_id, is_group, active)
            VALUES (?, ?, ?, 0, 1)
        ");
        $stmt->execute([$vorname, $nachname, $abteilungId]);
        $mitarbeiterId = $db->lastInsertId();
    }
    
    // Prüfen ob bereits für diese Schicht eingetragen
    $stmt = $db->prepare("
        SELECT id FROM shift_assignments 
        WHERE shift_id = ? AND employee_id = ?
    ");
    $stmt->execute([$schichtId, $mitarbeiterId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Wenn bereits eingetragen, Status auf 'registered' setzen und Bemerkung aktualisieren
        $stmt = $db->prepare("
            UPDATE shift_assignments 
            SET status = 'registered', note = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$bemerkung, $existing['id']]);
        sendSuccessResponse([], 'Eintragung aktualisiert');
    } else {
        // Neue Eintragung erstellen
        $stmt = $db->prepare("
            INSERT INTO shift_assignments (shift_id, employee_id, status, note)
            VALUES (?, ?, 'registered', ?)
        ");
        $stmt->execute([$schichtId, $mitarbeiterId, $bemerkung]);
        sendSuccessResponse([], 'Erfolgreich für Schicht eingetragen');
    }
}

/**
 * Eintragung für eine Schicht stornieren (nur für Admins)
 */
function cancelShift($db) {
    // Admin-Check
    $admin = checkAdminAuth();
    if (!$admin) {
        sendErrorResponse('Nur Administratoren können Eintragungen löschen. Bitte melden Sie sich an.', 403);
    }
    
    $schichtId = $_POST['schicht_id'] ?? 0;
    $mitarbeiterId = $_POST['mitarbeiter_id'] ?? 0;
    
    if (empty($schichtId) || empty($mitarbeiterId)) {
        sendErrorResponse('Schicht-ID und Mitarbeiter-ID sind erforderlich', 400);
    }
    
    // Eintragung löschen (nicht nur als abgesagt markieren, sondern wirklich löschen)
    $stmt = $db->prepare("
        DELETE FROM shift_assignments 
        WHERE shift_id = ? AND employee_id = ?
    ");
    $stmt->execute([$schichtId, $mitarbeiterId]);
    
    if ($stmt->rowCount() > 0) {
        sendSuccessResponse([], 'Eintragung erfolgreich gelöscht');
    } else {
        sendErrorResponse('Eintragung nicht gefunden', 404);
    }
}

/**
 * Eintragung löschen (Admin-Funktion)
 */
function deleteEntry($db) {
    // Admin-Check
    $admin = checkAdminAuth();
    if (!$admin) {
        sendErrorResponse('Nur Administratoren können Eintragungen löschen.', 403);
    }
    
    $zuweisungId = $_POST['zuweisung_id'] ?? 0;
    
    if (empty($zuweisungId)) {
        sendErrorResponse('Zuweisungs-ID ist erforderlich', 400);
    }
    
    // Eintragung löschen
    $stmt = $db->prepare("DELETE FROM shift_assignments WHERE id = ?");
    $stmt->execute([$zuweisungId]);
    
    if ($stmt->rowCount() > 0) {
        sendSuccessResponse([], 'Eintragung erfolgreich gelöscht');
    } else {
        sendErrorResponse('Eintragung nicht gefunden', 404);
    }
}

/**
 * Meine Schichten abrufen (für einen Mitarbeiter)
 */
function getMyShifts($db) {
    $mitarbeiterId = $_GET['mitarbeiter_id'] ?? 0;
    
    if (empty($mitarbeiterId)) {
        sendErrorResponse('Mitarbeiter-ID ist erforderlich', 400);
    }
    
    $stmt = $db->prepare("
        SELECT s.*, a.name as task_area_name, a.color as task_area_color, sz.status, sz.note
        FROM shift_assignments sz
        JOIN shifts s ON sz.shift_id = s.id
        JOIN task_areas a ON s.task_area_id = a.id
        WHERE sz.employee_id = ? AND sz.status != 'cancelled' AND s.active = 1
        ORDER BY s.date, s.start_time
    ");
    $stmt->execute([$mitarbeiterId]);
    $schichten = $stmt->fetchAll();
    foreach ($schichten as &$s) {
        $s['team_slots'] = parse_shift_team_slots($s['team_slots'] ?? null);
    }
    unset($s);
    
    sendSuccessResponse(['schichten' => $schichten]);
}

