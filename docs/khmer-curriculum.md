# Provisional Khmer curriculum

The school supplied the subject lists. They have not been verified against official Cambodian Ministry of Education requirements. Every profile remains `provisional`; saving a timetable does not approve the national curriculum.

## Administration

Open **Timetables - Khmer subjects and study streams**, or `/school/khmer-curriculum`. Profiles are stored in PostgreSQL in `curriculum_profiles` and `curriculum_requirements`. Administrators can edit subject names and weekly hours. A full session must total 20 hours. Khmer Literature and Mathematics require whole two-hour blocks; other subjects use one-hour lessons.

Initial subject counts: Grade 7: 10; Grade 8: 11; Grade 9: 10; Grade 10: 12; Grade 11: 8 per stream; Grade 12: 7 per stream. The initial allocations are Scholar defaults rather than national requirements. Both Morning (07:00-11:00) and Afternoon (13:00-17:00) run Monday-Friday.

Edit an existing Grade 11/12 class in Classes to change its stream. A/B/C default to Social Science and D/E to Science, but an administrator can explicitly choose either stream without creating a second identity. Existing subjects, student enrollment, marks and attendance remain linked. Legacy subjects outside the current profile have a zero teaching target and are retained for history.

Aliases `Khmer` - `Khmer Literature`, and `Computer Science`/`Computer Studies` - `ICT`, are normalized consistently with existing Khmer qualification records. This adds no Civics or Earth Science qualifications to real staff.

## Planned staffing and enrollment

The authorized setup added four plainly named planned positions: two Civics and two Earth Science teachers. Their origin is `generated_unverified`, with no employee confirmation. They have provisional planning availability and workload limits. Replace these records with actual staff and verified availability before real school use. No employee identity verification or Ministry curriculum approval is implied.

Saving an active Khmer teacher through Teacher Management automatically assigns matching unassigned curriculum subjects within the workload budget. If the timetable is already complete, a newly enrolled teacher can receive a qualified, available existing subject's entire timetable without moving its lesson times or emptying the donor's timetable. If no safe assignment exists, existing lessons remain unchanged. Teacher availability, breaks, qualifications and workload are checked before any existing lessons are reassigned.

## Timetable workflow

The administrator can generate a complete preview and publish it with its preview token. The CP-SAT solver uses exact database targets and confirmed room reservations. It enforces one teacher per class subject, teacher/room/cohort conflicts, availability, breaks, workload and subject limits, and full 20-hour class weeks. Every eligible teacher gets an assignment when feasible. A school-data fingerprint invalidates stale previews. Publication revalidates all rows under a database lock and transaction, backs up the data, and retains old lessons as cancelled history. Other academic years and non-Khmer lessons are preserved.

Python must be available on PATH, or set `CURRICULUM_PYTHON` to its executable. Install the solver dependency with:

```powershell
python -m pip install -r scripts/requirements-curriculum.txt --target .tmp/khmer-solver
```

For an existing installation:

```powershell
php artisan migrate
php artisan db:seed --class=KhmerCurriculumSeeder
php scripts/configure-provisional-khmer-curriculum.php
```

The synchronization script backs up data and verifies that student records, enrollment, marks, attendance and existing timetable rows remain unchanged. Scheduling is a separate explicit operation. The operational export/solve/publish scripts are also available under `scripts/*provisional*khmer*`; prefer the administrator preview flow for future changes.
