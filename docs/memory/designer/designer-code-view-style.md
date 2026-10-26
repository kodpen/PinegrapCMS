---
name: designer-code-view-style
description: A style typed in the designer's code view: which declarations go back to the font, image and Display & Sizing props, which stay the Attributes panel's style, and why (sizing printed twice before)
---

## Where does a typed style go?
- `_sdCodeSetAttr(node, 'style', was, now)`: the old `inlineStyle` field is folded in, then `_sdCodeTakeSizing()` hands the Display & Sizing declarations back to their props, then `_sdApplyInlineStyle()` splits the rest as the Attributes panel does (font as its bare family, `'X',sans-serif` read as X; an image's `object-position` and unit width/height, an icon's size to props; the rest to `_attrs.style`). The root node's style is `_attrs.style` as typed.

## How are the sizing declarations found?
- The emitter prints one style: font, `inlineStyle`, the type's own style, `getNodeExtraStyle()` (z-index, width, min/max, height, min/max, overflow, offsets, `--bs-aspect-ratio`), then `_attrs.style`. `_sdCodeTakeSizing()` checks that the printed text holds the props' sizing just before the user style; else (a component's own markup, an image in its ratio box, where the box takes the sizing) the typed style is used as it is.
- Each printed sizing declaration is matched by name and occurrence in the typed style: changed sets the prop, deleted clears it, unchanged keeps the stored spelling (`'600'` printed as `600px` stays `'600'`). A sizing property typed where none was printed is the Attributes panel's style, as in the panel's style row.
- An image's own width/height stay with `_sdInlineStyleProps()` (attribute or unit, image-size-props.md); its other sizing goes back here.

## What was wrong before?
- Every printed declaration went to `_attrs.style`: the sizing was printed twice (prop, then the copy, which wins), the Sizing panel showed the old value, and deleting a width in the code view changed nothing. Test: `tests/designer_code_view_attrs_test.php`.
