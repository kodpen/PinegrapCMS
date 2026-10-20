---
name: fn-module-map
description: Which includes/fn/ module holds which kind of function (core, auth, forms, ecommerce, seo, content, designer, widgets, output, system_status ...).
---

## Which module holds what? (`includes/fn/`)
- `core`: db*, escape/h/e, JSON, date-time, `lang()`, `truncate`, mime, HTTP responses, `array_key_last` polyfill. LOADED FIRST.
- `auth`: login, password, remember-me/device limit, bridge (`pg_code_version`...), CAPTCHA, login throttling, `validate_*_access`, `initialize_user`, access checks.
- `forms`: `select_*` builders, option lists, form-data preparation, reference codes, front-end screen loaders.
- `ecommerce`: order/cart items, quotes, tax, zones, stock/reservation, gift card, barcode, product copy, order cancellation.
- `seo`: structured data, OG, robots, sitemap, tag cloud, visitor/form view summaries, performance monitor.
- `content`: menu content, submitted-form content, catalog search/URL, image ids (the `$image_ids_in_array` global lives here), `render_layout`.
- `calendar`, `editor` (WYSIWYG, code editor fields, image editor), `mail` (PHPMailer + `email()` + notification mails), `contacts` (`merge_contacts`).
- `designer`: system styles, tree validation/expansion, node/component render, page/style save, CSS output.
- Widgets: `widgets_catalog` (catalog), `widgets_cart` (cart), `widgets_express_order` (`_eo_*` + order view), `widgets` (the others).
- `custom_form` (`pg_cf_*` + form submission), `files` (file name/upload limits), `cron` (scheduled jobs), `tour` (tours; `PG_TOUR_SHELL_KEY` lives here).
- `output`: `output_header/footer/menu/toolbar`, `pg_page_shell`, licence, asset versioning.
- `system_status`: system-status card and checks, cache clearing, file integrity, write permissions, changelog reading, table repair.
- `image` (image processing), `parasut` (Paraşüt), `update` (update channel, cURL TLS, archive extraction).
- Added in 2026.4.8: `errors`, `mail_queue`, `backup`, `consent` (see `docs/memory/core/fn-module-errors-mail-backup-consent.md`).
- Added in 2026.11.0: `privacy` (person token, consent log) and `audit` (`pg_audit()`), see `docs/memory/core/consent-log-and-audit-trail.md`.

Related: docs/memory/core/functions-php-split-rules.md
