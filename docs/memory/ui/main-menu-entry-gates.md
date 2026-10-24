---
name: main-menu-entry-gates
description: Main menu entries follow the gate of the screen they open: the screens that had no menu path for some of the people they admit, the entries that led to a refusal, and how both were checked
---

## What is the rule for a menu entry?
- An entry is drawn for exactly the people its screen admits: no entry that leads to "Access denied", and no screen that admits someone without a way to it from the menu (Ctrl+K and links inside other screens do not count). A new screen gets its entry under its own gate (`main-menu-panels.md`). Set up so in #1516.

## Which screens got a menu path?
- `view_system_styles.php` (Visual Page Editor home): it admits managers and role 3 users at content level (`pg_designer_access()`), but row 21 is for role < 2. The Pages panel has it for role >= 2; the active row is 2 for them, 21 below. The one exception to the rule: a role 3 user without a folder edit right gets no Pages row (`no_acl_check()`), so no entry, though the home lets them in; every page is locked for them there, so there is nothing to reach.
- `translations.php` (manager) and `private_label.php` (administrator): under Site Settings › Languages and Translation and › Appearance (`pg_settings_category_screens()`, `main-menu-settings-row.md`).
- `view_referral_sources.php`, `view_currencies.php`: also in the shipping row (15) under `USER_MANAGE_ECOMMERCE`, because Site Settings › Store Lists is drawn for role <= 2 only. Active row 20 for role <= 2, 15 for role 3.
- `view_ship_date_adjustments.php`: it needs `validate_ecommerce_access()` only, so its entries moved from under the reports right to the shipping block of row 15.
- `view_order_reports.php` for a role 3 user with the reports right only: row 15 becomes their "Order Reports" row (its own address, the orders icon, All Order Reports and Create Order Report, then Shipping Report). Its address was `view_shipping_methods.php`, which refuses them.

## Which entries were taken away from people the screen refuses?
- Calendars row: `validate_calendars_access($user, true)`; Contacts row: `validate_contacts_access($user, true)`. A role 3 user with the right but no calendar or contact group assigned is refused by every screen of the row.
- Create Contact Group: role < 3 (`add_contact_group.php` refuses role 3, decisions-made-access.md). Create Page: role < 3 or `create_pages`.
- ERP Settings: not for the read-only accountant (`USER_ERP_READONLY`), as `erp_accountant.php` computes `$can_settings`.
- Import My Site, Check for Updates, Repair Software: not on a hosted site (`pg_hosted()`); the screens refuse there.
- The Ship Date Adjustments button of `view_shipping_report.php`: `USER_MANAGE_ECOMMERCE` only.

## How was it checked?
- Ten sandbox users (roles 0-3, mixed rights, a calendar and a contact group assigned to one, read-only accountant with the settings right) on the old and new menu: every row equal except these changes, then every link of the menu opened as its user. Before: 10 refused links; after: none.

## What was left for the owner?
- `duplicate_products.php` (site-specific option ids, untranslated labels), `view_system_style_source.php` (needs a style id), the menu item and ad region screens managers reach only from the site (their lists are designer-gated), and Ctrl+K chips that offer screens the person cannot open (`includes/panel/search.php`).

Related: docs/memory/ui/main-menu-panels.md, docs/memory/ui/main-menu-settings-row.md
