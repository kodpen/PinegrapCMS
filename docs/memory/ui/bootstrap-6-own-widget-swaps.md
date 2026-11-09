---
name: bootstrap-6-own-widget-swaps
description: Own panel widgets replaced by Bootstrap 6 components with the same look (step B9k): which ones, the token mapping that keeps the pixels, how it was checked
---

## What has been swapped, and how does it look the same?
- Board channel task share: `.progress.ws-cb-progress-bar` > `.progress-bar`. `.ws-cb-progress-bar` only sets the `--bs-progress-*` tokens: height .3rem, `--pg-bg-2` track, `--pg-success` fill, no shadow, no transition, 2.6rem wide (#2273).
- Toolbar chips (settings section links, translations filter, recent-settings strip): `.chip` in a `.pg-chips` strip, `.active` for the current one. `.pg-toolbar .pg-chips > .chip` maps the old `.pg-chip` look onto `--bs-chip-*` tokens and keeps `min-width: auto`, since the chip field's `.pg-chips > .chip` rule lets chips shrink (#2273).
- Workspace notices (`toast()` in `workspace.js`): the same `.toast` markup, run by Bootstrap's Toast via `pgUI.toast` (5 s, paused while hovered, `data-bs-dismiss`), removed on `hidden` (#2273).
- Editor toast (`sdToast()`): `.toast.sd-toast.theme-<tone>` on `pgUI.toast`, `--pg-<tone>-bg-subtle`/`-fg-emphasis`. Close has its own handler, so a clickable note toast skips its click (#2721).
- `.btn-ghost` on the classic screens (96 places, #2373): `btn btn-sm btn-text theme-secondary`, `.btn` kept. `.btn-text.theme-secondary` in backend.src.css sets the ghost's `--bs-btn-*` colours; disabled colour and border are `initial`, so they fall back to the parent's ink as the ghost's did; the `.btn:focus-visible` ring still wins; every `.btn-ghost` rule is gone (#3361).
- The same in Workspace and ERP (191, #2479), the designer list, `pg_period_nav()`, `pg_list_sort_menu()` (#2505), Workspace rich tool rows (#2552), the Flows canvas (#3342, unshot). The rows' pressed tint and disabled look are `.ws-rich-tools .btn-text.theme-secondary` rules; disabled keeps `border-color: transparent`, as the class leaves the disabled border at `initial` (text colour).
- Workspace status choice (`workspace_status.js`): `label.btn-subtle.btn-check.ws-status-choice` wraps its radio; the `.ws-status-<key>` tint wins over the fill, `:has(input:checked)` adds the own-colour outline, `:has(input:focus-visible)` the panel ring.

## What was checked?
- Screenshots of the chips (rest, hover, light, dark) and the progress bar were pixel-identical. Toasts were not shot.
- `.btn-ghost` swap, computed styles equal before and after: slice 1 every ghost on 23 screens (light/dark, rest, hover, focus-visible, active); slice 3 (#2505) 142 on six screens; slice 4 (#2552) 36 on one tool row. Slice 2 (#2479) unshot: no panel rule sets `--bs-btn-*` around it. `tests/bs6_btn_ghost_swap_test.php` guards the files and scans every panel file but `assets/lib/`, backend CSS included, and asserts no `.btn-ghost` selector is left (#3361). `edit_theme_file.php`'s 4 ghosts went again (#3039).
- Status choice, computed styles (light/dark, rest, hover, focus): unchecked equal; the checked one, a grey pill since the switch (`input.btn-check:checked + .btn` outranked its outline rule), has its outline back. Forced colours: icon rule `.ws-status-choice.btn-check > i`, never `:has(…) *`.
- The translations filter's current chip carried `.active`, which `.pg-chip` never styled: it now shows as selected.

## What was kept, and which swaps were left?
- `bootstrap-6-own-widgets-kept.md`. The focus ring is on the tokens, sizes too since B10 (#4465, `panel-focus-ring.md`).
