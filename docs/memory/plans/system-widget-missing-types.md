---
name: system-widget-missing-types
description: Plan (2026-10-19, not started): classic page types with no designer system widget (folder view, photo gallery, confirmations, affiliate, order form…) and the fallback
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
- Region-level gaps (menu, menu sequence, ad region, tag cloud) are R6 in `legacy-retirement-prerequisites.md`.

## Which are covered?
- Custom form confirmation (a `form_item_view` page set as the form's confirmation page), order receipt (`order_view`, liveform `order_receipt`), login, logout, forgot/change/set password, registration and membership entrance, My Account and its sub-pages, view order, address book, e-mail preferences, catalog and detail, cart, express order, calendar view and event, form list and item view, search results, error.
- Classic multi-step checkout (shipping address and arrival, shipping method, billing information, order preview) stays frozen (`feature-roadmap-2026-10.md`): no widget unless the owner asks.

## What does a converted page use until a widget exists?
- A `region` node of type `system` (`<system></system>`) keeps the classic renderer running on the page, so the page works; only its inside is not designable. Retirement needs the owner's list of types that must be designable first.

## How is a new type added?
- `SW_TYPES` + `_swTypeDefaults`, `pg_sw_type_labels()`, renderer in `includes/fn/widgets*.php` with `_pg_inject_messages_node()` named after the classic liveform, PHP default tree and JS starter tree in lockstep, tokens and flags passing `tools/check_bindings.php`; compare against the classic page (skill `pinegrap-tasarimci-widget`).

## Open questions?
- Confirmations: widgets of their own, or a state / next-page setting of the registration and membership widgets?
- Which of the types above must be designable before L5 (retire), and which may stay `system` region nodes?

## Where to start?
- The owner's list; then one widget per PR. Order form and photo gallery are the ones visitors see most.
