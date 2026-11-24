# Employee salary

Administrators can open **Workspace → Employee salary**, choose a month, and open a teacher to set up salary and view attendance.

## Monthly rates (USD)

| Work sessions per workday | Base monthly salary |
| --- | ---: |
| One daytime session (4 hours) | $125.00 |
| Two daytime sessions (8 hours) | $250.00 |
| One evening session | $31.25 |
| Two evening sessions | $62.50 |
| Two daytime and two evening sessions | $312.50 |

Daytime and evening rates are additive. The salary cycle start date establishes the three-month increase periods, independently of old hire dates. Every three completed calendar months adds $5 to the **total monthly salary**, without multiplying the increase by the number of sessions. The increase repeats indefinitely. The populated profiles start with November 2026: November–January use the base salary, February–April add $5, and May–July add $10.

The current month is assessed as of today; past months use their final day, and future months show an end-of-month preview. Payments cannot be recorded for future months. There are no partial-month prorations.

## Attendance and payments

The name search updates payroll and payroll attendance as the administrator types. The **Teacher attendance** button opens a date-filtered teacher list with whole-day and individual session attendance entry.

Attendance uses the existing teacher daily records and session scans, with explicit payroll attendance marks taking precedence. A specific payroll session mark takes precedence over a whole-day payroll mark. Recording a new whole-day mark resets earlier payroll session overrides for that date. Daily totals count each date once, using absent, late, excused, then present as the summary priority. Missing attendance is shown as **Not recorded** and does not create a deduction.

For teachers configured for two daytime sessions (8 hours), a morning-only or afternoon-only absence deducts $10; absence in both deducts $15 total for the day. Late arrival deducts $1 once per day; the first excused day in each calendar month has no excuse deduction. Each additional excused day deducts $5 once across all sessions. If different sessions have different deduction types, those types add together. No absence rate is configured for teachers working only 4 daytime hours or only evenings. Late and excuse deductions still apply to them. Future attendance and attendance before the salary cycle start date are excluded.

The payroll list displays deductions and the net monthly salary. The detail page includes a dated deduction breakdown. Net salary is floored at zero.

The Leave Record shows an attendance bonus for completed months and a calendar-year total. It credits $10 per configured daytime session and $2.50 per configured evening session when attendance is recorded for every scheduled session. Late attendance qualifies. Absence, excused leave, and missing attendance on a scheduled session disqualify the month. Enabled Khmer holiday closures are skipped. The bonus is for reporting only and is excluded from payroll amounts.

Saving work sessions configures salary; it does not mark salary as paid. To record a completed salary payment, enter its date and optional reference and explicitly confirm it was paid. This records an existing disbursement; it does not transfer money. One salary payment can be recorded per teacher per month. The server calculates the amount and checks that it matches the reviewed amount.

Each recorded payment preserves the work sessions, salary cycle start date, base salary, increase, deductions and their breakdown, net total, recording administrator, and payment date. Subsequent profile changes leave those payment records intact. Payroll attendance edits are blocked for a month with a recorded salary payment. Existing student fee and expense ledgers are separate from this payroll history.
