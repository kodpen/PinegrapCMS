---
name: released-vs-open-version
description: Released versions are closed, the unreleased one open: where the status is, how the upgrade loop compares, no new number while one is open, schema the open version gave up is taken out
---

## Where do I find the released and the open version?
- `docs/memory/release/release-status.md`. Look there before adding anything new; if you cannot read it, ask instead of guessing.

## What is closed and what is open?
- The criterion is release, not having run on a dev install.
- A number that shipped to servers is closed: do not touch its `$versions` row, its `changelog.txt` heading, or the meaning of the DB change its `upgrade_to_*` function brings. Its function body may still be fixed for the sites that have not run it yet: `upgrade_to_2022_2()` got `install_drop_column_default()` for MySQL 5.7 (issue #464, PR #470; `database-server-compatibility.md`).
- An unreleased number is open: add the new schema step as a sub-step of that version's entry (e.g. `upgrade_2026_11_0_<topic>()`); the changelog section describes the final state (docs/memory/release/upgrade-changelog-rules.md).
- Never open a new number while an unreleased version exists. Reason: the old rule ("a number that ran once on dev is also closed") piled up eight numbers after 2026.4.3; on 2026-08-28 they were collapsed into one 2026.4.4 and the rule changed.

## What happens to schema the open version added and then gave up?
- Owner (2026-11-19, `workflow/decisions-made-schema-28.md`): "eski sürümlerde değilse ekleme aynı sürüm içinde eklendiği yerden kaldırabiliriz"; "bir kere eklendi artık değişemez yada kaldırılamaz mantığında olmamıza gerek yok".
- A table, column or index that only the open version's sub-steps add and that no code reads or writes any more is taken out where it was added: its line leaves the sub-step (the sub-step and its call too when nothing else is in it), a table leaves `get_tables()`, and the memory that named it is corrected. A site upgrading from the released version never gets it.
- Databases that ran the earlier step (dev installs) are cleaned by a clean-up sub-step of the open version: `install_drop_column()`, `install_drop_table()`, `install_drop_index()`, which do nothing where the thing is absent; rows worth keeping are moved first.
- The PR that gives a structure up removes it in the same PR, beside the step that adds its replacement; no later census looks for it. The 2026.11.0 census (PR #10391) found two leftovers, removed in #10402 (`whatsapp_inbox.read_user_id`, `read_at`) and #10403 (`ws_saved_replies.idx_user`). It asks the architect first (Fixed rules 5), who answers under this rule when the thing is the open version's own; what a released version brought stays the owner's question.

## What does the upgrade loop compare?
Only `config.version`:
```php
$function_name = 'upgrade_to_' . str_replace('.', '_', $version['number']);
if ($version_key > $database_version_key) { $function_name(); }
```
- A version whose key is greater than the DB's runs; an equal one is skipped.
- So after adding a step to an open version, a dev DB that already ran that version will not run the new step by itself: see docs/memory/release/rerun-upgrade-steps.md
