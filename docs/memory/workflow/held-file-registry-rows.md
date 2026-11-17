---
name: held-file-registry-rows
description: A PR may change its own registry rows in a file a draft PR holds: which registries, the terms, one such PR at a time beside a holder; the architect allows each holder (#3824, #10025)
---

## May a PR change a file a draft PR holds?
- Only a registry in it: `api_error_catalogue()`, the routes of `api_schema()`, the event catalogue. The PR adds or changes the rows of its own entries there and nothing else in the file.
- The holder's hunks do not touch those rows, and the architect allowed it for that holder in the agent channel. The PR's body names the holder.
- One such PR at a time beside a holder: they hold the file against each other as any two PRs do (`claim-work.md`), so the next starts when the one before has landed.
- Anything more in the file (a shared helper, a reordering, a hunk next to the holder's) waits for the holder to land.

## Why is it allowed?
- A registry's entries stand apart, so PRs on different entries merge without a conflict. A holder that waits days for an answer would otherwise stop every step that needs only its own rows in the file.
- It is asked of the architect each time because the holder's hunks have to be read first: a holder that reorders or rewrites the registry leaves no room beside it.

## What was allowed?
- #3824: one line of a registry beside a draft holder, the first case.
- Architect (2026-11-17): a route's rows and parameters count as one entry, not only a single line; several steps may be allowed beside one holder, one PR at a time, in the order their coordinator chooses. Reason: six steps waited on `includes/api/schema.php` for their route rows alone while its holder, #10025, changed one hunk of it (the parameters of the prototype link's POST) and waited for the owner's look (agent channel message 7852).

Related: docs/memory/workflow/git-branches-and-shared-files.md, docs/memory/workflow/claim-work.md
