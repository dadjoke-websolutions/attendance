# Anwesenheitsliste

Anwesenheitskontrolle für Trainings mit stark wechselnder Teilnehmerschaft.
Läuft auf gewöhnlichem Shared-Hosting: PHP 8.1+, MySQL/MariaDB, kein Composer,
kein npm, keine CDN-Abhängigkeit.

Die Seite lädt einmal und bringt alle Daten inline mit; danach arbeitet das
Frontend ohne Reload, Änderungen gehen per `fetch` an `api.php`.

## Installation

1. Dateien auf den Webspace kopieren (Unterordner ist in Ordnung).
2. Datenbank anlegen und Schema einspielen:

   ```
   mysql -u USER -p DBNAME < schema.sql
   ```

   Alternativ `schema.sql` in phpMyAdmin importieren. Bestehende
   Installationen aktualisiert man mit den Dateien in `migrations/`, der Reihe
   nach eingespielt.
3. `config.sample.php` nach `config.php` kopieren und ausfüllen.
4. Passwortschutz aktivieren (empfohlen – die Liste enthält Kontaktdaten):

   ```
   php -r "echo password_hash('deinpasswort', PASSWORD_DEFAULT), PHP_EOL;"
   ```

   Hash in `config.php` unter `password_hash` eintragen. Leerer String
   deaktiviert den Login.
5. `index.php` im Browser öffnen.

`config.php` ist in `.gitignore` und wird zusätzlich per `.htaccess` gesperrt.

## Bedienung

**Teilnehmerinnen** werden über die Schaltfläche oben rechts erfasst: Vorname,
Name, Geschlecht, Jahrgang, Trainingsanfrage, Mobile, E-Mail, Notizen, aktiv,
WhatsApp. «Trainingsanfrage» hält fest, wann die Anfrage eingegangen ist, und
darf leer bleiben. In der
Liste erscheinen Vorname, Name und Jahrgang; der Stift öffnet den Datensatz
erneut.

**Sortieren** nach Vorname oder Jahrgang, erneuter Klick kehrt die Richtung um.
Personen ohne Jahrgang stehen immer am Schluss. **Filtern** nach aktiv,
inaktiv oder alle. Das Suchfeld filtert laufend über Vor- und Nachname und
ignoriert Akzente – «ruegg» findet «Rüegg».

**Trainings** entstehen über «Training» oder das `+` am rechten Rand der
Tabelle. Datum und optional eine Bezeichnung, damit zwei Trainings am selben
Tag unterscheidbar bleiben.

**Anwesenheit**: Ein Klick auf den Punkt wechselt hellgrau (unbekannt) → grün
(anwesend) → rot (abwesend) → hellgrau. Punkte erscheinen erst ab dem Datum
der Trainingsanfrage; frühere Trainings bleiben als leere, grau hinterlegte
Zelle stehen und zählen nicht mit. Ohne Anfragedatum sind alle Trainings
bespielbar. Die Zahl der Anwesenden steht im
Spaltentitel. Ein Klick auf den Spaltentitel öffnet die Erfassungsansicht: ein
Training, eine Liste mit grossen Tap-Flächen für das Handy in der Halle. Ein
neu angelegtes Training öffnet diese Ansicht direkt; `Esc` oder «← Liste»
führt zurück.

**vCard**: Der Pfeil in der Teilnehmerzeile lädt eine `.vcf`-Datei. Der in
`config.php` gesetzte `vcf_note_prefix` wird der Notiz vorangestellt und
zusätzlich als `CATEGORIES` gesetzt, damit die Kontakte im Adressbuch
gruppierbar bleiben.

## Import der bisherigen Excel-Liste

Tabellenblatt als CSV mit Semikolon speichern, dann auf der Kommandozeile:

```
php tools/import.php teilnehmer.csv
```

Erwartete Kopfzeile (Reihenfolge beliebig, Spalten optional):

```
Vorname;Name;Geschlecht;Jahrgang;Trainingsanfrage;Mobile;Mail;Notizen;aktiv;whatsapp
```

Bestehende Anwesenheiten werden nicht importiert; die alten Trainings sind
erfahrungsgemäss schneller von Hand nachgetragen als abgebildet.

## Datenmodell

| Tabelle | Inhalt |
| --- | --- |
| `participants` | Stammdaten, `request_date` als Datum der Anfrage, `active` und `whatsapp` als Flags |
| `trainings` | Datum plus optionale Bezeichnung, eindeutig kombiniert |
| `attendance` | eine Zeile je bekanntem Status, `present` oder `absent` |

«Status unbekannt» wird nicht gespeichert, sondern durch die fehlende Zeile
dargestellt. Löscht man ein Training oder eine Person, entfernt der
Fremdschlüssel die zugehörigen Anwesenheiten mit.

## Schnittstelle

`api.php` erwartet Schreibzugriffe als POST mit JSON-Body und dem
Session-Token im Header `X-Token`:

| Aktion | Nutzlast |
| --- | --- |
| `bootstrap` | – (GET, liefert alle Daten) |
| `participant.save` | Felder, `id` für Änderung |
| `participant.delete` | `id` |
| `training.save` | `training_date`, `label`, `id` für Änderung |
| `training.delete` | `id` |
| `attendance.set` | `training_id`, `participant_id`, `status` (`present`, `absent` oder leer) |

## Mögliche Erweiterungen

- Sortierung nach Trainingsanfrage, um neue Anfragen zuoberst zu sehen
- Filter «nur WhatsApp» und Sammelexport der Nummern für die Gruppenbildung
- Jahrgang in die vCard aufnehmen (in vCard 3.0 nur als Notiz sauber möglich)
- Anwesenheitsquote je Person über eine Saison
