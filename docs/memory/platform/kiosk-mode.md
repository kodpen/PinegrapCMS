---
name: kiosk-mode
description: kiosk.php: what its query string sets in the session, how logout restarts it (token and kiosk mode required, visitor cookies cleared), and why the text parameters must be strings
---

## What does kiosk.php do?
- Without `action` it turns kiosk mode on for the session: `url` (same-origin only, `pg_safe_back_url()`), `inactivity_time`, `dialog_time` (whole positive numbers, else the defaults 30 and 15), `dialog_message`, `continue_button_label`, `logout_button_label` (else the translated defaults), then redirects to `url` or the site root.
- `action=update_activity` marks activity; `action=logout` signs out or clears the session and sends the visitor back to `kiosk.php` with the non-default values in the query string.
- `get_page_content.php` prints the values as JavaScript variables (`software_kiosk_*`) through `escape_javascript()`.

## Why must the text parameters be strings?
- `escape_javascript()` returns a non-scalar unchanged. An array (`?dialog_message[]=x`) was stored in the session and printed "Array" with an "Array to string conversion" warning on every page of the kiosk session. Since #5110 a text parameter is kept only when it is a non-empty string (`tests/kiosk_text_parameters_test.php`).

## Why does kiosk.php check the session token?
- A link planted elsewhere signed anyone out with `kiosk.php?action=logout`, and `kiosk.php?inactivity_time=1` put a signed-in session into kiosk mode whose timer signed it out; `logout.php` sent kiosk sessions on before its own token check (#5773).
- Now `action=logout` restarts only a kiosk session, and a signed-in one only with `token` (the kiosk script sends `software_token`); anything else goes to `logout.php`, which asks first. `logout.php` sends a kiosk session on only after its token check, with the token.
- Turning kiosk mode on from a signed-in session without the token shows a confirm form (GET, the link's settings and the token). A kiosk normally starts signed out, and its logout comes back with a fresh session, so the kiosk loop asks nothing.
- `tests/kiosk_text_parameters_test.php` (source pins).

## Which cookies does the kiosk logout clear?
- The session alone held nothing of the guest wishlist (`pg_wishlist` cookie, a year) or the `software[affiliate_code]` / `software[tracking_code]` cookies: the next customer saw the last one's saved products, a sign-in at the kiosk merged them into that account (`pg_wishlist_merge()`), and the next order was credited to the last visitor's partner.
- Since this fix `action=logout` calls `pg_wishlist_cookie_clear()` and expires both codes before the session ends. The consent answer is kept. `tests/kiosk_text_parameters_test.php` (source pin).
