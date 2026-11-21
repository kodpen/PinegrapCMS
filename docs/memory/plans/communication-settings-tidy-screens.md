---
name: communication-settings-tidy-screens
description: Plan communication-settings-tidy, its screen: the Communication pane as folded rows, Chat and Chat on the site with their groups, the words and what must be true
---

## By which rules?
- `ui/screen-composition-rules.md`; parts copied from the Design examples screen, its section in brackets (`workflow/screen-described-in-plan.md`). No part is new: nothing to ask the architect for a drawing.

## How is the pane laid out?
- Head: "Communication" and one purpose line [Type and text]: how people reach the site and the site reaches them.
- Folded rows [Settings pane], one open, each line saying its state ("On", "On, answered by Ayşe", "Not set up"), in this order: "Chat", "Chat on the site", "SMS", "SMS messages", "WhatsApp", "WhatsApp messages", "İYS".
- Chat: "Chat" [Fields, switch] with "Team members chat with each other in the panel, and visitors on the site when the bubble is on. Off, no chat shows anywhere."; Folder for chat attachments and its line; Chat Retention Period until VP3.
- Chat on the site: "Chat bubble on the site" [Fields, switch]; "Who answers visitors" [Fields, select] with "Visitors who are not signed in see the bubble only while someone is chosen here. Signed-in members write to the team from their own chat list."; "Email the operator when a message arrives while offline".
- Then a small label "The bubble" [Type and text]: Bubble label, Welcome message, Theme, Colour, Icon side by side from 768 px.
- Then a small label "Visitors who are not signed in": "Images", "Files", "Voice messages" [Fields, switch], Images per conversation, "Ask a puzzle before the first message" with "Forms and sign-in have their own under Accounts and sign-in" (a link); one line: "Team members and signed-in members may always send files."
- With Chat off: the bubble row's line says "Chat is off: nothing shows on the site"; its fields stay drawn.
- SMS to İYS: today's content, the switches `pg-switch`.
- Under 576 px: rows full width, Theme, Colour and Icon one under another. Foot: Save the fill, one only.

## Which words?
- "Chat", "Chat on the site", "Chat bubble on the site", "Who answers visitors", "Visitors who are not signed in", "Ask a puzzle before the first message"; sentence case; no name starts with "Enable".

## What must be true?
- Every field posts under today's name; `contact.save.php` stores what it stored.
- `#settings/contact/pgset-chat`, `…/pgset-sms`, `…/pgset-sms-messages`, `…/pgset-whatsapp`, `…/pgset-whatsapp-messages`, `…/pgset-iys` open their rows; `chat_operator_user_id` opens "Chat on the site".
- No popover is left, nothing more than one fold deep.
- The settings search finds "operator", "bubble", "captcha" and "attachments".

Related: docs/memory/plans/communication-settings-tidy.md, docs/memory/plans/communication-settings-tidy-steps.md
