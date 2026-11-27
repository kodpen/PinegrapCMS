---
name: lang-pack-translating
description: How a session translates a piece of a panel language pack: lang_pack.php --count/--missing/--check, writing with tr_add.php --lang, the rules a wording keeps, what check_lang.php fails on
module: lang_pack
---

## Which tools does a pack use?
- `php tools/lang_pack.php <code> --count`: a shard's Turkish keys, translated, missing, extra (keys Turkish lacks) and the share done.
- `--missing [--shard=<x>] [--limit=<n>]`: a JSON array of `{"key", "tr"}` for each key Turkish has and the language lacks, in key order. Turkish is the full list; English is the keys.
- `--check`: each key Turkish does not have and each broken wording; exit 1 when one is printed.
- Writing only with `php tools/tr_add.php --lang=<code> --from=<file.json>` (a JSON object key => wording): never by hand, never `--replace` over another session's wording without a reason.
- The helpers are `includes/fn/lang_pack.php`: `pg_lang_wording_problems()`, `pg_lang_pack_missing()`, `pg_lang_pack_counts()`, `pg_lang_pack_problems()` (`tests/lang_pack_test.php`).

## How is a piece translated?
- By the session itself: no outside service, no text sent out.
- Take one shard, or a `--limit` of it, with `--missing`; write the object key => wording in the scratchpad; add it with `tr_add.php --lang=<code> --from=`; run `lang_pack.php <code> --check` and `check_lang.php`.
- The key's English is the source; the Turkish beside it only tells a short key's meaning (`Net` is "KDV Hariç", a net amount; `Book` is a verb).
- Keep every `{var:N}` (it may move), `%s`/`%1$s`, `{name}` tokens, tags, `&lt;`-style entities, the start and end spaces, and the last punctuation mark. A `{suffix:N}` may be dropped, never added.
- Product names, code, units and file types are not translated.
- Formal address, the terms file's word for each term (German: [[lang-pack-german-terms]]), a length near the English: a panel button has no room for a sentence.

## What do the checks fail on?
- `check_lang.php` reads every language under `includes/local/` besides `tr` and `en.json`: a folder's shards in their exact form (`SHARD`), each key once (`DUPE`), and `PACK` lines for a key Turkish lacks or a wording that breaks its key (`pg_lang_pack_problems()`).
- A key the language lacks is no fault: `lang()` shows the English. A key a later PR adds stays English there until a top-up.
- A folder and a `<code>.json` side by side fail, as for Turkish.

## When is a language offered?
- Only when its code is in the shipped catalogue (`pg_software_language_catalog()`) and its wording is on disk ([[software-languages]]). Wording without the catalogue line is read on site pages in that language but not offered in the panel.
