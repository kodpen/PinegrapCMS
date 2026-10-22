---
name: panel-layout-pitfalls
description: Panel CSS and JS traps that fail silently, with their files: d-none not hidden, Popper in scroll boxes, flex overflow and min-height 0, nested :has(), forms and offcanvas, datepicker, multiselect.
---

## How do I hide and show an element?
- With `d-none`, never `hidden` / `el.hidden = true`: Bootstrap's display utilities (`d-flex`, `d-block`) carry `!important` and beat the `[hidden]` rule, so such an element stays on screen (`step-rail.md`).

## How is a dropdown placed inside a scrolling box?
- Popper with `strategy: "fixed"`: an absolutely positioned menu is clipped by the scroll box (`dashboard-sales-map-panel.md`). A menu a screen opens itself on `.dropdown-menu` takes its keys on `window` (`own-menu-dropdown-keydown.md`).

## Which layouts collapse only on narrow screens?
- `overflow` other than `visible` on a flex item drops its automatic minimum size: give the axis a floor first (`flex-overflow-hidden-pitfall.md`).
- Every flex item of a scroll chain needs `min-height: 0`, or the inner box never scrolls and the save bar falls below the screen; write `100dvh` after `100vh`, inside `@supports` (`settings-modal-layout.md`).

## Which CSS mistakes drop without a word?
- `:has()` inside `:has()` is invalid and the whole rule is dropped; a rule that overrides a `:has()` rule must carry the same predicate (`settings-card-layout-pitfalls.md`).
- A class string missing a space (`col-xxl-6col-lg-3`) is one unknown class: the cell stays full width at every size (`settings-card-field-widths.md`).
- `alert-dismissible` never takes `py-*`: its close button is placed absolutely and falls out of a shorter box (`settings-card-chrome.md`).

## What about forms, panels and pickers?
- Offcanvas panels stay inside the `<form>` (offcanvas moves nothing in the DOM, so their fields submit); anything with a form of its own stays outside the main form. A jQuery UI datepicker in an offcanvas or modal relies on `#ui-datepicker-div { z-index: 1065 !important }`. `multiselectCheckbox` fires no event per box: bind behaviour to state and re-sync on every change (`user-permission-rows.md`).

## Which JS traps have files of their own?
- Sortables: `sortable-init-pitfall.md`. Charts and code editors are destroyed before their markup is replaced: `chart-destroy-before-markup-change.md`, `panel-code-fields.md`. Markup inserted by AJAX: `settings-modal-injected-markup.md`. Notices: `liveform-notices-trap.md`.
