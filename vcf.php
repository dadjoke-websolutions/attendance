<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';
session_boot();

if (!logged_in()) {
    http_response_code(403);
    exit('Nicht angemeldet.');
}

$st = db()->prepare('SELECT * FROM participants WHERE id = ?');
$st->execute([(int) ($_GET['id'] ?? 0)]);
$p = $st->fetch();

if (!$p) {
    http_response_code(404);
    exit('Teilnehmer nicht gefunden.');
}

/** Werte gemäss RFC 2426 maskieren. */
function vesc(string $v): string
{
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $v);
    return str_replace("\n", '\\n', $v);
}

/** Zeilen nach 75 Oktetten falten. */
function vfold(string $line): string
{
    if (strlen($line) <= 75) {
        return $line;
    }
    $out = substr($line, 0, 75);
    $rest = substr($line, 75);
    foreach (str_split($rest, 74) as $chunk) {
        $out .= "\r\n " . $chunk;
    }
    return $out;
}

$first = (string) $p['first_name'];
$last  = (string) $p['last_name'];
$full  = trim($first . ' ' . $last);

$note = trim((string) cfg('vcf_note_prefix'));
$own  = trim((string) ($p['notes'] ?? ''));
if ($own !== '') {
    $note = $note === '' ? $own : $note . "\n" . $own;
}

$lines = [
    'BEGIN:VCARD',
    'VERSION:3.0',
    'N:' . vesc($last) . ';' . vesc($first) . ';;;',
    'FN:' . vesc($full),
];
if ($p['mobile'] !== '') {
    $lines[] = 'TEL;TYPE=CELL,VOICE:' . vesc((string) $p['mobile']);
}
if ($p['email'] !== '') {
    $lines[] = 'EMAIL;TYPE=INTERNET,PREF:' . vesc((string) $p['email']);
}
if ($note !== '') {
    $lines[] = 'NOTE:' . vesc($note);
}
$prefix = trim((string) cfg('vcf_note_prefix'));
if ($prefix !== '') {
    $lines[] = 'CATEGORIES:' . vesc($prefix);
}
$lines[] = 'REV:' . gmdate('Y-m-d\TH:i:s\Z');
$lines[] = 'END:VCARD';

$body = '';
foreach ($lines as $l) {
    $body .= vfold($l) . "\r\n";
}

$name = preg_replace('/[^A-Za-z0-9_\-]/', '', strtr($full, [
    'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', ' ' => '_',
]));
$name = $name !== '' ? $name : 'kontakt';

header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '.vcf"');
header('Content-Length: ' . strlen($body));
echo $body;
