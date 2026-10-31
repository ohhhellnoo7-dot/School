# Scholar interface and themes

The application uses the existing Laravel Blade layouts, routes, permissions and live data. The redesign does not change database schemas, records, scheduling rules, attendance calculations or payment calculations.

## Shared files

- `public/css/scholar-design.css`: the eight palettes, shared semantic tokens, shell, dashboard, tables, forms, timetables, menus, modals and responsive rules.
- `public/js/theme.js`: applies the theme before the body renders, persists the existing `scholar-theme` preference, synchronizes tabs, and provides theme-menu keyboard controls.
- `public/js/scholar-ui.js`: sidebar collapse, navigation context, avatar fallback and shared form confirmations.
- `resources/views/school/layout.blade.php`: existing permission-aware navigation, search, profile and language controls.
- `resources/views/school/partials/theme-picker.blade.php`: accessible theme names and color previews.
- `resources/views/school/partials/confirm-dialog.blade.php`: one shared confirmation dialog for existing `form[data-confirm]` actions.

The old design stylesheets remain available in the repository, but `theme-head.blade.php` no longer loads the overlapping theme stacks. Existing page-specific structural styles remain loaded. A centralized compatibility section supplies their established variable names, including timetable, classroom, chart and status tokens.

## Themes

| Theme | Character | Primary | Sidebar |
| --- | --- | --- | --- |
| Light | White surfaces and restrained blue | `#2563EB` | `#FFFFFF` |
| Night | Deep navy and soft blue highlights | `#60A5FA` | `#111C30` |
| Relax | Sage and generous rounded surfaces | `#27846A` | `#E4F1E8` |
| Cute | Pink, lavender and soft corners | `#DB5BA5` | `#FCE7F3` |
| Ocean | Coastal blue and aqua | `#0284C7` | `#073B62` |
| Sunset | Cream, peach and plum | `#E87945` | `#653B55` |
| Luxury | Charcoal, champagne and restrained geometry | `#C9A45C` | `#171C22` |
| Galaxy | Violet surfaces and cyan highlights | `#9B7BFF` | `#171433` |

Each palette specifies surfaces, text, muted text, border, sidebar, hero overlay, semantic statuses and corner radii. Use `--action` and `--on-action` for readable button combinations; `--primary` preserves the requested identity color. Lighter identity colors use a deeper action variant where necessary for contrast.

Use semantic properties such as `--surface`, `--ink`, `--muted`, `--border`, `--action`, `--success-soft` and `--danger-soft` in page components. Do not add per-page theme hex values. The theme swatches intentionally show palette colors.

## Interaction and accessibility

- Theme selection supports mouse, touch, Tab, arrow keys, Home, End and Escape. Preferences apply before rendering and persist after reload.
- The desktop sidebar collapse preference uses `scholar-sidebar-collapsed`. Mobile navigation retains Bootstrap's offcanvas focus handling.
- Existing form methods, CSRF tokens and submitters are retained by the confirmation dialog. Cancel does not submit the form.
- Shared transitions run for approximately 180–200 ms and respect `prefers-reduced-motion`.
- Timetables and wide tables scroll inside their containers; dropdowns can extend outside filter panels.
- Original school and directory illustrations remain in place. Directory overlays keep embedded image lettering behind an opaque text area.

## Verification

Browser QA used read-only rendered snapshots from the real application: dashboard, student directory/form, parents, teachers, classes, subjects, attendance, examinations, fees, reports, profile, teacher/student/classroom timetables and classroom availability.

The eight themes were checked at desktop and true 390-pixel mobile viewport sizes, including shared color contrast, horizontal overflow, theme keyboard behavior, mobile navigation, sidebar collapse and theme persistence across reload. Session dropdown visibility and the confirmation dialog were also checked. Business-table hashes before and after rendering were unchanged.

The full Laravel suite ran with 195 passing tests and three failures caused by attendance fixtures using `today()` on Saturday, October 31, 2026. Those three tests passed separately with the clock set to Monday, November 2, 2026. Neither the attendance rules nor the test files were changed for this frontend task.

Browser screenshots and private snapshot helpers are kept in the ignored `.tmp/ui-preview/` directory. The static QA server exposes no authenticated application routes and is stopped after checking.
