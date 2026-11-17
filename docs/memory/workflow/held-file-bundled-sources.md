---
name: held-file-bundled-sources
description: A script or stylesheet under assets/ (backend.src.js, backend.src.css, style_designer.src.js, style_designer.css) is held by the functions and rule blocks a PR changes, not as a whole file
---

## Does a PR on a script or stylesheet hold the whole file?
- No. In a script or stylesheet under `assets/` an open PR, draft or not, holds the functions and rule blocks its hunks change, not the file.
- That covers the sources the landing builds (`pg_js_bundles()`, `pg_css_bundles()`: `backend.src.js`, `backend.src.css`, `style_designer.src.js`, the Workspace scripts) and the files served as written (`style_designer.css`, `offer_editor.js`).
- The exception, held whole: a source whose `.min` twin a PR edits by hand (the `ENVIRONMENT_SUFFIX` twins of `core/important-files-and-js-twins.md`, such as `frontend.src.js` / `frontend.min.js`). Two PRs would both rewrite the twin.
- Another PR may change other parts of the file when none of its hunks is in the same function or rule block as an open PR's hunk, or on the lines next to one, and `git merge-tree --write-tree <its branch> <the other PR's head>` merges the file without a conflict.
- The session checks that itself against every open PR that changes the file (`…/pulls/<n>/files`) and asks nobody; its PR's body names the PRs it stands beside in that file.
- The same function, the same rule block or the neighbouring lines wait for the other PR to land, as any held file does (`claim-work.md`).
- Every other file, PHP above all, is held whole. What the architect may allow beside a holder there (a registry's rows, a function the holder does not touch) is in `held-file-registry-rows.md`.

## Why?
- These files hold the parts of many screens each (`backend.src.js` about 15,000 lines, `backend.src.css` 31,000, `style_designer.src.js` 84,000). Two hunks in different parts merge without a conflict and do not meet in the logic.
- Held whole, one draft waiting for an answer stops every change of the panel's script or of the designer's.
- The built `.min.js` / `.min.css` are never in a PR (`git-branches-and-shared-files.md`) and a file served as written has none, so two PRs on one source do not conflict there either.

## What was decided?
- Architect (2026-11-17): the rule for the built sources. The case: CN5 (#10153) needed two hunks in `score()` of `pgSettingsModal`, near line 12,045 of `backend.src.js`, while the draft of PN3 (#10042) held one line of `pg_bell_tile()` near line 4,026 and waited for the owner (agent channel message 7930).
- That day six open PRs changed `style_designer.src.js` and six `backend.src.css`, each in a part of its own: the rule as written (the whole file) was already not what kept them apart.
- Architect (2026-11-17): the same for a script or stylesheet served as written. The case: bug #10072 needed one `@media` rule beside the toast rules near line 3,703 of `assets/css/style_designer.css`, while the drafts #10101 and #10042 held only lines appended at its end (agent channel message 8008).

Related: docs/memory/workflow/claim-work.md, docs/memory/workflow/held-file-registry-rows.md, docs/memory/core/js-bundles.md
