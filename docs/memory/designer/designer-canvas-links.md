---
name: designer-canvas-links
description: Canvas links never navigate in the designer, including #fragment, because of the <base> tag; Bootstrap-bound links; why the editor opens dropdowns itself.
---

## Why can no link navigate on the canvas, even `#fragment`?
- The canvas document's `<head>` has `<base href="<site root>">` so root-relative asset paths resolve to the real site. **With `<base>`, `href="#section"` is not an in-document anchor**: it resolves to `<site>/#section`, a different document from the canvas address, and the iframe goes there. "Fragment is harmless, it scrolls" is wrong here.
- The click handler does **unconditional** `preventDefault()` for `<a href>`. A fragment still shows its meaning: the target is found via `canvasDoc.getElementById` and `scrollIntoView`'d.
- **Bootstrap-bound links are not exempt.** `preventDefault()` does not stop propagation; Bootstrap's delegated handlers still receive the event and tabs/accordions/dropdowns work. An exemption would leave the default action alive if Bootstrap throws before reaching its own `preventDefault`.
- Middle click is a separate gate: `auxclick` is also closed. Keyboard activation produces `click`, needs no extra handler.

## Why does the editor open dropdowns instead of Bootstrap?
- Bootstrap Dropdown looks for the menu as the trigger's **sibling**; on the canvas `.sd-wrap` sits in between, so it finds none and throws (`display:contents` does not change DOM sibling relations; this is the sibling version of a direct-child assumption).
- For `[data-bs-toggle="dropdown"]` the click handler finds the menu via scope, toggles `show` itself, and `stopPropagation()`s so Bootstrap never sees it.
- **The menu stays open** so the operator can select and edit items inside; Bootstrap would close it on the next click.
- When adding a new Bootstrap JS component, check whether it depends on a sibling/child relationship.
