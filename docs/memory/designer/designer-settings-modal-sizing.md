---
name: designer-settings-modal-sizing
description: Designer settings modal size and closing: modal-xl from 992px, full screen below, one height capped at 56rem, static backdrop, sizes in style_designer.css, rail becomes a strip under 992px.
---

## How big is the modal?
- From 992px the largest Bootstrap dialog (`modal-xl`: 800px up to 1199px, 1140px from 1200px), centred; below 992px the whole screen (`modal-fullscreen-lg-down`). It used to be full screen at every width, a 640px form in a sea of empty space.
- One height whatever pane is open: `.sd-settings-content` is `min(100vh - 3.5rem, 56rem)` (dvh where the browser has it), so a pane switch never moves the buttons and only the pane (`.sd-settings-body`) scrolls. Every flex item of that chain carries `min-height: 0`. 3.5rem is Bootstrap's own 1.75rem dialog margin twice.
- The rail (`.sd-settings-nav`) is 230px. Below 992px it is a strip above the pane: the group captions are hidden, the page group's caption becomes a thin line between the design's pills and the page's. Keep pill texts short.
- `data-bs-theme="dark"` on the modal gives the switches, the close button and the tag field their dark variants; the designer's own colours sit on top in `#styleSettingsModal` rules.

## Where do the sizes live?
- In `style_designer.css`, never inline on the skeleton: an inline size beats every media query, and Bootstrap helpers such as `flex-column`, `p-4`, `mb-3` carry `!important`. Content boxes (a row, a badge, a code field) are free.

## How does it close?
- Only from its own buttons: `data-bs-backdrop="static"` and `data-bs-keyboard="false"`; a click beside it or Escape makes Bootstrap shake it (`modal-static`). Before, a stray click closed it in the middle of typing.
- X and Apply do the same: what was changed is already on the design and nothing is undone. Every way out redraws the HTML tree (`hide.bs.modal`) and schedules the live preview (`hidden.bs.modal`); the theme file reaches the canvas on `change`, not on Apply.
- The footer's main button (`#sd-settings-save`) closes the modal and then clicks the toolbar's main button, under its word: Save on a draft, Publish otherwise (`_sdSettingsSyncSave()`). It is off while that one is disabled and in view mode, where the toolbar's is only out of reach.
- Reset Layout (footer, left) resets the editor's panel sizes, not the design; it is a neutral button with a title saying so.

Related: docs/memory/designer/designer-settings-modal-panes.md
