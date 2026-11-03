---
name: audiences-steps
description: Steps AU1-AU6 of plans/audiences.md: rule model and SQL builder, recompute job and announce, the rule editor, readers and doors, the audience token, close-out; claim as [plan:audiences] AU<n>
---

## Which run side by side?
- Wave 1: AU1 alone (questions 1-2 first). Wave 2: AU2, AU3 and AU4 at once (job, screen, readers: different files). Wave 3: AU5 after AU2 and `page-experiments.md` PX6. Wave 4: AU6.
- S under 400 product lines, M about 1,000. Tests in `tests/audiences_<topic>_test.php` (pure functions, red first), memory under `core/`, texts through `tr_add.php`.

## Which steps are there?
- AU1 (M) Rule model and SQL builder. `free` after questions 1-2.
  - Migration sub-step: `contact_groups.kind`, `contact_groups.rules`, `users_contact_groups_xref.source` (`install_add_column()`, rerun twice). New `includes/fn/audiences.php`: `pg_audience_conditions()` (the catalogue: key, label, operators, value kind, which table it reads), `pg_audience_rules_clean($rules)` (one shape, unknown keys dropped, bounds), `pg_audience_sql($rules)` (pure: returns the SELECT of contact ids with placeholders through `db_q()`, one sub-query per condition family: contacts, consent_log, orders, subscriptions, forms, visitor summary, xref), `pg_audience_count($rules)`. Tests: every condition compiles, the all/any nesting, a rule with no condition selects nobody, placeholders never carry raw text. Memory: `core/audiences-rules.md`.

- AU2 (M) Recompute job. After AU1.
  - `pg_audience_recompute($group_id, $budget)` in `includes/fn/audiences.php`: the rule's ids against the group's rule-made xref rows (`source = rule`), inserts and deletes in chunks, pages for big groups with a cursor in `data/temp/audience_<id>.json`, `last_computed_at` on the group; the general job (`pg_cron_jobs()`, light lane) takes the group with the oldest run first within `pg_cron_time_left()`; the difference goes to `pg_announce_contact_group_members()` so Flows, webhooks and campaign-profile triggers fire as for a hand change; Refresh now from the screen runs one group with short requests as the search index does. Tests: the diff on fixed sets, the chunking, the budget stop. Memory: `core/audiences-recompute.md`.

- AU3 (M) The rule editor. After AU1.
  - `edit_contact_group.php`: a "Members by rule" switch, the rule rows (condition pick, operator, value controls: text, number, days, a product or group picker through `pgCombobox`, a consent purpose list), all/any per level with one nesting, a live count ("about 1,240 contacts") from `pg_audience_count()`, the last run time, Refresh now; `view_contact_groups.php` shows kind and count; the contact card lists rule-made groups as read-only chips. Memory: `ui/contact-group-rules.md`.

- AU4 (S) Readers and doors. After AU1.
  - The doors that add a contact to a group by hand refuse a dynamic group with one message: the contact card tick boxes, forms' contact groups, products' contact groups, import, the API `contact_groups` members routes and MCP (`api/api-endpoint-rules.md`: the refusal shape); campaigns, SMS campaigns, the account's group list and exports read as today; erasure recomputes nothing (the xref rows go with the contact). Tests on the guard function. Memory: `core/audiences-rules.md` grows.

- AU5 (S) Audience token. After AU2 and PX6.
  - `{audience:group:<id>}` of `page-experiments.md` resolves a dynamic group through the xref as any group (nothing new to compute at request time); a "joined a group" campaign-profile trigger and the Flows event start name dynamic groups in their pick lists with their kind. Memory: `designer/audience-conditions.md` grows.

- AU6 (S) Close-out. After AU1-AU5.
  - PR body names `[NEW] Added contact groups filled by rules: orders, visits, consent, membership and fields decide who is in`; decisions to `core/`; the two plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
