---
name: security-findings-2026-10
description: Open medium/high security findings from the 2026-10-19 audit (session binding, MFA on remember-me, backup extraction, zip bombs, site import, form labels, CVV) with file:line and fixes
---

## Where does this come from?
- Code audit of 2026-10-19 on `development` @ `fd9de204`, read-only, no running instance. The PHP-region eval item is parked in `legacy-page-designer-retirement.md` (owner: no change now). SQL injection: none found across ~8,800 query sites. Done: #541 (notice escaping, `security-helpers-and-secret-storage.md`). Check `pinegrap-verilmis-kararlar` before each item; none below is on it.

## What is open, in order? (one PR each)
1. **Session not bound to its auth token** (`includes/fn/auth.php` `initialize_user()`, ~3340-3423; `includes/authentication.php` ~295): a revoked `auth_tokens` row only ends a session when the browser sends the revoked `software[auth]` cookie; a client without the cookie keeps the session and is minted a new token. `get_file.php` `initialize_user()` (~1338) checks nothing. Fix: `pg_session_sign_in()` stores the selector in `$_SESSION`; `initialize_user()` requires cookie selector == session selector, row present, `user_id == sessionuserid`, else `session_destroy()`; same in `get_file.php`.
2. **Required two-step skips remember-me** (`auth.php` ~3470-3486 calls `pg_session_sign_in()` without `pg_mfa_gate()`; `includes/settings/security.save.php` ~137 revokes nothing when `mfa_required_role` rises). Fix: gate in that branch, or `pg_auth_token_revoke_user()` for the affected roles on save.
3. **Backup extraction writes any name** (`view_folder_and_files_f.php` `explorer_backup_extract` ~8977-9038, no `pg_explorer_unsafe_archive_name()`; `explorer_backup_upload` ~706 allows `.htaccess`). A child `.htaccess` under `data/backups/` can override `deny from all` on Apache. Fix: skip unsafe names (`.php` only under `layouts/`), drop `.htaccess` from the upload allow-list.
4. **Zip bomb** (`explorer_zip_extract` ~9487-9563 and backup extract ~8950: `memory_limit -1` + whole entry via `getFromIndex()`, no size cap; reachable by role 3 with folder edit rights). Fix: `statIndex()` size caps, chunked copy as `pg_restore_archive_copy()`.
5. **Site importer** (`import_design.php` ~422-435, ~537-550 store fetched HTML as `style_code` after `remove_dynamic_code_blocks()`, which strips LiveSite markers only; fetches at ~1413/~1488 follow redirects with no private-IP guard; the `scan` step ~89-119 is a token-less GET). Fix: escape `<?` in imported HTML, fetch through `api_http_request()`, `validate_token_field()` on scan.
6. **Form field labels printed raw** (plain-text field written by role 3 via `add_field.php`/`edit_field.php`): `includes/fn/content.php` 742-921 (8 sites), `get_form_item_view.php` 542-757, `get_custom_form_confirmation_screen_content.php:153`, `edit_submitted_form.php` 420-681, `add_submitted_form.php` 311-515, `view_fields.php:391`; error texts in `includes/fn/custom_form.php` ~2580, `shopping_cart.php` ~609, `express_order.php` ~1349. Fix `h($field['label'])`. `content.php` and `get_form_item_view.php` are legacy renderers: ask the owner first.
7. **CVV stored in clear, card number never cleared** when no gateway is set (`submit_order.php` ~4258-4281, `edit_orders.php` ~517). Legacy manual-processing feature; needs an owner decision (never store the CVV, clear the number once processed).
8. Low items: `security-findings-low-2026-10.md`.

## Question for the owner
- Payment gateway secrets are plain text in `config` and rendered back into `value=` (`includes/settings/commerce.save.php` ~455-481, `commerce.php` 810/886; `prep.php` ~461 calls it deliberate). Is that a recorded decision like the Google client secret?
