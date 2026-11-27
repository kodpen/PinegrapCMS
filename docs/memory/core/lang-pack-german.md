---
name: lang-pack-german
description: The German panel pack: offered since 2026.11.0 as "Deutsch", its key count, the top-up of later keys, plurals without {suffix:N}, what the screens showed
module: software_languages
---

## Since when is German offered?
- 2026.11.0: `de` is a line of `pg_software_language_catalog()` with its own name "Deutsch", left to right, week from Monday ([[software-languages]]). Site Settings > General > Software Language, the installer and the browser language (`de-DE`) offer it.
- Its wording is `includes/local/de/<shard>.json`, one file per shard as for Turkish; written by sessions in eight steps (DE1 to DE8, #12149 to #12179), terms from [[lang-pack-german-terms]].

## How complete is it?
- On 2026-11-27, after the top-up: 79,841 of 79,841 Turkish keys, 100 %, `lang_pack.php de --check` 0 problems.
- A key a later PR adds is shown in English in a German panel until a top-up; nothing breaks.

## How are later keys topped up?
- `php tools/lang_pack.php de --missing > <file>` lists what Turkish has and German lacks; the session translates them and writes `php tools/tr_add.php --lang=de --from=<file.json>` ([[lang-pack-translating]]). The DE9 top-up was 139 keys (markets, OSS report, support requests, Xero sync, the import screen).
- A wrong or too long wording is changed with `tr_add.php --lang=de --replace` in a small PR; a button that breaks is fixed in the wording, never in the screen's code.

## Why does a German count never keep {suffix:N}?
- `{suffix:N}` is filled with the English plural ending (`s`), so a German wording keeping it printed "4 Produkts", "6 Bereichs" (seen on the dashboard in DE9). The 136 wordings that kept it now drop it and show the German plural in brackets: `Produkt(e)`, `Seite(n)`, `Bestellung(en)`, `Link(s)`; after vor/nach/um the dative `Tag(en)`; `Datensatz/-sätze`, `Wort/Wörter`.
- A new German wording never keeps `{suffix:N}`; `--check` does not flag it, since dropping is allowed and keeping is not a broken key.

## What did the screens show at 1440 and 390 px?
- DE9 looked at the dashboard, Site Settings panes, an order, All My Pages, the designer and a Workspace channel on the sandbox: no button or table head broke. The designer palette's three tabs (Komponenten, Geteilt, System) are cut with an ellipsis, as Components, Shared, System are in English; the wording was left.
- German runs about a third longer than English; table heads use abbreviations where the term list allows (MwSt., Nr.).
