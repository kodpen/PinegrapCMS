---
name: design-templates-standard-pages
description: What every design template carries: account, inbox, staff and blog pages under fixed keys, sample records, the support-center search sections, e-mail pages, settings keys checked against the renderer.
---

## Which pages does every template have?
- The same keys in every template: account (sign-in, registration, password, My Account, profile, email preferences, address book), inbox (`inbox`, `conversation`; the contact form opens a conversation), staff (`staff`, `blog_new`, `staff_entry`, `staff_directory`, shared `staff_bar`) and blog (`blog`, `blog_post`, `home_posts`).
- The staff link is drawn in the login region and on My Account from `staff_page_id` (`__staff_url`, flag `is_staff`), only for a member the staff page's folder lets in.
- A `search` page with the `site_search` widget (kind `search_results`, the Support Center). The product search form in the header stays on the catalog.

## Where do sample records come from?
- Form setting `'sample_records' => $sample_posts`: when Publish creates the form, the template's own records (field name → value) are written (`pg_cf_seed_sample_records()`, at most 24, newest first, dated over the last weeks).
- The other source, `'blog'` (copy the site's sample blog), is not used in templates: a site installed with a template has the starter site's forms removed, so its blog would open empty.

## How are support-center sections marked?
- Content sections are search sections: the `$search_sections` map at the end of a template (page key → `#id` or layer name → id + topic words) and the accordion builders (`<id>-item`) set `searchSection` / `searchKeywords`. A new content page goes into the map. Only a page's own tree counts, not a part inside a shared component or widget.

## How are e-mail pages built?
- E-mail pages are template pages too (a private folder, no sitemap, no search): a form's `notify_page_id` / `confirm_page_id`, a page's `comments` array (`email_page`, `email_subject`, `notify_email`), checkout cfg `order_receipt_email_page_id`; the staff address is `{{site_email}}`.
- A widget on an e-mail page takes its record or order from `pg_sw_render_context()` (there is no URL to read); the Messages node stays empty in an e-mail.

## Which settings keys are right?
- Check every key against the `$cfg[...]` the renderer reads: the cart uses `next_page_id_with_shipping` / `next_page_id_without_shipping`, not `next_page_id`.
- A catalog widget that stays on the page after adding to cart (`add_to_cart_stay_on_page`) still gets `add_to_cart_next_page_id`: it is the notice's "Go to cart" link (`pg_sw_cart_url()`); without it, the cart page the visitor saw last.

Related: docs/memory/designer/design-templates-file-format.md, docs/memory/platform/design-template-install-at-setup.md
