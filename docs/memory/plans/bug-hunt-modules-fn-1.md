---
name: bug-hunt-modules-fn-1
description: Module list of plans/bug-hunt.md, part 1: includes/fn/ modules a11y to pdf_merge, one line each with its state
---

## Which includes/fn/ modules (a11y to pdf_merge) are hunted?
- `a11y`: open

- `ai_crawlers`: open

- `ai_prompts`: open

- `ai_write`: open

- `audit`: open

- `audit_list`: open

- `audit_view`: open

- `auth`: done #4523

- `backup`: done #4563

- `backup_cloud`: open

- `backup_restore`: open

- `bundles`: open

- `calendar`: done #4632 #4646 (recurring overnight/multi-day events and room availability, unpadded view date; rest reviewed: escaping, access, recurrence expansion)

- `captcha`: clean (siteverify outcomes, token, widget escaping, settings card and save order checked)

- `carrier_api`: open

- `carrier_labels`: open

- `carrier_webhook`: open

- `cart_quotes`: clean (line cleaning, plan against stock/minimum/maximum/bin, create and open checks, apply and its repeat guard, completion, link page escaping checked)

- `classic_style_design`: open

- `cloud_file_picker`: open

- `cloudflare_tools`: open

- `collections`: taken by LQ, #4650

- `comments`: taken by LB — #4599

- `commission_list`: open

- `config_text`: open

- `connect`: open

- `consent`: done #4577, #4587

- `contact_list`: open

- `contacts`: done #4624

- `content`: open

- `core`: open

- `cron`: open

- `currency_rates`: open

- `custom_form`: open

- `dashboard`: open

- `db_maintenance`: open

- `design_templates_custom`: open

- `design_themes`: open

- `designer`: open

- `designer_fx`: open

- `designer_theme_vars`: open

- `designer_vocab`: open

- `ecommerce`: open

- `editor`: open

- `email_campaign_schedule`: open

- `email_campaign_send`: open

- `errors`: open

- `events`: open

- `explorer_side`: open

- `files`: done #4613

- `form_subscription`: open

- `forms`: open

- `geo`: open

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

- `link_resolve`: open

- `list_paging`: open

- `list_period`: open

- `mail`: open

- `mail_parse`: open

- `mail_queue`: done #4654 (rest checked: retry ladder, give-up and notices, property encoding and attachments, job-alive check, claim race, sweeps, List-Unsubscribe headers)

- `maintenance_mode`: open

- `merchant_center`: open

- `meta_tags`: open

- `mfa`: taken by KY — #4590

- `not_found_log`: open

- `office`: open

- `office_edit`: clean for addresses, value parsing, save lock, file replace, save package; sheet/docx patching not read

- `ops_alerts`: clean; lead: webhook_failing mails raw webhook URLs

- `order_completion`: done (#4884); receipt, ship dates, line forms, rewards, from_payment not read

- `order_list`: clean

- `order_report`: clean

- `order_status`: clean

- `order_view`: clean (callers escape)

- `output`: clean for list sort, quick edit, back URL, filter, period nav; header, menu, license, page panel open

- `page_bulk`: clean

- `page_cache`: clean (gate, serve and store checks, key, pack and write, stale lock, beacon, dirty rules, sweep checked)

- `page_facts`: clean (empty cart '' is the owner's rule)

- `page_revisions`: done (panel `revisions_list` reads 51 rows and sends 50 with `more`, #4842)

- `page_schedule`: clean (time parsing and range, save plan, job pass and its overlap guard, API time checks, list chip escaping checked)

- `page_type_pane`: clean (type rights, layout type rule, offer rights, contact group, private folder and custom form guards, form name check, pane CSRF, folder access and collaboration lock checked)

- `parasut`: clean (request and 429 retry, credentials and token cache, connect and settings card escaping, inbox totals and list filters, VKN lookup, Create Invoice and E-Shipment flows, kept-id SQL, order screen notices checked)

- `passkeys`: clean

- `payments`: done (#4629)

- `pdf_merge`: done (#4610)
