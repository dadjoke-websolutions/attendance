<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';
session_boot();

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['password'])) {
    if (password_verify((string) $_POST['password'], (string) cfg('password_hash'))) {
        session_regenerate_id(true);
        $_SESSION['auth']  = true;
        $_SESSION['token'] = bin2hex(random_bytes(16));
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $error = 'Passwort stimmt nicht.';
    usleep(400000);
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$title = (string) cfg('title');
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light">
<title>Anwesenheit · <?= $h($title) ?></title>
<link rel="stylesheet" href="assets/style.css?v=1">
</head>
<body>

<?php if (!logged_in()): ?>

<main class="gate">
  <h1>Anwesenheit</h1>
  <p><?= $h($title) ?></p>
  <form method="post" class="gate-form">
    <label for="pw">Passwort</label>
    <input type="password" id="pw" name="password" autocomplete="current-password" autofocus required>
    <?php if ($error !== ''): ?><p class="gate-error"><?= $h($error) ?></p><?php endif; ?>
    <button type="submit">Anmelden</button>
  </form>
</main>

<?php else: ?>

<header class="top">
  <h1>Anwesenheit <span><?= $h($title) ?></span></h1>
  <div class="top-actions">
    <button type="button" id="btnNewTraining" class="btn">Training</button>
    <button type="button" id="btnNewPerson" class="btn btn-accent">Teilnehmerin</button>
    <?php if (login_required()): ?><a class="btn btn-quiet" href="?logout=1">Abmelden</a><?php endif; ?>
  </div>
</header>

<nav class="bar">
  <input type="search" id="q" placeholder="Name suchen" autocomplete="off" spellcheck="false">
  <div class="seg" id="segActive" role="group" aria-label="Status">
    <button type="button" data-v="active" class="on">Aktiv</button>
    <button type="button" data-v="inactive">Inaktiv</button>
    <button type="button" data-v="all">Alle</button>
  </div>
  <div class="seg" id="segSort" role="group" aria-label="Sortierung">
    <button type="button" data-v="first" class="on">Vorname</button>
    <button type="button" data-v="year">Jahrgang</button>
  </div>
  <span class="count" id="count"></span>
</nav>

<main id="app"></main>

<dialog id="dlgPerson">
  <form method="dialog" id="formPerson">
    <h2 id="dlgPersonTitle">Teilnehmerin</h2>
    <div class="fields">
      <p class="f"><label for="f_first">Vorname</label><input id="f_first" name="first_name" required maxlength="80" autocomplete="off"></p>
      <p class="f"><label for="f_last">Name</label><input id="f_last" name="last_name" maxlength="80" autocomplete="off"></p>
      <p class="f f-third"><label for="f_year">Jahrgang</label><input id="f_year" name="birth_year" type="number" min="1900" max="2100" inputmode="numeric"></p>
      <p class="f f-third"><label for="f_gender">Geschlecht</label>
        <select id="f_gender" name="gender">
          <option value="f">weiblich</option>
          <option value="m">männlich</option>
          <option value="x">keine Angabe</option>
        </select></p>
      <p class="f f-third"><label for="f_request">Trainingsanfrage</label><input id="f_request" name="request_date" type="date"></p>
      <p class="f f-third"><label for="f_mobile">Mobile</label><input id="f_mobile" name="mobile" type="tel" maxlength="40" autocomplete="off"></p>
      <p class="f"><label for="f_email">E-Mail</label><input id="f_email" name="email" type="email" maxlength="190" autocomplete="off"></p>
      <p class="f f-wide"><label for="f_notes">Notizen</label><textarea id="f_notes" name="notes" rows="3"></textarea></p>
      <p class="f f-check"><label><input type="checkbox" id="f_active" name="active" checked> aktiv</label></p>
      <p class="f f-check"><label><input type="checkbox" id="f_whatsapp" name="whatsapp"> WhatsApp</label></p>
    </div>
    <footer class="dlg-foot">
      <button type="button" id="btnDeletePerson" class="btn btn-danger">Löschen</button>
      <span class="spacer"></span>
      <button type="button" class="btn btn-quiet" data-close>Abbrechen</button>
      <button type="submit" class="btn btn-accent">Speichern</button>
    </footer>
  </form>
</dialog>

<dialog id="dlgTraining">
  <form method="dialog" id="formTraining">
    <h2 id="dlgTrainingTitle">Training</h2>
    <div class="fields">
      <p class="f"><label for="t_date">Datum</label><input id="t_date" name="training_date" type="date" required></p>
      <p class="f"><label for="t_label">Bezeichnung <span class="hint">optional</span></label><input id="t_label" name="label" maxlength="80" autocomplete="off" placeholder="z.B. Halle B"></p>
    </div>
    <footer class="dlg-foot">
      <button type="button" id="btnDeleteTraining" class="btn btn-danger">Löschen</button>
      <span class="spacer"></span>
      <button type="button" class="btn btn-quiet" data-close>Abbrechen</button>
      <button type="submit" class="btn btn-accent">Speichern</button>
    </footer>
  </form>
</dialog>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script>
window.__BOOT = <?= json_encode(bootstrap_data(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.__TOKEN = <?= json_encode($_SESSION['token']) ?>;
</script>
<script src="assets/app.js?v=1"></script>

<?php endif; ?>

</body>
</html>
