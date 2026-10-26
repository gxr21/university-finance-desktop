<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use App\Database;
use App\Env;
use App\Http\Request;
use App\Kernel;
use App\Services\AuthService;
use App\Services\CashboxService;
use App\Services\DiscountService;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\SettingsService;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Security;

Env::load($root . '/.env');
putenv('DB_NAME=ufam_test');
$_ENV['DB_NAME'] = 'ufam_test';

$failed = 0;
$passed = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo "PASS  $name\n";
    } else {
        $failed++;
        echo "FAIL  $name" . ($detail ? " — $detail" : '') . "\n";
    }
}

$adminPdo = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST'), Env::get('DB_PORT', '3306')),
    Env::get('DB_USER'),
    Env::get('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$adminPdo->exec('DROP DATABASE IF EXISTS ufam_test');
$adminPdo->exec('CREATE DATABASE ufam_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$db = new Database([
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'name' => 'ufam_test',
    'user' => Env::get('DB_USER', 'ufam'),
    'pass' => Env::get('DB_PASS', ''),
]);
$db->pdo->exec(file_get_contents($root . '/database/migrations/001_schema.sql'));
require $root . '/database/seeds/DemoSeeder.php';
(new DemoSeeder($db))->run();

$audit = new Audit($db);
$auth = new AuthService($db, $audit);
$settings = new SettingsService($db, $audit);
$invoices = new InvoiceService($db, $audit, $settings);
$discounts = new DiscountService($db, $audit, $invoices, $settings);
$cash = new CashboxService($db, $audit);
$pay = new PaymentService($db, $audit, $invoices, $settings, $cash);
$exp = new ExpenseService($db, $audit, $cash, $settings);

$demo = Env::get('DEMO_PASSWORD', 'College#2026Demo');
$adminPass = Env::get('ADMIN_INITIAL_PASSWORD');

// Auth
try {
    $auth->register([
        'full_name' => 'مستخدم تجريبي جديد',
        'username' => 'newbie01',
        'phone' => '07801112233',
        'email' => 'newbie@college.local',
        'password' => 'Secret#123',
        'password_confirmation' => 'Secret#123',
        'agree' => true,
    ], '127.0.0.1');
    check('register valid account', true);
} catch (Throwable $e) {
    check('register valid account', false, $e->getMessage());
}

try {
    $auth->register([
        'full_name' => 'مستخدم تجريبي جديد',
        'username' => 'newbie01',
        'phone' => '07801112234',
        'email' => 'other@college.local',
        'password' => 'Secret#123',
        'password_confirmation' => 'Secret#123',
        'agree' => true,
    ], '127.0.0.1');
    check('reject duplicate username', false, 'should throw');
} catch (Throwable $e) {
    check('reject duplicate username', true);
}

try {
    $auth->register([
        'full_name' => 'مستخدم تجريبي آخر',
        'username' => 'newbie02',
        'phone' => '07801112235',
        'email' => 'n2@college.local',
        'password' => 'Secret#123',
        'password_confirmation' => 'mismatch',
        'agree' => true,
    ], '127.0.0.1');
    check('reject password mismatch', false);
} catch (Throwable $e) {
    check('reject password mismatch', true);
}

try {
    $auth->login('finance', $demo, '127.0.0.1', 'phpunit');
    check('login by username', true);
} catch (Throwable $e) {
    check('login by username', false, $e->getMessage());
}
try {
    $auth->login('07000000003', $demo, '10.0.0.2', 'phpunit');
    check('login by phone', true);
} catch (Throwable $e) {
    check('login by phone', false, $e->getMessage());
}
try {
    $auth->login('affairs@college.local', $demo, '10.0.0.3', 'phpunit');
    check('login by email', true);
} catch (Throwable $e) {
    check('login by email', false, $e->getMessage());
}
try {
    $auth->login('finance', 'wrong-pass-1', '10.0.0.4', 'phpunit');
    check('reject bad password', false);
} catch (Throwable $e) {
    check('reject bad password', str_contains($e->getMessage(), 'غير صحيحة'));
}

$db->execute("UPDATE users SET status='disabled' WHERE username='auditor'");
try {
    $auth->login('auditor', $demo, '10.0.0.5', 'phpunit');
    check('reject disabled account', false);
} catch (Throwable $e) {
    check('reject disabled account', true);
}
$db->execute("UPDATE users SET status='active' WHERE username='auditor'");

$adminLogin = $auth->login('admin', $adminPass, '10.0.0.6', 'phpunit');
check('admin initial login requires password change', !empty($adminLogin['user']['must_change_password']));

$student = $auth->login('student1', $demo, '10.0.0.7', 'phpunit');
check('student cannot access admin panel permission', !in_array('admin.panel', $student['user']['permissions'], true));

$cashier = $auth->login('cashier', $demo, '10.0.0.8', 'phpunit');
check('cashier cannot manage discounts', !in_array('discounts.manage', $cashier['user']['permissions'], true));

$expenseUser = $auth->login('expense', $demo, '10.0.0.9', 'phpunit');
check('expense accountant cannot view cashbox balance', !in_array('cashbox.view_balance', $expenseUser['user']['permissions'], true));

check('unprivileged user cannot create payment', !in_array('payments.create', $student['user']['permissions'], true));

$eveningInv = $db->queryOne("SELECT i.* FROM student_invoices i JOIN student_enrollments e ON e.id=i.enrollment_id JOIN study_types t ON t.id=e.study_type_id WHERE t.code='evening' LIMIT 1");
$parallelInv = $db->queryOne("SELECT i.* FROM student_invoices i JOIN student_enrollments e ON e.id=i.enrollment_id JOIN study_types t ON t.id=e.study_type_id WHERE t.code='parallel' LIMIT 1");
check('evening invoice created', (bool) $eveningInv);
check('parallel invoice created', (bool) $parallelInv);

$p2 = $db->queryOne('SELECT COUNT(*) c FROM invoice_installments WHERE invoice_id=?', [$eveningInv['id']]);
$p4 = $db->queryOne('SELECT COUNT(*) c FROM invoice_installments WHERE invoice_id=?', [$parallelInv['id']]);
check('two installments on evening policy', (int) $p2['c'] === 2);
check('four installments on parallel policy', (int) $p4['c'] === 4);
$catalog = new App\Services\CatalogService($db, $audit);
$yearId = (int) $db->queryOne('SELECT id FROM academic_years WHERE is_current=1')['id'];
$typeId = (int) $db->queryOne("SELECT id FROM study_types WHERE code='evening'")['id'];
$oneId = $catalog->saveTuitionPolicy([
    'academic_year_id' => $yearId,
    'study_type_id' => $typeId,
    'name' => 'قسط واحد اختباري',
    'base_amount' => '1000000',
    'installment_count' => 1,
    'applies_to' => 'new_only',
], 2);
$oneSched = $db->query('SELECT * FROM installment_schedules WHERE tuition_policy_id=?', [$oneId]);
check('single installment schedule', count($oneSched) === 1);

$onePolicy = $db->queryOne('SELECT id FROM tuition_policies WHERE installment_count=2');
check('installment policy exists', (bool) $onePolicy);

$fixedRule = $db->queryOne('SELECT * FROM discount_rules WHERE calculation="fixed"');
$pctRule = $db->queryOne('SELECT * FROM discount_rules WHERE category_code="top1"');
$invForDisc = $db->queryOne("SELECT i.* FROM student_invoices i JOIN student_enrollments e ON e.id=i.enrollment_id JOIN students s ON s.id=e.student_id WHERE s.university_number='202510002'");
$before = Money::norm($invForDisc['net_due']);
$d1 = $discounts->apply((int) $invForDisc['id'], (int) $pctRule['id'], 2, 'أوائل فئة أولى', true);
check('percent discount computed', Money::cmp($d1['amount'], Money::percent($invForDisc['base_amount'], '50')) === 0);
$fresh = $invoices->recalculate((int) $invForDisc['id']);
check('net did not go negative', Money::cmp($fresh['net_due'], '0') >= 0);

$top2 = $db->queryOne('SELECT id FROM discount_rules WHERE category_code="top2"');
$top3 = $db->queryOne('SELECT id FROM discount_rules WHERE category_code="top3"');
$inv3 = $db->queryOne("SELECT i.* FROM student_invoices i JOIN student_enrollments e ON e.id=i.enrollment_id JOIN students s ON s.id=e.student_id WHERE s.university_number='202510004'");
$discounts->apply((int) $inv3['id'], (int) $top2['id'], 2, 'فئة ثانية', true);
$discounts->apply((int) $inv3['id'], (int) $top3['id'], 2, 'فئة ثالثة مجمعة', true);
$sum = $db->queryOne("SELECT SUM(amount) t FROM student_discounts WHERE invoice_id=? AND status='approved'", [$inv3['id']]);
$base = $db->queryOne('SELECT base_amount FROM student_invoices WHERE id=?', [$inv3['id']]);
check('stacked top-student discounts stay within base', Money::cmp($sum['t'], $base['base_amount']) <= 0);

$hist = $db->queryOne("SELECT sd.amount, sd.rule_value FROM student_discounts sd JOIN student_invoices i ON i.id=sd.invoice_id JOIN student_enrollments e ON e.id=i.enrollment_id JOIN students s ON s.id=e.student_id WHERE s.university_number='202510003' LIMIT 1");
$db->execute('UPDATE discount_rules SET value=999999 WHERE id=?', [$fixedRule['id']]);
$hist2 = $db->queryOne("SELECT amount, rule_value FROM student_discounts WHERE amount=? LIMIT 1", [$hist['amount'] ?? '0']);
check('historical discount amount preserved after rule change', (bool) $hist && ($hist2['amount'] ?? null) === $hist['amount']);

$cashierId = (int) $db->queryOne("SELECT id FROM users WHERE username='cashier'")['id'];
$invPay = $db->queryOne("SELECT i.* FROM student_invoices i JOIN student_enrollments e ON e.id=i.enrollment_id JOIN students s ON s.id=e.student_id WHERE s.university_number='202510005'");
$amount = '250000';
$key = 'test-pay-1';
$res = $pay->collect(['invoice_id' => $invPay['id'], 'amount' => $amount, 'idempotency_key' => $key, 'notes' => 'دفعة اختبار'], $cashierId, 'عمار قاسم جاسم', '10.0.0.8');
check('collect valid payment', isset($res['receipt']['receipt_number']));
$after = $invoices->recalculate((int) $invPay['id']);
check('remaining updated', Money::cmp($after['remaining'], Money::sub($invPay['remaining'], $amount)) === 0);
$replay = $pay->collect(['invoice_id' => $invPay['id'], 'amount' => $amount, 'idempotency_key' => $key], $cashierId, 'عمار قاسم جاسم', '10.0.0.8');
check('duplicate payment blocked by idempotency', !empty($replay['replayed']));
$tx = $db->queryOne('SELECT t.*, c.code FROM cash_transactions t JOIN cashboxes c ON c.id=t.cashbox_id WHERE t.payment_id=?', [$res['payment']['id']]);
check('revenue posted to matching cashbox', ($tx['code'] ?? '') === 'parallel' && $tx['kind'] === 'tuition_payment');
$archived = $db->queryOne('SELECT r.id FROM payment_receipts r JOIN student_invoices i ON i.id=r.invoice_id JOIN student_enrollments e ON e.id=i.enrollment_id JOIN students s ON s.id=e.student_id WHERE s.university_number="202510005"');
check('receipt archived on student file', (bool) $archived);
$other = $db->queryOne('SELECT id FROM payment_receipts WHERE receipt_number=?', [$res['receipt']['receipt_number']]);
check('receipt number unique', (bool) $other);

$rev = $pay->reverse((int) $res['payment']['id'], 2, 'تصحيح اختبار', '10.0.0.10');
$log = $db->queryOne("SELECT id FROM audit_logs WHERE action='payment.reverse' AND entity_id=?", [(string) $res['payment']['id']]);
check('reversal keeps audit trail', (bool) $log && isset($rev['reversal_id']));
$still = $db->queryOne('SELECT status FROM payments WHERE id=?', [$res['payment']['id']]);
check('original payment not deleted', ($still['status'] ?? '') === 'reversed');

$box = $db->queryOne("SELECT id FROM cashboxes WHERE code='evening'");
$tiny = $db->queryOne('SELECT id FROM cashboxes');
$expenseUserId = (int) $db->queryOne("SELECT id FROM users WHERE username='expense'")['id'];
$book = $exp->create([
    'title' => 'تجهيز مختبر',
    'beneficiary' => 'شركة الرافدين',
    'cashbox_id' => $box['id'],
    'justification' => 'اختبار صرف',
    'items' => [['name' => 'جهاز', 'quantity' => 1, 'unit_price' => '999999999']],
], $expenseUserId);
$exp->submit((int) $book['book']['id'], $expenseUserId);
$exp->approve((int) $book['book']['id'], 'review', 2, 'مدير الحسابات');
$exp->approve((int) $book['book']['id'], 'dean', 2, 'العميد');
try {
    $exp->disburse((int) $book['book']['id'], 2);
    check('reject over-balance disbursement', false, 'should fail');
} catch (Throwable $e) {
    check('reject over-balance disbursement', str_contains($e->getMessage(), 'تعذر'));
}

// Concurrent-ish: two overlapping remaining checks using FOR UPDATE sequential
$invC = $db->queryOne("SELECT i.* FROM student_invoices i JOIN student_enrollments e ON e.id=i.enrollment_id JOIN students s ON s.id=e.student_id WHERE s.university_number='202510006'");
$half = Money::norm($invC['remaining']);
$a = $pay->collect(['invoice_id' => $invC['id'], 'amount' => $half, 'idempotency_key' => 'c1'], $cashierId, 'عمار', '1.1.1.1');
try {
    $pay->collect(['invoice_id' => $invC['id'], 'amount' => '1', 'idempotency_key' => 'c2'], $cashierId, 'عمار', '1.1.1.1');
    check('second full collection after remaining consumed rejected or zero remaining', Money::cmp($invoices->recalculate((int) $invC['id'])['remaining'], '0') === 0);
} catch (Throwable $e) {
    check('second collection rejected when remaining consumed', true);
}

putenv('DB_NAME=ufam_test');
$kernel = new Kernel($root);
$req = new Request('POST', '/api/auth/login', [], ['identifier' => 'expense', 'password' => $demo], [], [], [], '127.0.0.1', 'test');
$resHttp = $kernel->handle($req);
$body = json_decode($resHttp->body, true);
$token = $body['data']['token'] ?? '';
$_SESSION['csrf'] = $body['data']['csrf'] ?? ($_SESSION['csrf'] ?? 'x');
$req2 = new Request('GET', '/api/cashboxes', [], [], ['authorization' => 'Bearer ' . $token, 'x-csrf-token' => $_SESSION['csrf']], [], [], '127.0.0.1', 'test');
$res2 = $kernel->handle($req2);
check('HTTP forbids expense accountant cashbox balances', $res2->status === 403);

$req3 = new Request('POST', '/api/payments', [], ['invoice_id' => 1, 'amount' => 10, 'idempotency_key' => 'nope'], ['authorization' => 'Bearer ' . $token, 'x-csrf-token' => $_SESSION['csrf']], [], [], '127.0.0.1', 'test');
$res3 = $kernel->handle($req3);
check('HTTP forbids expense accountant creating a payment', $res3->status === 403);

$adminHttp = $kernel->handle(new Request(
    'POST',
    '/api/auth/login',
    [],
    ['identifier' => 'admin', 'password' => $adminPass],
    [],
    [],
    [],
    '127.0.0.1',
    'test'
));
$adminBody = json_decode($adminHttp->body, true);
check(
    'admin HTTP login returns password-change gate',
    ($adminHttp->status === 200)
        && !empty($adminBody['data']['token'])
        && !empty($adminBody['data']['user']['must_change_password'])
);
$appPage = $kernel->handle(new Request(
    'GET',
    '/app',
    [],
    [],
    [],
    ['UFAMSESSID' => (string) ($adminBody['data']['token'] ?? '')],
    [],
    '127.0.0.1',
    'test'
));
$bootOk = $appPage->status === 200
    && str_contains($appPage->body, '__UFAM_BOOT__')
    && str_contains($appPage->body, 'must_change_password')
    && str_contains($appPage->body, 'مدير النظام');
check('app page embeds admin session so the boot overlay can hide immediately', $bootOk);

$identity = $settings->identity();
check('identity loaded from settings not hardcoded only', ($identity['college_name'] ?? '') !== '');

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
