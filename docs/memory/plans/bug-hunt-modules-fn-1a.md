---
name: bug-hunt-modules-fn-1a
description: Module list of plans/bug-hunt.md, part 1a: includes/fn/ modules collections to legal_documents, one line each with its state
---

## Which includes/fn/ modules (collections to legal_documents) are hunted?
- `collections`: taken by LQ, #4650

- `comments`: taken by LB — #4599

- `commission_list`: clean

- `config_text`: clean

- `connect`: done #5245 (renewal read another owner's row; rest reviewed: scopes, PKCE, claims, tokens, revoke)

- `consent`: done #4577, #4587

- `contact_list`: clean

- `contacts`: done #4624

- `content`: open, partly hunted (#5343 #5364: product and group address names; #5387: e-mail charset; #5443: image tag rewrite after an image save; #5515: `escape_regex()` and the edit-mode image button; #5864: catalog item address from the page parameter; #5923: reference code title label escape, submitted form page keys; checked: catalog search, address name, transliteration, byte sizes, thumbnails, charset, month names, URL ids, form-data variables, image tags, links disabled in content, layout render, `get_form_info()`, the submitted-form content functions, `get_form_review_info()`, login home redirect, page-type URL, item edit checkboxes; next worker starts at the menu, common region and page option functions above `get_form_info()`)

- `core`: open, partly hunted (#5935: `escape_like()` left the backslash as LIKE's escape for the next character in about seventy searches; checked: redirect path, request lists, CSV guard, `escape_javascript()`, `escape_url()`, `truncate()`, `pluralize()`, `encode_url_path()`; next worker starts at `db_connect()` and the db/schema-cache helpers, then the time functions)

- `cron`: done #5889 (lane pick by due time: a resumable pass starved the hourly search index); read: run stamps, clock and tick budget, due-again, catalogue, AI range refresh, interval label, dispatcher, lanes, claim/release, output handler; done #5930 (`pg_cron_release()` clears only the lock stamp its own claim wrote, no schema)

- `currency_rates`: #5924 (second look: Frankfurter moved to api.frankfurter.dev/v1, BGN dropped; callers, rounding, dates, failure paths checked)

- `custom_form`: done #5926 (custom shipping and billing forms stored option values outside the list); read: field sync and options, ensure/adopt form, settings write, render bindings and URL prefill, rules, rich text ids, controls and reconcile, sample seeding, shipping/billing prepare and submit, mail links, new account texts, fill-time token, form limits

- `dashboard`: open

- `db_maintenance`: open

- `design_templates_custom`: #5976

- `design_themes`: open

- `designer`: open

- `designer_fx`: open

- `designer_theme_vars`: open

- `designer_vocab`: open

- `ecommerce`: open

- `editor`: open

- `email_campaign_schedule`: open

- `email_campaign_send`: done (#5934)

- `errors`: open

- `events`: clean

- `explorer_side`: open

- `files`: done #4613

- `form_subscription`: open

- `forms`: open

- `geo`: done #5975

- `google_analytics`: open

- `google_reviews`: open

- `image`: open

- `image_editor`: open

- `imap_client`: open

- `innodb`: open

- `lang`: open

- `lang_files`: open

- `language_redirect`: open

- `layout_convert`: open

- `layouts`: open

- `legal_documents`: open
