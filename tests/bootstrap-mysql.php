<?php

require dirname(__DIR__).'/vendor/autoload.php';

$database = (string) (getenv('DB_DATABASE') ?: ($_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? ''));
$connection = strtolower((string) (getenv('DB_CONNECTION') ?: ($_SERVER['DB_CONNECTION'] ?? $_ENV['DB_CONNECTION'] ?? '')));
$allowed = $database === 'moviemate_phase4_rehearsal'
    || str_starts_with($database, 'moviemate_phase4_mysql_test_');

if ($database === 'moviemate') {
    throw new RuntimeException('MySQL integration suite hard-refuses primary database [moviemate].');
}
if ($connection !== 'mysql' || ! $allowed) {
    throw new RuntimeException(
        'MySQL integration suite requires DB_CONNECTION=mysql and DB_DATABASE=moviemate_phase4_rehearsal '
        .'or a name beginning with moviemate_phase4_mysql_test_. Resolved database ['.$database.'].',
    );
}

fwrite(STDOUT, "MySQL integration database: {$database}\n");
