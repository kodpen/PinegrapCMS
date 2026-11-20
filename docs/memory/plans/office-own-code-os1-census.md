---
name: office-own-code-os1-census
description: OS1 census of plan office-own-code: the panel lists that export CSV today (screen, file, request, columns), most used first, those left out, the size
---

## Which panel lists export CSV today? (2026-11-19, most used first)
| # | Screen | File | CSV export | Columns |
|---|---|---|---|---|
| 1 | All Orders, Export > Single file | `view_orders.php` | `submit_data=Export Orders (single file)` | 82 + custom billing and shipping fields |
| 2 | Contacts, Export | `view_contacts.php` | `submit_data=Export Contacts` | 34, 38 with affiliates |
| 3 | Submitted Forms, Export | `view_submitted_forms.php` | `submit_data=Export Forms` | 12 (+ quiz_score) + the form's fields |
| 4 | Visitor Report, Export All to CSV | `view_visitor_report.php` | `export=csv` | by view, 4 to 20 |
| 5 | Gift Cards, Export | `view_gift_cards.php` | `submit_data=Export Gift Cards` | 15 |
| 6 | Audit Trail, Export CSV | `view_audit_log.php` | `export=csv` | 14 |
| 7 | Customers Report, Export top customers | `view_customer_report.php` | `csv=top` | 7 |
| 8 | ERP Aging, Download CSV | `erp_aging.php` | `csv=1` | 3 + age buckets |
| 9 | ERP Cash Flow, Download CSV | `erp_cashflow.php` | `csv=1` | 8 |
| 10 | ERP Audit Trail, CSV | `erp_audit.php` | `format=csv` | 11 |
| 11 | Key Codes, Export | `view_key_codes.php` | `submit_data=Export Key Codes` | 9 |
| 12 | Short Links, Export | `view_short_links.php` | `export=csv` | 3 |
| 13 | Campaign Profiles, Export | `view_email_campaign_profiles.php` | `export=Export` | 24 |
| 14 | Course learners, Download CSV | `edit_course.php` | `view=learners&csv=1` | 7 |
| 15 | Translations, Export CSV and Export pending texts | `translations_action.php` | `action=export` | 5 |

## Which are left out?
- Left out: Products > Export (already offers Excel, its own xlsx writer, `export_products_f.php`), the ERP export profiles (Parasut templates, XL4), the orders "Multiple files" zip and Parasut export, workflow tables (already .xlsx), the content import's redirect report (a file of the import, not a list), the importers' sample files.

## Does it fit one step?
- Size: each list only asks for the Excel form of its own CSV (the helper converts that CSV), a few lines a list, so the whole census fits one PR; no OS1a/OS1b.
