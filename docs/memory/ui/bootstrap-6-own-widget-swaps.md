---
name: bootstrap-6-own-widget-swaps
description: Own panel widgets replaced by Bootstrap 6 components with the same look (step B9k): which ones, the token mapping that keeps the pixels, what is left
---

## What has been swapped, and how does it look the same?
- Board channel task share: `.progress.ws-cb-progress-bar` > `.progress-bar`. `.ws-cb-progress-bar` only sets the `--bs-progress-*` tokens: height .3rem, `--pg-bg-2` track, `--pg-success` fill, no shadow, no transition, 2.6rem wide (#2273).
- Toolbar chips (settings section links, translations filter, recent-settings strip): `.chip` in a `.pg-chips` strip, `.active` for the current one. `.pg-toolbar .pg-chips > .chip` maps the old `.pg-chip` look onto `--bs-chip-*` tokens and keeps `min-width: auto`, since the chip field's `.pg-chips > .chip` rule lets chips shrink (#2273).
- Workspace notices (`toast()` in `workspace.js`): the same `.toast` markup, run by Bootstrap's Toast via `pgUI.toast` (5 s, paused while hovered, `data-bs-dismiss`), removed on `hidden` (#2273).
- Editor toast (`sdToast()`): `.toast.sd-toast.theme-<tone>` on `pgUI.toast`, `--pg-<tone>-bg-subtle`/`-fg-emphasis`. Close has its own handler, so a clickable note toast skips its click (#2721).
- `.btn-ghost` on the classic screens (96 places, #2373): `btn btn-sm btn-text theme-secondary`, `.btn` kept. `.btn-text.theme-secondary` in backend.src.css sets the ghost's `--bs-btn-*` colours; disabled colour and border are `initial`, so they fall back to the parent's ink as the ghost's did; the panel-wide `.btn:focus-visible` ring still wins; the `.btn` `:not()` chain excludes it.
- The same in Workspace and ERP (191, #2479), the designer list and `pg_period_nav()` / `pg_list_sort_menu()` (11, #2505) and the Workspace rich tool rows (13 in `workspace.js`, `workspace_editor.js`; #2552) and the Flows canvas (25 in `workspace_flow.js`, #3342). With no user left, the `.btn-ghost` rule, its `.active` twin and the chain's `:not(.btn-ghost)` are gone (#3342). The rows' pressed tint and disabled look are `.ws-rich-tools .btn-text.theme-secondary` rules; disabled keeps `border-color: transparent`, as the class leaves the disabled border at `initial` (text colour).

## What was checked?
- Screenshots of the chips (rest, hover, light, dark) and the progress bar were pixel-identical. Toasts were not shot.
- `.btn-ghost` swap, computed styles equal before and after: slice 1 every ghost on 23 screens (light/dark, rest, hover, focus-visible, active); slice 3 (#2505) 142 on six screens; slice 4 (#2552) 36 on one tool row (rest, pressed, disabled, AI rewrite). Slice 2 (#2479), no sandbox: Bootstrap styles `.btn-text`/`.theme-secondary` by class only and no panel rule sets `--bs-btn-*` around a converted button. `tests/bs6_btn_ghost_swap_test.php` guards the files and scans every product file but `assets/lib/`, the stylesheet included. Flows canvas (#3342) unshot, as slice 2. `edit_theme_file.php`'s 4 ghosts, back with #2983, went again (#3039) unshot: same class, same `#button_bar`.
- The translations filter's current chip carried `.active`, which `.pg-chip` never styled: it now shows as selected.

## What is left of B9k, and why does the rest wait?
- Focus ring: on the tokens; ring sizes wait for B10 (`panel-focus-ring.md`).
- Wait for B11, since no swap keeps the screen as it is:
  - Guest page toast (`workspace_guest.js`): the page loads Bootstrap's CSS but none of its JavaScript; a Toast would add the whole bundle for one notice.
  - `.ws-chip` to `.badge-subtle`: equal only by overriding every badge token (min-height, weight, line-height, gap, radius), and chips inside saved message HTML keep needing `.ws-chip`, so nothing is removed.
  - Role cards (`.pg-role-card`, `user_permissions.php`) to `form-field-card`: v6 shows the radio and puts it inside the card; today the radio is hidden (`btn-check`) and the label is the card.
  - Status choice (`.ws-status-choice`, `workspace_status.js`) to `label.btn-check.btn-subtle`: v6 fills the checked button; today it gets an outline in its own colour.
