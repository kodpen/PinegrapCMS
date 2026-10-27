---
name: bootstrap-6-own-widget-swaps
description: Own panel widgets replaced by Bootstrap 6 components with the same look (step B9k): which ones, the token mapping that keeps the pixels, what is left
---

## What has been swapped, and how does it look the same?
- Board channel task share (`workspace_board_channel.js`): `.progress.ws-cb-progress-bar` > `.progress-bar` (role progressbar, aria values). `.ws-cb-progress-bar` only sets the `--bs-progress-*` tokens: height .3rem, `--pg-bg-2` track, `--pg-success` fill, no shadow, no transition, 2.6rem wide (#2273).
- Toolbar chips (settings section links in `includes/settings/screen.php`, the translations filter, the recent-settings strip in `backend.src.js`): `.chip` in a `.pg-chips` strip, `.active` for the current one. `.pg-toolbar .pg-chips > .chip` maps the old `.pg-chip` look onto `--bs-chip-*` tokens and keeps `min-width: auto`, since the chip field's `.pg-chips > .chip` rule lets chips shrink (#2273).
- Workspace notices (`toast()` in `workspace.js`): the same `.toast` markup, now shown and hidden by Bootstrap's Toast through `pgUI.toast` (5 s, paused while hovered, `data-bs-dismiss`), removed on `hidden` (#2273).

## What was checked?
- Before/after screenshots of the chips (rest and hover, light and dark) and the progress bar were pixel-identical. The toast is transient and was not shot.
- The translations filter's current chip carried `.active`, which `.pg-chip` never styled: it now shows as selected.

## What is left of B9k?
- `.btn-ghost` (285) to `.btn-text.theme-secondary`, own theme tokens and focus ring, pills and `.ws-chip` to `.badge-subtle`, role cards to `form-field-card`, status choice to `label.btn-check.btn-subtle`, the editor's own toast (`sdToast`), the guest page's toast; only where screenshots show no change, else in B11 (`plans/bootstrap-6-components.md`).
