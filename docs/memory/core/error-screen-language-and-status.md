---
name: error-screen-language-and-status
description: The error screen output_error() draws: a site error in the site's language (pg_tr_finalize()), a panel screen's error in the panel's own frame (#9024); which "not found" screens answer 404
module: core, translate
---

## Which language does the error screen carry?
- `output_error()` (`includes/fn/core.php`) passes the screen `get_error_screen()` drew through `pg_tr_finalize()` (`includes/fn/translate.php`), as `get_page.php` and `prototype.php` do with a page: `<html lang>` becomes the site's language, or the language the address was asked in with the prefix on its links, and the language switchers are drawn. Before (#2045, PR #2621), every error screen and the visitor's 404 pages went out with the design tree's fixed `lang="en"`: `text-transform: uppercase` gave "MÜŞTERI HIZMETLERI" on a Turkish site and screen readers read English.
- `pg_tr_html_lang($content, $language)` sets the attribute: added when missing, replaced when "en" (the design tree's fixed value says nothing about the page), kept when another language was written into a style by hand; only the first `<html>` tag. `pg_tr_finalize()` uses it on a site with no languages set up; with languages, `pg_tr_render_finalize()` sets every page's. Tests: `tests/translate_html_lang_test.php`.

## Which "not found" screens answer 404?
- `erp_reconciliation.php`, `erp_account_prices.php` (account missing), `add_erp_return.php` (invoice missing) and the classic order view `get_view_order_screen_content.php` (order missing) pass 404 to `output_error()`; they answered 200 with a "not found" screen.
- `order_history_view_order.php` answers 404 ("Page cannot be found.") on a site without a page of the `view order` type: `get_view_order_screen()` would fall back to the legacy screen with no page properties, which printed only warnings and a missing template. The account widget links to the script only while the page exists; old e-mails and bookmarks still reach it.

## How does a panel user get back from an error?
- `output_error()` asks `pg_error_panel_link($script_name, SOFTWARE_DIRECTORY, USER_ROLE)`: an address (`welcome.php`) for a user of role 0-2 (role 3 includes site members) on a script of the software directory other than `get_page.php`, `router.php` and `index.php` (their errors are the site's own pages); '' otherwise. Before (#2045), a panel error such as "Account not found." on `erp_reconciliation.php` gave no way back to the panel.
- With an address, the error is drawn in the panel's own frame, never in the site's design: `pg_error_panel_screen()` = `output_header_secure()` (Bootstrap 6, the panel's stylesheet and language; title "Error", favicon `welcome`) around `pg_error_panel_card()`, the sign-in screens' card (`pg_secure_card()`) with the screen's message as written (its own "Go back" link kept) and a "Back to the control panel" button. It skips `pg_sw_error_context()`, `get_error_screen()` and `pg_tr_finalize()`.
- Why: `get_error_screen()` draws a designed error page first, and that page prints only its own blocks: the panel's "Record not found." on Edit Calendar Event, Edit Short Link, Edit Auto Dialog, Edit Page and Edit Calendar came out as the shop's "404 Page Not Found" with its header and footer, no message and no way back (#9024).
- A database failure still prints the bare red message (no frame, to avoid a loop), with the "Back to the control panel" link under it.
- `output_control_panel_header_includes()` reads `custom_css` once a request, so a screen that built `output_header()` and then stopped with `output_error()` does not define `CUSTOM_CSS` twice.
- Tests: `tests/error_page_test.php` (the card, and a source pin of the order in `output_error()`).
