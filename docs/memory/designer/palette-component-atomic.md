---
name: palette-component-atomic
description: Palette components explode into a node tree; only btn, badge (atomic) and language_switcher (per request) carry render/toHTML; the two button models, their panel, children in a component button.
---

## What is the palette component rule?
- Everything dropped from the palette **explodes into a real node tree** in `createFromPalette()`. Three components carry `render()` + `toHTML()` in `COMPONENTS`: the atomic `btn` and `badge`, and `language_switcher`, whose output depends on the request (`language-switcher-component.md`). The other twenty-six entries have just `label` / `icon` / `defaultProps`.
- Ten types (navbar, hero, card, carousel, accordion, tabs, pricing, features, progress, alert_box) once carried both: tree on fresh drop, opaque `component` node on pages saved before the explode. Same palette item behaved two ways by page age. Their `render()`/`toHTML()` pairs were deleted (~224 lines).
- **Never write `render()` for a new component**: it explodes into a tree, or if truly a single element it is atomic like `btn`/`badge`. Other palette rules: `palette-component-rules.md`.

## How is a button edited?
- Both models are edited from one panel: `propsButtonLinkOptions()` serves `component`/`btn` and semantic `<a>`/`<button>`; component branch reads props, semantic branch reads `cssClass`. Do not write two panels.
- Variant / Outline / Size rows sit behind `displayStyle === 'btn'`: without `.btn`, `btn-sm` does nothing, so the row is not drawn.
- **The palette's Button item produces semantic** (`<a class="btn btn-primary">`, `createFromPalette()` `ctype === 'btn'`); `component`/`btn` nodes come from ready-made trees: palette components and blocks (card, pricing, hero...), system widget layouts, the navbar's add-button action.

## Can a component button hold children?
- The code disagrees with itself. The panel puts a one-line note on the component model: "A component button holds no child elements. For an icon inside the button, use a <button> from the Semantic palette."
- Yet `canDrop()` lets inline drops into it (`component: ['content', 'component']`; a `btn` is `_isInteractiveNode()`, so `_isInlineOnlyDrag()` decides), `toHTML(p, pad, children)` writes them with the label placed by `textPosition`, and `_render_component_html($props, $pad, $kids)` prints them on the page (link-button-inline-children.md).

## Why do two button models stay on purpose?
- A plain call-to-action button is `component`/`btn` (look in props: variant, outline, size, text). A button with frame attributes or child markup is semantic `<button>`/`<a>` (look in `cssClass`; navbar toggle, close, carousel control, accordion header, tab, dropdown item; measured 21 structural vs 42 plain).
- They are not converted into each other: the component keeps its look in props, the semantic one in `cssClass`, and only the semantic one takes block children.
