---
name: memory-as-guide
description: Plan (owner 2026-11-10): memory as a guide for the next task as well as a record: decisions shown where a session lands, an overview per cluster, user documentation fed from memory
---

## Why this plan?
- The owner (2026-11-10) had the project memory reviewed against its three goals: another account goes on with no skills and no private memory, memory feeds the user documentation, the structure saves tokens.
- The design holds and stays: the folder table in `CLAUDE.md`, key rules per folder, a searched index, topic files of 4 KB, generated indexes, the CI check.
- Memory is an accurate record of changes and a weak guide for the next task. Six fresh sessions found what binds their task, but each spent 117,000 to 256,000 tokens and 28 to 100 tool calls, and one opened 41 memory files (`memory-as-guide-trials.md`).

## What was measured? (2026-11-10, `development` at 1058f58b6b)
- 2,733 files, 7.97 MB; 255 files on 2026-10-14. 96% of 1,635 merges since 2026-11-03 change memory. Of 24,321 function names and 10,660 file names in memory, 98-99% are in the code.
- Decisions: 77 files, 263 KB, about 280 entries, parts by date. `CLAUDE.md` sends a session there only before an audit finding. `erp/erp-till-below-zero.md` states an owner decision and never says it is one.
- Clusters without an overview: `workspace-flows-*` 98 files, `list-table-*` 44, `erp-parasut-*` 29, `designer-blocks-*` 26, `custom-form-*` 17. A search for "flow" in the workspace index returns 127 lines.
- The word a person sees is missing: the Wait box ("Bekle") is "delay box" in memory.
- Stale lines: Fixed rule 7 of `CLAUDE.md` ("round with `round()`") against `commerce/offer-percent-rounding.md`; "`WS_FLOW_DELAY_MAX` is a week" in `workspace/workspace-flows-template-events.md` (90 days); a row checkbox in `ui/list-and-table-screens.md`, which its next line rules out.
- User documentation has no footing here: which pages exist and how far they are written is known only on the owner's machine.

## Which decisions are made?
- Owner 2026-11-10, user documentation: the page list, what each page covers and the PR it is written up to go into memory. Design and page ids and panel addresses stay out: the repository is public.
- Owner 2026-11-10: "a cloud session puts the doc text in its PR" is dropped (9 of 297 PR bodies named documentation). Documentation is written from a feature's overview file and the page's mark.
- Owner 2026-11-10: his instruction for the time the architect is away stays in his machine's notes while the agent channels may change the rules.
- The session's choices: the decision marks are generated and no field is added by hand; the 4 KB limit and one index line per file stay; question headings are not added to the index (measured: no gain).
- Design template clusters get no overview while their plans are paused.

## What holds for every step?
- A step changes memory, `CLAUDE.md` or `tools/memory_index.php` with `tests/memory_index_test.php`; none changes the product.
- A generated file is never edited by hand; a tool step writes every index again in its own PR.
- An overview is at most 4 KB: what the feature is, the decisions that bind it, its parts by the label a person sees, which file holds what, how a new part is added. Model: `ui/image-editor-overview.md`.
- A stale or contradicting line met on the way is corrected in the same PR and named in its body.

## Where to start?
- `memory-as-guide-steps.md` holds the tool, rule and documentation steps in their order; `memory-as-guide-steps-2.md` holds the overviews, which run side by side.
