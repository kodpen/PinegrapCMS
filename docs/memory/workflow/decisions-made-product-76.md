---
name: decisions-made-product-76
description: Decisions list, product part 76 (2026-11-17): connectors managed in one place on Connections (poll 146); Voice message transcripts card moves (147); "E-posta gönderimi" category (148)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-75.md`.

## Where are the connectors of outside services managed? (owner, 2026-11-17, agent channel poll 146, message 9334)
- Asked how GitHub's four screens merge (CX1), with "nothing moves, the screens point at each other" recommended, he chose no option and typed: "bu bağdaştırıcıları bir düzene sokmazsak ileride çorba olacak lansman sonrasına kalmamalı. bağdaştırıcıları tek yerde yönetilir modern bir yönetim ekranı yapmalıyız. bu ve diğerleri"
- So piece-by-piece merges are not what he wants: one modern screen manages the connectors in one place, GitHub's and the others', and it is not left until after the launch.
- The architect's reading, told to him under the poll (messages 9372, 9374), not his words: the screen is Connections (`connections.php`); what is typed to connect to an outside service sits in that service's own panel there (connect, test, disconnect, where it is used); a feature card holds no key and names the connection it uses.
- It turns the 2026-10-30 reading that a Connections row shows no field and only leads to the setting's place (`decisions-made-product-14.md`). The plan `plans/connections-merges.md` carries priority 10 with his limit, before the launch.
- The integration packages themselves still wait until after 2026.11.0 (`decisions-made-product-69.md`): this orders what exists.
- Still his: the screen's look. A mock comes first (the list, the GitHub, Google and OpenAI panels, one feature card, the entry); nothing moves before his yes to its sheet.

## Where does the Voice message transcripts card sit? (owner, 2026-11-17, agent channel poll 147, message 9342)
- Asked how the AI keys' six places merge (CX2), he chose "Sesli mesaj dökümleri Asistanlar ve ajanlar'a taşınsın": the card moves from Site Settings › "Kanallar, e-posta ve ses" to Assistants and agents, after Pinegrap AI, and the category it leaves is named "Kanallar". No field changes; old links open the new place.
- Staying where they are, as the poll told him: the subscription key (General › Software; it is the licence), the Google Cloud Translation key (Languages and Translation; not a model, and it works on a site without the Workspace), Application Access.
- Not chosen: the Google translation key moving too; nothing moving.
- After poll 146 the card's key field is to move to OpenAI's panel on Connections; that waits for his yes to the mock.

## Where is e-mail sending set up? (owner, 2026-11-17, agent channel poll 148, message 9367)
- Asked how its four places merge (CX3), he chose "Site Ayarları'nda "E-posta gönderimi" kategorisi olsun": a new Site Settings category between Communication and Mailboxes, for a manager as today. It holds the sending service and the system e-mail, the campaign card with Communication's two texts (İleti İzni Etiketi, Düz Metin Altbilgisi), the sending domain and MailChimp.
- SMTP Settings leaves System tools and its old addresses open the category; the test and DKIM buttons become "Kaydet ve …". The poll told him this takes into the dialog a screen the 2026-10-30 arrangement had kept outside as a tool. Two steps, no table or column.
- Unchanged in every option: Google's and Microsoft's application on Connections, each mailbox's server in Mailboxes.
- Not chosen: SMTP Settings staying on System tools as the one mail place; nothing moving.
- He voted after typing poll 146's answer, so it narrows the reading there: the sending service's key and the SMTP server stay in this category; Connections shows e-mail sending as one row whose "Yönet" opens it.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-product-75.md
