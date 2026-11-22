---
name: bug-hunt-areas-backend-sweep-2
description: Second pass of the owner's whole-backend sweep of 2026-11-22: a role without manage rights, every edit screen saved unchanged, phone dialogs, PHPStan level 2; what it fixed, what read clean
---

## What did the second pass add?
- The same sandbox and crawl as the first pass (#11051, which fixed what it found), now signed in as a role 3 user with no manage permission, and as `admin` at 768 px; the 32 edit screens with a real record each saved unchanged; the dialogs of the crawled screens opened at 390 px; PHPStan level 2 read for argument and method faults.

## What did it find and fix?
- The starter `AT-ONCE` arrival date ("En kısa sürede") could not be saved from its screen, the list's quick edit or the API; a blank arrival date is now as soon as possible (#11053, `commerce/arrival-date-blank-as-soon-as-possible.md`).

## What read clean, or is no fault?
- Role 3: every admin screen refused or sent the user to `/my-account`, the ERP gate held; no screen showed admin content.
- Saving unchanged: every other edit screen landed where it should, with no PHP warning, script error or new line in `data/temp/php_errors.log`.
- 390 px: 46 dialogs, drawers and menus opened with no script error and nothing past the screen.
- 768 px: the 334 crawled screens, no page scrolls sideways and no new script error.
- `my_reviews.php` answers 403 to a user who reviews no application programme: its own refusal.
- `db_q()` placeholders against their parameters, the one-argument helpers (`h()`, `e()`, `escape_*()`, `lang()`), money cast with `(int)`: no fault.
- PHPStan level 2: `liveform::remove_form()` / `remove()` called with a form name ignore it and remove the instance's own form; read at each call site, that is the form meant or the screen's own after its output. The closures it counts are false positives; the rest is harmless legacy.
- `edit_system_style.php`: Save is greyed while another session holds the style (the edit lock); not a fault.
- The sign-in page's CSP reports (report-only) come from starter theme content: flexslider from cdnjs, Google Fonts over http in the sandbox.
- `product_builder.php` sits at the root but only defines functions for the product screens; opened by URL it answers an empty page: no data, no fault.
- The firewall answers 429 to a fast crawl as a non-admin user: slow the crawl.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/ui/panel-screenshots.md
