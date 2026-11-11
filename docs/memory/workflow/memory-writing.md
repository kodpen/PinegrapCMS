---
name: memory-writing
description: How a memory file is written so the next task finds it: labels as on screen, one owner per value, old values searched, lines under 500 characters, decisions dated, cluster overviews, trials
---

## What goes into docs/memory?
- What another account needs to go on with the work: the behaviour now, why, the symptom a PR fixed, the PR number (`CLAUDE.md`, Memory). A session's own notes and skills are not seen by the next account.
- Only what must not be public stays out: secrets, design and page ids, panel addresses of a real site (the repository is public).

## How is a file found?
- Its description names a screen, box or button by its English label on screen ("the Wait box", not only "the delay kind"), with the code name beside it. A session searches the words a person uses; a trial asked for a Wait box spent 51 calls finding it was kind `delay`.
- A line stays under 500 characters, the description included: the search tool shows a longer line as "[Omitted long matching line]", so its words are never read.
- `tools/memory_index.php` refuses a new or rewritten line over 500 characters, in `git push` and CI too; old ones pass until their file is edited ([[memory-long-lines]]).

## Where does a value live?
- One file owns a value (a limit, a default, a rule); the others point to that file and do not repeat the number.
- A PR that changes a limit or a rule searches all memory for the old value (`grep -rn 'old value' docs/memory`) and corrects or deletes every copy. "`WS_FLOW_DELAY_MAX` is a week" outlived the change to 90 days this way.

## How is a decision written?
- `Owner (YYYY-MM-DD):` (or `Architect (YYYY-MM-DD):`) at the start of the line, with the reason. `tools/memory_index.php` marks the file's index line `[decision: here]` from that form; a decision written without it is not seen (`workflow/DECISIONS.md`, `decisions-made.md`).
- A working rule is never written as an owner decision (`CLAUDE.md`, Fixed rules 6).

## When does a cluster get an overview?
- Ten files or more sharing a name prefix have `<prefix>-overview.md` (at most 4 KB): what the feature is, the decisions that bind it, its parts by the label a person sees, which file holds what, how a new part is added. Model: `ui/image-editor-overview.md`.
- The plan that grows a cluster keeps its overview current in its close-out step.

## How far is memory trusted?
- Rely on what memory says; check in the code only what the change depends on. A trial answered right by its fourth call, then spent 15 more checking in code what memory had said.
- When memory and code disagree, the code wins and the memory line is corrected in the same PR.

## How is memory tried?
- A trial: a fresh session reads `CLAUDE.md`, prepares one task in the owner's words up to the first line of code, changes nothing, and reports its searches, the memory files it opened, what binds the task and where memory slowed it. Better is the same findings with fewer files opened and fewer calls.
- Six tasks ran on 2026-11-10, before and after the overviews, decision marks and cluster map: from 117,000 to 256,000 tokens and 28 to 100 calls each down to 65,000 to 81,000 tokens and 4 to 11 calls. The tasks and each run's figures are in git history: `git show 4608205942:docs/memory/plans/<name>` with `memory-owner-steps.md`, and `4608205942^` with `memory-as-guide-trials.md`.
- The documentation task ran a third time once `user-docs-pages.md` existed: the pages, their mark and who may change them by the second call, from memory alone; the runs before had them from the machine's notes, or not at all. Its 210,000 tokens and 61 calls went into a 22-line change list read from 22 topic files, because memory has no list of what is new since a mark (`user-docs-pages.md`).
