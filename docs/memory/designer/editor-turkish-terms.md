---
name: editor-turkish-terms
description: The names Page Design Studio, Artifact Canvas (was Design Canvas), Designs and their Turkish; English terms the editor's Turkish labels may not keep (legacy, checkbox, alert, Breadcrumb); _sdT() test
---

## What are the editors and the designs called?
- Owner (2026-11-12, #8849, `workflow/decisions-made-panel-6.md`): the visual page editor (code `designer`) is named Page Design Studio ("Sayfa Tasarım Stüdyosu", "Stüdyo" once named), the image editor (code `image_editor`) Design Canvas ("Tasarım Tuvali"), designs Designs ("Tasarımlar"). Not "Visual Page Editor", "Görsel Sayfa Editörü", "Image Editor", "Görsel Düzenleyici" or "Visual Designs" (#8894).
- The image editor's name is Artifact Canvas ("Yapıt Tuvali", "Yapıt Tuvali'nde") (owner, 2026-11-20, `workflow/decisions-made-panel-10.md`). Every text says it: the panel screens since #10517, the canvas itself, the Page Design Studio and the site's edit button since #10669; no `Design Canvas` key is left in the language files. Memory says it since #10697; the owner's quoted words keep the old name. The short word "canvas" stays. Test: `tests/artifact_canvas_name_test.php`.
- Only texts changed: files, addresses, API names, settings and code names keep `image_editor`, `designer`, `visual_designer`, `pg_design_canvas_icon()`, `.pg-dc-icon`, the icon key `design_canvas`.
- The site's edit button on an image keeps the Pinegrap mascot (`ui/brand-logo.md`); its title and alt text say Artifact Canvas.
- `changelog.txt` got no `[NEW]` line for the rename; its earlier lines keep Design Canvas.
- A sentence is rewritten around the name, not patched ("Edit in the Page Design Studio", "Yapıt Tuvali'nde kaydedildi"). Third-party names stay ("Pintura Image Editor").
- A new text uses these names, as a new key with `php tools/tr_add.php`.

- `legacy` → "eski", `checkbox` → "onay kutusu", `alert` → "uyarı", `Attribute` → "Özellik", `Breadcrumb(s)` → "Sayfa Yolu". A file name (`forgot_password.php`) is never shown: say what the page is ("yerleşik parola sıfırlama sayfası").
- Technical binding names that the designer types (`loop_area`, `<code>first_name</code>`) may stay; they are names, not words.

## Where is it checked?
- `tests/designer_canvas_words_test.php`: the Express Order, Cart and Catalog Detail labels (#4331), and since #4779 every `_sdT('…')` key of `style_designer.src.js` against the word list above (#4706). It also checks that the Breadcrumb component's canvas placeholder title goes through `_sdT()`.
- Change a Turkish value with `php tools/tr_add.php --replace`, never by hand (`core/lang-files.md`).

## Which pinegrap.com pages name it?
- None yet: every page is written up to #500, before the editor (`workflow/user-docs-pages.md`). The pages that will: `/files` (File Manager, Edit File), `/design` (Edit Design File), `/visual-page-editor` (Page Design Studio), `/multilingual` (translated pictures), `/workspace` (Channels).
- They are written by a documentation session on the owner's machine (`workflow/user-docs-writing.md`), with the name Artifact Canvas and the Turkish "Yapıt Tuvali".
