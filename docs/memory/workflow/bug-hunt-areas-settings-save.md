---
name: bug-hunt-areas-settings-save
description: The owner's backend sweep of 2026-11-22: every Site Settings pane saved unchanged on the sandbox; what an unchanged save rewrites and why that is no fault; the one fault it found
---

## How was it run?
- On the sandbox (starter dump, Turkish panel, `admin`): each of the 19 panes of the settings dialog (`.pg-sm-link[data-pane]`) opened on a fresh `view_pages.php#settings/<key>`, and its form posted unchanged as Save posts it (jQuery `serialize()` to the `data-pgurl` address).
- The database was dumped before and after and compared table by table; `data/temp/php_errors.log` and the script errors were read per pane.

## What did it find?
- Every pane answered `success`, with no PHP message and no script error.
- One fault: the first Languages and Translation save on a site that never chose a source language counted as a change of it and dropped every page's kept index answer (`visitors/translation-source-save.md`).

## What does an unchanged save rewrite, and why is it no fault?
- Empty text columns go from NULL to ''.
- `allowed_bots` is written comma-separated instead of one per line; `waf_parse_list()` reads both.
- `remember_me_device_limit` 0 becomes 1, the field's minimum; it counts only while Limit devices per account is on.
- `chat_upload_folder_id` and `product_upload_folder_id` 0 become the top folder, which is what 0 resolves to (`pg_default_upload_folder()`).
- `mailchimp_store_id` empty becomes the host name, the value the Store ID field shows when it is empty.
- Defaults written once: `product_feeds` (the seller name from the organization name), `ecommerce_reference_price_rule` `lowest_30d`, `config_text` rows of other settings' defaults, the `connect_apps` object storage row, a `ws_profiles` row.
- `erp_fx_currencies` keeps the same currencies in the screen's order; `page_cache_generation` and the timestamps move.
- `translation_source_language` empty becomes the panel language, as before the fix: the list shows and posts the resolved language.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/visitors/translation-source-save.md
