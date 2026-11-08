# Grade, session and package tuition

Use School fees > Current program tuition > Edit tuition, or Edit on a price row. Choose a program and any combination of grades/levels. Japanese levels come from existing configured classes. Morning and afternoon can share prices; evening has a separate price. Uncheck the shared-price option to give afternoon its own rate.

Enter the monthly amount and, optionally, a six-month package amount for each session. Blank package amounts mean monthly x 3. The live preview shows package savings and the academic year's configured tuition. Example: 25 USD per month or 120 USD per six months gives 240 USD for 12 months paid in packages. A 10-month year is one six-month package plus four months: 220 USD.

Save & activate creates audited approved versions for the selected grades. Existing charges and scholarships are preserved. Original invoices and payments remain unchanged. Concurrent changes cause a stale-version validation error rather than silently overwriting the newer prices. Saving prices does not issue any student invoices.

Issue invoices supports Monthly, 6-month package, Semester, Annual, and Monthly installments. Select the student's actual class session. Monthly/installment invoices use that session's monthly rate. Other plans apply package prices to complete consecutive six-month blocks, with remaining months at the monthly rate. A package cannot overlap previously billed months; annual billing continues to exclude months already billed. Coverage still deduplicates sessions within the same class-group enrollment.

Package amounts are distributed across the package's monthly invoice items using integer cents, including any remainder, so line items sum exactly to the approved price. Configured monthly scholarships apply after tuition pricing and cannot exceed the discounted package rate. Registration and other one-time charges retain their existing enrollment/year behavior.

The tuition table and child enrollment cards show session rates and package/year totals. Invoice snapshots now record the selected session. Existing records have no session assigned retrospectively. Financial calculations use DECIMAL storage and integer minor units.

Validation: 35 related feature tests passed (401 assertions), including grade selection, stale edits, evening prices, package invoices, duplicate-month protection, cent rounding, authorization and preserving old invoices. The additive migration creates only nullable pricing/session fields; existing approved rates keep their previous behavior until an administrator saves changes.

Existing fee versions retain their original package duration. The six-month editor converts old package quotes proportionally for display; saving creates a six-month version. Issued invoices are never repriced. The legacy quarterly API plan remains compatible with historical three-month billing.
