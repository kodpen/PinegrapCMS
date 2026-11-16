---
name: website-features-rules
description: The rules every step of the nine website-side plans keeps (plans/website-features.md): fewest existing files, schema, switches, numbered features in a step's PR body, looks, texts, tests
---

## Which files may a step touch?
- Owner (2026-11-16, agent channel message 5667): "olabildiğince az sayfayı dokunmaya çalışalım ama sayfa ve veri tabanı eklenmesi gerekiyorsa sorun değil."
- Each plan's steps name the existing files a step changes and the one hook in each. A step that needs another existing file says so in its PR body with the reason; it does not widen quietly.
- New code goes into new files: a module under `includes/fn/` named in its topic file's `module:`, a root file only when it is called by its address, each with the Pinegrap header block.

## How does a table or column come?
- Only through a step of the open version with the idempotent helpers, listed in `get_tables()`, rerun twice on a dev database (`release/how-to-add-migration.md`).
- Owner (2026-11-16, agent channel poll 65, message 5751), asked whether the tables these plans need may be added (one each for the click map, content review and prototype comments with a flag on the share link, three for visitor notifications, two for release bundles): "Evet, eklenebilir; sütunları Mimar onaylasın".
- So the step's draft PR body carries the columns, types and indexes first, and the architect confirms them there before the rest is written (`workflow/decisions-made-schema-21.md`). Each feature works as "not there yet" until its step has run (a ready check).
- A setting is a settings text, never a `config` column (`data/config-table-no-varchar.md`).

## What may change on a running site?
- Nothing by itself. A new behaviour is behind a switch that is off, a setting nobody filled in, or a block nobody placed. A step whose feature would change a running site's output stops and asks.
- Legacy stays, the audience is mixed and global, no design variables (`workflow/decisions-made-product-40.md`).

## What does a step's PR body carry?
- A table of the feature numbers its step names (the letter and number of the plan's features file), each with the test or the picture that shows it. A number with nothing beside it is not done: the PR stays a draft.
- A step that finds a feature cannot be built as written stops and asks the architect in the agent channel; it never leaves the feature out or builds another one in its place.
- In words: what could not be verified.

## How are looks shown?
- A new screen, or a new part on a screen the owner keeps, posts close-up pairs under the plan's message in the agent channel: 1440 and 390, light and dark, and the states its features name. The architect brings the owner's looks to him; such a step is not marked ready before the look.
- Panel markup follows look A and Bootstrap 6; site output and blocks stay on Bootstrap 5.3.8 (`ui/README.md`, `designer/README.md`).

## Which rules of the code apply to all?
- Every text a person reads is translated: `lang()` in PHP, `_sdT()` in the designer, the Turkish through `tools/tr_add.php`.
- A pure function gets its test first, red before green when it changes a behaviour.
- A visitor's or guest's write has a same-origin check, a rate limit (`waf_rate_exceeded()`) and plain-text input; it never trusts an id it was sent without checking it against the page or link it came from.
- A busy counter follows `visitors/high-volume-counter-rules.md`.
- The close-out step of each plan checks every feature number against the code with the PR that built it, writes the topic files and deletes the plan's files.
