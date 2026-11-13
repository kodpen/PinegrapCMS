---
name: designer-blocks-conference
description: The Conference block family (conference- prefix): opening hero with a badge on its lanyard, day agenda with parallel sessions, speaker line-up as a poster; blocks placed instead; tests
---

## Where does it live?
- `assets/js/style_designer_blocks_conference.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `conference-`, palette group "Conference" (`bi-mic`). Built in `events-1` for [[design-template-events]]; `events-2` places it. `events-` is a core prefix (`events-list`, `events-cards`), hence `conference-`.

## Which blocks does it hold?
- `conference-hero` (Conference: Opening Hero): a dark band (`pg-conference-hero`), where and when, the `h1` in `pg-display-xl fw-bold`, two buttons, the early registration note, four figures that count up (`_fx` count). Beside it an attendee's badge (`pg-conference-lanyard`, a light card, `h2` Conference Pass, a hidden barcode) that drops in on load.
- `conference-agenda` (Conference: Day Agenda): on `bg-body-tertiary`, `h2` the day's theme, track pills, two buttons, then an `ol` of seven slots (`pg-conference-slot`, `<time>`): breaks as quiet lines, the keynote across the width, parallel sessions side by side from md up (`h3` each with track, speaker, kind, room). The slots rise one by one.
- `conference-lineup` (Conference: Speaker Line-up): `h2`, then a row per group (`pg-conference-group`, `h3` Keynotes and the four tracks), the names as links in the heading font, the keynotes giant; a slash in the theme's colour after each name but the last; two buttons. The groups rise one by one.

## Why are these not reused blocks?
- No hero draws a badge beside giant type. `booking-week` is a week in day columns, `timeline-*` one line of steps; none holds parallel sessions with a track each. `team-*` are photo cards, not a typographic line-up.

## Which general blocks does a conference site place instead?
- Passes `pricing-tiers`; venue and hotels `locations-spaces`, `locations-cards`; sponsors `logos-grid`; profiles `team-profiles`; meetups `events-cards`, `events-list`; words `testimonial-grid`; `faq-accordion`, `booking-cta`, `newsletter-split`.

## How are the drawings made?
- The lanyard's strap is the wrapper's `::before` in `--bs-primary`: 4.5rem on a phone, from lg up `100vh` tall so it reaches the band's top edge, which `overflow-hidden` clips (on a phone it would cross the words above the badge). The clip is the badge's `::before`.
- The badge hangs at -4deg from md up, around its clip, and straightens on hover; no transition under reduced motion.
- The glow: two `radial-gradient`s of `color-mix()` with `--bs-primary`, behind `@supports`.
- The line-up's slash is `::after` with `content: "/" / ""`, so a screen reader skips it and a wrapped row ends on it.

## Which colours does small text take?
- Eyebrows and details `text-body-secondary`; times `--bs-emphasis-color`, a break's `--bs-secondary-color`. The theme's colour stays on icons, the keynote's bar, the strap and the slashes ([[designer-block-theme-colours]]).
- Track pills are Bootstrap tints (`bg-success-subtle text-success-emphasis`…) with the track's icon and name, so the colour never says the track alone.
- Second buttons: `btn border link-body-emphasis pg-conference-second` (focus ring colour).

## Translation choices
- Tracks are "oturum dizisi" as in the template; Keynotes "Ana konuşmalar", line-up "Konuşmacı kadrosu", badge "Katılımcı Yaka Kartı". The sample reuses the template's programme and speakers.

## How is it tested and was it seen?
- `tests/designer_blocks_conference_test.php` (under Node): shared checks, words, headings, interactions, the day's order, nine track pills, seven slashes, rules.
- Drawn in headless Chromium with six looks and palettes, Turkish and English, light and dark, 320 to 1,440 px: no sideways scroll and no text under 4.5:1 (3:1 large).

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-overview.md
