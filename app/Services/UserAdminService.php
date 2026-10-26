<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;
use App\Support\Security;

final class UserAdminService
{
    public function __construct(
        private Database $db,
        private Audit $audit,
        private AuthService $auth,
    ) {
    }

    public function list(?string $status = null, ?string $q = null): array
    {
        $where = ['1=1'];
        $params = [];
        if ($status) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($q) {
            $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $users = $this->db->query(
            'SELECT u.id, u.full_name, u.username, u.phone, u.email, u.status, u.must_change_password, u.last_login_at, u.created_at, u.student_id
             FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id DESC',
            $params
        );
        foreach ($users as &$user) {
            $user['roles'] = $this->auth->rolesFor((int) $user['id']);
            $user['permissions'] = $this->auth->permissionsFor((int) $user['id']);
            $qr = $this->db->queryOne(
                'SELECT id, purpose, expires_at, used_at, revoked_at FROM qr_tokens WHERE user_id=? ORDER BY id DESC LIMIT 1',
                [$user['id']]
            );
            $user['qr'] = $qr;
        }
        return $users;
    }

    public function create(array $data, int $actorId): int
    {
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($username === '' || strlen($password) < 8) {
            throw HttpException::unprocessable('اسم المستخدم وكلمة مرور لا تقل عن 8 أحرف مطلوبان.');
        }
        $this->db->execute(
            'INSERT INTO users (full_name, username, phone, email, password_hash, status, must_change_password, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
            [
                trim((string) ($data['full_name'] ?? '')),
                $username,
                $data['phone'] ? Security::normalizePhone((string) $data['phone']) : null,
                $data['email'] ?? null,
                Security::hashPassword($password),
                $data['status'] ?? 'active',
                $actorId,
            ]
        );
        $id = (int) $this->db->lastId();
        if (!empty($data['roles']) && is_array($data['roles'])) {
            $this->setRoles($id, $data['roles'], $actorId);
        }
        $this->audit->log($actorId, 'user.create', 'user', $id, 'success');
        return $id;
    }

    public function setStatus(int $id, string $status, int $actorId): void
    {
        if (!in_array($status, ['pending', 'active', 'disabled'], true)) {
            throw HttpException::unprocessable('حالة غير صالحة.');
        }
        $this->db->execute('UPDATE users SET status=?, updated_by=? WHERE id=?', [$status, $actorId, $id]);
        if ($status === 'disabled') {
            $this->auth->revokeUserSessions($id);
        }
        $this->audit->log($actorId, 'user.status', 'user', $id, 'success', $status);
    }

    public function setRoles(int $userId, array $roleCodes, int $actorId): void
    {
        $before = $this->auth->rolesFor($userId);
        $this->db->execute('DELETE FROM user_roles WHERE user_id=?', [$userId]);
        foreach ($roleCodes as $code) {
            $role = $this->db->queryOne('SELECT id FROM roles WHERE code=?', [$code]);
            if ($role) {
                $this->db->execute(
                    'INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)',
                    [$userId, $role['id'], $actorId]
                );
            }
        }
        $this->audit->log($actorId, 'user.roles', 'user', $userId, 'success', null, $before, $roleCodes);
    }

    public function setPermission(int $userId, string $permCode, string $effect, int $actorId): void
    {
        $perm = $this->db->queryOne('SELECT id FROM permissions WHERE code=?', [$permCode]);
        if (!$perm) {
            throw HttpException::notFound('الصلاحية غير موجودة.');
        }
        $this->db->execute(
            'INSERT INTO user_permissions (user_id, permission_id, effect, granted_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE effect=VALUES(effect), granted_by=VALUES(granted_by)',
            [$userId, $perm['id'], $effect, $actorId]
        );
        $this->audit->log($actorId, 'user.permission', 'user', $userId, 'success', $permCode . ':' . $effect);
    }

    public function resetPassword(int $userId, string $password, int $actorId): void
    {
        if (strlen($password) < 8) {
            throw HttpException::unprocessable('كلمة المرور لا تقل عن 8 أحرف.');
        }
        $this->db->execute(
            'UPDATE users SET password_hash=?, must_change_password=1 WHERE id=?',
            [Security::hashPassword($password), $userId]
        );
        $this->auth->revokeUserSessions($userId);
        $this->audit->log($actorId, 'user.reset_password', 'user', $userId, 'success');
    }

    public function issueQr(int $userId, string $purpose, int $ttlSeconds, bool $singleUse, int $actorId): array
    {
        if (!in_array($purpose, ['login', 'activation', 'recovery'], true)) {
            throw HttpException::unprocessable('غرض الرمز غير صالح.');
        }
        $token = Security::randomToken(24);
        $this->db->execute(
            'INSERT INTO qr_tokens (user_id, token_hash, purpose, expires_at, single_use, created_by)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?)',
            [$userId, Security::hashToken($token), $purpose, $ttlSeconds, $singleUse ? 1 : 0, $actorId]
        );
        $id = (int) $this->db->lastId();
        $this->audit->log($actorId, 'qr.issue', 'qr_token', $id, 'success', $purpose);
        return ['id' => $id, 'token' => $token, 'purpose' => $purpose, 'ttl' => $ttlSeconds];
    }

    public function revokeQr(int $id, int $actorId): void
    {
        $this->db->execute('UPDATE qr_tokens SET revoked_at=NOW() WHERE id=?', [$id]);
        $this->audit->log($actorId, 'qr.revoke', 'qr_token', $id, 'success');
    }
}
