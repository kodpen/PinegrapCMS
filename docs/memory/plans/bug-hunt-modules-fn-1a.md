---
name: bug-hunt-modules-fn-1a
description: Module list of plans/bug-hunt.md, part 1a: includes/fn/ modules collections to editor, one line each with its state
---

## Which includes/fn/ modules (collections to editor) are hunted?
- `collections`: taken by LQ, #4650
- `comments`: taken by LB — #4599
- `commission_list`: clean
- `config_text`: clean
- `connect`: done #5245 (renewal read another owner's row; rest reviewed: scopes, PKCE, claims, tokens, revoke)
- `consent`: done #4577, #4587
- `contact_list`: clean
- `contacts`: done #4624
- `content`: open, partly hunted (#5343 #5364: product and group address names; #5387: e-mail charset; #5443: image tag rewrite after an image save; #5515: `escape_regex()` and the edit-mode image button; #5864: catalog item address from the page parameter; #5923: reference code title label escape, submitted form page keys; checked: catalog search, address name, transliteration, byte sizes, thumbnails, charset, month names, URL ids, form-data variables, image tags, links disabled in content, layout render, `get_form_info()`, the submitted-form content functions, `get_form_review_info()`, login home redirect, page-type URL, item edit checkboxes; next worker starts at the menu, common region and page option functions above `get_form_info()`)
- `core`: open, partly hunted (#5935: `escape_like()` left the backslash as LIKE's escape for the next character in about seventy searches; checked: redirect path, request lists, CSV guard, `escape_javascript()`, `escape_url()`, `truncate()`, `pluralize()`, `encode_url_path()`; #6018: `get_absolute_time()` wrote the site's clock with a Z suffix and no date in the `datetime` of a `'type' => 'time'` element; clean: db connect/charset/run/bind helpers, the schema cache and `pg_schema_has()`, the time zone helpers, `get_relative_time()`; #6042: `build_url()` dropped the given URL's port; clean: request-secure checks, JSON helpers, `prepare_for_eval()`, address-name helpers; #6089: `get_mime_type()` was case-sensitive (a `JPG` upload went into RSS enclosures as octet-stream), `opendir()` failure in `check_name_availability()`; clean: the rest of the file; hunted to the end)
- `cron`: done #5889 (lane pick by due time: a resumable pass starved the hourly search index); done #5930 (`pg_cron_release()` clears only the lock stamp its own claim wrote, no schema)
- `currency_rates`: #5924 (second look: Frankfurter moved to api.frankfurter.dev/v1, BGN dropped; callers, rounding, dates, failure paths checked)
- `custom_form`: done #5926 (custom shipping and billing forms stored option values outside the list); read: field sync and options, ensure/adopt form, settings write, render bindings and URL prefill, rules, rich text ids, controls and reconcile, sample seeding, shipping/billing prepare and submit, mail links, new account texts, fill-time token, form limits
- `dashboard`: clean (appearance whitelist, auto backdrop map, refresh bounds 10 s–1 day incl. INF strings, role gate; all callers load it via functions.php)
- `db_maintenance`: #5984 (MyISAM/Aria free-space share counted the holes twice)
- `design_templates_custom`: #5976
- `design_themes`: #6021 (a look or palette name closed its stylesheet comment; rest of the module checked clean)
- `designer`: #6034 (svg drawing cleaner let event handlers and unquoted script URLs through)
- `designer_fx`: open
- `designer_theme_vars`: open
- `designer_vocab`: clean
- `ecommerce`: done (#6030)
- `editor`: open, partly hunted (#6032: a visitor image next to `{path}` became protocol-relative; checked: the rich text sanitizer and its URL, style and image helpers, `_pg_rich_text_to_inline()`, the {path} input/output helpers; next worker starts at `get_wysiwyg_editor_code()`, then the `pg_code_editor_*` functions)
