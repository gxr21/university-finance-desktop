# مخطط العلاقات (ERD)

العلاقات المالية لا تُحذف عند تعطيل المستخدم. الفاتورة لقطة مستقلة عن سياسة القسط الحالية.

```mermaid
erDiagram
  universities ||--o{ colleges : تحتوي
  colleges ||--o{ departments : تحتوي
  academic_years ||--o{ student_enrollments : سنة
  study_types ||--o{ student_enrollments : نوع
  study_stages ||--o{ student_enrollments : مرحلة
  departments ||--o{ student_enrollments : قسم
  students ||--o{ student_enrollments : قيد
  students ||--o| users : "حساب اختياري"

  users ||--o{ user_roles : إسناد
  roles ||--o{ user_roles : دور
  roles ||--o{ role_permissions : منح
  permissions ||--o{ role_permissions : صلاحية
  users ||--o{ user_permissions : استثناء
  users ||--o{ user_sessions : جلسة
  users ||--o{ qr_tokens : رمز

  tuition_policies ||--o{ installment_schedules : جدول
  tuition_policies ||--o{ student_invoices : تصدر
  student_enrollments ||--o{ student_invoices : فاتورة
  student_invoices ||--o{ invoice_installments : أقساط
  discount_types ||--o{ discount_rules : قواعد
  discount_rules ||--o{ student_discounts : اعتماد
  student_invoices ||--o{ student_discounts : تطبيق
  student_invoices ||--o{ payments : دفعات
  payments ||--o| payment_receipts : وصل
  payment_receipts ||--o{ receipt_prints : إعادة_طباعة
  payment_receipts ||--o{ receipt_signatures : اعتماد_وثيقة

  study_types ||--o| cashboxes : خزنة
  cashboxes ||--o{ cash_transactions : دفتر
  payments ||--o{ cash_transactions : إيراد
  expense_books ||--o{ cash_transactions : صرف
  expense_books ||--o{ expense_items : بنود
  expense_books ||--o{ expense_approvals : موافقات

  teaching_staff ||--o{ teacher_fees : استحقاق
  teaching_staff ||--o{ timetables : محاضر
  departments ||--o{ timetables : جدول
  students ||--o{ student_documents : إضبارة
  users ||--o{ audit_logs : أثر
```

## قواعد السلامة

1. لا يُستخدم اسم الطالب كمفتاح.  
2. الرقم الجامعي فريد داخل الكلية.  
3. رقم الوصل فريد عالمياً.  
4. حركة الخزنة إما إيراد دفعة أو صرف كتاب أو تسوية موثّقة.  
5. `audit_logs` للإلحاق فقط من واجهة النظام.
