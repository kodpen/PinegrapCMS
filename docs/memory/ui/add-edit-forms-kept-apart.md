---
name: add-edit-forms-kept-apart
description: Create and edit screen pairs left without a shared includes/forms/ file on purpose (offer, product, system style, ERP documents, low-overlap pairs) and why each was left
---

## Which create/edit pairs share no form file on purpose?
- The 2026-10-08 code audit's shared-forms item moved most pairs to `includes/forms/` (each in its `*-shared-form.md`). These were weighed and left as they are:
  - offer: `add_offer.php` only redirects; one screen draws the form.
  - product: one screen already, `product_builder.php`.
  - system style: `includes/designer_screen.php` draws both.
  - erp_till, erp_quote, erp_purchase_order, erp_waybill: they already share `includes/erp/till_form.php`, `invoice_form.php` and `waybill_form.php`.
  - erp_expense, erp_invoice, comment, theme_file: the create and edit screens overlap by 8-12 % of their lines, too little to share.
- Not an owner decision: a new reason (a bug fixed twice, a field added to both) may reopen one; check `workflow/decisions-made.md` first.
