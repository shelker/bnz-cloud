<?php
/**
 * Datenbank-Konfiguration für Schichtplan-System
 * Bedburger Narrenzunft
 */

// Datenbank-Verbindungsdaten
// TODO: Diese Werte mit deinen tatsächlichen Datenbankdaten ersetzen
define('DB_HOST', 'jxzb.your-database.de');
define('DB_NAME', 'bvwtmy_db1');
define('DB_USER', 'bvwtmy_1');
define('DB_PASS', 'w7)V![UDShrK');
define('DB_CHARSET', 'utf8mb4');

/**
 * Datenbankverbindung herstellen
 * @return PDO|null
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

/**
 * JSON-Response senden
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Fehler-Response senden
 */
function sendErrorResponse($message, $statusCode = 400) {
    sendJsonResponse(['success' => false, 'message' => $message], $statusCode);
}

/**
 * Erfolgs-Response senden
 */
function sendSuccessResponse($data = [], $message = '') {
    $response = ['success' => true];
    if ($message) {
        $response['message'] = $message;
    }
    if ($data) {
        $response['data'] = $data;
    }
    sendJsonResponse($response);
}

/**
 * Admin-Authentifizierung prüfen
 * @return array|false Admin-Daten oder false
 */
function checkAdminAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }
    
    $db = getDbConnection();
    if (!$db) {
        return false;
    }
    
    // Prüfe ob Admin noch aktiv ist
    $stmt = $db->prepare("SELECT id, benutzername, email, vorname, nachname FROM admins WHERE id = ? AND aktiv = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Admin nicht mehr aktiv oder nicht gefunden
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_logged_in']);
        return false;
    }
    
    return $admin;
}

/**
 * Admin-Login durchführen
 * @param string $benutzername
 * @param string $passwort
 * @return array|false Admin-Daten oder false
 */
function adminLogin($benutzername, $passwort) {
    $db = getDbConnection();
    if (!$db) {
        return false;
    }
    
    $stmt = $db->prepare("SELECT id, benutzername, passwort_hash, email, vorname, nachname FROM admins WHERE benutzername = ? AND aktiv = 1");
    $stmt->execute([$benutzername]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        return false;
    }
    
    // Passwort prüfen
    if (!password_verify($passwort, $admin['passwort_hash'])) {
        return false;
    }
    
    // Session starten (falls noch nicht gestartet)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_benutzername'] = $admin['benutzername'];
    
    // Login-Zeit aktualisieren
    $stmt = $db->prepare("UPDATE admins SET zuletzt_angemeldet = NOW() WHERE id = ?");
    $stmt->execute([$admin['id']]);
    
    // Session-ID speichern (optional für Audit)
    $sessionId = session_id();
    $ipAdresse = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $db->prepare("INSERT INTO admin_sessions (admin_id, session_id, ip_adresse, user_agent, abgelaufen_am) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
    $stmt->execute([$admin['id'], $sessionId, $ipAdresse, $userAgent]);
    
    return $admin;
}

/**
 * Admin-Logout durchführen
 */
function adminLogout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['admin_id'])) {
        $db = getDbConnection();
        if ($db) {
            // Session als abgelaufen markieren
            $sessionId = session_id();
            $stmt = $db->prepare("UPDATE admin_sessions SET abgelaufen_am = NOW() WHERE session_id = ?");
            $stmt->execute([$sessionId]);
        }
    }
    
    // Session zerstören
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

/**
 * Team-Zeitfenster aus shifts.team_slots (JSON) parsen.
 * Erwartetes Format: [{"start":"18:00:00","end":"20:00:00","team":"Team 1"}, …]
 * Alternativ: start_time, end_time, team_label
 *
 * @param mixed $value JSON-String, Array oder null
 * @return array<int, array{start: string, end: string, team: string}>  Zeiten als HH:MM
 */
function parse_shift_team_slots($value) {
    if ($value === null || $value === '') {
        return [];
    }
    if (is_array($value)) {
        $decoded = $value;
    } else {
        $decoded = json_decode((string) $value, true);
    }
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $s = $row['start'] ?? $row['start_time'] ?? '';
        $e = $row['end'] ?? $row['end_time'] ?? '';
        if ($s === null || $e === null) {
            continue;
        }
        $s = trim((string) $s);
        $e = trim((string) $e);
        if ($s === '' || $e === '') {
            continue;
        }
        if (strlen($s) > 8) {
            $s = substr($s, 0, 8);
        }
        if (strlen($e) > 8) {
            $e = substr($e, 0, 8);
        }
        $team = '';
        if (isset($row['team'])) {
            $team = trim((string) $row['team']);
        } elseif (isset($row['team_label'])) {
            $team = trim((string) $row['team_label']);
        }
        $out[] = [
            'start' => substr($s, 0, 5),
            'end' => substr($e, 0, 5),
            'team' => $team,
        ];
    }
    return $out;
}

