---
name: affiliate-sign-up-widget
description: The Affiliate Sign Up Form widget (classic "affiliate sign up form" page type): handler and page row, controls by name, prefill, terms, what is left out.
module: widgets_affiliate_sign_up
---

## What is it?
- Kind `affiliate_sign_up_form` (`includes/fn/widgets_affiliate_sign_up.php`), step 5e (#2508) of [[system-widget-missing-types]]. It posts the classic names (`first_name`, `last_name`, `address_1`, `address_2`, `city`, `state`, `zip_code`, `country`, `phone_number`, `fax_number`, `email_address`, `affiliate_name`, `affiliate_website`, `affiliate_code`, `terms_and_conditions`, `submit`, hidden `current_page_id`, `next_page_id`) to `affiliate_sign_up_form.php`; its sign-in redirect, checks, contact update, e-mails and redirects are unchanged.

## Where are the settings?
- In the page's `affiliate_sign_up_form_pages` row: the handler asks for the terms box when the row of `current_page_id` names a terms page. A designed page gets a row at render (`INSERT IGNORE`, not in a background render). `terms_page_id`, `submit_button_label`, `next_page_id` are written only when the widget has the key; a page of 0 means "the page's own" (`pg_aff_page_row_changes()`).

## How is the form filled?
- First visit: the contact's business address, company and website (`pg_aff_prefill()`, "http://" without a website), the site's default country. After a refused post: the liveform's values; the Messages node `affiliate_sign_up_form` prints its errors.
- Country and state as the shipping address widget does them (`_pg_sa_state_controls()`, `pg_sa_state_script()`). The terms box is dropped without a terms page, required with one. Flags `has_terms`, `no_terms`; tokens `__submit_button_label`, `__terms_url`.

## What is left out?
- No PHP default tree: only the JS starter. Like the classic page it does not check that the affiliate programme is on.
