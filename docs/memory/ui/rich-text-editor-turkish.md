---
name: rich-text-editor-turkish
description: CKEditor 4's own Turkish texts (lang/tr.js): the Media Embed dialog, the Anchor (Çapa) button, shortcut tips; the stamp that makes browsers fetch it again
module: editor
---

## Where do the editor's own texts come from?
- From `assets/lib/ckeditor_4_20/lang/tr.js` (and `en.js`), loaded by `ckeditor.js` for `CKEDITOR.config.language`, not from the panel's `lang()` shards. A wrong text is corrected in that file by hand; `tools/tr_add.php` does not reach it.
- Words written the same in both languages stay: URL, Form, Normal, IFrame, Link (the Link button's own name, "Link Ekle/Düzenle").

## What was corrected? (#9120)
- Media Embed ("Farklı sitelerden medya içeriği ekle"): "Başlık (ekran okuyucular için) [Zorunlu]:", "URL [Zorunlu]:", "Yok", "Boyutlandırma yapma (varsayılanı kullan)", the empty-title error "Erişilebilirlik için medya başlığı zorunludur.". The hint names YouTube, Vimeo and Flickr; Qik, Viddler and My Opera are closed and Hulu no longer embeds (en.js too).
- The flag button (Anchor) read "Bağlantı Ekle/Düzenle", like Link beside it: now "Çapa Ekle/Düzenle", its dialog "Çapa Özellikleri" / "Çapa Adı", "Çapayı Düzenle", "Çapayı Kaldır"; the Link dialog's "Çapa Seç" and "Çapa Adı ile"; the page's placeholder "Çapa". The panel's own word for an anchor is "Çapa" too.
- "Üst Karakter Tuşu" (was "Üst Karater", in every shortcut tip), "Sekme sırasından çıkar", "Zengin Metin Editörü", font and table voice labels, Çam Yeşili, clipboard file-format notices, "Boyutlandırılabilir".
- `tests/ckeditor_turkish_texts_test.php` fails on any text tr.js still shares with en.js outside the list above.

## Why does the page set CKEDITOR.timestamp?
- CKEditor adds `?t=<stamp>` to every file it loads after `ckeditor.js` (language file, skin, plugin dialogs), and the bundle's stamp (`M8SC`) never changes, so a browser kept the old `tr.js`. `get_wysiwyg_editor_code()` sets `CKEDITOR.timestamp` to `pg_ckeditor_timestamp()`: the newer modification time of `en.js` and `tr.js` in base 36.

Related: [[rich-text-editor-names]].
