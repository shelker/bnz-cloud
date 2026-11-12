<?php
/**
 * Kopiere diese Datei nach send-sponsor-config.php und trage echte Zugangsdaten ein.
 * send-sponsor-config.php wird nicht ins Repository eingecheckt.
 *
 * ZeptoMail: Mail-Konsole → SMTP (EU oft smtp.zeptomail.eu, Port 587 TLS).
 * Benutzername ist oft wörtlich "emailapikey", Passwort = SMTP-Passwort aus der Konsole
 * (nicht dasselbe wie der REST-API-Key – siehe ZeptoMail-Hilfe).
 */
declare(strict_types=1);

return [
    'smtp_host' => 'smtp.zeptomail.eu',
    'smtp_port' => 587,
    'smtp_user' => 'emailapikey',
    'smtp_pass' => 'DEIN_SMTP_PASSWORT_HIER',
    'smtp_secure' => 'tls',
    'from_address' => 'noreply@bedburger-narrenzunft.de',
    'from_name' => 'BNZ Sponsoring',

    // Mehrere Empfänger (To). Duplikat-Adressen werden ignoriert.
    'recipients' => [
        ['address' => 'stefan.helker@bedburger-narrenzunft.de', 'name' => 'Stefan Helker'],
        // Kurzform nur E-Mail: 'weitere@bedburger-narrenzunft.de',
    ],

    // Optional: Blindkopien (nicht sichtbar für andere Empfänger).
    'bcc' => [
        // ['address' => 'archiv@bedburger-narrenzunft.de', 'name' => 'Archiv'],
    ],

    // Legacy: wird nur genutzt, wenn "recipients" fehlt oder leer ist.
    // 'to_address' => 'stefan.helker@bedburger-narrenzunft.de',
    // 'to_name' => 'Stefan Helker',

    // TLS schlägt oft mit "Certificate verify failed" fehl (Shared Hosting).
    // Auf true setzen, testen, danach wieder aus wenn möglich:
    // 'smtp_tls_insecure' => true,

    // Nur kurz zum Debuggen: JSON enthält dann Feld "detail" mit SMTP-Fehlertext.
    // 'verbose_errors' => true,
];
