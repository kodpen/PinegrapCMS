---
name: workspace-create-contact-row
description: /create contact (/oluştur kişi) in a channel: how the line is read, the card, the drawer for a line missing a field, the contacts right per kind, the decision as a tag; /create row still to come
---

## What does /create contact do? (CC7a)
- `/create contact Ada Lovelace, Acme Ltd, ada@example.com` (also `/oluştur kişi`, `kisi`): `ws_command_parse_contact()` takes an e-mail address anywhere, then the parts between commas: the name (first word the first name, the rest the last name), then the company.
- `ws_command_contact()` hands the fields to `ws_command_create_card()`, the same card as /create product: rows of first name, last name, company and e-mail, the decision tick, Create, "All fields", Cancel. No "On sale" tick: a contact has no switch (`ws_quick_create_has_switch()`).
- Written only by Create, through `ws_quick_create_save()` and the contacts writer of the catalogue (`ws_change_write_contact()`, `customer.created`). Several lines or a tag in the line are refused.

## What if the line leaves a required field out?
- The contact's required list is the catalogue's: first name, last name, company, e-mail (`ws_quick_create_missing()`, also read by `ws_quick_create_input()`).
- One left out: the answer is `create_preview` with `preview.missing` (the labels) and the typed values; the composer opens the drawer's new contact filled with them (the plan: an incomplete command opens the form its button opens). The rights are checked first.

## Who is offered it?
- Each kind of /create is the right of its record's screen: `ws_command_create_types_for()` asks `ws_change_allowed()` per kind (products and groups the e-commerce right, a contact the contacts right). `ws_command_gate_create()` offers /create to a person with one kind; `ws_command_create()` refuses a kind outside them with "You may not create this kind of record."; the kind popup (`ws_command_suggestions(null, $viewer)`) lists only theirs. /product keeps `ws_command_gate_product()`.

## What does the decision say?
- "{tag} was added." with no lines: the lines would show the e-mail address to members without the contacts right; the tag shows the contact only to who may see it.

## What about /create row (K22)?
- Not built yet (CC7b): a workflow table has no `#` tag kind, so "the table picked with #" needs one or another picker; the choice is the architect's.

## Tests?
- tests/workspace_command_create_contact_test.php; the kinds in workspace_commands_test.php and workspace_quick_create_test.php.
