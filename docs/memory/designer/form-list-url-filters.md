---
name: form-list-url-filters
description: form_list_view filters valued from the page address (author, tag archives): reserved names, value rules, who passes the address, canonical/title/noindex, token, flags, feed
---

## What does the URL-parameter dynamic value do?
- Filter `{field, operator, value: '', dynamic_value: 'url parameter', dynamic_value_attribute: 'author'}` compares the field with `?author=…` (lists only, #1272).
- No value in the address: filter left out, all listed. A fixed `value` wins.

## Which names are refused, and why?
- `pg_sw_url_param_name()`: `[a-z][a-z0-9_]{0,31}`, not in `pg_sw_url_param_reserved()`, not starting `utm_` or `pg_`. A refused name gives no value.
- `t`, `a`, `o` write tracking/affiliate/offer codes; `rss`, `pdf`, `email` switch the output; `pg_lang`, `highlight`, `send_to`, `r`, `page_number`, `query`, widget names are read elsewhere. Editor twin `_swUrlParamName()` warns; a test compares them.
- The editor normalises only the edited name; other rows keep theirs and their warning.

## Which values are accepted?
- `pg_sw_url_param_value()`: a string only (`?author[]=x` is none), trimmed, valid UTF-8 without `\p{Cc}\p{Cf}\p{Zl}\p{Zp}`, cut to 100 characters.
- Size operators: numbers unquoted; a non-plain decimal (`1e400`, `.5`) adds `AND (TRUE = FALSE)`, checked after the `T` → space rewrite.

## Who passes the address?
- `pg_sw_form_list_filter_sql($pid, $filters, $request = null)`. Only the list's render and feed pass `pg_sw_filter_request()` (`$_GET`; `array()` in e-mail, under `pg_seo_rendering()` or `UPDATE_SEARCH_INDEX`; the indexer's own `?x=` must not empty lists).
- `null` = no address: skipped, counted in `address`. Sitemap, search index, preview and record views pass none; a record view closes on `address > 0` (404).

## What does the page head do?
- Registry `pg_sw_url_filter_state()` (reset beside `pg_sw_item_missing(false)`): params, `inexact`, `empty`, title, `page_number`.
- Several lists (archive + "most read" box): `empty` only if all found nothing; `inexact`, title only from lists with records; a configured `filter_title` beats a bare value.
- Canonical: path + params sorted, RFC 3986 (`pg_sw_url_filter_query()`) + `page_number` > 1 (as asked, not searching; [[widget-list-paging]]); else bare. `query` dropped; `canonical_url` stays bare.
- Title and og:title: `filter_title` (`{value}` replaced; text without it follows the value; empty = value alone), via `pg_tr_text(…, 'seo')`, only when every value was "is equal to" and a record holds it byte for byte: `=` ignores case/accents under `utf8mb4_unicode_ci`, so list and feed run the `exact` twin (`CAST(… AS BINARY)`); no row = `inexact`. Dates kept stored (`31.12.2024` → `2024-12-31`).
- Robots: `noindex, follow` when nothing matched or inexact (`pg_sw_url_filter_noindex()`); status 200.
- Autodiscovery, feed (`/tag?tag=x&rss=true`) and channel link keep the params; channel title as page title.
- Search-sections JSON-LD title: `pg_search_sections_jsonld_json()` hex-escapes `< > &`.
- robots.txt: the page's noindex switch writes `Disallow: /<page>?` (every archive address).

## Token and flags?
- `^^__filter_value^^` (Bind Data "Value from the page address"): the values joined by ", " under the title rule, escaped once, `^` as `&#94;` (`pg_sw_filter_value_html()`).
- "Show only when": `has_filter_value` (title rule), `no_filter_value` (none applied; on in the installer preview). Lists mark only theirs (+ `has_records`/`no_records`: `form-list-result-count.md`); a pasted `record_found` is drawn. Resolved on surround and template before records go in (>1 MB spans broke PCRE).

## Cautions?
- `submitter` compares the username; prefer `submitter_name` or an author field.
- Check box + "is equal to" matches one exact value.
- A filter on a hidden field is a yes/no oracle for it.

## What is deferred?
- Per-filter tokens; per-value description/JSON-LD/sitemap; sample preview; "current record". Links: [[record-value-page-links]].

Tests: `tests/designer_form_list_url_filter_test.php`.
