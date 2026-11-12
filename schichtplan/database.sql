-- SQL CREATE Queries für Schichtplan-System
-- Bedburger Narrenzunft - Personalplanung 2026

-- Table: Groups (e.g. Marketenderinnen, Fanfaren, etc.)
CREATE TABLE IF NOT EXISTS `groups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Employees (can be individual persons or groups)
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) DEFAULT NULL,
  `group_id` INT(11) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `is_group` TINYINT(1) DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_active` (`active`),
  INDEX `idx_group` (`group_id`),
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Task Areas
CREATE TABLE IF NOT EXISTS `task_areas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#c4000c',
  `sort_order` INT(11) DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Shifts
CREATE TABLE IF NOT EXISTS `shifts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL,
  `start_time` TIME DEFAULT NULL,
  `end_time` TIME DEFAULT NULL,
  `team_slots` JSON DEFAULT NULL COMMENT 'Optional: Team-Zeitfenster [{"start":"HH:MM:SS","end":"…","team":"…"}]',
  `task_area_id` INT(11) NOT NULL,
  `required_count` INT(11) DEFAULT 1,
  `responsible_person` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_date` (`date`),
  INDEX `idx_task_area` (`task_area_id`),
  INDEX `idx_active` (`active`),
  FOREIGN KEY (`task_area_id`) REFERENCES `task_areas`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Shift Assignments (Registrations)
CREATE TABLE IF NOT EXISTS `shift_assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `shift_id` INT(11) NOT NULL,
  `employee_id` INT(11) NOT NULL,
  `status` ENUM('registered', 'confirmed', 'cancelled') DEFAULT 'registered',
  `note` TEXT DEFAULT NULL,
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shift_employee` (`shift_id`, `employee_id`),
  INDEX `idx_shift` (`shift_id`),
  INDEX `idx_employee` (`employee_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Data: Insert Groups (based on Excel)
INSERT INTO `groups` (`name`, `description`) VALUES
('Marketenderinnen', 'Marketenderinnen Gruppe'),
('Fanfaren', 'Fanfarenzug'),
('Ofiziere', 'Offiziere'),
('Ofizierstöchter', 'Offizierstöchter'),
('Funken', 'Funkenmariechen'),
('Elferrat', 'Elferrat');

-- Sample Data: Insert Task Areas (based on Excel)
INSERT INTO `task_areas` (`name`, `description`, `color`, `sort_order`) VALUES
('Elferrat', 'Elferrat Bereich', '#c4000c', 1),
('Kasse', 'Kassenbereich', '#0066cc', 2),
('Theke', 'Thekenbereich', '#00aa00', 3),
('Bedienung / Service', 'Bedienung und Service', '#ff9900', 4),
('Garderobe', 'Garderobenbereich', '#9966cc', 5),
('Künstlerbetreuung', 'Betreuung der Künstler', '#cc6600', 6),
('Aufbau / Schmücken', 'Aufbau und Dekoration', '#666666', 7);

-- Sample Data: Insert Groups as Employees
INSERT INTO `employees` (`group_id`, `is_group`, `active`) 
SELECT id, 1, 1 FROM `groups`;

-- Sample Data: Insert Shifts for Redner-Sitzung 22.01.2026
-- Aufbau/Schmücken - Mittwoch 16:00
INSERT INTO `shifts` (`date`, `start_time`, `task_area_id`, `required_count`, `responsible_person`, `description`) 
SELECT '2026-01-21', '16:00:00', id, 0, 'zusagen Personal', 'Aufbau und Schmücken'
FROM `task_areas` WHERE `name` = 'Aufbau / Schmücken';

-- Theke - Donnerstag Treffen 17:00 Uhr
INSERT INTO `shifts` (`date`, `start_time`, `task_area_id`, `required_count`, `responsible_person`, `description`) 
SELECT '2026-01-22', '17:00:00', id, 0, 'zusagen Personal', 'Treffen 17:00 Uhr'
FROM `task_areas` WHERE `name` = 'Theke';

-- Bedienung/Service - Donnerstag Treffen 17:00 Uhr
INSERT INTO `shifts` (`date`, `start_time`, `task_area_id`, `required_count`, `responsible_person`, `description`) 
SELECT '2026-01-22', '17:00:00', id, 0, 'zusagen Personal', 'Treffen 17:00 Uhr'
FROM `task_areas` WHERE `name` = 'Bedienung / Service';

-- Garderobe - Donnerstag Treffen 17:00 Uhr
INSERT INTO `shifts` (`date`, `start_time`, `task_area_id`, `required_count`, `responsible_person`, `description`) 
SELECT '2026-01-22', '17:00:00', id, 0, 'zusagen Personal', 'Treffen 17:00 Uhr'
FROM `task_areas` WHERE `name` = 'Garderobe';

-- Kasse - Donnerstag Treffen 17:00 Uhr
INSERT INTO `shifts` (`date`, `start_time`, `task_area_id`, `required_count`, `responsible_person`, `description`) 
SELECT '2026-01-22', '17:00:00', id, 0, 'zusagen Personal', 'Treffen 17:00 Uhr'
FROM `task_areas` WHERE `name` = 'Kasse';

-- Fixed Assignments (already assigned areas)
-- Elferrat: 11 Personen, Verantwortlicher: Robert L.
INSERT INTO `shifts` (`date`, `task_area_id`, `required_count`, `responsible_person`, `description`, `start_time`) 
SELECT '2026-01-22', id, 11, 'Robert L.', 'Tisch, Blumen, Bütt', '18:00:00'
FROM `task_areas` WHERE `name` = 'Elferrat';

-- Kasse: 4 Personen, Verantwortlicher: Melissa
INSERT INTO `shifts` (`date`, `task_area_id`, `required_count`, `responsible_person`, `description`, `start_time`) 
SELECT '2026-01-22', id, 4, 'Melissa', 'Wechselgeld, 2 Kassen', '18:00:00'
FROM `task_areas` WHERE `name` = 'Kasse';

-- Theke: 6 Personen, Verantwortlicher: Tim L.
INSERT INTO `shifts` (`date`, `task_area_id`, `required_count`, `responsible_person`, `start_time`) 
SELECT '2026-01-22', id, 6, 'Tim L.', '18:00:00'
FROM `task_areas` WHERE `name` = 'Theke';

-- Bedienung/Service: 10 Personen, Verantwortlicher: Andrea
INSERT INTO `shifts` (`date`, `task_area_id`, `required_count`, `responsible_person`, `start_time`) 
SELECT '2026-01-22', id, 10, 'Andrea', '18:00:00'
FROM `task_areas` WHERE `name` = 'Bedienung / Service';

-- Garderobe: 6 Personen, Verantwortlicher: Felix Fesser
INSERT INTO `shifts` (`date`, `task_area_id`, `required_count`, `responsible_person`, `description`, `start_time`) 
SELECT '2026-01-22', id, 6, 'Felix Fesser', 'Garderoben Kisten / Marken(Robert)', '18:00:00'
FROM `task_areas` WHERE `name` = 'Garderobe';

-- Künstlerbetreuung: 1 Person, Verantwortlicher: Wolfgang Correnz
INSERT INTO `shifts` (`date`, `task_area_id`, `required_count`, `responsible_person`, `start_time`) 
SELECT '2026-01-22', id, 1, 'Wolfgang Correnz', '18:00:00'
FROM `task_areas` WHERE `name` = 'Künstlerbetreuung';
