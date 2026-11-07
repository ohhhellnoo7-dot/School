# Parent Payment Overview Drawer

Open School fees > Parent payment status and click a parent's eye icon. The directory and financial rules are unchanged. Details are loaded on demand into one Bootstrap offcanvas (720px maximum on desktop, full width on mobile), with a fixed header/tab strip and one scrolling content area.

## Sections

- Overview: parent initials/photo, existing parent profile number, linked children, family status, per-currency paid/balance cards, earliest payment/reminder date, latest recorded payment and recent activity.
- Payment History: timeline of ledger payments, historical opening balances, refunds and other approved adjustments. Filter by student/invoice search, linked child, program, academic year, invoice status, transaction type and date range. The parent-scoped, eager-loaded history response is paginated to 15 items. Filters affect the timeline, not the family's overall summary cards.
- Upcoming Payments: unpaid invoice balances and due dates, the next approved tuition-calendar date, and a separate school-set reminder. Reminder forms use the existing backend and audit path. Save/clear feedback appears only after backend success; clearing a reminder does not create or cancel charges.

Click View invoice to load a read-only preview inside the drawer. The original full invoice remains available in a separate tab for Print / Save as PDF. No new PDF dependency or payment workflow is introduced.

## Financial and access rules

- Total Paid uses the existing invoice `paid` values and approved adjustments, through SchoolFinanceService. It never adds timeline rows to those totals. USD and KHR remain separate.
- Historical opening balances are labeled and have an accessible information tooltip. They are not new transactions. Missing historical dates, programs and years remain explicitly unknown.
- Refunds appear separately; timeline amounts are not an alternative accounting total.
- An unpaid invoice due date, an unissued tuition-calendar date, and a manual reminder remain distinct in Upcoming Payments. Unknown dates are not inferred from the last payment.
- Family status uses the existing rules; a parent without invoices is not marked Fully Paid.
- The details endpoint and reminder mutations are administrator-only. A child filter must belong to the selected parent. Invoice previews retain existing invoice authorization, including linked-parent/student restrictions.
- The redesign has no migrations and does not recalculate or modify financial records. Existing read-only tests compare fees, payments, adjustments, items and audit rows before/after drawer and preview requests.

## UI and verification

Native Bootstrap offcanvas focus trapping, backdrop, Escape and close behavior are reused. Tabs have ARIA relationships, roving focus and arrow/Home/End keyboard navigation. Requests are cancellable, use loading skeletons, and show clear error states. Tooltip instances are disposed when content changes. Motion respects prefers-reduced-motion. All styles use shared Scholar theme tokens.

Focused checks: `php artisan test --filter="ParentPaymentDrawerTest|ParentPaymentStatusTest|SchoolFinanceTest|FeePaymentTest"` ? 22 passed, 239 assertions.

Local Edge browser checks covered desktop/mobile sizes, history filtering, invoice preview, reminder input focus, tab navigation, Escape/close and focus restoration. All 21 configured themes were exercised for layout overflow. Screenshots are in `storage/app/qa/finance-drawer-*.png`; the local QA script is stored privately in `storage/app/private/finance-validation/check-drawer-browser.py`. Browser checks used read-only views and an invalid reminder request; no real payment or reminder was created.
