---
name: page-revisions
description: Page revisions (page_revisions, 11.0.15): what a row holds, when pg_designer_save_page() writes one and when not, the source labels, the two retention rules and the daily sweep in job.php
---

## What is a revision and when is one written?
- A live page publishes directly; a revision is the record of what was published, never a draft. `pg_designer_save_page()` calls `pg_page_revision_record()` after its UPDATE/INSERT whenever the save changed the page (`$changed`): a save carried unchanged because only a shared component moved writes none, and so does a save whose tree, drawn HTML and settings all equal the newest revision (`pg_page_revision_identical()`). Nothing is written on the editor's autosave (browser-local) or a dry run.
- Module `includes/fn/page_revisions.php`; table `page_revisions` (2026.11.0, 11.0.15): `page_id`, `style_id`, `revision_no` (per page, `COALESCE(MAX)+1` inside the INSERT; unique `(page_id, revision_no)`, one retry on 1062), `page_tree_json` (the tree as saved), `tree_hash`, `tree_code_hash` (SHA-1 of the drawn body, redrawn on restore: the renderer is deterministic), `settings_json`, `user_id`, `source`, `created_at`.
- `settings_json` = `pg_page_revision_settings_from_row()`: name, folder, title, description, search flags, sitemap, noindex/nofollow, home, comments, meta tags, JSON-LD from the row just written (missing columns left out; no schedule times).

## What do the source labels mean?
- `publish` (editor save, external API), `restore` (a revision loaded back into the editor and published again), `import` (HTML/ZIP/site importer, a design template: `pg_designer_apply_template()`), `ai` (the AI editor). A caller names it with `revision_source` in the page array; `pg_page_revision_source()` turns anything else into `publish`, so a label the editor's JSON carries can only mislabel, never break.

## How long are revisions kept?
- Two config columns, read by init.php as `PAGE_REVISIONS_KEEP` (30) and `PAGE_REVISIONS_DAYS` (90), clamped by `pg_page_revisions_retention()` (1–500, 1–3650). A row expires only when both rules let it go: it is beyond the last `keep` revisions AND older than `days` (`pg_page_revisions_expired()`, pure, cutoff inclusive). `pg_page_revisions_trim($page_id)` runs after every write, reading only the rows past the count rule (`LIMIT keep, 1000`).
- `job.php` runs `pg_page_revisions_sweep()` once a day (state `data/temp/page_revisions_sweep.json`): rows of deleted pages go (pages are deleted on several paths, none knows this table), then pages over `keep` are trimmed; one activity line when it removed anything.
- Set under Site Settings › Features › Page Revisions (`pgset-revisions`, `features.save.php`); an empty or non-numeric box keeps the stored value, since 1 would let the sweep drop all but one revision.

## What reads them?
- `pg_page_revisions_list($page_id, $limit, $before_no)` (no trees; user name, decoded settings, tree size) and `pg_page_revision_get($page_id, $revision_no)` (with the tree); panel actions `designer/revisions_list` and `designer/revision_get` (`includes/panel/designer.php`), open to content-level roles too, for a page that exists and that the person may edit (`pg_designer_revision_page_ok()`).
- The editor's History panel (`openSnapshotsPanel()`, toolbar clock): the page's revisions above local snapshots and autosave, 50 at a time. Compare (`_sdRevisionOpenCompare()`) draws the revision through the page's frame (`generateHTML(name, abc, tree)`) beside the page now, as blob documents (widgets as placeholders) and as a Myers line diff of the two HTML texts (`_sdLineDiff()`, 4000-edit cap, folded context). Load (`_sdRevisionRestore()`) replaces the active page's tree as unsaved work (one Ctrl+Z back), refused in view mode; the tab is marked `restoredFrom`, `_pgTabsSerialize()` sends `revision_source: 'restore'`, the mark goes after the save. Settings are shown, not restored.
- Tests: `tests/page_revisions_test.php` (retention, clamps, labels, snapshot, identity).
