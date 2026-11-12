---
name: decisions-made-workspace-21
description: Decisions list, workspace part 21 (2026-11-12 on): the incoming e-mail sits in the messages area, a mail still drops as a card (#8961); the canvas card's line of tables approved (#8964)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Where does the incoming e-mail sit? (owner, 2026-11-12, #8961)
- Inside the messages area, not among the Workspace's channels; its boxes' settings in the settings. A mail still drops as a card into the channel its box is set to; a click on the card opens that e-mail in the area. Plan `plans/messages-area-steps.md` (MA10).
- Owner (2026-11-12): "birde eposta gelen kutusu kanallardan menüye taşıyalım olur mu ayarları da ayarlara taşınsın."; asked where it sits: "eposta iletişim altında da mantıklı aslında".
- Asked about the cards: "eposta gelen kutusu kanallardan kalksın ama kanala yazılan eposta kaslın (eposta cartı ile düşebilir ayarlanılan kanala kullanıcı okumak için tıklar epostaya iletişim altından gider)".
- And: "oradan yanıtladığında da yanıtlandı olarak işaretlenir kanalda. eposta gövde html olabilmeli.": an answer written in the area marks the card in the channel as answered; the body of an e-mail written there can be HTML.
- The architect's, not the owner's words: the name stays "Gelen e-posta", never "gelen kutusu" (`workspace/mail-decisions.md`); what the card keeps besides the link is proposed in MA10a.

## Is the canvas card's line of tables and Bind a table approved? (owner, 2026-11-12, #8964)
- Approved with three corrections wanted in the same PR (#8960, step CO2 of `plans/canvas-one-steps.md`): at 390 px a bound table's name keeps its own line in the Bind a table drawer; the card's Open button reads "Aç" in Turkish through a text of its own; the chip says "okur" where the page only reads.
- Shown the card and the drawer drawn from the PR's own code, wide and phone, the owner (2026-11-12): "Ta, Ma" (Ta: approved, with the three corrections the architect found).
