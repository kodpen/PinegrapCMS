---
name: mail-settings-one-screens
description: Plan mail-settings-one, its screens: the E-mail category's two tabs, the Sending tab's rows, Add a mailbox with Sign in with Google, the parts asked of the architect as designs
---

## By which rules?
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, its section in brackets (`workflow/screen-described-in-plan.md`).
- Owner (2026-11-21): a part the Design examples screen lacks is asked of the architect as a design with a picture; its step waits for it.

## How is the E-mail category laid out? (EM1)
- Head: "E-mail", one purpose line [Type and text]: the e-mail the site sends, and the mailboxes it reads.
- Two tabs under the head, "Sending" and "Mailboxes", each with its state pill [Segmented control and status]: Sending "Ready", "Check the domain" or "Not set up"; Mailboxes "n reading" or "Sign-in needed". Asked of the architect: tabs inside the settings dialog.

## How is the Sending tab laid out? (EM2, EM3)
- Folded rows [Rows and gathered rows], one open: "How e-mail leaves", "Sending domain", "Campaign mail", "Send a test".
- How e-mail leaves: a line a kind of mail: an icon tile [Icon tiles and people], "System e-mail" or "Campaigns", the way in words ("Through the Google account info@…", "Through your mail server smtp.…"), "Change" quiet [Buttons].
- Change opens the ways as radio cards, as the New application sheet's starting points (`includes/api/app_new_view.php`): "Your mail server", "A sending service", "A Google account", "A Microsoft account", two a row, one under 576 px; the chosen way's fields only [Fields].
- Sending domain: a row a record (SPF, DKIM, DMARC) and "Bounces and complaints", each with its pill (Good, Check, Missing or wrong) and the record to copy, as the Sending domain card is today [Rows and gathered rows]; the DKIM row adds selector, domain and "Generate a key" quiet.
- Campaign mail: the job switch [Fields], Max. a run, Opt-In Label, Plain Text Footer; the campaign server's fields only when campaigns leave another way.
- Send a test: the address it goes to and "Send a test" quiet; the result as one line with its pill.
- Foot: Save the fill, one only.

## How is a mailbox added? (EM5)
- From Mailboxes' "Add a mailbox" (MT3's starting points): "Gmail or Google Workspace" shows, while Google's application is set up, one "Sign in with Google" button and a line: "Pinegrap reads and answers this mailbox." Microsoft the same.
- Back from the sign-in: the box is made, Test connection runs, the box opens on General with its address filled and "Which channel?" first.
- Not set up: two radio cards, "Set up Google sign-in (once, about 10 minutes)" and "Use an app password (2 minutes)".
- Asked of the architect: the Add a mailbox steps (choose, sign in, details, done) as one sheet with its progress; the provider sign-in buttons within Google's and Microsoft's brand rules; Mailboxes with no box yet; the Remove mailbox dialog listing what happens (`plans/mailboxes-tidy.md`).

## Which words?
- "E-mail", "Sending", "Mailboxes", "How e-mail leaves", "Sending domain", "Campaign mail", "Send a test", "Sign in with Google", "Use an app password"; never "SMTP Settings", "transport", "purpose", "mail_read" or a constant's name.

## What must be true?
- One "E-mail" entry in the settings list; `settings_mail_sending.php` and `settings_mailboxes.php` open its Sending and Mailboxes tabs.
- DKIM's record and "Generate a key" appear only in the Sending domain row.
- A Google account connected for a mailbox is offered on How e-mail leaves.
- With Google's application set up, Add a mailbox › Gmail shows one "Sign in with Google" and no Connections link.

Related: docs/memory/plans/mail-settings-one.md, docs/memory/plans/mail-settings-one-steps.md
