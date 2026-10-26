<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Env;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Security;

final class AuthService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    public function register(array $data, string $ip): array
    {
        $errors = [];
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));
        $phone = Security::normalizePhone(trim((string) ($data['phone'] ?? '')));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $confirm = (string) ($data['password_confirmation'] ?? '');
        $agree = (bool) ($data['agree'] ?? false);

        if (mb_strlen($fullName) < 5) {
            $errors['full_name'] = 'أدخل الاسم الكامل.';
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9._-]{2,31}$/', $username)) {
            $errors['username'] = 'اسم المستخدم يجب أن يبدأ بحرف ويتراوح بين 3 و32 حرفاً لاتينياً.';
        }
        if (!Security::validPhone($phone)) {
            $errors['phone'] = 'رقم الهاتف غير صالح. استخدم صيغة 07XXXXXXXXX.';
        }
        if (!Security::validEmail($email)) {
            $errors['email'] = 'البريد الإلكتروني غير صالح.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'كلمة المرور لا تقل عن 8 أحرف.';
        }
        if ($password !== $confirm) {
            $errors['password_confirmation'] = 'تأكيد كلمة المرور غير مطابق.';
        }
        if (!$agree) {
            $errors['agree'] = 'يجب الموافقة على سياسة استخدام النظام.';
        }
        if ($errors) {
            throw HttpException::unprocessable('تحقق من الحقول المدخلة.', $errors);
        }

        $dup = $this->db->queryOne(
            'SELECT username, phone, email FROM users WHERE username = ? OR phone = ? OR email = ? LIMIT 1',
            [$username, $phone, $email]
        );
        if ($dup) {
            if (($dup['username'] ?? '') === $username) {
                $errors['username'] = 'اسم المستخدم مستخدم مسبقاً.';
            }
            if (($dup['phone'] ?? '') === $phone) {
                $errors['phone'] = 'رقم الهاتف مسجّل مسبقاً.';
            }
            if (strcasecmp((string) ($dup['email'] ?? ''), $email) === 0) {
                $errors['email'] = 'البريد الإلكتروني مسجّل مسبقاً.';
            }
            throw HttpException::unprocessable('تحقق من الحقول المدخلة.', $errors);
        }

        $approval = Env::bool('REQUIRE_REGISTRATION_APPROVAL', true);
        $status = $approval ? 'pending' : 'active';
        $this->db->execute(
            'INSERT INTO users (full_name, username, phone, email, password_hash, status, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, 0)',
            [$fullName, $username, $phone, $email, Security::hashPassword($password), $status]
        );
        $id = (int) $this->db->lastId();
        $this->audit->log($id, 'user.register', 'user', $id, 'success', null, null, ['status' => $status], $ip);
        return [
            'id' => $id,
            'status' => $status,
            'message' => $status === 'pending'
                ? 'تم إنشاء الحساب وهو بانتظار موافقة الإدارة. لن تُمنح أي صلاحيات مالية تلقائياً.'
                : 'تم إنشاء الحساب. يمكنك تسجيل الدخول الآن.',
        ];
    }

    public function login(string $identifier, string $password, string $ip, string $ua): array
    {
        $identifier = trim($identifier);
        $this->assertNotLocked($identifier, $ip);
        $generic = 'بيانات الدخول غير صحيحة.';
        $user = $this->findByIdentifier($identifier);
        $ok = $user && Security::verifyPassword($password, $user['password_hash']);
        $this->db->execute(
            'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, ?)',
            [$identifier, $ip, $ok ? 1 : 0]
        );
        if (!$ok) {
            $this->audit->log($user['id'] ?? null, 'auth.login', 'user', $user['id'] ?? $identifier, 'failed', 'invalid_credentials', null, null, $ip);
            throw HttpException::unauthorized($generic);
        }
        if ($user['status'] === 'disabled') {
            $this->audit->log((int) $user['id'], 'auth.login', 'user', $user['id'], 'denied', 'disabled', null, null, $ip);
            throw HttpException::unauthorized($generic);
        }
        if ($user['status'] === 'pending') {
            $this->audit->log((int) $user['id'], 'auth.login', 'user', $user['id'], 'denied', 'pending', null, null, $ip);
            throw HttpException::unauthorized('الحساب بانتظار موافقة الإدارة.');
        }
        return $this->issueSession($user, $ip, $ua);
    }

    public function loginWithQr(string $token, string $ip, string $ua): array
    {
        $hash = Security::hashToken($token);
        $row = $this->db->queryOne(
            'SELECT q.*, u.status, u.full_name, u.username, u.must_change_password, u.student_id
             FROM qr_tokens q JOIN users u ON u.id = q.user_id
             WHERE q.token_hash = ? LIMIT 1',
            [$hash]
        );
        if (!$row || $row['revoked_at'] || ($row['single_use'] && $row['used_at']) || strtotime((string) $row['expires_at']) < time()) {
            $this->audit->log(null, 'auth.qr', 'qr_token', null, 'failed', 'invalid_or_expired', null, null, $ip);
            throw HttpException::unauthorized('رمز التحقق غير صالح أو منتهٍ.');
        }
        if ($row['purpose'] !== 'login' && $row['purpose'] !== 'activation') {
            throw HttpException::unauthorized('رمز التحقق غير صالح أو منتهٍ.');
        }
        if ($row['status'] !== 'active') {
            throw HttpException::unauthorized('رمز التحقق غير صالح أو منتهٍ.');
        }
        $this->db->execute(
            'UPDATE qr_tokens SET used_at = NOW(), last_used_ip = ? WHERE id = ?',
            [$ip, $row['id']]
        );
        $user = $this->db->queryOne('SELECT * FROM users WHERE id = ?', [$row['user_id']]);
        $this->audit->log((int) $row['user_id'], 'auth.qr', 'qr_token', $row['id'], 'success', $row['purpose'], null, null, $ip);
        return $this->issueSession($user, $ip, $ua);
    }

    public function requestReset(string $identifier, string $ip): void
    {
        $user = $this->findByIdentifier(trim($identifier));
        // Always succeed to avoid account enumeration.
        if (!$user || $user['status'] === 'disabled') {
            $this->audit->log(null, 'auth.reset_request', 'user', $identifier, 'success', 'opaque', null, null, $ip);
            return;
        }
        $token = Security::randomToken(24);
        $this->db->execute(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))',
            [$user['id'], Security::hashToken($token)]
        );
        $url = rtrim((string) Env::get('APP_URL', ''), '/') . '/reset?token=' . $token;
        $log = dirname(__DIR__, 2) . '/storage/logs/password_resets.log';
        @file_put_contents($log, date('c') . " user={$user['id']} url={$url}\n", FILE_APPEND);
        $this->audit->log((int) $user['id'], 'auth.reset_request', 'user', $user['id'], 'success', null, null, null, $ip);
    }

    public function resetPassword(string $token, string $password, string $confirm, string $ip): void
    {
        if (strlen($password) < 8 || $password !== $confirm) {
            throw HttpException::unprocessable('كلمة المرور غير صالحة أو غير متطابقة.', [
                'password' => 'أدخل كلمة مرور جديدة متطابقة لا تقل عن 8 أحرف.',
            ]);
        }
        $row = $this->db->queryOne(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [Security::hashToken($token)]
        );
        if (!$row) {
            throw HttpException::unprocessable('رابط الاستعادة غير صالح أو منتهٍ.');
        }
        $this->db->transaction(function () use ($row, $password) {
            $this->db->execute(
                'UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
                [Security::hashPassword($password), $row['user_id']]
            );
            $this->db->execute('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$row['id']]);
            $this->db->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [$row['user_id']]);
        });
        $this->audit->log((int) $row['user_id'], 'auth.reset', 'user', $row['user_id'], 'success', null, null, null, $ip);
    }

    public function changePassword(int $userId, string $current, string $next, string $confirm): void
    {
        $user = $this->db->queryOne('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$user || !Security::verifyPassword($current, $user['password_hash'])) {
            throw HttpException::unprocessable('كلمة المرور الحالية غير صحيحة.', ['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
        }
        if (strlen($next) < 8 || $next !== $confirm) {
            throw HttpException::unprocessable('كلمة المرور الجديدة غير صالحة.', ['password' => 'أدخل كلمة مرور جديدة متطابقة لا تقل عن 8 أحرف.']);
        }
        if (Security::verifyPassword($next, $user['password_hash'])) {
            throw HttpException::unprocessable('اختر كلمة مرور مختلفة عن الحالية.', ['password' => 'اختر كلمة مرور مختلفة عن الحالية.']);
        }
        $this->db->execute(
            'UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
            [Security::hashPassword($next), $userId]
        );
        $this->audit->log($userId, 'auth.change_password', 'user', $userId, 'success');
    }

    public function logout(string $sessionToken): void
    {
        $this->db->execute(
            'UPDATE user_sessions SET revoked_at = NOW() WHERE session_token_hash = ? AND revoked_at IS NULL',
            [Security::hashToken($sessionToken)]
        );
    }

    public function resolveSession(?string $token): ?array
    {
        if (!$token) {
            return null;
        }
        $row = $this->db->queryOne(
            'SELECT s.*, u.full_name, u.username, u.status, u.must_change_password, u.student_id, u.email, u.phone
             FROM user_sessions s JOIN users u ON u.id = s.user_id
             WHERE s.session_token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > NOW() LIMIT 1',
            [Security::hashToken($token)]
        );
        if (!$row || $row['status'] !== 'active') {
            return null;
        }
        $user = [
            'id' => (int) $row['user_id'],
            'full_name' => $row['full_name'],
            'username' => $row['username'],
            'status' => $row['status'],
            'must_change_password' => (bool) $row['must_change_password'],
            'student_id' => $row['student_id'] ? (int) $row['student_id'] : null,
            'email' => $row['email'],
            'phone' => $row['phone'],
        ];
        $user['roles'] = $this->rolesFor((int) $row['user_id']);
        $user['permissions'] = $this->permissionsFor((int) $row['user_id']);
        return $user;
    }

    public function rolesFor(int $userId): array
    {
        return array_column(
            $this->db->query(
                'SELECT r.code, r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
                [$userId]
            ),
            'name',
            'code'
        );
    }

    public function permissionsFor(int $userId): array
    {
        $fromRoles = $this->db->query(
            'SELECT p.code FROM user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?',
            [$userId]
        );
        $set = [];
        foreach ($fromRoles as $row) {
            $set[$row['code']] = true;
        }
        $overrides = $this->db->query(
            'SELECT p.code, up.effect FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ?',
            [$userId]
        );
        foreach ($overrides as $row) {
            if ($row['effect'] === 'deny') {
                unset($set[$row['code']]);
            } else {
                $set[$row['code']] = true;
            }
        }
        return array_keys($set);
    }

    public function revokeUserSessions(int $userId): void
    {
        $this->db->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [$userId]);
    }

    private function issueSession(array $user, string $ip, string $ua): array
    {
        $token = Security::randomToken(32);
        $lifetime = Env::int('SESSION_LIFETIME', 28800);
        $this->db->execute(
            'INSERT INTO user_sessions (user_id, session_token_hash, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))',
            [$user['id'], Security::hashToken($token), $ip, $ua, $lifetime]
        );
        $this->db->execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        $this->audit->log((int) $user['id'], 'auth.login', 'user', $user['id'], 'success', null, null, null, $ip);
        $roles = $this->rolesFor((int) $user['id']);
        $permissions = $this->permissionsFor((int) $user['id']);
        return [
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'full_name' => $user['full_name'],
                'username' => $user['username'],
                'must_change_password' => (bool) $user['must_change_password'],
                'student_id' => $user['student_id'] ? (int) $user['student_id'] : null,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        ];
    }

    private function findByIdentifier(string $identifier): ?array
    {
        $phone = Security::normalizePhone($identifier);
        return $this->db->queryOne(
            'SELECT * FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1',
            [$identifier, $identifier, $phone]
        );
    }

    private function assertNotLocked(string $identifier, string $ip): void
    {
        $max = Env::int('LOGIN_MAX_ATTEMPTS', 5);
        $window = Env::int('LOGIN_WINDOW_SECONDS', 900);
        $row = $this->db->queryOne(
            'SELECT COUNT(*) AS c FROM login_attempts
             WHERE success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
               AND (identifier = ? OR ip_address = ?)',
            [$window, $identifier, $ip]
        );
        if ((int) ($row['c'] ?? 0) >= $max) {
            throw HttpException::tooMany();
        }
    }
}
