---
name: sms-consent-box
description: The SMS marketing box beside the e-mail box (PR #3660): when it is drawn, how it starts, the consent_log sms rows it writes, where it is not yet
module: privacy
---

## When is the box drawn?
- `pg_consent_sms_offered($has_number)` (`includes/fn/privacy.php`): only while `pg_sms_ready()` (a provider on with a complete account) and the screen has a mobile number: one it asks for, or the contact's `contacts.mobile_phone` (`pg_consent_sms_number_known()`, `pg_sms_number()`). A site without SMS draws nothing new; no number, no box (#3570).
- Member widgets (registration, membership): a control named `opt_in_sms`, kept only when the tree also has a visible control bound to `mobile_phone` (`pg_member_tree_asks_mobile()`); `_pg_member_sms_box()` drops it otherwise, never in the editor. Starts ticked, as `opt_in`.
- The legacy registration and membership screens ask for no number, so they draw no SMS box.
- E-mail preferences: the legacy layouts draw it under `$sms_opt_in` with a hidden `opt_in_sms_shown=1`; the widget fills or drops `opt_in_sms`. Both use the contact's stored number.

## How does it start, where is the answer kept?
- The answer is a `consent_log` row, channel `sms`, purpose `marketing`; no column holds it (`contacts.iys_sms_status` is İYS's own answer, step 8).
- Preferences start from the person's last `sms:marketing` row (`pg_consent_sms_start()`): unticked only after a refusal, ticked with none, as the e-mail box of a DEFAULT-1 contact.

## When is a row written?
- `pg_consent_sms_answers($asked, $number_known, $posted)`: only when the box was drawn and the contact has a number after the save; then through `pg_consent_log_answers()` in the same call as the e-mail answer, so a repeated answer writes nothing.
- Sign-up: `pg_member_consent_answers()` for `pg_member_register()` / `pg_member_activate()`; the entrances set `sms_opt_in_asked` for a widget post whose tree asks it (`pg_member_widget_asks_opt_in(.., 'opt_in_sms')`).
- Preferences: a widget post that lists `opt_in_sms` in `pg_controls`, or a legacy post with `opt_in_sms_shown=1`; a custom layout without the box posts neither, so its silence is no refusal.

## What is not done yet?
- Checkout (billing information, express order, their widgets), custom forms bound to `opt_in`, the designer's default trees and help text: step 7b of `plans/sms-and-iys.md`.
- Commercial SMS stays refused by `pg_sms_send()` until step 9.

## Tests?
- `tests/sms_consent_test.php`: offered rule, start state, answers, changes per channel, member trees, source checks of each drawn box.

Related: docs/memory/core/consent-log-writers.md, docs/memory/plans/sms-and-iys.md
