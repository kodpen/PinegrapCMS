---
name: designer-settings-modal-sizing
description: Designer settings modal size and closing: dialog-xl from 1024px, full screen below, one height capped at 56rem, static backdrop, sizes in style_designer.css, rail becomes a strip under 1024px.
---

## How big is the modal?
- `<dialog class="dialog dialog-xl lg-down:dialog-fullscreen sd-settings-dialog sd-settings-content" id="styleSettingsModal">` (`includes/designer_screen.php`). From 1024px the largest Bootstrap dialog (`dialog-xl`, 1140px, never wider than the window minus its margins), top-aligned like every panel dialog (no `pg-dialog-centered`); below 1024px the whole screen. It used to be full screen at every width, a 640px form in a sea of empty space (changed in #582).
- One height whatever pane is open: from 1024px the dialog is `min(100vh - 3.5rem, 56rem)` (dvh where the browser has it), so a pane switch never moves the buttons and only the pane (`.sd-settings-body`) scrolls. Every flex item of that chain carries `min-height: 0`. 3.5rem is Bootstrap's own 1.75rem dialog margin (`--bs-dialog-margin`) twice.
- The rail (`.sd-settings-nav`) is 230px. Below 1024px it is a strip above the pane: the group captions are hidden, the page group's caption becomes a thin line between the design's pills and the page's. Keep pill texts short.
- `data-bs-theme="dark"` on the dialog gives the switches, the close button and the tag field their dark variants; the designer's own colours sit on top in `.sd-settings-*` / `#styleSettingsModal` rules.

## Where do the sizes live?
- In `style_designer.css` (inside `@layer custom`), never inline on the skeleton: an inline size beats every media query. Bootstrap 6 helpers (`flex-column`, `p-*`, `mb-*`) have no `!important` but sit in the utilities layer, above the stylesheet's own rules. Content boxes (a row, a badge, a code field) are free.

## How does it close?
- Only from its own buttons: `data-bs-backdrop="static"` and `data-bs-keyboard="false"`; a click beside it or Escape makes Bootstrap shake it (`dialog-static`). Before, a stray click closed it in the middle of typing.
- X and Apply do the same: what was changed is already on the design and nothing is undone. Every way out redraws the HTML tree (`pgUI.dialog.on(…, 'hide')`) and schedules the live preview (`'hidden'`); the theme file reaches the canvas on `change`, not on Apply.
- The footer's main button (`#sd-settings-save`) closes the dialog and then clicks the toolbar's main button, under its word: Save on a draft, Publish otherwise (`_sdSettingsSyncSave()`). It is off while that one is disabled and in view mode, where the toolbar's is only out of reach.
- Reset Layout (footer, left) resets the editor's panel sizes, not the design; it is a neutral button with a title saying so. `initSettingsMenu()` (`style_designer.js`) adds it to the dialog's `.dialog-footer`.

Related: docs/memory/designer/designer-settings-modal-panes.md
