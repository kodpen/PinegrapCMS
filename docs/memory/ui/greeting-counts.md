---
name: greeting-counts
description: Counts in the dashboard greeting, the Workspace channel greeting and the Ecommerce card: a key for one, nominative vs accusative link phrases, counts inside one key
---

## How does a greeting sentence read at a count of one?
- Every count in `pg_dashboard_brief()` (`includes/dashboard/brief.php`) and `ws_channel_briefing()` (`includes/workspace/briefing.php`) picks its key by the count: a `noun{suffix:1}` phrase ("1 basket") or, when the verb agrees with the count, two literal keys ("One task here is waiting on something." / "{var:1} tasks here are …"). Before, they read "1 people", "1 baskets", "1 tasks are …".
- The overdue line has three keys: the only open task, one of several, several.

## Why do comments and stock have two link phrases each?
- Turkish puts the object of "Approving the …" / "Restocking the …" in the accusative ("5 yorumu", "5 ürünü") and the subject of "There are …" in the nominative. One shared phrase made "Onay bekleyen 5 yorumu var". Now `'{var:1} waiting comment{suffix:1}'` / `'{var:1} empty product{suffix:1}'` (accusative) and `'{var:1} comment{suffix:1} waiting for approval'` / `'{var:1} out-of-stock product{suffix:1}'` (nominative, "5 onay bekleyen yorum") link to the same screen.

## Ecommerce card counts?
- `widget_3.php` prints `'{var:1} product{suffix:1}'` and `'{var:1} piece{suffix:1}'`, not a number glued before `lang('Product(s)')`.

## What was left alone?
- Sentences whose `{suffix}` is the verb ending (`is`/`are`): the Turkish value drops the suffix, so both languages read right.

Test: `tests/dashboard_brief_counts_test.php`.
