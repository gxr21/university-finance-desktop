<?php
declare(strict_types=1);

/**
 * تشغيل المشروع على الحاسوب الشخصي بنقرة واحدة.
 * يجهّز .env والمكتبات وقاعدة البيانات ثم يفتح المتصفح.
 */
$root = dirname(__DIR__);
$php = PHP_BINARY ?: 'php';
$port = 43127;
$url = 'http://127.0.0.1:' . $port . '/login';

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function fail(string $msg, int $code = 1): never
{
    fwrite(STDERR, $msg . PHP_EOL);
    if (PHP_OS_FAMILY === 'Windows') {
        out('');
        out('اضغط Enter للإغلاق...');
        fgets(STDIN);
    }
    exit($code);
}

out('========================================');
out('  نظام الإدارة المالية والإدارية الجامعية');
out('========================================');
out('');

foreach (['pdo_mysql' => 'pdo_mysql', 'mbstring' => 'mbstring', 'json' => 'json'] as $ext => $label) {
    if (!extension_loaded($ext)) {
        fail("امتداد PHP ناقص: {$label}\nثبّت XAMPP 8.2 أو أحدث ثم أعد تشغيل START.bat");
    }
}

$envPath = $root . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    $example = $root . DIRECTORY_SEPARATOR . '.env.example';
    if (!is_file($example)) {
        fail('ملف .env.example غير موجود.');
    }
    $env = (string) file_get_contents($example);
    $env = preg_replace('/^APP_KEY=$/m', 'APP_KEY=local-desktop-key-change-on-deploy-32ch', $env) ?? $env;
    $env = preg_replace('/^ADMIN_INITIAL_PASSWORD=$/m', 'ADMIN_INITIAL_PASSWORD=huda87654321', $env) ?? $env;
    $env = preg_replace('/^DEMO_PASSWORD=$/m', 'DEMO_PASSWORD=College#2026Demo', $env) ?? $env;
    $env = preg_replace('/^DB_USER=.*/m', 'DB_USER=root', $env) ?? $env;
    $env = preg_replace('/^DB_PASS=.*/m', 'DB_PASS=', $env) ?? $env;
    $env = preg_replace('/^APP_URL=.*/m', 'APP_URL=' . $url, $env) ?? $env;
    file_put_contents($envPath, $env);
    out('أُنشئ ملف الإعداد .env للتشغيل المحلي.');
}

$vendor = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($vendor)) {
    out('جارٍ تثبيت المكتبات (composer)...');
    $composer = $root . DIRECTORY_SEPARATOR . 'composer.phar';
    if (!is_file($composer)) {
        $phar = @file_get_contents('https://getcomposer.org/composer-stable.phar');
        if ($phar === false) {
            fail("تعذر تنزيل Composer. تحقق من الإنترنت أو ثبّت Composer يدوياً ثم أعد التشغيل.");
        }
        file_put_contents($composer, $phar);
    }
    passthru(escapeshellarg($php) . ' ' . escapeshellarg($composer) . ' install --no-dev --no-interaction --working-dir=' . escapeshellarg($root), $composerCode);
    if ($composerCode !== 0 || !is_file($vendor)) {
        fail('فشل تثبيت المكتبات.');
    }
}

require $vendor;
use App\Env;

Env::load($envPath);

function tryMysql(string $host, string $port, string $user, string $pass): bool
{
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return true;
    } catch (Throwable) {
        return false;
    }
}

function writeEnvValue(string $path, string $key, string $value): void
{
    $raw = (string) file_get_contents($path);
    $line = $key . '=' . $value;
    if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $raw)) {
        $raw = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $raw, 1) ?? $raw;
    } else {
        $raw .= PHP_EOL . $line . PHP_EOL;
    }
    file_put_contents($path, $raw);
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}

$host = Env::get('DB_HOST', '127.0.0.1') ?: '127.0.0.1';
$dbPort = Env::get('DB_PORT', '3306') ?: '3306';

if (PHP_OS_FAMILY === 'Windows') {
    foreach (['C:\\xampp\\mysql_start.bat', 'C:\\xampp\\mysql\\bin\\mysqld.exe'] as $mysqlStart) {
        if (is_file($mysqlStart) && !tryMysql($host, $dbPort, 'root', '')) {
            out('محاولة تشغيل MySQL من XAMPP...');
            if (str_ends_with(strtolower($mysqlStart), '.bat')) {
                pclose(popen('start /MIN "" ' . escapeshellarg($mysqlStart), 'r'));
            }
            sleep(4);
            break;
        }
    }
}

$candidates = [
    [Env::get('DB_USER', 'root') ?: 'root', (string) (Env::get('DB_PASS') ?? '')],
    ['root', ''],
    ['root', 'root'],
    ['ufam', 'ufam_local_dev_only'],
];

$okUser = null;
$okPass = null;
foreach ($candidates as [$user, $pass]) {
    if (tryMysql($host, $dbPort, $user, $pass)) {
        $okUser = $user;
        $okPass = $pass;
        break;
    }
}

if ($okUser === null) {
    fail(
        "لم يتم الاتصال بـ MySQL على {$host}:{$dbPort}.\n" .
        "على ويندوز: ثبّت XAMPP وشغّل MySQL من لوحة التحكم ثم أعد النقر على START.bat\n" .
        "https://www.apachefriends.org/"
    );
}

writeEnvValue($envPath, 'DB_USER', $okUser);
writeEnvValue($envPath, 'DB_PASS', $okPass);
Env::load($envPath);
out("الاتصال بقاعدة البيانات جاهز (المستخدم: {$okUser}).");

out('تجهيز الجداول والبيانات التجريبية...');
passthru(escapeshellarg($php) . ' ' . escapeshellarg($root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'install.php'), $installCode);
if ($installCode !== 0) {
    fail('فشل تجهيز قاعدة البيانات. تأكد أن MySQL يعمل.');
}

out('');
out('التشغيل: ' . $url);
out('حساب المدير: admin   /   huda87654321');
out('حسابات العرض: finance أو cashier   /   College#2026Demo');
out('للإيقاف: أغلق هذه النافذة أو اضغط Ctrl+C');
out('');

$open = 'http://127.0.0.1:' . $port . '/login';
if (PHP_OS_FAMILY === 'Windows') {
    pclose(popen('cmd /c start "" /B cmd /c "timeout /t 1 /nobreak >nul & start ' . $open . '"', 'r'));
} elseif (PHP_OS_FAMILY === 'Darwin') {
    exec('bash -c ' . escapeshellarg('sleep 1; open ' . escapeshellarg($open)) . ' >/dev/null 2>&1 &');
} else {
    exec('bash -c ' . escapeshellarg('sleep 1; xdg-open ' . escapeshellarg($open) . ' >/dev/null 2>&1') . ' >/dev/null 2>&1 &');
}

$cmd = sprintf(
    '%s -S 127.0.0.1:%d -t %s %s',
    escapeshellarg($php),
    $port,
    escapeshellarg($root . DIRECTORY_SEPARATOR . 'public'),
    escapeshellarg($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'router.php')
);
passthru($cmd, $serverCode);
exit($serverCode);
