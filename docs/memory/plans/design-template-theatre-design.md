---
name: design-template-theatre-design
description: Design of plans/design-template-theatre.md: the shipped features the Theatre template reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Productions are a collection ([[collection-fields]]): a genre pick list (Drama, Musical, Comedy, Dance, Opera, Family), a state pick list (Now Playing, Coming to the Stage, Past Seasons), the run as words ("Until the end of spring", never a fixed date), the stage, the running time, age guidance, the writer and director, the cast as long text, a summary, `photo_1`-`photo_3`, office field `featured`. The list filters `?genre=` and `?status=` ([[collection-lists]]); a theatre moves a production along by editing its record; audiences' words are comments.
- Performances are a second collection like Music's shows: the production (pick list of the sample titles, filtered `?production=`), `perf_date` (a date field; samples counted from the opening day by `$perf_on`), the time, the stage, an access pick list (Standard, Relaxed Performance, Captioned, Audio Described, Sign Language Interpreted; `?access=`), ticket state (On Sale, Few Left, Sold Out); the performance page may add a `ticket_url` for an outside box office, the list row links only the performance page. `whats_on` lists from today, `past_performances` before it.
- Venue hire is a request ([[design-template-museum]] requests): spaces are the template's data; the Hire Enquiries form (space by `?space=`, the event, guests, dates as words with `min_date` today, needs, consent), office-only status, messages in the Comments widget, `submitter_only`, My Hire Enquiries, the staff list.
- Learning: youth theatre groups and workshops are data by age; a school group booking and a youth theatre place are requests (forms with `?session=` / `?group=`), followed like the hire enquiry.
- With the shop: ticket bands (Stalls, Dress Circle, Upper Circle, Concession, Under 26) as products of a Tickets group, friends tiers (Friend, Best Friends, Family Friends, Patron) of a Friends group, `shippable => false`; prices from one table (`$ticket_prices`, `$tier_prices`) through `$money()`. Only shop pages carry `requires => ecommerce`; public pages say the prices as words.
- Not built (new behaviour): seat selection or a seating map, a ticket tied to one performance's date in the cart, capacity per performance, member pricing at checkout, Gift Aid.

## Which field names do the parts bind?
- The performance row: `perf_date`, `perf_time`, `production`, `stage`, `access`, `ticket_state`, `form_item_view`; the production card: `photo_1`, `title`, `genre`, `status`, `run`, `stage`, `summary`; the facts lists add `running_time`, `age_guidance`. Sample dates come from `$perf_on` (Music's `$show_on`). Request links: `?space=` (hire), `?group=` (youth place), `?workshop=` (school group).

## Look and names
- Prefix `pth-` for classes, `th-` for field ids. Fonts: Playfair Display headings, Karla body. Look `luxe`, palette `crimson`, order 99, icon `bi-ticket-perforated`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample; performance dates count from today.
- Turkish: "oyun" or "yapım" for a production ("Yapımlar" for the list), "temsil" for a performance, "sezon" for the season, "bilet" for a ticket, "Tiyatro Dostları" for friends, "sahne" for a stage, "salon kiralama" for venue hire, "eğitim" for learning, "gençlik tiyatrosu" for youth theatre, "Haberler" for News; check every shared key in the shards before reuse: "Season", "Stage", "Run", "Production", "Coming Soon" and "Not available" mean other things there, so the template says "The Season", "Theatre Stage", "Run Dates", "The Production", "Coming to the Stage", "Space not available".

Related: docs/memory/plans/design-template-theatre.md
