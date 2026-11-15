---
name: channel-profile-cards
description: Plan (owner 2026-11-15, channel decision 3775): who an agent or a person is, shown in channels: a card on the name, role badge, state, usage mark, a clearer agents panel; PF1 to PF8
---

## What is asked?
- Owner (2026-11-15, in the architect's window): "kanallarda ajanın rolü ve modeli (göndermişse) ve varsa kalan kota bilgisi gibi bilgiler. ajanın adının yanında (kota bilgisi ve diğer bilgileri fareyi (adına veya görseli) üzerine getirdiğimizde gözüksün). bu ve bunun gibi kullanışlı özellikleri planla". He chose all four parts below and added: "yardımcı olacak faydalı bunlar gibi şeylerde iyi olur".
- Before: role, reported model, plan usage and last seen show only on the Agents card of Site Settings (`workspace/agent-report.md`, `agent-role.md`, `agent-last-seen.md`); in a channel an agent's name carries "(application)" and nothing more.

## What is shown?
- The agent card: on the name or the picture of an agent in a message, the member list and "Agents in this channel", opened by hover, keyboard focus or a tap. Name and `@handle`, role, the model it reported, each usage window with its percent and reset time, how old the report is, last seen, its state and how many requests wait for it. A fact the agent never sent is left out, not shown empty.
- Beside the name in a message: the role's badge in place of "(application)", and a state dot on the picture: working (a request it claimed), waiting, not seen for ten minutes, paused.
- The @ menu: each agent with its role and state. When a reported window is at 90% or more, a mark beside the name, in the menu and on the card. This takes back "no warning on plan usage" of 2026-11-13 (`agent-report.md`); it is a mark, it stops nothing.
- The same card for a person: role, department, working hours, away days (`ws_profiles.mail_away`, IB12).
- "Agents in this channel" of Channel settings, redrawn in plain words (`channel-profile-cards-panel.md`), and the drawer itself: tabs, icon, colour, switches, versions (`-settings.md`).
- The card's layout and the further facts he chose: `channel-profile-cards-facts.md`.

## Who sees it?
- Team members who may read the channel. A guest of a customer room sees no card, no usage and no state; an application's key reads the same facts in the channel-agent rows of the API.
- The figures are the agent's own report: the card says so, as the Agents card does, and mutes a report older than a day.

## Which steps are there?
- PF1 (S, no product code) Census: where a sender's name and picture is drawn, what the payload carries, where a person's facts are read, who may see what. Done, #9599 (`-census*.md`).

- PF2 (M) The facts on the server: one reader for an agent's card (role, model, windows, ages, state, waiting count) and its panel action; the API rows; tests. Done, #9618.

- PF3 (M) The agent card in channels. A new design: its pictures go to the owner (`Approval: channel`). #9630 waits for his look.

- PF4 (S) Role badge and state dot in a message. Done: #9629.

- PF5 (S) The @ menu's role, state and usage mark. Done #9628.

- PF6a (S) A person's facts on the server: one reader (`-census-facts.md`, "Where are a person's facts?"), its panel action, tests. Needs no code of PF3.

- PF6b (S) The card for a person, drawn with PF3's card functions.

- PF7 (M) The channel's agents panel redrawn, the same words on the Agents card of Site Settings. A new design: its pictures go to the owner.

- PF7b (M) The Channel settings drawer: `channel-profile-cards-settings.md`. The owner's look.

- PF8 (S) Close-out: memory files, `agent-report.md`'s "left out" line corrected, this plan deleted.

## What does every step keep?
- No schema change: every fact is stored today. A schema need found by PF1 is asked of the architect.
- A channel with no agent looks as today; nothing is read for a guest.
- PF1 first, then PF2; PF3 to PF5 and PF6a after PF2, PF6b after PF3 and PF6a; PF7 and PF7b after PF1, beside the rest. Claim as `[plan:channel-profile-cards] <step> — <topic>`.
