<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';
session_boot();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!logged_in()) {
    out(['error' => 'Nicht angemeldet. Seite neu laden.'], 401);
}

$action = (string) ($_GET['action'] ?? '');
$isRead = $action === 'bootstrap';

if (!$isRead) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        out(['error' => 'POST erwartet.'], 405);
    }
    $token = $_SERVER['HTTP_X_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['token'], (string) $token)) {
        out(['error' => 'Sitzung abgelaufen. Seite neu laden.'], 403);
    }
}

$in = [];
if (!$isRead) {
    $raw = file_get_contents('php://input') ?: '';
    $in  = json_decode($raw, true);
    if (!is_array($in)) {
        out(['error' => 'Ungültige Anfrage.'], 400);
    }
}

$str = static fn(string $k, int $max = 190): string => s_cut(trim((string) ($in[$k] ?? '')), $max);

try {
    switch ($action) {

        case 'bootstrap':
            out(bootstrap_data());

        case 'participant.save':
            $first = $str('first_name', 80);
            if ($first === '') {
                out(['error' => 'Vorname fehlt.'], 422);
            }
            $year = (int) ($in['birth_year'] ?? 0);
            $request = $str('request_date', 10);
            $row = [
                'first_name' => $first,
                'last_name'  => $str('last_name', 80),
                'gender'     => in_array($in['gender'] ?? '', ['f', 'm', 'x'], true) ? $in['gender'] : 'x',
                'birth_year' => ($year >= 1900 && $year <= 2100) ? $year : null,
                'request_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $request) ? $request : null,
                'mobile'     => $str('mobile', 40),
                'email'      => $str('email'),
                'notes'      => s_cut((string) ($in['notes'] ?? ''), 5000),
                'active'     => !empty($in['active']) ? 1 : 0,
                'whatsapp'   => !empty($in['whatsapp']) ? 1 : 0,
            ];
            $id = (int) ($in['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(static fn($k) => "$k = :$k", array_keys($row)));
                $st  = db()->prepare("UPDATE participants SET $set WHERE id = :id");
                $st->execute($row + ['id' => $id]);
            } else {
                $cols = array_keys($row);
                $st = db()->prepare(
                    'INSERT INTO participants (' . implode(', ', $cols) . ') VALUES (:' . implode(', :', $cols) . ')'
                );
                $st->execute($row);
                $id = (int) db()->lastInsertId();
            }
            out(['participant' => [
                'id'         => $id,
                'first_name' => $row['first_name'],
                'last_name'  => $row['last_name'],
                'gender'     => $row['gender'],
                'birth_year' => $row['birth_year'],
                'request_date' => $row['request_date'],
                'mobile'     => $row['mobile'],
                'email'      => $row['email'],
                'notes'      => $row['notes'],
                'active'     => (bool) $row['active'],
                'whatsapp'   => (bool) $row['whatsapp'],
            ]]);

        case 'participant.delete':
            $st = db()->prepare('DELETE FROM participants WHERE id = ?');
            $st->execute([(int) ($in['id'] ?? 0)]);
            out(['ok' => true]);

        case 'training.save':
            $date = $str('training_date', 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                out(['error' => 'Datum fehlt oder ist ungültig.'], 422);
            }
            $label = $str('label', 80);
            $id    = (int) ($in['id'] ?? 0);
            try {
                if ($id > 0) {
                    $st = db()->prepare('UPDATE trainings SET training_date = ?, label = ? WHERE id = ?');
                    $st->execute([$date, $label, $id]);
                } else {
                    $st = db()->prepare('INSERT INTO trainings (training_date, label) VALUES (?, ?)');
                    $st->execute([$date, $label]);
                    $id = (int) db()->lastInsertId();
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    out(['error' => 'Für dieses Datum gibt es bereits ein Training mit gleicher Bezeichnung.'], 409);
                }
                throw $e;
            }
            out(['training' => ['id' => $id, 'training_date' => $date, 'label' => $label]]);

        case 'training.delete':
            $st = db()->prepare('DELETE FROM trainings WHERE id = ?');
            $st->execute([(int) ($in['id'] ?? 0)]);
            out(['ok' => true]);

        case 'attendance.set':
            $tid    = (int) ($in['training_id'] ?? 0);
            $pid    = (int) ($in['participant_id'] ?? 0);
            $status = (string) ($in['status'] ?? '');
            if ($tid <= 0 || $pid <= 0) {
                out(['error' => 'Training oder Teilnehmer unbekannt.'], 422);
            }
            if (!in_array($status, ['', 'present', 'absent'], true)) {
                out(['error' => 'Unbekannter Status.'], 422);
            }
            if ($status !== '') {
                // Kein Status für Trainings vor der Trainingsanfrage
                $chk = db()->prepare(
                    'SELECT t.training_date, p.request_date
                       FROM trainings t JOIN participants p ON p.id = ?
                      WHERE t.id = ?'
                );
                $chk->execute([$pid, $tid]);
                $ref = $chk->fetch();
                if (!$ref) {
                    out(['error' => 'Training oder Teilnehmerin existiert nicht mehr. Seite neu laden.'], 409);
                }
                if ($ref['request_date'] !== null && $ref['training_date'] < $ref['request_date']) {
                    out(['error' => 'Dieses Training liegt vor der Trainingsanfrage. Seite neu laden.'], 409);
                }
            }

            try {
                if ($status === '') {
                    $st = db()->prepare('DELETE FROM attendance WHERE training_id = ? AND participant_id = ?');
                    $st->execute([$tid, $pid]);
                } else {
                    $st = db()->prepare(
                        'INSERT INTO attendance (training_id, participant_id, status) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE status = VALUES(status)'
                    );
                    $st->execute([$tid, $pid, $status]);
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    out(['error' => 'Training oder Teilnehmerin existiert nicht mehr. Seite neu laden.'], 409);
                }
                throw $e;
            }
            out(['ok' => true]);

        default:
            out(['error' => 'Unbekannte Aktion.'], 404);
    }
} catch (Throwable $e) {
    error_log('attendance api: ' . $e->getMessage());
    out(['error' => 'Serverfehler – die Änderung wurde nicht gespeichert.'], 500);
}
