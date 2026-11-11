---
name: designer-blocks-gym
description: The Gym block family (gym- prefix, own palette group): free pass hero, class finder with an intensity meter; the weekly timetable as a general block; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_gym.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `gym-`, palette group "Gym" (`bi-lightning-charge`). Built for the Gym template's polishing round ([[design-template-gym]], `plans/design-template-own-looks-order.md`, `gym-1`); a fitness, boxing or Pilates studio places the same blocks.

## Which blocks does it hold?
- `gym-hero`: the way in. A badge with today's hours (icon, "Open today", two `<time>` elements), the page's `h1`, a line, Get a Free Pass / Class Timetable; beside it a 4:5 picture (`pg-gym-photo`) with the trial pass card (`pg-gym-pass`: a ticket with a dashed edge, what the week holds as a tick list, "Nothing is paid now.") over its lower edge from md up, under it below md. On load the words rise, the picture fades (delay 200), the pass rises (delay 500).
- `gym-classes`: the class finder. Header with focus links (`nav nav-pills`, one per focus; the pages point them at `?focus=`), then an `ul` of three class `article` cards: icon, focus, `h3` name, a line, length and level, the intensity meter (`pg-gym-meter`: five rising steps, the reached ones `pg-gym-on`, `role="img"` with "Intensity N of 5" as its name and the same words beside it), Book this class. Header fades, cards rise (stagger 120) on view.
- Sample texts are the template's own (its classes, focuses, levels, plan points); no year or month.

## Why are these not reused blocks?
- The hero is not `hero-split` / `showcase-floating`: it carries the hours today and a trial pass the visitor asks for, the gym's way in. The class finder is not `services-icon-cards` / `features-grid`: each class carries its facts and an intensity scale to compare hard and easy classes.

## Which general blocks does a gym site place instead?
- Weekly timetable: `booking-week` (added with this step, [[designer-blocks-general]]). Plans: `pricing-tiers` / `pricing-toggle`. Coaches: `team-profiles`. Facilities: `features-grid` / `bento-features`. First visit: `steps-numbered`. Opening hours: `hours-card`. Figures: `stats-icons`. Members' words: `testimonial-grid`. Join call: `booking-split` / `cta-inline`. Questions: `faq-categorized`. Trainers stay a collection list widget.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_gym_test.php` runs it through `tests/helpers/designer_family_run.php`: own group, a section per block with one top heading and no skipped level, no fixed colour or `pgy-`/`gy-` class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; the hours badge's two times, the pass, three classes with a meter each and the steps reached; the meter's name and the `<time>` read from the file; the rules through `_pg_designer_block_css_parse()`, and `gym-hero` shipping both rules (its needs list the pass before the frame, whose rule names `.pg-gym-pass`; fixed in #8655, [[designer-block-style]]). `designer_blocks_booking_test.php` covers `booking-week`.
- The Gym template places these blocks on its home, classes and timetable: [[design-template-gym-home]].

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
