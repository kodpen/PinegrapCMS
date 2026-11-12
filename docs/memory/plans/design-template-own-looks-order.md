---
name: design-template-own-looks-order
description: Order of the polishing round after Launch: every shipped template's family and pages steps, in which order, and where each template stands
---

## How does a template's polishing go?
- Step `<id>-1`: its blocks. First the reuse search ([[design-template-own-look]]): a block of the same structure is placed or extended, never copied; general blocks go into the general groups, only sector-specific ones into the family (prefix = the template's short name). The PR body names the blocks reused and added. Step `<id>-2`: home and signature pages rebuilt on it (the sector's pages, not the standard ones), motion where it fits, version raised, its `designer/design-template-<id>.md` updated. A small template may do both in one PR; a big one a third step. Main plan: [[design-template-own-looks]].
- From when L3 lands, up to four templates at a time, in the order below; a template's `-2` waits for its `-1`.

## In which order, and why?
0. G1, G2 (after L1): general blocks the sector templates share, each checked against the existing groups first, so the later families stay small and reuse them. Done in #8082: eight of ten were already palette blocks; spaces to hire and seasonal hours were added. Which block covers each: [[designer-blocks-general]].
1. Built on the shared builders (`$story_band`, `$framed`, `$ask_band`, `$figures`, `$testimonials`): they make the set look alike. Most builders first: wedding, photo, school, architecture, campsite, cityhall, construction, museum, hotel, travel, gym, music.
2. Sector templates with the same eyebrow, figures, lift-card and FAQ pattern, in the picker's order: clinic, salon, cafe, events, courses, law, realestate, portfolio, appstudio, dealership, market, social, charity.
3. The shop templates: store, boutique, bookshop.
4. Templates that already have a family or their own layout: news, magazine (one step each: motion and the family's newer blocks), forum.
5. Last: hello (the default starter), playground (it then shows each family's blocks).
- Every shipped template is in this list or in the status lines below; Launch is in the main plan. A template plan opened after 2026-11-07 starts with its family step ([[design-template-own-look]]) and needs no polishing step.

## Which templates came from their own plans?
- Their plans built their pages and have finished, so their `-1` and `-2` come ahead of groups 2 to 5 (their status lines are first). Gallery's signature pages are artists and exhibitions.

## Where does each template stand?
- Travel: `-1` #8337, `-2` #8657 done.

- Gym: `-1` #8339, `-2` #8655 done.

- Clinic: `-1` #8346 done.

- Dealership: `-1` #8640 done; `-2` open.

- Bakery: `-1` #8665, `-2` #8715 done.

- Brewery: `-1` #8666, `-2` #8714 done.

- Carrental: `-1` #8669, `-2` #8747 done.

- Filmstudio: `-1` #8689 done; `-2` open.

- Florist: `-1`, `-2` open.

- Homeservices: `-1` #8773 done; `-2` open.

- Library: `-1`, `-2` open.

- Podcast: `-1`, `-2` open.

- Recruitment: `-1`, `-2` open.

- Restaurant: `-1`, `-2` open.

- Theatre: `-1`, `-2` open.

- Veterinary: `-1`, `-2` open.

- Gallery: `-1`, `-2` open.

- Skiresort: `-1` #8050 done; it needs no `-2` (its plan built the pages).

- Wedding: `-1` #8167, `-2` #8196 done.

- Photo: `-1` #8232, `-2` #8255 done.

- School: `-1` #8233, `-2` #8257 done.

- Architecture: `-1` #8264, `-2` #8280 done.

- Campsite: `-1` #8269, `-2` #8301 done.

- Cityhall: `-1` #8291, `-2` #8308 done.

- Construction: `-1` #8292, `-2` #8324 done.

- Museum: `-1` #8306, `-2` #8326 done.

- Hotel: `-1` #8325, `-2` #8336 done.

- Music: `-1` #8338, `-2` #8347 done.

- Coworking: `-1` #8113, `-2` #8127 done.

- Drivingschool: `-1` #8117, `-2` #8138 done.

- Ferry: `-1` #8123, `-2` #8142 done.

- Sportsclub: `-1` #8140, `-2` #8175 done.

- Pharmacy: `-1` #8160, `-2` #8181 done.

- Marina: `-1` #8159, `-2` #8185 done.

- Appstudio: `-1`+`-2` #8642.

## Who claims what?
- Any worker, in the order above, one step per PR; a step whose template still has an open PR on its files waits.
