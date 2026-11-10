---
name: memory-writing
description: How a memory file is written so the next task finds it: labels as on screen, one owner per value, old values searched, lines under 500 characters, decisions dated, an overview per cluster
---

## What goes into docs/memory?
- What another account needs to go on with the work: the behaviour now, why, the symptom a PR fixed, the PR number (`CLAUDE.md`, Memory). A session's own notes and skills are not seen by the next account.
- Only what must not be public stays out: secrets, design and page ids, panel addresses of a real site (the repository is public).

## How is a file found?
- Its description names a screen, box or button by its English label on screen ("the Wait box", not only "the delay kind"), with the code name beside it. A session searches the words a person uses; a trial asked for a Wait box spent 51 calls finding it was kind `delay`.
- A line stays under 500 characters, the description included: the search tool shows a longer line as "[Omitted long matching line]", so its words are never read.

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
