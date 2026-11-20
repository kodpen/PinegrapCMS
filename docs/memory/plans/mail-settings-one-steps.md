---
name: mail-settings-one-steps
description: Plan mail-settings-one, steps EM1 to EM6: the E-mail category's two tabs, the Sending tab's rows, the sending domain in one place, one account for both, Sign in with Google, close-out
---

## Which steps are there?
- EM1 (M) first, after MT1 of `plans/mailboxes-tidy.md` has landed: one "E-mail" entry in the settings list, two tabs "Sending" and "Mailboxes" drawn as the architect's design, the tab kept in the address; the old addresses, anchors and search words open their tab (`pg_settings_legacy_anchors()`, `fromUrl()`); each tab keeps today's gate. Tests: every old address lands on its tab; a user without a tab's right sees only the other. Fixes: C1.

- EM2 (M) after EM1: the Sending tab as folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`): "How e-mail leaves" with one summary line a kind of mail (system, campaigns) naming the way it leaves now, the way chosen as starting points, each opening only its fields; the order of C3 said in that summary, not in a paragraph; "Send a test" with its result line. Field names unchanged. Fixes: C3.

- EM3 (S) after EM2: "Sending domain" as the one place for SPF, DKIM and DMARC: the DKIM key's selector, domain and "Generate a key" move into its DKIM row, the DKIM TXT Record card goes; "Campaign mail" row: the job switch, Max. a run, Opt-In Label, Plain Text Footer, the campaign's own server only as the architect answers C4. Fixes: C4, C5.

- EM4 (M) after EM2, as the architect answers C2: one Google or Microsoft account serves sending and reading; System's select lists accounts of both purposes; an account connected for sending used by a box asks the wider consent on the same account; the Connect buttons of the Sending tab and of Mailboxes call one helper. Tests: a `mail_read` account is offered for sending, a `mail` account chosen for a box asks again. Fixes: C2.

- EM5 (M) after MT3 and EM4: "Sign in with Google" and "Sign in with Microsoft" in Add a mailbox: while the provider's application is set up, one button; the return makes the box from the account's address with its service and account, runs Test connection and opens the box on General for its channel. Not set up: "Set up Google sign-in (once)" and "Use an app password", the two ways that exist. Fixes: C7.

- EM6 (S) close-out: MailChimp moved as the architect answers C6, the addresses of C8 as answered (or named as a next plan); `ui/settings-mail-sending.md`, `ui/look-settings-mail-sending.md`, `ui/look-settings-mailboxes.md`, `ui/connections-mailboxes-accounts.md` and `workspace/mail-google.md` corrected; `ui/settings-mail.md` written; this plan's files deleted.

## Which steps run side by side?
- EM1 first. After EM1: EM2. After EM2: EM3 and EM4. EM5 waits for MT3 and EM4. EM1, EM4 and EM5 enter `includes/workspace/mail_settings.php` or `includes/settings/mailboxes*.php`: never beside a mailboxes-tidy step open on them.

## How is a step checked?
- A screen step builds a whole part, renders it at 1440 and 390 px before ready and writes "Screen check" in the PR; a part not on the Design examples screen waits for the architect's design.

Related: docs/memory/plans/mail-settings-one.md, docs/memory/plans/mail-settings-one-screens.md
