---
name: workspace-create-contact-row
description: /create contact (/oluştur kişi) in a channel: how the line is read, the card, the drawer for a line missing a field, the contacts right per kind, the decision as a tag; /create row still to come
---

## What does /create contact do? (CC7a, PR #10187)
- `/create contact Ada Lovelace, Acme Ltd, ada@example.com` (also `/oluştur kişi`, `kisi`): `ws_command_parse_contact()` takes an e-mail address anywhere, then the parts between commas: the name (first word the first name, the rest the last name), then the company.
- `ws_command_contact()` hands the fields to `ws_command_create_card()`, the same card as /create product: rows of first name, last name, company and e-mail, the decision tick, Create, "All fields", Cancel. No "On sale" tick: a contact has no switch (`ws_quick_create_has_switch()`).
- Written only by Create, through `ws_quick_create_save()` and the contacts writer of the catalogue (`ws_change_write_contact()`, `customer.created`). Several lines or a tag in the line are refused.

## What if the line leaves a field out?
- The required list is the catalogue's, read as `ws_change_create_check()` reads it: at least one of first name, last name, company, e-mail (architect, 2026-11-17, agent channel message 8140). `ws_quick_create_missing()` says that for every kind (a product's list is its name alone) and both the card and `ws_quick_create_input()` ask it.
- A line with one of the four answers with the card (no row for what was not written). A line with none answers `create_preview` with `preview.missing`, and the composer opens the drawer's empty new contact.
- The drawer stars only a list of one (`ws_quick_create_starred()`): none of a contact's four. Create with all four empty is refused with "A customer needs at least a name, a company or an e-mail address." (`ws_quick_create_missing_error()`).

## What does the strip show?
- Once the kind word is typed, the registry entry's `variants` line for it (architect, message 8138): `/oluştur kişi <ad soyad>, <şirket>, <e-posta>` with its own example. Product and group keep the command's line; before a kind, `<tür>`. `commandArgument()` in workspace.js swaps the arguments; an argument's `after` writes the comma. CC7b and CC8 add their kinds' lines there.

## Who is offered it?
- Each kind of /create is the right of its record's screen: `ws_command_create_types_for()` asks `ws_change_allowed()` per kind (products and groups the e-commerce right, a contact the contacts right). `ws_command_gate_create()` offers /create to a person with one kind; `ws_command_create()` refuses a kind outside them with "You may not create this kind of record."; the kind popup (`ws_command_suggestions(null, $viewer)`) lists only theirs. /product keeps `ws_command_gate_product()`.

## What does the decision say?
- "{tag} was added." with no lines: the lines would show the e-mail address to members without the contacts right; the tag shows the contact only to who may see it.

## What about /create row (K22)?
- CC7b, its own PR. No new `#` tag kind (architect, message 8138): the words after `satır` match the names of the tables the person may write (the whole name without case, else a prefix only one table has); none or several make the table the card's first field.

## Tests?
- tests/workspace_command_create_contact_test.php; the kinds in workspace_commands_test.php and workspace_quick_create_test.php.
