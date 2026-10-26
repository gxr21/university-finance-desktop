<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Support\Audit;

final class TimetableService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    public function list(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        foreach (['academic_year_id', 'department_id', 'study_stage_id', 'staff_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "t.$key = ?";
                $params[] = $filters[$key];
            }
        }
        return $this->db->query(
            'SELECT t.*, sub.name AS subject_name, s.full_name AS staff_name, r.name AS room_name, d.name AS department_name, st.name AS stage_name
             FROM timetables t
             JOIN subjects sub ON sub.id=t.subject_id
             LEFT JOIN teaching_staff s ON s.id=t.staff_id
             LEFT JOIN rooms r ON r.id=t.room_id
             JOIN departments d ON d.id=t.department_id
             JOIN study_stages st ON st.id=t.study_stage_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY t.day_of_week, t.starts_at',
            $params
        );
    }

    public function save(array $data, int $userId): int
    {
        $this->assertNoClash($data);
        $this->db->execute(
            'INSERT INTO timetables (academic_year_id, department_id, study_stage_id, subject_id, staff_id, room_id, day_of_week, starts_at, ends_at, lecture_type, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['academic_year_id'], $data['department_id'], $data['study_stage_id'], $data['subject_id'],
                $data['staff_id'] ?: null, $data['room_id'] ?: null,
                $data['day_of_week'], $data['starts_at'], $data['ends_at'],
                $data['lecture_type'] ?? 'theory', $data['notes'] ?? null, $userId,
            ]
        );
        $id = (int) $this->db->lastId();
        $this->audit->log($userId, 'timetable.create', 'timetable', $id, 'success');
        return $id;
    }

    public function delete(int $id, int $userId): void
    {
        $this->db->execute('DELETE FROM timetables WHERE id=?', [$id]);
        $this->audit->log($userId, 'timetable.delete', 'timetable', $id, 'success');
    }

    public function subjects(): array
    {
        return $this->db->query(
            'SELECT s.*, d.name AS department_name, st.name AS stage_name
             FROM subjects s JOIN departments d ON d.id=s.department_id JOIN study_stages st ON st.id=s.study_stage_id ORDER BY s.name'
        );
    }

    public function saveSubject(array $data): int
    {
        $this->db->execute(
            'INSERT INTO subjects (department_id, study_stage_id, code, name) VALUES (?, ?, ?, ?)',
            [$data['department_id'], $data['study_stage_id'], $data['code'], $data['name']]
        );
        return (int) $this->db->lastId();
    }

    private function assertNoClash(array $data): void
    {
        if (!empty($data['room_id'])) {
            $room = $this->db->queryOne(
                'SELECT id FROM timetables WHERE room_id=? AND day_of_week=? AND starts_at < ? AND ends_at > ? LIMIT 1',
                [$data['room_id'], $data['day_of_week'], $data['ends_at'], $data['starts_at']]
            );
            if ($room) {
                throw HttpException::conflict('القاعة مشغولة في هذا الوقت.');
            }
        }
        if (!empty($data['staff_id'])) {
            $staff = $this->db->queryOne(
                'SELECT id FROM timetables WHERE staff_id=? AND day_of_week=? AND starts_at < ? AND ends_at > ? LIMIT 1',
                [$data['staff_id'], $data['day_of_week'], $data['ends_at'], $data['starts_at']]
            );
            if ($staff) {
                throw HttpException::conflict('التدريسي لديه محاضرة متعارضة.');
            }
        }
        $group = $this->db->queryOne(
            'SELECT id FROM timetables WHERE academic_year_id=? AND department_id=? AND study_stage_id=? AND day_of_week=? AND starts_at < ? AND ends_at > ? LIMIT 1',
            [$data['academic_year_id'], $data['department_id'], $data['study_stage_id'], $data['day_of_week'], $data['ends_at'], $data['starts_at']]
        );
        if ($group) {
            throw HttpException::conflict('المرحلة لديها محاضرة في الوقت نفسه.');
        }
    }
}
