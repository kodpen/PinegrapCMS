---
name: decisions-made
description: The decisions list: places reviewed and kept as they are on purpose; how to treat it and who adds to it, then the code and files that look dead or wrong but stay.
---

## What is this list?
- Places that were reviewed and are kept as they are on purpose. Nothing in the code says why, so audits keep reporting them as "dead code", "unused field" or "missing check". Finding one is not a finding: the decision is made (CLAUDE.md fixed rule 6).
- Read it before writing any audit finding and before proposing a cleanup or refactor.
- Parts: this file (code and files); `decisions-made-access.md` (open doors, gates, secrets, TLS); `decisions-made-data.md` (columns, dumps, migrations, servers); `decisions-made-schema.md` (approved steps and columns); `decisions-made-product.md` (checkout totals, translation, designer, legacy layer, ERP limits, erasure); `decisions-made-seo.md` (SEO settings); `decisions-made-customers.md` (consent, İYS, reviews, contact merge); `decisions-made-widgets.md` (system widgets); `decisions-made-workspace.md` (workspace); `decisions-made-messages.md` (message kinds); `decisions-made-files.md` (file access); `decisions-made-panel.md` (breakpoints, menu); `decisions-made-waf.md` (firewall).

## How is it treated?
- Do not change, clean up or "improve" a listed place. If you really believe an entry is wrong, ask before acting; changing it silently breaks this rule.
- A topic file elsewhere in docs/memory that covers a listed place must agree with this list.

## Who adds to it?
- The owner. When an audit raises "is this a decision too?", do not write the finding: open a decision issue (`architect.md`). An accepted decision is written here and in the matching topic file.

## Which code and files look dead or wrong but stay?
- `frontend.*.css`, `pinegrap.*.css`, `livesite.min.css` (root): no `<link>` in the code serves them; pages call them from stored HTML (style code, page content), as the starter dumps' style code links `{path}{software_directory}/livesite.src.css`. Never delete them.
- `myself_upsell.php`: nothing includes it or calls its functions. It stays for now; once a careful review settles it, it may join `clean_up.php`'s removal list (`platform/clean-up-tool-file-list.md`). Do not delete it yourself.
- `software_update.php`, the `VERSION === '2026'` block: never removed. The undotted `2026` is a real stop: a site coming from an old version (e.g. 2025.2) halts there before upgrading, moves `config.php` and the other data paths under `data/`, then the update channel changes. Taken for dead code because no dotted version reaches it.
- `STRUTURED_DATA` (defined in `init.php` from the `config.strutured_data` column): the misspelling is consistent; do not fix it.
- `barcode_decrease_inventory.php`: its "yes" answer is a feature, not a bug. (The decision does not say which answer; today the endpoint answers `status: success` also when a scan asks for more than the stock, which it then sets to 0.)
- `apps.php`, `apps_settings.php` and the `custom_apps` table were removed in 2026.4.4: do not bring them back (`api/removed-rest-api-apps.md`).
- Dashboard widget ids are permanent (dashboards in the field store them): 6, 10, 11, 12, 13, 17, 19, 22, 24 and 26 are retired and never reused; their content lives on in 3, 5, 7, 9 and 21. Id 1 was reused on purpose for the sales map (registry comment in `welcome.php`).
- e-Fatura threshold warning (owner, 2026-10-26, #1773; `erp/erp-efatura-threshold.md`): the thresholds are `config` settings (schema approved: "erp şeması eklemek serbest (gerekliler)", see `decisions-made-data.md`); internet sales leave counter sales out and keep marketplace orders, all sales keep both, ERP invoices without an order are not counted; there is no scheduled job, the sum is cached for a day and read by the System Status tile ("2 ve 3 önerildiği gibi olsun"). Not a missing job or a missing count.
- Common region to component (owner 2026-10-27): opt-in, one source, no inline edit (`designer/common-region-to-shared-component.md`).
