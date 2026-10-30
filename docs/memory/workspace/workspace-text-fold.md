---
name: workspace-text-fold
description: Turkish-aware case-insensitive search of the workspace (ws_fold(), ws_text_position(), ws_text_contains() in commands.php): what folds, who calls it, why not mb_stripos(), and ws_scheduled_compare()
---

## Why not mb_stripos() or mb_strtolower()?
They use the language-neutral Unicode mapping: `İ` becomes `i` + U+0307 and `I` becomes `i`, never `ı`. So "YILDIZ" never found "Yıldız", "ismail" never found "İsmail", "ışık" never found "IŞIK" (bug #3400, fixed for the searches in #3486).

## What is the helper?
- `ws_fold($text)` in `includes/workspace/commands.php`, next to `ws_lower()`: `İ`, `I`, `ı` become `i`, then `mb_strtolower()`. All four i letters are one, so "ISTANBUL" written without dots finds "İstanbul" too.
- Every character maps to exactly one, so a position in the folded text is the position in the original: `ws_text_position($haystack, $needle)` returns that character offset (or `false`; an empty needle is 0), `ws_text_contains()` is its boolean.
- `ws_lower()` stays for what must keep `ı` and `i` apart (command names, sort order); a search uses `ws_fold()`.
- `commands.php` is loaded by `includes/workspace/bootstrap.php`, so every workspace file can call it at run time.

## Who calls it?
- Ctrl+K palette (`palette.php`): `ws_palette_snippet()` cuts its excerpt at `ws_text_position()`, the channel filter (name, topic) and the people filter (name, username).
- Channel board search (`channel_board.php`): message bodies, file names, pins, thread summaries.
- Person and department pickers (`ws_ref_search()` in `refs.php`).
- Tests: `tests/workspace_text_fold_test.php` (the issue's pairs, plain ASCII, positions, the palette excerpt).

- Comparisons (#3500, part 2 of #3400): `ws_scheduled_compare()` in `scheduled.php` folds both sides for eq and neq (`ws_fold()`) and contains (`ws_text_contains()`): the Flows condition box, the derived "compare" value, scheduled record conditions. "AYDIN" is "Aydın", "İzmir" contains "izmir". Tests in tests/workspace_flow_site_numbers_test.php.
- The browser side already uses `toLocaleLowerCase('tr')`.
