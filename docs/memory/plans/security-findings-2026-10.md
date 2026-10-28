---
name: security-findings-2026-10
description: 2026-10-19 security audit: fixed by that day's PRs and #713; items 2-8 decided by the owner 2026-10-28 (labels, CVV, escape_like, sign-in message, gateway secrets, ERP host, rich text)
---

## Where does this come from?
- Read-only code audit of 2026-10-19 (`development` @ `fd9de204`). SQL injection: none in ~8,800 query sites. Check `workflow/decisions-made.md` before each item; none below is on it.

## What was fixed?
- #541 notice escaping; #550 content-level `<?` defused, importer SSRF/CSRF, `url_to_absolute()`; #551 session bound to its token, remember-me MFA gate; #552 backup extract/upload names and sizes, zip bomb caps; #554 form labels and file names escaped, Cancel targets, download names, `random_int()`, `dregion` hidden from designers; #556 SameSite/strict mode, token renewed at sign-in, mail links from `HOSTNAME_SETTING`, install unlock lockout, int casts, `sql_device_type()`, API `escape_like()`; #713 the installer loads `waf.php`, so that lockout counts. Memory: `core/php-regions-eval-and-content.md`, `core/session-token-binding.md`, `core/security-helpers-and-secret-storage.md`, `platform/backup-upload-extraction.md`.

## What is still open? (one PR each)
1. **PHP-region eval re-ordering** (owner 2026-10-19: no change now; `legacy-page-designer-retirement.md`): evaluate designer code only (style code, designer regions) before text and widgets are expanded, so the eval never sees expanded content. #550 is the defence layer, not the architecture.
2. **Classic front-end form renderers print the label raw**: `includes/fn/content.php` (8 sites), `get_form_item_view.php` (11), `get_custom_form_confirmation_screen_content.php:153`. `h()` there changes the output of a site that typed markup into a label. Decided (owner, 2026-10-28): escape the label everywhere; typed markup becomes text.
3. **CVV stored in clear, card number never cleared** when no gateway is set (`submit_order.php` ~4258-4281, `edit_orders.php` ~517; `card_number` AES-CBC, plain without openssl). Legacy manual processing. Decided (owner, 2026-10-28): never store the CVV, clear the number once processed, refuse the save without openssl.
4. **`escape_like()` in the panel lists** (`view_orders.php` 44, `view_contacts.php` 33, `view_submitted_forms.php` 11): Decided (owner, 2026-10-28): escape them; `%` and `_` are plain characters in these searches.
5. **Login screens name an existing account** (`index.php`, `registration_entrance.php`, `membership_entrance.php`, `change_password.php`; `auth.php` skips the hash on an unknown name): Decided (owner, 2026-10-28): one message plus a dummy `password_verify()`.
6. **Gateway secrets rendered back** (`includes/settings/commerce.save.php` ~455-481, `commerce.php` 810/886; `prep.php` ~461 calls it deliberate): iyzipay secret, Authorize.net key, PayPal API passwords are plain in `config` and printed into `value=` for every manager. Decided (owner, 2026-10-28): the encrypted-blob pattern of `security-helpers-and-secret-storage.md`, printed masked.
7. **ERP sales invoice `web_address`** falls back to `URL_SCHEME . HOSTNAME` (the checkout request's Host header) when `ERP_WEB_ADDRESS` is empty (`includes/erp/order_bridge.php` ~614) and is stored on the invoice. Decided (owner, 2026-10-28): `HOSTNAME_SETTING` (`platform/hostname-in-mail-links.md`).
8. **Done (#2732)** — **Designed-form rich text** (`designer/rich-text-profiles.md`): Decided (owner, 2026-10-28): two profiles (visitor on the front end, staff in `edit_submitted_form.php`), and `pg_sanitize_rich_text()` over unfiltered `standard` rows (pre-switch answers, API writes) before the staff editor.

## What was never verified on a running site?
- #552 on IIS/nginx, #556's gateway returns with the re-ordered cookie re-issue. A sandbox pass (`tools/setup_sandbox.sh`) before the release closes these.
- Sandbox-checked: #551 (`core/session-token-binding.md`); #550's designer text (a manager's hand-made `<?php` is stored as `&lt;?php`, not run); #556's cookie flags, strict mode, sign-in token renewal.
