---
name: decisions-made-workspace-32
description: Decisions made, workspace part 32 (2026-11-15): the site tells an application agent how it works; who an agent or a person is shows in channels; Channel settings and Channel info redrawn
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does the site itself tell an application agent how it works? (owner, 2026-11-15, agent channel message 3748)
- Yes. His words: "ajanın claude.md de belirtilmeden de ajan kanallarında yolunu bulması lazım sonuçta pinegrap cms dağıtıldığında claude.md olmayacak".
- He chose "Evet, planı yaz (Önerilen)": an instructions route read with the agent's own key and a start text copied from the Agents card, no schema. Not chosen: only a fixed text on the card; wait for now. Then: "bu özelliği acil tamamlattıralım".
- Built by #9603, #9600 and #9620: `workspace/agent-instructions.md`.

## Is who an agent or a person is shown in channels? (owner, 2026-11-15, agent channel messages 3775 and 3796)
- Yes, all four parts offered: a card on the name or the picture (role, reported model, plan usage and reset, last seen, waiting requests); a role badge and a state dot beside the name; role and state in the @ menu with a mark when the reported usage is high; the same card for people. His words are in `plans/channel-profile-cards.md`.
- The mark takes back "no warning on plan usage" of 2026-11-13 (`workspace/agent-report.md`). It is a mark: it stops nothing.
- The card (3796): "Çubuklu düzen", one bar a usage window; not chosen: a slimmer card with the percent only. The agent's picture: "ajanın ikonu bu kadar oyun karakteri gibi olmasın", so its own picture, else the panel's robot icon in a quiet tile, never an emoji face. The further facts he chose: `plans/channel-profile-cards-facts.md`.

## How are Channel settings and Channel info redrawn? (owner, 2026-11-15, agent channel messages 3780, 3781, 3783, 3784 and 3786)
- "Agents in this channel" in plain words: "kademe kısmı benim tarafımdan bile ne olduğu anlaşılmıyor" (`plans/channel-profile-cards-panel.md`).
- The drawer (`plans/channel-profile-cards-settings.md`): tabs at its top ("Kanal ayarları tepesine şu tablardan koyarsan"), the icon and the colour as popovers beside the name, switches at the row's end, the conversation versions "daha mantıklı".
- Channel info only tells ("kanal bilgisi kısmında ayar sızmış"), but the reader's notification level and the read-aloud switch stay in it: "bildirim ayarları kanal bilgisinde kalsın o zaman aksi taktirde daha büyük karışıklık olur". Channel settings stays a manager's.
- The architect's, not his words: which part sits under which tab, how the versions list is drawn, Add a person and Manage under People and agents. These are new designs: he approves them on the pictures.
