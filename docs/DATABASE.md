# وصف قاعدة البيانات

المحرك InnoDB، الترميز utf8mb4، المبالغ `DECIMAL(18,2)`.

الجداول الأساسية:

- الوصول: `users`, `roles`, `permissions`, `user_roles`, `role_permissions`, `user_permissions`, `user_sessions`, `qr_tokens`, `login_attempts`, `password_resets`
- الهيكل: `universities`, `colleges`, `departments`, `academic_years`, `study_types`, `study_stages`
- الطلبة: `students`, `student_enrollments`, `student_documents`
- المالي: `tuition_policies`, `installment_schedules`, `student_invoices`, `invoice_installments`, `discount_types`, `discount_rules`, `student_discounts`, `payments`, `payment_receipts`, `receipt_prints`, `receipt_signatures`
- الخزائن: `cashboxes`, `cash_transactions`
- المصروفات: `expense_books`, `expense_items`, `expense_approvals`
- التدريس: `teaching_staff`, `teacher_fee_types`, `teacher_fees`, `teacher_fee_payments`, `subjects`, `rooms`, `timetables`
- الرقابة: `audit_logs`, `system_settings`, `setting_revisions`, `file_attachments`, `notifications`, `backup_jobs`

الرصيد = مجموع الحركات `credit` المرحلة ناقص `debit`. لا يوجد حقل رصيد يُعدَّل يدوياً.

الهجرة: `database/migrations/001_schema.sql` عبر `php bin/install.php`.
