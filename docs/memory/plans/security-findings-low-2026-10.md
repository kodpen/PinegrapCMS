---
name: security-findings-low-2026-10
description: Low-severity security items from the 2026-10-19 audit (open redirects, SameSite, strict sessions, user enumeration, mt_rand key codes, LIKE escaping, int casts) with file:line
---

## Where does this come from?
- The low tier of `security-findings-2026-10.md`; same audit, same rules. Each line is a small PR on its own, or several lines in one when they touch the same screen.

## What is open?
- `send_to` into `window.location` on Cancel (`view_fields.php:190`, `view_system_style_source.php:112`, use `pg_safe_back_url()`).
- Session cookie has no SameSite and `express_order.php`/`order_preview.php` re-issue it as None before the token check.
- Form token not renewed at sign-in, `session.use_strict_mode` off.
- Login screens reveal existing usernames.
- Key codes from `mt_rand` (`add_key_code.php:193`, `edit_offer_f.php:1834`).
- Install unlock outside `pg_login_throttle_guard()`.
- `find_and_replace.php` lets a Designer edit `dregion_code`.
- `escape_like()` missing on `LIKE` in `view_orders.php`, `view_contacts.php`, `view_submitted_forms.php` and API search.
- `(int)` casts on session ids in `get_page_content.php:1111`, `submit_order.php` 232/4359, `express_order.php:996`, `billing_information.php:304`.

## Question for the owner
- The sales invoice's `web_address` falls back to `URL_SCHEME . HOSTNAME` (the checkout request's Host header) when `ERP_WEB_ADDRESS` is empty (`includes/erp/order_bridge.php` ~614). Should it be `HOSTNAME_SETTING`, as for mail links (`platform/hostname-in-mail-links.md`)?
