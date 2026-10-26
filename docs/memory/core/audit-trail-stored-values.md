---
name: audit-trail-stored-values
description: What audit_log leaves out of before/after, how secrets are masked (by name, inside JSON text), how JSON text and long values are stored, what the audit screen must expect
---

## Which columns are never compared?
- `pg_audit_volatile()`, per table in `pg_audit_tables()` (`skip`, `skip_prefix`): columns ending in `timestamp`, the last editor, `seo_*` of page and products (recalculated after each save), `layout_modified`, `user.token`, `api_apps.last_used_ip`, config job bookkeeping and ciphertext re-encrypted with a new IV on every save (`backup_remote_settings`, `parasut_credentials_enc`), `orders.mailchimp_sync_error`.
- `config.oauth_google_client_secret` / `oauth_google_secret_iv` are compared, so a real change of the Google secret still logs `***` → `***`: the Security save decrypts the stored secret and writes the two columns only when the field differs from it or clears a stored one (PR #1128; before, every save re-encrypted it with a new IV and logged a change).

## How are secrets masked?
- By field name (`pg_audit_secret_key()`), at any depth, as `***`; an empty value stays empty so set and cleared still show. Over-masked by name: `api_apps.require_signature`, `api_secret_prev_expires`, `api_secret_hint`.
- Not secrets though named after passwords, kept in clear (PR #1048): the Security screen's on/off rules `strong_password`, `password_hint`, `forgot_password_link` (before, turning them on or off logged `***` on both sides), `user_password_algo`, `user_password_changed_at`, `password_wrap_cursor`, `password_wrap_done`. `user.user_password_hint` (the hint text) stays masked.
- `config.backup_remote_settings` is `<ciphertext>:<iv>`, not JSON; it is left out rather than masked.

## How is JSON text stored?
- A text value holding a JSON object or list up to 64 KB (`pg_audit_limits()` 'json') is decoded and masked by the same names (`config.product_feeds` keeps feed tokens inside). Up to 2000 bytes of masked JSON it is kept as the object or list; past that, as the masked text compacted (`truncated`, `length`, `sha1`, `start`): `length` and `sha1` are of the masked text, not of the column.
- Longer text is never decoded (#905 review): a page tree (`page.page_tree_json`) can be megabytes; decoding it cost 11-12x its size in memory on a delete, and a 64 KB+ tree pushed the side past its limit so the row kept only field names (the page's name was lost). It is compacted like any long string, with the column's own `sha1`.
- An empty JSON object comes back as `[]`. Before #905 the same column was stored as its original string. The audit screen (plan step 5) shows a string, an object/list and a compacted value.

## What about lines written before #905?
- An SEO settings save stored `config.product_feeds` as its text, whole or cut to its start, with the feed tokens in clear. Step 11.0.38 (PR #1792) masks those lines in place with `pg_audit_remask()`: `pg_audit_redact()`, then `pg_audit_mask_starts()` masks the secret `"name":"value"` pairs of a cut start. Kept, not deleted (owner, 2026-10-26: who changed the settings stays); only changed lines are written.

## Which name is the label?
- `pg_audit_label_of()`: the first non-empty label column of `pg_audit_tables()`, from the row after, or before when there is none (a delete).
- A file or page in the Recycle Bin is parked as `pgbin_<id>_<name>`; the prefix built from the row's own id is taken off, so `recycle` and delete-from-bin lines carry the real name (PR #1058; before, `pgbin_6992_x.txt`, and a search by name missed the delete line). `before_json`/`after_json` keep the parked name.

## Tests?
- `tests/audit_log_test.php`: parked labels, JSON text with secrets, a 100 KB and a 40 KB tree on a page delete (name and title kept, secret not in the opening), `_private`/`_license` names, config ciphertext left out.

Related: docs/memory/core/audit-trail-writers.md, docs/memory/core/consent-log-and-audit-trail.md
