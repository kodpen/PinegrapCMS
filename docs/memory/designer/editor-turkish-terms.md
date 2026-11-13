---
name: editor-turkish-terms
description: The names Page Design Studio, Design Canvas, Designs and their Turkish; English terms the editor's Turkish labels may not keep (legacy, checkbox, alert, Breadcrumb); _sdT() test
---

## What are the editors and the designs called?
- Owner (2026-11-12, #8849, `workflow/decisions-made-panel-6.md`): the visual page editor (code `designer`) is named Page Design Studio ("Sayfa Tasarım Stüdyosu", "Stüdyo" once named), the image editor (code `image_editor`) Design Canvas ("Tasarım Tuvali"), designs Designs ("Tasarımlar"). Not "Visual Page Editor", "Görsel Sayfa Editörü", "Image Editor", "Görsel Düzenleyici" or "Visual Designs" (#8894).
- Only texts changed: files, addresses, API names, settings and code names keep `image_editor`, `designer`, `visual_designer`.
- A sentence is rewritten around the name, not patched ("Edit in the Page Design Studio", "Tasarım Tuvali'nde kaydedildi"). Third-party names stay ("Pintura Image Editor").
- A new text uses these names, as a new key with `php tools/tr_add.php`.

- `legacy` → "eski", `checkbox` → "onay kutusu", `alert` → "uyarı", `Attribute` → "Özellik", `Breadcrumb(s)` → "Sayfa Yolu". A file name (`forgot_password.php`) is never shown: say what the page is ("yerleşik parola sıfırlama sayfası").
- Technical binding names that the designer types (`loop_area`, `<code>first_name</code>`) may stay; they are names, not words.

## Where is it checked?
- `tests/designer_canvas_words_test.php`: the Express Order, Cart and Catalog Detail labels (#4331), and since #4779 every `_sdT('…')` key of `style_designer.src.js` against the word list above (#4706). It also checks that the Breadcrumb component's canvas placeholder title goes through `_sdT()`.
- Change a Turkish value with `php tools/tr_add.php --replace`, never by hand (`core/lang-files.md`).
