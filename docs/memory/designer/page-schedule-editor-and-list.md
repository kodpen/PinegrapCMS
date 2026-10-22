---
name: page-schedule-editor-and-list
description: Page schedule in the designer: Page Settings boxes and the Draft switch (forced state vs own choice), tab badge, where a tab takes the stored times, designs list badge and chips
---

## Where is it shown?
- Page Settings, section Schedule (`#sd-page-schedule-group`, designer level only): two boxes, a status line, the site zone. A publish time ahead checks and locks the Draft switch; the page's own choice is kept apart (`p._draftByChoice`, `_pgSyncDraftBox()`) and comes back when the time is cleared; a locked switch is never read as a choice (`_pgReadFieldsIntoPage()`); a menu move or a saved state replaces the choice (#824: a time typed and cleared on a live page left the switch on, and the next save took the page off the site unasked). Tab badge: a clock pill with the next change as title (`_pgScheduleNote()`); the editor compares times as text against the site clock from `drafts.schedule.offset`.
- Designs list: "On a schedule" badge beside the state plus "{n} scheduled, next change …" (`pg_page_schedule_design_counts()`); chips of scheduled pages get a clock and a title (`data-scheduled`, repainted by `designer_home.js` from `design_publish`'s `scheduled` map). The existing `Scheduled` key means "Kuyrukta" (mail queue), hence the separate key.

## Where do the boxes get the stored times?
- The server sends `page_publish_at` / `page_unpublish_at` with every page (`pg_designer_load_pages()`); a tab takes them in `_pgTabsInit()` (editor opened) and `_pgTabsAttachLoaded()` (page of another design). Until #823 neither did: after a reopen the boxes and badge were empty and the page's first save sent `''`, which the save plan reads as "no time" and stored 0 (a scheduled publish lost; a stale editor put a page the job had taken off back on the site). `tests/designer_page_tabs_test.php` checks both take every `_PG_PAGE_FIELDS` key.

Related: docs/memory/designer/page-schedule.md
