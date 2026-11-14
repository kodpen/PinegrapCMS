---
name: decisions-made-panel-9
description: Decisions list, panel part 9 (2026-11-14 on): the dashboard's assistant chat keeps its last lines after a reload; a link that opens reads "Aç", not "Açık"
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does the dashboard's assistant chat keep its talk after a reload? (owner, 2026-11-14, agent channel messages 3230 and 3236)
- Yes, shortly: after a reload the box "Chat with an assistant" of the Assistant card shows the last 5 lines of the talk started from it, the person's and the assistant's. Nothing else said in the channel comes into the box.
- Owner (2026-11-14), after trying the box: "mesajını aldım ancak sayfayı yenileyince mesajlarda gitti. mesaj geçmişi çok kısa tutulsa 3-5 mesaj iyi olurdu (bu kanalda konuşulanlar buraya gelsin demek değil)".
- The architect's, not the owner's words (work order: channel message 3239): the browser keeps the talk's ids only, never a message's text, and the lines are read again from the server, whose gate decides what comes back; no table. The talk is forgotten 24 hours after its last line; "New chat" beside the title empties it at once.
- Built by #9529 (`ui/dashboard-v2-ai-card.md`).

## How does a link that opens something read in Turkish? (owner, 2026-11-14, agent channel message 3236)
- As the verb, "Aç". The key 'Open' reads "Açık" (the state) and is not used on a link; the links of the Assistant card and the Team cards take the key 'Open it'.
- Owner (2026-11-14): "Açık yerine Aç yazmalıydı, çeviri sorunu var".
