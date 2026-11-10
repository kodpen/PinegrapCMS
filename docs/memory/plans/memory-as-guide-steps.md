---
name: memory-as-guide-steps
description: Steps MG1-MG4 and MG11-MG14 of plans/memory-as-guide.md: decision list and marks, writing rules, cluster map, link check, documentation pages, private notes; claim as [plan:memory-as-guide] MG<n>
---

## How are the steps taken?
- Claim with a draft `[plan:memory-as-guide] MG<n> — <topic>`. A step that changes only `docs/` or `CLAUDE.md` lands alone.
- MG1, MG4 and MG11 change `tools/memory_index.php`: one at a time, in that order, each with tests in `tests/memory_index_test.php`, red first, and with every index written again in its PR.
- The overviews, MG5 to MG10, are in `memory-as-guide-steps-2.md`; they run side by side with these.

## Steps
- MG1 Decision list and marks (done, #8457): the tool writes `workflow/DECISIONS.md`, named in the workflow README as a searched file: one line per entry heading of `workflow/decisions-made*.md` (file, heading) and one per topic file whose own text records a dated owner or architect decision (file, description).
  - A topic file's index line ends with `[decision: <part>]` when a part names the file or the file cites an issue number of an entry heading, and with `[decision: here]` when only its own text records one. Today that marks 470 of 2,614 topic files, `erp/erp-till-below-zero.md` among them.

- MG2 Wording, after MG1 (done, #8464): `CLAUDE.md` and the workflow key rule say "before changing or removing a behaviour, search `workflow/DECISIONS.md`", not only before an audit finding. `decisions-made.md` gives the search line in place of its Parts list.

- MG3 Writing rules (done, #8472): `workflow/memory-writing.md` (new) and three lines in the Memory section of `CLAUDE.md`.
  - A description names a screen, box or button by its English label on screen. One file owns a value, the others point to it. A PR that changes a limit or a rule searches all memory for the old value. A line stays under 500 characters: the search tool hides longer ones. A decision is written `Owner (YYYY-MM-DD):`.
  - A cluster of ten files or more has an overview, kept up by its plan's close-out step.
  - Rely on what memory says and check in code what the change depends on. What another account needs goes to `docs/memory` unless it must not be public.

- MG4 Cluster map, after MG1: each folder README lists its clusters (six or more files sharing their first two name tokens, a plural s aside) with the count and the start file, `<prefix>.md` or `<prefix>-overview.md`, when there is one.

- MG11 Link check, after MG4: `--check` fails on a `[[name]]` or `folder/file.md` reference to a memory file that does not exist; plans are exempt, they name files to come. Corrects the broken ones (about ten).

- MG12 (owner's machine) Documentation pages: `workflow/user-docs-pages.md` with the pinegrap.com pages by address, the clusters each covers and the PR each is written up to; the new rule in `owner-rules.md`; a row for documentation work in the table of `CLAUDE.md`.

- MG13 (owner's machine) Private notes: what another account needs and may be public moves from the machine's notes into `docs/memory`.

- MG14 Close-out, after all the others: the trials of `memory-as-guide-trials.md` run again; the lasting rules stay in `workflow/memory-writing.md`; the plan is deleted.
