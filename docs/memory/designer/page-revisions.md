---
name: page-revisions
description: Page revisions (page_revisions, 11.0.15): what a row holds, when pg_designer_save_page() writes one and when not, the source labels, the two retention rules and the daily sweep in job.php
---

## What is a revision and when is one written?
- A live page publishes directly; a revision is the record of what was published, never a draft. `pg_designer_save_page()` calls `pg_page_revision_record()` after its UPDATE/INSERT whenever the save changed the page (`$changed`): a save carried unchanged because only a shared component moved writes none, and so does a save whose tree, drawn HTML and settings all equal the newest revision (`pg_page_revision_identical()`). Nothing is written on the editor's autosave (browser-local) or a dry run.
- Module `includes/fn/page_revisions.php`; table `page_revisions` (2026.11.0, 11.0.15): `page_id`, `style_id`, `revision_no` (per page, taken inside the INSERT with `COALESCE(MAX)+1`; the unique key `(page_id, revision_no)` makes two saves at once take different numbers, the writer retries once on 1062), `page_tree_json` (the tree as saved), `tree_hash`, `tree_code_hash` (SHA-1 of the drawn body; the body itself is drawn again on restore, the renderer is deterministic), `settings_json`, `user_id`, `source`, `created_at`.
- `settings_json` = `pg_page_revision_settings_from_row()`: name, folder, title, description, search flags, sitemap, noindex/nofollow, home, the comment settings, meta tags and JSON-LD, read from the page row the save just wrote; columns a database lacks are left out.

## What do the source labels mean?
- `publish` (editor save, external API), `restore` (a revision loaded back into the editor and published again), `import` (HTML/ZIP/site importer, a design template: `pg_designer_apply_template()`), `ai` (the AI editor). A caller names it with `revision_source` in the page array; `pg_page_revision_source()` turns anything else into `publish`, so a label the editor's JSON carries can only mislabel, never break.

## How long are revisions kept?
- Two config columns, read by init.php as `PAGE_REVISIONS_KEEP` (30) and `PAGE_REVISIONS_DAYS` (90), clamped by `pg_page_revisions_retention()` (1–500, 1–3650). A row expires only when both rules let it go: it is beyond the last `keep` revisions AND older than `days` (`pg_page_revisions_expired()`, pure, cutoff inclusive). `pg_page_revisions_trim($page_id)` runs after every write, reading only the rows past the count rule (`LIMIT keep, 1000`).
- `job.php` runs `pg_page_revisions_sweep()` once a day (state file `data/temp/page_revisions_sweep.json`): the rows of pages that no longer exist go (a page is deleted on several paths, none of them knows this table), then every page with more rows than `keep` is trimmed. Logged as one activity line when it removed anything.

## What reads them?
- `pg_page_revisions_list($page_id, $limit, $before_no)` (no trees; user name, decoded settings, tree size) and `pg_page_revision_get($page_id, $revision_no)` (with the tree). The History panel, compare, restore and the API routes are the next steps of `plans/page-revisions-and-scheduling.md`.
- Tests: `tests/page_revisions_test.php` (retention, clamps, labels, snapshot, identity).
