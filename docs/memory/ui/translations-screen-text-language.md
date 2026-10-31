---
name: translations-screen-text-language
description: Translations screen (translations.php): which elements carry lang and dir="rtl" so screen readers and right-to-left translators get the text's own language
---

## Which elements carry a language?
The panel's `<html lang>` is the panel language, so every element of `translations.php` that holds site text says its own language through the local `$text_lang($code)` closure: ` lang="…"`, plus ` dir="rtl"` when the code's `pg_tr_known_languages()` row has index 5 === 1 (ar, he, fa, ur; the same test `includes/translate/render.php` uses for the site's `dir`). An empty code gives nothing.
- Source language (`$source`): the row's `.pg-tr-source` text, the glossary row's term, the glossary dialog's Term field `#pg_tr_term`.
- Target language (`$language`): the row's `.pg-tr-target` box, the glossary row's translation, the dialog's "Translation to use" field `#pg_tr_term_translation`.
- Each language's own code: the language name in the switcher items and in the current-language button (the "Name shown to visitors", by default the native name).

## Why?
WCAG 3.1.2: without `lang` a screen reader read a German or Arabic text in the Turkish voice, and an Arabic or Hebrew translator typed into a left-to-right box (left-aligned, end punctuation on the wrong side).

`translations.js` only fills the dialog's values and never redraws the rows, so the markup holds. Pinned by `tests/translations_screen_language_test.php`.
