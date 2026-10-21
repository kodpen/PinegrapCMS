---
name: page-revisions
description: Page revisions (page_revisions, 11.0.15): what a row holds, when a save writes one, the lazy first row of a page without one, source labels, retention and the daily sweep, what is not covered
---

## What is a revision and when is one written?
- Owner, 2026-10-19: a live page publishes directly, there is no working copy; a revision records what was published, never a draft, and a restore is a new publish. Before, history was browser-local only.
- `pg_designer_save_page()` calls `pg_page_revision_record()` after its write when the save changed the page (`$changed`) and returns `revision_no` (read back by the inserted row's id, not `MAX()`). None for a save carried unchanged (only a shared component moved), one equal to the newest in tree and settings (`pg_page_revision_identical()`, pure), autosave or a dry run.
- Lazy first row (#829): before the UPDATE of a changing save, a page with no revision (published before 11.0.15, or copied row by row by `duplicate_page_f.php` / `duplicate_style.php`) is recorded as stored by `pg_page_revision_seed()` (values from the row: `pg_page_revision_seed_values()`, pure): revision 1, `created_at = page_timestamp`, `user_id = page_user`, source `publish`; the new publish is #2. No migration backfill; a 1062 on the seed means another save did it. Before, the first publish after the upgrade left no way back.
- The drawn HTML is hashed, not compared (#828): the renderer is not deterministic (navbar id `uniqid()`, carousel id `mt_rand()`), so a schedule-only save of a navbar page wrote a duplicate row.
- Module `includes/fn/page_revisions.php`; table `page_revisions` (11.0.15): `page_id`, `style_id`, `revision_no` (per page, `COALESCE(MAX)+1` in the INSERT; unique `(page_id, revision_no)`, one retry on 1062), `page_tree_json`, `tree_hash`, `tree_code_hash` (hash only: the body is redrawn on restore), `settings_json`, `user_id`, `source`, `created_at`.
- `settings_json` (`pg_page_revision_settings_from_row()`): name, folder, title, description, search flags, sitemap, noindex/nofollow, home, comments, meta tags, JSON-LD, canonical, robots extras; missing columns left out. No times: a schedule change writes a row only when it moves the folder.

## What do the source labels mean?
- `publish` (editor save, external API), `restore` (History panel or the API), `import` (HTML/ZIP/site importer, design template), `ai` (the AI editor). Callers pass `revision_source`; `pg_page_revision_source()` turns anything else into `publish`, so a bad label mislabels, never breaks.

## How long are revisions kept?
- `PAGE_REVISIONS_KEEP` (30) and `PAGE_REVISIONS_DAYS` (90) from config, clamped by `pg_page_revisions_retention()` (1–500, 1–3650). A row goes only when beyond the last `keep` AND older than `days` (`pg_page_revisions_expired()`, pure). `pg_page_revisions_trim()` runs after every write (`LIMIT keep, 1000`).
- `job.php` runs `pg_page_revisions_sweep()` daily (state `data/temp/page_revisions_sweep.json`): rows of deleted pages (no delete path knows this table), then pages over `keep`.
- Site Settings › Features › Page Revisions (`pgset-revisions`); an empty or non-numeric box keeps the stored value (1 would let the sweep keep one revision).

## What is not covered?
- Classic (legacy) pages: no revisions and no scheduling; their editor is retiring (`plans/legacy-page-designer-retirement.md`).
- Open: shared components (`shared_component` actions) have no writer. Same table with a `shared_id` column, or one of their own?

Related: docs/memory/designer/page-revisions-history-panel.md, docs/memory/designer/page-schedule.md
