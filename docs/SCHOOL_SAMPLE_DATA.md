# Fictional Cambodian school dataset

The existing Laravel project now contains complete, runnable seeders, a student factory, profile fields, model calculations, searchable tables, detail pages and CRUD validation. No new project or replacement database was created. The configured database is PostgreSQL; the migration and seeders also run with the SQLite test database.

## Run the code

Open PowerShell in `D:\laravel_projects\com3ms1ms2` and run:

```powershell
php artisan migrate
php artisan db:seed --class=CambodianSchoolSeeder
php artisan school:verify-sample
php artisan test
php artisan view:cache
```

These commands were already run against this project's configured database, except that the automated tests use an isolated in-memory SQLite database. There is no need to insert the sample data again. `php artisan db:seed` also runs the new dataset because it is registered in `database/seeders/DatabaseSeeder.php`.

The aggregate seeder runs all ten component seeders in dependency order inside a database transaction. Use the aggregate command above rather than running component seeders out of order. Do not run `migrate:fresh`, `db:wipe`, or a rollback against your existing school data.

## What was inserted and verified

| Dataset | Requested sample data |
| --- | ---: |
| Students | 100 (`STU001`?`STU100`) |
| Teachers | 20 (`TCH001`?`TCH020`) |
| Guardians | 50 (`PAR001`?`PAR050`), two children each |
| Classes | Grade 7?12, sections A and B; existing matching classes reused |
| Subjects | 10 subject names, assigned to all 12 classes: 120 class subject records |
| Historical attendance | 3,000 records: 100 students ? previous 30 weekdays |
| Today's attendance | 100 extra records when today is a weekday |
| Exams | 72: six subjects ? 12 classes |
| Results | 600: six exams per sample student |
| Fees | 500: five fee categories per student, USD |
| Announcements | 10 with author, audience and publication date |
| Timetable | At least two weekly periods per class subject; existing periods reused |

The live database had 120 students before this work and now has **220 total students**. Existing data means dashboard totals can exceed the requested sample quantities. There are now 16 total classes and 160 total class subject records. The ten subject names are Mathematics, English, Khmer, Physics, Chemistry, Biology, History, Geography, Computer Science and Physical Education. The schema stores one subject row per class assignment, so the dashboard's subject count measures assignments rather than distinct names.

All **4,627 pre-existing school records** were compared with snapshots and remained unchanged. The seed added **208 timetable entries**, and every new entry passed the teacher, classroom and class overlap check. Eight older schedule entries participate in pre-existing overlaps; they remain unchanged and the verification command reports them separately. Existing subject schedules can make the total number of periods exceed two.

Names and addresses are fictional. Phone numbers are illustrative and must not be used for calls or messages. Sample emails use `sample.school.test`. Weekday attendance excludes Saturdays and Sundays; there is no existing holiday-calendar table, so public holidays are not inferred. Attendance for previous days is strictly before today; today's attendance is added separately for the dashboard.

## Safe repeat runs

Run the same aggregate seeder again whenever needed. Stable student numbers, profile identifiers, subject/class pairs and other lookup keys prevent duplicates. Existing records and edited sample values are retained, including passwords. Conflicting sample identifiers stop the entire transaction with a clear error. Dates and sample amounts are only filled when records are first created.

Running on a later day can add attendance for newly covered weekdays. It does not erase older attendance or reset overdue fees. The verifier checks the current rolling 30 weekdays, so run the seeder first when verifying on a later date. Sample insertion is disabled in production.

New teacher and guardian accounts get randomly generated passwords; your existing accounts and passwords are unchanged. Sign in with your existing administrator account. To test a sample guardian or teacher portal, set that account's password through **User accounts ? Edit** first. Creating a parent does not create a student login; the existing student account link remains available on the student form.

## Check the pages

1. Open `/students` and search for `STU001`. Check name, gender, calculated age, class, email, phone, guardian and status. Use sorting, class/status filters and pagination. Open the eye icon to see full details, attendance, grades and fees.
2. Open `/school/teachers`. Subject assignments, qualifications, salary, hire date and status are available. Salary is in USD.
3. Open `/school/parents`. Each sample guardian has two linked children. The detail page links to the children and displays the relationship recorded for each child.
4. Open `/school/classes`, `/school/subjects`, `/school/attendance`, `/school/examinations`, `/school/grades`, `/school/fees`, `/school/schedules` and `/school/announcements`.
5. Each module has search, a sort field, ascending/descending order, pagination, an eye icon and permission-controlled edit/delete actions. Where relevant there are status, attendance date or class teacher filters. Fees and attendance can also be searched by student name or student number.
6. Use an administrator to create a temporary unlinked record, read it, edit it and delete it. Use a linked sample record to check the protective error: students with attendance, fees or grades and other records with dependencies cannot be deleted through the application.
7. Open `/dashboard`: all totals and the enrollment, attendance, monthly payments and gender charts query the database. Present and absent counts are separate. Late attendance is separate in the chart.
8. A parent can see only linked children and their allowed records. Teachers can edit attendance, results and assignments only within their permissions. Students cannot access another student's records.

The automated feature tests exercise these permissions, CRUD, duplicate attendance, fee overpayment, exam mark boundaries, identifier collisions, transaction rollback, repeat seeding, linked-record deletion protection and schedule conflicts.

## Calculations and relationships

- **Age** comes from date of birth; it is not stored as a number that becomes stale.
- **Attendance percentage** is `(present + late) / all recorded days ? 100`. Absent and excused records are included in the denominator. An empty history gives 0%. The student profile labels this policy explicitly.
- **Grade percentage** is `marks / total_marks ? 100`. A is at least 90%, B at least 80%, C at least 70%, D at least 60%, otherwise F. The sample exams are out of 100.
- **Fee balance** is `amount - paid`. Paid means no balance; overdue means a positive balance with a due date before today; partially paid means some paid with a remaining balance and no overdue date; pending means no payment and not overdue. Overdue takes priority over partially paid. Overpayments are rejected.
- **Monthly payments** sum the recorded `paid` amount grouped by `paid_at`. The existing schema stores a running paid total on each fee, not an installment ledger; the chart is not an accounting history of separate payment transactions.
- **Timetables** use weekdays and times within 07:00?11:00 or 13:00?17:00. A new or updated period cannot overlap the same teacher, class or room. Changing a subject teacher/class also checks existing periods. Existing legacy overlaps are reported rather than rewritten.

| Relationship | Existing foreign key / implementation |
| --- | --- |
| Class ? teacher | `school_classes.teacher_id ? users.id` |
| Student ? class | `students.school_class_id ? school_classes.id` |
| Student ? parent | `students.parent_id ? users.id`; `User::children()` supports siblings |
| Student ? login account | Optional `students.user_id ? users.id` |
| Student ? guardian relationship | `students.guardian_relationship`, allowing a relationship per child |
| Subject ? class / teacher | `subjects.school_class_id`, `subjects.teacher_id` |
| Attendance ? student | `attendances.student_id`; unique student/date |
| Exam ? subject ? class | `examinations.subject_id` |
| Result ? student / exam | `grades.student_id`, `grades.examination_id`; unique pair |
| Fee ? student | `fees.student_id`; description holds fee category |
| Schedule ? subject ? class/teacher | `schedules.subject_id`; room and period on schedule |
| Announcement ? author | New nullable `announcements.created_by ? users.id` |

The additive migration leaves existing columns and records in place. Teacher and guardian profiles reuse the current `users` model; no parallel teacher/parent tables were introduced. Application deletion guards protect dependencies before the database's existing cascading deletes could run. Direct SQL deletes still follow the original foreign key rules.

## Exact source files

Complete source code for every file below is in [SCHOOL_SAMPLE_DATA_CODE.md](SCHOOL_SAMPLE_DATA_CODE.md), as well as the actual project files. Paths are relative to `D:\laravel_projects\com3ms1ms2`.

- `database/migrations/2026_10_26_000001_create_school_tables.php`
- `database/migrations/2026_10_26_000002_add_school_profile_details.php`
- `database/factories/StudentFactory.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/CambodianSchoolSeeder.php`
- `database/seeders/CambodianSchoolData.php`
- `database/seeders/TeacherSeeder.php`
- `database/seeders/ClassSeeder.php`
- `database/seeders/SubjectSeeder.php`
- `database/seeders/ParentSeeder.php`
- `database/seeders/StudentSeeder.php`
- `database/seeders/AttendanceSeeder.php`
- `database/seeders/ExaminationSeeder.php`
- `database/seeders/PaymentSeeder.php`
- `database/seeders/TimetableSeeder.php`
- `database/seeders/AnnouncementSeeder.php`
- `app/Models/User.php`
- `app/Models/Student.php`
- `app/Models/SchoolClass.php`
- `app/Models/Subject.php`
- `app/Models/Attendance.php`
- `app/Models/Examination.php`
- `app/Models/Grade.php`
- `app/Models/Fee.php`
- `app/Models/Schedule.php`
- `app/Models/Announcement.php`
- `app/Support/SchoolModules.php`
- `app/Support/SchoolDependencies.php`
- `app/Support/TimetableConflicts.php`
- `app/Support/SampleSchoolVerifier.php`
- `app/Http/Controllers/StudentController.php`
- `app/Http/Controllers/SchoolModuleController.php`
- `app/Http/Controllers/DashboardController.php`
- `routes/web.php`
- `routes/console.php`
- `resources/views/school/students/index.blade.php`
- `resources/views/school/students/form.blade.php`
- `resources/views/school/students/show.blade.php`
- `resources/views/school/modules/index.blade.php`
- `resources/views/school/modules/form.blade.php`
- `resources/views/school/modules/show.blade.php`
- `resources/views/school/modules/value.blade.php`
- `resources/views/school/dashboard.blade.php`
- `tests/Feature/CambodianSchoolDataTest.php`
- `tests/Feature/SchoolTest.php`

## Existing foreign key behavior

The original school migration already enforces unique student numbers and emails, class/section pairs, subject codes, attendance student/date pairs and result student/exam pairs. Class/subject references use restrictive foreign keys where defined, while some student-dependent records originally use cascading deletion. The new application guards stop linked records being deleted accidentally through controllers; the existing foreign keys are preserved. The new announcement author reference uses a restrictive foreign key.

## Recheck the preservation audit

The local audit helper and hashes are stored under `storage/app/qa/`. To compare the current database with the snapshot taken just before sample insertion:

```powershell
php storage/app/qa/preserve-school-data.php verify
```

This is a read-only comparison, including a conflict check for every timetable entry added since that snapshot. It will report changes if you intentionally edit an original record afterward; that is expected. Do not replace the snapshot if you want to keep the original comparison baseline.

## AJAX dashboard refresh

`GET /dashboard/data` is an authenticated, non-cached JSON endpoint. It shares its database queries and Blade content with the initial dashboard page. `public/js/dashboard.js` refreshes the content and Chart.js charts every 30 seconds, on tab return and through the Refresh button. It retains the last loaded content if a request fails and explains expired sessions. Refreshing performs no data writes.

School administrators see all school records. Teachers, parents and students see their assigned or linked records, with matching card labels. An unlinked account displays a linking notice rather than implying the database is empty. Link students through Students ? Edit ? Linked student account / Linked parent account, or assign a teacher through Classes/Subjects ? Edit. No account roles or relationships are automatically changed.

The pending-fees calculation uses the sum of amounts minus the sum paid; this avoids an aggregate alias collision with the Fee model's calculated balance accessor. Recent registrations also use ID as a tie-breaker for records created in the same second.

A read-only live comparison is available with `php storage/app/qa/dashboard-data-check.php`. The verified administrator figures were 220 students, 30 teachers, 16 classes, 160 class subject records and $73,660 outstanding. The complete test suite passes 17 tests and 484 assertions, including AJAX database changes, role isolation, linking and fee recalculation.
