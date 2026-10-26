<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class UserRepository
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->queryOne('SELECT id, full_name, username, phone, email, status, student_id FROM users WHERE id=?', [$id]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->queryOne('SELECT * FROM users WHERE username=?', [$username]);
    }
}
