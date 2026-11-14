---
name: generated-files-off-prs
description: Plan (owner accepted 2026-11-14): generated files (memory indexes, minified bundles) leave the PRs and are produced when a train lands; first a census of what conflicts
---

## What is asked?
- A ready PR turns `dirty` when another PR has regenerated the same generated file: a memory `INDEX.md` or README, `workflow/DECISIONS.md`, a `.min.js` bundle. Its owner then merges `development`, regenerates and waits for the checks again, for a file nobody wrote. A docs PR (#9371) was refreshed so several times in one night.
- The architect proposed taking these files out of the PRs and letting the train produce them (channel message 2639); the owner accepted (2026-11-14, channel message 2645: "5 önerin kabul. Onaylıyorum").

## What is there today?
- Generated and committed: `docs/memory/README.md`, the folder READMEs (their key-rules block is hand-written), every `INDEX.md`, `workflow/DECISIONS.md`, `core/fn-modules.md` (`tools/memory_index.php`); the `.min.js` and `.min.css` bundles (`tools/build_js.php`, `build_css.php`); the code editor bundle (`build_code_editor.php`).
- CI runs each tool with `--check` on every PR, so every PR carries the regenerated file, and two PRs adding a memory file to one folder conflict in its `INDEX.md`.
- The train already regenerates them when they conflict (`workflow/merge-train-tool.md`); a PR landing alone gets its indexes the same way (`workflow/merge-train.md`).
- The language shards are sorted by key and no longer conflict (`core/lang-files.md`): not part of this plan.

## Which steps?
- GF1 (S) Census, no code. From the PRs merged since 2026-11-13: per generated path, how often a PR turned `dirty` on it alone and how often a train regenerated it. Who reads each file from the repository (sessions grep the indexes; a release serves the bundles). Two ways compared with their cost: the PRs stop committing the file and the train produces it, or the PRs keep it and a merge driver regenerates. The numbers and the chosen way go into this file; they set the scope of GF2 and GF3.
  - Done: numbers in `plans/generated-files-off-prs-census.md`. Chosen: the PRs stop committing the files and the landing produces them (a merge driver leaves GitHub's dirty state as it is). GF3 is needed: bundles are in 47 of the 55 PR merges dirty on generated files alone and in 37 of the 54 such train merges.
- GF2 (M) Memory indexes. A PR no longer changes the generated memory files; on a PR, CI checks the PR's own memory files (frontmatter, size, line length) and not the lists. The train, and the landing of a PR alone, runs `php tools/memory_index.php` after its merges and commits the result inside the same fast-forward push. `tools/pre_push.sh` follows.
- GF3 (M) Bundles, only when GF1's numbers ask for it: the same for `.min.js` and `.min.css`. A worker's sandbox builds its own bundle; `development` holds fresh ones after every landing.
- GF4 (S) Close-out: the rules into `workflow/git-branches-and-shared-files.md` and `workflow/merge-train-tool.md`, the lines of `CLAUDE.md` they change (Working mode 7, "When a task is done") by that PR with its reason, this plan deleted.

## What does every step keep?
- `development` always holds generated files that match their sources: the full `--check` stays on `development` and in the train's own run.
- No landing leaves a stale index or bundle behind: producing them is part of the landing push, never a later commit.
- The files stay in the repository; the key-rules blocks stay hand-written.
- Tools and CI only: no product code under `pinegrap/` but the bundles themselves, no schema.
- GF1 first; GF2 and GF3 after it; GF4 last. Claim as `[plan:generated-files-off-prs] <step> — <topic>`.