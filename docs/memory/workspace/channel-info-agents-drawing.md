---
name: channel-info-agents-drawing
description: The drawing of Channel info's one agents block for both tabs (built in #11617): a calm two-line row that opens in place, what goes, the measures
---

## Where is the drawing?
- `workspace/channel-info-agents-drawing/`: `agents.html` with `agents-dark.jpg` and `agents-light.jpg`; `drawing.css` sits on top of `ui/look-sheet/sheet.css`. Names and numbers are examples.
- Boards: A1 today's two lists; B1 the block with Info and with Activity chosen, and a channel with no registry agent; S1 a row at rest in each state; O1 a row opened; M1 nine agents, a column of 300 px, a phone; Z1 the row three times enlarged, the keys, what is read out.

## What was asked, and what is decided?
- Owner (2026-11-25, agent channel, request 2951): the two tabs tell very different things about the same agents; one agents part, a little more detailed, common to both.
- Owner (2026-11-25, agent channel, request 2952, on the first drawing): not modern enough; the texts, buttons and badges still crowd it.
- Architect (2026-11-25): one block "Agents", first under the tabs, the same with Info and with Activity chosen; it does not move or read again when the tab changes and an open row stays open. With no registry agent (no tabs) it stands where "Agents here" is today. A row at rest has two lines and no badge, pill or button; it opens for the detail.

## What does the block hold?
- Head: "Agents" and a plain count; at its end only the states that ask for a look ("1 working · 1 waiting", nothing when all are ready); for a manager one icon button named "Manage agents". "Project: …" moves under the block, into the Activity pane.
- Rows ordered working, waiting, not seen, ready, paused; the assistants last. Past six: six rows and a quiet row "3 more", kept while the panel is open.

## What does a row at rest show?
- The face with the state dot (`.ws-agent-dot`); line 1 the name and the role; line 2 the state's word, then " · " and the one thing worth knowing.
- Working: the progress note, else "for <name>, since HH:MM". Waiting: "2 requests in its queue". Not seen: "last knock 3 h ago". Ready: the model, with "reported 7 d ago" in amber when older than a day, "no report yet" before a first one. Paused: nothing more, the row dimmed. An assistant: "Assistant · always ready".
- The state's word is blue for working, amber for waiting and not seen, plain for ready and paused.
- At the row's end a context ring (tip "Context 412k of 1M tokens (41%)"), amber from 90%; a chevron only while the row is pointed at, focused or open.

## What does an opened row show?
- A press, Enter or Space opens it in place; several may be open. A list of facts, a fact with nothing to say left out: Doing (the whole note, wrapped), Asked by (a link to the message) with since, Queue, Handle, Wakes, Model, Context (a bar and "412k of 1M · 41%"), Branch (cut, whole in its tip), Seen (amber when not seen), Reported (amber when older than a day, "Not yet").
- The agent card stays on messages and on the channel head's faces; in the block the row opens instead, so the name carries no card.

## What goes?
- Info's "Agents here" list, and the Activity tab's summary line and agents list: the block replaces them.
- The badge "AI agent", the state pill and "Manage" as a worded button: the head, the square face, the dot and the state's word say the same.

## Which measures?
- Row: 8 above and below, 10 at the sides, corner 12, 2 between rows, no line; it reaches 10 past the text column. Open: the card ground with a hairline. On a phone 52 high.
- Face 28 with corner 30%; name 13.5 px at 600; role and line 2 12.5 px, one line each, the role cut first. Ring 18 across, line 2.5. Facts: labels 76 wide, 12.5 px, 7 between. The rest is on board Z1.
- A state that changes while the panel is open is announced once, politely.

Related: docs/memory/workspace/channel-info-agents-block.md, docs/memory/workspace/channel-activity-tab.md, docs/memory/workspace/channel-members-agents.md, docs/memory/workspace/agent-state-marks.md
