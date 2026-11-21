---
name: channel-timeline-redraw
description: Plan: the conversation timeline rebuilt from the architect's drawing (one well in the tabs row, days as columns, runs as bars, Ctrl and Shift with the wheel); one step, TR1
---

## Why is it redrawn?
- Owner (2026-11-21, in the architect's window, with a picture of the strip after #11027): "çizmeden işi verdiğin için saçma sapan oldu ben çizersin diye güvenerek sana vermiştim. planı düzgün yap." Then: "az daha dar olsun enden de ve klavye kısayolları ile çalışsın ctrl ve shift scroll ile yaklaş uzaklaş ve kaydır" (`workflow/decisions-made-workspace-46.md`).
- What he met: every turn a pill of its own with the same space around it, so runs and days could not be told apart; a dot over nearly every turn; the strip as wide as the row and up to the card's edge; the zoom buttons on a line of their own under it; gradient and shadow on eight tones.
- The job had gone out in words though the strip has no model on the Design examples screen. The drawing comes first now (`workflow/architect-draws-new-part.md`): `workspace/channel-timeline-drawing/timeline.html`, rendered as `timeline-dark.jpg` and `timeline-light.jpg`, boards 1 to 9.

## What stays as built?
- The read's reach and limits (`ws_conversation_timeline()`), the runs and their weights (`timelineRuns()`, `timelineLayout()`), one colour a writer, the card's head, the click, the live update, the zoom kept per channel, the opening zoom, the move to Channel info under 220 px.

## TR1 (M), one PR: what is built?
- The drawing, said in words in `channel-timeline-redraw-screens.md` and `channel-timeline-redraw-card.md`. Files: `assets/js/workspace_conversation_timeline.js`, the `.ws-ctl*` rules of `backend.src.css`, `includes/workspace/conversation_timeline.php` (the card's shortcut rows), `tests/workspace_conversation_timeline_test.php`.
- The well joins the Design examples screen as a pattern in the same PR (`ui/look-design-examples.md`).
- Memory: `workspace/workspace-channel-timeline.md` and `workspace-channel-timeline-zoom.md` corrected to the new behaviour; this plan with its screens and card files is deleted by the step, the drawing stays.
- Screen check in the PR body: boards 1 to 6 and 9 beside the built screen at 1440, 900 and 390 px, dark and light, on a conversation of five days or more with a message of each kind of row; then Ctrl with the wheel, Shift with the wheel, the plain wheel, each key of board 8, a row opened by pointer and by keyboard.
- Where the drawing and the words differ the drawing wins. What neither says is asked of the architect in the agent channel before it is built, never decided in the step.

## Who builds it, and when?
- Owner (2026-11-21, on the drawing): "bu görevi @arge ye verelim o iyi yapar". The research agent builds TR1 itself, one PR.
- Agent channel line, the first priority. Nothing else touches the source files meanwhile (`workflow/held-file-bundled-sources.md`).

Related: docs/memory/plans/channel-timeline-redraw-screens.md, docs/memory/plans/channel-timeline-redraw-card.md, docs/memory/workspace/workspace-channel-timeline.md, docs/memory/workspace/workspace-channel-timeline-zoom.md
