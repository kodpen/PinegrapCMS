---
name: page-schedule-editor-and-list
description: Where a page's schedule is shown: Page Settings boxes and the Draft switch (the forced state vs the page's own choice), the tab badge, the designs list badge and chips
---

## Where is it shown?
- Page Settings, section Schedule (`#sd-page-schedule-group`, designer level only): two boxes, a status line, the site zone. A publish time ahead checks and locks the Draft switch; the page's own choice is kept apart (`p._draftByChoice`, `_pgSyncDraftBox()`) and comes back when the time is cleared; a locked switch is never read as a choice (`_pgReadFieldsIntoPage()`); a menu move or a saved state replaces the choice (#824: a time typed and cleared on a live page left the switch on, and the next save took the page off the site unasked). Tab badge: a clock pill with the next change as title (`_pgScheduleNote()`); the editor compares times as text against the site clock from `drafts.schedule.offset`.
- Designs list: "On a schedule" badge beside the state plus "{n} scheduled, next change …" (`pg_page_schedule_design_counts()`); chips of scheduled pages get a clock and a title (`data-scheduled`, repainted by `designer_home.js` from `design_publish`'s `scheduled` map). The existing `Scheduled` key means "Kuyrukta" (mail queue), hence the separate key.

Related: docs/memory/designer/page-schedule.md
