---
name: screen-follows-drawing
description: A screen the architect drew is built as drawn: button order, sentences, pills, links; a words-only description beside it gives way (owner, 2026-11-21)
---

## What did the owner say?
- Owner (2026-11-21, with a picture of Claude's row in Site Settings › Assistants and agents on dev): "mimara özellikle çizdirttiğim ekrandaki düğme dizilimleri ve cümleleri dahil tamamen farklı olmuş", "mesela hazır etiketi yok, nasıl çalışır yok", "dikkate alınmıyacaksa mimara o kadar token neden israf ediyorum ki?"
- What he met: AT2b (#10898) had built the row from the plan's words as one column; the drawing (`git show 95a7d40a:docs/memory/plans/assistants-settings-tidy/assistants.jpg`) had a grid, the Ready pill, "How does this work?" and other sentences.

## What is the rule?
- When a screen has a drawing (a `.jpg` or `.html` beside its plan; a closed plan's drawing is read with `git show <commit>:<path>`), the step builds it as drawn: the parts in the drawing's order, its buttons and their order, its sentences, pills, links and notes. A words-only screens file gives way to the drawing where they differ.
- Where the drawing cannot be true (the product works otherwise, a field it lacks must stay), the step keeps the drawing's look and its PR says what differs and why.
- The step is checked in the sandbox (`tools/setup_sandbox.sh`) against the drawing, in both themes and at phone width, before it is marked ready.
- This does not bring back drawing approvals: no step waits for a drawing (`screen-described-in-plan.md`).

## Where was it first applied?
- Claude's row and its drawer Set up on claude.ai (`ui/settings-claude-row.md`).

Related: docs/memory/workflow/screen-described-in-plan.md, docs/memory/ui/settings-claude-row.md
