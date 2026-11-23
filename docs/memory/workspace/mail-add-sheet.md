---
name: mail-add-sheet
description: Add a mailbox as the three-step sheet (EM5 #11175): Choose, Sign in, Details; the provider buttons on the site's own applications, the return, Test connection before the box is stored, Finish
---

## What is drawn?
- Site Settings › E-mail › Mailboxes › "Add a mailbox" opens the drawer `pgset-ws-mail-0` as a sheet (board D of `ui/email-settings-drawing/`, `ui/settings-mail.md`): the step line "Choose", "Sign in", "Details" in the band, no Done step (`includes/workspace/mail_add.php`, `ws_mail_add_sheet()`).
- Choose: the four starting points as choice cards (`ws_mail_start_points()`: Gmail, Microsoft, Another server, No server); Continue fill, Cancel quiet. No server skips Sign in.
- Sign in: Gmail or Microsoft with the site's application set up and a manager signed in (`ws_mail_add_ready()`) shows the provider's own button and one line. Otherwise Gmail shows "Set up Google sign-in" (opens Google's numbered steps drawer) and "Use an app password"; Microsoft a line and "Set up the Microsoft application". Another server and the app password show the box's server fields and "Test connection".
- Details: the result line, "Which channel?" (focused; Kept in above it when the Workspace is on), Name and E-mail address, "Other settings" folded (On, Support box, signature, answers, routing). Finish is `ws_mail_box_save` value 0.
- Foot: Back ghost from Sign in on, Cancel quiet, the step's fill.

## How does a sign-in come back?
- The button submits a form outside the settings form (`ws_mail_add_connect_forms()`: connect.php, purpose `mail_read`), returning to `settings_mailboxes.php?ws_mail_add=gmail|microsoft#pgset-ws-mail`.
- `pg_connect_flash()` now keeps the connected account's id, and a copy for `pg_connect_last_take()`: the Sending tab takes the flash first on the E-mail page.
- `ws_mail_add_returned()` checks the account (site's, the provider's, holds `mail_read`), tests it in memory (`ws_mail_add_probe_box()`, `ws_mail_box_test()`) and leaves the fields and the result in the session (`ws_mail_add_pending()`, 30 minutes). The sheet opens once on Details; a refused sign-in opens Sign in with "The sign-in did not finish".

## Why is the box stored only at Finish?
- A box needs a channel, asked on Details; a box made on return would be left behind by Cancel. The drawing's caption says Finish closes the sheet and the list shows the box.
- Test connection on Sign in (`ws_mail_add_test`) tries the typed server without storing; a failure marks the password (a service's server) or the IMAP server (another server) like a refused save. A pass keeps the sealed password in the session; Finish uses it when the password box is empty for the same server and user (`ws_mail_add_finish_input()`).
- After Finish the new box is tested once more (`mail_test`) so its row shows its pill.

## What was seen, what not?
- Sandbox, 1440 and 390 px: no application, Google set up, Microsoft set up, the app password with a failed test, another server, No server's Details, a sign-in refused at Google (access_denied).
- Not run: a real Google or Microsoft consent and the green Details after it (no account in the cloud session); the sheet inside the settings dialog after Test connection (resume clicks the Add button once the pane is painted). No bottom sheet under 576 px: the drawer is full width there.

Tests: `tests/workspace_mail_add_test.php`, `tests/workspace_mail_drawer_test.php`.

Related: docs/memory/workspace/mail-box-start-setup.md, docs/memory/api/mail-one-account.md, docs/memory/workspace/mail-google.md
