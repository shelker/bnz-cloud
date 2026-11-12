<?php
/**
 * Sponsoring-Anfrage per SMTP (z. B. ZeptoMail). Liegt nach dem Build unter dist/api/.
 */
declare(strict_types=1);

// Kein file-scope const (auf manchen Hostern/reihenfolgebedingt undefiniert) — define() ist zuverlässig.
if (!defined('SPONSOR_MAX_ATTACHMENT_BYTES')) {
    define('SPONSOR_MAX_ATTACHMENT_BYTES', 10 * 1024 * 1024);
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$configPath = __DIR__ . '/send-sponsor-config.php';
if (!is_readable($configPath)) {
    http_response_code(503);
    echo json_encode(['error' => 'mail_not_configured']);
    exit;
}

/** @var array<string, mixed> $config */
$config = require $configPath;

$data = parseRequestPayload();
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

if (!validatePayload($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payload']);
    exit;
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_readable($autoload)) {
    http_response_code(503);
    echo json_encode(['error' => 'mail_not_configured']);
    exit;
}

require_once $autoload;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = trim((string) $config['smtp_host']);
    $mail->SMTPAuth = true;
    $mail->Username = trim((string) $config['smtp_user']);
    $mail->Password = trim((string) $config['smtp_pass']);
    $secure = strtolower((string) ($config['smtp_secure'] ?? 'tls'));
    if ($secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($secure === 'none' || $secure === '') {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->Port = (int) $config['smtp_port'];
    $mail->Timeout = 45;
    $mail->CharSet = 'UTF-8';

    applySmtpSslOptions($mail, $config);

    $mail->setFrom(trim((string) $config['from_address']), (string) $config['from_name']);

    $recipients = resolveRecipients($config);
    if ($recipients === []) {
        error_log('[send-sponsor] Keine gültigen Empfänger in send-sponsor-config.php');
        http_response_code(503);
        echo json_encode(['error' => 'mail_not_configured']);
        exit;
    }
    foreach ($recipients as $r) {
        $mail->addAddress($r['address'], $r['name']);
    }

    $toKeys = [];
    foreach ($recipients as $r) {
        $toKeys[strtolower($r['address'])] = true;
    }
    foreach (resolveBccList($config) as $b) {
        if (isset($toKeys[strtolower($b['address'])])) {
            continue;
        }
        $mail->addBCC($b['address'], $b['name']);
    }

    $firm = trim((string) ($data['firmName'] ?? ''));
    $mail->Subject = 'Sponsoring-Anfrage: ' . $firm;

    $attachment = validateUploadedLogo();
    $html = buildHtmlBody($data, $attachment !== null ? $attachment['name'] : null);
    $mail->isHTML(true);
    $mail->Body = $html;
    $mail->AltBody = strip_tags(str_replace(['</p>', '</tr>', '<br>', '<br/>', '<br />'], "\n", $html));

    if ($attachment !== null) {
        $mail->addAttachment($attachment['path'], $attachment['name']);
    }

    $mail->send();
    echo json_encode(['ok' => true]);
} catch (MailException $e) {
    $detail = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
    error_log('[send-sponsor] PHPMailer: ' . $detail);
    http_response_code(500);
    echo json_encode(sendFailedResponse($config, $detail));
} catch (\Throwable $e) {
    error_log('[send-sponsor] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(sendFailedResponse($config, $e->getMessage()));
}

/**
 * @param array<string, mixed> $config
 *
 * @return array<string, string>
 */
function sendFailedResponse(array $config, string $internalDetail): array
{
    $out = ['error' => 'send_failed'];
    if (empty($config['verbose_errors'])) {
        return $out;
    }
    // Nur aktivieren zum Debuggen – SMTP-Fehler nie öffentlich dauerhaft loggen.
    $safe = (string) (preg_replace('/\s+/', ' ', $internalDetail) ?? '');
    if (strlen($safe) > 500) {
        $safe = substr($safe, 0, 500);
    }

    return $out + ['detail' => $safe];
}

/**
 * TLS-Zertifikatsprüfung schlägt auf manchen Shared-Hostern fehl → SMTP bricht ab.
 *
 * @param array<string, mixed> $config
 */
function applySmtpSslOptions(PHPMailer $mail, array $config): void
{
    $opts = [];

    if (!empty($config['smtp_tls_insecure'])) {
        $opts['ssl'] = [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ];
    }

    if (!empty($config['smtp_options']) && is_array($config['smtp_options'])) {
        $opts = array_replace_recursive($opts, $config['smtp_options']);
    }

    if ($opts !== []) {
        $mail->SMTPOptions = $opts;
    }
}

/**
 * @param array<string, mixed> $body
 */
function validatePayload(array $body): bool
{
    $firm = trim((string) ($body['firmName'] ?? ''));
    $person = trim((string) ($body['contactPerson'] ?? ''));
    $email = trim((string) ($body['email'] ?? ''));
    $street = trim((string) ($body['street'] ?? ''));
    $postalCode = trim((string) ($body['postalCode'] ?? ''));
    $city = trim((string) ($body['city'] ?? ''));
    if ($firm === '' || $person === '' || $email === '' || $street === '' || $postalCode === '' || $city === '') {
        return false;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * @param array<string, mixed> $payload
 */
function buildHtmlBody(array $payload, ?string $attachmentName = null): string
{
    // Farben wie tailwind.config.js (bnz / slate)
    $bnz600 = '#C41E3A';
    $bnz700 = '#9B1630';
    $bnz100 = '#FCE7EB';
    $bnz50 = '#FDF2F4';
    $slate800 = '#1e293b';
    $slate600 = '#475569';
    $slate500 = '#64748b';
    $border = '#e2e8f0';
    $font = "'IBM Plex Sans', Helvetica, Arial, sans-serif";

    $rows = [
        ['Firmenname', $payload['firmName'] ?? ''],
        ['Ansprechpartner', $payload['contactPerson'] ?? ''],
        ['E-Mail', $payload['email'] ?? ''],
        ['Telefon', $payload['phone'] ?? ''],
        ['Straße', $payload['street'] ?? ''],
        ['PLZ', $payload['postalCode'] ?? ''],
        ['Ort', $payload['city'] ?? ''],
        ['Zunftsitzung-Tickets', $payload['zunftsitzungTickets'] ?? ''],
        ['Automatische Verlängerung', !empty($payload['autoRenewal']) ? 'Ja' : 'Nein'],
        ['Anmerkungen', $payload['notes'] ?? ''],
    ];

    $lines = '';
    foreach ($rows as [$k, $v]) {
        if ($v === null || $v === '' || (is_string($v) && trim($v) === '')) {
            continue;
        }
        $lines .= '<tr>'
            . '<td style="padding:10px 14px;border:1px solid ' . $border . ';background:' . $bnz100 . ';font-weight:600;color:' . $slate800 . ';width:38%;font-family:' . $font . ';font-size:14px;">'
            . h((string) $k) . '</td>'
            . '<td style="padding:10px 14px;border:1px solid ' . $border . ';background:#ffffff;color:' . $slate600 . ';font-family:' . $font . ';font-size:14px;">'
            . h((string) $v) . '</td>'
            . '</tr>';
    }

    $sel = $payload['selections'] ?? [];
    $selRows = '';
    if (is_array($sel)) {
        foreach ($sel as $s) {
            if (!is_array($s)) {
                continue;
            }
            $cat = $s['categoryName'] ?? ($s['category']['name'] ?? '');
            $pkg = is_array($s['pkg'] ?? null) ? $s['pkg'] : [];
            $name = $pkg['name'] ?? '';
            $price = '';
            if (isset($pkg['price'])) {
                $price = ' (' . number_format((float) $pkg['price'], 2, ',', '') . ' €)';
            }
            $selRows .= '<li style="margin-bottom:6px;color:' . $slate600 . ';">'
                . '<span style="color:' . $slate800 . ';font-weight:600;">' . h((string) $cat) . '</span>'
                . ': ' . h((string) $name) . ' <span style="color:' . $bnz700 . ';">' . h($price) . '</span></li>';
        }
    }
    if ($selRows === '') {
        $selRows = '<li style="color:' . $slate500 . ';">(keine Pakete)</li>';
    }

    $sub = isset($payload['subtotal']) ? number_format((float) $payload['subtotal'], 2, ',', '') . ' €' : '—';
    $disc = isset($payload['discountPercent']) ? (string) $payload['discountPercent'] . ' %' : '—';
    $tot = isset($payload['total']) ? number_format((float) $payload['total'], 2, ',', '') . ' €' : '—';

    $attachBlock = '';
    if ($attachmentName !== null && $attachmentName !== '') {
        $attachBlock = '<div style="margin-top:20px;padding:14px 16px;background:' . $bnz50 . ';border-radius:10px;border:1px solid ' . $border . ';font-family:' . $font . ';font-size:14px;color:' . $slate600 . ';">'
            . '<strong style="color:' . $slate800 . ';">Anhang (Logo/Datei):</strong> ' . h($attachmentName)
            . '</div>';
    }

    $sectionTitle = static function (string $label) use ($bnz700, $font, $border): string {
        return '<p style="margin:24px 0 10px;font-family:' . $font . ';font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:' . $bnz700 . ';border-bottom:2px solid ' . $border . ';padding-bottom:8px;">'
            . h($label) . '</p>';
    };

    $hContact = $sectionTitle('Kontakt');
    $hAuswahl = $sectionTitle('Auswahl');
    $hPreis = $sectionTitle('Preisübersicht');

    return <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;margin:0;padding:0;">
  <tr>
    <td align="center" style="padding:28px 16px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid {$border};box-shadow:0 4px 6px -1px rgba(0,0,0,0.06);">
        <tr>
          <td style="background:{$bnz600};padding:22px 24px;">
            <h1 style="margin:0;font-family:{$font};font-size:22px;font-weight:700;line-height:1.25;color:#ffffff;">Neue Sponsoring-Anfrage</h1>
            <p style="margin:10px 0 0;font-family:{$font};font-size:13px;color:rgba(255,255,255,0.92);">Bedburger Narrenzunft · Sponsoring</p>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 24px 28px;font-family:{$font};font-size:15px;color:{$slate800};line-height:1.55;">
            {$hContact}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">{$lines}</table>
            {$hAuswahl}
            <ul style="margin:0;padding:16px 18px 16px 22px;background:{$bnz50};border-radius:12px;border:1px solid {$border};font-family:{$font};font-size:14px;">{$selRows}</ul>
            {$hPreis}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:{$bnz50};border-radius:12px;border:1px solid {$border};overflow:hidden;">
              <tr>
                <td style="padding:12px 16px;font-family:{$font};font-size:14px;color:{$slate600};border-bottom:1px solid {$border};">Zwischensumme</td>
                <td style="padding:12px 16px;font-family:{$font};font-size:14px;color:{$slate800};text-align:right;border-bottom:1px solid {$border};">{$sub}</td>
              </tr>
              <tr>
                <td style="padding:12px 16px;font-family:{$font};font-size:14px;color:{$slate600};border-bottom:1px solid {$border};">Rabatt</td>
                <td style="padding:12px 16px;font-family:{$font};font-size:14px;color:{$slate800};text-align:right;border-bottom:1px solid {$border};">{$disc}</td>
              </tr>
              <tr>
                <td style="padding:14px 16px;font-family:{$font};font-size:15px;font-weight:700;color:{$bnz700};">Gesamt</td>
                <td style="padding:14px 16px;font-family:{$font};font-size:17px;font-weight:700;color:{$bnz600};text-align:right;">{$tot}</td>
              </tr>
            </table>
            {$attachBlock}
            <p style="margin:28px 0 0;font-family:{$font};font-size:12px;line-height:1.5;color:{$slate500};border-top:1px solid {$border};padding-top:18px;">Diese Nachricht wurde über das Sponsoring-Formular auf der Website der Bedburger Narrenzunft gesendet.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
HTML;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Empfänger: "recipients" bevorzugt, sonst Fallback "to_address" / "to_name".
 *
 * @return list<array{address: string, name: string}>
 */
function resolveRecipients(array $config): array
{
    $list = [];
    if (!empty($config['recipients']) && is_array($config['recipients'])) {
        foreach ($config['recipients'] as $entry) {
            $n = normalizeMailEntry($entry);
            if ($n !== null) {
                $list[] = $n;
            }
        }
    }
    if ($list !== []) {
        return dedupeAddresses($list);
    }

    $fallback = normalizeMailEntry([
        'address' => $config['to_address'] ?? '',
        'name' => $config['to_name'] ?? '',
    ]);

    return $fallback !== null ? [$fallback] : [];
}

/**
 * Blindkopien — nur Schlüssel "bcc".
 *
 * @return list<array{address: string, name: string}>
 */
function resolveBccList(array $config): array
{
    $list = [];
    if (empty($config['bcc']) || !is_array($config['bcc'])) {
        return [];
    }
    foreach ($config['bcc'] as $entry) {
        $n = normalizeMailEntry($entry);
        if ($n !== null) {
            $list[] = $n;
        }
    }

    return dedupeAddresses($list);
}

/**
 * @param string|array<string, mixed> $entry
 *
 * @return array{address: string, name: string}|null
 */
function normalizeMailEntry($entry): ?array
{
    if (is_string($entry)) {
        $addr = trim($entry);
        if ($addr === '' || filter_var($addr, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return ['address' => $addr, 'name' => ''];
    }
    if (!is_array($entry)) {
        return null;
    }
    $addr = trim((string) ($entry['address'] ?? $entry['email'] ?? ''));
    if ($addr === '' || filter_var($addr, FILTER_VALIDATE_EMAIL) === false) {
        return null;
    }

    return ['address' => $addr, 'name' => trim((string) ($entry['name'] ?? ''))];
}

/**
 * @param list<array{address: string, name: string}> $list
 *
 * @return list<array{address: string, name: string}>
 */
function dedupeAddresses(array $list): array
{
    $seen = [];
    $out = [];
    foreach ($list as $row) {
        $key = strtolower($row['address']);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $row;
    }

    return $out;
}

/**
 * JSON aus Body oder multipart-Feld "data".
 *
 * @return array<string, mixed>|null
 */
function parseRequestPayload(): ?array
{
    $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    if (stripos($ct, 'multipart/form-data') === 0) {
        $raw = $_POST['data'] ?? '';
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?? '', true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * @return array{path: string, name: string}|null
 */
function validateUploadedLogo(): ?array
{
    if (empty($_FILES['logo']) || !is_array($_FILES['logo'])) {
        return null;
    }

    $f = $_FILES['logo'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($f['error'] ?? 0) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'upload_failed']);
        exit;
    }

    $tmp = $f['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        http_response_code(400);
        echo json_encode(['error' => 'upload_invalid']);
        exit;
    }

    $size = (int) ($f['size'] ?? 0);
    if ($size > SPONSOR_MAX_ATTACHMENT_BYTES) {
        http_response_code(400);
        echo json_encode(['error' => 'file_too_large']);
        exit;
    }

    $mime = detectMimeType($tmp, (string) ($f['name'] ?? ''));

    $allowed = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
    ];
    if (!in_array($mime, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_file_type']);
        exit;
    }

    $origName = (string) ($f['name'] ?? 'anhang');
    $safeName = basename(preg_replace('/[^a-zA-Z0-9._\- äöüÄÖÜß]/u', '_', $origName));
    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        $safeName = 'logo-' . bin2hex(random_bytes(4)) . '.bin';
    }

    return ['path' => $tmp, 'name' => $safeName];
}

/**
 * MIME-Typ (fileinfo; Fallback über Endung bei octet-stream/fehlendem finfo).
 */
function detectMimeType(string $tmpPath, string $originalName): string
{
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }

    $allowedExt = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
    ];
    if ($mime === '' || $mime === 'application/octet-stream' || $mime === 'text/plain') {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (isset($allowedExt[$ext])) {
            return $allowedExt[$ext];
        }
    }

    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string) @mime_content_type($tmpPath);
    }

    return $mime;
}
