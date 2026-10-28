---
name: error-screen-language-and-status
description: The error screen output_error() draws leaves through pg_tr_finalize() like a page, so <html lang> is the site's or the address's language; which "not found" screens answer 404; what is still left
module: core, translate
---

## Which language does the error screen carry?
- `output_error()` (`includes/fn/core.php`) passes the screen `get_error_screen()` drew through `pg_tr_finalize()` (`includes/fn/translate.php`), as `get_page.php` and `prototype.php` do with a page: `<html lang>` becomes the site's language, or the language the address was asked in with the prefix on its links, and the language switchers are drawn. Before (#2045, PR #0), every error screen and the visitor's 404 pages went out with the design tree's fixed `lang="en"`: `text-transform: uppercase` gave "MÜŞTERI HIZMETLERI" on a Turkish site and screen readers read English.
- `pg_tr_html_lang($content, $language)` sets the attribute: added when missing, replaced when "en" (the design tree's fixed value says nothing about the page), kept when another language was written into a style by hand; only the first `<html>` tag. `pg_tr_finalize()` uses it on a site with no languages set up; with languages, `pg_tr_render_finalize()` sets every page's. Tests: `tests/translate_html_lang_test.php`.

## Which "not found" screens answer 404?
- `erp_reconciliation.php`, `erp_account_prices.php` (account missing), `add_erp_return.php` (invoice missing) and the classic order view `get_view_order_screen_content.php` (order missing) pass 404 to `output_error()`; they answered 200 with a "not found" screen.
- `order_history_view_order.php` answers 404 ("Page cannot be found.") on a site without a page of the `view order` type: `get_view_order_screen()` would fall back to the legacy screen with no page properties, which printed only warnings and a missing template. The account widget links to the script only while the page exists; old e-mails and bookmarks still reach it.

## What is left?
- A panel script's error screen is still drawn in the site's design with no link back to the panel: `output_error()` cannot tell a panel request from a site request (no marker constant; `USER_LOGGED_IN` and the software directory cover both), and the shell would come from `get_error_screen()` (forms.php).
