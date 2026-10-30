---
name: translation-rules
description: Every visible string uses lang() (designer: _sdT()), English source as the key; keys added only with tools/tr_add.php; exact keys, placeholders, duplicates, misleading keys, check_lang
---

## What must be wrapped in lang()?
- Every user-visible string: `lang('...')` in PHP and the panel, `_sdT('...')` in the visual designer's JavaScript (`sdt-designer-js-text-rules.md`). Code comments stay English; language file values and `lang()` strings are not comments.
- The argument MUST exist as a key of the Turkish language (the shards in `includes/local/tr/`, [[lang-files]]): add the key together with the new string. `lang('X')` returns the Turkish value, or the English string when the key is missing.

## How is a key written?
- The key is the English source string character for character: spaces, punctuation, case and placeholders included. The value is the Turkish text:
```json
"{var:1} is required": "{var:1} gereklidir",
"{var:1|c} is required": "{var:1|c} gereklidir"
```
- Every `{var:N}` of the key stays in the value; its position may move with Turkish word order. A `{suffix:N}` is left out of the Turkish value (`"Item{suffix:1}": "Öğe"`): kept, it glues the English plural ending onto the Turkish word ("kayıt" + "s" = "kayıts"). Syntax: `lang-function-syntax.md`.
- One key, one meaning: a field name and an adjective take separate keys. `Priority` is the field ("Öncelik"); the pricing block's cell is `Priority support` ("Öncelikli destek"). PR #3510.

## How is a key added?
- Only with `php tools/tr_add.php "English" "Türkçe" ["English" "Türkçe" ...]` (or `--from=<file.json>`), never by editing a shard by hand: it puts each key into its shard at its sorted place in the exact form `check_lang.php` demands, and refuses a key already there with another value (`--replace` changes it on purpose). A conflict is settled with `php tools/tr_resolve.php` ([[lang-files]]).
- A key written twice (a hand edit, a merge that kept both sides) fails `check_lang.php`; `php tools/tr_add.php --normalize` rewrites the shard with the later copy, the value `json_decode()` reads, so the text the site shows does not change. Before the check existed, eight keys stood twice in the old single file: a merge had written seven of them twice, and #1015 and #1021 had each added the same marketplace text.
- Files are written through a temporary file and `rename()`: `lang()` reads the language once per request, and a half-written shard does not decode, so every text in it would fall back to English.
- Look for an existing key before adding one: short words already carry translations that are wrong as screen labels (`Net` -> "KDV Hariç", `From` -> "Şundan", `To` -> "Şuna", `Method` -> "Metod"). Use a longer key (`Net change`, `Start`, `End`) rather than changing the old value (`erp/erp-cashflow-report.md`).
- Only Turkish (`includes/local/tr/`) and `en.json` ship; `en.json` only overrides English wording. Software text on a page in another site language: `software-text-in-site-languages.md`.

## How is it checked?
- `php tools/check_lang.php` (CI): every `lang('...')` and `_sdT('...')` key exists in the Turkish language, and `_sdT()` is called with a single-quoted literal only. Each value's placeholders, tags, escaped entities and edge spaces against its key: `tests/tr_placeholders_test.php`.
- It also fails on a shard out of its exact form (`SHARD`) and on a key written more than once (`DUPE` with the key and every line it is on, and a note when the copies' values differ). `json_decode()` keeps the last copy without a word, so a second copy with another value would hide a translation from every other check. The reader is `pg_json_duplicate_keys()` in `includes/fn/core.php` (`tests/lang_file_duplicates_test.php`); it returns null when a string or bracket does not close, and the check then fails instead of reporting a clean file.

Related: docs/memory/core/lang-function-syntax.md, docs/memory/core/lang-files.md
