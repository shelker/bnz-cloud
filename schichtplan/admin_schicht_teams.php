<?php
require_once 'config.php';

if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: admin_login.php');
    exit;
}

$admin = checkAdminAuth();
if (!$admin) {
    header('Location: admin_login.php');
    exit;
}

$db = getDbConnection();

/**
 * @return string|null HH:MM:SS oder null
 */
function admin_normalize_slot_time($t) {
    $t = trim((string) $t);
    if ($t === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
        return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
    }
    if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $t, $m)) {
        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    return null;
}

$flashOk = isset($_GET['saved']);
$flashErr = '';

if ($db && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_team_slots'])) {
    $shiftId = (int) ($_POST['shift_id'] ?? 0);
    if ($shiftId <= 0) {
        $flashErr = 'Ungültige Schicht-ID.';
    } else {
        $starts = $_POST['slot_start'] ?? [];
        $ends = $_POST['slot_end'] ?? [];
        $teams = $_POST['slot_team'] ?? [];
        $built = [];
        $n = max(count($starts), count($ends), count($teams));
        for ($i = 0; $i < $n; $i++) {
            $st = admin_normalize_slot_time($starts[$i] ?? '');
            $en = admin_normalize_slot_time($ends[$i] ?? '');
            if ($st === null || $en === null) {
                continue;
            }
            $tm = isset($teams[$i]) ? trim((string) $teams[$i]) : '';
            $built[] = ['start' => $st, 'end' => $en, 'team' => $tm];
        }
        if (empty($built)) {
            $stmt = $db->prepare('UPDATE shifts SET team_slots = NULL WHERE id = ?');
            $stmt->execute([$shiftId]);
        } else {
            $json = json_encode($built, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $flashErr = 'JSON konnte nicht erzeugt werden.';
            } else {
                $stmt = $db->prepare('UPDATE shifts SET team_slots = ? WHERE id = ?');
                $stmt->execute([$json, $shiftId]);
            }
        }
        if ($flashErr === '') {
            header('Location: admin_schicht_teams.php?saved=1&id=' . $shiftId);
            exit;
        }
    }
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editShift = null;
$editSlots = [];

if ($db && $editId > 0) {
    $stmt = $db->prepare('
        SELECT s.*, a.name AS task_area_name
        FROM shifts s
        JOIN task_areas a ON s.task_area_id = a.id
        WHERE s.id = ? AND s.active = 1
    ');
    $stmt->execute([$editId]);
    $editShift = $stmt->fetch();
    if ($editShift) {
        $editSlots = parse_shift_team_slots($editShift['team_slots'] ?? null);
    }
}

$shiftList = [];
if ($db) {
    $stmt = $db->query("
        SELECT s.id, s.date, s.start_time, s.end_time, s.team_slots, a.name AS task_area_name
        FROM shifts s
        JOIN task_areas a ON s.task_area_id = a.id
        WHERE s.active = 1
        ORDER BY s.date ASC, COALESCE(s.start_time, '00:00:00') ASC, s.id ASC
        LIMIT 500
    ");
    $shiftList = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Team-Zeitfenster – Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'narrenzunft': '#c4000c' } } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <header class="bg-narrenzunft text-white shadow-lg">
        <div class="container mx-auto px-3 sm:px-4 py-3 flex items-center justify-between flex-wrap gap-2">
            <h1 class="text-lg sm:text-xl font-bold"><i class="fas fa-users mr-2"></i>Team-Zeitfenster</h1>
            <div class="flex gap-2">
                <a href="admin.php" class="text-sm underline hover:no-underline">Zurück</a>
                <a href="?logout=1" class="text-sm underline hover:no-underline">Abmelden</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-3 sm:px-4 py-4 max-w-5xl">
        <?php if ($flashOk): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-900 rounded-lg text-sm">
                Gespeichert.
            </div>
        <?php endif; ?>
        <?php if ($flashErr !== ''): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-900 rounded-lg text-sm">
                <?php echo htmlspecialchars($flashErr); ?>
            </div>
        <?php endif; ?>

        <p class="text-sm text-gray-600 mb-6">
            Pro Schicht können mehrere Zeitfenster mit Team-Bezeichnung hinterlegt werden (z.&nbsp;B. abwechselnd Team&nbsp;1 / Team&nbsp;2).
            Gesamtzeiten der Schicht bleiben in <code class="bg-gray-100 px-1 rounded">start_time</code> / <code class="bg-gray-100 px-1 rounded">end_time</code> (z.&nbsp;B. 18:00–02:00). Ohne Einträge hier ist die Darstellung wie bisher nur mit Gesamtzeit.
        </p>

        <?php if (!$editShift && $editId > 0): ?>
            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-sm">Schicht nicht gefunden.</div>
        <?php elseif ($editShift): ?>
            <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-8">
                <h2 class="text-lg font-bold mb-1">Schicht bearbeiten</h2>
                <p class="text-sm text-gray-600 mb-4">
                    <?php echo htmlspecialchars(date('d.m.Y', strtotime($editShift['date']))); ?> ·
                    <?php echo htmlspecialchars($editShift['task_area_name']); ?>
                    <?php if ($editShift['start_time']): ?>
                        · <?php echo substr($editShift['start_time'], 0, 5); ?>
                        <?php if ($editShift['end_time']): ?>–<?php echo substr($editShift['end_time'], 0, 5); ?><?php endif; ?>
                    <?php endif; ?>
                </p>

                <form method="post" id="slotsForm">
                    <input type="hidden" name="save_team_slots" value="1">
                    <input type="hidden" name="shift_id" value="<?php echo (int) $editShift['id']; ?>">

                    <div id="slotRows" class="space-y-3 mb-4">
                        <?php
                        $rows = $editSlots;
                        if (empty($rows)) {
                            $rows = [['start' => '', 'end' => '', 'team' => '']];
                        }
                        foreach ($rows as $idx => $row):
                        ?>
                            <div class="slot-row flex flex-wrap gap-2 items-end border border-gray-200 rounded-lg p-3 bg-gray-50">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Von</label>
                                    <input type="time" name="slot_start[]" value="<?php echo htmlspecialchars($row['start']); ?>"
                                           class="border rounded px-2 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Bis</label>
                                    <input type="time" name="slot_end[]" value="<?php echo htmlspecialchars($row['end']); ?>"
                                           class="border rounded px-2 py-1.5 text-sm">
                                </div>
                                <div class="flex-1 min-w-[140px]">
                                    <label class="block text-xs text-gray-500 mb-1">Team / Hinweis</label>
                                    <input type="text" name="slot_team[]" value="<?php echo htmlspecialchars($row['team']); ?>"
                                           placeholder="z. B. Team 1" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <button type="button" class="remove-slot text-red-600 text-sm px-2 py-1 hover:bg-red-50 rounded" title="Zeile entfernen">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="addSlotRow" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded text-sm">
                            <i class="fas fa-plus mr-1"></i>Zeile hinzufügen
                        </button>
                        <button type="submit" class="bg-narrenzunft text-white px-4 py-2 rounded text-sm hover:bg-red-800">
                            Speichern
                        </button>
                        <a href="admin_schicht_teams.php" class="inline-flex items-center px-4 py-2 rounded text-sm border border-gray-300 bg-white hover:bg-gray-50">
                            Zur Liste
                        </a>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">
                        Alle Zeilen ohne Von/Bis werden ignoriert. Keine gültige Zeile = Team-Zeitfenster werden gelöscht (nur Gesamtzeit bleibt).
                    </p>
                </form>
            </div>

            <template id="slotRowTemplate">
                <div class="slot-row flex flex-wrap gap-2 items-end border border-gray-200 rounded-lg p-3 bg-gray-50">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Von</label>
                        <input type="time" name="slot_start[]" value="" class="border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Bis</label>
                        <input type="time" name="slot_end[]" value="" class="border rounded px-2 py-1.5 text-sm">
                    </div>
                    <div class="flex-1 min-w-[140px]">
                        <label class="block text-xs text-gray-500 mb-1">Team / Hinweis</label>
                        <input type="text" name="slot_team[]" value="" placeholder="z. B. Team 1" class="border rounded px-2 py-1.5 text-sm w-full">
                    </div>
                    <button type="button" class="remove-slot text-red-600 text-sm px-2 py-1 hover:bg-red-50 rounded" title="Zeile entfernen">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </template>
            <script>
                (function () {
                    var container = document.getElementById('slotRows');
                    var tpl = document.getElementById('slotRowTemplate');
                    document.getElementById('addSlotRow').addEventListener('click', function () {
                        container.appendChild(tpl.content.cloneNode(true));
                    });
                    container.addEventListener('click', function (e) {
                        var btn = e.target.closest('.remove-slot');
                        if (!btn) return;
                        var row = btn.closest('.slot-row');
                        if (row && container.querySelectorAll('.slot-row').length > 1) row.remove();
                    });
                })();
            </script>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h2 class="text-lg font-bold mb-4">Schichten</h2>
            <?php if (empty($shiftList)): ?>
                <p class="text-gray-500 text-sm">Keine Schichten.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-gray-600">
                                <th class="py-2 pr-2">Datum</th>
                                <th class="py-2 pr-2">Zeit</th>
                                <th class="py-2 pr-2">Bereich</th>
                                <th class="py-2 pr-2">Team-Fenster</th>
                                <th class="py-2">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shiftList as $s): ?>
                                <?php
                                $slots = parse_shift_team_slots($s['team_slots'] ?? null);
                                $slotSummary = '–';
                                if (!empty($slots)) {
                                    $parts = [];
                                    foreach ($slots as $sl) {
                                        $parts[] = $sl['start'] . '–' . $sl['end'] . ($sl['team'] !== '' ? ' ' . $sl['team'] : '');
                                    }
                                    $slotSummary = implode('; ', $parts);
                                }
                                ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-2 pr-2 whitespace-nowrap"><?php echo htmlspecialchars(date('d.m.Y', strtotime($s['date']))); ?></td>
                                    <td class="py-2 pr-2 whitespace-nowrap">
                                        <?php
                                        if ($s['start_time']) {
                                            echo substr($s['start_time'], 0, 5);
                                            if ($s['end_time']) {
                                                echo '–' . substr($s['end_time'], 0, 5);
                                            }
                                        } else {
                                            echo '–';
                                        }
                                        ?>
                                    </td>
                                    <td class="py-2 pr-2"><?php echo htmlspecialchars($s['task_area_name']); ?></td>
                                    <td class="py-2 pr-2 max-w-xs truncate" title="<?php echo htmlspecialchars($slotSummary); ?>"><?php echo htmlspecialchars($slotSummary); ?></td>
                                    <td class="py-2">
                                        <a href="admin_schicht_teams.php?id=<?php echo (int) $s['id']; ?>"
                                           class="text-narrenzunft font-medium hover:underline">Bearbeiten</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
