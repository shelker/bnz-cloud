-- Team-Zeitfenster pro Schicht (optional)
-- Beispiel JSON in team_slots:
-- [{"start":"18:00:00","end":"20:00:00","team":"Team 1"},{"start":"20:00:00","end":"22:00:00","team":"Team 2"},…]

ALTER TABLE `shifts`
  ADD COLUMN `team_slots` JSON NULL DEFAULT NULL
  COMMENT 'Team-Zeitfenster als JSON-Array'
  AFTER `end_time`;
