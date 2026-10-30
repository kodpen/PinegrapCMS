---
name: settings-recategorization-steps
description: Steps R1-R9 of plans/settings-recategorization.md: what each moves, its files and size, the order; claim as [plan:settings-recategorization] R<n>
---

## Which steps are there?
- Sizes: S is under 400 product lines, M is about 1,000. Line numbers are from a29a87c68; re-read them before use.
- Every step edits `includes/settings/registry.php`, so the steps run one at a time, in this order.

- R1 (M) System tools screen. Done in #3795 (`ui/settings-system-tools.md`).

- R1b (S) R1's rest, after #3141 (it holds the files): a System tools row under the main menu's System heading (`includes/fn/output.php`; `pg_menu_settings_entries()` still lists the tool groups, so drop them from it at the same time) and a help row for `system_tools.php` in `get_help_url.php` (its own topic; the screen passes `'help' => 'site-settings#site-settings-intro'` until then).

- R2 (M) Accounting category, cut in two (size).
  - R2a: a new `accounting` key, `accounting.php` and `accounting.save.php`; the ERP and E-Invoice blocks and their columns leave `commerce.php`/`commerce.save.php` unchanged; anchors `pgset-erp`, `pgset-invoice`, `pgsub-invoice` land on them; return links name `accounting`.
  - R2b: the `erp_settings.php` cards (rules, late fee, e-Fatura threshold, templates) move in and that screen opens `#settings/accounting`; `system_status.php` 3589 still lands on the threshold card. Ask first (plan, open questions).
  - After #3141 (holds `output.php`, `get_help_url.php`), with R1b: the menu's ERP Settings row links `#settings/accounting`; `case 'settings_accounting.php'` in `output_menu()`; a `settings_accounting` help row.

- R3 (M) Renames and the security moves. Labels become Accounts and sign-in, Firewall and HTTPS; the keys `security` and `firewall` stay. The encryption key block moves from Payment Methods to its own card, and the MFA hint (`security.php` 200) points there. Trusted Proxies moves out from under Enable Firewall. Blocked Email Addresses moves from IP Lists to Spam Protection.

- R4 (M) General › Organization. One card for the organization's name, address, logo, support e-mail and tax number and office; each field keeps its column and save file. The two "Support E-mail Address" fields: decided #3799, Organization takes the site's; the Affiliate Program card's field is relabelled "Affiliate Program E-mail Address" (`tr_add.php`), no column or reader change. ERP Settings' pointer card goes.

- R5 (S) Visitors and privacy. Visitor Tracking and Cookie Consent come from SEO, Legal Documents from Ecommerce.

- R6 (S) SEO and sharing. The Site icon, Social sharing and Structured data blocks (now drawn in `prep.php`) become cards. Product Feeds moves to Ecommerce. AI Crawlers becomes the one place for AI bots, and the Firewall card shows its state and a link there. If #3557 is open, fix it here: Structured Data and Merchant Center post the same `merchant_country` field.

- R7 (S) Updates and maintenance, and Integrations. Update Channel, Cron Jobs, Backups, Alerts and Maintenance Mode leave General. API becomes Integrations, takes Google Reviews from Communication, and gets the links listed in the plan. The panel's look gets one place: Private label comes in under Appearance, from `private_label.php`.

- R8 (M) Search. A search box in the dialog covers every category's cards and field labels, from the registry plus the drawn labels. Palette keywords get fixed: false hits ("logo" on E-Invoice) and misses (favicon, kvkk, workspace, prefetch, encryption). Headings over the list come here if the owner wants them.

- R9 (S) Links and notes. Update the text links that name old places ("Site Settings › Contact" in `erp_settings.php`, "Settings › General" in `workspace/ai.php`). Check every deep link (dashboard tips, #3558). Refresh descriptions and help mapping. Remove `pg_settings_status()` and `pg_settings_facts()`, which have no callers. Rewrite `ui/settings-modal-architecture.md`, write the decisions into the decisions list, and delete this plan.
