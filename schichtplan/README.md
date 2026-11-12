# Schichtplan-System - Bedburger Narrenzunft

Ein modernes Schichtplan-System für die Personalplanung der Bedburger Narrenzunft.

## Features

- 📅 **Kalenderansicht** mit Monatsnavigation
- 👥 **Eintragungssystem** für Mitarbeiter
- 🎨 **Farbcodierte Aufgabenbereiche** für bessere Übersicht
- 📱 **Responsive Design** für Desktop und Mobile
- ✅ **Vollständigkeitsprüfung** - zeigt an, ob Schichten voll belegt sind
- 🔄 **Echtzeit-Updates** ohne Seitenneuladung

## Installation

### 1. Datenbank einrichten

1. Erstelle eine neue Datenbank (z.B. `bnz_schichtplan`)
2. Führe die SQL-Datei aus:
   ```bash
   mysql -u dein_user -p bnz_schichtplan < database.sql
   ```

### 2. Datenbankverbindung konfigurieren

Öffne `config.php` und trage deine Datenbankdaten ein:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bnz_schichtplan');
define('DB_USER', 'dein_db_user');
define('DB_PASS', 'dein_db_passwort');
```

### 3. Dateiberechtigungen prüfen

Stelle sicher, dass PHP Schreibrechte auf die benötigten Verzeichnisse hat.

## Verwendung

### Zugriff

Das System ist unter **https://schichtplan.bnz1886.de** erreichbar.

### Schichten anzeigen

1. Öffne die Schichtplan-Website im Browser
2. Navigiere durch die Monate mit den Pfeiltasten
3. Klicke auf einen Tag, um die Details zu sehen

### Für Schicht eintragen

1. Klicke auf einen Tag im Kalender
2. Klicke auf "Für diese Schicht eintragen"
3. Wähle einen Mitarbeiter aus dem Dropdown
4. Optional: Füge eine Bemerkung hinzu
5. Klicke auf "Eintragen"

### Eintragung stornieren

1. Öffne die Tagesdetails
2. Klicke auf das X neben deinem Namen
3. Bestätige die Stornierung

## Datenbankstruktur

### Tabellen

- **mitarbeiter**: Mitarbeiter/Benutzer des Systems
- **aufgabenbereiche**: Verschiedene Aufgabenbereiche/Positionen (z.B. Kasse, Theke, Garderobe)
- **schichten**: Geplante Schichten mit Datum, Zeit, Aufgabenbereich
- **schichtzuweisungen**: Eintragungen von Mitarbeitern für Schichten

### Beispiel-Aufgabenbereiche

Das System kommt mit folgenden Standard-Aufgabenbereichen:
- Elferrat (#c4000c)
- Kasse (#0066cc)
- Theke (#00aa00)
- Bedienung / Service (#ff9900)
- Garderobe (#9966cc)
- Künstlerbetreuung (#cc6600)
- Aufbau / Schmücken (#666666)

## API-Endpunkte

### Schichten für einen Tag abrufen
```
GET api.php?action=get_day_shifts&date=2026-01-15
```

### Für Schicht eintragen
```
POST api.php
action=signup
schicht_id=1
mitarbeiter_id=1
bemerkung=Optional
```

### Eintragung stornieren
```
POST api.php
action=cancel
schicht_id=1
mitarbeiter_id=1
```

### Meine Schichten abrufen
```
GET api.php?action=get_my_shifts&mitarbeiter_id=1
```

## Anpassungen

### Neue Aufgabenbereiche hinzufügen

Füge neue Aufgabenbereiche direkt in der Datenbank ein:

```sql
INSERT INTO aufgabenbereiche (name, beschreibung, farbe, sortierung) 
VALUES ('Neuer Aufgabenbereich', 'Beschreibung', '#ff0000', 8);
```

### Mitarbeiter hinzufügen

```sql
INSERT INTO mitarbeiter (vorname, nachname, email, telefon) 
VALUES ('Max', 'Mustermann', 'max@example.com', '0123456789');
```

### Schichten erstellen

```sql
INSERT INTO schichten (datum, startzeit, endzeit, aufgabenbereich_id, anzahl_benoetigt, ort) 
VALUES ('2026-01-15', '18:00:00', '22:00:00', 1, 2, 'Hauptbühne');
```

## Domain

Das System ist unter **https://schichtplan.bnz1886.de** erreichbar.

## QR-Code

Ein QR-Code-Generator ist unter `qr-generator.php` verfügbar, um den Schichtplan-Link einfach zu teilen.

## Technische Details

- **Backend**: PHP 7.4+
- **Frontend**: Vanilla JavaScript, Tailwind CSS
- **Datenbank**: MySQL/MariaDB
- **Design**: Angelehnt an das bestehende BNZ-Design (#c4000c)
- **Domain**: schichtplan.bnz1886.de

## Sicherheit

- Alle Datenbankabfragen verwenden Prepared Statements
- Input-Validierung für Datum und IDs
- XSS-Schutz durch htmlspecialchars()
- CSRF-Schutz kann bei Bedarf hinzugefügt werden

## Lizenz

© 2026 Bedburger Narrenzunft

