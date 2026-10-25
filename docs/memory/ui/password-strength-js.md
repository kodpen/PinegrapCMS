---
name: password-strength-js
description: assets/js/pg_password_strength.js, the panel copy of validate_password_strength() for the password meter (PR #1698): API, the PHP traps it copies, the shared test
---

## What is it?
- `window.pgPasswordStrength` in `assets/js/pg_password_strength.js`: `isStrong(pw)`, `check(pw)` (`{length, capital, numbers, symbol}`, each true when met), `rules` (10, 1, 2, 1, the numbers `get_strong_password_requirements()` lists).
- Served as written, no `.min` twin and no bundle; no screen loads it yet. The B9i meter (`plans/bootstrap-6-components.md`) scores with it when "Require Strong Password" (`config.strong_password`) is on.

## What must stay the same as the PHP function?
- `validate_password_strength()` in `includes/fn/auth.php`; change both together.
- Length counts characters like `mb_strlen()`: `Array.from(pw).length`, not `.length` (an emoji is two UTF-16 units).
- The PHP patterns have no `/u`: only ASCII `A-Z` is a capital and only `0-9` are numbers; any other character (`ş`, `Ç`, a space) counts as non-alphanumeric.

## How is it tested?
- `tests/password_strength_test.php` holds one case list and runs it on the PHP function and on the script under Node (skipped only without Node, like `tests/pg_ui_test.php`).
