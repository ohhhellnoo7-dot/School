# Pay all families

Administrators can open Pay in the School fees header, select 1, 6 or 12 months, review the preview, select a payment method and confirm funds were received. This is a manual recording workflow, not a bank payment gateway. Implementing or previewing the feature never records live payments automatically.

## Scope and calculations

The batch covers active students whose enrollment date has started and whose linked account is a parent. All enrolled programs are included. Multiple identical sessions in one class-group enrollment are charged once. If those sessions have different prices, the preview asks the administrator to resolve the enrollment instead of silently selecting a rate.

The calendar-month tuition period begins in the current month. It uses the existing approved grade/session fees, package pricing, scholarships and one-time enrollment/year charges. USD and KHR totals stay separate. Preview amounts and issued invoices reuse SchoolFinanceService.quoteItems rather than implementing a second calculator.

Every selected month needs a unique approved billing calendar. Missing prices, overlapping calendars, unreviewed legacy fees and incompatible sessions block the entire batch. Existing coverage is reused. A one-month batch cannot accidentally settle a larger existing annual invoice; settle that invoice individually or choose its complete period. Previously paid invoices are linked as already settled without new receipts.

## Date lock and duplicate protection

After a successful batch, Pay is disabled globally for all administrators and all duration choices until the next eligible date. One month from 7 November is 7 December; six months is 7 May; twelve months is 7 November of the next year. Month-end dates clamp without drifting: 31 January -> 28 February -> 31 March when paid on time. A late payment starts its next waiting period from the actual payment day.

Both the preview and server-side payment handler check the lock. The header re-enables at the eligible date when focused or on its minute check. A fresh request key cannot bypass the server lock. Replaying the same successful request key returns the original result without another payment. Reusing it with a different duration or method is rejected.

Preview fingerprints detect changed rates, enrollments or balances. All invoices, payments, receipts, allocation links, audits and the next-date lock commit in one transaction using the existing finance row lock. Failure partway through rolls back the entire batch and the lock. Invoices retain their original amounts and sessions.

Bulk payment runs and allocations are protected by foreign keys and unique submission/cycle keys. Individual invoice and receipt pages keep their existing authorization behavior; bulk preview/results/mutations are administrator-only.

## Validation

Feature checks cover all duration choices, package totals, children in multiple programs, duplicate sessions, next-date/month-end rules, repeated submissions, stale previews, missing prices, authorization and a simulated failure during the second payment. Related finance tests are run with:

`php artisan test --filter="BulkTuitionPaymentTest|SessionTuitionTest|SchoolFinanceTest|ParentPaymentDrawerTest|ParentPaymentStatusTest|FeePaymentTest|DashboardAjaxTest"`

Live read-only preview at implementation: 119 families, 202 active linked children, 203 enrollment rows and no setup issues. Before/after counts remained zero invoices, zero payments and zero bulk runs. No live bulk payments were made during implementation.
