---
name: release-bundle-time
description: A release bundle's publish time: the general job's pass, publishing once in the name of who set the time, a bundle held back to open with its reason, the bell line
module: page_bundles
---

## What does the general job do with a bundle's time?
- `pg_page_bundle_job_run()` in `job.php`, every tick: scheduled bundles with `publish_at` due, 5 a tick, the oldest time first. With none due it is one indexed read (`idx_status_publish`).
- The time is cleared first (`publish_at = 0` with the time read in the WHERE), so two overlapping runs never publish one bundle and nothing is retried by itself.
- Then `pg_page_bundle_publish()` with no choices: the same checks and writes as a person's publish (`release-bundle-publish.md`). A page changed since needs a person's choice, so it holds the bundle.

## In whose name?
- The person who set the time (`scheduled_by`), read again from `user` and only while still role 0 or 1 (`pg_page_bundle_time_person()`); otherwise nothing is written and the reason says so.
- The page save logs through `$_SESSION['sessionusername']`, so the pass sets it to that person for the publish and gives the old value back, as `api_design_save_as_owner()` does. The job's own lines ("release bundle (…) was published / was not published at its scheduled time") are `UNKNOWN`.
- `pg_recycle_page()` asks `pg_folder_edit_access()` of the user it is given, not `check_edit_access()` of the signed-in visitor: the job has nobody signed in, and the used replacements must still reach the Recycle Bin. Its callers (`edit_page.php`, `edit_pages.php`) pass the signed-in user, so their answer is the same.

## What happens when it cannot go out whole?
- Held by the checks (a page open in the editor, changed since, refused) or a failure taken back: the bundle moves back to `open` with the time and `scheduled_by` cleared. A held bundle gets the pages named in `failed_reason` (`pg_page_bundle_time_held_reason()`); a failure keeps the save's answer the publish wrote.
- Partly published (a take-back failed): it stays `partial`, as from a person's publish.
- Either way a bell line `bundle_held` (module Content) goes to who set the time and who made the bundle, each once (`pg_page_bundle_time_people()`). The row keeps the bundle's name and id; `pg_page_bundle_bell_display()` reads the reason from the bundle when drawn and opens `page_bundles.php?id=N`.

## Which test guards it?
- `tests/page_bundles_time_test.php`; the bell's module in `tests/bell_row_test.php`.
