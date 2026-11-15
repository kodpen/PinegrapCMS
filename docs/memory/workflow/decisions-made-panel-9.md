---
name: decisions-made-panel-9
description: Decisions list, panel part 9 (2026-11-14 on): assistant chat after a reload; a link that opens reads "Aç"; kept screens' two buttons and widths; Site Settings keeps its place after a save
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

## Do the kept screens' two buttons and Add Product's field widths take the new look? (owner, 2026-11-15, agent channel message 4662)
- Yes, both. "Done" in the Sessions drawer of Edit User and "New Offer" on Offers become the look's filled button (`pg-button pg-button-fill`); Add Product (`product_builder`) takes the field widths of add and edit screens (#9658).
- Owner (2026-11-15), on the close-up pairs 4658 (the buttons) and 4659 (the fields): "2 görselin de sonra alanı daha iyi". The architect had recommended the buttons alone.
- Of the full-screen pairs before them (4643 to 4645) he wrote: "açıkcası görselleri inceledim ve bir fark göremedim" (4655); poll 43 closed with no vote.
- The screens stay on the kept list (`decisions-made-panel-3.md`): any other change in their own files still waits for his look at its picture (`plans/panel-old-screens-rules.md`).
- Work: the buttons #9721; the widths as one small PR after #9658 has landed.

## Does the Site Settings dialog keep its place after a save? (owner, 2026-11-15, in the architect's window)
- His words on the "Assistants and agents" pane: "mesela ayarlarda şu anda kaydedince kaldığım yer kapanıp sayfanın üzerine kayıyorum. bunun yerine yar penceresinde bir mesaj yeterli olur. ve gördüğün gibi ayar ekranları modern değil ve karman çorman düğmeler kayıyor bilgiler eksik vs."
- Yes: after a save the pane stays where it was scrolled, the drawer he saved from stays open, and the dialog says "saved" in one message. The agents' settings are redrawn and say plainly what each door is for. Plan: `plans/agents-card-settings.md`.
