---
name: php-regions-eval-and-content
description: get_page_content.php evaluates the assembled page when PHP_REGIONS is on; who may write PHP, where content-level markup is defused with pg_defuse_php_tags(), and the site importer's fetch rules.
---

## Where does page PHP run?
- `get_page_content.php` runs the assembled page through `eval` when `PHP_REGIONS` is on (default) and the page holds a PHP open tag; `dregion` code and `custom_php` nodes have their own evals. Style code, designer regions and those nodes are designer work (role 0-1) and PHP in them is a feature.
- Shared components, system widgets and page text are expanded into the page BEFORE that eval, so markup that a content-level operator writes reaches it. Reordering it, so that only designer code (style code, designer regions) is evaluated, before text and widgets are expanded, is parked (owner 2026-10-19: no change now); the defence below stands on its own.

## What is pg_defuse_php_tags() and where is it called?
- `pg_defuse_php_tags($html)` (`includes/fn/core.php`, beside `h()`) rewrites `<?` to `&lt;?`: the characters stay on screen, the PHP parser sees no open tag, the rest of the markup is untouched. It is for markup printed raw; plain text goes through `h()` as before.
- Called in: `_pg_dm_neutralise_markers()` (every string a content-level save keeps, `includes/designer_access.php`); `pg_sw_escape_token_values()` for every raw key (`pg_sw_raw_token_keys()` plus `$raw_extra`); the hand-built `__item_description` maps in `widgets_cart.php`, `widgets_express_order.php` and the order widget in `widgets.php`; the rich-text branch of `pg_sw_apply_tokens()` and the order-form values in `widgets_catalog.php`; `import_design.php` on fetched HTML before it becomes style code.
- A new widget that prints a rich-text column raw must either build its map through `pg_sw_escape_token_values()` or wrap that value in `pg_defuse_php_tags()`.
- Liveform messages are printed before the eval as well; they go through `pg_defuse_page_message()` (page-messages-defuse.md). Tests: `tests/php_tags_in_content_test.php`.
- Symptom fixed: a manager, a role-3 user with folder edit rights or `manage_ecommerce`, or a `products:write` API key could put `<?php system($_GET['c']); ?>` into page text or a product description and run it on every public view.

## How does the site importer fetch?
- `import_design.php` fetches through `import_design_fetch()`: every address, redirect hops included, is checked with `api_http_check_url($url, true)` (http allowed, loopback/private/reserved refused), the resolved address is pinned with `CURLOPT_RESOLVE`, `CURLOPT_FOLLOWLOCATION` stays off and at most five hops are walked. The stream fallback uses `follow_location => 0` + `ignore_errors` and reads `Location` itself.
- Both the scan and the import step post with a CSRF token (`validate_token_field()` before the URL check); the intro form posts.
- The scan says why an address inside the server's network is refused ("…is inside the network of this server…"); an address that does not resolve, or a redirect leading inside, gets the general "no web page" answer. Before #647 both read as a typing mistake.
- `url_to_absolute.php` resolves relative addresses again: its host class `[a-zA-Z\d-.%]` was an invalid range under PCRE2 (PHP 7.3+) and returned false for every URL, and `mb_strrpos()` was given `'UTF-8'` as the offset.
