<?php
declare(strict_types=1);

use App\Database;
use App\Env;
use App\Support\Money;
use App\Support\Security;

final class DemoSeeder
{
    public function __construct(private Database $db)
    {
    }

    public function run(): void
    {
        if ($this->db->queryOne('SELECT id FROM users LIMIT 1')) {
            return;
        }
        $this->permissions();
        $this->roles();
        $this->structure();
        $this->settings();
        $this->users();
        $this->academicContent();
        $this->financePolicies();
        $this->students();
        $this->teachers();
    }

    private function permissions(): void
    {
        $items = [
            ['admin.panel', 'دخول لوحة الإدارة', 'admin'],
            ['users.view', 'عرض المستخدمين', 'users'],
            ['users.manage', 'إدارة المستخدمين', 'users'],
            ['users.approve', 'اعتماد الحسابات', 'users'],
            ['roles.manage', 'إدارة الأدوار والصلاحيات', 'users'],
            ['settings.manage', 'الإعدادات العامة', 'settings'],
            ['identity.manage', 'الهوية الرسمية', 'settings'],
            ['academic.manage', 'الهيكل الأكاديمي', 'academic'],
            ['students.view', 'عرض الطلبة', 'students'],
            ['students.manage', 'إدارة ملفات الطلبة', 'students'],
            ['students.financial.view', 'عرض الذمة المالية للطالب', 'students'],
            ['tuition.manage', 'إدارة الأقساط', 'finance'],
            ['discounts.manage', 'إدارة الخصومات', 'finance'],
            ['payments.create', 'تسجيل الدفعات', 'finance'],
            ['payments.reverse', 'عكس أو تصحيح دفعة', 'finance'],
            ['receipts.view', 'عرض الوصولات', 'finance'],
            ['receipts.print', 'طباعة الوصولات', 'finance'],
            ['cashbox.view_balance', 'عرض رصيد الخزنة', 'finance'],
            ['cashbox.evening.view', 'خزنة المسائي', 'finance'],
            ['cashbox.parallel.view', 'خزنة الموازي', 'finance'],
            ['expenses.create', 'إنشاء مصروف', 'expenses'],
            ['expenses.view', 'عرض المصروفات', 'expenses'],
            ['expenses.approve', 'اعتماد المصروف', 'expenses'],
            ['expenses.disburse', 'تنفيذ الصرف', 'expenses'],
            ['reports.financial', 'التقارير المالية', 'reports'],
            ['reports.admin', 'التقارير الإدارية', 'reports'],
            ['audit.view', 'سجل التدقيق', 'audit'],
            ['qr.manage', 'إدارة رموز QR', 'security'],
            ['teachers.view', 'عرض أجور التدريسيين', 'teachers'],
            ['teachers.manage', 'إدارة أجور التدريسيين', 'teachers'],
            ['timetable.view', 'عرض الجدول', 'timetable'],
            ['timetable.manage', 'تعديل الجدول', 'timetable'],
            ['backup.manage', 'النسخ الاحتياطي', 'admin'],
        ];
        foreach ($items as [$code, $name, $module]) {
            $this->db->execute('INSERT INTO permissions (code, name, module) VALUES (?, ?, ?)', [$code, $name, $module]);
        }
    }

    private function roles(): void
    {
        $roles = [
            'system_admin' => ['مدير النظام', 'admin.panel users.view users.manage users.approve roles.manage settings.manage identity.manage academic.manage students.view qr.manage audit.view backup.manage timetable.view reports.admin'],
            'finance_manager' => ['مدير الحسابات', 'students.view students.financial.view tuition.manage discounts.manage receipts.view cashbox.view_balance cashbox.evening.view cashbox.parallel.view reports.financial expenses.view expenses.approve expenses.disburse payments.reverse audit.view teachers.view teachers.manage'],
            'cashier' => ['أمين الصندوق', 'payments.create receipts.view receipts.print students.view students.financial.view cashbox.view_balance cashbox.evening.view cashbox.parallel.view'],
            'expense_accountant' => ['محاسب المصروفات', 'expenses.create expenses.view'],
            'student_affairs' => ['شؤون الطلبة', 'students.view students.manage receipts.view timetable.view'],
            'student' => ['طالب', ''],
            'auditor' => ['مدقق', 'reports.financial cashbox.view_balance cashbox.evening.view cashbox.parallel.view audit.view receipts.view students.view students.financial.view expenses.view'],
        ];
        foreach ($roles as $code => [$name, $perms]) {
            $this->db->execute('INSERT INTO roles (code, name, is_system) VALUES (?, ?, 1)', [$code, $name]);
            $roleId = $this->db->lastId();
            foreach (preg_split('/\s+/', trim($perms)) as $perm) {
                if ($perm === '') {
                    continue;
                }
                $p = $this->db->queryOne('SELECT id FROM permissions WHERE code=?', [$perm]);
                if ($p) {
                    $this->db->execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $p['id']]);
                }
            }
        }
    }

    private function structure(): void
    {
        $this->db->execute('INSERT INTO universities (name, name_en, code) VALUES (?,?,?)', ['جامعة بغداد', 'University of Baghdad', 'UOB']);
        $uni = $this->db->lastId();
        $this->db->execute('INSERT INTO colleges (university_id, name, name_en, code, dean_name) VALUES (?,?,?,?,?)', [
            $uni, 'كلية العلوم والتكنولوجيا', 'College of Science and Technology', 'CST', 'د. حيدر الهاشمي',
        ]);
        $college = $this->db->lastId();
        foreach ([['علوم الحاسوب', 'CS'], ['الذكاء الاصطناعي', 'AI'], ['شبكات الحاسوب', 'NET']] as [$n, $c]) {
            $this->db->execute('INSERT INTO departments (college_id, name, code) VALUES (?,?,?)', [$college, $n, $c]);
        }
        $this->db->execute('INSERT INTO academic_years (name, starts_on, ends_on, is_current) VALUES (?,?,?,1)', ['2025-2026', '2025-09-01', '2026-06-30']);
        $this->db->execute('INSERT INTO study_types (code, name, cashbox_code) VALUES ("evening","الدراسة المسائية","evening"), ("parallel","الدراسة الموازية","parallel")');
        foreach ([['stage1', 'المرحلة الأولى', 1], ['stage2', 'المرحلة الثانية', 2], ['stage3', 'المرحلة الثالثة', 3], ['stage4', 'المرحلة الرابعة', 4]] as $s) {
            $this->db->execute('INSERT INTO study_stages (code, name, sort_order) VALUES (?,?,?)', $s);
        }
        $evening = $this->db->queryOne('SELECT id FROM study_types WHERE code="evening"');
        $parallel = $this->db->queryOne('SELECT id FROM study_types WHERE code="parallel"');
        $this->db->execute('INSERT INTO cashboxes (study_type_id, code, name) VALUES (?,?,?), (?,?,?)', [
            $evening['id'], 'evening', 'خزنة الدراسة المسائية',
            $parallel['id'], 'parallel', 'خزنة الدراسة الموازية',
        ]);
        foreach ([
            ['martyrs', 'خصم قناة الشهداء', null],
            ['college', 'خصم الكلية', null],
            ['top_students', 'خصم الأوائل', 'top'],
            ['social', 'خصم الرعاية الاجتماعية', null],
            ['president', 'خصم رئيس الجامعة', null],
            ['minister', 'خصم الوزير', null],
            ['disability', 'خصم المعوقين', 'disability'],
        ] as $d) {
            $this->db->execute('INSERT INTO discount_types (code, name, category) VALUES (?,?,?)', $d);
        }
        $this->db->execute('INSERT INTO rooms (code, name, capacity) VALUES ("H1","قاعة 1",60), ("LAB1","مختبر الشبكات",30)');
        $this->db->execute('INSERT INTO teacher_fee_types (code, name, formula_note) VALUES ("overload","محاضرات إضافية","تُدخل القيمة يدوياً وفق قرار العمادة"), ("exam","أجور لجان امتحانية","بدون معادلة مخفية في الشيفرة")');
    }

    private function settings(): void
    {
        $identity = [
            'university_name' => 'جامعة بغداد',
            'college_name' => 'كلية العلوم والتكنولوجيا',
            'department_name' => 'علوم الحاسوب',
            'dean_name' => 'د. حيدر الهاشمي',
            'deputy_name' => 'د. سناء عبد الأمير',
            'document_title' => 'وصل استلام أجور دراسية',
            'contact_phone' => '07900000000',
            'contact_email' => 'finance@example.edu',
            'contact_address' => 'بغداد',
            'currency_code' => 'IQD',
            'currency_label' => 'دينار عراقي',
            'header_text' => 'جمهورية العراق — وزارة التعليم العالي والبحث العلمي',
            'footer_text' => 'وثيقة مالية رسمية — لا يعتد بالتعديل اليدوي أو الكشط.',
            'print_orientation' => 'portrait',
        ];
        $this->db->execute('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?), (?, ?)', [
            'identity', json_encode($identity, JSON_UNESCAPED_UNICODE),
            'financial', json_encode(['allow_overpayment' => false, 'max_discount_rate' => 100, 'late_after_days' => 14], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function users(): void
    {
        $adminPass = Env::get('ADMIN_INITIAL_PASSWORD');
        if (!$adminPass) {
            throw new RuntimeException('ADMIN_INITIAL_PASSWORD مفقود.');
        }
        $demo = Env::get('DEMO_PASSWORD', 'College#2026Demo');
        $this->insertUser(Env::get('ADMIN_FULL_NAME', 'مدير النظام'), Env::get('ADMIN_USERNAME', 'admin'), '07000000001', 'admin@college.local', $adminPass, 'active', 1, ['system_admin']);
        $this->insertUser('د. حيدر الهاشمي', 'finance', '07000000002', 'finance@college.local', $demo, 'active', 0, ['finance_manager']);
        $this->insertUser('عمار قاسم جاسم', 'cashier', '07000000003', 'cashier@college.local', $demo, 'active', 0, ['cashier']);
        $this->insertUser('زياد طارق', 'expense', '07000000004', 'expense@college.local', $demo, 'active', 0, ['expense_accountant']);
        $this->insertUser('حيدر السلامي', 'affairs', '07000000005', 'affairs@college.local', $demo, 'active', 0, ['student_affairs']);
        $this->insertUser('المدقق الداخلي', 'auditor', '07000000006', 'auditor@college.local', $demo, 'active', 0, ['auditor']);
        $admin = $this->db->queryOne('SELECT id FROM cashboxes WHERE code="evening"');
        $this->db->execute(
            'INSERT INTO cash_transactions (cashbox_id, direction, amount, kind, status, notes, created_by) VALUES (?, "credit", ?, "opening", "posted", "رصيد افتتاحي تجريبي", 1)',
            [$admin['id'], '50000000']
        );
        $par = $this->db->queryOne('SELECT id FROM cashboxes WHERE code="parallel"');
        $this->db->execute(
            'INSERT INTO cash_transactions (cashbox_id, direction, amount, kind, status, notes, created_by) VALUES (?, "credit", ?, "opening", "posted", "رصيد افتتاحي تجريبي", 1)',
            [$par['id'], '80000000']
        );
    }

    private function insertUser(string $name, string $username, string $phone, string $email, string $password, string $status, int $mustChange, array $roles): int
    {
        $this->db->execute(
            'INSERT INTO users (full_name, username, phone, email, password_hash, status, must_change_password) VALUES (?,?,?,?,?,?,?)',
            [$name, $username, $phone, $email, Security::hashPassword($password), $status, $mustChange]
        );
        $id = (int) $this->db->lastId();
        foreach ($roles as $code) {
            $role = $this->db->queryOne('SELECT id FROM roles WHERE code=?', [$code]);
            $this->db->execute('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$id, $role['id']]);
        }
        return $id;
    }

    private function academicContent(): void
    {
        $cs = $this->db->queryOne('SELECT id FROM departments WHERE code="CS"');
        $st1 = $this->db->queryOne('SELECT id FROM study_stages WHERE code="stage1"');
        $this->db->execute('INSERT INTO subjects (department_id, study_stage_id, code, name) VALUES (?,?,?,?), (?,?,?,?)', [
            $cs['id'], $st1['id'], 'CS101', 'مقدمة في البرمجة',
            $cs['id'], $st1['id'], 'CS102', 'هياكل بيانات',
        ]);
    }

    private function financePolicies(): void
    {
        $year = $this->db->queryOne('SELECT id FROM academic_years WHERE is_current=1');
        $evening = $this->db->queryOne('SELECT id FROM study_types WHERE code="evening"');
        $parallel = $this->db->queryOne('SELECT id FROM study_types WHERE code="parallel"');
        $this->policy((int) $year['id'], (int) $evening['id'], 'قسط المسائي 2025-2026', '2000000', 2);
        $this->policy((int) $year['id'], (int) $parallel['id'], 'قسط الموازي 2025-2026', '2500000', 4);
        $top = $this->db->queryOne('SELECT id FROM discount_types WHERE code="top_students"');
        $martyrs = $this->db->queryOne('SELECT id FROM discount_types WHERE code="martyrs"');
        $disability = $this->db->queryOne('SELECT id FROM discount_types WHERE code="disability"');
        $college = $this->db->queryOne('SELECT id FROM discount_types WHERE code="college"');
        $this->rule((int) $top['id'], 'أوائل — الفئة الأولى', 'percent', '50', 'top1', 1);
        $this->rule((int) $top['id'], 'أوائل — الفئة الثانية', 'percent', '30', 'top2', 1);
        $this->rule((int) $top['id'], 'أوائل — الفئة الثالثة', 'percent', '15', 'top3', 1);
        $this->rule((int) $martyrs['id'], 'قناة الشهداء', 'percent', '100', null, 0);
        $this->rule((int) $disability['id'], 'معوقون — درجة أولى', 'percent', '50', 'first_degree', 1);
        $this->rule((int) $disability['id'], 'معوقون — الشخص نفسه', 'percent', '30', 'self', 1);
        $this->rule((int) $college['id'], 'خصم الكلية — مبلغ تجريبي', 'fixed', '100000', null, 1);
        foreach (['social' => 'الرعاية الاجتماعية', 'president' => 'رئيس الجامعة', 'minister' => 'الوزير'] as $code => $label) {
            $t = $this->db->queryOne('SELECT id FROM discount_types WHERE code=?', [$code]);
            $this->rule((int) $t['id'], $label . ' (قيمة تُضبط من الإدارة)', 'percent', '0', null, 1, 0);
        }
    }

    private function policy(int $year, int $type, string $name, string $base, int $count): void
    {
        $this->db->execute(
            'INSERT INTO tuition_policies (academic_year_id, study_type_id, name, base_amount, installment_count, applies_to, created_by) VALUES (?,?,?,?,?,"new_only",1)',
            [$year, $type, $name, $base, $count]
        );
        $id = $this->db->lastId();
        $part = Money::norm(bcdiv($base, (string) $count, 2));
        $alloc = '0.00';
        for ($i = 1; $i <= $count; $i++) {
            $amt = $i === $count ? Money::sub($base, $alloc) : $part;
            $alloc = Money::add($alloc, $amt);
            $months = [10, 12, 2, 4];
            $month = $months[$i - 1] ?? 10;
            $yearNum = $month >= 10 ? 2025 : 2026;
            $this->db->execute('INSERT INTO installment_schedules (tuition_policy_id, sequence, amount, due_date) VALUES (?,?,?,?)', [
                $id, $i, $amt, sprintf('%d-%02d-01', $yearNum, $month),
            ]);
        }
    }

    private function rule(int $typeId, string $name, string $calc, string $value, ?string $cat, int $stack, int $active = 1): void
    {
        $this->db->execute(
            'INSERT INTO discount_rules (discount_type_id, name, calculation, value, category_code, stackable, is_active, created_by) VALUES (?,?,?,?,?,?,?,1)',
            [$typeId, $name, $calc, $value, $cat, $stack, $active]
        );
    }

    private function students(): void
    {
        $year = $this->db->queryOne('SELECT id FROM academic_years WHERE is_current=1');
        $college = $this->db->queryOne('SELECT id FROM colleges LIMIT 1');
        $cs = $this->db->queryOne('SELECT id FROM departments WHERE code="CS"');
        $ai = $this->db->queryOne('SELECT id FROM departments WHERE code="AI"');
        $evening = $this->db->queryOne('SELECT id FROM study_types WHERE code="evening"');
        $parallel = $this->db->queryOne('SELECT id FROM study_types WHERE code="parallel"');
        $s1 = $this->db->queryOne('SELECT id FROM study_stages WHERE code="stage1"');
        $s2 = $this->db->queryOne('SELECT id FROM study_stages WHERE code="stage2"');
        $s3 = $this->db->queryOne('SELECT id FROM study_stages WHERE code="stage3"');
        $list = [
            ['202510001', 'زيد إبراهيم خليل البياتي', $cs['id'], $evening['id'], $s3['id']],
            ['202510002', 'فاطمة محمد حامد الناصري', $ai['id'], $parallel['id'], $s1['id']],
            ['202510003', 'كرار حيدر علي الشمري', $cs['id'], $evening['id'], $s1['id']],
            ['202510004', 'نور سعدي عبد الرحمن', $cs['id'], $parallel['id'], $s2['id']],
            ['202510005', 'أحمد جاسم كريم', $ai['id'], $parallel['id'], $s1['id']],
            ['202510006', 'سارة علي حسين', $cs['id'], $evening['id'], $s2['id']],
        ];
        $audit = new App\Support\Audit($this->db);
        $settings = new App\Services\SettingsService($this->db, $audit);
        $invoices = new App\Services\InvoiceService($this->db, $audit, $settings);
        $students = new App\Services\StudentService($this->db, $audit, $invoices);
        $discounts = new App\Services\DiscountService($this->db, $audit, $invoices, $settings);
        foreach ($list as $row) {
            $students->create([
                'university_number' => $row[0],
                'full_name' => $row[1],
                'academic_year_id' => $year['id'],
                'college_id' => $college['id'],
                'department_id' => $row[2],
                'study_type_id' => $row[3],
                'study_stage_id' => $row[4],
            ], 5);
        }
        $inv = $this->db->queryOne('SELECT i.id FROM student_invoices i JOIN student_enrollments e ON e.id=i.enrollment_id JOIN students s ON s.id=e.student_id WHERE s.university_number="202510003"');
        $collegeRule = $this->db->queryOne('SELECT id FROM discount_rules WHERE name LIKE "خصم الكلية%"');
        if ($inv && $collegeRule) {
            $discounts->apply((int) $inv['id'], (int) $collegeRule['id'], 2, 'اعتماد تجريبي لخصم الكلية', true);
        }
        $st = $this->db->queryOne('SELECT id FROM students WHERE university_number="202510001"');
        $demo = Env::get('DEMO_PASSWORD', 'College#2026Demo');
        $sid = $this->insertUser('زيد إبراهيم خليل البياتي', 'student1', '07000000007', 'student1@college.local', $demo, 'active', 0, ['student']);
        $this->db->execute('UPDATE users SET student_id=? WHERE id=?', [$st['id'], $sid]);
    }

    private function teachers(): void
    {
        $cs = $this->db->queryOne('SELECT id FROM departments WHERE code="CS"');
        $this->db->execute('INSERT INTO teaching_staff (full_name, department_id, title, staff_number) VALUES (?,?,?,?)', [
            'د. أحمد كاظم', $cs['id'], 'أستاذ', 'T-1001',
        ]);
        $year = $this->db->queryOne('SELECT id FROM academic_years WHERE is_current=1');
        $type = $this->db->queryOne('SELECT id FROM teacher_fee_types WHERE code="overload"');
        $staff = $this->db->queryOne('SELECT id FROM teaching_staff LIMIT 1');
        $this->db->execute(
            'INSERT INTO teacher_fees (staff_id, academic_year_id, fee_type_id, due_amount, paid_amount, remaining, status, created_by) VALUES (?,?,?,?,0,?, "pending", 2)',
            [$staff['id'], $year['id'], $type['id'], '1500000', '1500000']
        );
        $sub = $this->db->queryOne('SELECT id FROM subjects LIMIT 1');
        $room = $this->db->queryOne('SELECT id FROM rooms LIMIT 1');
        $st1 = $this->db->queryOne('SELECT id FROM study_stages WHERE code="stage1"');
        $this->db->execute(
            'INSERT INTO timetables (academic_year_id, department_id, study_stage_id, subject_id, staff_id, room_id, day_of_week, starts_at, ends_at, lecture_type, created_by)
             VALUES (?,?,?,?,?,?,1,"08:30:00","10:30:00","theory",5)',
            [$year['id'], $cs['id'], $st1['id'], $sub['id'], $staff['id'], $room['id']]
        );
    }
}
