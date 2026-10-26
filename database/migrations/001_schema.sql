SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS universities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  name_en VARCHAR(191) NULL,
  code VARCHAR(64) NULL,
  logo_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS colleges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  university_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(191) NOT NULL,
  name_en VARCHAR(191) NULL,
  code VARCHAR(64) NULL,
  logo_path VARCHAR(255) NULL,
  dean_name VARCHAR(191) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_colleges_university FOREIGN KEY (university_id) REFERENCES universities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  college_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(191) NOT NULL,
  code VARCHAR(64) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_departments_college_code (college_id, code),
  CONSTRAINT fk_departments_college FOREIGN KEY (college_id) REFERENCES colleges(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_years (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_academic_years_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(64) NOT NULL,
  cashbox_code VARCHAR(32) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_study_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_stages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(64) NOT NULL,
  sort_order INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_study_stages_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(191) NOT NULL,
  username VARCHAR(64) NOT NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(191) NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  student_id BIGINT UNSIGNED NULL,
  last_login_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_phone (phone),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(128) NOT NULL,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(191) NOT NULL,
  module VARCHAR(64) NOT NULL,
  UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_role_permissions_perm FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
  user_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  effect ENUM('allow','deny') NOT NULL DEFAULT 'allow',
  granted_by BIGINT UNSIGNED NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, permission_id),
  CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_user_permissions_perm FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  session_token_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_sessions_hash (session_token_hash),
  KEY idx_user_sessions_user (user_id),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qr_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  purpose ENUM('login','activation','recovery') NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  single_use TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  last_used_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_qr_tokens_hash (token_hash),
  KEY idx_qr_tokens_user (user_id),
  CONSTRAINT fk_qr_tokens_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(191) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_attempts_lookup (identifier, ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_resets_hash (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  university_number VARCHAR(32) NOT NULL,
  full_name VARCHAR(191) NOT NULL,
  national_id VARCHAR(32) NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(191) NULL,
  gender ENUM('male','female','unspecified') NOT NULL DEFAULT 'unspecified',
  birth_date DATE NULL,
  address VARCHAR(255) NULL,
  status ENUM('active','graduated','suspended','withdrawn') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_students_university_number (university_number),
  KEY idx_students_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
  ADD CONSTRAINT fk_users_student FOREIGN KEY (student_id) REFERENCES students(id);

CREATE TABLE IF NOT EXISTS student_enrollments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  college_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NOT NULL,
  study_type_id BIGINT UNSIGNED NOT NULL,
  study_stage_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','completed','transferred') NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enrollment_student_year (student_id, academic_year_id),
  KEY idx_enrollments_filters (academic_year_id, study_type_id, study_stage_id, department_id),
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_enroll_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_enroll_college FOREIGN KEY (college_id) REFERENCES colleges(id),
  CONSTRAINT fk_enroll_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_enroll_type FOREIGN KEY (study_type_id) REFERENCES study_types(id),
  CONSTRAINT fk_enroll_stage FOREIGN KEY (study_stage_id) REFERENCES study_stages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tuition_policies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  study_type_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  study_stage_id BIGINT UNSIGNED NULL,
  name VARCHAR(191) NOT NULL,
  base_amount DECIMAL(18,2) NOT NULL,
  installment_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
  applies_to ENUM('new_only','current_without_receipts') NOT NULL DEFAULT 'new_only',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tuition_lookup (academic_year_id, study_type_id, department_id, study_stage_id),
  CONSTRAINT fk_tuition_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_tuition_type FOREIGN KEY (study_type_id) REFERENCES study_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installment_schedules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tuition_policy_id BIGINT UNSIGNED NOT NULL,
  sequence TINYINT UNSIGNED NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  due_date DATE NULL,
  UNIQUE KEY uq_installment_seq (tuition_policy_id, sequence),
  CONSTRAINT fk_installment_policy FOREIGN KEY (tuition_policy_id) REFERENCES tuition_policies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enrollment_id BIGINT UNSIGNED NOT NULL,
  tuition_policy_id BIGINT UNSIGNED NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  base_amount DECIMAL(18,2) NOT NULL,
  discount_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  net_due DECIMAL(18,2) NOT NULL,
  paid_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  remaining DECIMAL(18,2) NOT NULL,
  status ENUM('unpaid','partial','paid','overdue') NOT NULL DEFAULT 'unpaid',
  last_payment_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_invoice_enrollment (enrollment_id),
  KEY idx_invoices_status (status, academic_year_id),
  CONSTRAINT fk_invoice_enroll FOREIGN KEY (enrollment_id) REFERENCES student_enrollments(id),
  CONSTRAINT fk_invoice_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_installments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  sequence TINYINT UNSIGNED NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  due_date DATE NULL,
  status ENUM('pending','partial','paid','overdue') NOT NULL DEFAULT 'pending',
  UNIQUE KEY uq_invoice_installment_seq (invoice_id, sequence),
  CONSTRAINT fk_inv_inst_invoice FOREIGN KEY (invoice_id) REFERENCES student_invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discount_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(191) NOT NULL,
  category VARCHAR(64) NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_discount_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discount_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  discount_type_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(191) NOT NULL,
  calculation ENUM('percent','fixed') NOT NULL,
  value DECIMAL(18,2) NOT NULL,
  category_code VARCHAR(64) NULL,
  study_type_id BIGINT UNSIGNED NULL,
  department_id BIGINT UNSIGNED NULL,
  study_stage_id BIGINT UNSIGNED NULL,
  stackable TINYINT(1) NOT NULL DEFAULT 0,
  max_amount DECIMAL(18,2) NULL,
  required_documents VARCHAR(255) NULL,
  valid_from DATE NULL,
  valid_to DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_discount_rules_type FOREIGN KEY (discount_type_id) REFERENCES discount_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_discounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  discount_rule_id BIGINT UNSIGNED NULL,
  discount_type_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(191) NOT NULL,
  calculation ENUM('percent','fixed') NOT NULL,
  rule_value DECIMAL(18,2) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  category_code VARCHAR(64) NULL,
  reason VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_student_discounts_invoice FOREIGN KEY (invoice_id) REFERENCES student_invoices(id),
  CONSTRAINT fk_student_discounts_type FOREIGN KEY (discount_type_id) REFERENCES discount_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  enrollment_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  method ENUM('cash','transfer','other') NOT NULL DEFAULT 'cash',
  status ENUM('posted','reversed') NOT NULL DEFAULT 'posted',
  idempotency_key VARCHAR(64) NOT NULL,
  notes VARCHAR(255) NULL,
  reversal_of BIGINT UNSIGNED NULL,
  reversed_reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payments_idempotency (idempotency_key),
  KEY idx_payments_invoice (invoice_id, status),
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES student_invoices(id),
  CONSTRAINT fk_payments_enroll FOREIGN KEY (enrollment_id) REFERENCES student_enrollments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_receipts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  receipt_number VARCHAR(32) NOT NULL,
  verify_token_hash CHAR(64) NOT NULL,
  issued_at DATETIME NOT NULL,
  base_amount DECIMAL(18,2) NOT NULL,
  discount_total DECIMAL(18,2) NOT NULL,
  net_due DECIMAL(18,2) NOT NULL,
  paid_now DECIMAL(18,2) NOT NULL,
  paid_before DECIMAL(18,2) NOT NULL,
  remaining_after DECIMAL(18,2) NOT NULL,
  cashier_name VARCHAR(191) NOT NULL,
  identity_snapshot JSON NOT NULL,
  signature_path VARCHAR(255) NULL,
  seal_path VARCHAR(255) NULL,
  status ENUM('issued','void') NOT NULL DEFAULT 'issued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_receipts_number (receipt_number),
  UNIQUE KEY uq_receipts_payment (payment_id),
  UNIQUE KEY uq_receipts_verify (verify_token_hash),
  CONSTRAINT fk_receipts_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
  CONSTRAINT fk_receipts_invoice FOREIGN KEY (invoice_id) REFERENCES student_invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipt_prints (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_id BIGINT UNSIGNED NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  confirmed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_prints_receipt FOREIGN KEY (receipt_id) REFERENCES payment_receipts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipt_signatures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_id BIGINT UNSIGNED NOT NULL,
  signer_user_id BIGINT UNSIGNED NOT NULL,
  kind ENUM('cashier','seal','authorized') NOT NULL,
  image_path VARCHAR(255) NULL,
  signed_at DATETIME NOT NULL,
  CONSTRAINT fk_receipt_sig_receipt FOREIGN KEY (receipt_id) REFERENCES payment_receipts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cashboxes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  study_type_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(191) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cashboxes_code (code),
  UNIQUE KEY uq_cashboxes_type (study_type_id),
  CONSTRAINT fk_cashboxes_type FOREIGN KEY (study_type_id) REFERENCES study_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cash_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cashbox_id BIGINT UNSIGNED NOT NULL,
  direction ENUM('credit','debit') NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  kind ENUM('opening','tuition_payment','expense','reversal','adjustment') NOT NULL,
  status ENUM('posted','void') NOT NULL DEFAULT 'posted',
  payment_id BIGINT UNSIGNED NULL,
  expense_book_id BIGINT UNSIGNED NULL,
  reference VARCHAR(64) NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cash_tx_box (cashbox_id, status, created_at),
  CONSTRAINT fk_cash_tx_box FOREIGN KEY (cashbox_id) REFERENCES cashboxes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_books (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_number VARCHAR(32) NOT NULL,
  title VARCHAR(191) NOT NULL,
  beneficiary VARCHAR(191) NOT NULL,
  cashbox_id BIGINT UNSIGNED NOT NULL,
  total_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  justification TEXT NULL,
  status ENUM('draft','submitted','pending_approval','approved','disbursed','rejected','cancelled') NOT NULL DEFAULT 'draft',
  rejection_reason VARCHAR(255) NULL,
  identity_snapshot JSON NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  submitted_at DATETIME NULL,
  disbursed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_expense_books_number (book_number),
  CONSTRAINT fk_expense_books_cashbox FOREIGN KEY (cashbox_id) REFERENCES cashboxes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_book_id BIGINT UNSIGNED NOT NULL,
  sequence INT NOT NULL,
  name VARCHAR(191) NOT NULL,
  description VARCHAR(255) NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(18,2) NOT NULL,
  line_total DECIMAL(18,2) NOT NULL,
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_expense_items_book FOREIGN KEY (expense_book_id) REFERENCES expense_books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_book_id BIGINT UNSIGNED NOT NULL,
  step ENUM('review','dean','deputy') NOT NULL,
  decision ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  signer_user_id BIGINT UNSIGNED NULL,
  signer_name VARCHAR(191) NULL,
  signed_at DATETIME NULL,
  comment VARCHAR(255) NULL,
  CONSTRAINT fk_expense_approvals_book FOREIGN KEY (expense_book_id) REFERENCES expense_books(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teaching_staff (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(191) NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  title VARCHAR(64) NULL,
  staff_number VARCHAR(32) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_teaching_staff_number (staff_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_fee_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(191) NOT NULL,
  formula_note VARCHAR(255) NULL,
  UNIQUE KEY uq_teacher_fee_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_fees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  fee_type_id BIGINT UNSIGNED NOT NULL,
  due_amount DECIMAL(18,2) NOT NULL,
  paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  remaining DECIMAL(18,2) NOT NULL,
  due_date DATE NULL,
  status ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_teacher_fees_staff FOREIGN KEY (staff_id) REFERENCES teaching_staff(id),
  CONSTRAINT fk_teacher_fees_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_teacher_fees_type FOREIGN KEY (fee_type_id) REFERENCES teacher_fee_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_fee_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_fee_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tfp_fee FOREIGN KEY (teacher_fee_id) REFERENCES teacher_fees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subjects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id BIGINT UNSIGNED NOT NULL,
  study_stage_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(191) NOT NULL,
  UNIQUE KEY uq_subjects_dept_code (department_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(191) NOT NULL,
  capacity INT NULL,
  UNIQUE KEY uq_rooms_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS timetables (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NOT NULL,
  study_stage_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  staff_id BIGINT UNSIGNED NULL,
  room_id BIGINT UNSIGNED NULL,
  day_of_week TINYINT NOT NULL,
  starts_at TIME NOT NULL,
  ends_at TIME NOT NULL,
  lecture_type ENUM('theory','lab','tutorial') NOT NULL DEFAULT 'theory',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_timetable_lookup (academic_year_id, department_id, study_stage_id),
  CONSTRAINT fk_tt_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_tt_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_tt_subject FOREIGN KEY (subject_id) REFERENCES subjects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(191) NOT NULL,
  doc_type VARCHAR(64) NOT NULL,
  file_attachment_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_student_docs_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS file_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type VARCHAR(64) NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(191) NOT NULL,
  stored_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(128) NOT NULL,
  size_bytes INT NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_files_owner (owner_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(191) NOT NULL,
  body VARCHAR(500) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  ref_type VARCHAR(64) NULL,
  ref_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user (user_id, is_read),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id VARCHAR(64) NULL,
  result ENUM('success','denied','failed') NOT NULL DEFAULT 'success',
  reason VARCHAR(255) NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_action (action, created_at),
  KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
  setting_value JSON NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS setting_revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(64) NOT NULL,
  old_value JSON NULL,
  new_value JSON NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_setting_revisions_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backup_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(191) NOT NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(191) NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_schema_migrations_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
