---
name: settings-mail
description: Site Settings › E-mail as a whole (plan mail-settings-one, EM1 to EM6): the two tabs, what each holds, the architect's eight answers, the approved drawing, which file holds each part
---

## What is the E-mail category?
- One category `email`, "E-mail", with the tabs "E-mail sending" and "Mailboxes", each under the gate its old category had; one right shows no tab control (`settings-email-tabs.md`, EM1 #10983).
- Asked by the owner (2026-11-21): "eposta ayar menüsü tek olabilir. içinde 2 ye tablar ile ayrılır"; the two screens sent the reader to each other and offered no Google sign-in for a box.

## Which drawing does it follow?
- `ui/email-settings-drawing/email-settings.html` and `.jpg`, boards A to E, approved by the owner (poll 211, `workflow/decisions-made-panel-14.md`). Its parts are the Design examples screen's patterns (`look-mail-patterns.md`); a change copies them, never the picture.
- A: the tabs with a state pill; How e-mail leaves as a line a kind of mail with Change. B: the ways sheet. C: Mailboxes with no box. D: Add a mailbox in three steps. E: Remove mailbox listing its effects.

## What does the Sending tab hold?
- Four folded rows: How e-mail leaves, Sending domain, Campaign mail, Send a test (`settings-mail-sending.md`, `settings-mail-sending-rows.md`, EM2 #11045, EM3 #11082). Its look: `look-settings-mail-sending.md`.
- DKIM's selector, domain, record and "Save and generate a DKIM key" only in Sending domain's DKIM row (C5).
- Campaigns may leave "The same way as system e-mail", a way the administrator chooses (`core/mail-campaign-way.md`, C4).
- MailChimp is not in it since EM6: its key, list and sync are on Mailchimp's page of Connections; Campaign mail shows the connection (`connections-mailchimp-page.md`, C6).

## What does the Mailboxes tab hold?
- The rows of mailboxes-tidy (`look-settings-mailboxes.md`); Add a mailbox is the three-step sheet with Sign in with Google or Microsoft on the site's own application (`workspace/mail-add-sheet.md`, EM5 #11175); Remove mailbox lists its effects (`workspace/mail-box-remove.md`).

## What did the architect answer? (2026-11-21, agent channel message 11313)
- C1: one category, tabs kept in the address, each tab its old gate.
- C2: one `connect_accounts` row per provider account for sending and reading; removing a box never disconnects an account sending uses (`api/mail-one-account.md`, EM4 #11103).
- C3: How e-mail leaves says in one sentence a kind of mail what happens today, whatever layer decides it; no paragraph about the layers' order.
- C4: nothing switches by itself on a running site (`core/mail-campaign-way.md`).
- C5: DKIM in one place. C6: MailChimp to Connections, the old anchors opening its page; Opt-In Label and Plain Text Footer in Campaign mail.
- C7: a Gmail box signs in on Add a mailbox, no trip to Connections while Google's application is set up.
- C8: the addresses the mail uses stay with their features (Support E-mail Address on General, the others on Security and Commerce): no copy in the Sending tab; the settings search finds them.

## What did the owner say no to?
- No connect.pinegrap.com broker and no Google verification now (poll 212, `workflow/decisions-made-panel-14.md`): sign-in runs on the site's own application, and a site without one sees "Set up Google sign-in" and "Use an app password".

Related: docs/memory/ui/settings-email-tabs.md, docs/memory/ui/settings-mail-sending.md, docs/memory/ui/look-settings-mailboxes.md
