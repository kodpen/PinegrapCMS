---
name: translation-rules
description: Every visible string uses lang() (designer JS: _sdT()), English source as tr.json key; tr.json: exact keys, placeholders, append only, duplicates, atomic writes, misleading keys, check_lang.php
---

## What must be wrapped in lang()?
- Every user-visible string: `lang('...')` in PHP and the panel, `_sdT('...')` in the visual designer's JavaScript (`sdt-designer-js-text-rules.md`). Code comments stay English; `tr.json` values and `lang()` strings are not comments.
- The argument MUST exist as a key in `includes/local/tr.json`: add the key together with the new string. `lang('X')` returns the tr.json value, or the English string when the key is missing.

## How is a tr.json key written?
- The key is the English source string character for character: spaces, punctuation, case and placeholders included. The value is the Turkish text:
```json
"{var:1} is required": "{var:1} gereklidir",
"{var:1|c} is required": "{var:1|c} gereklidir"
```
- Every `{var:N}` of the key stays in the value; its position may move with Turkish word order. A `{suffix:N}` is left out of the Turkish value (`"Item{suffix:1}": "Öğe"`): kept, it glues the English plural ending onto the Turkish word ("kayıt" + "s" = "kayıts"). Syntax: `lang-function-syntax.md`.

## How is tr.json edited?
- A new key goes at the END of the file. Do not reformat or sort the file: it is shared, and parallel branches only append to it (`workflow/git-branches-and-shared-files.md`).
- The one change made in place: removing a duplicate key. Delete the earlier copy, whose value `json_decode()` ignores; the later copy keeps its line and value, so the text the site shows does not change. Before the check below existed, eight keys stood twice: a merge that kept both sides had written seven of them twice, and two PRs (#1015, #1021) had each added the same marketplace text.
- A script that writes it writes a temporary file and `rename()`s it into place: `lang()` reads the file once per request, and a half-written file does not decode, so every text on that page falls back to English.
- Look for an existing key before adding one: short words already carry translations that are wrong as screen labels (`Net` -> "KDV Hariç", `From` -> "Şundan", `To` -> "Şuna", `Method` -> "Metod"). Use a longer key (`Net change`, `Start`, `End`) rather than changing the old value (`erp/erp-cashflow-report.md`).
- Only `tr.json` and `en.json` ship; `en.json` only overrides English wording. Software text on a page in another site language: `software-text-in-site-languages.md`.

## How is it checked?
- `php tools/check_lang.php` (CI): every `lang('...')` and `_sdT('...')` key exists in tr.json, and `_sdT()` is called with a single-quoted literal only. It does not compare the placeholders of a value with its key.
- It also fails when tr.json holds a key more than once (`DUPE` with the key and every line it is on, and a note when the copies' values differ). `json_decode()` keeps the last copy without a word, so a second copy with another value would hide a translation from every other check. The reader is `pg_json_duplicate_keys()` in `includes/fn/core.php` (`tests/lang_file_duplicates_test.php`); it returns null when a string or bracket does not close, and the check then fails instead of reporting a clean file.

Related: docs/memory/core/lang-function-syntax.md
