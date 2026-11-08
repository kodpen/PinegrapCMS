---
name: design-template-zoo-design
description: Design of plans/design-template-zoo.md: the shipped features the Zoo template reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Animals are a collection ([[collection-fields]]): common name, species (Latin name) as words, a habitat pick list (Savanna, Rainforest, Polar Coast, Aquarium, Australia Walk, Farm), a class pick list (Mammal, Bird, Reptile, Amphibian, Fish, Invertebrate), a conservation status pick list (the seven IUCN levels), diet, lifespan and the habitat number as words, a summary, details as long text, `photo_1`, office field `featured`. The list filters `?habitat=` and `?class=` ([[collection-lists]]).
- Today's schedule (feeding times and keeper talks) is words the staff edit in the designer, drawn by the `zoo-today` block; it is the same every day of a season, so no dated record. Opening hours by season reuse `hours-card` (or `hours-seasons` once shipped).
- Tickets (adult, child, senior, family), memberships (single, couple, family, with free entry for a year) and adoptions (per animal, three levels) are one price table in parts.php read by `$money()`: the public pages say them as words, the shop makes them products of three groups, `shippable => false`; only the shop pages and `address_book` carry `requires => ecommerce`. A ticket is shown at the gate from the receipt; an adoption pack is e-mailed (words only, no file delivery). Adoption levels on the public page reuse `pricing-tiers`.
- A school or group visit is a request, never a booking: the Visit Requests form (visit kind by `?visit=`: school trip, guided group tour, keeper for a day, birthday; group kind by `?group=`; age by `?age=` through `_cf.url_param`; date with `min_date` today; number of visitors; workshop wanted; accessibility needs; notes; consent linking `privacy`), office-only status (Received, Confirmed, Waiting List, Cancelled), messages on the record page's Comments widget, `submitter_only` view, My Visits, the staff list.
- Events are a collection dated as Café and Restaurant: a `date` field counted from the day the template opens (`$event_on`), time, place, kind pick list (Night Tour, Family Day, Talk, Workshop), price as words, `photo_1`, `featured`; the list shows today on, sorted by date.
- Conservation news is the standard blog; keepers are fixed data on `about`.
- Not built (new behaviour): timed entry slots or capacity in the cart, an interactive or live map, live animal cams, a real adoption certificate generator, membership cards or member pricing in the cart, push alerts for feeding times, donations as a payment flow beyond products.

## Look and names
- Prefix `pzo-` for classes (`pzo-brand`, `pzo-display`, `pzo-hero`, `pzo-zone`, `pzo-price`), `zoo-` for field and section ids. Two fonts: Bricolage Grotesque headings, Rubik body. A green and sand palette; order and look settled by the assembly step; icon `bi-tree`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: seasons are words, events count from today.
- Turkish: "hayvanat bahçesi" for the zoo, "akvaryum", "hayvan", "yaşam alanı" for a habitat, "besleme saati" for a feeding time, "bakıcı sohbeti" for a keeper talk, "park haritası", "bilet", "üyelik", "sahiplenme" for an adoption, "okul gezisi" for a school visit, "grup ziyareti", "koruma" for conservation, "nesli tükenmekte" for Endangered, "etkinlik", "Haberler" for News; check every shared key in tr.json before reuse.

## Which field names do the cards read?
- Animals (step 5 names its fields so): `title`, `species`, `habitat`, `habitat_number`, `animal_class`, `conservation_status`, `diet`, `lifespan`, `summary`, `details`, `photo_1`, `featured`.
- Events (step 7): `title`, `event_date`, `event_time`, `kind`, `place`, `price`, `summary`, `photo_1`, `featured`.

Related: docs/memory/plans/design-template-zoo.md
