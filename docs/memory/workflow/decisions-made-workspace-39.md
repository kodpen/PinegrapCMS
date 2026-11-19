---
name: decisions-made-workspace-39
description: Decisions made, workspace part 39 (2026-11-19): a general memory for the agent channels by project; a new agent channel reads the others' decisions; the Decisions and Files tabs' new look
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-38.md`.

## Is memory general, and how is it listed? (owner, 2026-11-19, in the architect's window)
- "bellekler de aynı görevler gibi kart kart duruyor bunlar görsel 1 deki gibi proje ve hafıza başlığı şeklinde depolansa çok iyi olurdu. bellek o sohbete özel değil de genel olsaydı sohbet penceresini silip yeni ajan kanalı sohbeti açabilirdim." His picture: the Claude app's memory list, project headings that fold with one row per record.
- A channel's own records stay, so running sites keep theirs; the general memory is added beside them. Schema: `decisions-made-schema-27.md`. Plan: `plans/workspace-memory-general.md`.

## Does a new agent channel read the other agent channels' decisions? (owner, 2026-11-19, agent channel poll 151, message 9480)
- "evet ama bugün için ajan kanalını ajan kanalı olarak nitelendiren bir seçenek yok bu nasıl olacak", on the question whether a newly opened agent channel also sees the decisions made in the other agent channels (a deleted channel's decisions go with it, an archived channel's stay).
- The architect's answer in the channel that day: the switch exists as a command only; the next entry.

## Does the agent channel switch show on screen? (owner, 2026-11-19, agent channel poll 152, message 9481)
- "Evet: kanal ayarlarında ve Yeni kanal formunda 'Ajan kanalı' anahtarı olsun". Until then only the command `/ajan-kanalı aç` switched it (`workspace/agent-channel-flag.md`), and the development channel read as switched off that day. Step GM4 of `plans/workspace-memory-general.md`.

## How do a channel's Decisions and Files tabs look? (owner, 2026-11-19, in the architect's window)
- Decisions: "karar uzunsa katlansın ve kararlar dikey kaydırmalı kartlarda olsun(daha geniş kartlar olsun) bu alan modernleşsin."
- Files: a count on the tab, and less stutter with the channel's 298 pictures: "tasarım olarak oldukça hoş ama kasmayı azaltacak bir yol varsa iyi olur."
- Built in TD1 (#10396) and TD2 (#10415): `workspace/workspace-channel-panes.md`, `workspace/workspace-channel-files-tab.md`.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workspace/channel-memory.md, docs/memory/workspace/workspace-channel-panes.md
