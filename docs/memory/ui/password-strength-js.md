---
name: password-strength-js
description: pg_password_strength.js, the panel copy of validate_password_strength() and the Bootstrap 6 Strength meter it drives (#1698, #2211, installer #2274): scoring on and off, where it shows, tests
---

## What is it?
- `window.pgPasswordStrength` in `assets/js/pg_password_strength.js`: `isStrong(pw)`, `check(pw)` (`{length, capital, numbers, symbol}`, each true when met), `rules` (10, 1, 2, 1, the numbers `get_strong_password_requirements()` lists), `score(pw)`, `advisoryScore(pw)`, `level(score, strict)`, `thresholds`.
- Served as written, no `.min` twin and no bundle; `pg_password_strength_script()` (`includes/fn/auth.php`) prints its `<script defer>` once per page.

## How does the meter score?
- `pg_password_strength_meter($input_id, $strict)` prints Bootstrap 6 Strength markup (`.strength`, four `.strength-segment`, `.strength-text`) with `data-pg-strength-*` hooks; messages Weak / Fair / Good / Strong from `lang()`. `$strict` is `STRONG_PASSWORD` (`config.strong_password`).
- Strict (setting on): one point for typing plus one per rule met, thresholds 2/3/4: weak up to one rule, then fair, good, and strong exactly when `isStrong()` is true, which is what the server accepts.
- Off (the column default, so always at install): Bootstrap's own default scoring (8+ characters, 12+, lower, upper, digit, special, two specials, 16+; thresholds 2/4/6). It only advises; nothing is refused.

## Why not Bootstrap's Strength script?
- Its `scorer` option is a function, which markup cannot carry, and panel code reaches Bootstrap's JS only through `pgUI`, which has no `strength` kind (other B9 PRs held `pg_ui.js`). The script sets `data-bs-strength`, `.active` and the text itself, the "static" use the v6 docs allow.
- The markup never carries `data-bs-strength` at load: Bootstrap's data API would start its own default scorer on the element at DOMContentLoaded. The text colour is `fg-danger`/`fg-warning`/`fg-info`/`fg-success`, the tones Bootstrap's script uses.

## Where does it show?
- Panel chrome only (Bootstrap 6): `set_password.php`'s built-in form (`get_set_password()` with `page_id` 0, `$strength_meter` in `set_password_system.php`). A site page (5.3.8, no Strength) gets nothing; change password is drawn only on site pages, so it has no meter.
- The installer's administrator password (`install/index.php`, `admin_password`): `pg_password_strength_meter('admin_password', false)`, always advisory because `strong_password` is off in a new database. Its own `.pg-strength` CSS and scoring script were removed (#2274).

## What must stay the same as the PHP function?
- `validate_password_strength()` in `includes/fn/auth.php`; change both together.
- Length counts characters like `mb_strlen()`: `Array.from(pw).length`, not `.length` (an emoji is two UTF-16 units).
- The PHP patterns have no `/u`: only ASCII `A-Z` is a capital and only `0-9` are numbers; any other character (`ş`, `Ç`, a space) counts as non-alphanumeric.

## How is it tested?
- `tests/password_strength_test.php` holds one case list and runs it on the PHP function and on the script under Node (skipped only without Node, like `tests/pg_ui_test.php`); it checks strict "strong" equals "the server accepts", the advisory levels and the meter markup.
