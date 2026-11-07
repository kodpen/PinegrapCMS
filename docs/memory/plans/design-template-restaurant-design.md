---
name: design-template-restaurant-design
description: Design of plans/design-template-restaurant.md: the shipped features the Restaurant template reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Menu: a collection (the Rooms and Trainers pattern): the Menu form with the dish's course, price as words, diet tags, a badge, a photo and a staff choice `featured` (Show on the home page); `menu` lists each course in a tab (a list filtered by the course's fixed words, as Café; no `?course=` link, the tabs would ignore it), `dish` is the record page, `dish_new` the staff page. Price stays words, so no shop product per dish.
- Table reservations: a request, as Café and Hotel: a record of the Reservations form (date with `min_date` today, time from `$time_choices` (every half hour of a sitting), guests, occasion, `?occasion=`, `?event=`, `?time=` by `_cf.url_param`), an office-only status, messages on the record page's Comments widget, `submitter_only` view, My Reservations, the staff list. The booking widget is not used for tables: a service has capacity 1 (`commerce/bookings-model.md`).
- The chef's table (one table, one sitting per evening): `book_online` places the booking widget with `'tree' => 'starter'` beside a link to the reservation form ([[booking-templates]]).
- Tasting menus and the wine list are fixed sections built from the template's data; a tasting menu links the reservation form with the menu chosen.
- Events: records of the Events form, as Café: date counted from the day the template opens (`$event_on`), an event card books through `reserve?event=…`.
- Private dining: rooms from the template's data, an enquiry form as Hotel's event enquiries (`?room=`, `?kind=`), office-only status, messages on the record page, views `submitter_only`, a staff list.
- Gift vouchers: shop products in the Gift Vouchers group, as Hotel ([[design-template-hotel]]). Only shop pages carry `requires => ecommerce`; shared parts never link them.
- Not built (new behaviour): live table availability or a floor plan, deposits or no-show fees, online food ordering and delivery, voucher redemption against a reservation, allergen filters beyond the form list's filter.

## Look and names
- Prefix `prt-` for classes (`prt-brand`, `prt-display`, `prt-eyebrow`, `prt-hero`, `prt-lift`, `prt-price`, `prt-quote`), `rt-` for field ids. Two fonts: Cormorant Garamond headings, Jost body. Look `elegant`, palette `burgundy`, order 99 (79 gym … 95, 97 taken by open plans), icon `bi-egg-fried`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: seasons and weekdays only.
- Turkish: "rezervasyon" for a reservation, "menü" for the menu, "tadım menüsü" for a tasting menu, "şarap listesi", "hediye çeki" for a gift voucher, "özel yemek" for private dining, "şefin masası" for the chef's table; check every shared key in the shards before reuse.

Related: docs/memory/plans/design-template-restaurant.md
