---
name: decisions-made-product-109
description: Decisions made, product part 109 (2026-11-24 on): a form list's feed sends a teaser for a record whose detail page is not public; a saved-cart link's order belongs to whoever completes it
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-108.md`.

## What does a form list's feed send for a record whose detail page is not public? (owner, 2026-11-24, in the architect's window, bug #11440)
- Asked with three options (title and link; no item; title and link with a list option to drop the items), he chose "Başlık ve bağlantı".
- The item carries title, link, pubDate, guid and category. It carries no description and no media enclosure.
- "Not public" is a detail page whose folder answers `get_access_control_type()` other than `public`: membership, private, registration, guest.
- Both feeds: the designed one (`pg_sw_form_feed_parts()`) and the classic one in `get_page.php`. No option on the list.
- Why: the body is what the folder protects and it left the site in the feed; the title is already on the public list page, and the link leads to the sign-in screen.
- Running sites: a feed reader of a members-only blog keeps getting new items, without the body.
- Architect (2026-11-24): the feed answers as for a visitor who is not signed in, whoever asks, so one address gives one answer.

## Whose order is a cart opened by its saved-cart link and completed by another person? (owner, 2026-11-24, in the architect's window, bug #11435)
- Asked with three options (the opener's; the builder's with the buyer's details hidden, which needs a schema change; as today), he chose "Bağlantıyı açanın".
- `retrieve_order()`, for an order that is not already the opener's: a signed-in opener becomes its `user_id` / `contact_id`, as `initialize_order()` rebinds; for a guest both are cleared, and `connect_user_to_order()` binds a later sign-in as before.
- The builder no longer finds the order in their order history, nor the buyer's billing name, address, phone, e-mail and card digits on View Order.
- Why: the person who paid had their details shown to another account. The Storefront API binds an adopted cart the same way (`plans/storefront-api-cart-token.md`).
- Running sites: the same account opening its link on another device changes nothing. One person preparing an order for another to pay is what cart quotes do (`commerce/cart-quotes.md`).
- Architect (2026-11-24): the rule is about who completes. A builder who completes their own cart after another person opened the link still gets the order on their account; a test keeps it.

## Does a shared component that draws a converted common region count as used? (architect, 2026-11-24, bug #11502)
- Yes. It is a reading of the owner's decision of 2026-10-27 (`designer/common-region-to-shared-component.md`): deleting the component stays the region's undo.
- `pg_shared_component_usage()` also reads the category `common_region:<id>`. The single delete says that the component draws the common region wherever its tag stands and that deleting brings back the region's earlier content, then deletes. `delete_many` keeps such a row unless forced, as any used row.
