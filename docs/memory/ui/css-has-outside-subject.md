---
name: css-has-outside-subject
description: Never write :has() outside a selector's subject, nor :has() over :hover or the canvas selection; what each cost the visual designer (issue #11987) and what replaced them
---

## Why is a :has() outside the subject a trap?
- In `A:has(x) > B` the `:has()` is on `A`, not on the element styled. Chromium cannot tell which `A` a DOM change affects, so any insertion or class change restyles every element `B` can match under the body.
- `backend.src.css` had `.pg-set-card .row > * + [class]:not(.collapse):has(> .row:only-child) > .row > [class]…` (settings cards). Every `[class]` element of every panel screen was restyled after each DOM change: in the visual designer, about 4,000 elements (the Layers tree, the palette) on every click, scroll and toast.
- It is now `… > [class]:not(.collapse) > .row:only-child > [class]…`, which matches the same elements.
- Write the condition on the element itself (`X > .row:only-child`), or give the parent a class from script. A `:has()` on the subject (`.card:has(> .x)`) is fine.

## Why does the canvas not use :has() for hover and selection?
- `:hover:not(:has(.sd-wrap:hover))` (innermost hovered wrapper) and `.sd-shared-wrap:has(.sd-wrap.sd-selected)` (a part holding the selection) made Chromium match `:has()` through each hovered or part wrapper's subtree. In the Store template every pointer move and selection restyled the whole canvas (1,700 elements, 15-23 ms on the sandbox, two or three times a click).
- The canvas mouseover handler now writes `.sd-hov` on the innermost wrapper and `.sd-part-hov` on the innermost part under the pointer (`_sdHoverMark()`); the rules read `.sd-hov.sd-hov:hover`, keeping `:hover` and the old specificity. Leaving the canvas clears both.
- `_sdPartSelMark()` writes `.sd-part-sel` on every part over a selected wrapper; `renderSelectionOnly()`, `render()` and `_sdPlaceSelectedChrome()` call it, and only what changes is written.
- A new canvas rule for the hovered or selected element reads these classes, never a `:has()` over `:hover` or `.sd-selected`.

## How was it found?
- A Chromium trace with `disabled-by-default-devtools.timeline.invalidationTracking`: `ScheduleStyleInvalidationTracking` with `changedPseudo: has` on BODY, then thousands of "Invalidation set matched attribute". Switching each `:has()` rule off in turn (setting its `selectorText`) and timing one insertion found the rule.

Related: docs/memory/designer/designer-performance.md, docs/memory/designer/designer-selection-one-layout.md
