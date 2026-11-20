---
name: mail-settings-one-screens
description: Plan mail-settings-one, its screens: the E-mail category's two tabs, the Sending tab's rows, Add a mailbox with Sign in with Google, built from the architect's design files
---

## By which rules?
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, its section in brackets (`workflow/screen-described-in-plan.md`).
- The parts the Design examples screen lacks are drawn in `mail-settings-one-design.md` (boards A to C) and `mail-settings-one-design-box.md` (D, E), approved by the owner (poll 211); a step builds them as drawn.

## How is the E-mail category laid out? (EM1)
- Head: "E-mail", one purpose line [Type and text]: the e-mail the site sends, and the mailboxes it reads.
- The two tabs "Sending" and "Mailboxes" with their state pills: design A.

## How is the Sending tab laid out? (EM2, EM3)
- Folded rows [Rows and gathered rows], one open: "How e-mail leaves", "Sending domain", "Campaign mail", "Send a test".
- How e-mail leaves: design A; Change opens the ways sheet of design B: "A Google account", "A Microsoft account", "A sending service", "Your own mail server", and for campaigns "The same way as system e-mail" first.
- Sending domain: a row a record (SPF, DKIM, DMARC) and "Bounces and complaints", each with its pill and the record to copy, as the Sending domain card is today; the DKIM row adds selector, domain and "Generate a key" quiet.
- Campaign mail: the job switch [Fields], Max. a run, Opt-In Label, Plain Text Footer.
- Send a test: the address it goes to and "Send a test" quiet; the result as one line with its pill.
- Foot: Save the fill, one only.

## How is a mailbox added and removed? (EM5, mailboxes-tidy)
- Mailboxes with no box: design C.
- Add a mailbox: the three-step sheet of design D (Choose, Sign in, Details), no "Done" step: Finish closes the sheet and the list shows the box.
- The provider buttons: design D2, within Google's and Microsoft's brand rules.
- Remove mailbox: the dialog of design E, listing what stays and what goes.

## Which words?
- "E-mail", "Sending", "Mailboxes", "How e-mail leaves", "Sending domain", "Campaign mail", "Send a test", "Sign in with Google", "Use an app password", "The same way as system e-mail"; never "SMTP Settings", "transport", "purpose", "mail_read" or a constant's name.

## What must be true?
- One "E-mail" entry in the settings list; `settings_mail_sending.php` and `settings_mailboxes.php` open its Sending and Mailboxes tabs.
- DKIM's record and "Generate a key" appear only in the Sending domain row.
- A Google account connected for a mailbox is offered on How e-mail leaves, marked "Connected for a mailbox".
- With Google's application set up, Add a mailbox › Gmail shows one sign-in button and no Connections link.
- A running site's campaigns leave as before until the administrator chooses another way.

Related: docs/memory/plans/mail-settings-one.md, docs/memory/plans/mail-settings-one-steps.md, docs/memory/plans/mail-settings-one-design.md, docs/memory/plans/mail-settings-one-design-box.md
