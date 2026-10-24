---
name: fn-module-map
description: Which includes/fn/ module holds which kind of function (core, auth, forms, ecommerce, seo, content, designer, widgets, output, system_status ...).
---

## Which module in `includes/fn/` holds what?
- `core`: db*, escape/h/e, JSON, date-time, `lang()`, `truncate`, mime, HTTP responses, `array_key_last` polyfill. LOADED FIRST.
- `auth`: login, password, remember-me/device limit, bridge (`pg_code_version`...), CAPTCHA, login throttling, `validate_*_access`, `initialize_user`, access checks.
- `forms`: `select_*` builders, option lists, form-data preparation, reference codes, front-end screen loaders.
- `ecommerce`: order/cart items, quotes, tax, zones, stock/reservation, gift card, barcode, product copy, order cancellation, carrier recognition. `shipping` (2026.11.0): the only writer of shipment rows (`docs/memory/commerce/shipment-write-path.md`).
- `seo`: structured data, OG, robots, sitemap, tag cloud, visitor/form view summaries, performance monitor.
- `content`: menu content, submitted-form content, catalog search/URL, image ids (the `$image_ids_in_array` global lives here), `render_layout`.
- `calendar`, `editor` (WYSIWYG, code editor fields, image editor), `mail` (PHPMailer + `email()` + notification mails), `contacts` (`merge_contacts`, `pg_contact_display_name()`).
- `designer`: system styles, tree validation/expansion, node/component render, page/style save, CSS output.
- Widgets: `widgets_catalog` (catalog), `widgets_cart` (cart), `widgets_express_order` (`_eo_*` + order view), `widgets` (the others).
- `custom_form` (`pg_cf_*` + form submission), `files` (file name/upload limits), `cron` (scheduled jobs), `tour` (tours; `PG_TOUR_SHELL_KEY` lives here).
- `output`: `output_header/footer/menu/toolbar`, `pg_page_shell`, licence, asset versioning. `dashboard`: card styles, backdrop, refresh time, their gate.
- Sign-in and identity: `mfa` (two-step sign-in), `signin` (social sign-in), `connect` (connected accounts, the site as an OAuth client), `geo` (IP to country), `qr` (QR codes as inline SVG).
- Store: `payments` (payment drivers), `carrier_api` (carriers' REST APIs), `returns` (customer returns), `order_status` (status moves), `price_history`, `product_feeds`, `currency_rates`, `legal_documents` (checkout legal documents).
- Pages and designer: `meta_tags`, `design_templates_custom`, `design_themes`, `designer_theme_vars` (PHP twin of the Themes panel), `widgets_account`, `widgets_error`, `widgets_language`, `signature`, `page_revisions`, `page_schedule`, `maintenance_mode`, `language_redirect`, `translate` (front-end translation), `ai_crawlers` (robots.txt AI section), `ai_write`, `short_link_redirects`, `not_found_log` (the 404 log, `platform/not-found-log.md`), `office` / `office_edit` (Word/Excel preview and cell edits), `page_cache` (anonymous page cache gate and file cache, visitor beacon, `pg_header_callback_add()`: the one way to register a header callback).
- System and visitors: `events` (`pg_announce()`), `backup_cloud`, `backup_restore`, `innodb`, `db_maintenance`, `ops_alerts`, `sms`, `captcha` (Turnstile, hCaptcha: `waf/form-captcha-providers.md`), `visitor_retention`, `visitor_texts`, `visitor_partitions`, `visitor_ip`. Loaded only where needed: `search_console` (`seo_search_job.php`).
- `system_status`: the status card and checks, cache clearing, file integrity, write permissions, changelog, table repair.
- `image` (image processing), `parasut` (Paraşüt), `update` (update channel, cURL TLS, archive extraction).
- Added in 2026.4.8 and 2026.11.0 (`errors`, `mail_queue`, `backup`, `consent`, `privacy`, `audit`, `privacy_export`, `reviews`, `comments`): [[fn-module-map-additions]].

Related: docs/memory/core/functions-php-split-rules.md
