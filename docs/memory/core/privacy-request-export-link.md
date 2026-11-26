---
name: privacy-request-export-link
description: The signed download link of an information answer (KV4c, #10944): the file in data/privacy_exports, HMAC, 7 days, privacy_download.php 410, Send a new link, Withdraw the link
module: privacy_request_link
---

## What does the answer e-mail carry?
- Owner (2026-11-21, `workflow/decisions-made-product-95.md`): the exported data goes by a signed link valid 7 days, never as an attachment.
- `pg_privacy_request_send()` for kind `information`: `pg_privacy_link_prepare()` builds the export (`pg_privacy_export_build()`, the operator's export rule; refused stops the send) and writes it as JSON before the mail; the link goes under the staff's text (`pg_privacy_link_mail_lines()`), the mail is `secret` (kept out of the failure log). Nothing found: no file, no link.
- The `answer` column keeps the staff's text without the link. Mail failed: the file is deleted.
- After the send: audit `link` (after: `expires_at` only) and the export's own `export` / `person` line.

## Where is the file and what is the link?
- `data/privacy_exports/<request>-<end>-<32 hex>.json` (`pg_privacy_link_dir()`, mode 0600); `data/` is closed to the web. Nothing of the person in the name.
- Link: `privacy_download.php?r=<request>&e=<end>&n=<file part>&s=<sig>`, `s` = HMAC-SHA256 of `privacy-request-export:<id>:<n>:<e>` under `ENCRYPTION_KEY` (`pg_fs_key()`), compared with `hash_equals`.
- No column: the file is the link's state. A new link deletes the old file once its e-mail has gone (a failed send deletes only the new file, `pg_privacy_link_discard()`, so the old link keeps working; #11779), "Withdraw the link" deletes it, `job.php` runs `pg_privacy_link_sweep()` beside the request sweep and deletes files past their end.
- An erasure of the person deletes the files of their requests (`_pg_privacy_erase_apply_privacy_requests()`, before the rows lose the address), and a request whose address became a person token is never served (#11779).

## What does privacy_download.php do?
- `pg_privacy_link_valid()`: signature, end in the future and at most 7 days ahead, the row kind `information` and status `answered`, its address not a person token (erased), the file there. Then audit `download` as a site visitor (`pg_audit_as_site()`; the IP as every audit line keeps it), `Content-Disposition: attachment` `personal-data-<day>.json`.
- Anything else: status 410, one sentence "This link has ended. Ask the site for a new one.", on the secure card; never says whether a request exists.
- Headers: `no-store, private`, `X-Robots-Tag: noindex, nofollow`, `no-referrer`, `DENY`. In `waf_sensitive_scripts()` (GETs counted like `erp_invoice.php`).
- A mail scanner opening the link counts as a download.

## What does the drawer show?
- A closed information request answered: under "The answer sent", "Download link" (`pg_privacy_link_card()`): Link "Works until …", "Ended", "Withdrawn" or "No link was sent"; Downloaded with the time or "Not downloaded yet" (`pg_privacy_link_state()` from the audit lines since the last `link`).
- "Send a new link" (`action=link_new`, `pg_privacy_link_resend()`: fresh export, new 7 days, a short e-mail) and, while it works, "Withdraw the link" (`link_withdraw`, audit `revoke`). Both confirm first.
- History labels: `link` "Download link sent", `download` "Downloaded".

## What never goes out?
- The API's steps keep action, time and user only, so a `link` line's end never leaves; no route returns the link, its signature or the file (`api/api-privacy-routes.md`).

## Tests?
- `tests/privacy_request_link_test.php`: file name, signature parts and prefix, past 7 days / changed signature / withdrawn all invalid, the drawer state, the page's sentence and headers, no attachment, the API steps.
- Not seen with a mail server or a browser when written.

Related: [[privacy-request-answer]], [[privacy-requests]], [[personal-data-export]]
