<?php
require_once 'config.php';

// Logout verarbeiten BEVOR Admin-Check
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: admin_login.php');
    exit;
}

// Admin-Check
$admin = checkAdminAuth();
if (!$admin) {
    header('Location: admin_login.php');
    exit;
}

$db = getDbConnection();

// Alle Eintragungen laden
$eintragungen = [];
if ($db) {
    $stmt = $db->query("
        SELECT sz.*, 
               s.date, s.start_time, s.end_time, s.team_slots, s.responsible_person,
               a.name as task_area_name, a.color as task_area_color,
               e.first_name, e.last_name, e.is_group,
               g.name as group_name
        FROM shift_assignments sz
        JOIN shifts s ON sz.shift_id = s.id
        JOIN task_areas a ON s.task_area_id = a.id
        JOIN employees e ON sz.employee_id = e.id
        LEFT JOIN groups g ON e.group_id = g.id
        WHERE sz.status != 'cancelled'
        ORDER BY s.date DESC, s.start_time DESC, sz.registered_at DESC
    ");
    $eintragungen = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex, nofollow">
    <title>Eintragungen verwalten - Admin</title>
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
        @media (max-width: 768px) {
            .table-responsive {
                display: none;
            }
            .card-view {
                display: block;
            }
        }
        @media (min-width: 769px) {
            .table-responsive {
                display: block;
            }
            .card-view {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-narrenzunft text-white shadow-lg">
        <div class="container mx-auto px-3 sm:px-4 py-3 sm:py-4">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center min-w-0 flex-1">
                    <img src="../assets/bnz-logo-300.png" alt="BNZ Logo" class="h-8 sm:h-12 mr-2 sm:mr-3 flex-shrink-0">
                    <h1 class="text-lg sm:text-2xl font-bold truncate">Eintragungen</h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <a href="admin.php" class="text-xs sm:text-sm hover:text-gray-200 touch-button flex items-center px-2 sm:px-0">
                        <i class="fas fa-arrow-left sm:mr-1"></i>
                        <span class="hidden sm:inline">Zurück</span>
                    </a>
                    <a href="?logout=1" class="bg-red-800 hover:bg-red-900 active:bg-red-950 px-3 sm:px-4 py-2 rounded-lg text-xs sm:text-sm transition-colors touch-button">
                        <i class="fas fa-sign-out-alt sm:mr-2"></i>
                        <span class="hidden sm:inline">Abmelden</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-3 sm:px-4 py-4 sm:py-6">
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                <h2 class="text-lg sm:text-xl font-bold">Alle Eintragungen</h2>
                <span class="text-xs sm:text-sm text-gray-600"><?php echo count($eintragungen); ?> Eintragungen</span>
            </div>
            
            <?php if (empty($eintragungen)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p>Keine Eintragungen vorhanden.</p>
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="table-responsive overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b">
                                <th class="text-left p-3 text-sm font-semibold">Datum</th>
                                <th class="text-left p-3 text-sm font-semibold">Zeit</th>
                                <th class="text-left p-3 text-sm font-semibold">Aufgabenbereich</th>
                                <th class="text-left p-3 text-sm font-semibold">Person</th>
                                <th class="text-left p-3 text-sm font-semibold">Abteilung</th>
                                <th class="text-left p-3 text-sm font-semibold">Bemerkung</th>
                                <th class="text-left p-3 text-sm font-semibold">Eingetragen am</th>
                                <th class="text-center p-3 text-sm font-semibold">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eintragungen as $eintragung): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 text-sm"><?php echo date('d.m.Y', strtotime($eintragung['date'])); ?></td>
                                    <td class="p-3 text-sm">
                                        <?php 
                                        if ($eintragung['start_time']) {
                                            echo substr($eintragung['start_time'], 0, 5);
                                            if ($eintragung['end_time']) {
                                                echo ' - ' . substr($eintragung['end_time'], 0, 5);
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        $ts = parse_shift_team_slots($eintragung['team_slots'] ?? null);
                                        if (!empty($ts)) {
                                            echo '<div class="text-xs text-gray-500 mt-1">';
                                            foreach ($ts as $sl) {
                                                echo htmlspecialchars($sl['start'] . '–' . $sl['end'] . ($sl['team'] !== '' ? ' · ' . $sl['team'] : '')) . '<br>';
                                            }
                                            echo '</div>';
                                        }
                                        ?>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 rounded text-white text-xs" 
                                              style="background-color: <?php echo htmlspecialchars($eintragung['task_area_color']); ?>">
                                            <?php echo htmlspecialchars($eintragung['task_area_name']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm">
                                        <?php 
                                        if ($eintragung['is_group'] == 1) {
                                            echo htmlspecialchars($eintragung['group_name']);
                                        } else {
                                            echo htmlspecialchars(($eintragung['first_name'] ?? '') . ' ' . ($eintragung['last_name'] ?? ''));
                                        }
                                        ?>
                                    </td>
                                    <td class="p-3 text-sm text-gray-600">
                                        <?php echo $eintragung['is_group'] == 1 ? '-' : htmlspecialchars($eintragung['group_name'] ?? '-'); ?>
                                    </td>
                                    <td class="p-3 text-sm text-gray-600">
                                        <?php echo htmlspecialchars($eintragung['note'] ?: '-'); ?>
                                    </td>
                                    <td class="p-3 text-sm text-gray-600">
                                        <?php echo date('d.m.Y H:i', strtotime($eintragung['registered_at'])); ?>
                                    </td>
                                    <td class="p-3 text-center">
                                        <button onclick="deleteEntry(<?php echo $eintragung['id']; ?>)" 
                                                class="bg-red-600 hover:bg-red-700 active:bg-red-800 text-white px-3 py-2 rounded text-sm transition-colors touch-button">
                                            <i class="fas fa-trash mr-1"></i>Löschen
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Card View -->
                <div class="card-view space-y-4">
                    <?php foreach ($eintragungen as $eintragung): ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                                        <span class="px-2 py-1 rounded text-white text-xs font-medium" 
                                              style="background-color: <?php echo htmlspecialchars($eintragung['task_area_color']); ?>">
                                            <?php echo htmlspecialchars($eintragung['task_area_name']); ?>
                                        </span>
                                        <span class="text-sm font-semibold text-gray-800">
                                            <?php echo date('d.m.Y', strtotime($eintragung['date'])); ?>
                                        </span>
                                        <?php if ($eintragung['start_time']): ?>
                                            <span class="text-sm text-gray-600">
                                                <?php echo substr($eintragung['start_time'], 0, 5); ?>
                                                <?php if ($eintragung['end_time']): ?>
                                                    - <?php echo substr($eintragung['end_time'], 0, 5); ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php
                                        $tsM = parse_shift_team_slots($eintragung['team_slots'] ?? null);
                                        if (!empty($tsM)):
                                        ?>
                                            <div class="text-xs text-gray-500 w-full mt-1">
                                                <?php foreach ($tsM as $sl): ?>
                                                    <div><?php echo htmlspecialchars($sl['start'] . '–' . $sl['end'] . ($sl['team'] !== '' ? ' · ' . $sl['team'] : '')); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="text-sm">
                                            <span class="font-medium">Person:</span>
                                            <?php 
                                            if ($eintragung['is_group'] == 1) {
                                                echo htmlspecialchars($eintragung['group_name']);
                                            } else {
                                                echo htmlspecialchars(($eintragung['first_name'] ?? '') . ' ' . ($eintragung['last_name'] ?? ''));
                                            }
                                            ?>
                                        </div>
                                        <?php if ($eintragung['is_group'] != 1 && $eintragung['group_name']): ?>
                                            <div class="text-sm text-gray-600">
                                                <span class="font-medium">Abteilung:</span> <?php echo htmlspecialchars($eintragung['group_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($eintragung['note']): ?>
                                            <div class="text-sm text-gray-600">
                                                <span class="font-medium">Bemerkung:</span> <?php echo htmlspecialchars($eintragung['note']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-500">
                                            Eingetragen: <?php echo date('d.m.Y H:i', strtotime($eintragung['registered_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button onclick="deleteEntry(<?php echo $eintragung['id']; ?>)" 
                                    class="w-full bg-red-600 hover:bg-red-700 active:bg-red-800 text-white px-4 py-3 rounded-lg text-sm font-medium transition-colors touch-button">
                                <i class="fas fa-trash mr-2"></i>Löschen
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        async function deleteEntry(zuweisungId) {
            if (!confirm('Möchten Sie diese Eintragung wirklich löschen?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_entry');
            formData.append('zuweisung_id', zuweisungId);
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Eintragung erfolgreich gelöscht.');
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
                }
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Löschen. Bitte versuchen Sie es erneut.');
            }
        }
    </script>
</body>
</html>

