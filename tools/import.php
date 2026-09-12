<?php
/**
 * Teilnehmerimport aus CSV (nur Kommandozeile).
 *
 *   php tools/import.php teilnehmer.csv
 *
 * Erwartete Kopfzeile (Semikolon getrennt, Reihenfolge beliebig, Spalten optional):
 *   Vorname;Name;Geschlecht;Jahrgang;Mobile;Mail;Notizen;aktiv;whatsapp
 *
 * Geschlecht: w/f = weiblich, m = männlich, alles andere = keine Angabe.
 * aktiv/whatsapp: 1, ja, x, true → wahr; leer oder 0 → falsch.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Nur über die Kommandozeile aufrufen.\n");
}
require __DIR__ . '/../lib.php';

$file = $argv[1] ?? '';
if (!is_file($file)) {
    exit("Aufruf: php tools/import.php teilnehmer.csv\n");
}

$fh = fopen($file, 'r');
$head = fgetcsv($fh, 0, ';');
if (!$head) {
    exit("CSV ist leer.\n");
}
$head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $head[0]);
$map = [];
foreach ($head as $i => $name) {
    $map[s_lower(trim((string) $name))] = $i;
}

$pick = static function (array $row, array $names) use ($map): string {
    foreach ($names as $n) {
        if (isset($map[$n]) && isset($row[$map[$n]])) {
            return trim((string) $row[$map[$n]]);
        }
    }
    return '';
};
$truthy = static fn(string $v): int => in_array(s_lower($v), ['1', 'ja', 'j', 'x', 'true', 'wahr', 'y', 'yes'], true) ? 1 : 0;

$st = db()->prepare(
    'INSERT INTO participants (first_name, last_name, gender, birth_year, mobile, email, notes, active, whatsapp)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$n = 0;
while (($row = fgetcsv($fh, 0, ';')) !== false) {
    $first = $pick($row, ['vorname', 'first_name']);
    $last  = $pick($row, ['name', 'nachname', 'last_name']);
    if ($first === '' && $last === '') {
        continue;
    }
    $g    = s_lower($pick($row, ['geschlecht', 'gender', 'sex']));
    $year = (int) $pick($row, ['jahrgang', 'jg', 'birth_year']);
    $act  = $pick($row, ['aktiv', 'active']);

    $st->execute([
        $first,
        $last,
        in_array($g, ['w', 'f', 'weiblich', 'female'], true) ? 'f' : (in_array($g, ['m', 'männlich', 'male'], true) ? 'm' : 'x'),
        ($year >= 1900 && $year <= 2100) ? $year : null,
        $pick($row, ['mobile', 'mobil', 'telefon', 'tel']),
        $pick($row, ['mail', 'e-mail', 'email']),
        $pick($row, ['notizen', 'notiz', 'notes', 'bemerkung']),
        $act === '' ? 1 : $truthy($act),
        $truthy($pick($row, ['whatsapp', 'wa'])),
    ]);
    $n++;
}
fclose($fh);

echo "$n Teilnehmerinnen importiert.\n";
