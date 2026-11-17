---
name: held-file-bundled-sources
description: In a bundled script or stylesheet (backend.src.js, backend.src.css, style_designer.src.js, the Workspace scripts) an open PR holds the functions and rule blocks it changes, not the file
---

## Does a PR on a bundled source hold the whole file?
- No. In a source of `pg_js_bundles()` or `pg_css_bundles()` (`backend.src.js`, `backend.src.css`, `style_designer.src.js`, the Workspace scripts) an open PR, draft or not, holds the functions and rule blocks its hunks change, not the file.
- Another PR may change other parts of that file when none of its hunks is in the same function or rule block as an open PR's hunk, or on the lines next to one, and `git merge-tree --write-tree <its branch> <the other PR's head>` merges the file without a conflict.
- The session checks that itself against every open PR that changes the file (`…/pulls/<n>/files`) and asks nobody; its PR's body names the PRs it stands beside in that file.
- The same function, the same rule block or the neighbouring lines wait for the other PR to land, as any held file does (`claim-work.md`).
- Every other file is held whole. What the architect may allow beside a draft holder there (a registry's rows, a function the holder does not touch) is in `held-file-registry-rows.md`.

## Why?
- Each of these files holds the parts of many screens (about 15,000, 31,000 and 84,000 lines). Two hunks in different parts merge without a conflict and do not meet in the logic.
- Held whole, one draft waiting for an answer stops every change of the panel's script or of the designer's.
- The built `.min.js` / `.min.css` are never in a PR (`git-branches-and-shared-files.md`), so two PRs on one source do not conflict there either.

## What was decided?
- Architect (2026-11-17): the rule above. The case: CN5 (#10153) needed two hunks in `score()` of `pgSettingsModal`, near line 12,045 of `backend.src.js`, while the draft of PN3 (#10042) held one line of `pg_bell_tile()` near line 4,026 and waited for the owner (agent channel message 7930).
- That day six open PRs changed `style_designer.src.js` and six `backend.src.css`, each in a part of its own: the rule as written (the whole file) was already not what kept them apart.

Related: docs/memory/workflow/claim-work.md, docs/memory/workflow/held-file-registry-rows.md, docs/memory/core/js-bundles.md
