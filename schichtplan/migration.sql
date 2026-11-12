-- Migration SQL für Schichtplan-System
-- Bedburger Narrenzunft - Personalplanung 2026
-- 
-- Diese Migration kann auf eine bestehende Datenbank angewendet werden
-- ohne Datenverlust. Bestehende Tabellen werden nur erweitert, nicht gelöscht.

-- ============================================
-- 1. Tabellen erstellen (falls nicht vorhanden)
-- ============================================

-- Tabelle: Gruppen (z.B. Marketenderinnen, Fanfaren, etc.)
CREATE TABLE IF NOT EXISTS `gruppen` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `beschreibung` TEXT DEFAULT NULL,
  `aktiv` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: Aufgabenbereiche
CREATE TABLE IF NOT EXISTS `aufgabenbereiche` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `beschreibung` TEXT DEFAULT NULL,
  `farbe` VARCHAR(7) DEFAULT '#c4000c',
  `sortierung` INT(11) DEFAULT 0,
  `aktiv` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: Mitarbeiter (kann einzelne Personen oder Gruppen sein)
CREATE TABLE IF NOT EXISTS `mitarbeiter` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vorname` VARCHAR(100) DEFAULT NULL,
  `nachname` VARCHAR(100) DEFAULT NULL,
  `gruppe_id` INT(11) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `telefon` VARCHAR(50) DEFAULT NULL,
  `ist_gruppe` TINYINT(1) DEFAULT 0,
  `aktiv` TINYINT(1) DEFAULT 1,
  `erstellt_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_aktiv` (`aktiv`),
  INDEX `idx_gruppe` (`gruppe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: Schichten
CREATE TABLE IF NOT EXISTS `schichten` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `datum` DATE NOT NULL,
  `startzeit` TIME DEFAULT NULL,
  `endzeit` TIME DEFAULT NULL,
  `aufgabenbereich_id` INT(11) NOT NULL,
  `anzahl_benoetigt` INT(11) DEFAULT 1,
  `verantwortlicher` VARCHAR(255) DEFAULT NULL,
  `beschreibung` TEXT DEFAULT NULL,
  `ort` VARCHAR(255) DEFAULT NULL,
  `aktiv` TINYINT(1) DEFAULT 1,
  `erstellt_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_datum` (`datum`),
  INDEX `idx_aufgabenbereich` (`aufgabenbereich_id`),
  INDEX `idx_aktiv` (`aktiv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: Schichtzuweisungen (Eintragungen)
CREATE TABLE IF NOT EXISTS `schichtzuweisungen` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `schicht_id` INT(11) NOT NULL,
  `mitarbeiter_id` INT(11) NOT NULL,
  `status` ENUM('angemeldet', 'bestätigt', 'abgesagt') DEFAULT 'angemeldet',
  `bemerkung` TEXT DEFAULT NULL,
  `angemeldet_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_schicht_mitarbeiter` (`schicht_id`, `mitarbeiter_id`),
  INDEX `idx_schicht` (`schicht_id`),
  INDEX `idx_mitarbeiter` (`mitarbeiter_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. Fremdschlüssel hinzufügen (falls nicht vorhanden)
-- ============================================

-- Prüfe ob Fremdschlüssel bereits existieren und füge sie hinzu falls nicht
SET @db_name = DATABASE();
SET @fk_exists = 0;

-- Foreign Key: mitarbeiter.gruppe_id -> gruppen.id
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'mitarbeiter'
  AND CONSTRAINT_NAME = 'mitarbeiter_ibfk_1'
  AND REFERENCED_TABLE_NAME = 'gruppen';

SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `mitarbeiter` ADD CONSTRAINT `mitarbeiter_ibfk_1` FOREIGN KEY (`gruppe_id`) REFERENCES `gruppen`(`id`) ON DELETE SET NULL',
  'SELECT "Foreign Key mitarbeiter.gruppe_id already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Foreign Key: schichten.aufgabenbereich_id -> aufgabenbereiche.id
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'schichten'
  AND CONSTRAINT_NAME LIKE '%aufgabenbereich%'
  AND REFERENCED_TABLE_NAME = 'aufgabenbereiche';

SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `schichten` ADD CONSTRAINT `schichten_ibfk_aufgabenbereich` FOREIGN KEY (`aufgabenbereich_id`) REFERENCES `aufgabenbereiche`(`id`) ON DELETE RESTRICT',
  'SELECT "Foreign Key schichten.aufgabenbereich_id already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Foreign Key: schichtzuweisungen.schicht_id -> schichten.id
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'schichtzuweisungen'
  AND CONSTRAINT_NAME = 'schichtzuweisungen_ibfk_1'
  AND REFERENCED_TABLE_NAME = 'schichten';

SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `schichtzuweisungen` ADD CONSTRAINT `schichtzuweisungen_ibfk_1` FOREIGN KEY (`schicht_id`) REFERENCES `schichten`(`id`) ON DELETE CASCADE',
  'SELECT "Foreign Key schichtzuweisungen.schicht_id already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Foreign Key: schichtzuweisungen.mitarbeiter_id -> mitarbeiter.id
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'schichtzuweisungen'
  AND CONSTRAINT_NAME = 'schichtzuweisungen_ibfk_2'
  AND REFERENCED_TABLE_NAME = 'mitarbeiter';

SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `schichtzuweisungen` ADD CONSTRAINT `schichtzuweisungen_ibfk_2` FOREIGN KEY (`mitarbeiter_id`) REFERENCES `mitarbeiter`(`id`) ON DELETE CASCADE',
  'SELECT "Foreign Key schichtzuweisungen.mitarbeiter_id already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. Bestehende Tabellen erweitern (falls nötig)
-- ============================================

-- Prüfe und füge fehlende Spalten zu bestehenden Tabellen hinzu

-- Mitarbeiter-Tabelle: gruppe_id hinzufügen falls nicht vorhanden
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'mitarbeiter'
  AND COLUMN_NAME = 'gruppe_id';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `mitarbeiter` ADD COLUMN `gruppe_id` INT(11) DEFAULT NULL AFTER `nachname`, ADD INDEX `idx_gruppe` (`gruppe_id`)',
  'SELECT "Column mitarbeiter.gruppe_id already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mitarbeiter-Tabelle: ist_gruppe hinzufügen falls nicht vorhanden
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'mitarbeiter'
  AND COLUMN_NAME = 'ist_gruppe';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `mitarbeiter` ADD COLUMN `ist_gruppe` TINYINT(1) DEFAULT 0 AFTER `telefon`',
  'SELECT "Column mitarbeiter.ist_gruppe already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Schichten-Tabelle: verantwortlicher hinzufügen falls nicht vorhanden
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'schichten'
  AND COLUMN_NAME = 'verantwortlicher';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `schichten` ADD COLUMN `verantwortlicher` VARCHAR(255) DEFAULT NULL AFTER `anzahl_benoetigt`',
  'SELECT "Column schichten.verantwortlicher already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Schichten-Tabelle: startzeit und endzeit als nullable falls nicht bereits so
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'schichten'
  AND COLUMN_NAME = 'startzeit'
  AND IS_NULLABLE = 'NO';

SET @sql = IF(@col_exists > 0,
  'ALTER TABLE `schichten` MODIFY COLUMN `startzeit` TIME DEFAULT NULL',
  'SELECT "Column schichten.startzeit already nullable" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 4. Beispiel-Daten einfügen (nur wenn Tabellen leer sind)
-- ============================================

-- Gruppen einfügen (nur wenn noch keine vorhanden)
INSERT INTO `gruppen` (`name`, `beschreibung`)
SELECT * FROM (
    SELECT 'Marketenderinnen' AS name, 'Marketenderinnen Gruppe' AS beschreibung
    UNION ALL SELECT 'Fanfaren', 'Fanfarenzug'
    UNION ALL SELECT 'Ofiziere', 'Offiziere'
    UNION ALL SELECT 'Ofizierstöchter', 'Offizierstöchter'
    UNION ALL SELECT 'Funken', 'Funkenmariechen'
    UNION ALL SELECT 'Elferrat', 'Elferrat'
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `gruppen` WHERE `gruppen`.`name` = tmp.name);

-- Aufgabenbereiche einfügen (nur wenn noch keine vorhanden)
INSERT INTO `aufgabenbereiche` (`name`, `beschreibung`, `farbe`, `sortierung`)
SELECT * FROM (
    SELECT 'Elferrat' AS name, 'Elferrat Bereich' AS beschreibung, '#c4000c' AS farbe, 1 AS sortierung
    UNION ALL SELECT 'Kasse', 'Kassenbereich', '#0066cc', 2
    UNION ALL SELECT 'Theke', 'Thekenbereich', '#00aa00', 3
    UNION ALL SELECT 'Bedienung / Service', 'Bedienung und Service', '#ff9900', 4
    UNION ALL SELECT 'Garderobe', 'Garderobenbereich', '#9966cc', 5
    UNION ALL SELECT 'Künstlerbetreuung', 'Betreuung der Künstler', '#cc6600', 6
    UNION ALL SELECT 'Aufbau / Schmücken', 'Aufbau und Dekoration', '#666666', 7
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `aufgabenbereiche` WHERE `aufgabenbereiche`.`name` = tmp.name);

-- Gruppen als Mitarbeiter eintragen (nur wenn noch nicht vorhanden)
INSERT INTO `mitarbeiter` (`gruppe_id`, `ist_gruppe`, `aktiv`)
SELECT g.id, 1, 1
FROM `gruppen` g
WHERE NOT EXISTS (
    SELECT 1 FROM `mitarbeiter` m 
    WHERE m.gruppe_id = g.id AND m.ist_gruppe = 1
);

-- ============================================
-- 5. Schichten für Redner-Sitzung 22.01.2026 einfügen
-- ============================================

-- Aufbau/Schmücken - Mittwoch 16:00
INSERT INTO `schichten` (`datum`, `startzeit`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `beschreibung`)
SELECT '2026-01-21', '16:00:00', a.id, 0, 'zusagen Personal', 'Aufbau und Schmücken'
FROM `aufgabenbereiche` a
WHERE a.name = 'Aufbau / Schmücken'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-21' 
        AND s.startzeit = '16:00:00' 
        AND s.aufgabenbereich_id = a.id
  );

-- Theke - Donnerstag Treffen 17:00 Uhr
INSERT INTO `schichten` (`datum`, `startzeit`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `beschreibung`)
SELECT '2026-01-22', '17:00:00', r.id, 0, 'zusagen Personal', 'Treffen 17:00 Uhr'
FROM `aufgabenbereiche` a
WHERE a.name = 'Theke'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '17:00:00' 
        AND s.aufgabenbereich_id = a.id
  );

-- Bedienung/Service - Donnerstag Treffen 17:00 Uhr
INSERT INTO `schichten` (`datum`, `startzeit`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `beschreibung`)
SELECT '2026-01-22', '17:00:00', r.id, 0, 'zusagen Personal', 'Treffen 17:00 Uhr'
FROM `aufgabenbereiche` a
WHERE a.name = 'Bedienung / Service'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '17:00:00' 
        AND s.aufgabenbereich_id = a.id
  );

-- Garderobe - Donnerstag Treffen 17:00 Uhr
INSERT INTO `schichten` (`datum`, `startzeit`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `beschreibung`)
SELECT '2026-01-22', '17:00:00', r.id, 0, 'zusagen Personal', 'Treffen 17:00 Uhr'
FROM `aufgabenbereiche` a
WHERE a.name = 'Garderobe'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '17:00:00' 
        AND s.aufgabenbereich_id = a.id
  );

-- Kasse - Donnerstag Treffen 17:00 Uhr
INSERT INTO `schichten` (`datum`, `startzeit`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `beschreibung`)
SELECT '2026-01-22', '17:00:00', r.id, 0, 'zusagen Personal', 'Treffen 17:00 Uhr'
FROM `aufgabenbereiche` a
WHERE a.name = 'Kasse'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '17:00:00' 
        AND s.aufgabenbereich_id = a.id
  );

-- Feste Zuweisungen (bereits zugewiesene Bereiche)
-- Elferrat: 11 Personen, Verantwortlicher: Robert L.
INSERT INTO `schichten` (`datum`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `beschreibung`, `startzeit`)
SELECT '2026-01-22', a.id, 11, 'Robert L.', 'Tisch, Blumen, Bütt', '18:00:00'
FROM `aufgabenbereiche` a
WHERE a.name = 'Elferrat'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '18:00:00' 
        AND s.aufgabenbereich_id = a.id
        AND s.verantwortlicher = 'Robert L.'
  );

-- Kasse: 4 Personen, Verantwortlicher: Melissa
INSERT INTO `schichten` (`datum`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `beschreibung`, `startzeit`)
SELECT '2026-01-22', a.id, 4, 'Melissa', 'Wechselgeld, 2 Kassen', '18:00:00'
FROM `aufgabenbereiche` a
WHERE a.name = 'Kasse'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '18:00:00' 
        AND s.aufgabenbereich_id = a.id
        AND s.verantwortlicher = 'Melissa'
  );

-- Theke: 6 Personen, Verantwortlicher: Tim L.
INSERT INTO `schichten` (`datum`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `startzeit`)
SELECT '2026-01-22', a.id, 6, 'Tim L.', '18:00:00'
FROM `aufgabenbereiche` a
WHERE a.name = 'Theke'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '18:00:00' 
        AND s.aufgabenbereich_id = a.id
        AND s.verantwortlicher = 'Tim L.'
  );

-- Bedienung/Service: 10 Personen, Verantwortlicher: Andrea
INSERT INTO `schichten` (`datum`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `startzeit`)
SELECT '2026-01-22', a.id, 10, 'Andrea', '18:00:00'
FROM `aufgabenbereiche` a
WHERE a.name = 'Bedienung / Service'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '18:00:00' 
        AND s.aufgabenbereich_id = a.id
        AND s.verantwortlicher = 'Andrea'
  );

-- Garderobe: 6 Personen, Verantwortlicher: Felix Fesser
INSERT INTO `schichten` (`datum`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `beschreibung`, `startzeit`)
SELECT '2026-01-22', a.id, 6, 'Felix Fesser', 'Garderoben Kisten / Marken(Robert)', '18:00:00'
FROM `aufgabenbereiche` a
WHERE a.name = 'Garderobe'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '18:00:00' 
        AND s.aufgabenbereich_id = a.id
        AND s.verantwortlicher = 'Felix Fesser'
  );

-- Künstlerbetreuung: 1 Person, Verantwortlicher: Wolfgang Correnz
INSERT INTO `schichten` (`datum`, `aufgabenbereich_id`, `anzahl_benoetigt`, `verantwortlicher`, `startzeit`)
SELECT '2026-01-22', a.id, 1, 'Wolfgang Correnz', '18:00:00'
FROM `aufgabenbereiche` a
WHERE a.name = 'Künstlerbetreuung'
  AND NOT EXISTS (
      SELECT 1 FROM `schichten` s 
      WHERE s.datum = '2026-01-22' 
        AND s.startzeit = '18:00:00' 
        AND s.aufgabenbereich_id = a.id
        AND s.verantwortlicher = 'Wolfgang Correnz'
  );

-- ============================================
-- Migration abgeschlossen
-- ============================================

SELECT 'Migration erfolgreich abgeschlossen!' AS status;

