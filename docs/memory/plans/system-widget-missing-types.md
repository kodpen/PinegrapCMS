---
name: system-widget-missing-types
description: Plan (2026-10-19, not started): classic page types with no designer system widget (multi-step checkout, order form, folder view, photo gallery, confirmations, affiliate…)
---

## Which classic page types have no widget?
Compared on 2026-10-19: the `page_type` cases of `get_page_content.php` against `SW_TYPES` / `pg_sw_type_labels()`.
| Page type | Classic renderer | Note |
|---|---|---|
| folder view | `get_folder_view_screen_content.php` | page/file list of a folder |
| photo gallery | `get_photo_gallery.php` | album grid of a folder's images |
| e-mail a friend | `get_email_a_friend_screen_content.php` | form that mails the page link |
| form view directory | `get_form_view_directory_screen_content.php` | list of form list views |
| registration confirmation | `get_registration_confirmation_screen_content.php` | after the registration entrance |
| membership confirmation | `get_membership_confirmation_screen_content.php` | after the membership entrance |
| affiliate sign up form / confirmation / welcome | `get_affiliate_*` | affiliate programme pages |
| order form | `get_order_form.php` | a product group with quantity boxes, many products to the cart at once |
| shipping address and arrival, shipping method, billing information, order preview | `get_shipping_address_and_arrival.php`, `get_shipping_method.php`, `get_billing_information.php`, `get_order_preview.php` | classic multi-step checkout, see below |
- Region-level gaps (menu, menu sequence, ad region, tag cloud) are R6 in `legacy-retirement-prerequisites.md`.

## Which are covered?
- Custom form confirmation (a `form_item_view` page set as the form's confirmation page), order receipt (`order_view`, liveform `order_receipt`), login, logout, forgot/change/set password, registration and membership entrance, My Account and its sub-pages, view order, address book, e-mail preferences, catalog and detail, cart, express order, calendar view and event, form list and item view, search results, error.

## Why does the classic multi-step checkout need widgets?
- Owner (2026-10-19): almost every existing site checks out through it, so it cannot be left as a `system` node. "Frozen" in `feature-roadmap-2026-10.md` means its code stays and keeps working, not that it gets no widget.
- Four widgets, one per step, post to the classic handlers (`shipping_address_and_arrival.php`, `shipping_method.php`, `billing_information.php`, `order_preview.php` -> `submit_order.php`) with their field names, as `express_order` does for `express_order.php`. The shopping cart widget already links into the flow (`next_page_id_with_shipping` / `_without_shipping`).
- The order preview's totals are computed as `get_order_preview.php` computes them; that calculation is never changed (`workflow/decisions-made-product.md`), the widget only draws what it returns.

## What does a converted page use until a widget exists?
- A `region` node of type `system` (`<system></system>`) keeps the classic renderer running on the page, so the page works; only its inside is not designable. A custom layout (`layout_type = 'custom'`) keeps drawing through that node too; swapping it for a widget is Y5 of `legacy-layouts-in-designer.md`, opt-in per page. Retirement needs the owner's list of types that must be designable first.

## How is a new type added?
- `SW_TYPES` + `_swTypeDefaults`, `pg_sw_type_labels()`, renderer in `includes/fn/widgets*.php` with `_pg_inject_messages_node()` named after the classic liveform, PHP default tree and JS starter tree in lockstep, tokens and flags passing `tools/check_bindings.php`; compare against the classic page (`designer/system-widget-test-pages.md`).

## Open questions?
- Confirmations: widgets of their own, or a state / next-page setting of the registration and membership widgets?
- Which of the types above must be designable before L5 (retire), and which may stay `system` region nodes?

## Where to start?
- The multi-step checkout first (every existing shop uses it), then the owner's list; one widget per PR.
