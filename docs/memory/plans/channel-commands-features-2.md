---
name: channel-commands-features-2
description: Plan channel-commands, features K15 to K28: /bildirim, /davet, /ayrıl, /konu, /ara, /bellek, /oluştur for a contact, row, ERP account and event, /kargo, application arguments, an agent's command
---

## The channel's own (CC6)
- (K15) /bildirim (/notify) `hepsi | anma | hiç` (`all | mentions | never`): the writer's notifications of this channel (`ws_channel_set_notify()`). Without a value the answer names the choice in force and the three words. To the writer alone.
- (K16) /davet (/invite) `@people`: "Add people" (`ws_channel_members_add`) with its rights. The answer names who was added and who was in already.
- (K17) /ayrıl (/leave): a confirmation card to the writer alone, then `ws_channel_leave()`. The menu's refusals are kept.
- (K18) /konu (/topic) `<text>`: the channel's "What it is about" (`ws_channel_update`), the menu's rights. Without a text it shows the present one to the writer.
- (K19) /ara (/search) `<words>`: opens the palette on this channel with the words. Nothing is posted and nothing asked of the server by the command itself.
- (K20) /bellek (/memory) `<text>`: a memory entry (`ws_memory_save()`), as the Memory tab's "New entry"; typed as a reply it carries that message's link.

## Records (CC7 to CC9)
- (K21) /oluştur kişi (/create contact): the create card with first name, last name, company, e-mail and "All fields", written through the catalogue of `includes/workspace/changes.php` (`ws_quick_create_types()` grows by the kind).
- Architect (2026-11-17, agent channel messages 8138, 8140): a contact is complete with at least one of the four, as the catalogue's create check has it; no field is starred, and only a line with none of them opens "New contact". The argument strip shows the typed kind's own arguments.
- (K22) /oluştur satır (/create row) `<table>`: a row of a workflow table. The words after the kind are matched against the names of the tables the person may write (the whole name, else a prefix only one has): one match shows that table's fields, none or several put a select of those tables first. `ws_wtable_row_create()` writes with the table's rights.
- Architect (2026-11-17, message 8138): no `#` tag kind for a table. A tag kind reaches every message box, the stored tokens and the API's tag types: a feature of its own, the owner's to want.
- (K23) /oluştur cari (/create account): an ERP account through the catalogue. Offered only with the ERP module on and the right.
- (K24) /oluştur etkinlik (/create event): a site calendar event through the catalogue.
- (K25) /kargo (/tracking) `#order <number>`: the order's tracking number through the old-and-new card and Save. The card says the customer is not mailed. "Keep it in this channel as a decision" as /price has it.
- Each of K21 to K25 is offered only to a person the record's screen lets write, and refuses as that screen does.

## Applications and agents (CC10 to CC12)
- (K26) An application command's arguments: a definition kept with the command in the registry's own argument shape (the kinds of `ws_command_argument_kinds()`), so the argument list and the strip work as for a built-in command. The event `workspace.app.command` carries the values by name beside the raw text. A command without a definition works as today. The call that registers a command refuses an unknown kind and more arguments than the limit.
- (K27) An agent's command: an application agent that waits on its request queue registers commands as an application does. Typed, the line is posted in the channel under the writer's name and a request hangs on that message (`ws_ai_requests.message_id`), the command and its values in the brief. A mention's brakes apply (`workspace/agent-doors.md`); an agent not allowed in the channel is not offered there. The webhook event is unchanged. No schema.
- (K28) An application's answer for the writer alone, after a census of its own: where such an answer is kept and how long, how the application sends it for one command, what a phone and a second window of the writer show.
