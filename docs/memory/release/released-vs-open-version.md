---
name: released-vs-open-version
description: Released versions are closed, unreleased ones open: where the status is recorded, how the upgrade loop compares versions, no new number while one is open.
---

## Where do I find the released and the open version?
- `docs/memory/release/release-status.md`. Look there before adding anything new.

## What is closed and what is open?
- The criterion is release, not having run on a dev install.
- A number that shipped to servers is closed: do not touch its `$versions` row, its `changelog.txt` heading, or the meaning of the DB change its `upgrade_to_*` function brings.
- An unreleased number is open: add the new schema step as a sub-step of that version's entry (e.g. `upgrade_2026_4_4_<topic>()`); the changelog section describes the final state; dev-internal fixes are not written as [DÜZELTME].
- Never open a new number while an unreleased version exists. Reason: the old rule ("a number that ran once on dev is also closed") piled up eight numbers after 2026.4.3; on 2026-08-28 they were collapsed into one 2026.4.4 and the rule changed.

## What does the upgrade loop compare?
Only `config.version`:
```php
$function_name = 'upgrade_to_' . str_replace('.', '_', $version['number']);
if ($version_key > $database_version_key) { $function_name(); }
```
- A version whose key is greater than the DB's runs; an equal one is skipped.
- So after adding a step to an open version, a dev DB that already ran that version will not run the new step by itself: see docs/memory/release/rerun-upgrade-steps.md
