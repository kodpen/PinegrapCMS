---
name: channel-info-agents-drawing
description: The drawing of Channel info's one agents block for both tabs (plan channel-info-agents, step CI1): the sheet, its boards, a row's lines in each state, what goes, the measures
---

## Where is the drawing?
- `workspace/channel-info-agents-drawing/`: `agents.html` with `agents-dark.jpg` and `agents-light.jpg`; `drawing.css` sits on top of `ui/look-sheet/sheet.css`. Names and numbers are examples; the labels are the step's.
- Boards: A1 today's two lists; B1 the block with Info and with Activity chosen, and a channel with no registry agent; S1 a row in each state; M1 nine agents, a column of 300 px, a phone; Z1 the row three times enlarged, the keys, what is read out.

## What was asked, and what is decided?
- Owner (2026-11-25, agent channel, request 2951): "bu alanda 2 sekme var iki sekmede ajan alanları çok farklı bilgiler veriyor bunun yerine ajanlar alanı biraz daha detaylı olabilir her iki sekmede ortak tek olabilir."
- Architect (2026-11-25): one block "Agents", first under the tabs, the same with Info and with Activity chosen; it does not move or read again when the tab changes. In a channel with no registry agent (no tabs) it stands where "Agents here" is today.

## What does the block hold?
- Head: "Agents" with its count; at its end the summary ("1 working · 1 waiting · 1 ready", registry agents only); under it "Project: …". Both leave the Activity tab's top.
- Rows ordered working, waiting, not seen, ready, paused; the assistants (Claude, Pinegrap AI, a model agent) last. Past six rows: six and "Show all n", kept while the panel is open.
- Under the rows, for a manager, "Manage" as today.

## Which lines does a row have?
- Top: the face with the state dot (`.ws-agent-dot`), the name (opens the agent card as today), the role, the state as a pill at the row's end. An assistant has the badge "Assistant" in the pill's place and no dot.
- Work, only while working: "Asked by <name>" (a link to the message) "· since HH:MM", the progress note under it, wrapped and never cut. While waiting: "n requests waiting".
- Reach: `@handle · <wake rule>` in today's words.
- Report: the model, the branch (the part cut), at the line's end the context as a small bar with its per cent, amber from 90%. "No report yet" before a first report. Older than a day the line is dimmed and "reported …" is amber.
- Time: the last seen in today's words ("Last knock …", "Last seen …", "Always ready"), amber when not seen, then "reported … ago".
- A line with nothing to say is left out, so a row is three to six lines. A paused agent's whole row is dimmed.

## What goes?
- Info's "Agents here" list, and the Activity tab's summary line, project line and agents list: the block replaces them.
- The badge "AI agent" on each row: the head and the square face are the mark that tells agents from people (owner 2026-11-12, #8880); "Assistant" stays.
- The context's words ("412k of 1M tokens") leave the row for the bar's tip and the agent card.

## Which measures?
- Row: 10 above and below, a line between rows; face 28 with corner 30%, 10 to the text; dot 8 with a ring of the ground.
- Name 13.5 px at 600; role 12 px, cut first; pill 22 high. Work, reach and report lines 12 px; the note 12.5 px italic in the first ink; time line 11.5 px in the third ink. Context bar 40 by 4.
- The name, the "Asked by" link, "Show all" and "Manage" take the focus. A state that changes while the panel is open is announced once, politely.

Related: docs/memory/plans/channel-info-agents.md, docs/memory/workspace/channel-activity-tab.md, docs/memory/workspace/channel-members-agents.md, docs/memory/workspace/agent-state-marks.md
