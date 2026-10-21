---
name: page-revisions
description: Page revisions (page_revisions, 11.0.15): what a row holds, when a save writes one, source labels, retention and the daily sweep, the History panel, what is not covered
---

## What is a revision and when is one written?
- Owner, 2026-10-19: a live page publishes directly, there is no working copy; a revision records what was published, never a draft, and a restore is a new publish. Before, history was browser-local only (autosave, 50 undo steps, 20 snapshots) and died with the browser.
- `pg_designer_save_page()` calls `pg_page_revision_record()` after its write when the save changed the page (`$changed`) and returns the number as `revision_no` (read back by the inserted row's id, not `MAX()`). None for a save carried unchanged (only a shared component moved), one equal to the newest revision in tree, drawn HTML and settings (`pg_page_revision_identical()`), the editor's autosave or a dry run.
- Module `includes/fn/page_revisions.php`; table `page_revisions` (2026.11.0, 11.0.15): `page_id`, `style_id`, `revision_no` (per page, `COALESCE(MAX)+1` inside the INSERT; unique `(page_id, revision_no)`, one retry on 1062), `page_tree_json`, `tree_hash`, `tree_code_hash` (hash only: the body is redrawn on restore, the renderer is deterministic), `settings_json`, `user_id`, `source`, `created_at`.
- `settings_json` (`pg_page_revision_settings_from_row()`): name, folder, title, description, search flags, sitemap, noindex/nofollow, home, comments, meta tags, JSON-LD, canonical and robots extras; missing columns left out. No times, so a schedule change writes a row only when it moves the folder.

## What do the source labels mean?
- `publish` (editor save, external API), `restore` (History panel or the API), `import` (HTML/ZIP/site importer, design template), `ai` (the AI editor). Callers pass `revision_source`; `pg_page_revision_source()` turns anything else into `publish`, so a bad label mislabels, never breaks.

## How long are revisions kept?
- `PAGE_REVISIONS_KEEP` (30) and `PAGE_REVISIONS_DAYS` (90) from config, clamped by `pg_page_revisions_retention()` (1–500, 1–3650). A row goes only when beyond the last `keep` AND older than `days` (`pg_page_revisions_expired()`, pure). `pg_page_revisions_trim()` runs after every write (`LIMIT keep, 1000`).
- `job.php` runs `pg_page_revisions_sweep()` daily (state `data/temp/page_revisions_sweep.json`): rows of deleted pages (no delete path knows this table), then pages over `keep`.
- Site Settings › Features › Page Revisions (`pgset-revisions`); an empty or non-numeric box keeps the stored value (1 would let the sweep keep one revision).

## What reads them?
- `pg_page_revisions_list()` (no trees) and `pg_page_revision_get()` (with tree); panel actions `designer/revisions_list` / `revision_get`, also for content-level roles who may edit the page (`pg_designer_revision_page_ok()`). API: `page-revisions-schedule-api.md`.
- History panel (`openSnapshotsPanel()`): revisions above local snapshots, 50 at a time. Compare (`_sdRevisionOpenCompare()`): fetches the revision's shared components the cache lacks first (PR #1111; one dropped since drew nothing), then blob documents side by side and a Myers line diff (`_sdLineDiff()`, 4000-edit cap). Load (`_sdRevisionRestore()`): the tree as unsaved work (one Ctrl+Z), refused in view mode; `revision_source: 'restore'` goes with the next save. Settings are shown, not restored.
- A restore is a save, so the translated body (`page_translations`, keyed on the drawn body) is drawn again as after any publish. Tests: `tests/page_revisions_test.php`.

## What is not covered?
- Classic (legacy) pages: no revisions and no scheduling; their editor is retiring (`plans/legacy-page-designer-retirement.md`).
- Open: shared components (`shared_component` actions) have no writer. Same table with a `shared_id` column, or one of their own?

Related: docs/memory/designer/page-schedule.md
