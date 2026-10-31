---
name: settings-recategorization-steps
description: Steps R1-R9 of plans/settings-recategorization.md: what each moves, its files and size, the order; claim as [plan:settings-recategorization] R<n>
---

## Which steps are there?
- Sizes: S is under 400 product lines, M is about 1,000. Line numbers are from a29a87c68; re-read them before use.
- Every step edits `includes/settings/registry.php`, so the steps run one at a time, in this order.

- R1 (M) System tools screen. Done in #3795 (`ui/settings-system-tools.md`).

- R1b (S) Done in #3929. R1's rest, after #3141 (it holds the files): a System tools row under the main menu's System heading (`includes/fn/output.php`; `pg_menu_settings_entries()` still lists the tool groups, so drop them from it at the same time) and a help row for `system_tools.php` in `get_help_url.php` (its own topic; the screen passes `'help' => 'site-settings#site-settings-intro'` until then).

- R2 (M) Accounting category, cut in two (size).
  - R2a, done in #3819: a new `accounting` key, `accounting.php` and `accounting.save.php`; the ERP and E-Invoice blocks and their columns leave `commerce.php`/`commerce.save.php` unchanged; anchors `pgset-erp`, `pgset-invoice`, `pgsub-invoice` land on them; return links name `accounting`.
  - R2b, done in #3840 (decided #3828, A; `erp/erp-settings-cards.md`): the `erp_settings.php` cards in the pane, the screen kept for role 3; no redirect while the template editor is only there.
  - Done in #3929, with R1b: the menu's ERP Settings row links `#settings/accounting`; `case 'settings_accounting.php'` in `output_menu()`; a `settings_accounting` help row.

- R3 (M) Renames and the security moves. Done in #3858 (`ui/settings-modal-architecture.md`).

- R4 (M) General › Organization. Done in #3874 (`ui/settings-modal-architecture.md`).

- R5 (S) Visitors and privacy. Done in #3899: a new `privacy` key (`privacy.php`, `privacy.save.php`, `settings_privacy.php`) after SEO; Visitor Tracking and Cookie Consent left SEO, Legal Documents left Ecommerce; anchors `pgset-analytics`, `pgset-cookies`, `pgset-legal` land on it (`ui/settings-modal-architecture.md`).
  - Done in #3929, with R1b: `case 'settings_privacy.php'` in `output_menu()`; a `settings_privacy` help row (the help falls back until then).

- R6 (S) SEO and sharing — done (#3908). The Site icon, Social sharing and Structured data blocks (now drawn in `prep.php`) become cards. Product Feeds moves to Ecommerce. AI Crawlers becomes the one place for AI bots, and the Firewall card shows its state and a link there. If #3557 is open, fix it here: Structured Data and Merchant Center post the same `merchant_country` field.

- R7, cut in four (size).
  - R7a, done in #3968: a `maintenance` category, "Updates and maintenance", last; Update Channel, Cron Jobs, Alerts, Maintenance Mode left General; their anchors land on it (`ui/settings-modal-architecture.md`).
  - R7b (M) Backups joins it (card, save, remote test, `?backup_connect=`), anchor `pgset-backup`.
  - R7c (S) API becomes Integrations, takes Google Reviews from Communication, gets the plan's links.
  - R7d (M) Private label under Appearance, from `private_label.php`.

- R8 (M) Search. A search box in the dialog covers every category's cards and field labels, from the registry plus the drawn labels. Palette keywords: false hits ("logo" on E-Invoice) and misses (favicon, kvkk, workspace, prefetch, encryption). Headings over the list as decided #3879 (Site, Security, Selling, Running the site; `.pg-sm-group`, a `group` key per category).

- R9 (S) Links and notes. Update the text links that name old places ("Site Settings › Contact" in `erp_settings.php`, "Settings › General" in `workspace/ai.php`). Check deep links (#3558). Refresh descriptions, help. Remove `pg_settings_status()` and `pg_settings_facts()`, which have no callers. Rewrite `ui/settings-modal-architecture.md`, write the decisions into the decisions list, and delete this plan.
