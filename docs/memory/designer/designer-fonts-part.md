---
name: designer-fonts-part
description: The Google Fonts browser of the designer as the part 'fonts' (D5 part 6): what moved, what the core keeps, the host it works through
---

## What is in the part?
- `assets/js/style_designer_fonts.js` (bundle `style_designer_fonts.min.js`, part `fonts` in `pg_designer_parts()`): the `#sd-gf-modal` dialog (built on the first open, with its close button, search field and capturing Escape listener), the catalogue list (`renderGfBrowser()`), the detail with the preview and the weights (`openGfPicker()`), Add / Update / Remove.
- Handed to every editor (`designer_screen.php`); it runs the first time "Add a Google Font" is asked for (the Fonts section's menu, the command palette's `act_add_font`).

## What does the core keep?
- The design's font list `gfAdded`, its field (`parseGfField()`, `saveGfField()`), the Fonts section of the assets panel (`renderFontList()`), the importer's font merge and `GOOGLE_FONTS` / `injectGoogleFonts()` (the canvas needs them at open).
- `openGfModal()` is a stub: `_sdPartCall('fonts', 'open', [_gfHost]).catch(_sdPartFailed)`.

## How does the part reach the font list?
- `_gfHost` (inside `initAssetsPanel()`): `added()` the live list, `set(list)` stores a new list, saves the field and redraws the Fonts section, `catalogue()` the font names. Entry points: `open(host)` and `pick(host, family)` (the detail of one font).
- Tests: `tests/designer_parts_test.php` (`fonts` cases) runs the part under Node on a fake host; `tests/designer_chrome_names_test.php` reads the close button's name in the part file.
