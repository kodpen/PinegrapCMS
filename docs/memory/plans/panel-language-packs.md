---
name: panel-language-packs
description: Plan (owner 2026-11-27, poll 247): the panel in further languages, first the mechanism (one list of languages, the column's type, the checks, the pack tool); German follows in its own plan
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 247, `workflow/decisions-made-product-117.md`): "Önce düzenek ve Almanca". First what a further language needs, then German; his answer is the yes for the change of the column's type below.
- Arabic and the right-to-left panel, and further languages, come after German in plans of their own. A panel language per user is not in this work: it needs a column.

## What stands today? (counted in the code on 2026-11-27)
- The panel's language is one setting a site, `config.software_language`, an `ENUM('en','tr')` (`install/index.php`). `lang()` reads `includes/local/<code>/` through `pg_lang_map()` (`core/lang-files.md`); a key with no wording shows its English.
- English and Turkish are named one by one in: the Site Settings select (`includes/settings/prep.php`), the installer's select and `install_cli.php`, `router.php` (the `local` parameter and `dedect_user_language()`), `tools/check_lang.php`, and six PHP and five script comparisons with `'tr'` (date and number forms, sort order).
- Turkish has 79,407 keys in 28 shards; `tools/tr_add.php --lang=<code>` already writes another language's shards. A language with a folder is used on site pages in that language too: the owner was told so in poll 247.

## Which steps? (S under 400 lines, M 400 to 1,000; each waits for the one before it)
- PL1 (M) One list of the panel's languages and the column.
  - A new module `includes/fn/software_languages.php`: `pg_software_languages()` gives each offered language's code, its own name and its direction, from one shipped list (English and Turkish at first) cut to those whose wording is on disk.
  - Every place named above reads that list: the two selects, the saving of the setting (a code outside the list is refused), the installer, `router.php`. Each comparison with `'tr'` is read and either kept with its reason or turned into a property of the language.
  - A migration step in the open version turns `config.software_language` into `VARCHAR(10) NOT NULL DEFAULT 'en'` with `install_modify_column()`; the installer creates it so. Stored values stay.
  - The panel's `<html lang>` takes the code. Tests for the list and a refused code; the step run twice on a dev database.
- PL2 (M) The checks and the pack tool.
  - `tools/check_lang.php` checks every language folder's form. For a language other than Turkish a missing key is no fault; a key Turkish does not have, or a wording whose `{var:N}`, `%s`, tags or entities differ from its key's, is one.
  - `tools/lang_pack.php <code>`: `--missing [--shard=<x>] [--limit=<n>]` prints the keys Turkish has and the language lacks, each with the Turkish beside it, as JSON; `--check` runs the wording checks; `--count` the figures a shard. Writing stays `tr_add.php --lang=<code> --from=<file>`.
  - Memory `core/lang-pack-translating.md`: how a session translates a piece (below), and `core/lang-pack-german-terms.md`: the German of the panel's 150 most used terms, counted from the keys, so every step uses the same word.
- PL3 (S) Close-out after `plans/panel-language-german.md` has ended: the lasting rules into `core/`, this file and that one deleted.

## How is a pack translated?
- By the session itself, read by nobody line by line: no outside service, no text sent out.
- The key's English is the source and the Turkish beside it tells the meaning of a short key. Placeholders, tags, entities, spaces at the ends and the last punctuation mark stay as they are; product names, code and units are not translated.
- Formal address, the terms file's words, a length near the English.

## What do the steps keep?
- No other schema change; `lang()` and the shards' form stay; Turkish keys still only through `tr_add.php`.
- A key a later PR adds shows English in a further language until a top-up: no fault of that PR.
