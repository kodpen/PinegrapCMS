---
name: designer-blocks-travel
description: The Travel block family (travel- prefix, own palette group): day-by-day itinerary, destination guide; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_travel.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `travel-`, palette group "Travel" (`bi-airplane`). Built for the Travel template's polishing round ([[design-template-travel]], `plans/design-template-own-looks-order.md`, `travel-1`); usable by any travel agency, tour operator or walking-holiday company.

## Which blocks does it hold?
- `travel-itinerary`: header (eyebrow, `h2` tour name, a line), then an `ol.pg-travel-days` of four days on a dashed route line, each a round marker `pg-travel-day` ("Day" + number, tabular figures), `h3` title, a line, the night (moon icon) and the meals (cup icon) in words. Beside it an `aside` card (`sticky-lg-top`): `h3` "Trip at a glance", `dl.pg-travel-glance` (Duration, Group size, Starts and ends, Departures as words), "Starting from" + amount + "per traveller" in separate nodes (`pg-travel-figure`), Book a Tour / Plan a Tailor-made Trip. Header fades, days rise (stagger 100) on view.
- `travel-destination`: a 4:3 picture in a `figure` with the region as an icon-and-word badge; beside it eyebrow (country), `h2` name, a line, `h3` "Best time to go" over a `ul.pg-travel-seasons` of four seasons (icon, name, verdict; a good one `pg-travel-best`, tinted and with a check and "Best time", never colour alone), a `dl.pg-travel-glance` in two columns (Getting there, Language, Currency, Entry visa), See the tours here / Plan a Tailor-made Trip. Picture fades, seasons rise (stagger 80) on view.

## Why are these not reused blocks?
- The itinerary is not `timeline-vertical` / `steps-numbered` / `museum-trail` / `ferry-crossings`: days of one trip with where each night is spent and the meals, beside the trip's facts and price. The guide is not `hero-split` / `hours-seasons` / `zoo-animal`: one place's seasons judged for going (no months, no hours) with its practical facts.

## Which general blocks does a travel site place instead?
- Travel styles and why travel with us: `features-grid` / `services-icon-cards`. Deals: `pricing-tiers` or `services-icon-cards`. Booking steps: `steps-numbered`. Included / not included: `features-list`. Travel information: `features-grid`. Figures: `stats-icons`. Travellers' words: `testimonial-ratings` / `testimonial-grid`. Tailor-made call: `booking-split` / `cta-inline`. Questions: `faq-categorized`. Team: `team-profiles`. Tours, destinations and journal posts stay collection list widgets.

## What do its rules and texts follow?
- Rules `pg-travel-days`, `pg-travel-day`, `pg-travel-glance`, `pg-travel-figure`, `pg-travel-seasons`; each colours with a theme variable. Sample texts reuse the template's Cappadocia tour and destination keys; departures and seasons are words, no year or month. "Length" reads "Uzunluk" and "Visa" is the card brand in the shared keys, so the facts are "Duration" and "Entry visa".

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_travel_test.php` runs it through `tests/helpers/designer_family_run.php`: own group, a section per block starting with its one `h2` and only `h3` under it, no fixed colour or `ptv-`/`tv-` class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; four day markers and their nights, facts and price nodes; the region badge, two good seasons in words; the `ol`, `dl`, `ul`, `aside` and check read from the file; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
