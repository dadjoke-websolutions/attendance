<?php
/**
 * Kopie dieser Datei als config.php ablegen und ausfüllen.
 * config.php wird von .gitignore ausgeschlossen.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'attendance',
        'user' => 'dbuser',
        'pass' => '',
    ],

    // Passwortschutz für die ganze Anwendung.
    // Hash erzeugen:  php -r "echo password_hash('meinpasswort', PASSWORD_DEFAULT), PHP_EOL;"
    // Leerer String = kein Login (nur sinnvoll, wenn der Ordner anderweitig geschützt ist).
    'password_hash' => '',

    // Titel in der Kopfzeile
    'title' => 'Volley Probetraining',

    // Wird jeder VCF-Notiz vorangestellt
    'vcf_note_prefix' => 'Volley Probetraining',
];
