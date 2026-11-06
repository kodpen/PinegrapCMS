---
name: design-template-photo-design
description: Design of plans/design-template-photo.md: which shipped features the Photo template reuses for galleries, booking, proofing and downloads, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Galleries are a collection (the Hotel's Rooms pattern, [[collection-fields]]): a gallery has a category pick list (Weddings, Portraits, Family, Newborn, Events, Commercial), a location and a story in words, `cover` and `photo_1`-`photo_6` image kinds, office field `featured`. The list filters `?category=` ([[collection-lists]]); the gallery is its record page.
- Packages are the template's data, shown with `$money()` prices; each opens the booking request with the package chosen (`?package=`, `_cf.url_param`).
- A booking is a request, never a reservation: a record of Shoot Bookings (package, category, preferred date with `min_date` today, time of day, studio or on location, people, notes, consent), office-only status, messages on the record page's Comments widget, `submitter_only` view, My Bookings, the staff list. A studio mini session can also be booked at a free time on `book_online` with the booking widget's starter ([[booking-templates]]).
- Proofing and downloads: a client's gallery is a page in a `private` folder; the studio duplicates the sample folder (`duplicate_folder.php`) for each client, puts the photos and a ZIP in it and gives that client the folder in the panel; files in a private folder are served only to whoever may open it. The client sends the frames they choose with the Proof Selections form (`?gallery=`), `submitter_only`, an office-only status, messages in its Comments widget. The studio may also attach finished files to a booking's messages.
- With the shop: a session deposit per package and gift vouchers in a Photo Sessions group, `shippable => false` (the Events tickets, [[design-template-events]]). Only shop pages carry `requires => ecommerce`; shared parts and record pages never link them.
- Not built (new behaviour): picking favourites by clicking a photo (a heart per photo), watermarking, per-client gallery lists without the folder, print ordering from a photo, live availability for on-location shoots, a balance invoice after the deposit.

## Look and names
- Prefix `pph-` for classes (`pph-brand`, `pph-display`, `pph-eyebrow`, `pph-price`, `pph-lift`, `pph-quote`, `pph-hero`), `ph-` for field ids. Two fonts: DM Serif Display headings, Manrope body. Look `minimal`, palette `steel`, order 85 (79 gym, 81 charity, 83 travel), icon `bi-camera`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample.
- Turkish: "çekim" for a shoot or session, "galeri" for a gallery, "paket" for a package, "seçim" for a proof selection, "kapora" for a deposit; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-photo.md
