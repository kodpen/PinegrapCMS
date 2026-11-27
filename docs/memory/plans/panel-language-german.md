---
name: panel-language-german
description: Plan (owner 2026-11-27, poll 247): the German language pack, 79,407 keys in eight steps side by side by shard; the mechanism has landed and the eight steps are free, DE9 waits for them
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 247, `workflow/decisions-made-product-117.md`): German is the first further language of the panel. The mechanism and the rules of translating: `plans/panel-language-packs.md`.

## When do the steps start?
- The architect marks lines `free` by a docs PR. PL2 of `plans/panel-language-packs.md` has landed (#12091), so DE1 to DE8 are marked (2026-11-27); DE9 is marked when the eight have landed.
- The eight translating steps run side by side: each writes only its own shards under `includes/local/de/`, so they never meet in a file. One worker a step.

## Which translating steps are there? (keys counted on 2026-11-27; a language file does not count in a PR's product lines)
- DE1 `free`: shard `t` (13,019 keys).
- DE2 `free`: shard `a` (9,422).
- DE3 `free`: shards `s`, `r` (9,872).
- DE4 `free`: shards `c`, `n` (9,370).
- DE5 `free`: shards `p`, `w`, `o` (10,967).
- DE6 `free`: shards `e`, `_`, `f`, `m` (11,173).
- DE7 `free`: shards `b`, `d`, `i`, `l`, `h` (9,873).
- DE8 `free`: shards `y`, `g`, `u`, `v`, `0`, `k`, `q`, `j`, `z`, `x` (5,711).

## How does a worker do its step?
- It reads `core/lang-pack-translating.md` and `core/lang-pack-german-terms.md` first.
- In pieces of about 300 keys: `php tools/lang_pack.php de --missing --shard=<x> --limit=300`, the piece translated, written with `php tools/tr_add.php --lang=de --from=<file>`, committed, then the next. A window that is compacted loses nothing: the next piece is again what is missing.
- At the end `php tools/lang_pack.php de --check` and `php tools/check_lang.php` are clean for its shards and `--count` shows none missing; the PR names the figures and twenty wordings it read again in the panel's context.
- A key whose meaning neither the English nor the Turkish settles is searched in the code once; the wording follows the screen that shows it.

## What comes after the eight?
- DE9 (S) German is offered, after DE1 to DE8 have landed.
  - `de` joins the shipped list of `pg_software_languages()` with its own name "Deutsch"; a top-up of the keys added since; the sandbox with German chosen at 1440 and 390 px.
  - The screens looked at: the dashboard, Site Settings, an order, the page editor, the designer's frame, the Workspace. Text that breaks a button or a table head is shortened in the wording, never in the screen's code.
  - Then the lasting figures into `core/`, and this file deleted with PL3.

## What is known and accepted?
- Until DE9 the panel does not offer German, but a site page in German shows the software's own texts in German as far as the shards have landed, and English for the rest.
- Nobody reads the pack line by line; a wrong wording is corrected by `tr_add.php --lang=de --replace` in a small PR when it is met.
