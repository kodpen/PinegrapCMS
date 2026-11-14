---
name: generated-files-off-prs-census
description: Census of plan generated-files-off-prs: how often generated memory lists and bundles made a PR dirty or a train regenerate them, who reads them, the two ways and their cost
---

## What was counted, and how?
- The 244 PR landings on `development` from 2026-11-13 00:00 to 2026-11-14 09:40 UTC (first-parent merge commits `<title> (#n)`). Each train merge and each merge of `development` into a PR branch was replayed with `git merge-tree --write-tree` to list its conflicting paths.
- Generated: the memory lists of `tools/memory_index.php` (READMEs, `INDEX.md`, `DECISIONS.md`, `core/fn-modules.md`) and the `.min.js` / `.min.css` bundles. Language shards are counted apart (not in the plan).

## How often did a PR turn dirty on generated files alone?
- 76 conflicted merges of `development` into 46 PR branches; 55 of them on 35 PRs had only generated files in conflict: bundles 43, memory lists 8, both 4. Each one cost a merge, a rebuild and a CI run of about ten minutes.
- By path: `backend.min.css` 27, `plans/INDEX.md` 6, `workspace.min.js` 5, `style_designer.min.js` 4, `core/fn-modules.md` 4, `workspace_flow.min.js` and `backend.min.js` 2 each, the workspace, ui and designer READMEs 2 each, the rest 1.

## How often did a train regenerate them?
- 76 of 244 train merges conflicted; 54 on generated files alone (bundles 32, memory lists 17, both 5), 22 together with other files.
- By path: `backend.min.css` 24, `workspace.min.js` 8, `workspace_flow.min.js` 7, `core/fn-modules.md` 7, `ui/README.md` 6, `style_designer.min.js` and `backend.min.js` 5 each, the plans and designer `INDEX.md` and designer README 4 each, the workspace README and INDEX 3 each.
- A clean merge can still leave a list stale (a cluster count): 5 train merges changed a README or INDEX that merged without conflict, and twice a later "Regenerate the memory indexes" commit fixed `workspace/README.md` on `development`.
- 209 of the 244 landings changed a memory list, 99 a bundle. The code editor bundle never conflicted.

## Who reads each file from the repository?
- Memory lists: sessions, from `development` (CLAUDE.md: READMEs read, `INDEX.md` and `DECISIONS.md` grepped); `tools/free_work.php` and `tools/merge_train.php` by path only. No product file, release or test reads them, so a PR branch whose lists lag behind its own new files harms nothing: its session knows them.
- Bundles: the product serves them (`ENVIRONMENT_SUFFIX` `min`; the sources only when the `.min` file is missing, a stale one is served as is), the release is cut from `development`, the owner's dev checkout runs `development`, and about 12 tests read a bundle's contents (36 more name one).
- `backend.min.css` and `backend.min.js` are one line each (540 KB, 250 KB): any two edits of their sources conflict.

## Which way, and at what cost?
- A, the PRs stop committing them and the landing produces them: no PR turns dirty on them, the train's settling and the silent stale lists end. Cost: CI checks a PR's own memory files instead of the lists; a PR that still edits a list is refused; for bundles PR CI builds before the tests, and the landing machine needs Node and terser through `npx`, which `tools/pre_push.sh` notes is not everywhere.
- B, the PRs keep them and a git merge driver regenerates: GitHub computes `mergeable_state` on its own servers without custom drivers, so the PR still turns dirty; a driver sees one file's three versions, not the merged tree, and needs setup in every clone. The train already settles these conflicts. B removes neither cost.
- Chosen: A, as the plan describes. The numbers ask for GF3 as well: bundles are in 47 of the 55 dirty PRs and 37 of the 54 train regenerations, `backend.min.css` alone in about half of each.

Related: plans/generated-files-off-prs.md, workflow/merge-train-tool.md, core/js-bundles.md, core/css-bundles.md
