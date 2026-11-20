---
name: designer-options-copy-style
description: Options panel "Copy style" and "Paste style" (DP10): the brush, what is never taken, fitting to another kind, one undo step, the copy kept in the browser; the quick strip on Content
---

## Where are "Copy style" and "Paste style"?
- The brush on the identity row (`#sd-id-brush`, beside the three dots) opens a menu: "Copy style", "Paste style". With several elements selected "Copy style" is dimmed; "Paste style" is dimmed until something is copied and is guarded as the canvas menu is (`_sdGuardItem()`).
- Under 300 px the brush is not drawn: the three-dot menu opens with the two entries.
- The selected element's canvas toolbar has a brush (`.sd-tb-brush-btn`): "Copy style" at once. The review list's foot (`designer-options-review.md`) has "Copy style" beside "Reset all".

## What is copied?
- The element's changed Style rows: every control of the Style tab (all its cards, closed ones read from `_sdPanelLazy`, a held tab from `_sdOptHeld`) whose value differs from its default as the dot reads it (`_sdMcChanged()`). Classes (a group, a spacing prefix, a class switch) and props (inline style props among them), a row once.
- Never: a row of Content or Advanced, an attribute, a prop holding content, a link, a picture's address or a data binding (`_SD_STYLE_NEVER`), even from a Style row. A background picture (`data-bg-prop`) is not a copied control.
- Nothing changed: a toast says so and the copy kept stays.

## Where is the copy kept?
- In this browser (`localStorage` `sd_style_copy`, `{ v: 1, kind, rows }`), so it pastes on another page of the design; never sent to the server (D28). Read back only in its own shape (`_sdStyleClipRead()`); without storage it lives while the editor is open.

## What does "Paste style" do?
- Fits the copy to the selection's Style controls (`_sdStyleFit()`): the same row is kept; a class group of another kind whose entries hold the value takes it through its own group (a heading's colour onto an image's colour row); any other row is left out and counted.
- Writes as one undo step: one element `saveState()`, the rows, `_sdRenderNode()`; several elements `_sdMcCommit()` (every selected element). The toast: "Style pasted." or how many options were left out. Nothing fits: a warning, nothing written.
- View mode nudges; a content-level lock refuses with its reason (K4).

## What is the quick strip?
- On Content only, after the element's own section: three cards (52 px), each a Style row's name over its value (a colour with its square, "— Multiple —" in grey italics when several elements differ). A click opens Style with the row's card open and focuses its control (`_sdQuickGo()`, through `_sdOptReveal()`).
- Rows: the family's fixed list (`_SD_QUICK_FIXED`): a text (heading, paragraph, link, button) alignment, font size, colour; an image corner, shadow, bottom spacing; any other box background, corner, shadow. Once this browser counted changes for the kind (`sd_quick_counts`, a change in Style counted per `_sdPanelKind()`), the rows changed most often come first, the fixed list fills the rest (`_sdQuickPick()`). A row the panel lacks is skipped.
- Several elements: drawn only when the kinds are alike. Not drawn while the review view, a search or a jump draws every tab.

## Where is the code and the test?
- Pure helpers: the block "Copy and paste style: pure helpers" of `style_designer.src.js`; the hooks: the block "Copy and paste style, the quick strip: the core's hooks" (`_sdStyleCopy`, `_sdStylePaste`, `_sdStyleBrushPut`, `_sdQuickDraw`). Kept in the core: the strip is drawn with the Content panel.
- `tests/designer_style_copy_test.php`: content, link and binding never taken; a row the target lacks left out and counted; the paste one undo step on one and on three elements; the stored shape; the strip's rows per kind and by count.
- Not tested: the menus, the toast and the strip's drawing in a browser.
