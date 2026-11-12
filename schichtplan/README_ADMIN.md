# Admin-System für Schichtplan

## Installation

1. **Migration ausführen:**
   ```bash
   mysql -u dein_user -p deine_datenbank < migration_admin.sql
   ```

2. **Standard-Login-Daten:**
   - **Benutzername:** `admin`
   - **Passwort:** `admin123`
   
   **WICHTIG:** Bitte ändern Sie das Passwort nach dem ersten Login!

## Funktionen

### Admin-Login
- Zugriff über `/schichtplan/admin_login.php`
- Session-basierte Authentifizierung
- Passwort wird mit `password_hash()` gespeichert

### Admin-Bereich
- Übersicht aller Eintragungen
- Löschen von Eintragungen (nur für Admins)
- Verwaltung des Schichtplan-Systems

### Sicherheit
- Nur Administratoren können Eintragungen löschen
- Normale Benutzer können sich nur eintragen, nicht löschen
- Session-Management mit automatischem Timeout
- Passwort-Hashing mit bcrypt

## Neue Admin erstellen

```sql
INSERT INTO `admins` (`benutzername`, `passwort_hash`, `email`, `vorname`, `nachname`, `aktiv`) 
VALUES ('neuer_admin', 'PASSWORT_HASH_HIER', 'email@example.com', 'Vorname', 'Nachname', 1);
```

Passwort-Hash generieren:
```php
<?php
echo password_hash('dein_passwort', PASSWORD_DEFAULT);
?>
```

## Passwort ändern

```sql
UPDATE `admins` 
SET `passwort_hash` = 'NEUER_HASH_HIER' 
WHERE `benutzername` = 'admin';
```

## Dateien

- `migration_admin.sql` - Datenbank-Migration für Admin-System
- `admin_login.php` - Login-Seite
- `admin.php` - Admin-Übersicht
- `admin_eintragungen.php` - Eintragungen verwalten
- `config.php` - Erweitert um Admin-Funktionen

## API-Endpunkte

### Löschen (nur für Admins)
```
POST api.php
action=delete_entry
zuweisung_id=123
```

### Stornieren (nur für Admins)
```
POST api.php
action=cancel
schicht_id=1
mitarbeiter_id=2
```

