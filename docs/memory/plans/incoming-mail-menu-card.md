---
name: incoming-mail-menu-card
description: Plan (owner 2026-11-19): Incoming e-mail pinned to the main menu, a card of it on the new dashboard for whoever may read it, the channel named in an open mail's head; IM1-IM3
---

## What did the owner ask?
- Owner (2026-11-19, in the architect's window, agent channel message 9493): "eposta gelen kutusu ana menüye sabitlenebilsin. ve bir widgeti olsun yetki dahilinde gösterim (gelen kutusu mesaj başlıkları yeterli olur tıklandığında gelen kutusuna yönlensin, widget temalarıyla uyumlu bir widget ayarlayalım)".
- And: "eposta bir kanala otomatik yazılıyorsa epostanın başlık alanında yazıldığı kanal adı yazsın."
- He also asked whether more than one mailbox can be set up. It can: the Mailboxes card of Site Settings and of Workspace Settings adds boxes (`workspace/workspace-mail-settings-dialog.md`), the list reads up to 50 and offers a Mailbox select once two have conversations (`workspace/mail-list.md`). Nothing to build.

## What is there today? (read in the code on 2026-11-19, not tried)
- Incoming e-mail is the first tab of Communication, main menu row 9, whose badge is the unread count (`ui/messages-area-shell.md`). A person pins My Notes to their own menu: `ws_profiles.notes_pinned`, row 24 (`ui/main-menu-panels.md`).
- The new dashboard's cards have string ids and one file each; the old dashboard stays untouched (owner 2026-11-01, `ui/dashboard-v2-overview.md`).
- The channel is already written: on a list row after the box's name, and in the open conversation's head as "customer · box · #channel" in small muted text, with Open in channel beside it (`ws_mail_pane_html()`).

## What is built?
- IM1, the pin. Owner poll 154 (2026-11-19) decides between a pin each person sets, as My Notes has, and one site-wide switch; the first needs a column `ws_profiles.mail_pinned`. Either way: a row "Incoming e-mail" in the menu's untitled group under Workspace, in the next free slot (27), shown only to whoever `ws_mail_list_visible()` admits, carrying the unread count, lighting on `messages_mail.php`; Communication's own row and tab stay.
- IM2, the card. A card `mail` on the new dashboard, offered in the gallery to whoever may open the tab: the head with the unread count and a link to the tab, then the newest eight conversations of the Incoming view as the screen lists them (`ws_mail_list()`, so the card never shows what the screen hides): sender, bold while unread, the subject, the time. A row opens `messages_mail.php?conversation=<id>`. Empty: "No e-mail waiting".
- The card reads only the frame's `--pgd-*` tokens, so the four card faces and the backdrops draw it (`ui/dashboard-v2-drawn-look.md`, `ui/dashboard-v2-looks.md`). Its contract: `ui/dashboard-v2-storage.md`.
- IM3, the channel seen. In the open conversation's head the channel leaves the muted line and becomes a pill reading "In #name", a link to the mail's card in the channel; a box kept in no channel shows none. The list row keeps its channel.
- One PR each; IM1 holds the schema step when the poll chooses the person's pin. Each sends one composed picture (light and one dark, Turkish) to the architect before the label.

## What is decided?
- The owner's words: `workflow/decisions-made-product-78.md`. Architect (2026-11-19): the card goes to the new dashboard only, since the old one stays as it is by his decision of 2026-11-01; eight rows and the pill's wording are the reading of his words.

## How does the plan close?
- `ui/messages-area-shell.md`, `workspace/mail-screen-pane.md` and a new `ui/dashboard-v2-mail-card.md` say the new state and this plan is deleted.
