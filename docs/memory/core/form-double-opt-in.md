---
name: form-double-opt-in
description: Double opt-in for custom forms that add to a contact group (11.0.27): the switch, the signed stateless link, confirm_subscription.php, consent_log pending/granted, the global opt-out
---

## What does the switch do?
- `custom_form_pages.double_opt_in` (`upgrade_2026_11_0_form_double_opt_in()`, 11.0.27, default 0; PR #1387). On: a submission keeps the contact but not the form's group; the contact's own address gets a link; the group is joined only after the button on `confirm_subscription.php`. Off: unchanged.
- Not held when the contact is subscribed already, the group is gone, or the registration/membership group is the same group. Subscribed = in the group and not opted out of it (`pg_fs_subscribed()`): someone who left the group on the e-mail preferences screen may confirm again.
- Legacy page form (inside "Add to Contact Group") and designer form settings (`data-s="double_opt_in"`). Every read and write waits for `pg_fs_ready()`; the designer shows the switch only with `formMeta.doubleOptIn`.
- The form's own confirmation message does not mention the mail; the switch's help text tells the editor to say it there.

## How is the link made and checked?
- `includes/fn/form_subscription.php`. HMAC-SHA256 of `subscribe-confirm:c:g:t:<normalised email>` under `ENCRYPTION_KEY`; query `c`, `g`, `t`, `s`; nothing stored; 7 days, 5 min clock skew. `pg_fs_link_state()`: invalid, member, expired, confirm (member first: a second click says "already subscribed").
- A changed address or key invalidates open links. No key or no contact address: the group stays out, `log_activity()` says why.

## Why GET shows a button and POST confirms?
- Mail scanners open links. GET never writes; POST checks `validate_token_field()` and the signature again. Headers `no-store`, `noindex`, `no-referrer`; frame `output_header_secure()`.

## What does confirming write?
- `pg_fs_confirm()`: the xref row and `opt_in` row = 1, each one `INSERT … SELECT … WHERE NOT EXISTS` (two presses at once leave one row; the xref table has no unique key), `consent_log` granted, `log_activity()`. Writes go through `mysqli_query`, not `db()`, so a failure shows "could not be saved" instead of ending the request.
- `contacts.opt_in` is never set: a contact with every e-mail turned off keeps that, because a yes to one group must not restart the other groups' mail (non-subscription groups read only `contacts.opt_in`). The page then says to turn e-mail back on in the e-mail preferences. Open for the owner: whether the confirm page should offer that switch itself.

## What goes into consent_log?
- channel `email`, purpose `group:<id>`, source `form`, ip and user agent. `pending` is written before each confirmation mail; no row, no mail. At most one mail a day per address and group (`pg_fs_resend_allowed()`, newest pending row by person token).
- The limit is per exact address: plus tags and Gmail dots make new tokens, so a variant gets its own mail a day. The per-recipient and per-domain buckets of #1386 must cover this mail too.
- Pending is never consent: the privacy export prints it as "Waiting for confirmation".

## What must the submitter-mail variable respect?
- `pg_fs_pending_url($form_id)` holds the link for `^^confirm_subscription_url^^` (#1385). The submitter mail goes to `$submitter_email_address`, which may be the signed-in user's address or an e-mail field not bound to the contact, so `pg_cf_confirm_subscription_url()` fills the variable only when that address, normalised, equals `pg_fs_contact_email()` (`pg_cf_confirm_subscription_url_for()`); otherwise someone else would get the link.
- "Alternative confirmation page if contact group" checks membership right after submission, so a held group does not match.

Related: docs/memory/core/consent-log-and-audit-trail.md, docs/memory/plans/forms-subscription-double-opt-in.md
