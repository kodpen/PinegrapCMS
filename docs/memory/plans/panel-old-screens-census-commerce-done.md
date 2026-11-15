---
name: panel-old-screens-census-commerce-done
description: LM1a census: commerce and ERP panel screens already on look A (no step needed) and screen families neither LM1a nor LM1b names
---

## What is already on look A, so no step needs it?
- Lists on the driver with the toolbar and nothing of their own left: `view_products`, `view_product_attributes`, `view_arrival_dates`, `view_containers`, `view_currencies`, `view_gift_cards`, `view_key_codes`, `view_offers`, `view_order_reports`, `view_orders_for_contact`.
- Also: `view_recurring_commission_profiles`, `view_referral_sources`, `view_ship_date_adjustments`, `view_shipping_methods`, `view_tax_zones`, `view_zones`, `view_countries`, `view_states`, `view_contact_groups`, `view_cart_quotes`, `view_returns`, `view_verified_shipping_addresses`, `view_stock_moves`, `erp_accounts`, `erp_invoices`, `erp_waybills`, `erp_cash`, `erp_inbox`.
- Reports: Order Report `view_order_report` and the Shipping Report `view_shipping_report` (`ui/look-report-screens.md`).
- The small add/edit screens on `includes/forms/` (their card titles are `pg-title-section` since #8934); what they keep is group C5.

## What is outside both censuses?
- LM1a's task names none of these, and LM1b's (pages, files, forms, comments, users, visitors, tools, Site Settings) may not: calendars and bookings, ads and ad regions, e-mail and SMS campaigns, menus, regions, styles, themes, short links, Search Console and audits, the WAF and site logs. Checked against `panel-old-screens-census-site.md` once it lands.
