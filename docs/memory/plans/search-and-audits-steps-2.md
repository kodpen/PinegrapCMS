---
name: search-and-audits-steps-2
description: Steps SA6-SA14 of plans/search-and-audits.md: registry, same input, editor checks on the draft, audit panel, editor measurements, screen, delta, tick lock; claim as [plan:search-and-audits] SA<n>
---

## Which steps are there?
- Sizes as in `search-and-audits-steps.md`.

- SA6 (M) Rule registry. Done (#4769). `free`: severities unchanged.
  - New `includes/fn/audit_rules.php`: concept → SEO code, a11y code, WCAG criterion and level, severity per scope, editor rule ids, document-only, browser-only. `pg_seo_structure_severities()` and `pg_a11y_rules()` read it.
  - `tools/check_audit_rules.php` in CI: every editor rule id and `seo:` code in `style_designer.src.js` has an entry, and each entry's codes have a checker. `tests/audit_rules_test.php`.

- SA7 (S) Same pages, same visitor. After SA5. Done (#4771).
  - Which page types and folders each pass covers is written in one place: the index keeps private folders (search filters its rows by folder), the audits public ones. The structure and accessibility buttons render anonymous, as the jobs do: today they render as the signed-in manager (`view_pages.php`), so a gated page's findings depend on who pressed.

- SA8 (M) The editor runs the server checks on the draft. After SA6 and SA7. Done (#4786).
  - `seo_check` renders the draft on the server (its tree, widgets as the Preview draws them with `pg_designer_preview_widgets()`, shared components, the head) under the background renderer's rules and runs the structure rules in `document` mode with the page's Open Graph and JSON-LD context. The document-only findings carried over from the stored ones go; link and speed stay read from the saved page.
  - Tests: the draft render and the endpoint's composition beside the parity checks of `designer/seo-score-live-check-parity.md`.

- SA9 (M) The editor's audit panel. After SA8. Done (#4839).
  - The draft's accessibility findings beside SEO, one row per registry concept (label, SEO severity, WCAG criterion); badges through a `data-pg-node` attribute written only in the audit render; the canvas checks listed under the same concepts. `php tools/build_js.php`.

- SA10 (S) Editor measurements kept. After SA9. Done (#4851).
  - On save the editor posts its browser-only findings (contrast, target size); they are stored as `a11y_` rows marked as measured in the editor, replaced on each save, left alone by the nightly pass. The report and its PDF say where and when.

- SA11 (M) The screen. After SA3 and SA4. Done (#4850).
  - Per engine (index, SEO scores, structure, accessibility): last full run, running or due, progress, failures named, its switch, Run now (short requests); the Pages tools menu, the main menu and the panel search open it.

- SA12 (S) Delta stage. After SA4. Done (#4837).
  - Each tick first re-indexes pages whose `page_timestamp` and records whose `forms.last_modified_timestamp` is newer than their row; the daily full run stays.

- SA14 (S) The index's tick lock in minutes; a failed designer record keeps its row. Done (#4983).
  - The catalogue's `update_search_index` lock 600 s (was 14400): a dispatched tick and a panel request work within a budget, and a tick a host's job runner killed outright released nothing for four hours, the heavy lane with it. A command-line run, the whole pass, claims 14400 s (`pg_search_index_claim_seconds()`, `pg_cron_claim($name, $seconds)`).
  - A designer record whose render throws keeps its row (`pg_search_index_keep_item()`), as question 5 decided; SA1 left it out.

- SA13 (S) Close-out.
  - Lasting behaviour and decisions to the `designer/`, `platform/` and `visitors/` topic files; the five plan files deleted.
