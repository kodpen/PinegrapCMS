---
name: forms-subscription-double-opt-in
description: Proposal (2026-10-21, awaiting owner decision): forms that add to a contact group get bot limits, signed link variables, double opt-in and a Turnstile/hCaptcha option
---

## Where does this come from?
- kodpen.com since 2026-10-13: most entries of the subscription form are strangers' real addresses (subscription bombing). The maths CAPTCHA does not stop it.

## What does the code do now?
- `custom_form.php` adds the contact to `contact_group_id` on submission (~1113-1150), no confirmation; an anonymous submission always inserts a new contact (~141, ~1069). The submitter e-mail (~1294-1412) goes to the typed address every time: the victim gets each one.
- CAPTCHA (`includes/fn/auth.php`): "a + b" in plain text (bots parse it), answer in the session with its issue time (`pg_form_captcha_issue()` ~3000), honeypot textarea `body`; skipped for any signed-in session (~3063, ~3118, ~3152). An auto-registration form signs the visitor in (`custom_form.php` ~927). StopForumSpam over plain http (~3173).
- WAF: `custom_form.php` is a sensitive script (`waf.php` ~1819, 30/min per address), skipped for `IS_BOT` (item 4 of `security-findings-2026-10-waf.md`) and any signed-in session (`waf_user_is_authenticated()` ~3350, ~4206).
- Signed links only in campaigns: `pg_email_preferences_signature()` (`includes/fn/mail.php` ~243), footer ~967, `List-Unsubscribe` (`mail_queue.php` ~422). Form mails resolve `^^...^^` in `get_variable_submitted_form_data_for_content()` (`content.php` ~3064) and `key_code` by `replace_variables()`.
- `consent_log` (11.0.16) has no form writer; its statuses and sources are code lists (`privacy.php` ~117, ~127), so `pending`/`form` need no schema.

## Steps (one small PR each)
a. No schema: `waf_rate` buckets in `custom_form.php`: per recipient (one submitter mail and one sign-up per address per 24 h), per address for group forms, per domain; minimum fill time from `issued` (signed time field when CAPTCHA is off); StopForumSpam over https.
b. `^^email_preferences_url^^` (signed, `pg_email_preferences_query()`) and `^^confirm_subscription_url^^` in submitter subject, body and e-mail page, beside `key_code`.
c. Double opt-in: the submission keeps the contact, not the group. Link: HMAC with ENCRYPTION_KEY (own prefix) over contact, group, time; 7 days; no stored state. GET shows a button, POST confirms (mail scanners open links). Confirm joins the group and logs `granted` (ip, UA); submission logs `pending`. A per-form switch needs `custom_form_pages.double_opt_in`: one 2026.11.0 step, label from the owner; the designer form settings get it too.
d. Turnstile/hCaptcha: keys in `connect_apps` `captcha_<provider>` like `sms_<key>` (no schema); `get_captcha_fields()`/`get_captcha_info()` print the widget, so the designer `captcha` section binding stays; server-side verify; host into `waf_csp_policy()`.

## What must the owner decide?
1. Per-form switch (schema) or automatic for groups with `email_subscription` on (no schema, behaviour change)?
2. Turnstile, hCaptcha or both; on a failed verify call refuse or accept?
3. Skip CAPTCHA for signed-in members, or only staff?
4. İYS proof: group in `purpose`, keep IP and UA? (`sms-and-iys.md` step 4)
5. kodpen.com group members since 2026-10-13 never confirmed: remove (recommended) or keep; never send them a reconfirm mail.
6. Bucket numbers.

## Where to start?
- Step a; read `waf/waf-login-captcha.md`, `designer/custom-form-options-and-rules.md`.
