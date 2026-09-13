<?php
declare(strict_types=1);

function cfg(?string $key = null)
{
    static $c = null;
    if ($c === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('config.php fehlt. config.sample.php kopieren und ausfüllen.');
        }
        $c = require $file;
        $c += ['title' => 'Anwesenheit', 'vcf_note_prefix' => '', 'password_hash' => ''];
    }
    return $key === null ? $c : ($c[$key] ?? null);
}

/** Auf $max Zeichen kürzen, auch ohne mbstring. */
function s_cut(string $v, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($v, 0, $max, 'UTF-8');
    }
    if (strlen($v) <= $max) {
        return $v;
    }
    // Bytegrenze, danach ein eventuell angeschnittenes Zeichen entfernen
    return preg_replace('/[\x80-\xBF]+$|[\xC0-\xFD]$/', '', substr($v, 0, $max)) ?? '';
}

/** Kleinschreibung, auch ohne mbstring. */
function s_lower(string $v): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $d = cfg('db');
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['name']);
        $pdo = new PDO($dsn, $d['user'], $d['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function session_boot(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(16));
    }
}

function login_required(): bool
{
    return cfg('password_hash') !== '';
}

function logged_in(): bool
{
    return !login_required() || !empty($_SESSION['auth']);
}

/** Alle Daten in einem Rutsch – wird in index.php eingebettet und von api.php geliefert. */
function bootstrap_data(): array
{
    $participants = db()->query(
        'SELECT id, first_name, last_name, gender, birth_year, request_date,
                mobile, email, notes, active, whatsapp
           FROM participants ORDER BY first_name, last_name'
    )->fetchAll();

    foreach ($participants as &$p) {
        $p['id']         = (int) $p['id'];
        $p['birth_year'] = $p['birth_year'] === null ? null : (int) $p['birth_year'];
        $p['active']     = (bool) $p['active'];
        $p['whatsapp']   = (bool) $p['whatsapp'];
    }
    unset($p);

    $trainings = db()->query(
        'SELECT id, training_date, label FROM trainings ORDER BY training_date, id'
    )->fetchAll();
    foreach ($trainings as &$t) {
        $t['id'] = (int) $t['id'];
    }
    unset($t);

    // Kompakt: [training_id, participant_id, "present"|"absent"]
    $attendance = [];
    foreach (db()->query('SELECT training_id, participant_id, status FROM attendance') as $r) {
        $attendance[] = [(int) $r['training_id'], (int) $r['participant_id'], $r['status']];
    }

    return [
        'participants' => $participants,
        'trainings'    => $trainings,
        'attendance'   => $attendance,
    ];
}
