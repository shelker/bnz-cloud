-- Migration SQL für Admin-System
-- Bedburger Narrenzunft - Schichtplan
-- 
-- Diese Migration fügt Admin-Funktionalität hinzu
-- Basiert auf bestehenden Tabellen

-- ============================================
-- 1. Admin-Tabelle erstellen (falls nicht vorhanden)
-- ============================================

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `benutzername` VARCHAR(100) NOT NULL,
  `passwort_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `vorname` VARCHAR(100) DEFAULT NULL,
  `nachname` VARCHAR(100) DEFAULT NULL,
  `aktiv` TINYINT(1) DEFAULT 1,
  `erstellt_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `zuletzt_angemeldet` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_benutzername` (`benutzername`),
  INDEX `idx_aktiv` (`aktiv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. Admin-Sessions-Tabelle erstellen (falls nicht vorhanden)
-- ============================================

CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) NOT NULL,
  `session_id` VARCHAR(255) NOT NULL,
  `ip_adresse` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `erstellt_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `abgelaufen_am` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session` (`session_id`),
  INDEX `idx_admin` (`admin_id`),
  INDEX `idx_abgelaufen` (`abgelaufen_am`),
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. Standard-Admin erstellen (nur wenn noch keine Admins vorhanden)
-- ============================================
-- Passwort: admin123 (Bitte nach dem ersten Login ändern!)
-- Passwort-Hash wird mit password_hash() in PHP erstellt

-- Prüfe ob bereits Admins existieren
SET @admin_count = (SELECT COUNT(*) FROM `admins`);

-- Standard-Admin nur einfügen wenn noch keine vorhanden
-- Passwort: admin123
SET @sql = IF(@admin_count = 0,
  'INSERT INTO `admins` (`benutzername`, `passwort_hash`, `email`, `vorname`, `nachname`, `aktiv`) VALUES (''admin'', ''$2y$10$nSaDa9q7FbkCYuX.bigaN.WZsufydnYsA7m6JkJs6WwZPI7FMNCtG'', NULL, ''Admin'', ''Benutzer'', 1)',
  'SELECT "Admin bereits vorhanden, überspringe Standard-Admin" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 4. Spalte für Admin-ID in schichtzuweisungen hinzufügen (optional, für Audit)
-- ============================================

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'schichtzuweisungen'
  AND COLUMN_NAME = 'geloescht_von_admin_id';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `schichtzuweisungen` ADD COLUMN `geloescht_von_admin_id` INT(11) DEFAULT NULL AFTER `geaendert_am`, ADD INDEX `idx_admin` (`geloescht_von_admin_id`)',
  'SELECT "Column schichtzuweisungen.geloescht_von_admin_id already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Foreign Key für geloescht_von_admin_id (optional)
SET @fk_exists = 0;
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'schichtzuweisungen'
  AND CONSTRAINT_NAME = 'schichtzuweisungen_ibfk_admin'
  AND REFERENCED_TABLE_NAME = 'admins';

SET @sql = IF(@fk_exists = 0 AND @col_exists = 0,
  'ALTER TABLE `schichtzuweisungen` ADD CONSTRAINT `schichtzuweisungen_ibfk_admin` FOREIGN KEY (`geloescht_von_admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL',
  'SELECT "Foreign Key schichtzuweisungen.geloescht_von_admin_id already exists or column does not exist" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Migration abgeschlossen
-- ============================================

SELECT 'Admin-Migration erfolgreich abgeschlossen!' AS status;
SELECT 'Standard-Admin: benutzername=admin, passwort=admin123' AS hinweis;
SELECT 'BITTE PASSWORT NACH DEM ERSTEN LOGIN ÄNDERN!' AS warnung;

