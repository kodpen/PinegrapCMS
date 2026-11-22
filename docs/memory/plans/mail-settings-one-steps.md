---
name: mail-settings-one-steps
description: Plan mail-settings-one, steps EM1 to EM6: the E-mail category's two tabs, the Sending tab's rows, the sending domain in one place, one account for both, Sign in with Google, close-out
---

## Which steps are there?
- EM1 (M) first, after MT1 of `plans/mailboxes-tidy.md`, the design files and DX2b (`plans/design-examples-patterns-mail.md`): category `email` with tabs `sending` and `mailboxes` (design A), the tab kept in the address; old addresses, anchors and search words open their tab (`pg_settings_legacy_anchors()`, `fromUrl()`); each tab keeps today's gate, one right shows no tab control. Tests: every old address lands on its tab; a user with one tab's right sees only it. Fixes: C1. Done: #10983.

- EM2 (M) after EM1: the Sending tab as folded rows (`pg_settings_fold()`): "How e-mail leaves" (design A) with one line a kind of mail naming the way it leaves today, Change opening the ways sheet (design B, DX2's `.pg-start`), each way showing only its fields; "Send a test" with its result line. Field names unchanged. Fixes: C3. Done: #11045.

- EM3 (S) after EM2: "Sending domain" as the one place for SPF, DKIM and DMARC: selector, domain and "Generate a key" move into its DKIM row, the DKIM TXT Record card goes. "Campaign mail" row: job switch, Max. a run, Opt-In Label, Plain Text Footer. Campaigns' ways: "The same way as system e-mail" first, a new `config_text` setting; the campaign server's fields only under "Your own mail server". Fixes: C4, C5. Done: #11082.
- Before building the Campaigns sentence, EM3's worker reads which layers carry campaigns (the Sending service, a connected account) and writes it in the PR; a running site's campaigns keep leaving as today.

- EM4 (M) after EM2: one account for sending and reading: System's select lists `mail` and `mail_read` accounts, a box's account marked "Connected for a mailbox"; a `mail` account chosen for a box asks the wider consent on the same row (login hint, scopes kept); removing a box keeps an account sending uses; Connections' Disconnect names both uses; the Connect buttons of both tabs call one helper.
- Tests of EM4: a `mail_read` account is offered for sending; a `mail` account chosen for a box asks again and keeps one row. Fixes: C2.

- EM5 (M) after MT3 and EM4: Add a mailbox as the three-step sheet of design D (Choose, Sign in, Details), the provider buttons of D2 on the site's own Google and Microsoft applications; the return makes the box, runs Test connection and opens Details on "Which channel?"; not set up: "Set up Google sign-in" and "Use an app password". The provider buttons copy DX2b's pattern. Fixes: C7.

- EM6 (S) close-out: MailChimp's key, list and sync to Connections' Mailchimp page, old anchors opening it; `ui/settings-mail-sending.md`, `ui/look-settings-mail-sending.md`, `ui/look-settings-mailboxes.md`, `ui/connections-mailboxes-accounts.md` and `workspace/mail-google.md` corrected; `ui/settings-mail.md` written; this plan's files deleted. Fixes: C6.

## Which steps run side by side?
- EM1 first. After EM1: EM2. After EM2: EM3 and EM4. EM5 waits for MT3 and EM4. EM1, EM4 and EM5 enter `includes/workspace/mail_settings.php` or `includes/settings/mailboxes*.php`: never beside a mailboxes-tidy step open on them.

## How is a step checked?
- An EM step copies its parts from the patterns DX2b puts on the Design examples screen and writes no recipe of its own; it builds a whole part, renders it at 1440 and 390 px before ready and writes "Screen check" in the PR.

Related: docs/memory/plans/mail-settings-one.md, docs/memory/plans/mail-settings-one-screens.md, docs/memory/plans/mail-settings-one-design-box.md
