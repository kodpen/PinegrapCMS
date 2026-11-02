---
name: bug-hunt-modules-fn-1b
description: Module list of plans/bug-hunt.md, part 1b: includes/fn/ modules link_resolve to pdf_merge, one line each with its state
---

## Which includes/fn/ modules (link_resolve to pdf_merge) are hunted?
- `link_resolve`: done (#4976: names in other letter case were broken links)

- `list_paging`: clean (page window, limit, search terms and Turkish folding, label order, row output checked)

- `list_period`: clean

- `mail`: taken (convert_html_to_text next)

- `mail_parse`: done (#4992: a quoted-printable part ending on a soft break kept its `=`)

- `mail_queue`: done #4654 (rest checked: retry ladder, give-up and notices, property encoding and attachments, job-alive check, claim race, sweeps, List-Unsubscribe headers)

- `maintenance_mode`: clean

- `merchant_center`: clean; lead: a country change keeps the registered data source's feed label, so pushes name another label

- `meta_tags`: done (#4952: a page `og:image` kept the design's `og:image:alt` and size)

- `mfa`: taken by KY — #4590

- `not_found_log`: clean (name, referrer, decide, write and stale restart, cap, sweep, keyset page, where, redirect URL checked); lead: `pg_not_found_log_answers()` shows `binned` for a name a live pattern now answers (patterns are asked only for names nothing else holds)

- `office`: done (#4946: a run property turned off with `w:val="off"` showed its formatting in the Word preview)

- `office_edit`: clean (sheet/docx patching read; `locked` is xsd:boolean, no "off")

- `ops_alerts`: clean; lead: webhook_failing mails raw webhook URLs

- `order_completion`: done (#4884, #4962: DST end dates); line forms, from_payment clean; lead: two payment callbacks at once can both lower `ship_tos.shipping_cost`

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
