---
name: editor-turkish-terms
description: The editors' names (Design Canvas, Page Design Studio, Designs) in both languages; English terms the editor's Turkish labels may not keep (legacy, checkbox, alert, Attribute, Breadcrumb); tests
---

## What are the editors called?
- Owner (2026-11-12, #8849, `workflow/decisions-made-panel-6.md`), in every `lang()` and `_sdT()` text since #8894:
  - the image editor is the Design Canvas, "Tasarım Tuvali" (never "Görsel Düzenleyici"); its mark: `ui/design-canvas-icon.md`;
  - the visual page editor, in texts also the visual designer, is the Page Design Studio, "Sayfa Tasarım Stüdyosu" (never "Görsel Sayfa Editörü", "görsel tasarımcı"); its menu row: `ui/main-menu-design-studio-row.md`;
  - designs are Designs, "Tasarımlar", not Visual Designs.
- Only texts: file names, addresses, API and setting names and code identifiers keep `image_editor`, `style_designer`, `designer`. A sentence is rewritten, not patched ("Tasarım Tuvali'nde düzenle"). A third-party product keeps its name ("Pintura Image Editor").
- `tests/design_names_test.php`: no English key and no Turkish value of the shards uses an old name.

## Which words may a Turkish editor label not keep?
- `legacy` → "eski", `checkbox` → "onay kutusu", `alert` → "uyarı", `Attribute` → "Özellik", `Breadcrumb(s)` → "Sayfa Yolu". A file name (`forgot_password.php`) is never shown: say what the page is ("yerleşik parola sıfırlama sayfası").
- Technical binding names that the designer types (`loop_area`, `<code>first_name</code>`) may stay; they are names, not words.

## Where is it checked?
- `tests/designer_canvas_words_test.php`: the Express Order, Cart and Catalog Detail labels (#4331), and since #4779 every `_sdT('…')` key of `style_designer.src.js` against the word list above (#4706). It also checks that the Breadcrumb component's canvas placeholder title goes through `_sdT()`.
- Change a Turkish value with `php tools/tr_add.php --replace`, never by hand (`core/lang-files.md`).
