<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use App\Database;
use App\Env;

Env::load($root . '/.env');

$dbName = Env::get('DB_NAME', 'ufam');
$admin = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '3306')),
    Env::get('DB_USER', 'ufam'),
    Env::get('DB_PASS', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$safe = str_replace('`', '', $dbName);
if (in_array('--fresh', $argv, true)) {
    $admin->exec('DROP DATABASE IF EXISTS `' . $safe . '`');
}
$admin->exec('CREATE DATABASE IF NOT EXISTS `' . $safe . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$db = new Database([
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'name' => $dbName,
    'user' => Env::get('DB_USER', 'ufam'),
    'pass' => Env::get('DB_PASS', ''),
]);

$sql = file_get_contents($root . '/database/migrations/001_schema.sql');
$db->pdo->exec($sql);
$applied = $db->queryOne('SELECT id FROM schema_migrations WHERE filename=?', ['001_schema.sql']);
if (!$applied) {
    $db->execute('INSERT INTO schema_migrations (filename) VALUES (?)', ['001_schema.sql']);
}

require $root . '/database/seeds/DemoSeeder.php';
(new DemoSeeder($db))->run();

foreach (['logs', 'documents', 'receipts', 'backups', 'qr', 'uploads'] as $dir) {
    $path = $root . '/storage/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0750, true);
    }
    file_put_contents($path . '/.gitkeep', '');
}

echo "Installed database `{$dbName}` and seed data.\n";
echo "Admin username comes from ADMIN_USERNAME. Change the initial password on first login.\n";
