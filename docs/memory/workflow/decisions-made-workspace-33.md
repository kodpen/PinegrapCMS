---
name: decisions-made-workspace-33
description: Decisions made, workspace part 33 (2026-11-15): what a guest sees of agents and canvases in a room; the four agent work features of poll 49; the open items line above a channel
---

## What does a guest see of what agents and canvases put in a room? (owner, 2026-11-15, in the architect's window)
- His words: "workspace_guest.php de görsel, tuval (sadece önizleme) ve diğer sayfa içi içerikleri de gösterelim olur mu? hepsi salt okunur. düzenleme yetkisi olan bir guest için de tuval vs. erişimi olmasın ama görüntüleyebilsin".
- So an agent's picture and file show as a person's; a canvas shows as its drawn picture only, also to a guest who may write; nothing of it is changed from the guest page.
- Asked how a live page shows to a guest, he chose "Kart ve Aç (Önerilen)": the page's card with Open and Full screen, run in the guarded frame as in the panel.
- Asked what a record's tag shows to a guest, he chose "Yalnız türü, bugünkü gibi (Önerilen)": the tag reads as its kind alone, as before.
- The architect's readings, not his words: "diğer sayfa içi içerikler" are the agent's task card and the live page; a live page has no View source, versions, Undo or Download for a guest; mail cards, change cards, buttons, request lines and the lines of applications that are no agents stay hidden.
- Unchanged: a guest's own abilities, and that nothing a guest does asks an agent (`decisions-made-workspace-24.md`).

## Does the guest page say an agent's role and model? (owner, 2026-11-15, in the architect's window)
- His words, on a picture of the guest page: "ajan yazıyor ama rol leri ve modelleri yazmıyor o konuda da bir düzeltme yapalım".
- So beside an agent's name the guest page shows the badge, then the agent's role and the model it reported; an empty fact is left out.
- The architect's reading: no usage, no state dot and no card for a guest, as `workspace/agent-card-look.md` keeps them from a guest.
- Plan: guest-page-content in the plans folder (GP1 to GP3), brought by GP1's PR #9693.

## Which of the R&D agent's four proposals are built? (owner, 2026-11-15, agent channel poll 49)
- Asked in channel message 5089 as a poll with several answers; he chose all four: "1 Görev metni", "2 İlerleme notu", "3 Ajana görev verme", "4 Doğru / Yanlış". Why (the proposals, messages 5084 to 5087): a site with several agents must say who does what, show where a long request stands, give work as tasks and know which agent answers well.
- Built by `plans/agent-work-features.md` in the order he was told: the duty text and the progress note, the task hand-over, Right or Wrong last.
- The architect's readings, not his words: the card shows the duty to whoever may read the channel; only a person hands a task to an agent; the asker and the channel's managers mark an answer; a Wrong only counts and wakes nobody.

## What stands above a channel's conversation for its open items? (owner, 2026-11-15, agent channel message 5143)
- His words: "oylama ve ajan sorusu, checkin ve onay isteği gibi özellikler bir kanalda kullanılmışsa kanalın üst tarafında kısaca ne olduğu ile kaydırma düğmesi yer alsın."
- So a line in the strip above the conversation names the channel's open polls, agent questions, check-ins and approval requests, each with a button that scrolls to it: `plans/channel-open-items.md`. Its pictures go to him before it lands.
