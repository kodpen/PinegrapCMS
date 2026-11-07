---
name: design-template-gallery-design
description: Design of plans/design-template-gallery.md: the shipped features it reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Artists: a collection (the Vets and Pharmacists pattern): the Artists form (name, medium pick list, born in and lives in as words, statement, biography, portrait, `photo_1`-`photo_3`), record page `artist`, list `artists` filtered by `?medium=`, staff page `artist_new`. The artist page lists the artist's works (a Works list filtered by the artist reference, [[collection-lists]]).
- Works: a collection: title, artist (a reference to the Artists form by label, [[collection-references]]), exhibition (reference), medium, year and size as words, price as words ("Price on request" allowed), an office-only availability pick list (Available, Reserved, Sold) shown as a badge. The work page links `enquire?artwork=<title>`.
- Exhibitions: a collection (the Museum pattern): status pick list (Now On, Coming Soon, Past), dates as words, the opening night as words (day, hours, "all welcome"), the artists as words, the curator's text, `photo_1`-`photo_3`; list `exhibitions` by `?status=`; the exhibition page lists its works and links `viewing?exhibition=<title>`.
- Enquiries: a request form (the Veterinary appointment pattern): the work by `_cf.url_param` (`artwork`), the kind (buying, price and availability, a commission, shipping and framing), the message, how to reply, consent linking `privacy`; office-only status (received, answered, reserved for you, sold, closed); messages on the record page's Comments widget, `submitter_only` view, My Enquiries, the staff list. Never a price commitment or a sale: a sale goes through the gallery.
- Private viewings: a second request form: the exhibition by `_cf.url_param` (`exhibition`), the preferred date with `min_date` today, morning/afternoon/evening, the party size, a note; status (requested, confirmed, declined, held); same record page, lists and e-mails.
- Shop: signed limited editions, small works and exhibition catalogues as ordinary products (shippable). Only shop pages carry `requires => ecommerce`; shared parts never link them.
- The Journal: the blog with a topic field, filtered by `?topic=`.
- Not built (new behaviour): a sold-out edition counter, live availability synced between a work and a product, an online offer or reserve price, an artwork provenance register, a fixed-date event calendar, a virtual viewing room.

## Look and names
- Prefix `pgl-` for classes (`pgl-brand`, `pgl-display`, `pgl-eyebrow`, `pgl-hero`, `pgl-frame`, `pgl-lift`, `pgl-price`, `pgl-caption`, `pgl-quote`), `gl-` for field ids. Two fonts: a high-contrast serif for headings (DM Serif Display), a clean sans for the body (Inter); the assembly checks them against the templates landed by then. Look `minimal`, palette `graphite`, icon `bi-easel2`; the order is settled at assembly. White walls: a light page, generous space, works framed with a thin border and a caption under them (artist, title, year, medium). No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: dates are words ("Opening night: Thursday, 6 to 9 pm").
- Turkish: "galeri" for gallery, "sanatçı" for artist, "sergi" for exhibition, "eser" for a work, "açılış gecesi" for the opening night, "özel gösterim" for a private viewing, "baskı / edisyon" for an edition, "sanat danışmanlığı" for art advisory, "Günce" for the Journal (the shipped key); check every shared key in tr.json before reuse (the Museum's and Photo's words).

Related: docs/memory/plans/design-template-gallery.md
