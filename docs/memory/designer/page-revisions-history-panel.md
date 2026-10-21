---
name: page-revisions-history-panel
description: Who reads page revisions: list and get helpers, the panel actions, the History panel (empty state, Compare, Load/restore), the API pointer, tests
---

## What reads them?
- `pg_page_revisions_list()` (no trees) and `pg_page_revision_get()` (with tree); panel actions `designer/revisions_list` / `revision_get`, also for content-level roles who may edit the page (`pg_designer_revision_page_ok()`). API: `page-revisions-schedule-api.md`.
- History panel (`openSnapshotsPanel()`): revisions above local snapshots, 50 at a time; a page with no row says the next publish first records it as it is. Compare (`_sdRevisionOpenCompare()`): fetches the revision's shared components the cache lacks first (#1111), then blob documents side by side and a Myers line diff (`_sdLineDiff()`, 4000-edit cap). Load (`_sdRevisionRestore()`): the tree as unsaved work (one Ctrl+Z), refused in view mode; `revision_source: 'restore'` goes with the next save. Settings are shown, not restored.
- A restore is a save, so the translated body (`page_translations`, keyed on the drawn body) is drawn again as after any publish. Tests: `tests/page_revisions_test.php`.

Related: docs/memory/designer/page-revisions.md, docs/memory/designer/page-revisions-schedule-api.md
