---
name: design-template-brewery-design
description: Design of plans/design-template-brewery.md: what the Brewery template reuses for beers, taproom events, tours, the shop and the Beer Club, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Beers are a collection (the Hotel's Rooms pattern, [[collection-fields]]): a style pick list (lager, pale ale, IPA, stout, sour, wheat beer), an availability pick list (year-round, seasonal, limited release, taproom only), `abv` and `ibu` as words, tasting notes, food pairing, malts and hops, a picture, office field `featured`. Drinkers' notes on a beer are the record page's comments with stars.
- Taproom events are a collection: date kind written from today on opening (`$event_on()`, the Music shows way), start time as words, a kind pick list (live music, quiz night, tap takeover, beer release, food pop-up), entry as words, `ticket_url` (hidden URL; tickets, if any, are sold elsewhere). The list shows dates from today on, past events the rest ([[collection-lists]]).
- A brewery tour booking is a request record (the Hotel's booking way): tour, date with `min_date` today, time, group size, `?tour=` by `_cf.url_param`, a required confirmation that every taster is of legal drinking age; office-only status, messages on the record page's Comments widget, `submitter_only` view, My Tour Bookings, the staff list.
- Private tastings: `book_online` places the booking widget with `'tree' => 'starter'`, beside a link to the tour request form ([[booking-templates]]).
- The tap list and the taproom's food are page content edited in the designer (a list that changes daily has no form of its own).
- With the shop: beer packs (mixed case, six-pack, a single beer's case), glassware, merch and gift cards in a Brewery Shop group; Beer Club terms (three, six, twelve months) with `membership_days`, so the members' area (`club` folder, access `membership`) opens for the term bought, as the Courses template ([[design-template-courses]]). Only shop pages carry `requires => ecommerce`; shared parts and record pages never link them.
- Not built (new behaviour): an age gate before the site opens, automatic monthly club charges from a template catalog (a term is bought again to renew), scheduled club box deliveries, a live tap board from a till, selling event tickets, tour capacity per slot, licence checks at checkout.

## Look and names
- Prefix `pbw-` for classes (`pbw-brand`, `pbw-display`, `pbw-eyebrow`, `pbw-hero`, `pbw-lift`, `pbw-date`, `pbw-abv`), `bw-` for field ids. Two fonts: Zilla Slab headings, Work Sans body. Look `organic`, palette `amber`, order 99 (93 school, 95 museum, 97 architecture), icon `bi-cup-straw`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month: event days count from the day the template opens; seasonal beers say their season in words.
- The footer and the shop say the beers are for adults of legal drinking age and to drink responsibly.
- Turkish: "bira" for a beer, "bira stili" for a style, "taproom" as "tadım salonu", "fabrika turu" for a tour, "Bira Kulübü" for the club, "etkinlik" for an event, "alkol oranı" for ABV, "acılık" for IBU; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-brewery.md
