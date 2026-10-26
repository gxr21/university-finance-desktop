<?php
declare(strict_types=1);

namespace App;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Services\AuthService;
use App\Services\CashboxService;
use App\Services\CatalogService;
use App\Services\DiscountService;
use App\Services\ExpenseService;
use App\Services\FileService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\SettingsService;
use App\Services\StudentService;
use App\Services\TeacherService;
use App\Services\TimetableService;
use App\Services\UserAdminService;
use App\Support\Audit;
use App\Support\Qr;
use App\Support\Security;

final class Kernel
{
    private Database $db;
    private Router $router;
    private AuthService $auth;
    private SettingsService $settings;
    private Audit $audit;
    private InvoiceService $invoices;
    private DiscountService $discounts;
    private CashboxService $cashboxes;
    private PaymentService $payments;
    private StudentService $students;
    private ExpenseService $expenses;
    private UserAdminService $users;
    private CatalogService $catalog;
    private TeacherService $teachers;
    private TimetableService $timetable;
    private ReportService $reports;
    private FileService $files;

    public function __construct(string $root)
    {
        Env::load($root . '/.env');
        $this->db = new Database([
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => Env::get('DB_PORT', '3306'),
            'name' => Env::get('DB_NAME', 'ufam'),
            'user' => Env::get('DB_USER', 'ufam'),
            'pass' => Env::get('DB_PASS', ''),
        ]);
        $this->audit = new Audit($this->db);
        $this->auth = new AuthService($this->db, $this->audit);
        $this->settings = new SettingsService($this->db, $this->audit);
        $this->invoices = new InvoiceService($this->db, $this->audit, $this->settings);
        $this->discounts = new DiscountService($this->db, $this->audit, $this->invoices, $this->settings);
        $this->cashboxes = new CashboxService($this->db, $this->audit);
        $this->payments = new PaymentService($this->db, $this->audit, $this->invoices, $this->settings, $this->cashboxes);
        $this->students = new StudentService($this->db, $this->audit, $this->invoices);
        $this->expenses = new ExpenseService($this->db, $this->audit, $this->cashboxes, $this->settings);
        $this->users = new UserAdminService($this->db, $this->audit, $this->auth);
        $this->catalog = new CatalogService($this->db, $this->audit);
        $this->teachers = new TeacherService($this->db, $this->audit);
        $this->timetable = new TimetableService($this->db, $this->audit);
        $this->reports = new ReportService($this->db);
        $this->files = new FileService($this->db, $root . '/storage');
        $this->router = new Router();
        $this->routes();
    }

    public function handle(?Request $request = null): Response
    {
        $request ??= Request::fromGlobals();
        try {
            if ($request->method === 'OPTIONS') {
                return new Response(204);
            }
            if (!str_starts_with($request->path, '/api')) {
                try {
                    return $this->page($request);
                } catch (HttpException $e) {
                    if ($e->status === 401) {
                        return Response::redirect('/login');
                    }
                    return Response::html('<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><body style="font-family:Tajawal,sans-serif;padding:2rem">' . Security::e($e->getMessage()) . '</body></html>', $e->status);
                }
            }
            $match = $this->router->match($request);
            if (!$match) {
                throw HttpException::notFound('المسار غير موجود.');
            }
            $route = $match['route'];
            $token = $this->sessionToken($request);
            if ($route['auth']) {
                $user = $this->auth->resolveSession($token);
                if (!$user) {
                    throw HttpException::unauthorized();
                }
                $request->user = $user;
                $request->permissions = $user['permissions'];
                if (!empty($user['must_change_password']) && !str_contains($request->path, '/auth/change-password') && !str_contains($request->path, '/auth/logout') && !str_contains($request->path, '/auth/me')) {
                    throw HttpException::forbidden('يجب تغيير كلمة المرور الابتدائية أولاً.');
                }
                if ($route['permission'] && !$request->can($route['permission'])) {
                    $this->audit->log((int) $user['id'], 'access.denied', 'route', $request->path, 'denied', $route['permission'], null, null, $request->ip);
                    throw HttpException::forbidden();
                }
            }
            if (in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $route['auth']) {
                $csrf = $request->header('x-csrf-token') ?? (string) $request->input('csrf_token', '');
                $expected = $_SESSION['csrf'] ?? '';
                if ($expected === '' || !hash_equals($expected, $csrf)) {
                    throw HttpException::forbidden('رمز الحماية غير صالح. أعد تحميل الصفحة.');
                }
            }
            $result = ($route['handler'])($request, $match['params']);
            return $result instanceof Response ? $result : Response::json(['ok' => true, 'data' => $result]);
        } catch (HttpException $e) {
            return Response::json([
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors,
            ], $e->status);
        } catch (\Throwable $e) {
            if (Env::bool('APP_DEBUG')) {
                return Response::json(['ok' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
            }
            return Response::json(['ok' => false, 'message' => 'حدث خطأ غير متوقع. أُسجل في سجل النظام.'], 500);
        }
    }

    private function sessionToken(Request $request): ?string
    {
        $this->startPhpSession();
        if ($request->bearer()) {
            return $request->bearer();
        }
        $cookie = Env::get('SESSION_NAME', 'UFAMSESSID');
        return $request->cookies[$cookie] ?? ($_SESSION['api_token'] ?? null);
    }

    private function startPhpSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            return;
        }
        $secure = Env::bool('COOKIE_SECURE', false);
        session_name(Env::get('SESSION_NAME', 'UFAMSESSID') . '_PHP');
        session_set_cookie_params([
            'lifetime' => Env::int('SESSION_LIFETIME', 28800),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => Env::get('COOKIE_SAMESITE', 'Lax'),
        ]);
        session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = Security::randomToken(16);
        }
    }

    private function setAuthCookie(string $token): void
    {
        $this->startPhpSession();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['api_token'] = $token;
            $_SESSION['csrf'] = Security::randomToken(16);
        }
        if (headers_sent()) {
            return;
        }
        $name = Env::get('SESSION_NAME', 'UFAMSESSID');
        setcookie($name, $token, [
            'expires' => time() + Env::int('SESSION_LIFETIME', 28800),
            'path' => '/',
            'secure' => Env::bool('COOKIE_SECURE', false),
            'httponly' => true,
            'samesite' => Env::get('COOKIE_SAMESITE', 'Lax'),
        ]);
    }

    private function clearAuthCookie(): void
    {
        $this->startPhpSession();
        $name = Env::get('SESSION_NAME', 'UFAMSESSID');
        setcookie($name, '', ['expires' => time() - 3600, 'path' => '/']);
        $_SESSION = [];
        session_destroy();
    }

    private function page(Request $request): Response
    {
        $path = $request->path;
        if ($path === '/') {
            $token = $this->sessionToken($request);
            $user = $this->auth->resolveSession($token);
            return Response::redirect($user ? '/app' : '/login');
        }
        $identity = $this->settings->identity();
        if ($path === '/login') {
            return $this->view('login', ['identity' => $identity]);
        }
        if ($path === '/register') {
            return $this->view('register', ['identity' => $identity]);
        }
        if ($path === '/reset') {
            return $this->view('reset', ['identity' => $identity, 'token' => $request->query['token'] ?? '']);
        }
        if ($path === '/app' || $path === '/admin') {
            $this->startPhpSession();
            return $this->view('app', [
                'identity' => $identity,
                'csrf' => $_SESSION['csrf'] ?? '',
                'adminOnly' => $path === '/admin',
            ]);
        }
        if (preg_match('#^/receipts/(\d+)/print$#', $path, $m)) {
            return $this->printReceipt((int) $m[1], $request);
        }
        if (preg_match('#^/expenses/(\d+)/print$#', $path, $m)) {
            return $this->printExpense((int) $m[1], $request);
        }
        if (preg_match('#^/verify/([A-Za-z0-9]+)$#', $path, $m)) {
            $row = $this->payments->publicVerify($m[1]);
            return $this->view('verify', ['identity' => $identity, 'row' => $row]);
        }
        if (preg_match('#^/files/(\d+)$#', $path, $m)) {
            return $this->downloadFile((int) $m[1], $request);
        }
        throw HttpException::notFound('الصفحة غير موجودة.');
    }

    private function view(string $name, array $data = []): Response
    {
        extract($data, EXTR_SKIP);
        ob_start();
        $view = dirname(__DIR__) . '/views/' . $name . '.php';
        if (!is_file($view)) {
            throw HttpException::notFound();
        }
        include $view;
        return Response::html((string) ob_get_clean());
    }

    private function requireUser(Request $request): array
    {
        $user = $this->auth->resolveSession($this->sessionToken($request));
        if (!$user) {
            throw HttpException::unauthorized();
        }
        return $user;
    }

    private function printReceipt(int $id, Request $request): Response
    {
        $user = $this->requireUser($request);
        if (!$this->canAny($user, ['receipts.view', 'receipts.print', 'payments.create', 'students.view'])) {
            throw HttpException::forbidden();
        }
        if (!empty($user['student_id'])) {
            $owned = $this->db->queryOne(
                'SELECT r.id FROM payment_receipts r JOIN student_invoices i ON i.id=r.invoice_id JOIN student_enrollments e ON e.id=i.enrollment_id WHERE r.id=? AND e.student_id=?',
                [$id, $user['student_id']]
            );
            if (!$owned) {
                throw HttpException::forbidden();
            }
        }
        $receipt = $this->db->queryOne('SELECT * FROM payment_receipts WHERE id=?', [$id]);
        if (!$receipt) {
            throw HttpException::notFound();
        }
        $snap = $this->invoices->snapshot((int) $receipt['invoice_id']);
        $tokenFile = dirname(__DIR__) . '/storage/receipts/' . $id . '.token';
        $token = is_file($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';
        $qr = $token ? Qr::dataUri(rtrim((string) Env::get('APP_URL'), '/') . '/verify/' . $token) : '';
        $identity = json_decode((string) $receipt['identity_snapshot'], true) ?: $this->settings->identity();
        return $this->view('receipt_print', compact('receipt', 'snap', 'identity', 'qr'));
    }

    private function printExpense(int $id, Request $request): Response
    {
        $user = $this->requireUser($request);
        if (!$this->canAny($user, ['expenses.view', 'expenses.create', 'expenses.approve', 'expenses.disburse'])) {
            throw HttpException::forbidden();
        }
        $data = $this->expenses->get($id);
        if (!$this->canAny($user, ['expenses.approve', 'expenses.disburse']) && (int) $data['book']['created_by'] !== (int) $user['id']) {
            throw HttpException::forbidden();
        }
        $identity = json_decode((string) ($data['book']['identity_snapshot'] ?? ''), true) ?: $this->settings->identity();
        return $this->view('expense_print', $data + compact('identity'));
    }

    private function downloadFile(int $id, Request $request): Response
    {
        $user = $this->requireUser($request);
        if (!$this->canAny($user, ['students.view', 'students.manage', 'identity.manage', 'expenses.view', 'expenses.create'])) {
            throw HttpException::forbidden();
        }
        $row = $this->db->queryOne('SELECT * FROM file_attachments WHERE id=?', [$id]);
        if (!$row) {
            throw HttpException::notFound();
        }
        $path = $this->files->absolute($row['stored_path']);
        return Response::file($path, $row['original_name'], $row['mime_type']);
    }

    private function canAny(array $user, array $perms): bool
    {
        foreach ($perms as $p) {
            if (in_array($p, $user['permissions'], true)) {
                return true;
            }
        }
        return false;
    }

    private function routes(): void
    {
        $r = $this->router;
        $r->add('POST', '/api/auth/register', fn(Request $req) => $this->auth->register($req->body, $req->ip), false);
        $r->add('POST', '/api/auth/login', function (Request $req) {
            $data = $this->auth->login((string) $req->input('identifier', ''), (string) $req->input('password', ''), $req->ip, $req->userAgent);
            $this->setAuthCookie($data['token']);
            $data['csrf'] = $_SESSION['csrf'];
            return $data;
        }, false);
        $r->add('POST', '/api/auth/qr', function (Request $req) {
            $data = $this->auth->loginWithQr((string) $req->input('token', ''), $req->ip, $req->userAgent);
            $this->setAuthCookie($data['token']);
            $data['csrf'] = $_SESSION['csrf'];
            return $data;
        }, false);
        $r->add('POST', '/api/auth/forgot', function (Request $req) {
            $this->auth->requestReset((string) $req->input('identifier', ''), $req->ip);
            return ['message' => 'إذا كان الحساب موجوداً فستصلك تعليمات الاستعادة وفق سياسة المؤسسة.'];
        }, false);
        $r->add('POST', '/api/auth/reset', function (Request $req) {
            $this->auth->resetPassword((string) $req->input('token', ''), (string) $req->input('password', ''), (string) $req->input('password_confirmation', ''), $req->ip);
            return ['message' => 'تم تعيين كلمة المرور. يمكنك تسجيل الدخول.'];
        }, false);
        $r->add('POST', '/api/auth/logout', function (Request $req) {
            $token = $this->sessionToken($req);
            if ($token) {
                $this->auth->logout($token);
            }
            $this->clearAuthCookie();
            return ['message' => 'تم تسجيل الخروج.'];
        }, false);
        $r->add('GET', '/api/auth/me', function (Request $req) {
            $this->startPhpSession();
            $user = $req->user;
            $identity = $this->settings->identity();
            $catalog = $this->catalog->bootstrap();
            return ['user' => $user, 'identity' => $identity, 'catalog' => $catalog, 'csrf' => $_SESSION['csrf'] ?? ''];
        });
        $r->add('POST', '/api/auth/change-password', function (Request $req) {
            $this->auth->changePassword((int) $req->user['id'], (string) $req->input('current_password', ''), (string) $req->input('password', ''), (string) $req->input('password_confirmation', ''));
            return ['message' => 'تم تغيير كلمة المرور.'];
        });
        $r->add('GET', '/api/dashboard', fn(Request $req) => $this->reports->dashboard($req->permissions, $req->user['student_id'] ?? null));
        $r->add('GET', '/api/notifications', function (Request $req) {
            return $this->db->query('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 30', [$req->user['id']]);
        });
        $r->add('POST', '/api/notifications/{id}/read', function (Request $req, array $p) {
            $this->db->execute('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [$p['id'], $req->user['id']]);
            return ['ok' => true];
        });

        $r->add('GET', '/api/students', function (Request $req) {
            $this->assertCan($req, 'students.view');
            return $this->students->list($req->query, (int) ($req->query['page'] ?? 1));
        });
        $r->add('GET', '/api/students/search', function (Request $req) {
            $this->assertCanAny($req, ['students.view', 'payments.create', 'students.financial.view']);
            return $this->students->search((string) ($req->query['q'] ?? ''));
        });
        $r->add('GET', '/api/students/{id}', function (Request $req, array $p) {
            $this->guardStudentFile($req, (int) $p['id']);
            return $this->students->file((int) $p['id']);
        });
        $r->add('POST', '/api/students', function (Request $req) {
            $this->assertCan($req, 'students.manage');
            return $this->students->create($req->body, (int) $req->user['id']);
        }, true, 'students.manage');
        $r->add('PUT', '/api/students/{id}', function (Request $req, array $p) {
            $this->assertCan($req, 'students.manage');
            $this->students->update((int) $p['id'], $req->body, (int) $req->user['id']);
            return ['message' => 'تم حفظ بيانات الطالب.'];
        }, true, 'students.manage');

        $r->add('GET', '/api/invoices/{id}', function (Request $req, array $p) {
            $this->assertCanAny($req, ['students.financial.view', 'payments.create', 'tuition.manage']);
            return $this->invoices->snapshot((int) $p['id']);
        });
        $r->add('POST', '/api/enrollments/{id}/invoice', function (Request $req, array $p) {
            $this->assertCan($req, 'tuition.manage');
            return $this->invoices->issueForEnrollment((int) $p['id'], (int) $req->user['id']);
        }, true, 'tuition.manage');

        $r->add('GET', '/api/discount-rules', function (Request $req) {
            $this->assertCanAny($req, ['discounts.manage', 'students.financial.view', 'payments.create']);
            return $this->db->query(
                'SELECT r.*, t.name AS type_name, t.code AS type_code FROM discount_rules r JOIN discount_types t ON t.id=r.discount_type_id ORDER BY t.name, r.name'
            );
        });
        $r->add('POST', '/api/discount-rules', function (Request $req) {
            $this->assertCan($req, 'discounts.manage');
            return ['id' => $this->catalog->saveDiscountRule($req->body, (int) $req->user['id'])];
        }, true, 'discounts.manage');
        $r->add('PUT', '/api/discount-rules/{id}', function (Request $req, array $p) {
            $this->assertCan($req, 'discounts.manage');
            $this->catalog->updateDiscountRule((int) $p['id'], $req->body, (int) $req->user['id']);
            return ['message' => 'تم حفظ قاعدة الخصم. الخصومات التاريخية لا تُعاد كتابتها.'];
        }, true, 'discounts.manage');
        $r->add('POST', '/api/invoices/{id}/discounts', function (Request $req, array $p) {
            $this->assertCan($req, 'discounts.manage');
            return $this->discounts->apply((int) $p['id'], (int) $req->input('rule_id'), (int) $req->user['id'], $req->input('reason'), true);
        }, true, 'discounts.manage');

        $r->add('POST', '/api/payments', function (Request $req) {
            $this->assertCan($req, 'payments.create');
            $result = $this->payments->collect($req->body, (int) $req->user['id'], $req->user['full_name'], $req->ip);
            if (!empty($result['receipt']['id']) && !empty($result['receipt']['verify_token'])) {
                $dir = dirname(__DIR__) . '/storage/receipts';
                if (!is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }
                file_put_contents($dir . '/' . $result['receipt']['id'] . '.token', $result['receipt']['verify_token']);
            }
            return $result;
        }, true, 'payments.create');
        $r->add('POST', '/api/payments/{id}/reverse', function (Request $req, array $p) {
            $this->assertCan($req, 'payments.reverse');
            return $this->payments->reverse((int) $p['id'], (int) $req->user['id'], (string) $req->input('reason', ''), $req->ip);
        }, true, 'payments.reverse');
        $r->add('GET', '/api/receipts', function (Request $req) {
            $this->assertCanAny($req, ['receipts.view', 'payments.create', 'students.view']);
            $q = '%' . ($req->query['q'] ?? '') . '%';
            $sql = 'SELECT r.*, s.full_name, s.university_number FROM payment_receipts r
                    JOIN student_invoices i ON i.id=r.invoice_id
                    JOIN student_enrollments e ON e.id=i.enrollment_id
                    JOIN students s ON s.id=e.student_id WHERE 1=1';
            $params = [];
            if (!empty($req->query['q'])) {
                $sql .= ' AND (r.receipt_number LIKE ? OR s.full_name LIKE ? OR s.university_number LIKE ?)';
                $params = [$q, $q, $q];
            }
            if (!empty($req->user['student_id'])) {
                $sql .= ' AND s.id = ?';
                $params[] = $req->user['student_id'];
            }
            $sql .= ' ORDER BY r.id DESC LIMIT 100';
            return $this->db->query($sql, $params);
        });
        $r->add('POST', '/api/receipts/{id}/print', function (Request $req, array $p) {
            $this->assertCanAny($req, ['receipts.print', 'payments.create', 'receipts.view']);
            $this->payments->recordPrint((int) $p['id'], (int) $req->user['id'], (bool) $req->input('confirmed', false));
            return ['message' => 'سُجّل طلب الطباعة.'];
        });

        $r->add('GET', '/api/cashboxes', function (Request $req) {
            $this->assertCan($req, 'cashbox.view_balance');
            $out = [];
            foreach ($this->db->query('SELECT * FROM cashboxes') as $box) {
                if ($box['code'] === 'evening' && !$req->can('cashbox.evening.view') && !$req->can('reports.financial')) {
                    continue;
                }
                if ($box['code'] === 'parallel' && !$req->can('cashbox.parallel.view') && !$req->can('reports.financial')) {
                    continue;
                }
                $out[] = $this->cashboxes->summary((int) $box['id']);
            }
            return $out;
        }, true, 'cashbox.view_balance');
        $r->add('GET', '/api/cashboxes/{id}/ledger', function (Request $req, array $p) {
            $this->assertCan($req, 'cashbox.view_balance');
            return $this->cashboxes->ledger((int) $p['id'], (int) ($req->query['page'] ?? 1));
        }, true, 'cashbox.view_balance');
        $r->add('POST', '/api/cashboxes/{id}/adjust', function (Request $req, array $p) {
            $this->assertCan($req, 'reports.financial');
            $this->cashboxes->openingOrAdjust((int) $p['id'], (string) $req->input('direction'), (string) $req->input('amount'), (string) $req->input('kind', 'adjustment'), (string) $req->input('notes', ''), (int) $req->user['id']);
            return ['message' => 'سُجّلت الحركة في دفتر الخزنة.'];
        }, true, 'reports.financial');

        $r->add('GET', '/api/expenses', function (Request $req) {
            $this->assertCanAny($req, ['expenses.view', 'expenses.create', 'expenses.approve']);
            $all = $req->can('expenses.approve') || $req->can('expenses.disburse') || $req->can('reports.financial');
            return $this->expenses->list($req->query['status'] ?? null, (int) $req->user['id'], $all);
        });
        $r->add('GET', '/api/expenses/{id}', function (Request $req, array $p) {
            $this->assertCanAny($req, ['expenses.view', 'expenses.create']);
            return $this->expenses->get((int) $p['id']);
        });
        $r->add('POST', '/api/expenses', fn(Request $req) => $this->expenses->create($req->body, (int) $req->user['id']), true, 'expenses.create');
        $r->add('PUT', '/api/expenses/{id}', function (Request $req, array $p) {
            $this->assertCan($req, 'expenses.create');
            return $this->expenses->updateDraft((int) $p['id'], $req->body, (int) $req->user['id']);
        }, true, 'expenses.create');
        $r->add('POST', '/api/expenses/{id}/submit', fn(Request $req, array $p) => $this->expenses->submit((int) $p['id'], (int) $req->user['id']), true, 'expenses.create');
        $r->add('POST', '/api/expenses/{id}/approve', function (Request $req, array $p) {
            $this->assertCan($req, 'expenses.approve');
            return $this->expenses->approve((int) $p['id'], (string) $req->input('step', 'review'), (int) $req->user['id'], $req->user['full_name'], $req->input('comment'));
        }, true, 'expenses.approve');
        $r->add('POST', '/api/expenses/{id}/reject', function (Request $req, array $p) {
            $this->assertCan($req, 'expenses.approve');
            return $this->expenses->reject((int) $p['id'], (int) $req->user['id'], (string) $req->input('reason', ''));
        }, true, 'expenses.approve');
        $r->add('POST', '/api/expenses/{id}/disburse', fn(Request $req, array $p) => $this->expenses->disburse((int) $p['id'], (int) $req->user['id']), true, 'expenses.disburse');

        $r->add('GET', '/api/users', fn() => $this->users->list($_GET['status'] ?? null, $_GET['q'] ?? null), true, 'users.view');
        $r->add('POST', '/api/users', function (Request $req) {
            return ['id' => $this->users->create($req->body, (int) $req->user['id'])];
        }, true, 'users.manage');
        $r->add('POST', '/api/users/{id}/status', function (Request $req, array $p) {
            $perm = $req->input('status') === 'active' ? 'users.approve' : 'users.manage';
            $this->assertCanAny($req, ['users.approve', 'users.manage']);
            $this->users->setStatus((int) $p['id'], (string) $req->input('status'), (int) $req->user['id']);
            return ['message' => 'تم تحديث حالة الحساب.'];
        });
        $r->add('POST', '/api/users/{id}/roles', function (Request $req, array $p) {
            $this->users->setRoles((int) $p['id'], (array) $req->input('roles', []), (int) $req->user['id']);
            return ['message' => 'تم تحديث الأدوار.'];
        }, true, 'roles.manage');
        $r->add('POST', '/api/users/{id}/permissions', function (Request $req, array $p) {
            $this->users->setPermission((int) $p['id'], (string) $req->input('permission'), (string) $req->input('effect', 'allow'), (int) $req->user['id']);
            return ['message' => 'تم تحديث الصلاحية الفردية.'];
        }, true, 'roles.manage');
        $r->add('POST', '/api/users/{id}/password', function (Request $req, array $p) {
            $this->users->resetPassword((int) $p['id'], (string) $req->input('password'), (int) $req->user['id']);
            return ['message' => 'أُعيد تعيين كلمة المرور. سيُطلب تغييرها عند الدخول.'];
        }, true, 'users.manage');
        $r->add('POST', '/api/users/{id}/qr', function (Request $req, array $p) {
            $ttl = (int) ($req->input('ttl') ?: Env::int('QR_LOGIN_TTL_SECONDS', 300));
            $issued = $this->users->issueQr((int) $p['id'], (string) $req->input('purpose', 'login'), $ttl, (bool) $req->input('single_use', true), (int) $req->user['id']);
            $issued['qr'] = Qr::dataUri($issued['token']);
            return $issued;
        }, true, 'qr.manage');
        $r->add('POST', '/api/qr/{id}/revoke', function (Request $req, array $p) {
            $this->users->revokeQr((int) $p['id'], (int) $req->user['id']);
            return ['message' => 'أُلغي الرمز.'];
        }, true, 'qr.manage');

        $r->add('POST', '/api/departments', function (Request $req) {
            return ['id' => $this->catalog->saveDepartment($req->body, (int) $req->user['id'])];
        }, true, 'academic.manage');
        $r->add('POST', '/api/academic-years', function (Request $req) {
            return ['id' => $this->catalog->saveYear($req->body, (int) $req->user['id'])];
        }, true, 'academic.manage');
        $r->add('GET', '/api/tuition-policies', function (Request $req) {
            $this->assertCanAny($req, ['tuition.manage', 'students.financial.view']);
            return $this->db->query(
                'SELECT p.*, st.name AS study_type_name, d.name AS department_name, ss.name AS stage_name, y.name AS year_name
                 FROM tuition_policies p
                 JOIN study_types st ON st.id=p.study_type_id
                 JOIN academic_years y ON y.id=p.academic_year_id
                 LEFT JOIN departments d ON d.id=p.department_id
                 LEFT JOIN study_stages ss ON ss.id=p.study_stage_id
                 ORDER BY p.id DESC'
            );
        });
        $r->add('POST', '/api/tuition-policies', function (Request $req) {
            return ['id' => $this->catalog->saveTuitionPolicy($req->body, (int) $req->user['id'])];
        }, true, 'tuition.manage');

        $r->add('GET', '/api/settings/identity', fn() => $this->settings->identity());
        $r->add('POST', '/api/settings/identity', function (Request $req) {
            $current = $this->settings->identity();
            $this->settings->put('identity', array_merge($current, $req->body), (int) $req->user['id'], (string) $req->input('reason', 'تحديث الهوية'));
            if (!empty($req->body['university_name'])) {
                $uni = $this->db->queryOne('SELECT id FROM universities ORDER BY id ASC LIMIT 1');
                if ($uni) {
                    $this->db->execute('UPDATE universities SET name=? WHERE id=?', [$req->body['university_name'], $uni['id']]);
                }
            }
            if (!empty($req->body['college_name'])) {
                $col = $this->db->queryOne('SELECT id FROM colleges ORDER BY id ASC LIMIT 1');
                if ($col) {
                    $this->db->execute('UPDATE colleges SET name=?, dean_name=? WHERE id=?', [$req->body['college_name'], $req->body['dean_name'] ?? null, $col['id']]);
                }
            }
            return ['identity' => $this->settings->identity()];
        }, true, 'identity.manage');
        $r->add('POST', '/api/settings/financial', function (Request $req) {
            $current = $this->settings->financial();
            $this->settings->put('financial', array_merge($current, $req->body), (int) $req->user['id'], 'تحديث السياسات المالية');
            return ['financial' => $this->settings->financial()];
        }, true, 'settings.manage');
        $r->add('GET', '/api/settings/revisions', function (Request $req) {
            $this->assertCanAny($req, ['identity.manage', 'audit.view']);
            return $this->db->query('SELECT * FROM setting_revisions ORDER BY id DESC LIMIT 100');
        });

        $r->add('GET', '/api/staff', fn() => $this->teachers->listStaff(), true, 'teachers.view');
        $r->add('POST', '/api/staff', function (Request $req) {
            return ['id' => $this->teachers->saveStaff($req->body, (int) $req->user['id'])];
        }, true, 'teachers.manage');
        $r->add('GET', '/api/teacher-fees', fn() => $this->teachers->listFees(), true, 'teachers.view');
        $r->add('POST', '/api/teacher-fees', function (Request $req) {
            return ['id' => $this->teachers->createFee($req->body, (int) $req->user['id'])];
        }, true, 'teachers.manage');
        $r->add('POST', '/api/teacher-fees/{id}/pay', function (Request $req, array $p) {
            $this->teachers->payFee((int) $p['id'], (string) $req->input('amount'), (int) $req->user['id'], $req->input('notes'));
            return ['message' => 'سُجّل صرف أجور التدريسي في وحدته المستقلة عن خزنة الطلبة.'];
        }, true, 'teachers.manage');

        $r->add('GET', '/api/timetable', fn(Request $req) => $this->timetable->list($req->query), true, 'timetable.view');
        $r->add('POST', '/api/timetable', function (Request $req) {
            return ['id' => $this->timetable->save($req->body, (int) $req->user['id'])];
        }, true, 'timetable.manage');
        $r->add('DELETE', '/api/timetable/{id}', function (Request $req, array $p) {
            $this->timetable->delete((int) $p['id'], (int) $req->user['id']);
            return ['message' => 'حُذف الموعد.'];
        }, true, 'timetable.manage');
        $r->add('GET', '/api/subjects', fn() => $this->timetable->subjects(), true, 'timetable.view');
        $r->add('POST', '/api/subjects', function (Request $req) {
            return ['id' => $this->timetable->saveSubject($req->body)];
        }, true, 'timetable.manage');

        $r->add('GET', '/api/reports/financial', function (Request $req) {
            return $this->reports->financial($req->query);
        }, true, 'reports.financial');
        $r->add('GET', '/api/audit', function (Request $req) {
            $q = $req->query['q'] ?? '';
            $sql = 'SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE 1=1';
            $params = [];
            if ($q !== '') {
                $sql .= ' AND (a.action LIKE ? OR a.entity_type LIKE ? OR u.full_name LIKE ?)';
                $like = '%' . $q . '%';
                $params = [$like, $like, $like];
            }
            $sql .= ' ORDER BY a.id DESC LIMIT 200';
            return $this->db->query($sql, $params);
        }, true, 'audit.view');

        $r->add('POST', '/api/backup', function (Request $req) {
            $file = 'backup-' . date('Ymd-His') . '.sql';
            $path = dirname(__DIR__) . '/storage/backups/' . $file;
            $cmd = sprintf(
                'mysqldump --user=%s --password=%s --host=%s --port=%s %s > %s',
                escapeshellarg(Env::get('DB_USER')),
                escapeshellarg(Env::get('DB_PASS')),
                escapeshellarg(Env::get('DB_HOST')),
                escapeshellarg(Env::get('DB_PORT', '3306')),
                escapeshellarg(Env::get('DB_NAME')),
                escapeshellarg($path)
            );
            exec($cmd, $out, $code);
            if ($code !== 0) {
                throw HttpException::unprocessable('تعذر إنشاء النسخة الاحتياطية في هذه البيئة.');
            }
            $this->db->execute('INSERT INTO backup_jobs (filename, notes, created_by) VALUES (?, ?, ?)', [$file, $req->input('notes'), $req->user['id']]);
            $this->audit->log((int) $req->user['id'], 'backup.create', 'backup', $file, 'success');
            return ['filename' => $file];
        }, true, 'backup.manage');
        $r->add('GET', '/api/backup', function () {
            return $this->db->query('SELECT id, filename, notes, created_at FROM backup_jobs ORDER BY id DESC');
        }, true, 'backup.manage');
    }

    private function assertCan(Request $req, string $perm): void
    {
        if (!$req->can($perm)) {
            $this->audit->log((int) ($req->user['id'] ?? 0), 'access.denied', 'permission', $perm, 'denied', $req->path, null, null, $req->ip);
            throw HttpException::forbidden();
        }
    }

    private function assertCanAny(Request $req, array $perms): void
    {
        foreach ($perms as $p) {
            if ($req->can($p)) {
                return;
            }
        }
        throw HttpException::forbidden();
    }

    private function guardStudentFile(Request $req, int $studentId): void
    {
        if (!empty($req->user['student_id'])) {
            if ((int) $req->user['student_id'] !== $studentId) {
                throw HttpException::forbidden();
            }
            return;
        }
        $this->assertCanAny($req, ['students.view', 'students.manage', 'students.financial.view', 'payments.create']);
    }
}
