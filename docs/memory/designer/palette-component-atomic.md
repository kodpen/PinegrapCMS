---
name: palette-component-atomic
description: Palette components either explode into a real node tree or are atomic (only btn and badge have render/toHTML); button two models and propsButtonLinkOptions.
---

## What is the palette component rule?
- Everything dropped from the palette **explodes into a real node tree** in `createFromPalette()`. Only two atomic components remain, `btn` and `badge`, and only they carry `render()` + `toHTML()` in `COMPONENTS`. The other twenty-six entries have just `label` / `icon` / `defaultProps`.
- Ten types (navbar, hero, card, carousel, accordion, tabs, pricing, features, progress, alert_box) once carried both: tree on fresh drop, opaque `component` node on pages saved before the explode. Same palette item behaved two ways by page age. Their `render()`/`toHTML()` pairs were deleted (~224 lines).
- **Never write `render()` for a new component**: it explodes into a tree, or if truly a single element it is atomic like `btn`/`badge`.

## How is a button edited?
- Both models are edited from one panel: `propsButtonLinkOptions()` serves `component`/`btn` and semantic `<a>`/`<button>`; component branch reads props, semantic branch reads `cssClass`. Do not write two panels.
- Variant / Outline / Size rows sit behind `displayStyle === 'btn'`: without `.btn`, `btn-sm` does nothing, so the row is not drawn.
- The restricted model (component, cannot take children) is stated by a one-line note at the top of the panel; semantic buttons carry no note.
- **The palette's "Düğme" item produces semantic**; `component`/`btn` nodes exist only in system widgets' ready-made layouts.

## Why do two button models stay on purpose?
- A plain call-to-action button is `component`/`btn` (variant and size dropdowns, text field). A button with frame attributes or child markup is semantic `<button>` (navbar toggle, close, carousel control, accordion header, tab, dropdown item; measured 21 structural vs 42 plain).
- One cannot be converted to the other: a `component` node has no children, a semantic node has no variant panel.
