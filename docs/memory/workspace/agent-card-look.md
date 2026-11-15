---
name: agent-card-look
description: The agent card's look in a channel (PF3, owner 2026-11-15): the barred layout, the agent's picture (never a game face), the facts he chose, who sees the card; the person card shares its frame
---

## Which layout? (PF3, #9630)
- Owner (2026-11-15, channel decision 3796), asked between this and a slimmer card with the percent only: "Çubuklu düzen".
- Top: picture, name, `@handle`, role pill. A box: state, waiting requests, model, last seen, today's answered and failed. One bar a usage window with its percent and reset time, amber with a mark at 90% or more. Under it: what wakes it, its last answer here (jumps to the message), the channels it is in that the reader may read, the note that the figures are the agent's own report, and Mention.
- A fact the agent never sent is left out, not shown empty; a report older than a day is muted (`agent-report.md`).
- Built from `.pg-card`, `--pg-surface-float`, `--pg-shadow-float`, `.pg-pill` tones and `.pg-tabular`; style `.ws-agent-card*` beside `.ws-agent-face`. The look: `Approval: channel 4096`.

## Which picture does an agent have?
- Owner (2026-11-15), on the draft's robot face: "ajanın ikonu bu kadar oyun karakteri gibi olmasın, bu özen gösterilsin".
- Its own picture when it has one (`ws_agent_card_avatar()`), else the panel's robot icon in a quiet tile of its colour, as Channel info draws it. Never an emoji or a cartoon face. The same on the message, the card and "Agents in this channel".

## Which further facts did he choose?
- Owner (2026-11-15, channel decision 3796): all four offered for the agent card: the channels it is in, its last answer in this channel with its time, today's counts, what wakes it and what it may do. Not offered, so not built: who added it.
- All four offered for the person card: a Message button, local time, open tasks, the department's lead. Open tasks were left out of PF6a by the architect (channel 3989); the rest is on it (`person-card-facts.md`).
- The card shows what wakes it (`wake`, the member row's words) and sends the tier as a number; the panel's tier words (`ws_channel_agent_tier_words()`) are not on the card.

## Who sees it?
- Team members who may read the channel. A guest of a customer room sees no card, no usage and no state, and the guest page's payload carries none (`decisions-made-workspace-33.md`).
- An application's key reads the same facts in the API's channel-agent rows (`agent-card-facts.md`).
- No schema change was needed: every fact was stored already.

## Where is the rest?
- What the server sends and the state order: `agent-card-facts.md`; load failure and scroll: `agent-card-behaviour.md`; the dot and role badge in a message and the @ menu: `agent-state-marks.md`.
