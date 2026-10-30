# School-wide room assignment repair — 30 October 2026

The new request authorizes automatic room-reference repairs across Khmer, English and Chinese when the existing physical room or explicit reservation provides a unique, valid, conflict-free match. It supersedes the earlier requirement to confirm every room correction individually. Ambiguous room changes, competing commitments and changes outside reservations still require review and confirmation.

## Inspection and exact causes

Checked 54 class identities (30 Khmer, 12 English, 12 Chinese), 128 existing cohorts, 500 lesson records including history and 491 active lessons. The physical room table already contained 42 rooms. Initially all 500 lesson records lacked physical classroom IDs; many used legacy labels that differ from the existing physical room number.

The resolver compares physical room number/name aliases and explicit class-identity/session reservation relationships. It never equates class names with room names. Existing valid room IDs remain authoritative. It checks operational status, cohort capacity, overlapping reservations and all proposed/confirmed room commitments together, using half-open intervals and compatible academic years. Unknown legacy academic years remain potentially overlapping until resolved. Cancelled lessons do not occupy rooms.

The old room-ID-only overlap count was misleading: unlinked legacy rows had no room ID to compare. The new report identifies 138 overlapping candidate-room pairs that require review, including evening lessons extending beyond their actual reservations. These are inferred conflicts, not claims that the corresponding room assignments have already been confirmed.

## Applied corrections

* Full PostgreSQL recovery backup: `storage/app/qa/school-before-khmer-20261030-222631.dump`.
* Added an academic-year-aware active-slot unique index. No school records were deleted or recreated.
* Repaired 220 references on the original lesson IDs: 200 Khmer records and 20 English records. No Chinese reference passed the automatic-repair checks.
* Preserved every subject, teacher, student, cohort, room, reservation, lesson time, academic year and cancellation state. Only the recorded `classroom_id`, canonical room label and corresponding update timestamp changed. Each correction has an audit row and private before-change snapshot.
* Detailed repair audit: `storage/app/private/room-repairs/school-room-repaired-20261030-223008.json`.

`php scripts/verify-school-room-repairs.php` passed against all 14 backed-up school tables and verifies that the corrected IDs match their exact approved candidates. Before/after snapshots are private under `storage/app/private`.

## Remaining issues and administrator review

The school-wide validation report remains **blocked**:

| Finding | Count |
| --- | ---: |
| Remaining classroom assignment issues | 280 |
| Teacher overlap pairs | 0 |
| Student cohort overlap pairs | 0 |
| Overlapping proposed room pairs | 138 |
| Proven missing required subject allocations | 0 |
| Unknown curriculum/cohort subject configurations | 579 |
| Invalid active lessons | 427 |
| Reservations without established student cohorts | 140 |
| Chinese identities without an explicit second-evening session window | 12 |

Zero proven missing curriculum hours does **not** establish completeness: the weekly requirements are unknown. No hours or subjects were invented to fill empty periods. Remaining room findings overlap: 100 lessons exceed reservation times and 276 participate in candidate-room conflicts; these counts must not be added as distinct lessons.

The database currently records Chinese class-session windows at 17:30–18:30. The code now supports actual configured 18:30–19:30 windows in booking validation, lesson validation, teacher free periods and weekly grids. It does not shift existing valid sessions, invent another student cohort, or shorten existing two-hour legacy lessons to fit a one-hour reservation.

Open **School Timetables → Review physical room assignments → Complete school validation report** (`/school/timetables/school-audit`). Every unresolved finding includes its program, class/cohort, weekday, interval, teacher and known/proposed physical room. Use **Fix Room** to choose an available room; a change from the cohort's reservation requires an explicit reason visible in the confirmation preview. Reserved rooms remain unavailable to other cohorts. Times outside reservations must be reviewed rather than silently clipped.

Readable report: `storage/app/private/room-repairs/school-validation-20261030-223145.md`. Detailed JSON: the matching `.json` file. Reports can be regenerated with `php scripts/export-school-timetable-audit.php`.

## Connected views and layout

Student, teacher and classroom tabs continue to read one shared `schedules` record. Automatic repair writes that existing record, so the warning disappears in each view when its actual cause is fixed. Specific unresolved warnings remain visible. Academic-year filters prevent future-year lessons from appearing as current commitments; legacy unknown-year lessons remain included conservatively. Current classroom activity and teacher availability also filter the relevant year.

The existing row-span grid displays a two-hour lesson once, spanning its full interval. Off-grid legacy records are shown in a separate review section instead of being clipped or hidden. Both Chinese evening hour rows are supported. Incomplete/unknown curriculum is explicitly distinguished from an intentionally designated teacher free period or a saved student relaxation period. Reservations retain **View Lessons** details. Scholar's layouts, hero artwork, navigation, classroom cards and theme variables remain in place.

## Validation

The regression suite and focused room tests cover all three programs, unique-label matching, explicit-reservation matching, ambiguous names, competing proposals, confirmed ID preservation, academic-year separation, the 19:30 Chinese boundary, shared lesson IDs, approved room-change reasons and administrator-only audit access. PostgreSQL and page-render checks are recorded in the final task result. Browser interaction and visual verification of all four themes remain unperformed.

The full regression run passed **165 tests (2,661 assertions)**. Final focused checks passed **37 tests (419 assertions)** after correcting grade/session selection and academic-year-aware available-option filtering. All seven PostgreSQL page renders, Blade compilation, PostgreSQL uniqueness/concurrency probes and the final 14-table preservation verification passed. Grade filters now cover Grades 1–12 and cannot keep an unrelated previously selected cohort. Switching the three tabs retains the selected academic year.
