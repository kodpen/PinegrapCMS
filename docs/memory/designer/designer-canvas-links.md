---
name: designer-canvas-links
description: Canvas links never navigate in the designer, even #fragment, because of the <base> tag; Bootstrap-bound links; the editor opens dropdowns itself, guarded on the canvas window, edited items too.
---

## Why can no link navigate on the canvas, even `#fragment`?
- The canvas document's `<head>` has `<base href="<site root>">` so root-relative asset paths resolve to the real site. **With `<base>`, `href="#section"` is not an in-document anchor**: it resolves to `<site>/#section`, a different document from the canvas address, and the iframe goes there. "Fragment is harmless, it scrolls" is wrong here.
- The click handler does **unconditional** `preventDefault()` for `<a href>`. A fragment still shows its meaning: the target is found via `canvasDoc.getElementById` and `scrollIntoView`'d.
- **Bootstrap-bound links are not exempt.** `preventDefault()` does not stop propagation; Bootstrap's delegated handlers still receive the event and tabs/accordions/dropdowns work. An exemption would leave the default action alive if Bootstrap throws before reaching its own `preventDefault`.
- Middle click is a separate gate: `auxclick` is also closed. Keyboard activation produces `click`, needs no extra handler.

## Why does the editor open dropdowns instead of Bootstrap?
- Bootstrap Dropdown looks for the menu as the trigger's **sibling**; on the canvas `.sd-wrap` sits in between, so it finds none and throws (`display:contents` does not change DOM sibling relations; this is the sibling version of a direct-child assumption).
- For `[data-bs-toggle="dropdown"]` the editor finds the menu via scope and toggles `show` itself; one menu is open at a time.
- **The menu stays open** so the operator can select and edit items inside; Bootstrap would close it on the next click. A redraw keeps it open on the canvas only, never in the tree (designer-canvas-open-state.md).
- When adding a new Bootstrap JS component, check whether it depends on a sibling/child relationship.

## Why is the dropdown guard on the canvas window, not the document?
- Bootstrap 5.3 registers its delegated data-api handlers on `document` with **capture = true**. A capture listener of the editor on the document sat beside them: `stopPropagation()` never stops another listener on the same node, so Bootstrap still ran and threw "Cannot read properties of null (reading 'classList')" on every toggle click and every Escape. Where toggle and menu are siblings in one node (the language switcher component) Bootstrap opened the menu and the editor closed it in the same click.
- The guard is a capture listener on `canvasDoc.defaultView`, which runs before any document listener; stopping the event there keeps Bootstrap out.
- The same window guard takes `keydown` Escape / ArrowUp / ArrowDown on a toggle or inside a `.dropdown-menu`: Escape closes the open menus, other keys go on to the editor's shortcuts (`_sdShortcutKeydown()`, called directly because the document listener no longer sees the event).
- A dropdown item or toggle being edited in place (`isContentEditable`) is stopped there too (`_sdDdEditKey()`), but its own key handler runs first: the editors store it on the element as `_sdEditKeydown` (`setupInlineEditing()`, `_createVirtualText()`), and what it lets bubble goes on to `_sdShortcutKeydown()`. Left to the document, Bootstrap cancelled the key (the caret did not move, typing replaced the whole text) and threw "Cannot read properties of undefined (reading 'parentNode')": it looks for the toggle beside the menu, inside its wrapper there is none. Escape ends the edit and leaves the menu open; a second Escape closes it.
- A click on a dropdown toggle does not select it; a `<button>` toggle is still selected on mousedown.
