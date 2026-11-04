---
name: code-audit-2026-10-followups
description: Architecture items from the 2026-10-19 audit that no other plan holds: one outgoing HTTP wrapper, nested named functions, CI checks, and the parked explicit "who may write PHP" rule
---

## Where does this come from?
- The refactor part of the 2026-10-19 audit; security items are in `security-findings-2026-10.md`, older refactors in `refactor-candidates.md`. Nothing here is a decision; check `workflow/decisions-made.md` first.

## What are the items? (size S/M/L)
- Done: 1a-1i #1884 to #2743, 2 #972, 4 #673
3. **"Who may write PHP" is implicit in four evals** (`get_page_content.php` page eval, `dregion`, `custom_php` markers, hooks). One `pg_php_regions_allowed_sources()` and a settings default of "designer code only" would make it explicit. Waits on the eval re-ordering parked in `core/php-regions-eval-and-content.md`. M.

## Where to start?
- Only item 3 is left; it waits on the eval re-ordering, parked by the owner (2026-10-19). When that is decided, item 3 moves to `core/php-regions-eval-and-content.md` and this file is deleted.
