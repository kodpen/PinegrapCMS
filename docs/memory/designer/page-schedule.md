---
name: page-schedule
description: Scheduled publishing of visual-designer pages (page.publish_at/unpublish_at, 11.0.17): what a save does with the two times, the general job's pass, manual moves, the designs list badge, editor fields
---

## What is stored?
- `page.publish_at` (a draft goes on the site) and `page.unpublish_at` (a page on the site goes back to the drafts): unix times, 0 for none, each indexed (2026.11.0, 11.0.17). Entered as the site's wall clock (`datetime-local`, PHP default zone = site zone); `pg_page_schedule_parse()` also takes digits and ISO with offset/Z, refuses a date that does not exist. Module `includes/fn/page_schedule.php`; needs the drafts schema (`pg_page_draft_ready()`).

## What does a save do with them?
- `pg_designer_save_page()` calls `pg_page_schedule_save_plan()` next to the draft plan, before the dry-run return, so a bad time refuses the whole multi-page save. Keys `page_publish_at` / `page_unpublish_at`; a key not sent keeps the stored time. Rules in `pg_page_schedule_plan()` (pure, `tests/page_schedule_test.php`): end not after start → error; home page → both dropped with a warning; publish time ahead → draft; a time that has passed is carried out by the save and cleared, with a warning when it changed the state.
- Why carry out a passed time: an editor left open across the job's move sends the old draft state back; reading the stale passed time as "do it" keeps the state the job set.
- A content-level save (restricted) leaves both columns alone, like the draft state. A schedule-only change writes no revision (the snapshot has no times). The save result and the editor's id map carry the times back (`publish_at`, `unpublish_at` as datetime-local text).

## What does the general job do?
- `pg_page_schedule_run()` on every tick of `job.php` (interval 60 s): due `publish_at` rows first, then due `unpublish_at`, 100 each, binned pages skipped. The column is cleared with `WHERE … AND col = <time read>` and the move happens only when that UPDATE touched one row, so overlapping runs move a page once. A page already in place only loses the time; the home page stays on the site (own log line). Activity lines say "at its scheduled time" (user `UNKNOWN`).

## How do manual moves interact?
- One move for everyone: `pg_page_draft_move()` (includes/fn/designer.php), used by the designs list (`pg_designer_design_set_draft()`) and the job. Put on the site clears `publish_at`; taken off clears `unpublish_at`. The editor's Publish / take-off menu does the same on the tab before saving.
- `duplicate_style.php` copies page rows column by column; the copy gets 0 for both times, so a copied design does not go live beside the original.

## Where is it shown?
- Page Settings, section Schedule (`#sd-page-schedule-group`, designer level only): two boxes, a status line, the site zone. A publish time ahead checks and locks the Draft switch. Tab badge: a clock pill with the next change as title (`_pgScheduleNote()`); the editor compares times as text against the site clock from `drafts.schedule.offset`.
- Designs list: "On a schedule" badge beside the state plus "{n} scheduled, next change …" (`pg_page_schedule_design_counts()`); chips of scheduled pages get a clock and a title (`data-scheduled`, repainted by `designer_home.js` from `design_publish`'s `scheduled` map). The existing `Scheduled` key means "Kuyrukta" (mail queue), hence the separate key.

Related: docs/memory/designer/page-revisions.md
