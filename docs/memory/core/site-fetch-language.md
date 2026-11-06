---
name: site-fetch-language
description: Site-side fetch()/XMLHttpRequest calls send the page language (pg_lang from <html data-pg-lang>) themselves: which scripts do it, how the server reads it, the scan test
---

## Why does a site script send pg_lang itself?
- `frontend.src.js` adds `pg_lang` to jQuery requests to the software directory (`ajaxPrefilter`). fetch() and a plain XMLHttpRequest pass by it, so on a page drawn in another language (`/en/...`) the answer (refusal, sent-code sentence, the SMS text) came back in the source language.
- The server reads it in `pg_tr_define_from_request()` (`includes/fn/translate.php`): the query string, a JSON body (api.php passes it) or a form field (init.php, through `$_REQUEST`). Only an enabled language other than the source counts.

## Which scripts add it?
- `chat_site.src.js` / hand-patched `chat_site.min.js` (`api()`, JSON body to api.php), `assets/js/pg_passkey.js` (`post()`, FormData to passkey.php), the cash-on-delivery code button (`pg_sms_cod_*` script in `includes/fn/sms_codes.php`, sms_code.php), and both recovery-code send scripts in `includes/fn/passkeys.php`.
- Earlier, on their own: `pg_civ_variants.js`, `pg_wishlist.js` (`content-translation-product-answers.md`), the booking widget (#7169), the express order api.php calls (#7176).
- Left out: `assets/js/product_bundle.js` (panel product screen), beacons that need no answer (page cache, experiments, site search stats, the toolbar state).

## Tests?
- `tests/site_fetch_language_test.php` scans the sources for the page-language lines.
