# Scholar teacher management

## Combined teacher schedule generator

From **Teachers → View Schedule**, use **Generate Schedule** or **Regenerate Schedule**. The same control is available on the teacher management profile. `TeacherScheduleGenerator` reserves three randomly selected one-hour free periods on different working weekdays, then uses a bounded, most-constrained-first search to allocate missing approved curriculum hours around them. Existing lessons, classroom use, class sessions, qualifications, unavailable periods, and teacher limits constrain every candidate. Existing shared student lessons are preserved. Unknown curriculum produces no invented lessons; enter approved subject hours in Teacher Reports before asking for additional teaching lessons. Infeasible plans produce a validation explanation instead of partial writes.

The preview displays the current weekly grid and proposed new lessons and free periods. Nothing is written during preview, including identity sequences. The administrator must confirm the displayed proposal; existing free periods additionally require explicit replacement confirmation. A server-side token expires after 30 minutes. A fingerprint of scheduling inputs rejects changed availability, assignments, limits, or lessons. Confirmation holds the existing scheduling lock, revalidates each write, and saves the entire plan transactionally. The generator adds approved missing lessons to the existing `schedules` table, so class and teacher views share the same records. It does not reschedule historical lessons automatically.

The weekly view includes teacher, grade, class, subject, and session filters, workload, designated free-period counts, hourly teaching/free/available cards, classroom names, and print styling. Existing theme tokens supply card colors in Light, Night, Relax, and Cute. Browser visual and print-preview QA remains a manual deployment check.

New implementation paths: `app/Services/TeacherScheduleGenerator.php` and `resources/views/school/teachers/schedule-preview.blade.php`, with routes and controller actions under `teacher-management.schedules.*`. Existing teacher-management migrations already provide the required schema; no additional tables or destructive migration are needed for this addition.

Open **Teachers** (`/school/teachers`) as an administrator. This upgrade uses the existing Laravel application, PostgreSQL database, shared layout, sidebar, navigation, and Light/Night/Relax/Cute theme picker.

## Preserved records and audit findings

The database contains 10 active Khmer, 10 active English, and 10 active Chinese teachers. No inactive Khmer teachers were found. English and Chinese teachers also have Khmer subject assignments. The current Khmer catalog has 13 distinct stored names, rather than assuming the illustrative 16-subject catalog. All existing names and grade relationships remain intact.

There are 30 Khmer class identities and 60 configured Khmer class sessions. Morning and afternoon remain sessions of one identity. Reports show active enrollment counts per session, missing class links, and possible shared students when names and birth dates match. Empty cohorts are marked unconfirmed. The configured-session demand is 1,020 academic hours per week, assuming five four-hour days and three student relaxation hours per session. The hours-only staffing minimum at 20 teaching hours is 51, so the target of 30 is not a guarantee of coverage. Unenrolled or unlinked configured sessions require review before approving a hiring plan.

Existing teacher assignments include more than two subjects per teacher. They are preserved and flagged. A general degree in the old free-text qualification field does not automatically verify particular teaching subjects. The new qualification and working-period tables are intentionally not populated from assumptions about historical records.

The before/after audit compared SHA-256 hashes of every original column in `users`, `students`, `school_classes`, `class_groups`, `class_sessions`, `subjects`, and `schedules`: all were unchanged. Counts remain 241 users, 204 students, 128 legacy classes, 54 class groups, 108 sessions, 550 subjects, and 500 schedules. Only additive schema and default configuration records were applied. No sample teachers or school timetable changes were committed during implementation.

## Interface and administrator workflow

The Teachers page keeps the original `teacher-workspace.png` hero artwork and its existing visual treatment. It displays four statistics, simple Search/Program/Status controls, a seven-column list, and Quick Actions. Advanced session/class/account filters are collapsed. Mobile uses teacher cards. The original image file is unchanged.

1. **Add Teacher** collects name, gender, phone, unique email, program, one or two verified subjects, teaching limit, working sessions, and active/inactive status. A teacher can inherit the school workload limit by clearing their individual override. Newly created accounts receive a random password; this form does not send or disclose credentials. Account access can be managed using the existing administrator account tools.
2. **Manage** opens Overview, Assign Classes, Weekly Timetable, and Break Schedule tabs. Confirm real qualifications and working hours. Custom hours support part-time duty and unavailable intervals. Morning/afternoon presets apply Monday–Friday; English and Chinese can use their actual evening/custom hours.
3. Historical surplus subject assignments must be explicitly cleared or reassigned using the existing Subjects editor. Recording verified qualifications preserves historical lessons and exposes discrepancies; it does not silently remove assignments or certify all past subjects. New/reassigned subjects and lessons must pass the current rules.
4. **Assign Classes** filters the existing grade, class, session, and subject records. The available-time picker checks confirmed working hours, qualifications, workload, breaks, and existing timetable entries. Saving checks again. Replacing a subject's existing teacher requires explicit confirmation and validates every existing lesson for that subject.
5. **Generate Weekly Teacher Breaks** previews all active teachers, or **Generate Breaks** previews one teacher. No break records are saved by previewing. A teacher needs at least one valid hourly slot on three different weekdays. The generator randomly selects three distinct days and a valid slot on each. Insufficient schedules show the requested explanation and **Fix Schedule** link; no teaching lesson is removed or moved.
6. Confirm the preview to save. Replacing existing breaks requires a second explicit checkbox. Editing one break validates and saves the entire three-break schedule atomically. Every completed recurring weekly break plan contains exactly three one-hour breaks on different weekdays. Breaks are separate from student relaxation entries and never count as teaching workload. Midday 11:00–13:00 is excluded.
7. **Assign Teachers Automatically** previews only vacant Khmer assignments with confirmed active class-session links and existing approved lesson times. It simulates assignments under a transaction and rolls back all preview writes. Qualified teachers with lower current workloads are considered first. Existing assignments are retained. Missing curriculum links, lesson times, qualifications, or availability are reported. Confirmation rechecks the actual database and saves the whole proposed batch atomically. Existing assignments changed since preview cause rejection.
8. Optional **Generate Sample Teachers** previews clearly labeled fictional Cambodian planning records, using reserved `.invalid` email addresses. Records are created only after the administrator confirms that exact preview. Existing email identifiers are never overwritten. Replay is rejected, and the staffing target is rechecked. These records start without verified qualifications or duty periods and cannot be assigned lessons until configured. This feature was tested only against the isolated test database.

Preview tokens are stored server-side, expire after 30 minutes, and are checked on confirmation. Replaced/stale previews cannot be confirmed by an older form. Break previews also check whether the saved break plan changed after preview.

## Workloads, reports, and scheduling integrity

Workload sums actual academic timetable durations in minutes across all programs, excluding student relaxation and teacher breaks. Classes are counted by identity, so two sessions do not duplicate the identity. Status is UNASSIGNED (no lessons), AVAILABLE (below limit), FULL (at limit), or OVERLOADED (above limit). Inactive account status is shown separately.

**Teacher Reports** (`/teacher-management/reports`) contains the configurable Khmer staffing target and school teaching limit, subject coverage, teacher workloads, session/enrollment evidence, and expandable calculation details. Subject coverage uses approved per-class curriculum minutes when supplied; otherwise it marks existing scheduled hours as provisional demand. Enter approved weekly subject hours in the report. The existing hourly class timetable generator requires complete, whole-hour curriculum allocations totaling 17 hours when explicit allocations are supplied.

Subject capacity is allocated through a flow network so each teacher's free minutes are counted only once across their qualifications. It removes commitments in all programs, unavailable periods, and scheduled teacher breaks, and checks relevant class sessions. Existing lessons are checked for qualifications, working hours, breaks, workload, and conflicts. These are planning estimates, not proof of a complete future timetable: unknown curriculum, missing cohorts, and competing simultaneous lessons require review. Assignments always undergo exact interval validation at save time.

`teacher_management_settings` provides a singleton lock acquired by scheduling and qualification writes. Teacher profile changes, break confirmations, manual lesson assignments, automatic assignment confirmations, regular subject/schedule saves, and class timetable generation serialize through this lock. Server entry points validate qualifications, two distinct subjects across all teaching assignments, active status, working hours, unavailable intervals, workload, class sessions, teacher breaks, and teacher/class/room overlaps.

Historical fixture/import code can still create unconfigured legacy model records; it is not a scheduling API. Ordinary application subject/schedule endpoints enforce the current rules, and direct Eloquent writes for configured teacher profiles are additionally guarded. Direct bulk SQL/imports must use the service or perform equivalent validation. This preserves legacy data without disabling validation on new administrator operations.

PostgreSQL qualification slots and unique keys enforce at most two verified subjects, including concurrent inserts. Deferred break-plan constraint triggers enforce exactly three breaks at transaction completion; row checks enforce one-hour duration and weekdays, and unique plan/day keys prohibit duplicates. Session, duty, lesson overlap, and availability checks are enforced by the locked scheduling service. Recurring plans are used because the existing timetable is weekly and has no academic-year entity. No academic-year records are invented.

The older destructive teacher timetable helper no longer clears schedules or invents subject combinations. It delegates to the existing class timetable generator. The class generator now considers confirmed duty, breaks, and workload during planning; it retains the existing explicit regeneration confirmation and never silently cancels student lessons to fit a teacher break.

## Verification

```powershell
php artisan test --compact
php artisan view:cache
php scripts/audit-teacher-management.php
php scripts/audit-teacher-management.php --smoke
php scripts/verify-teacher-management-postgres.php
```

The PHPUnit suite uses the existing isolated SQLite configuration. PostgreSQL verification uses two connections to check lock contention and disposable temporary clones to check exact-three breaks, durations, duplicate days, and the subject limit. It rolls back all probe changes without touching school records or identity sequences. The smoke command renders the directory, reports, and a profile using the configured PostgreSQL database without saving anything.

The main suite covers the new preview/confirmation flows, randomized break invariants, workload, subject limits, conflicts, unavailability, part-time/session handling, sample confirmation/replay, existing record preservation, shared subject capacity, configurable target, enrollment/session demand, hero markup, theme options, permissions, and existing system behavior. Responsive CSS and existing theme tokens are reused. Visual browser QA and JavaScript runtime QA have not been performed in this environment.

The additive migrations were applied to the configured school database. Other deployments should run `php artisan migrate`. Do not run destructive reset commands on school data.

## Exact changed paths

All paths are relative to `D:\laravel_projects\com3ms1ms2`.

- `app/Http/Controllers/SchoolModuleController.php`
- `app/Http/Controllers/TeacherManagementController.php`
- `app/Models/Schedule.php`
- `app/Models/Subject.php`
- `app/Services/AutomaticTimetableGenerator.php`
- `app/Services/TeacherManagementService.php`
- `app/Support/TeacherTimetable.php`
- `app/Support/TimetableConflicts.php`
- `database/migrations/2026_10_29_000004_create_teacher_management_tables.php`
- `database/migrations/2026_10_29_000005_refine_teacher_break_integrity_function.php`
- `public/css/teacher-management.css`
- `public/js/teacher-management.js`
- `resources/views/school/layout.blade.php`
- `resources/views/school/teachers/assignment-preview.blade.php`
- `resources/views/school/teachers/break-preview.blade.php`
- `resources/views/school/teachers/form.blade.php`
- `resources/views/school/teachers/index.blade.php`
- `resources/views/school/teachers/manage.blade.php`
- `resources/views/school/teachers/reports.blade.php`
- `resources/views/school/teachers/sample-preview.blade.php`
- `resources/views/school/teachers/timetable.blade.php`
- `resources/views/school/teachers/weekly-grid.blade.php`
- `routes/web.php`
- `scripts/audit-teacher-management.php`
- `scripts/verify-teacher-management-postgres.php`
- `tests/Feature/AutomaticTimetableGeneratorTest.php`
- `tests/Feature/CambodianSchoolDataTest.php`
- `tests/Feature/SchoolTest.php`
- `tests/Feature/TeacherManagementTest.php`
- `tests/Feature/TeacherScheduleTest.php`
- `docs/teacher-management-upgrade.md`

`app/Models/Assignment.php` remains unchanged because it represents homework rather than teacher scheduling.
