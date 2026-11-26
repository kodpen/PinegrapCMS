---
name: settings-ecommerce-programmes-feeds
description: Ecommerce settings, Affiliate, Referral and Product feeds rows opened (EC5): groups, the ticked group list and its filter, New address in a drawer, the two seller identities
module: commerce_settings_fold
---

## How are the three rows drawn?
- As Gift cards and Rewards (`ui/settings-ecommerce-giftcards-rewards.md`): no popover, no `div.switch`, no `collapse-switcher`; every field under its switch whatever the switch says. Test: `tests/settings_commerce_programmes_test.php`.
- Affiliate programme: Turn on the affiliate programme and Approve affiliates automatically (`pg-switch`), Default commission rate, Affiliate contact group, Affiliate programme e-mail address, Group offer. The old popover `#affiliate_program_row` and the row's icon tile are gone.
- Referral programme: the switch with its one line; group "For the friend" (Offer for the friend, Smallest order that counts); group "For the customer" (Reward, Reward lasts, Rewards a month per customer). The note while Ecommerce is off or the step has not run is an amber help line, not an alert.
- Product feeds: a `pg-switch` a format with its help, its address and its last build line; group "Seller" (Seller name, Seller website, Privacy policy address, Terms of sale address); group "Products" (Brand for products without one, the groups left out); "Save and build now"; Google Merchant Center's part last.

## What replaced the Ctrl multi-select?
- `pg_commerce_settings_group_ticks($name, $legend, $groups, $chosen, $help, $filter_label)`: a `pg-checkbox` a group, each posting its id into `product_feed_excluded_groups[]` as the select did, so `commerce.save.php` and the registry's `lists` entry read it unchanged. None ticked posts nothing, as an empty select did.
- Above eight groups a filter ("Filter groups") stands over the list; `pg_commerce_settings_reveal_script()` hides the ticks whose words miss it with an inline display (a tick's `d-flex` outweighs `d-none`). A hidden tick keeps its state and still posts. The list scrolls past 18rem (`.pg-commerce-ticks`, `backend.src.css`).

## Where did New address's confirm go?
- The browser `confirm()` became a drawer a format (`pg-feed-address-<format>`, `pg_settings_drawer()`): the row's quiet New address opens it; the drawer's danger button "Make a new address" posts `product_feed_new_address=<format>` through `form="pg_settings_modal_form"` (or `pg_settings_page_form` on the page path), as the drawer is drawn beside the pane.

## Which seller identity is read for what?
- General › Organization (`organization_name`, address, city, state, zip, country → `ORGANIZATION_*`): the shop's own name and postal address on mails, receipts, invoices, packing slips and the shipping origin; Merchant Center's default country.
- Product feeds' Seller group (`config.product_feeds`): read only by the feed builder: seller name in the OpenAI rows (needed, `no_seller_name`) and the Google XML channel title; seller website in OpenAI rows and the XML channel link; privacy and terms addresses in OpenAI rows only. The screen prefills Seller name with the organization name while it is empty; the build never falls back to it.
- Not joined (#11938 changed no behaviour). Open: whether the feed's seller fields should read General › Organization is the architect's to decide and has not been asked yet; a step that would join them asks first (`plans/settings-tidy-census.md`).

Related: docs/memory/ui/settings-ecommerce.md, docs/memory/commerce/product-feed-serving.md, docs/memory/commerce/referral-settings-card.md
