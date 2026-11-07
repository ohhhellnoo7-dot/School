# School fees and payments

Open School fees in the existing sidebar, or `/finance`. Administrators can use Fee settings and Issue invoices. Parents and students have read-only access scoped to linked student records.

## First use

1. In Fee settings, choose the academic year, first billable month and month count. Create example drafts, or enter a fee configuration yourself. Japanese levels come from the existing Japan program. Examples are suggestions, never activated automatically.
2. Review each program/grade version, its currency, dates, discounts and one-time charges. Approve & activate only after confirming school prices. Review / revise creates a new draft. Previous configurations remain available.
3. In Issue invoices, search for a student. Review any historical fees first: map old tuition to its enrollment and month coverage, or classify documented non-tuition charges. This preserves original money and prevents duplicate billing.
4. Select an enrolled program/class, matching approved version, and payment plan. Each program is billed separately. Multiple sessions of one class group count as one enrollment.
5. Open an invoice to record verified payments, apply authorized adjustments, issue receipts or print. Print / Save as PDF uses the browser's PDF destination.

## Accounting rules

- Monetary database columns use DECIMAL (PostgreSQL NUMERIC). Application arithmetic uses integer minor units, never floating-point amounts.
- Existing `fees` rows serve as invoices. `invoice_items` hold snapshots; `invoice_periods` uniquely cover student, academic year, enrollment and billable month. A payment is allocated directly to one invoice using `fee_payments.fee_id`; split payments across invoices require separate allocations/receipts.
- Annual billing covers the remaining unbilled months. Semester billing covers the configured semester length. Installments create separate monthly invoices with individual due dates. Partial payments settle an existing invoice in portions.
- Flat monthly scholarships/discounts reduce each month's tuition. Registration, books, uniforms, examinations and other charges apply once per enrollment/year, on the first issued invoice. Individual discounts/credits can also be recorded as audited adjustments.
- Payments, billing, configuration approvals and adjustments run in transactions using a shared finance row lock. Database unique keys prevent duplicate billing months, receipt numbers and payment submission keys. A repeated key with a changed payload is rejected.
- Original invoice amounts do not change. Discounts/waivers/credits reduce net billed; charges increase it. Refunds reduce net collected and reopen debt. Pair a refund with a credit if the underlying charge must also be cancelled.
- Paid means balance fully settled. Waived means full waiver; refunded indicates returned payment with an unpaid balance. Overdue uses the invoice due date. An overdue balance can still exist on a refunded invoice.
- Existing payment totals are retained as opening balances. Their transaction histories are not fabricated. Opening balances are included in totals but excluded from receipt trends. Legacy fees remain unclassified by program until an administrator reconciles them.
- USD and KHR totals stay separate. No exchange rate is assumed. The general overview dashboard's dollar cards show USD; the finance page and Reports show both currencies separately.
- Once billing has started, a revised fee configuration must retain its academic year's month calendar, billable month count and currency. Next-year settings can define a new calendar.
- Generic fee edits/deletes are disabled. Student deletion is restricted when financial history exists. Audit entries capture actors, actions and details; recent history is visible in Fee settings.
- Parent/student invoice, export and receipt queries use the existing linked-student authorization scope. Only administrators can make financial changes.

## Branding and themes

The existing Scholar sidebar, navigation, logo and theme picker remain. Finance styles use shared theme variables and responsive grids/tables. Configure receipt school name/address/phone through `config/finance.php` (SCHOOL_NAME, SCHOOL_ADDRESS, SCHOOL_PHONE). PDF export uses browser printing; no new PDF or payment-gateway dependency is installed. Rendering checks do not replace visual review at every screen size/theme.

## Validation performed

- 23 finance/payment/dashboard tests passed (228 assertions), plus PostgreSQL transactional smoke checks for all four programs.
- Full-suite regression scan: 267 passed, 10 failed (before the final additional configuration workflow test). The same 10 attendance/timetable failures were reproduced with the original Fee model, dashboard/module controllers and original affected test files. Their baseline comparison and full logs are stored privately under `storage/app/private/finance-validation`.
- Additive PostgreSQL migration applied. All 736 original fee records were compared before/after using the original columns: identical SHA-256 `087618fbbf29db2ad45bdf4b2b756c5326f54575f35c8485abbb4ef82c8e38e5`.
- Original billed total: USD 175,130.30; paid total: USD 112,850.00. No example prices were approved, and no real invoices/payments were created by smoke tests.
- Original fee snapshot: `storage/app/private/finance-before-upgrade.json`. Financial migrations intentionally refuse automatic rollback because dropping the ledger would erase history; use a reviewed archival migration/backup restoration instead.

Run focused checks with `php artisan test --filter="SchoolFinanceTest|FeePaymentTest|DashboardAjaxTest"`.
