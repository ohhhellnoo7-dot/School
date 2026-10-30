# Scholar lesson rotation upgrade

## Use the feature

1. Open a class timetable. Expand **Scheduling Settings** to enter the school's approved weekly hours for every assigned subject, choose a preferred one-hour or two-hour lesson duration, and confirm the separate student relaxation requirement. Zero hours explicitly excludes a subject from that week's teaching demand. Two-hour preferences allow a final one-hour block for odd weekly totals. Curriculum and relaxation must fit the session's 20 weekly hours. These settings are not official Cambodian curriculum figures.
2. Confirm teacher qualifications, at most two distinct subject assignments, working hours and the weekly limit in Teacher Management. The initial school limit remains 20 teaching hours. Physical room reservations cover the student cohort; an optional reservation supervisor is a separate commitment and is never displayed as the subject teacher.
3. **Generate timetable** and **Regenerate timetable** now produce previews. Compare the current and proposed lessons, teachers, rooms and free periods. Highlighted proposed blocks have changed. Confirm the exact preview to save; replacement requires a separate checkbox. Server-side tokens expire after 30 minutes and cannot be replayed. Changed scheduling inputs invalidate a preview.
4. Lesson blocks use the shared `schedules` records in both class and teacher views. Two-hour blocks span two hourly rows. Unassigned time says **FREE PERIOD / No Class**, with no subject or teacher. Scheduled student relaxation is a separate entry type. **Cancel lesson** retains history and releases the teacher while preserving the room reservation.
5. Classroom cards and room detail pages display **room occupancy** independently from **current activity**. The subject and teacher come from the current lesson, never from the full-session supervisor. The interval is inclusive at the start and exclusive at the end: at 09:00 a 07:00–09:00 lesson finishes and the 09:00 lesson becomes current. Upcoming lessons show their own teacher and times. Occupancy remains **Occupied** during a free period covered by a reservation.
6. Room details provide daily lessons, a weekly lesson rotation, and a separate reservation timetable. Live cards, room activity and teacher availability poll every 30 seconds while the tab is visible. **Follow current time** resumes live mode after a historical date/time check. Teacher availability reports current/next lessons, teaching, designated free periods, available duty time, off-duty time or inactive status.

## Planning and integrity

`RotationTimetableGenerator` plans one selected class session at a time against all other current commitments. It loads approved curriculum, teachers, qualifications, duty/unavailable periods, teacher free periods, registered physical classrooms, cohort enrollment, room reservations and existing lessons. Most-constrained-first backtracking assigns one-hour or two-hour blocks. It respects session boundaries, workload, room capacity, class/teacher/room overlaps and student relaxation. Existing valid student relaxation times are retained in regeneration. New student relaxation hours use different weekdays. Unused hours stay empty; no teaching demand is invented to fill the session.

Assigned teachers must already be approved for their subjects. For a vacant class subject, the planner can select an active qualified teacher within the two-subject rule. A subject uses the same teacher for all of its proposed lessons. Teachers with no designated free periods receive three random one-hour periods on distinct confirmed working weekdays, avoiding existing commitments. Existing designated teacher periods are retained by class regeneration. Teachers lacking adequate confirmed availability receive a clear validation error. The teacher-only additive generator also respects configured lesson duration and physical reservations.

Both preview and confirmation use the existing singleton scheduling lock. Room booking writes acquire that same lock before their existing resource locks. Confirmation validates the scheduling fingerprint, cancels previous active class entries, creates required teacher free periods, assigns any vacant subjects and inserts the proposed lessons atomically. Errors roll back the entire operation. Cancelled lesson records remain for audit and are excluded from active workload, availability and conflicts. The partial PostgreSQL/SQLite unique index prevents duplicate active class start slots while allowing cancellation history at the same time. All ordinary schedule writes remain subject to the locked validation service; direct SQL imports must follow the same protocol.

The additive migrations add subject lesson duration, class weekly relaxation minutes, optional physical room IDs and cancellation timestamps. No new class identities, teachers or academic years are invented. Existing cohort/session links remain intact. Reversing the history-aware uniqueness migration refuses to destroy history if it would violate the original unique constraint.

## Existing data needing review

Inspection found 30 Khmer teachers, 30 Khmer class identities, 42 physical rooms, 540 reservations and 500 timetable entries. All 550 existing subject records currently lack approved weekly curriculum values. The generator therefore requires curriculum entry before building replacement class timetables. It does not distribute arbitrary example hours.

Some legacy lesson room labels differ from the corresponding physical class-session reservation (for example, `Room 101` versus physical `7A`). Room cards link those historical lessons through their class-session reservation and show a room-link warning. Confirmed new plans use the physical room ID and room number. No historical room string was silently changed. Administrators should review ambiguous room links before approving replacement plans.

Original subject-limit violations also remain: the original teachers have historical assignments exceeding two distinct subjects. Across all programs, 30 original teacher records are affected; the 20 recently added sample Khmer teachers respect the limit. No assignments were removed as part of this upgrade. New scheduling rejects unresolved qualification/subject-limit violations. Grade/section identities without an established legacy enrollment cohort are not duplicated automatically; link the actual cohort and curriculum before generation.

All nine audited school tables retain exactly their pre-upgrade original-column hashes. Only additive schema and migration history changed. The teacher and classroom hero artwork, layouts and theme switcher remain in place. New cards use existing Light/Night/Relax/Cute theme variables. Browser visual, live polling and print preview QA have not been performed in a browser in this environment; PHP rendering, HTTP/authorization tests, lesson-boundary tests and JavaScript syntax checks have been performed.

## Verification

```powershell
php artisan test --compact
php artisan view:cache
php scripts/audit-rotation.php
php scripts/audit-rotation.php --smoke
php scripts/verify-rotation-postgres.php
php scripts/verify-teacher-management-postgres.php
```

PostgreSQL probes use disposable temporary tables and transaction rollback. They do not change school rows or consume school identity sequences. The main test suite uses the isolated in-memory SQLite configuration.
