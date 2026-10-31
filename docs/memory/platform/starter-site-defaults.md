---
name: starter-site-defaults
description: Starter dump defaults corrected by #3330 (PR #3581): empty AddThis sharing code plus the 11.0.133 data step, Turkish title 'Web Sitem', AT-ONCE named 'En kısa sürede'.
---

## What sharing code do the starter sites ship?
- None: `config.social_networking_code` is `''` in both dumps, with `social_networking` 1 and the advanced type kept. `get_page_content.php` prints nothing for an empty code, so the pages look the same.
- Why: both dumps shipped an AddThis script. AddThis closed on 2023-05-31, so every page sent a request that only failed (#3330, decision #3567, PR #3581). 2026.1.12 replaced AddThis for the simple sharing type only.

## What happens on running sites?
- Step `upgrade_2026_11_0_starter_addthis()` (11.0.133) empties the code only where it is still one of the two stock scripts, byte for byte (`BINARY ... IN`). A code the owner wrote or edited stays. A second run changes nothing.
- The title and arrival names are never migrated, since an owner may have written them.

## Which Turkish defaults changed?
- `config.title` is `'Web Sitem'` (was `'My Website'`), and arrival date 1 (`AT-ONCE`) is named `'En kısa sürede'` (was `'Bir defada'`). Its description stays. The English dump keeps its title `'liveSite'`.
- `tests/starter_defaults_test.php` checks the dumps and the step. The fixture `'Bir defada'` in `tests/order_view_rows_test.php` is test data, not the dump.
