---
name: image-focus-value
description: A picture's focus point as a value: the pair of percentages, word pairs read, a value per screen size, how the Image element and a background picture write it, the pg-focus rule
module: image_focus
---

## Where does the value live?
- Image element: `objectPosition` (every screen, as before), `objectPositionTablet`, `objectPositionPhone`. A background picture: inside its node's `style` attribute (`background-position`, plus the custom properties below), so every writer that prints the style already follows.
- Functions: `includes/fn/image_focus.php` (`pg_focus_*()`), twins `_sdFocus*()` beside `_sdImgDim()` in `style_designer.src.js`. Test: `tests/designer_image_focus_test.php` runs both on the same cases (PR #IF1PR).

## How is a value read and written?
- `pg_focus_parse()` reads `30% 20%`, one percentage (y 50 %), `0`, and the words left, center, right, top, bottom in either order (`top center` = `50% 0%`, `left 20%`). Clamped to 0..100. A length (`10px`), three parts or a vertical word before a number (`top 20%`) is null.
- `pg_focus_format()` writes whole percentages (`round()`). A value typed before is stored as typed until it is moved: nothing rewrites it.

## How do the screen sizes work?
- `pg_focus_screen(width)`: below 768 px phone, below 992 tablet, else desktop; the full canvas (0) is desktop. Canvas sizes 375 and 576 are phone, 768 tablet, 992/1200/full desktop.
- A screen with no value shows the next larger one's (`pg_focus_for_screen()`). `pg_focus_same_everywhere()` keeps the desktop's and removes the others.

## What markup does it give?
- `pg_focus_decls(values, prop)`: with no tablet or phone value, `object-position:<value>` exactly as before (no focus set: nothing). Byte for byte the old output.
- With one: `object-position:var(--pg-focus,D);--pg-focus-d:D;--pg-focus-t:T;--pg-focus-p:P`, each filled from the larger screen (so a nested element never inherits another's), plus the class `pg-focus`. Without the rule the desktop value shows.
- The rule is the block rule `pg-focus` in `_SD_BLOCK_CSS`: it sets `--pg-focus` per media query (991.98 px, 767.98 px). Whatever writes a per screen value adds it to User Styles: `_sdEnsureBlockStyles(['pg-focus'])` in the editor, `pg_designer_block_css(['pg-focus'], $existing)` on the server.
- An unreadable tablet or phone value is left out, so only percentages and words reach the custom properties.

## Which writers use it?
- Image: the page's `_render_content_html()`, the editor's `toHTML()`, the canvas's `render()` and the `_inlineBase` of `buildNodeEl()`; `tests/designer_image_size_test.php` holds a per screen case for all three.
- Background: `pg_focus_style_read()` / `pg_focus_style_write()` (twins `_sdFocusStyleRead/Write()`) read and replace `background-position` in the style text in place; a `;` inside `url("data:…")` is kept. The caller adds or removes `pg-focus` in the node's classes by `pg_focus_has_screens()`.

Related: docs/memory/designer/image-size-props.md, docs/memory/designer/designer-block-style.md, docs/memory/plans/designer-image-focus.md
