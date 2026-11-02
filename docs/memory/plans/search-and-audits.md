---
name: search-and-audits
description: Plan (owner request 2026-11-01): search index and page audits as resumable passes that survive a bad page or a killed job; one rule set for panel and editor; one screen; SA1-SA14
---

## What is wrong now?
- The search index is one process that must render every page, record and file to the end, with no resume. A runner that stops `job.php` after a fixed time, or a proxy in front of the panel's window, cuts it; the next run starts again at the first page, so the newest pages (last in id order) and the visual-editor records (drawn after every page) never get in.
- One page whose render throws an `Error`, exits or fails a query, or one office file the converter cannot read, ends the run, and ends every later run at the same place.
- Only the dispatcher locks a run. A panel-button or crontab run beside it: the first to finish deletes, in its sweep (`timestamp != $timestamp`), every row the other has rewritten. Pages vanish until the next run.
- `pg_cron_dispatch_finished()` stamps a crashed or unfinished job as run: System Status shows it healthy, the retry waits a day, the accessibility screen and its PDF call a partial pass a full check ("No problem found" for checks never run). A killed job keeps its lock (index: 4 h) and, never stamped, is picked first again: the heavy lane (SEO, accessibility, backup) starves.
- Three rule sets on three inputs: the editor's `validateTree()` ("!" badges) on its tree and canvas, its `seo_check` on `generateHTML()` as a fragment, the panel's passes on the server render. The accessibility checks never run in the editor; contrast and target size use resolved CSS there and inline styles only on the server.

## What is proposed?
- One background renderer (`pg_seo_render_page()` hardened): anonymous, `Throwable` caught, buffers and borrowed request state restored.
- Every pass resumable: a cursor after every item, a tick inside the process's time limit, an item that killed a tick skipped and named the next time, "due again" in minutes, a completion marker of its own for the health screens.
- The index sweep removes only rows older than its run; every entry takes one lock.
- One rule registry (concept → SEO code, a11y code, WCAG criterion, editor rule, severity per scope). The editor runs the server checks on a server render of the draft; what only a browser can measure is measured there and kept with the report.
- One screen for the four engines: state, failures, switches, Run now.

## Which files hold the rest?
- `search-and-audits-steps.md`: SA1-SA5. `search-and-audits-steps-2.md`: SA6-SA14.
- `search-and-audits-causes.md`: why runs fail and passes stall, by file and line.
- `search-and-audits-rules.md`: the three rule sets side by side.

## Which questions are decided? (owner 2026-11-01 for 1-4, architect for 5-6; PR #4693; `workflow/decisions-made-product-18.md`)
1. One panel screen "Search and audits" for the four engines, opened from the Pages tools menu, the main menu and Site Settings › SEO; the popup window goes.
2. The index, structure and accessibility jobs are on for a new install; a running site is unchanged until the operator switches them on there.
3. The editor's audit panel lists the draft's accessibility findings beside SEO.
4. Contrast and target size measured in the editor are kept with the accessibility report, marked "measured in the editor", replaced on each save.
5. A visual-editor record whose render fails keeps its last indexed text, as pages do from SA1; the failed item is skipped and named the next time.
6. A delta stage between full runs re-indexes pages and records changed since their row; no schema: a record is told from its indexed address (`?r=`), modified time against the row's run timestamp; an item without a readable one waits for the full run.

## Where does it start?
- SA1-SA8 need no answer; SA1, SA3 and SA5 first. Read `designer/designer-records-site-search.md`, `designer/search-index-run-failures.md`, `platform/scheduled-jobs-command-line-render.md`, `platform/accessibility-report.md`.
