<?php
require_once 'config.php';
$db = getDbConnection();

// Prüfe ob GET-Parameter gesetzt sind
$yearParam = isset($_GET['year']) ? (int)$_GET['year'] : null;
$monthParam = isset($_GET['month']) ? (int)$_GET['month'] : null;
$showStats = isset($_GET['stats']) && $_GET['stats'] == '1';
$dateParam = isset($_GET['date']) ? $_GET['date'] : null;
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

// Wenn keine Parameter gesetzt, finde ersten Monat mit Schichten
if ($yearParam === null || $monthParam === null) {
    if ($db) {
        // Finde ersten Monat mit Schichten (zukünftig oder heute)
        $stmt = $db->query("
            SELECT DATE_FORMAT(MIN(date), '%Y') as year, DATE_FORMAT(MIN(date), '%m') as month
            FROM shifts 
            WHERE active = 1 AND date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            ORDER BY date ASC
            LIMIT 1
        ");
        $firstShift = $stmt->fetch();
        
        if ($firstShift && $firstShift['year'] && $firstShift['month']) {
            $currentYear = (int)$firstShift['year'];
            $currentMonth = (int)$firstShift['month'];
        } else {
            // Wenn keine zukünftigen Schichten, zeige erste Schicht überhaupt
            $stmt = $db->query("
                SELECT DATE_FORMAT(MIN(date), '%Y') as year, DATE_FORMAT(MIN(date), '%m') as month
                FROM shifts 
                WHERE active = 1
                ORDER BY date ASC
                LIMIT 1
            ");
            $firstShift = $stmt->fetch();
            
            if ($firstShift && $firstShift['year'] && $firstShift['month']) {
                $currentYear = (int)$firstShift['year'];
                $currentMonth = (int)$firstShift['month'];
            } else {
                // Fallback: Aktuelles Datum
                $currentYear = date('Y');
                $currentMonth = date('m');
            }
        }
    } else {
        // Fallback: Aktuelles Datum
        $currentYear = date('Y');
        $currentMonth = date('m');
    }
} else {
    $currentYear = $yearParam;
    $currentMonth = $monthParam;
}

// Monatsnamen auf Deutsch
$monthNames = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
];

// Wochentage auf Deutsch
$weekDays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

// Ersten Tag des Monats
$firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay); // 0 = Sonntag, 1 = Montag, etc.

// Monatsnavigation
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Schichten für den Monat laden
$schichten = [];
if ($db) {
    $startDate = date('Y-m-01', $firstDay);
    $endDate = date('Y-m-t', $firstDay);
    
    $stmt = $db->prepare("
        SELECT s.id, s.date, s.start_time, s.end_time, s.team_slots, s.task_area_id, s.required_count, 
               s.responsible_person, s.description, s.location, s.active, s.created_at, s.updated_at,
               a.name as task_area_name, a.color as task_area_color,
               COUNT(sz.id) as registered_count
        FROM shifts s
        LEFT JOIN task_areas a ON s.task_area_id = a.id
        LEFT JOIN shift_assignments sz ON s.id = sz.shift_id AND sz.status != 'cancelled'
        WHERE s.date BETWEEN ? AND ? AND s.active = 1
        GROUP BY s.id, s.date, s.start_time, s.end_time, s.team_slots, s.task_area_id, s.required_count, 
                 s.responsible_person, s.description, s.location, s.active, s.created_at, s.updated_at,
                 a.name, a.color
        ORDER BY s.date, COALESCE(s.start_time, '00:00:00')
    ");
    $stmt->execute([$startDate, $endDate]);
    $schichten = $stmt->fetchAll();
    foreach ($schichten as &$schichtRow) {
        $schichtRow['team_slots'] = parse_shift_team_slots($schichtRow['team_slots'] ?? null);
    }
    unset($schichtRow);
    
    // Schichten nach Datum gruppieren
    $schichtenByDate = [];
    foreach ($schichten as $schicht) {
        $datum = $schicht['date'];
        if (!isset($schichtenByDate[$datum])) {
            $schichtenByDate[$datum] = [];
        }
        $schichtenByDate[$datum][] = $schicht;
    }
    
    // Statistiken pro Abteilung (Group) laden
    $statsByGroup = [];
    $totalShifts = 0;
    $totalPersons = 0;
    $statsDate = null;
    $statsDateLabel = '';
    
    if ($showStats) {
        // Prüfe ob ein einzelnes Datum übergeben wurde
        if ($dateParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
            // Statistik für einen einzelnen Tag
            $statsDate = $dateParam;
            $statsDateStart = $statsDate;
            $statsDateEnd = $statsDate;
            
            // Datum für Anzeige formatieren
            $dateObj = new DateTime($statsDate);
            $statsDateLabel = $weekDays[$dateObj->format('w')] . ', ' . $dateObj->format('d') . '. ' . $monthNames[(int)$dateObj->format('m')] . ' ' . $dateObj->format('Y');
        } elseif ($yearParam === null || $monthParam === null) {
            // Statistik für alle Monate (wenn kein year/month angegeben)
            $statsDateStart = null; // null bedeutet: keine Beschränkung
            $statsDateEnd = null;
            $statsDateLabel = 'Alle Monate';
        } else {
            // Statistik für den gesamten Monat
            $statsDateStart = $startDate;
            $statsDateEnd = $endDate;
            $statsDateLabel = $monthNames[$currentMonth] . ' ' . $currentYear;
        }
        
        if ($statsDateStart === null || $statsDateEnd === null) {
            // Stats für alle Monate - keine Datumsbeschränkung
            $stmt = $db->query("
                SELECT 
                    g.id,
                    g.name as group_name,
                    COUNT(DISTINCT sa.id) as shift_count,
                    COUNT(DISTINCT e.id) as person_count
                FROM groups g
                INNER JOIN employees e ON g.id = e.group_id AND e.active = 1
                INNER JOIN shift_assignments sa ON e.id = sa.employee_id AND sa.status != 'cancelled'
                INNER JOIN shifts s ON sa.shift_id = s.id AND s.active = 1
                WHERE g.active = 1
                GROUP BY g.id, g.name
                ORDER BY g.name ASC
            ");
        } else {
            // Stats für bestimmten Zeitraum
            $stmt = $db->prepare("
                SELECT 
                    g.id,
                    g.name as group_name,
                    COUNT(DISTINCT sa.id) as shift_count,
                    COUNT(DISTINCT e.id) as person_count
                FROM groups g
                INNER JOIN employees e ON g.id = e.group_id AND e.active = 1
                INNER JOIN shift_assignments sa ON e.id = sa.employee_id AND sa.status != 'cancelled'
                INNER JOIN shifts s ON sa.shift_id = s.id 
                    AND s.date >= ? 
                    AND s.date <= ?
                    AND s.active = 1
                WHERE g.active = 1
                GROUP BY g.id, g.name
                ORDER BY g.name ASC
            ");
            $stmt->execute([$statsDateStart, $statsDateEnd]);
        }
        $statsByGroup = $stmt->fetchAll();
        
        // Gesamtzahlen berechnen
        foreach ($statsByGroup as $stat) {
            $totalShifts += (int)$stat['shift_count'];
            $totalPersons += (int)$stat['person_count'];
        }
        
        // Mitarbeiter mit den meisten Schichten laden
        if ($statsDateStart === null || $statsDateEnd === null) {
            // Stats für alle Monate - keine Datumsbeschränkung
            $stmt = $db->query("
                SELECT 
                    e.id,
                    e.first_name,
                    e.last_name,
                    e.is_group,
                    g.name as group_name,
                    COUNT(sa.id) as shift_count
                FROM employees e
                LEFT JOIN groups g ON e.group_id = g.id
                INNER JOIN shift_assignments sa ON e.id = sa.employee_id AND sa.status != 'cancelled'
                INNER JOIN shifts s ON sa.shift_id = s.id AND s.active = 1
                WHERE e.active = 1
                GROUP BY e.id, e.first_name, e.last_name, e.is_group, g.name
                HAVING shift_count > 0
                ORDER BY shift_count DESC, e.last_name ASC, e.first_name ASC
                LIMIT 200
            ");
        } else {
            // Stats für bestimmten Zeitraum
            $stmt = $db->prepare("
                SELECT 
                    e.id,
                    e.first_name,
                    e.last_name,
                    e.is_group,
                    g.name as group_name,
                    COUNT(sa.id) as shift_count
                FROM employees e
                LEFT JOIN groups g ON e.group_id = g.id
                INNER JOIN shift_assignments sa ON e.id = sa.employee_id AND sa.status != 'cancelled'
                INNER JOIN shifts s ON sa.shift_id = s.id 
                    AND s.date >= ? 
                    AND s.date <= ?
                    AND s.active = 1
                WHERE e.active = 1
                GROUP BY e.id, e.first_name, e.last_name, e.is_group, g.name
                HAVING shift_count > 0
                ORDER BY shift_count DESC, e.last_name ASC, e.first_name ASC
                LIMIT 20
            ");
            $stmt->execute([$statsDateStart, $statsDateEnd]);
        }
        $topEmployees = $stmt->fetchAll();
    } else {
        $topEmployees = [];
    }
    
    // Alle Personen für den angegebenen Monat laden (wenn all=1)
    $allEmployees = [];
    if ($showAll && $yearParam !== null && $monthParam !== null) {
        $allStartDate = date('Y-m-01', mktime(0, 0, 0, $monthParam, 1, $yearParam));
        $allEndDate = date('Y-m-t', mktime(0, 0, 0, $monthParam, 1, $yearParam));
        
        $stmt = $db->prepare("
            SELECT DISTINCT
                e.id,
                e.first_name,
                e.last_name,
                e.is_group,
                g.name as group_name
            FROM employees e
            LEFT JOIN groups g ON e.group_id = g.id
            INNER JOIN shift_assignments sa ON e.id = sa.employee_id AND sa.status != 'cancelled'
            INNER JOIN shifts s ON sa.shift_id = s.id 
                AND s.date >= ? 
                AND s.date <= ?
                AND s.active = 1
            WHERE e.active = 1
            ORDER BY 
                COALESCE(e.last_name, '') ASC,
                COALESCE(e.first_name, '') ASC,
                COALESCE(g.name, '') ASC
        ");
        $stmt->execute([$allStartDate, $allEndDate]);
        $allEmployees = $stmt->fetchAll();
    }
}

// Gruppen für Abteilungs-Dropdown laden
$gruppen = [];
if ($db) {
    $stmt = $db->query("SELECT id, name FROM groups WHERE active = 1 ORDER BY name");
    $gruppen = $stmt->fetchAll();
}

/**
 * Bestimmt die Farbe basierend auf der Verfügbarkeit von Helfern
 * @param int $requiredCount Anzahl der benötigten Helfer (0 = unbegrenzt)
 * @param int $registeredCount Anzahl der bereits angemeldeten Helfer
 * @return string Hex-Farbcode
 */
function getAvailabilityColor($requiredCount, $registeredCount) {
    if ($requiredCount == 0) {
        // Unbegrenzt - grün
        return '#22c55e'; // green-500
    }
    
    $frei = $requiredCount - $registeredCount;
    
    if ($frei <= 0) {
        // Voll - rot
        return '#ef4444'; // red-500
    }
    
    $prozentFrei = ($frei / $requiredCount) * 100;
    
    if ($prozentFrei >= 50) {
        // Viele Plätze frei - grün
        return '#22c55e'; // green-500
    } else {
        // Wenige Plätze frei - gelb
        return '#eab308'; // yellow-500
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
    <title>Schichtplan - Bedburger Narrenzunft</title>
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
        .calendar-day {
            transition: all 0.2s ease;
            min-height: 80px;
            touch-action: manipulation;
        }
        .calendar-day:active {
            transform: scale(0.95);
        }
        .calendar-day:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .shift-badge {
            font-size: 0.6rem;
            padding: 0.15rem 0.25rem;
            border-radius: 0.2rem;
            margin: 0.05rem 0;
            display: block;
            width: 100%;
            line-height: 1.1;
        }
        .touch-button {
            min-height: 44px;
            min-width: 44px;
        }
        .view-switch-btn {
            color: #6b7280;
        }
        .view-switch-btn.active {
            background-color: #c4000c;
            color: white;
        }
        .view-switch-btn:not(.active):hover {
            background-color: #e5e7eb;
        }
        .view-container {
            display: none;
        }
        .view-container.active {
            display: block;
        }
        @media (min-width: 640px) {
            .calendar-day {
                min-height: 120px;
            }
            .calendar-day:hover {
                transform: translateY(-2px);
            }
            .shift-badge {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }
        }
        @media (max-width: 640px) {
            .calendar-day {
                min-height: 70px;
            }
            .calendar-day:hover {
                transform: none;
            }
            /* Mobile Listenansicht Optimierungen */
            #listView .view-container {
                padding: 0.5rem;
            }
            #listView .bg-white {
                padding: 0.75rem;
            }
        }
        /* Line clamp für Beschreibungen */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-narrenzunft text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-center">
                <img src="../assets/bnz-logo-300.png" alt="BNZ Logo" class="h-8 sm:h-12 mr-3">
                <h1 class="text-2xl sm:text-3xl font-bold">Schichtplan <?php echo $currentYear; ?></h1>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6">
        <!-- Aufruf an die Jecken -->
        <div class="bg-gradient-to-r from-narrenzunft to-red-700 text-white rounded-lg shadow-lg p-5 sm:p-6 mb-6 relative overflow-hidden">
            <div class="relative z-10">
                <div class="flex items-start gap-3 sm:gap-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-heart text-3xl sm:text-4xl opacity-80"></i>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-xl sm:text-2xl font-bold mb-3">Liebe Jecken der Bedburger Narrenzunft! 🎭</h2>
                        <p class="text-base sm:text-lg leading-relaxed mb-3">
                            Unser Verein lebt von eurem Engagement und eurer Unterstützung! Jede helfende Hand zählt und macht unsere Veranstaltungen zu etwas Besonderem. 
                        </p>
                        <p class="text-sm sm:text-base leading-relaxed opacity-95">
                            Bitte tragt euch für die Schichten ein, bei denen ihr helfen könnt. Ob Theke, Kasse, Service oder Garderobe – <strong>jede Unterstützung ist wertvoll</strong> und hilft uns, unsere Traditionen zu pflegen und gemeinsam unvergessliche Karnevalsmomente zu schaffen.
                        </p>
                        <p class="text-sm sm:text-base mt-3 font-semibold">
                            Vielen Dank für eure Unterstützung! ❤️ Alaaf!
                        </p>
                    </div>
                </div>
            </div>
            <!-- Dekoratives Element -->
           
        </div>

        <!-- Ansicht-Switch und Monatsnavigation -->
        <div class="bg-white rounded-lg shadow-md p-3 sm:p-4 mb-4 sm:mb-6">
            <!-- Ansicht-Switch -->
            <div class="flex justify-center mb-4">
                <div class="inline-flex bg-gray-100 rounded-lg p-1">
                    <button id="viewCalendarBtn" 
                            class="px-4 py-2 rounded-md transition-colors touch-button flex items-center gap-2 view-switch-btn active">
                        <i class="fas fa-calendar"></i>
                        <span class="text-sm sm:text-base font-medium">Kalender</span>
                    </button>
                    <button id="viewListBtn" 
                            class="px-4 py-2 rounded-md transition-colors touch-button flex items-center gap-2 view-switch-btn">
                        <i class="fas fa-list"></i>
                        <span class="text-sm sm:text-base font-medium">Liste</span>
                    </button>
                </div>
            </div>
            
            <!-- Monatsnavigation -->
            <div id="monthNavigation" class="flex items-center justify-between gap-2">
                <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?><?php echo $showStats ? '&stats=1' : ''; ?>" 
                   class="bg-narrenzunft text-white px-3 py-2 sm:px-4 rounded-lg hover:bg-red-800 active:bg-red-900 transition-colors touch-button flex items-center justify-center text-sm sm:text-base">
                    <i class="fas fa-chevron-left"></i>
                    <span class="hidden sm:inline ml-1">Vorheriger</span>
                </a>
                <h2 class="text-lg sm:text-2xl font-bold text-gray-800 text-center flex-1 px-2">
                    <?php echo $monthNames[$currentMonth] . ' ' . $currentYear; ?>
                </h2>
                <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?><?php echo $showStats ? '&stats=1' : ''; ?>" 
                   class="bg-narrenzunft text-white px-3 py-2 sm:px-4 rounded-lg hover:bg-red-800 active:bg-red-900 transition-colors touch-button flex items-center justify-center text-sm sm:text-base">
                    <span class="hidden sm:inline mr-1">Nächster</span>
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>

        <!-- Statistiken pro Abteilung (nur wenn ?stats=1) -->
        <?php if ($showStats && !empty($statsByGroup)): ?>
            <div class="bg-gradient-to-br from-white to-gray-50 rounded-xl shadow-lg border border-gray-200 p-4 sm:p-5 mb-4 sm:mb-6">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h3 class="text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-chart-pie text-narrenzunft"></i>
                        <span class="hidden sm:inline">Statistik</span>
                        <span class="text-xs sm:text-sm font-normal text-gray-600"><?php echo $statsDateLabel; ?></span>
                    </h3>
                    <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-600">
                        <div class="flex items-center gap-1">
                            <i class="fas fa-calendar-check text-narrenzunft"></i>
                            <span class="font-semibold text-gray-800"><?php echo $totalShifts; ?></span>
                        </div>
                        <span class="text-gray-400">•</span>
                        <div class="flex items-center gap-1">
                            <i class="fas fa-users text-narrenzunft"></i>
                            <span class="font-semibold text-gray-800"><?php echo $totalPersons; ?></span>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
                    <?php foreach ($statsByGroup as $stat): ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-2.5 sm:p-3 hover:shadow-md hover:border-narrenzunft transition-all">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-2 h-2 rounded-full bg-narrenzunft flex-shrink-0"></div>
                                <span class="font-medium text-gray-800 text-xs sm:text-sm truncate flex-1">
                                    <?php echo htmlspecialchars($stat['group_name']); ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1 text-xs text-gray-600">
                                    <i class="fas fa-calendar-check"></i>
                                    <span class="font-semibold text-gray-800"><?php echo (int)$stat['shift_count']; ?></span>
                                </div>
                                <div class="flex items-center gap-1 text-xs text-gray-600">
                                    <i class="fas fa-user"></i>
                                    <span class="font-semibold text-gray-800"><?php echo (int)$stat['person_count']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Liste der Mitarbeiter mit den meisten Schichten -->
            <?php if (!empty($topEmployees)): ?>
                <div class="bg-gradient-to-br from-white to-gray-50 rounded-xl shadow-lg border border-gray-200 p-4 sm:p-5 mb-4 sm:mb-6">
                    <h3 class="text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2 mb-3 sm:mb-4">
                        <i class="fas fa-trophy text-narrenzunft"></i>
                        <span>Mitarbeiter mit den meisten Schichten</span>
                    </h3>
                    <div class="space-y-2">
                        <?php foreach ($topEmployees as $index => $employee): 
                            $employeeName = '';
                            if ($employee['is_group'] && $employee['group_name']) {
                                $employeeName = $employee['group_name'];
                            } else {
                                $parts = array_filter([$employee['first_name'], $employee['last_name']]);
                                $employeeName = !empty($parts) ? implode(' ', $parts) : ($employee['group_name'] ?? 'Unbekannt');
                            }
                            $shiftCount = (int)$employee['shift_count'];
                        ?>
                            <div class="bg-white border border-gray-200 rounded-lg p-3 hover:shadow-md hover:border-narrenzunft transition-all flex items-center justify-between">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-narrenzunft text-white flex items-center justify-center font-bold text-sm">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-800 truncate">
                                            <?php echo htmlspecialchars($employeeName); ?>
                                        </div>
                                        <?php if ($employee['group_name'] && !$employee['is_group']): ?>
                                            <div class="text-xs text-gray-500 truncate">
                                                <?php echo htmlspecialchars($employee['group_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                                    <i class="fas fa-calendar-check text-narrenzunft"></i>
                                    <span class="font-bold text-gray-800 text-sm sm:text-base">
                                        <?php echo $shiftCount; ?> Schicht<?php echo $shiftCount !== 1 ? 'en' : ''; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Liste aller Personen für den Monat (wenn all=1) -->
        <?php if ($showAll && !empty($allEmployees)): ?>
            <div class="bg-gradient-to-br from-white to-gray-50 rounded-xl shadow-lg border border-gray-200 p-4 sm:p-5 mb-4 sm:mb-6">
                <h3 class="text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2 mb-3 sm:mb-4">
                    <i class="fas fa-users text-narrenzunft"></i>
                    <span>Alle Personen - <?php echo $monthNames[$monthParam] . ' ' . $yearParam; ?></span>
                    <span class="text-xs sm:text-sm font-normal text-gray-600 ml-auto">
                        <?php echo count($allEmployees); ?> Person<?php echo count($allEmployees) !== 1 ? 'en' : ''; ?>
                    </span>
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                    <?php foreach ($allEmployees as $employee): 
                        $employeeName = '';
                        if ($employee['is_group'] && $employee['group_name']) {
                            $employeeName = $employee['group_name'];
                        } else {
                            $parts = array_filter([$employee['first_name'], $employee['last_name']]);
                            $employeeName = !empty($parts) ? implode(' ', $parts) : ($employee['group_name'] ?? 'Unbekannt');
                        }
                    ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-3 hover:shadow-md hover:border-narrenzunft transition-all">
                            <div class="font-medium text-gray-800">
                                <?php echo htmlspecialchars($employeeName); ?>
                            </div>
                            <?php if ($employee['group_name'] && !$employee['is_group']): ?>
                                <div class="text-xs text-gray-500 mt-1">
                                    <?php echo htmlspecialchars($employee['group_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Kalender -->
        <div id="calendarView" class="view-container active bg-white rounded-lg shadow-md p-2 sm:p-4 mb-4 sm:mb-6 overflow-x-auto">
            <!-- Wochentage Header -->
            <div class="grid grid-cols-7 gap-1 sm:gap-2 mb-2 min-w-[350px]">
                <?php foreach ($weekDays as $day): ?>
                    <div class="text-center font-bold text-gray-700 py-1 sm:py-2 text-xs sm:text-sm"><?php echo $day; ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Kalendertage -->
            <div class="grid grid-cols-7 gap-1 sm:gap-2 min-w-[350px]">
                <?php
                // Leere Zellen vor dem ersten Tag
                for ($i = 0; $i < $dayOfWeek; $i++) {
                    echo '<div class="aspect-square"></div>';
                }

                // Tage des Monats
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $currentDate = date('Y-m-d', mktime(0, 0, 0, $currentMonth, $day, $currentYear));
                    $isToday = ($currentDate == date('Y-m-d'));
                    $hasShifts = isset($schichtenByDate[$currentDate]);
                    $dayShifts = $hasShifts ? $schichtenByDate[$currentDate] : [];
                    
                    $bgClass = $isToday ? 'bg-red-100 border-2 border-narrenzunft' : 'bg-gray-50 border border-gray-200';
                    if ($hasShifts) {
                        $bgClass .= ' ring-2 ring-blue-300';
                    }
                    ?>
                    <div class="calendar-day <?php echo $bgClass; ?> rounded-lg p-1.5 cursor-pointer flex flex-col" 
                         onclick="showDayDetails('<?php echo $currentDate; ?>')">
                        <div class="font-bold <?php echo $isToday ? 'text-narrenzunft' : 'text-gray-800'; ?> mb-1 text-sm flex-shrink-0">
                            <?php echo $day; ?>
                        </div>
                        <div class="flex-1 space-y-0.5 overflow-y-auto min-h-0">
                            <?php if ($hasShifts): ?>
                                <?php foreach ($dayShifts as $schicht): 
                                    $anzahlBenoetigt = (int)($schicht['required_count'] ?? 0);
                                    $angemeldet = (int)($schicht['registered_count'] ?? 0);
                                    $availabilityColor = getAvailabilityColor($anzahlBenoetigt, $angemeldet);
                                    $slotTitles = [];
                                    if (!empty($schicht['team_slots'])) {
                                        foreach ($schicht['team_slots'] as $sl) {
                                            $slotTitles[] = $sl['start'] . '–' . $sl['end'] . ($sl['team'] !== '' ? ' ' . $sl['team'] : '');
                                        }
                                    }
                                    $zeitTitle = !empty($slotTitles) ? implode('; ', $slotTitles) : ($schicht['start_time'] ? substr($schicht['start_time'], 0, 5) . ($schicht['end_time'] ? '–' . substr($schicht['end_time'], 0, 5) : '') : '');
                                    $badgeTitle = $schicht['task_area_name'] . ($zeitTitle !== '' ? ' - ' . $zeitTitle : '');
                                ?>
                                    <div class="shift-badge text-white truncate" 
                                         style="background-color: <?php echo htmlspecialchars($availabilityColor); ?>"
                                         title="<?php echo htmlspecialchars($badgeTitle); ?>">
                                        <?php 
                                        $time = $schicht['start_time'] ? substr($schicht['start_time'], 0, 5) . ' ' : '';
                                        $aufgabenbereichName = $schicht['task_area_name'];
                                        // Kürze lange Namen für bessere Anzeige
                                        $displayText = $time . $aufgabenbereichName;
                                        if (strlen($displayText) > 20) {
                                            $displayText = substr($displayText, 0, 17) . '...';
                                        }
                                        echo htmlspecialchars($displayText); 
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-[9px] text-gray-400">-</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
            </div>
        </div>

        <!-- Listen-Ansicht -->
        <div id="listView" class="view-container">
            <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-4 sm:mb-6">
                <div class="flex items-center justify-between mb-4 sm:mb-6">
                    <h3 class="text-lg sm:text-2xl font-bold text-gray-800">
                        <i class="fas fa-list mr-2 text-narrenzunft"></i>Alle Schichten
                    </h3>
                    <?php if (!empty($schichten)): ?>
                        <span class="text-sm sm:text-base text-gray-600 bg-gray-100 px-3 py-1 rounded-full">
                            <?php echo count($schichten); ?> Schicht<?php echo count($schichten) !== 1 ? 'en' : ''; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if (empty($schichten)): ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-calendar-times text-5xl mb-4 text-gray-300"></i>
                        <p class="text-lg font-medium mb-2">Keine Schichten für diesen Monat vorhanden.</p>
                        <p class="text-sm">Bitte wählen Sie einen anderen Monat aus.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6 sm:space-y-8">
                        <?php 
                        // Schichten nach Datum gruppieren für Listenansicht
                        $schichtenByDateList = [];
                        foreach ($schichten as $schicht) {
                            $datum = $schicht['date'];
                            if (!isset($schichtenByDateList[$datum])) {
                                $schichtenByDateList[$datum] = [];
                            }
                            $schichtenByDateList[$datum][] = $schicht;
                        }
                        ksort($schichtenByDateList);
                        
                        foreach ($schichtenByDateList as $datum => $tagSchichten): 
                            $datumObj = new DateTime($datum);
                            $wochentag = $weekDays[$datumObj->format('w')];
                            $tag = $datumObj->format('d');
                            $monat = $monthNames[(int)$datumObj->format('m')];
                            $isToday = ($datum == date('Y-m-d'));
                        ?>
                            <!-- Datums-Header -->
                            <div class="sticky top-0 z-10 bg-white py-2 -mt-2 mb-3">
                                <div class="flex items-center gap-3 pb-2 border-b-2 border-gray-200">
                                    <div class="flex items-center gap-2">
                                        <div class="bg-narrenzunft text-white px-3 py-1.5 rounded-lg text-sm sm:text-base font-bold min-w-[50px] text-center">
                                            <?php echo $wochentag; ?>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                                            <span class="text-lg sm:text-xl font-bold text-gray-800">
                                                <?php echo $tag . '. ' . $monat; ?>
                                            </span>
                                            <span class="text-sm sm:text-base text-gray-600">
                                                <?php echo $datumObj->format('Y'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($isToday): ?>
                                        <span class="ml-auto px-2 py-1 bg-red-100 text-narrenzunft text-xs font-medium rounded">
                                            Heute
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-sm text-gray-500 ml-auto">
                                        <?php echo count($tagSchichten); ?> Schicht<?php echo count($tagSchichten) !== 1 ? 'en' : ''; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Schichten des Tages -->
                            <div class="space-y-3 sm:space-y-4 ml-0 sm:ml-4">
                                <?php foreach ($tagSchichten as $schicht): 
                                    $anzahlBenoetigt = (int)($schicht['required_count'] ?? 0);
                                    $angemeldet = (int)($schicht['registered_count'] ?? 0);
                                    $isFull = $anzahlBenoetigt > 0 && $angemeldet >= $anzahlBenoetigt;
                                    $availabilityColor = getAvailabilityColor($anzahlBenoetigt, $angemeldet);
                                    $frei = $anzahlBenoetigt > 0 ? $anzahlBenoetigt - $angemeldet : null;
                                ?>
                                    <div class="bg-white border-2 <?php echo $isFull ? 'border-gray-300' : 'border-gray-200 hover:border-narrenzunft'; ?> rounded-xl p-4 sm:p-5 hover:shadow-lg transition-all cursor-pointer group" 
                                         onclick="showDayDetails('<?php echo $datum; ?>')">
                                        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                                            <!-- Linke Seite: Hauptinfos -->
                                            <div class="flex-1 min-w-0">
                                                <!-- Aufgabenbereich und Zeit in einer Zeile -->
                                                <div class="flex items-center gap-3 mb-2 flex-wrap">
                                                    <span class="px-4 py-2 rounded-lg bg-blue-600 text-white text-base sm:text-lg font-bold whitespace-nowrap shadow-md">
                                                        <i class="fas fa-tasks mr-2"></i><?php echo htmlspecialchars($schicht['task_area_name']); ?>
                                                    </span>
                                                    <?php if ($schicht['start_time']): ?>
                                                        <div class="flex items-center gap-1 text-gray-700 font-medium">
                                                            <i class="fas fa-clock text-sm"></i>
                                                            <span class="text-sm sm:text-base">
                                                                <?php echo substr($schicht['start_time'], 0, 5); ?>
                                                                <?php if ($schicht['end_time']): ?>
                                                                    <span class="text-gray-400 mx-1">-</span><?php echo substr($schicht['end_time'], 0, 5); ?>
                                                                <?php endif; ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($schicht['team_slots'])): ?>
                                                    <div class="mb-2 text-sm text-gray-700 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2">
                                                        <p class="font-semibold text-gray-800 mb-1.5 flex items-center gap-2">
                                                            <i class="fas fa-users text-narrenzunft"></i> Team-Zeitfenster
                                                        </p>
                                                        <ul class="space-y-1 text-gray-700">
                                                            <?php foreach ($schicht['team_slots'] as $sl): ?>
                                                                <li>
                                                                    <span class="font-medium tabular-nums"><?php echo htmlspecialchars($sl['start']); ?>–<?php echo htmlspecialchars($sl['end']); ?></span>
                                                                    <?php if ($sl['team'] !== ''): ?>
                                                                        <span class="text-gray-600"> · <?php echo htmlspecialchars($sl['team']); ?></span>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Verfügbarkeits-Status dezenter -->
                                                <div class="mb-3">
                                                    <?php if ($isFull): ?>
                                                        <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-red-50 border border-red-200 rounded text-xs">
                                                            <i class="fas fa-times-circle text-red-500 text-xs"></i>
                                                            <span class="text-red-600">Voll</span>
                                                            <span class="text-red-500">(<?php echo $angemeldet; ?>/<?php echo $anzahlBenoetigt; ?>)</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-green-50 border border-green-200 rounded text-xs">
                                                            <i class="fas fa-check-circle text-green-500 text-xs"></i>
                                                            <?php if ($frei !== null): ?>
                                                                <span class="text-green-600"><?php echo $frei; ?> frei</span>
                                                            <?php else: ?>
                                                                <span class="text-green-600">Frei</span>
                                                            <?php endif; ?>
                                                            <?php if ($anzahlBenoetigt > 0): ?>
                                                                <span class="text-green-500">(<?php echo $angemeldet; ?>/<?php echo $anzahlBenoetigt; ?>)</span>
                                                            <?php else: ?>
                                                                <span class="text-green-500">(<?php echo $angemeldet; ?>)</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Zusätzliche Details -->
                                                <div class="space-y-2 text-sm text-gray-600">
                                                    <?php if ($schicht['responsible_person']): ?>
                                                        <div class="flex items-start gap-2">
                                                            <i class="fas fa-user-tie text-narrenzunft mt-0.5 flex-shrink-0"></i>
                                                            <span><strong class="text-gray-700">Verantwortung:</strong> <?php echo htmlspecialchars($schicht['responsible_person']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($schicht['location']): ?>
                                                        <div class="flex items-start gap-2">
                                                            <i class="fas fa-map-marker-alt text-narrenzunft mt-0.5 flex-shrink-0"></i>
                                                            <span><?php echo htmlspecialchars($schicht['location']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($schicht['description']): ?>
                                                        <div class="flex items-start gap-2 pt-1 border-t border-gray-100">
                                                            <i class="fas fa-info-circle text-narrenzunft mt-0.5 flex-shrink-0"></i>
                                                            <p class="text-gray-700 leading-relaxed"><?php echo htmlspecialchars($schicht['description']); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Rechte Seite: Action Button -->
                                            <div class="flex-shrink-0 sm:ml-4">
                                                <?php if (!$isFull): ?>
                                                    <button onclick="event.stopPropagation(); openSignupModal(<?php echo $schicht['id']; ?>)" 
                                                            class="w-full sm:w-auto bg-narrenzunft text-white px-5 py-3 rounded-lg hover:bg-red-800 active:bg-red-900 transition-colors text-sm sm:text-base font-semibold touch-button shadow-md hover:shadow-lg group-hover:scale-105 transition-transform whitespace-nowrap">
                                                        <i class="fas fa-plus mr-2"></i>Eintragen
                                                    </button>
                                                <?php else: ?>
                                                    <div class="w-full sm:w-auto px-5 py-3 rounded-lg bg-gray-200 text-gray-600 text-sm sm:text-base font-semibold text-center whitespace-nowrap cursor-not-allowed">
                                                        <i class="fas fa-lock mr-2"></i>Voll
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info-Box -->
        <?php if (empty($schichten)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-yellow-600 mr-3 mt-1"></i>
                    <div>
                        <h3 class="font-bold text-yellow-800 mb-2">Keine Schichten für diesen Monat gefunden</h3>
                        <p class="text-yellow-700 text-sm mb-2">
                            Die Schichten sind für <strong>Januar 2026</strong> erstellt. 
                            Bitte navigiere zum Januar 2026, um die Schichten zu sehen.
                        </p>
                        <a href="?year=2026&month=1" class="inline-block bg-narrenzunft text-white px-4 py-2 rounded-lg hover:bg-red-800 transition-colors text-sm font-medium">
                            Zu Januar 2026 springen
                        </a>
                     
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Legende -->
        <div class="bg-white rounded-lg shadow-md p-4">
            <h3 class="font-bold text-lg mb-3">Legende</h3>
            <div class="flex flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="w-4 h-4 rounded mr-2" style="background-color: #22c55e;"></div>
                    <span class="text-sm">Viele Plätze frei / Unbegrenzt</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 rounded mr-2" style="background-color: #eab308;"></div>
                    <span class="text-sm">Wenige Plätze frei</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 rounded mr-2" style="background-color: #ef4444;"></div>
                    <span class="text-sm">Voll / Keine Plätze mehr frei</span>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal für Tagesdetails -->
    <div id="dayModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-2 sm:p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[95vh] sm:max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-narrenzunft text-white p-4 sm:p-5 flex justify-between items-center z-10 shadow-md">
                <h3 class="text-lg sm:text-xl font-bold flex items-center gap-2" id="modalTitle">
                    <i class="fas fa-calendar-day"></i>
                    <span>Schichten</span>
                </h3>
                <button onclick="closeDayModal()" class="text-white hover:text-gray-200 active:opacity-70 touch-button rounded-lg p-2 hover:bg-red-800 transition-colors">
                    <i class="fas fa-times text-xl sm:text-2xl"></i>
                </button>
            </div>
            <div class="p-4 sm:p-6 bg-gray-50" id="modalContent">
                <!-- Wird dynamisch gefüllt -->
            </div>
        </div>
    </div>

    <!-- Modal für Eintragung -->
    <div id="signupModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-2 sm:p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[95vh] sm:max-h-none overflow-y-auto">
            <div class="sticky top-0 bg-narrenzunft text-white p-3 sm:p-4 flex justify-between items-center z-10">
                <h3 class="text-lg sm:text-xl font-bold">Für Schicht eintragen</h3>
                <button onclick="closeSignupModal()" class="text-white hover:text-gray-200 active:opacity-70 touch-button">
                    <i class="fas fa-times text-xl sm:text-2xl"></i>
                </button>
            </div>
            <div class="p-4 sm:p-6">
                <form id="signupForm">
                    <input type="hidden" id="signupSchichtId" name="schicht_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Vorname <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="signupVorname" name="vorname" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-narrenzunft focus:border-transparent" 
                               required autocomplete="given-name">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nachname <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="signupNachname" name="nachname" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-narrenzunft focus:border-transparent" 
                               required autocomplete="family-name">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Abteilung <span class="text-red-600">*</span>
                        </label>
                        <select id="signupAbteilung" name="abteilung_id" 
                                class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-narrenzunft focus:border-transparent" required>
                            <option value="">Bitte wählen...</option>
                            <?php foreach ($gruppen as $g): ?>
                                <option value="<?php echo $g['id']; ?>">
                                    <?php echo htmlspecialchars($g['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Bemerkung <span class="text-gray-500 text-xs">(optional)</span>
                        </label>
                        <textarea id="signupBemerkung" name="bemerkung" rows="3" 
                                  class="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-narrenzunft focus:border-transparent"
                                  placeholder="Zusätzliche Informationen..."></textarea>
                    </div>
                    <button type="submit" 
                            class="w-full bg-narrenzunft text-white py-3 rounded-lg hover:bg-red-800 transition-colors font-medium">
                        <i class="fas fa-check mr-2"></i>Eintragen
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="js/schichtplan.js"></script>
</body>
</html>

