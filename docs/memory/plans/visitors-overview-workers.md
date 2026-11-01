---
name: visitors-overview-workers
description: How two coordinators' workers take plans/visitors-overview.md: the waves, which steps run side by side, what a section step owns and may edit, what its PR shows, who reviews
---

## Which steps run side by side?
- Wave 1 (now): V1, V2 and V3, three workers on different files (`visitors.php` with the shell and period; `visitor_figures.php` with the API resource; `visitor_retention.php` with the saved-report condition).
- Wave 2, as each lands: V13 and V15 after V3; V4 after V2 and V3; V5, V6, V8 and V11 after V1 and V2; V9 after V1 alone; V7 after V4; V10 after V2 and V9; V12 after V5, V6 and V9. With V1-V3 in, eleven steps are open at once; a coordinator keeps at most six (Working mode 8), so two coordinators cover the wave.
- Wave 3: V14 after V2, V4 and V10; V16 alone, after everything.
- The two coordinators split the open steps by number, odd and even, and take the other's leftovers when their own are gone; a step is taken only when no draft holds it (Claim work, step 1). No free step: `coordinator-no-plans.md`.

## What does a step own, and what may it not touch?
- A section step: `includes/visitors/<name>.php`, its labelled block in `assets/css/pg_visitors.src.css` (and the rebuilt `.min.css`), `tests/visitors_<name>_test.php`, `visitors/visitors-<name>.md`, its own step line. Nothing else: no edit of `visitors.php`, `shell.php`, `pg_visitors.src.js`, the figures module or another section. A helper it needs lives in its own file; one that two sections need is moved into `shell.php` or `visitor_figures.php` by the second PR, as a small edit named in its body; a clash with an open step goes to the architect (Working mode 10).
- V1, V2, V3 and V4 own the shared files their lines name; after they land those files take only the small edits above.
- Texts through `php tools/tr_add.php`; every printed value through `h()`; numbers `pg_format_number()`, money `prepare_amount()`; markup in Bootstrap 6 checked by `php tools/check_bs6.php --strict`; panel CSS in `@layer custom` on `--pg-*` tokens (`ui/panel-css-tokens.md`).

## What does the PR show?
- What the section shows for today, 7 days and a month, and with no data; the browser try at 1280 and 390 px in light and dark on a sandbox (`workflow/sandbox-and-environment.md`, `ui/panel-screenshots.md`); the test file; the memory topic. Under "Could not verify": a site whose summary is not written yet, a site with retention off, a site not linked to Search Console, whichever the sandbox could not be.
- A figure the PR shows is checked once against the saved-report screen for the same window (the two must agree to the visit).

## Who reviews?
- The merging session, as every PR (`workflow/merge-review.md`); no "Ready for review" wait. The owner looks at the screen on the dev site once V5 is in; a change of behaviour he asks for comes as a decision issue, never as a comment a worker acts on alone.
