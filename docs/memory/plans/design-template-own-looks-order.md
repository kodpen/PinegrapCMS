---
name: design-template-own-looks-order
description: Order of the polishing round after Launch: every shipped template's family and pages steps, and where the open template plans (Skiresort, Gallery and the rest) take theirs
---

## How does a template's polishing go?
- Step `<id>-1`: its blocks. First the reuse search ([[design-template-own-look]]): a block of the same structure is placed or extended, never copied; general blocks go into the general groups, only sector-specific ones into the family (prefix = the template's short name). The PR body names the blocks reused and added. Step `<id>-2`: home and signature pages rebuilt on it (the sector's pages, not the standard ones), motion where it fits, version raised, its `designer/design-template-<id>.md` updated. A small template may do both in one PR; a big one a third step. Main plan: [[design-template-own-looks]].
- From when L3 lands, up to four templates at a time, in the order below; a template's `-2` waits for its `-1`.

## In which order, and why?
0. G1, G2 (after L1): general blocks the sector templates share, each checked against the existing groups first, so the later families stay small and reuse them. Done in one PR (#8082): eight of the ten were already palette blocks; spaces to hire and seasonal hours were added. Which block covers each: [[designer-blocks-general]].
1. Built on the shared builders (`$story_band`, `$framed`, `$ask_band`, `$figures`, `$testimonials`): they make the set look alike. Most builders first: wedding, photo, school, architecture, campsite, cityhall, construction, museum, hotel, travel, gym, music.
2. Sector templates with the same eyebrow, figures, lift-card and FAQ pattern, in the picker's order (first seen first): clinic, salon, cafe, events, courses, law, realestate, portfolio, appstudio, dealership, market, social, charity.
3. The shop templates: store, boutique, bookshop.
4. Templates that already have a family or their own layout: news, magazine (one step each: motion and the family's newer blocks), forum.
5. Last: hello (the default starter), playground (it then shows each family's blocks).
- Every shipped template is in this list; Launch is in the main plan.

## Where do the open template plans come?
- Their files are not edited here (open PRs hold most). In each, the assembly step (look, fonts, rules) follows the rule: no new stock band added there. Their pages are built, so each one's polishing (`-1`, `-2`) joins the list as soon as its assembly lands, ahead of the groups not yet claimed: bakery, brewery, carrental, coworking, drivingschool, ferry, filmstudio, florist, homeservices, library (assembly #7982 open), marina, pharmacy, podcast, recruitment, restaurant, sportsclub, theatre, veterinary.
- Gallery: step 3 (`pages_public.php`, #7992) was opened before the rule and lands as it is; steps 4 and 5 (artists, exhibitions) are its signature pages: when `gallery-1` (its family) has landed they place its blocks, otherwise its `-2` rebuilds them after the assembly.
- Skiresort: step 2 (`cards.php`, #7986) lands as it is. `skiresort-1` (its family: conditions board, lift status, slope map, pass cards, snow report) is done (#8050); steps 3-4 then build home, conditions and slopes on it, and it needs no `-2`.
- Coworking: `coworking-1` ([[designer-blocks-coworking]]) and `coworking-2` ([[design-template-coworking-pages]]) are done (#8113, #8127).
- Drivingschool: `drivingschool-1` (its family: licence classes, theory signs, test sheet) is done (#8117); `drivingschool-2` rebuilds home, licences and the theory and practical test pages on it.
- Ferry: `ferry-1` (its family: departure board, route timetable, crossing strip, fares by vehicle; [[designer-blocks-ferry]]) is done (#8123); `ferry-2` rebuilds home, routes, timetables and fares on it.
- A template plan opened after 2026-11-07 starts with its family step ([[design-template-own-look]]) and needs no polishing step.

## Who claims what?
- Any worker, in the order above, one step per PR; a step whose template still has an open PR on its files waits.
