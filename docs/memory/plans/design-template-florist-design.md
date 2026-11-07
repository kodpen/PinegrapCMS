---
name: design-template-florist-design
description: Design of plans/design-template-florist.md: shipped features reused for bouquets, delivery dates, subscriptions and enquiries, what is not built, look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Bouquets, occasions, subscription plans, event services, delivery areas and flowers in season are page content from `parts.php` data; prices are words written with `$money()` ("From …"). Every page stands without the shop.
- A bouquet order request is a record of Flower Orders (bouquet, occasion, size by `_cf.url_param`, a delivery date with `min_date` today, a delivery time, the recipient's name, phone and address, the card message, a budget band), an office-only status, messages on the record page's Comments widget, `submitter_only` view, My Requests, the staff list. It is a request the shop confirms, not a sale.
- With the shop: the bouquets are products in groups by occasion, with vases and extras, gift cards (`shippable => false`) and prepaid subscription bundles (a number of deliveries paid at once, one product per plan). The delivery date of a shop order is the checkout's requested arrival date, which the store sets up in its shipping settings ([[checkout-requested-arrival-date]]); the template creates no arrival dates. Only shop pages carry `requires => ecommerce`; shared parts and record pages never link them.
- A subscription request is a record of Subscription Requests (plan by `?plan=`, start date with `min_date` today, delivery day and time, address), office-only status, the same view and lists as an order request.
- Event and wedding flowers: an enquiry record (event type by `?event=`, date, guests, budget band), office-only status, the record's Comments widget; a first consultation is booked at a free time with the booking widget (`consultation`, starter tree, [[booking-templates]]).
- Our Work is a collection (the Wedding's Real Weddings pattern, [[collection-fields]]): title, event type and season pick lists, `photo_1`-`photo_4` image kinds, summary, story, flowers used as long text, office field `featured`. Lists filter `?type=` and `?season=` ([[collection-lists]]).
- Not built (new behaviour): recurring billing of a template product, a card message or delivery slot stored on a shop order line, delivery zones that price shipping by postcode, a same-day cut-off enforced at checkout, stock of fresh stems.

## Look and names
- Prefix `pfl-` for classes (`pfl-brand`, `pfl-display`, `pfl-eyebrow`, `pfl-price`, `pfl-lift`, `pfl-quote`, `pfl-script`), `fl-` for field ids. Two fonts: Cormorant Garamond headings, Nunito Sans body. Look `organic`, palette `coral`, order 99 (97 architecture), icon `bi-flower1`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: seasons and "the day before" only.
- Turkish: "çiçekçi" for the florist, "buket" for a bouquet, "aranjman" for an arrangement, "özel gün" for an occasion, "teslimat" for delivery, "çiçek aboneliği" for a subscription, "kart mesajı" for the card message, "sipariş talebi" for an order request, "etkinlik" for an event; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-florist.md
