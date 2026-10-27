---
name: system-widget-missing-types
description: Plan (2026-10-19, multi-step checkout done): classic page types with no designer system widget (multi-step checkout, order form, folder view, photo gallery, confirmations, affiliate…)
---

## Which classic page types have no widget?
Compared 2026-10-19: the `page_type` cases of `get_page_content.php` against `SW_TYPES` / `pg_sw_type_labels()`.
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
- Region-level gaps (menu, ad region, tag cloud…): done as widgets (`designer/region-node-to-widget.md`).

## Which are covered?
- Form confirmation and item view, order receipt and view, login, logout, password screens, registration and membership entrance, My Account and its sub-pages, catalog and detail, cart, express order, the multi-step checkout, calendar view and event, form list, search results, error.

## Why did the classic multi-step checkout get widgets?
- Owner (2026-10-19): almost every site checks out through it, so it cannot stay a `system` node ("frozen" means its code keeps working). The four widgets post to the classic handlers with their names; the order preview draws `get_order_preview()`'s own values (`workflow/decisions-made-product.md`).

## What does a converted page use until a widget exists?
- A `region` node of type `system` (`<system></system>`) keeps the classic renderer running on the page, so the page works; only its inside is not designable. A custom layout (`layout_type = 'custom'`) keeps drawing through that node too; swapping it for a widget is Y5 of `legacy-layouts-in-designer.md`, opt-in per page. Retirement needs the owner's list of types that must be designable first.

## How is a new type added?
- `SW_TYPES` + `_swTypeDefaults`, `pg_sw_type_labels()`, renderer in `includes/fn/widgets*.php` with `_pg_inject_messages_node()` named after the classic liveform, PHP default tree and JS starter tree in lockstep, tokens and flags passing `tools/check_bindings.php`; compare against the classic page (`designer/system-widget-test-pages.md`).

## Which order did the owner give?
- 2026-10-27, through the coordinator: "bekleyen işçilere yanıtı sen ver benim yerime", "en makul ve işlevsel seçimle", and "sorular mimari soru değilse makul seçim yap". Under that the order is: order form, folder view, photo gallery, confirmations, affiliate sign-up form.
- Confirmations: decided when reached; preferred: a state / next-page setting of the registration and membership widgets.

## Open questions?
- Which of the types above must be designable before L5 (retire), and which may stay `system` region nodes?

## Where to start?
- The multi-step checkout is done. Next: the next type of the owner's order that no open PR holds. One widget per PR.

## Which steps are there?
1-4. Done: shipping address (#1746; verified box left), method (#1759), billing (#1777), order preview (#1789); [[shipping-address-widget]] and siblings, [[order-preview-widget]].
- Left from 4: what [[order-preview-widget]] lists under "What is left out?". 4b, card fields and surcharge rows switching with the method: done (#1918).
5. The owner's order (above): 5a order form done (#2367, [[order-form-widget]]), 5b folder view, 5c photo gallery, 5d confirmations, 5e affiliate sign-up form.
