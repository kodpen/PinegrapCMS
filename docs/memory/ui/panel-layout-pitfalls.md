---
name: panel-layout-pitfalls
description: Panel CSS and JS traps that fail silently, with their files: hiding under CSS layers, menus in scroll boxes, flex min-height 0, nested :has(), drawers in forms, top layer, multiselect.
---

## How do I hide and show an element?
- Toggle `d-none` (the panel's one convention, e.g. `show(el, on)` in `offer_editor.js`, `step-rail.md`). Bootstrap 6 utilities sit in `@layer utilities` without `!important`: `d-none` beats every panel rule (all panel CSS is in `@layer custom`), but an inline display (`style="display:…"`, jQuery `.show()`/`.css()`) beats `d-none`.
- `[hidden]` is `display: none !important` in `@layer reboot`, so `hidden` / `el.hidden = true` now hides even a `d-flex` element and beats inline styles too.
- A panel rule cannot hide (or re-display) an element that carries a `d-*` utility: the utilities layer wins. Change the class instead.

## How is a menu placed inside a scrolling box?
- Floating UI with `strategy: "fixed"`, passed as `floatingConfig` through `pgUI.menu.get()`: an absolutely positioned menu is clipped by the scroll box (`dashboard-sales-map-panel.md`). A menu a screen opens itself on `.menu` takes its keys on `window` (`own-menu-dropdown-keydown.md`).

## Which layouts collapse only on narrow screens?
- `overflow` other than `visible` on a flex item drops its automatic minimum size: give the axis a floor first (`flex-overflow-hidden-pitfall.md`).
- Every flex item of a scroll chain needs `min-height: 0`, or the inner box never scrolls and the save bar falls below the screen; write `100dvh` after `100vh`, inside `@supports` (`settings-modal-layout.md`).

## Which CSS mistakes drop without a word?
- `:has()` inside `:has()` is invalid and the whole rule is dropped; a rule that overrides a `:has()` rule must carry the same predicate (`settings-card-layout-pitfalls.md`).
- A class string missing a space (`2xl:col-6lg:col-3`) is one unknown class: the cell stays full width at every size (`settings-card-field-widths.md`).
- `pg-alert-dismissible` never takes `py-*`: its close button is placed absolutely and falls out of a shorter box (`settings-card-chrome.md`).

## What about forms, panels and pickers?
- Drawers (`<dialog class="drawer">`) stay inside the `<form>`: the top layer moves nothing in the DOM, so their fields submit; anything with a form of its own stays outside the main form (`user-permission-rows.md`).
- Open dialogs and drawers are in the browser's top layer, where no z-index reaches. A floating layer for something inside one is appended to `pgUI.overlayHost(el)`: select2's dropdown and the jQuery UI date, date-time and time pickers do (`inOverlayHost()` in `pg_fields.js` moves the picker's box on each open); one hung off `<body>` stays behind the dialog whatever its z-index.
- `multiselectCheckbox` fires no event per box: bind behaviour to state and re-sync on every change (`user-permission-rows.md`).

## Which JS traps have files of their own?
- Sortables: `sortable-init-pitfall.md`. Charts and code editors are destroyed before their markup is replaced: `chart-destroy-before-markup-change.md`, `panel-code-fields.md`. Markup inserted by AJAX: `settings-modal-injected-markup.md`. Notices: `liveform-notices-trap.md`.
