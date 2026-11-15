---
name: channel-profile-cards-panel
description: Steps PF7 and PF7b of channel-profile-cards (owner 2026-11-15): Channel settings redrawn, the agents panel in plain words, icon and colour as popovers beside the name, switches at the row's end
---

## What is asked?
- Owner (2026-11-15, in the architect's window, with a picture of Channel settings): "bu alanda biraz daha modern ve anlaşılır olsun. kademe kısmı benim tarafımdan bile ne olduğu anlaşılmıyor".
- Before: every agent of the channel is an open form of its own: a "Wakes it" select, a "Tier" select reading "1: reading and messages", a switch "Add the loop guard text to its requests", Remove from the channel and Save. Five agents fill the drawer and no line says what a choice does.

## What does the panel show?
- One compact row an agent: picture, name, `@handle`, role badge, state (the card's own, PF2), and in a few words what wakes it and what it may do. The row opens that agent's settings; only one is open at a time.
- The tier is asked as "What may it do by itself?", each choice with a line under it:
  - 1: it only reads and writes messages; every change of a record waits for a person's approval;
  - 2: it applies changes that can be undone, for seven days; the rest wait for approval;
  - 3: it applies every change, deletions and money included; these cannot be undone.
- The wake rule as "When is it woken?": when its name is written, on every message, on messages starting with a word, on answers to its messages; a help line under the chosen one.
- The loop guard as a switch with its reason: agents answering one another are told to stop after a few turns.
- The same words on the Agents card of Site Settings where it names a tier or a door.

## What else of Channel settings is redrawn? (PF7b)
- Owner (2026-11-15, with a picture of the Colour and Icon parts): "bu alanlarıda modernize edelim. switchler sağda ayarlardaki gibi olabilir. renkler ve ikonlar genişlemiş olmasına gerek yok. Kanal adı solunda ikonu seçicisi ve altında veya aynı sırada renk seçicisi daha bütün olurdu. ikonlar ve renkler popover gibi gelebilir".
- The icon is chosen from a button at the left of the channel's name, showing the current icon in the current colour; the colour from a button in the same row or right under it. Each opens a popover: the icon list with its search, the colour swatches with "no colour". Neither list stands open in the drawer.
- Every switch of the drawer sits at the end of its row with its title and help line at the left, as a row of Site Settings does ("Write the linked records' events into the channel" and the others).
- The lists, the search, who may choose and what is stored stay (`workspace/workspace-channel-icon.md`).

## What stays?
- The stored values, who may change them (a channel manager) and what each does: `workspace/agent-risk-tiers.md`, `agent-registry.md`, `channel-members-agents.md`. No schema change.
- Texts through `lang()`, Turkish with `tools/tr_add.php`; the Turkish "Kademe" is replaced, not explained.

## How is it approved?
- A new design: the picture set (light and dark, 1440 and 390 px) goes to the owner in the agent channel, `Approval: channel`.
