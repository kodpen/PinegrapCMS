---
name: page-history-editor-revision
description: Which page revision History names "designer (change by editor)": only the one the apply wrote, kept as steps.revision_no; old changes matched on after_hash and the second (#10216)
module: page_revisions
---

## Which revision names the editor?
- Only the revision the apply of the editor's change wrote. The apply keeps its number in the change's `steps` as `revision_no` (no column; 0 when the save wrote none, then no revision is named).
- `pg_page_revisions_list()` asks `pg_page_revisions_editor()` (pure) for each revision; a numbered change matches its number alone.

## How does the apply know the number?
- `pg_designer_save_page()` returns `revision_no`, but the apply saves through `_pg_cp_write()`, which answers only an error. `pg_page_revision_record()` keeps the number it wrote per page for the request (`pg_page_revision_written()`), cleared at its start; the apply's UPDATE takes it once (`pg_content_proposals_steps_revision()`).

## And a change applied before the number was kept?
- No `revision_no` key: it names the revision whose `tree_hash` is its `after_hash` and whose `created_at` is its `decided_at` or one second before (`decided_at` is taken just after the save). A slower save leaves it unnamed.

## Why not the tree fingerprint alone?
- A revision keeps the page settings too; a save changing only settings writes a new revision with the same `tree_hash`. Matching on `after_hash` alone named the revision before a settings-only change and every later settings-only revision "(change by editor)" (#10216).

## Which test guards it?
- `tests/content_proposals_history_test.php`.

Related: docs/memory/designer/content-proposals-designer-side.md, docs/memory/designer/page-revisions-history-panel.md
