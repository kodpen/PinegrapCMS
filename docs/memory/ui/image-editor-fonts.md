---
name: image-editor-fonts
description: The image editor's Google Fonts choice in the Text tool and the one font catalogue it shares with the visual designer
module: image_editor
---

## Where is the Google Fonts list?
- `assets/js/pg_google_fonts.js` sets `window.PgGoogleFonts` (`order`, `categories`: sans, serif, display, hand, mono; `all()`): about two hundred families, most popular first, each once. It ships in the code (no API key, no live fetch).
- It is the first source of both `style_designer.min.js` and `pg_image_editor.min.js` (`pg_js_bundles()`); the designer's `GOOGLE_FONTS` is `['Default']` plus `all()`, with no copy of its own. `tests/js_bundles_test.php` lets this one file sit in two bundles.

## How does the Text tool offer it?
- Under the font tiles, "Choose from Google Fonts" opens a sheet (`pg_image_editor_fonts.js`): a search box, the categories as one scrolling row, and a listbox of families, each row in its own face with `Ağ Iı Şş Çç`. A row's stylesheet is linked only when it scrolls into view (IntersectionObserver, `loadTextFont()`), not all at once.
- Keys stay in the sheet: arrows, Page Up/Down, Home/End move the choice (`aria-activedescendant`), Enter takes it, Escape closes, Tab goes round the sheet.
- A choice sets `font: 'g:<family>'` (no new layer field; the save waits for the face as for site fonts) and joins the last five families (`M.fontRecent()`, browser storage `pgie-recent-fonts`), shown as tiles after the site's fonts.
- The overlay's grid row is the overlay's height (`.pgie-gfwrap`), else the row grows to the whole list and the box lands off screen on a phone.
- `linkFamily()` asks for several weights; a family without one of them is refused whole by Google, so on error its regular face is linked instead.
- Tests: `tests/image_editor_google_fonts_test.php` (the catalogue, one source, `fontSearch()`, `fontRecent()`).
