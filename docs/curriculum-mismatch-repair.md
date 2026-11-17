# Curriculum mismatch repair — 16 November 2026

The 2026–2027 timetable audit now passes for 129 scheduled class sessions: 60 Khmer, 24 Chinese, 36 English and 9 Japan. Across 1,750 active lessons, no weekly curriculum mismatches or teacher, cohort or room overlaps remain.

The Khmer repair follows existing curriculum requirements without changing their weekly targets. The solver preserved 865 lesson IDs, retired 95 lessons into cancellation history and added 115 lessons. It checked qualifications, working periods, workload limits, subject limits, lesson blocks, room capacity and reservations before publication. Finding a feasible plan required allowing some lessons in previously balanced classes to move because teachers are shared across classes.

Physical Education required 50 weekly teaching hours against the existing qualified teacher's 40-hour limit. Following the user's authorization to resolve the shortage using our judgment, one explicitly unverified position, `Planned Physical Education Teacher 1` (ID 1387), was added using the existing planned-staff convention. This is a staffing placeholder requiring a real teacher, not a verified employee. The timetable remains provisional and also retains the four existing planned Civics and Earth Science positions.

The Chinese A/B cohorts had already been separated by the confirmed policy in `scripts/separate-chinese-evening-cohorts.php`: A attends 17:30–18:30 and B attends 18:30–19:30. Their subject requirements had incorrectly retained the former shared two-hour totals. The repair changed 120 subject requirements across 24 classes from 120 to 60 minutes weekly, after checking the exact weekday subject and time pattern. No Chinese lesson times were changed. English and Japan records were unchanged.

Reports and backups are on the local storage disk:

- `timetable-drafts/khmer-curriculum-repair-published-20261116-214814.json`
- `timetable-backups/20261116-214813-17208254-af28-4a0e-a051-d1d28ef302cb.json`
- `timetable-drafts/chinese-split-curriculum-repair-20261116-214907.json`
- `timetable-backups/20261116-214907-900a20ba-c1f0-44ad-8885-97d5f093f71d.json`
- `timetable-drafts/curriculum-repair-verification.json`

Run `php scripts/verify-curriculum-repair.php` to repeat the live read-only verification. Publisher tests cover preserving lesson IDs, idempotent repairs, stale-plan rejection and conflict rejection.
