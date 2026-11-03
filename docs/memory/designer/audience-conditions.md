---
name: audience-conditions
description: Audience conditions on page elements: the __audience_* tokens, the pg_ret cookie, personal or per country/device copies in the page cache, the editor list
module: page_audience
---

## What can an element be shown by?
- A page condition ([[page-conditions]]) outside any widget, with the tokens of `includes/fn/page_audience.php`: `__audience_returning`, `__audience_country` (two capitals, `pg_geo_country_code()` as the language redirect reads it), `__audience_device` (`phone`, `tablet`, `desktop` from the user agent, `pg_audience_device()`), `__audience_utm_source`, `__audience_utm_campaign` (the request's parameter, else the session's from visitor tracking), `__audience_member` (`USER_MEMBER`), `__audience_group_<id>` (the signed-in contact in `contacts_contact_groups_xref`, any source, so a dynamic group of `core/audiences-rules.md` works as it is), `__audience_experiment_<id>` (the variant key in `pg_exp`).
- Signed in is the existing `__visit_signed_in`; no second name.
- The plan's `{audience:...}` became tokens: bindings take `[a-z0-9_]` only and the page-condition markers, operators and twin evaluator are reused unchanged.
- Values are read only for the tokens the page's spans name (`pg_audience_values_for()`); a page drawn for no visit (e-mail, search index, SEO score) reads '' for all.

## How is a returning visitor told?
- `pg_ret` holds the time the browser was first seen (`pg_audience_returning_touch()`, called by `get_page_content()` on every draw for a visit; a year, HttpOnly, Lax). Returning is '1' when that time is at least 30 minutes old (`PG_AUDIENCE_VISIT_GAP`).
- Functional, listed under necessary in `pg_consent_catalogue()`. `pg_page_cache_set_cookie_names()` leaves it out, so sending it keeps a page cacheable; a cacheable answer drops it (`header_remove('Set-Cookie')`), so a browser served only kept copies gets it on the first page drawn for it.

## How may such a page be kept?
- `pg_audience_cache_rule($kinds)`: `country` and `device` vary (one file-cache copy per value); `returning`, `utm_*`, `experiment` make the answer personal (`pg_page_cache_personal('audience')`); `member` and `group` change nothing: only a signed-in visitor can be one, and no cache serves a signed-in session (as `__visit_signed_in`). The plan said personal for those; dropped because it would cost every signed-out view.
- The kinds come from every span in the drawn page (`pg_audience_html_tokens()`), also those a condition around them cuts, so the copy's key never depends on which spans one visit reached.
- A varying page is stored as a pointer under the address key (empty body, meta `aud` = the kinds) and the page under `pg_audience_cache_key(key, kinds, values)`. `pg_page_cache_lookup()` reads the pointer, computes the visitor's values (`pg_audience_request_values()`, runs in the router: geo from waf.php, the user agent) and reads that copy; `$out['key']` stays the address's, so a miss stores both again.
- No shared cache keeps it: `Cache-Control: private, max-age=0` on the drawn answer and on a file-cache hit; a CDN cannot vary by country.

## What does the editor offer?
- Bind Data's condition rows outside a widget list `SD_AUDIENCE_FACTS` after the visit facts, then one "in contact group" per `formMeta.contactGroups` and one "version shown in experiment" per `formMeta.experiments` (`pg_audience_experiment_options()`: not ended, with variant keys as choices). Each carries `ops` (yes/no, is/is not, text); the operator list shows those plus one written before, and choosing the token moves a misfit operator to its first.
- `_sdAudienceToken()` twins `pg_audience_token_kind()`. `check_bindings.php` needs nothing: the list name holds no TOKEN and audience tokens are no widget palette; `tests/experiments_audience_test.php` compares the list with `pg_audience_tokens()`.

## Tests?
- `tests/experiments_audience_test.php`: each token's value from fixed requests, the cache rule per tree, the copy keys, the stored meta, the cookie exemption, the twin list.
