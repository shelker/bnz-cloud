-- Migration: Umbenennung von rollen zu aufgabenbereiche
-- Bedburger Narrenzunft - Schichtplan-System
-- 
-- Diese Migration benennt die Tabelle "rollen" in "aufgabenbereiche" um
-- und die Spalte "rolle_id" in "aufgabenbereich_id"

-- ============================================
-- 1. Foreign Key Constraints entfernen
-- ============================================

-- Foreign Key von schichten.rolle_id entfernen
SET @db_name = DATABASE();
SET @fk_exists = 0;

SELECT COUNT(*) INTO @fk_exists
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'schichten'
  AND CONSTRAINT_NAME LIKE '%rolle%'
  AND REFERENCED_TABLE_NAME = 'rollen';

SET @sql = IF(@fk_exists > 0,
  'ALTER TABLE `schichten` DROP FOREIGN KEY `schichten_ibfk_1`',
  'SELECT "Foreign Key schichten.rolle_id does not exist" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 2. Tabelle umbenennen
-- ============================================

-- Tabelle rollen in aufgabenbereiche umbenennen
RENAME TABLE `rollen` TO `aufgabenbereiche`;

-- ============================================
-- 3. Spalte umbenennen
-- ============================================

-- Spalte rolle_id in aufgabenbereich_id umbenennen
ALTER TABLE `schichten` CHANGE COLUMN `rolle_id` `aufgabenbereich_id` INT(11) NOT NULL;

-- Index umbenennen (falls vorhanden)
ALTER TABLE `schichten` DROP INDEX IF EXISTS `idx_rolle`;
ALTER TABLE `schichten` ADD INDEX `idx_aufgabenbereich` (`aufgabenbereich_id`);

-- ============================================
-- 4. Foreign Key wieder hinzufügen
-- ============================================

-- Foreign Key wieder hinzufügen mit neuem Namen
SET @fk_exists = 0;

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

SELECT "Migration erfolgreich abgeschlossen: rollen -> aufgabenbereiche, rolle_id -> aufgabenbereich_id" AS message;

