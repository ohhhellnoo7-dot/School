# Khmer timetable rebuild — diagnostic before changes

Inspected 30 October 2026 against PostgreSQL. The read-only baseline is saved privately in `storage/app/qa/khmer-before.json` (including hashes of nine school tables).

* 499 active lessons; all 499 lack `classroom_id`. Labels and reservations cannot prove which physical room an administrator intended. Physical room overlap counts are therefore inconclusive.
* No overlapping teacher or cohort pairs detected in the current active records.
* Existing `class_groups` represent identities and `school_classes` represent session cohorts. Keep these records and enrollment links; do not duplicate identities.
* The canonical lesson table is `schedules`. Existing class and teacher views query it, but room activity infers links from names/reservations and can display contradictory rooms.
* Existing generator handles individual classes, not a complete atomic Khmer replacement. Approved curriculum, qualifications and availability must be verified before a complete draft can be declared ready.
* Baseline tests: 121 pass, 27 fail. The pre-existing untracked unified/rebuild tests refer to missing routes and services. No production records were changed during inspection.

## Backup and recovery

Before any administrator confirms replacement, take a full PostgreSQL backup using the connection's database name and host (enter the password interactively):

```powershell
pg_dump --host=localhost --username=postgres --format=custom --file=storage/app/qa/school-before-timetable.dump DATABASE_NAME
pg_restore --list storage/app/qa/school-before-timetable.dump
```

Test recovery into a separate empty database with `pg_restore --dbname=RECOVERY_DATABASE storage/app/qa/school-before-timetable.dump`. Do not restore over the running school database. Application confirmation also writes a private JSON snapshot and aborts if writing it fails. JSON is an audit snapshot; the full database dump is the recovery backup.

## Review workflow

Open **School Timetables**. Review diagnostics, establish physical room IDs explicitly, enter approved weekly subject requirements, teacher qualifications and duty hours, then generate a Khmer replacement preview. Missing prerequisites are blockers, never invented. Preview does not publish. Confirmation requires the preview token and explicit replacement approval; changed scheduling inputs invalidate it. Existing cancelled lessons remain as history. English/Chinese schedules are read as constraints and excluded from Khmer replacement.

## Initial review artifacts, before authorized subject-limit correction

The complete Khmer preparation is **blocked**, with 70 prerequisite findings: 28 missing cohort/session links, 32 cohorts without approved curriculum, and 10 teachers exceeding two approved subjects. All 30 identities exist; only 32 of the 60 configured morning/afternoon cohort links exist. No enrollments or cohorts were invented to fill these gaps. The diagnostic additionally reports 499 missing physical room IDs, 10 missing complete teacher free-period plans and two overloaded teachers. The generator checks remaining teacher availability, physical reservations, required hours and free periods once the prerequisite blockers are resolved.

* Full PostgreSQL recovery archive: `storage/app/qa/school-before-khmer-20261030-205820.dump` (209,325 bytes). `pg_restore --list` reads the archive successfully; a full recovery rehearsal was not performed.
* Blocked replacement draft: `storage/app/private/timetable-drafts/khmer-20261030-205824.json`. This includes all findings and no publishable lesson plan. Generate an updated artifact with `php artisan school:khmer-timetable-draft --year=2026-2027`.
* Baseline preservation check: `php scripts/verify-khmer-preservation.php`. All original rows and columns in nine school tables match. This includes English and Chinese schedules. The additive migration retains the pre-existing `lesson_room_corrections` table and its history, and adds the missing room-change reason column. Fresh installations also receive the legacy academic-year column.

Student, teacher and physical-room tabs query the same `Schedule` IDs. Qualified substitute teachers live on the lesson; editing one lesson does not overwrite the approved subject teacher. Editing and batch confirmation use the existing singleton PostgreSQL row lock. Stale or replayed previews and late batch failures leave the timetable intact. Room-name inference no longer places unconfirmed lessons on physical room cards; reservations continue to establish occupancy. The separate room repair tool requires its own preview and confirmation and never runs during Khmer generation.

The UI retains Scholar's layout, sidebar, hero artwork on existing pages and Light/Night/Relax/Cute theme controls. Weekly blocks use table row spans, wrap long labels and render overlapping records in one conflict block. PHP page rendering and Blade compilation have been verified; interactive browser and visual theme checks have not been performed.

Validation commands:

```powershell
php artisan test --compact
php artisan view:cache
php scripts/smoke-unified-timetable.php
php scripts/verify-rotation-postgres.php
php scripts/verify-teacher-management-postgres.php
php scripts/verify-khmer-preservation.php
```

The pre-existing untracked test files required two fixture corrections: supplying the missing physical-room fixture helper and sending the actual afternoon session in the shared-room privacy scenario. Assertions were retained.

Final checks: **151 tests passed (2,458 assertions)**. Blade compilation, six PostgreSQL page renders, PostgreSQL concurrent-writer serialization, active-slot uniqueness and three-period integrity probes passed. The nine-table preservation check and `git diff --check` passed. JavaScript/browser interaction checks were not run; Node is unavailable on PATH.

## Authorized two-subject correction — 30 October 2026

The administrator explicitly authorized removing extra teacher subject assignments. The correction kept at most two distinct subjects per Khmer teacher, prioritizing protected other-program assignments and existing approved qualifications. It removed 44 extra assignment links from 10 teachers. All subject records remain available for reassignment. Eight active Khmer lessons for the removed teaching assignments were cancelled with their original teacher and full history retained. Substitute teachers' valid lessons were preserved.

Full PostgreSQL backup: `storage/app/qa/school-before-khmer-20261030-212336.dump`. Detailed before/after audit and private JSON snapshot location: `storage/app/qa/subject-limit-applied.json`.

`php scripts/verify-khmer-teacher-subject-limit.php` verifies all 14 backed-up tables against the snapshot, allowing only the explicitly recorded teacher unassignments and lesson cancellations. It passed and confirmed that no Khmer teacher has more than two assigned or actively taught subjects. English/Chinese schedules and all other school rows are unchanged. The older original-baseline verification is now historical: its subjects/schedules hashes intentionally differ following this authorized correction.

The updated complete-school draft is `storage/app/private/timetable-drafts/khmer-20261030-212352.json`. It remains blocked by **60** prerequisites: 28 missing cohort/session links and 32 missing approved curriculum configurations. Subject hours were not invented, and no replacement timetable was published. Extra assignments can be previewed safely with `php scripts/limit-khmer-teacher-subjects.php`; the `--apply` option mutates data and should only be used for explicitly authorized corrections.

Two focused correction tests passed (17 assertions), covering preservation of subject records, cancellation history, substitute lessons, other-program assignments, backups and the two-subject limit.

## All-class subject-block rules — 30 October 2026

The new all-class request fixes Khmer Literature and Mathematics at 120 consecutive minutes, and other subjects at 60 minutes. The existing `Khmer` catalog name is treated as Khmer Literature for duration only; names, qualifications and approved weekly totals are not renamed or invented. Odd weekly totals for a two-hour subject are now a blocking curriculum discrepancy rather than a shortened final lesson. English/Chinese duration settings are unchanged.

The rule is applied to rotation generation, teacher additive previews, manual shared-lesson validation, curriculum settings and final confirmation. The old hourly generator refuses established Khmer cohorts and directs administrators to the confirmation-gated replacement preview. The batch preview now retains the original student relaxation day/time before retiring old lessons in its rolled-back simulation.

Classroom availability cards and class-group room assignments provide **View Lessons** links. Every weekly reservation also expands into the actual, physically confirmed shared lesson records for its cohort/day/session. Reservations without confirmed lesson IDs explicitly say so and remain reserved; no subject rows are invented. The Scholar heroes, cards and theme variables remain in use.

Diagnostics, blocked drafts and replacement previews include a validation report for **every identity 7A–12E**, including identities with no linked cohort. It reports each existing cohort's subject duration, required versus scheduled weekly hours, missing subjects, qualifications, workload, free-period conflicts, shared lesson IDs, room references, reservations and overlaps across all programs. Complete-school confirmation rechecks all 30 identities and every existing Khmer cohort in the same publishing transaction.

The live catalog contains 280 Khmer subject records across 31 cohorts with subjects; 64 duration settings required correction. These settings were applied after a full PostgreSQL backup (`storage/app/qa/school-before-khmer-20261030-215826.dump`). Audit details: `storage/app/qa/khmer-duration-settings-applied.json`. `php scripts/verify-khmer-duration-settings.php` passed against all 14 backed-up tables: only recorded subject duration settings and their update timestamps changed. All lesson records, weekly requirements, students, teachers, rooms, reservations and other programs are preserved.

The current full-school preparation is still **blocked** by 28 missing cohort/session links and 32 missing curriculum configurations. It has not published a replacement. The detailed 30-class review is available at `storage/app/private/timetable-drafts/khmer-all-classes-validation.md`, with JSON source `storage/app/private/timetable-drafts/khmer-20261030-215953.json`. This report includes 32 existing cohorts; 14 identities have no linked cohort. Use `php scripts/export-khmer-class-validation.php` after preparing an updated draft to refresh the readable report.

An isolated database test builds all 30 identities plus a separate afternoon cohort under 12E, validates all 31 cohorts, confirms the complete-school plan and checks that student, teacher and classroom tabs expose the same lesson IDs with two-row lesson blocks. Further tests cover exact relaxation-time preservation, rejection of odd Mathematics hours, one-hour History blocks and the legacy-generator publishing guard. Earlier teacher-preview tests were updated to expect two-hour Mathematics lessons, preserving their workload, confirmation and stale-preview assertions.

Latest validation: **159 tests passed (2,581 assertions)**. Blade compilation, six PostgreSQL page renders, 14-table preservation verification, PostgreSQL active-slot uniqueness and concurrent-writer/free-period integrity probes passed. Browser interaction and visual verification of the four themes remain unperformed.
