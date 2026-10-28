---
name: error-screen-language-and-status
description: The error screen output_error() draws leaves through pg_tr_finalize() like a page, so <html lang> is the site's or the address's language; which "not found" screens answer 404; what is still left
module: core, translate
---

## Which language does the error screen carry?
- `output_error()` (`includes/fn/core.php`) passes the screen `get_error_screen()` drew through `pg_tr_finalize()` (`includes/fn/translate.php`), as `get_page.php` and `prototype.php` do with a page: `<html lang>` becomes the site's language, or the language the address was asked in with the prefix on its links, and the language switchers are drawn. Before (#2045, PR #2621), every error screen and the visitor's 404 pages went out with the design tree's fixed `lang="en"`: `text-transform: uppercase` gave "MÜŞTERI HIZMETLERI" on a Turkish site and screen readers read English.
- `pg_tr_html_lang($content, $language)` sets the attribute: added when missing, replaced when "en" (the design tree's fixed value says nothing about the page), kept when another language was written into a style by hand; only the first `<html>` tag. `pg_tr_finalize()` uses it on a site with no languages set up; with languages, `pg_tr_render_finalize()` sets every page's. Tests: `tests/translate_html_lang_test.php`.

## Which "not found" screens answer 404?
- `erp_reconciliation.php`, `erp_account_prices.php` (account missing), `add_erp_return.php` (invoice missing) and the classic order view `get_view_order_screen_content.php` (order missing) pass 404 to `output_error()`; they answered 200 with a "not found" screen.
- `order_history_view_order.php` answers 404 ("Page cannot be found.") on a site without a page of the `view order` type: `get_view_order_screen()` would fall back to the legacy screen with no page properties, which printed only warnings and a missing template. The account widget links to the script only while the page exists; old e-mails and bookmarks still reach it.

## How does a panel user get back from an error?
- `output_error()` adds "Back to the control panel" (to `welcome.php`) under the message when `pg_error_panel_link($script_name, SOFTWARE_DIRECTORY, USER_ROLE)` returns an address: a user of role 0-2 (role 3 includes site members), a script of the software directory, not `get_page.php`, `router.php` or `index.php` (their errors are the site's own pages). The screen itself stays in the site's design: `get_error_screen()` draws the site's error page. Before (#2045), a panel error such as "Account not found." on `erp_reconciliation.php` gave no way back to the panel.
- A designed error page (error page widget) prints the stored message as text, not this content, so it shows no such link; its `^^__back_url^^` (a same-site referrer) is the way back there. Test: `tests/error_page_test.php`.
