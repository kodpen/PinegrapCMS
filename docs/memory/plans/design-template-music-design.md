---
name: design-template-music-design
description: Design of plans/design-template-music.md: what the Music template reuses for shows, releases, merch, press kit and mailing list, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Releases are a collection (the Hotel's Rooms pattern, [[collection-fields]]): a format pick list (album, EP, single, live), `year` as words, a cover image, the track list as long text (one line per track, "1. Title — 3:42", `$tracks_text()`), credits, listening links as hidden URL fields (`listen_url`, `video_url`), office field `featured`. Fans' words on a release are the record page's comments.
- Shows are a collection: date kind written from today on opening (`$show_on()`, the Events way, [[design-template-events]]), city, country pick list, venue, doors time as words, a ticket state pick list (on sale, few left, sold out, free entry), `ticket_url` (hidden URL; tickets are sold elsewhere). The tour list shows dates from today on, past shows the rest ([[collection-lists]]).
- A booking enquiry from a promoter or festival is a request record (the Hotel's booking way): event, city, date with `min_date` today, capacity, fee band; office-only status, messages on the record page's Comments widget, `submitter_only` view, My Enquiries, the staff list.
- The press kit is page content plus files in a public `press` folder (the Charity reports way).
- The mailing list is the standard newsletter form into the contact group `newsletter`.
- With the shop: merch products (shirt, hoodie, tote, poster, vinyl, cassette) shippable, and gift cards, in a Merch group. Only shop pages carry `requires => ecommerce`; shared parts and record pages never link them.
- Not built (new behaviour): selling tickets or seat maps, audio players or streaming of tracks, digital downloads of music, embedded tour feeds from ticket services, fan clubs with paid tiers.

## Look and names
- Prefix `pmu-` for classes (`pmu-brand`, `pmu-display`, `pmu-eyebrow`, `pmu-hero`, `pmu-lift`, `pmu-date`, `pmu-quote`), `mu-` for field ids. Two fonts: Bebas Neue headings, Inter body. Look `bold`, palette `midnight`, order 91 (79 gym, 81 charity, 83 travel, 85 photo, 87 wedding, 89 construction, 90 forum), icon `bi-music-note-beamed`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample shows: dates are counted from the day the template opens; release years are words relative to none ("Debut", "Second album").
- Turkish: "konser" for a show, "turne" for the tour, "diskografi" for the discography, "albüm", "single", "EP" as they are, "parça" for a track, "basın kiti" for the press kit, "merch" as "ürünler" in headings ("Grup Ürünleri"), "e-posta listesi" for the mailing list, "konser talebi" for a booking enquiry; check every shared key in tr.json before reuse: `Tour` is "Tur", `Tracks` "Oturum Dizileri", `Release` "Kaldır", `Bookings` "Randevular", so the template uses `Tour Dates`, `Track Listing`, `Release Title`, `Show Bookings`.

Related: docs/memory/plans/design-template-music.md
