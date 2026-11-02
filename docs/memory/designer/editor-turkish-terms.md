---
name: editor-turkish-terms
description: Which English terms the visual editor's Turkish labels may not keep (legacy, checkbox, alert, Attribute, Breadcrumb, file names) and the test that checks every _sdT() key
---

## Which words may a Turkish editor label not keep?
- `legacy` → "eski", `checkbox` → "onay kutusu", `alert` → "uyarı", `Attribute` → "Özellik", `Breadcrumb(s)` → "Sayfa Yolu". A file name (`forgot_password.php`) is never shown: say what the page is ("yerleşik parola sıfırlama sayfası").
- Technical binding names that the designer types (`loop_area`, `<code>first_name</code>`) may stay; they are names, not words.

## Where is it checked?
- `tests/designer_canvas_words_test.php`: the Express Order, Cart and Catalog Detail labels (#4331), and since #4779 every `_sdT('…')` key of `style_designer.src.js` against the word list above (#4706). It also checks that the Breadcrumb component's canvas placeholder title goes through `_sdT()`.
- Change a Turkish value with `php tools/tr_add.php --replace`, never by hand (`core/lang-files.md`).
