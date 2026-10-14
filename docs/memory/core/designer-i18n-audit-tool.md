---
name: designer-i18n-audit-tool
description: docs/_tools/designer_i18n.php check/extract/apply workflow, how Turkish is detected, and the three classes allowed in skip.
---

## How do I audit or convert designer text?
- Audit: `php docs/_tools/designer_i18n.php check`. It reports keys missing from tr.json, remaining Turkish literals, text embedded in HTML, Turkish comments, and PHP trees.
- Conversion goes through a table: `extract` -> fill the `en` column of `docs/_tools/designer_i18n_map.tsv` -> `apply`. The table is the single source of TR->EN terminology.

## How is Turkish detected?
- `tr.json` itself is the dictionary. Words without diacritics ("Altta", "Standart", "Kurumsal") escaped a hand-written list, so `has_turkish()` counts as Turkish every word that appears in Turkish values but not in English keys.
- Comments do NOT use this dictionary (`has_turkish_prose()`): words like "chrome", "strike", "stem" also occur on the Turkish side of tr.json.
- In comments, quoted text and paths (`/siparis`, `'Kayıt bulunamadı.'`) are what the comment talks about, not its language; they are stripped before the check.

## What may go in skip?
- `skip` is only for three classes: (1) recognition dictionaries (the SEO generic link-text list); (2) raw database values (`complete / exported / paid / tamamlandı`); (3) old stored values the code matches (`'Kayıt bulunamadı.'`, `'Faz 1'`).
- Palette component sample content (sample name, address, email, price) is NOT skip; it is translated.

Related: docs/memory/core/sdt-designer-js-text-rules.md
