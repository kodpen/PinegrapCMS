---
name: settings-recategorization-steps
description: Steps R1-R9 of plans/settings-recategorization.md: what each moves, its files and size, the order; claim as [plan:settings-recategorization] R<n>
---

## Which steps are there?
- Sizes: S is under 400 product lines, M is about 1,000. Line numbers are from a29a87c68; re-read them before use.
- Every step edits `includes/settings/registry.php`, so the steps run one at a time, in this order.

- R1 (M) System tools screen. A new root `system_tools.php` draws the five link groups as cards, with each link keeping its rights. The dialog's list (`modal.php`) drops the groups and gets "System tools ›" at its end. The main menu's System heading gets a row (`output.php`). The palette (`includes/panel/search.php`) lists the screen and each tool; it misses today Application Access, Marketplaces, Database Engine, Mail queue, Clean Up, Purge Cache, Updates, Sessions, Audit trail and Referral Sources. Tests: registry and menu tests.

- R2 (M) Accounting category. A new `accounting` key, with `accounting.php` and `accounting.save.php`. The ERP and E-Invoice blocks and their columns move out of `commerce.php` and `commerce.save.php`. The `erp_settings.php` cards move in, and that screen opens `#settings/accounting`. The ERP menu row links there. Legacy anchors are added for `pgset-erp` and `pgset-invoice`.

- R3 (M) Renames and the security moves. The labels become Accounts and sign-in and Firewall and HTTPS; the keys `security` and `firewall` stay, so links keep working (new labels through `tools/tr_add.php`). The encryption key block moves from Payment Methods to its own card, and the MFA hint (`security.php` 200) points there. Trusted Proxies moves out from under Enable Firewall. Blocked Email Addresses moves from IP Lists to Spam Protection.

- R4 (M) General › Organization. One card for the organization's name, address, logo, support e-mail and tax number and office; each field keeps its column, and each save file reads the posts it saved before. Settle the two "Support E-mail Address" fields first (open question). ERP Settings' pointer card goes.

- R5 (S) Visitors and privacy. Visitor Tracking and Cookie Consent come from SEO, Legal Documents from Ecommerce.

- R6 (S) SEO and sharing. The Site icon, Social sharing and Structured data blocks (now drawn in `prep.php`) become cards. Product Feeds moves to Ecommerce. AI Crawlers becomes the one place for AI bots, and the Firewall card shows its state and a link there. If #3557 is still open, fix it here: Structured Data (`prep.php` 889) and Merchant Center (`seo_merchant.php` 53) post the same `merchant_country` field.

- R7 (S) Updates and maintenance, and Integrations. Update Channel, Cron Jobs, Backups, Alerts and Maintenance Mode leave General. API becomes Integrations, takes Google Reviews from Communication, and gets the links listed in the plan. The panel's look gets one place: Private label comes in under Appearance, from `private_label.php`.

- R8 (M) Search. A search box in the dialog covers every category's cards and field labels, from the registry plus the drawn labels. Palette keywords get fixed: the false hits ("logo" lands on E-Invoice and on Uploads & Logs) and the misses (favicon, kvkk, workspace, prefetch, encryption). Headings over the list come here if the owner wants them.

- R9 (S) Links and notes. Update the text links that name old places ("Site Settings › Contact" in `erp_settings.php`, "Settings › General" in `workspace/ai.php`). Check that every deep link (dashboard tips, #3558) still names a card that exists. Refresh the category descriptions and the help mapping. Remove `pg_settings_status()` and `pg_settings_facts()`, which have no callers. Rewrite `ui/settings-modal-architecture.md`, write the decisions into the decisions list, and delete this plan.
