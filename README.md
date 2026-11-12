# Bedburger Narrenzunft - Foto Upload System

Ein elegantes Foto-Upload-System für die Bedburger Narrenzunft mit QR-Code-Zugang und geschützter Admin-Galerie.

## Features

- 📸 **Einfacher Foto-Upload** via Drag & Drop oder Kamera/Bibliothek
- 🎨 **Schöne Animationen** während des Uploads
- 🔒 **Geschützte Admin-Galerie** mit Passwort-Schutz
- 📅 **Datum-Gruppierung** für bessere Übersicht
- 💾 **Download-Funktionalität** für einzelne Fotos oder komplette Tagespakete
- 📱 **Responsive Design** mit Tailwind CSS
- 🎨 **Vereinsbranding** in der Farbe #c4000c

## Installation

1. **Dateien auf Webserver hochladen**
   ```bash
   # Alle Dateien in das Webroot-Verzeichnis kopieren
   cp -r * /var/www/html/
   ```

2. **Verzeichnisberechtigungen setzen**
   ```bash
   chmod 755 uploads/
   chmod 755 js/
   ```

3. **PHP-Extensions prüfen**
   - `gd` (für Thumbnail-Erstellung)
   - `zip` (für Download-Funktionalität)

4. **Admin-Passwort ändern**
   ```php
   // In admin/index.php Zeile 6 ändern:
   $adminPassword = 'dein_sicheres_passwort';
   ```

## Verwendung

### Für Gäste (Foto-Upload)
1. QR-Code scannen oder Link aufrufen
2. Fotos per Drag & Drop oder Kamera/Bibliothek auswählen
3. Upload wird automatisch verarbeitet

### Für Admins (Galerie-Verwaltung)
1. `/admin/` aufrufen
2. Mit Passwort anmelden
3. Fotos nach Datum gruppiert ansehen
4. Einzelne Fotos oder Tagespakete herunterladen

## Dateistruktur

```
bnz-cloud/
├── index.php          # Haupt-Upload-Seite
├── admin.php          # Geschützte Admin-Galerie
├── upload.php         # Upload-Handler
├── download.php       # Download-Handler
├── js/
│   └── upload.js      # Frontend-JavaScript
├── uploads/           # Upload-Verzeichnis
│   ├── 2024-01-15/    # Tagesordner
│   │   ├── foto1.jpg
│   │   ├── thumb_foto1.jpg
│   │   └── ...
│   └── ...
└── README.md
```

## Sicherheit

- Admin-Bereich ist passwortgeschützt
- Dateityp-Validierung (nur Bilder)
- Dateigröße-Limit (10MB pro Foto)
- Sichere Dateinamen-Generierung
- Upload-Verzeichnis ist nicht direkt zugänglich

## Anpassungen

### Vereinsfarbe ändern
```css
/* In index.php und admin.php */
colors: {
    'narrenzunft': '#deine_farbe'
}
```

### Upload-Limits anpassen
```php
// In upload.php
$maxFileSize = 20 * 1024 * 1024; // 20MB
```

### Thumbnail-Größe ändern
```php
// In upload.php createThumbnail()
$maxWidth = 400;
$maxHeight = 400;
```

## Support

Bei Fragen oder Problemen wende dich an den Entwickler.

---
**Bedburger Narrenzunft** - Karneval 2024 🎭

