---
name: order-completion-line-comments
description: Order completion stage functions for a line's comments, its e-mailed page and the product_ordered and order_completed campaigns (A2c4): inputs, unit rules, what changed
module: order_completion
---

## Which functions? (A2c4)
- `pg_order_complete_campaigns($contact_id)`: the `order_completed` campaigns, after the ship dates, before the product loop.
- Per line, after the submitted forms (`commerce/order-completion-lines.md`): `pg_order_complete_line_comments($line, $variable_fields, $product_forms, $forms)`, `pg_order_complete_line_email_page($line, $billing_email_address, $variable_fields, $product_forms, $forms)`, `pg_order_complete_line_campaigns($line, $contact_id)` (`product_ordered`).
- `$forms` is what `pg_order_complete_lines()` collects per unit while it updates or creates forms: quantity number => `id`, `type` (`created`/`updated`). `$variable_fields` and `$product_forms` come from `pg_order_line_variable_fields()` / `pg_order_line_product_form_fields()`, so the order row, never the request.
- The contact and billing address come from the completion's context (`submit_order()` writes `contact_id` into `orders` first); a webhook completion passes the same columns.

## How are comments added?
- One per unit when `submit_form_quantity_type` is "One Form per Quantity", else one (`pg_order_line_unit_count()`, pure); the same rule as the submitted forms.
- `pg_order_line_comment_target()` (pure): on a form item view the unit's own form (`item_type` `submitted_form`), else no item; null (no comment) when the product comments only on an updated form and the unit's was not updated.
- Per comment, as before: an updated form's `allow_new_comments_for_items` rows deleted, the comment inserted, scheduled comments with `publish_cancel` cancelled, `submitted_form_info` rewritten (views kept), the administrator, submitter (form item view with a submitter page) and watcher (watcher page) e-mails.
- Before, a unit with no form read an unset `$forms[n]` (a notice); now it is no form.

## How is the page e-mailed?
- Subject: the page title with the order's and the first unit's variables, else "Product Information". Body: `get_page_content()` preview for e-mail; a `<!-- Start Quantity Row -->…<!-- End Quantity Row -->` block repeats per unit with that unit's product form (`pg_order_product_form_number()`) and submitted form (`pg_order_line_unit_form()`, pure: own, else the first unit's).
- To the billing address, BCC the product's `email_bcc`, else `ECOMMERCE_EMAIL_ADDRESS` (`pg_order_email_page_bcc()`, pure); queued.

## What still reads the request?
- The functions read no session or form (`tests/order_completion_test.php`), but `get_page_content()` and the comment mailers they call are shared renderers not checked for the session; A3 checks them before a webhook calls them.

Related: commerce/order-completion.md, commerce/order-completion-lines.md, plans/order-completion-function.md
