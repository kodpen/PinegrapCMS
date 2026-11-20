---
name: mailboxes-tidy
description: Plan (owner 2026-11-20): Site Settings › Mailboxes tidied: folded parts, a box's drawer in four tabs, a mailbox removed from its drawer, a new box from starting points; MT1 to MT4
priority: 8
---

## What is asked?
- Owner (2026-11-20, in the architect's window, with a picture of a box's drawer): "bu ayar çorba gibi bir ekran olmuş. epostayı kaldıracak düğme falan yok"; a Gmail account connected on Connections read as not connected here (#10825).
- The rules: `ui/screen-composition-rules.md`, `workflow/screen-described-in-plan.md`. Parts are copied from the Design examples screen, its section in brackets.

## What is there today?
- `includes/workspace/mail_settings.php`: the box's drawer is one form of about thirty fields (name, place, checking server, three empty routing rows, reading, sending, automatic answer) with paragraphs of help; the Gmail steps stand inside it (`workspace/mail-google.md`). No action removes a box.

## How is the pane laid out?
- A purpose line, then folded rows [rows], one open: "Mailboxes", "Accounts for mailboxes", "Incoming mail rules", "Blocked senders", and "Bring in an .eml file" last.
- Mailboxes: a list row a box: tile, name, address and where it is kept, a state pill [status] (Reading, Off, Sign-in needed, Error with its first words), the last check. Actions quiet small: Test connection, Edit; the row's menu: Turn off, Remove. "Add a mailbox" is the pane's one fill.
- Accounts for mailboxes: Google and Microsoft as connect rows; the state in the words Connections uses; "Not connected" has its "Connect" button beside it.

## How is a box's drawer laid out?
- Head: name, address, the state pill. Tabs [segmented control]: General, Reading, Answers, Routing.
- General: name, e-mail address, kept in (A channel, The mailbox itself as segments), the channel, On, Support box, signature.
- Reading: mail service, then only that service's fields; user and password or the connected account; folder, after reading. Folded under "Advanced": server and port when the service fills them, the checking server ("The server whose sender check is trusted"), reads bounces. Test connection quiet.
- Answers: send through, the server's fields only for an own server, the sent copy and Sent folder, automatic answer, Pinegrap AI suggests.
- Routing: a row a route (address or +tag, channel) with Remove; "Add a route" quiet; no empty rows; one line of help.
- Foot: Save fill, Cancel quiet, "Remove mailbox" danger. Its confirm says what happens: reading stops, the stored password is deleted, its rules go, the conversations stay where they are.
- One line of help a field; no paragraph in the form.

## How is a box added?
- Starting points (cards with an icon and a line): Gmail or Google Workspace, Microsoft 365 or Outlook, Another mail server, No server (.eml files). The choice sets the service and opens the drawer on Reading with that way's fields.
- Google's and Microsoft's set-up outside the site: numbered steps in a drawer "Set up the Google application", opened by one button.

## Which steps are there?
- MT1 (M) first, after #10825's fix has landed: the pane's rows, the box row with its pill and menu, Remove (check and write; tests: conversations stay, the box's rules and password go).
- MT2 (M) after MT1: the drawer in tabs, Routing as rows, the folds, help cut to a line.
- MT3 (S) after MT2: the starting points and the set-up drawers.
- MT4 (S) close-out: `ui/look-settings-mailboxes.md`, `workspace/workspace-mail-settings-dialog.md` and `workspace/mail-google.md` corrected, this plan deleted.

## What binds it?
- Field names, ids and what a save stores do not change: the save module and the settings search read them; a test pins the posted names.
- `mail_settings.php` is entered by one step at a time. The folded row is the one AT1 of `plans/assistants-settings-tidy-steps.md` builds.
- Architect (2026-11-20): Remove deletes the box's settings and never a conversation.
