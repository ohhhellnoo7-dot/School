# Scholar School Management

Laravel 13.38, PHP 8.4 (the installed dependency lock), Blade, Bootstrap 5.3, Bootstrap Icons, and Chart.js 4.5. Core frontend assets are included under `public/vendor`, so Node/npm is not required to run the school portal. The optional Google font request falls back to system fonts offline.

## Current local installation

The existing PostgreSQL connection in `.env` is retained, as requested: `127.0.0.1:5432`, database `com3ms1ms2`. School tables are added through a migration; the existing customer module is retained and now restricted to administrators. No existing tables are reset.

```powershell
composer install
php artisan migrate
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Open **http://127.0.0.1:8000/login**.

For a fresh installation, copy `.env.example` to `.env` and run `php artisan key:generate` before migrating. Do not replace an existing application key.

## MySQL / XAMPP alternative

1. Start Apache and MySQL in XAMPP. This machine's `C:\xampp\mysql\bin\my.ini` uses **3308**. Most XAMPP installations use 3306.
2. Open phpMyAdmin and create a new `school_management` database with `utf8mb4_unicode_ci` collation.
3. Set these values in `.env`, using the credentials for your MySQL installation:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3308
DB_DATABASE=school_management
DB_USERNAME=root
DB_PASSWORD=
```

4. Run `php artisan config:clear`, `php artisan migrate`, and `php artisan storage:link`.
5. Use `php artisan serve`, or configure an Apache virtual host whose DocumentRoot points to this project's **public** directory. Never serve the project root. Laravel 13 requires PHP 8.3+, while the installed dependency lock requires PHP 8.4; older XAMPP PHP distributions need upgrading before running `composer install`.

The migrations support PostgreSQL and MySQL. Automated tests use an isolated SQLite in-memory database and do not reset the configured local database.

## Demo accounts

For local development only:

```powershell
php artisan db:seed --class=SchoolSeeder
```

| Role | Email | Password |
| --- | --- | --- |
| Administrator | admin@school.test | School@2026! |
| Teacher | teacher@school.test | School@2026! |
| Student | student@school.test | School@2026! |
| Parent | parent@school.test | School@2026! |

The seeder creates 24 students, four classes, 12 subjects, schedules, exams/results, attendance, fees, and announcements. It does not overwrite existing accounts or school records. The sample parent is linked to Emma Parker, as is the sample student account. The seeder refuses to run in production. Remove demo accounts/data before using the database with real student information.

For a larger local dataset, run `php artisan db:seed --class=ExpandedSchoolSeeder`. This adds 96 students with student accounts, 48 linked parent accounts, eight teachers, eight classes, 48 subjects, weekday schedules, attendance, results, assignments, fees, and announcements. Existing records are preserved. Expanded sample logins include `demo.student001@school.test`, `demo.parent1@school.test`, and `demo.teacher1@school.test`, all with password `School@2026!`. Keep these sample accounts limited to local development.

To create a new administrator without demo data:

```powershell
php artisan school:admin office@example.edu --name="School Administrator"
```

The command prompts privately for the password and its confirmation. It creates a new account and does not promote or overwrite existing accounts.

## Features and access

- **Administrator:** all student, teacher, class, subject, attendance, examination, grade, fee, assignment, timetable, announcement and user-account CRUD; aggregate charts; printable reports.
- **Teacher:** assigned classes and subjects, student information in assigned classes, attendance, results for assigned subjects, assignments, and teaching timetable. Administrative data and other teachers' assigned records are protected on the server.
- **Student:** own linked profile, attendance, results, class timetable, assignments, and published announcements.
- **Parent:** linked children, their attendance/results/fees, and published announcements.

Administrators create teacher and parent accounts under **User accounts**, then link students through the student form. A teacher must be assigned to a class or subject to access those students. A student account can link to only one student record. Public registration always creates an unlinked student account, regardless of submitted role fields. Until the office links it, its dashboard shows empty states.

Students support search, class/status filters, pagination, age derived from date of birth, profile-photo validation, and guardian contact information. Photo uploads use the public storage disk. Deleting a student also removes associated attendance, grades, fees and the uploaded photo; the confirmation states this explicitly.

Attendance allows one entry per student per date. Results require an examination for the student's current class and marks within its maximum. Fees enforce nonnegative amounts and prohibit overpayment. Referenced classes/subjects cannot be deleted while dependent students/exams exist. Administrative self-deletion/demotion is blocked. Account roles cannot change until their student/parent/teaching links are removed.

Fees are **manual records in USD**, not an online payment gateway or accounting ledger. Monthly revenue attributes each fee's total `paid` amount to its `paid_at` date; it is not a transaction-by-transaction payment history. Enrollment charts show enrollment dates in the last six months, attendance charts show today's recorded entries, and gender charts use only the current user's visible students. Unrecorded attendance is not counted as an absence.

## Validation

```powershell
php artisan test
php artisan view:cache
php vendor/bin/pint --test
```

Feature tests cover auth, privilege escalation, role-specific pages, student CRUD/uploads, record isolation, teacher restrictions, duplicate attendance, marks validation, fee overpayment, and administrator self-lockout.

An optional Windows Edge browser check runs with `python scripts/browser-check.py` while the app is served on port 8000. It uses the local demo administrator and writes desktop/mobile screenshots under `storage/app/qa`. It verifies chart initialization, JavaScript errors, responsive width, and mobile navigation. Run it only against a local demo installation.

## Deployment configuration

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the HTTPS URL, and `SESSION_SECURE_COOKIE=true` for HTTPS. Configure database credentials privately in `.env`, set the application timezone, keep `storage` and `bootstrap/cache` writable, and run `php artisan optimize`. No third-party identity provider or email-based password recovery is configured; the school administrator can reset an account password using the account edit screen.
