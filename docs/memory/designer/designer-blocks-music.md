---
name: designer-blocks-music
description: The Music block family (music- prefix, own palette group): release hero, tour dates, press kit; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_music.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `music-`, palette group "Music" (`bi-music-note-beamed`). Built for the Music template's polishing round ([[design-template-music]], `plans/design-template-own-looks-order.md`, `music-1`); any band, artist, choir or DJ places the same blocks.

## Which blocks does it hold?
- `music-release`: the newest record. A `pg-music-sleeve` with a square cover (`pg-music-cover`) and, from md up, a disc (`pg-music-disc`, `aria-hidden`, drawn with theme colours) behind it that slides out on load; beside it eyebrow "Out Now", the page's `h1`, format and song count, a line, Listen Now / Buy on Vinyl. Under them `h2` "Track Listing" and an `ol.pg-music-tracks`: number, title, length as a `time` with an ISO duration (`PT3M41S`); two columns from md up, tabular figures; the credits line. Sleeve fades, disc slides (delay 300), words rise, tracks fade on view.
- `music-tour`: tour dates. Header (eyebrow, `h2` "Tour Dates", a line, Book us for your city) above an `ol` of five shows, each a line: the day (`pg-music-day`: weekday large, "This week" / "In three weeks" small; no date or month), `h3` city and venue, the ticket state as a tinted pill with an icon and a word (on sale, few left, sold out), Tickets or, sold out, Waiting List. Header fades, shows rise (stagger 100) on view.
- `music-presskit`: for press and promoters. The one-sheet: eyebrow, `h2` "Press Kit", a short bio, a `dl.pg-music-sheet` (Genre, From, Line-up, Record Label, Latest Release; label and value side by side), Book the Band / Write to the Press Office; beside it `h3` Press Photos (three 4:3 photos, each caption with a `download` link) and `h3` Files (an `ul` of three: icon, `h4` name, kind, a `download` button). One-sheet fades, photos rise (stagger 120), files fade.
- Sample texts are the template's own (Night Bus Hymns and its tracks, the shows' cities and venues, the ticket states, the label line); no year or month.

## Why are these not reused blocks?
- The release is not `hero-split`: a sleeve with its disc and the track listing with lengths. The tour is not `events-list` / `events-cards`: no date card and description per event, but a dense line per show with its ticket state and the waiting list; `sportsclub-fixtures` and `ferry-departures` are a score and a timetable. The press kit is not `architecture-sheet`: the one-sheet sits beside downloadable press photos and files.

## Which general blocks does a music site place instead?
- Members: `team-grid` / `team-profiles`. Videos: `image-grid` / `bento-gallery`. Merch: `services-image-cards`. Press quotes and fans' words: `quote-pull` / `quote-spotlight` / `testimonial-grid`. Figures: `stats-counter`. Mailing list: `newsletter-split` / `newsletter-inline`. Booking call: `booking-cta` / `cta-inline`. Questions: `faq-accordion`. Releases, shows and posts stay collection list widgets.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_music_test.php` runs it through `tests/helpers/designer_family_run.php`: own group, a section per block with one top heading and no skipped level, no fixed colour or `pmu-`/`mu-` class, `{var}` filled, no year or month (abbreviations too), entrances kept by `_sdFxNormalize()`; the sleeve, disc and eleven tracks, five shows with icon-and-word ticket states, the one-sheet, three photos and three files; the ordered lists, durations, downloads and hidden disc read from the file; the rules through `_pg_designer_block_css_parse()`, each with a theme variable.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
