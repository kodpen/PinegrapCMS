---
name: form-submission-limits
description: custom_form.php per visitor address, per e-mail and per domain limits in waf_rate - atomic counting before the hook, inbox folding, settings, why the domain limit is off by default, known weak spots.
---

## What are they?
- `waf_rate` rows (`pg_cf_throttle_subjects()`): `cfi` the visitor's `waf_ip_subject()` per hour, `cfr` the address per day, `cfd` its domain per hour. `waf_rate_key()` hashes subjects, so no address is stored. PR #1386; the other checks: `waf/form-submission-checks.md`.
- `cfr`/`cfd` only when the form mails the submitter, adds to a contact group or auto-registers (`pg_cf_form_writes_to_submitter()`); a form that only stores has no recipient to protect. The address is `pg_cf_submitter_address_guess()`, in the order the later code uses: contact e-mail field, account address, the only e-mail field; an office-use-only field a visitor cannot see counts with its default value.
- The address is folded to its inbox (`pg_cf_throttle_mailbox()`): trimmed, lower-cased, `+tag` dropped; at gmail.com/googlemail.com dots dropped and the domain is gmail.com.

## How are they counted?
- `pg_cf_throttle_take()` counts each row with `waf_rate_record()` (one atomic INSERT ... ON DUPLICATE KEY UPDATE) and refuses when the new total is over the limit; rows after the first full one are not counted. No read-then-write, so parallel requests from one address, each with its own session, cannot all pass.
- Runs before the hook code, so a hook that ends the request (`go()`, `exit`) is still counted. A refused submission is counted too, and so is one the hook later refuses.
- Fails open like every `waf_rate` counter (a counter that cannot be written returns 0).
- Refusal: "Too many submissions. Please try again later.", `waf_log_event('rate', 'form-<scope>', ...)` and a `DENY rule=form-<scope>` line in `data/firewall.log`. No offence is registered: the domain counter is shared by innocent visitors.
- Works with the firewall off; sweeps itself (1 in 50). `waf_rate_sweep_queries()` keeps `|cfr` keys 24 h (`waf_rate_long_scopes()`).

## Where are the numbers set?
- `config.form_limit_ip_hour` 10, `form_limit_recipient_day` 3, `form_limit_domain_hour` 0, off (2026.11.0 step 11.0.28); Site Settings, Security, Session & Password card. 0 turns a limit off; clamped 0..10000. Before the step, `waf_setting()` gives the same defaults.

## Why is the domain limit off by default?
- `cfd` is one site-wide counter per domain: anyone can send a few submissions naming gmail.com addresses and refuse every Gmail user on every form for the hour (a denial of service), and a busy site's real traffic can reach it alone. The per-address day limit and the per-visitor hour limit already stop one inbox from being flooded, so the default is 0 (PR #1386). An operator can switch it on in the Security settings; the help text there gives the reason.
- The approved design first said 30 per hour; the owner kept it off for this reason (2026-10-24).

## What are the known weak spots?
- Fixed windows: `cfr` starts at UTC midnight, so "3 per day" allows 3 at 23:59 and 3 more at 00:00.
- `cfi` is skipped for a private or reserved address (`pg_cf_visitor_address_counted()`) and for the Trusted Proxies (`waf_ip_is_infrastructure()`): behind a proxy that is not in Trusted Proxies every visitor would share one bucket. A proxy on a public address that is not listed still does that: set Trusted Proxies.
