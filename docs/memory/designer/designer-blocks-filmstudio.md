---
name: designer-blocks-filmstudio
description: The Film Studio block family (filmstudio- prefix, own palette group): showreel hero, title card with end credits, cuts for every screen drawn to scale; blocks placed instead; test
---

## Where does it live?
- `assets/js/style_designer_blocks_filmstudio.js` ([[designer-block-family-files]]); prefix `filmstudio-`, group "Film Studio" (`bi-film`). Built for `filmstudio-1` of `plans/design-template-own-looks-order.md` ([[design-template-filmstudio]], [[design-template-owner-line]]); any video production company places it.

## Which blocks does it hold?
- `filmstudio-reel`: dark band (`data-bs-theme="dark"`). Centred eyebrow, `h1`, line, Start a Project and See Our Work (`btn-secondary`: outline is too faint on a dark band). The screen (`pg-filmstudio-screen`, viewfinder corners): a slate (Showreel, `<time datetime="PT2M30S">`, "4K · 16:9") over a 16:9 `video` (`controls`, `preload="none"`, `playsinline`, poster, title; never `autoplay`).
- Under it "In this reel" (`h2`) and an `ol.pg-filmstudio-chapters` of five films: timecode `<time datetime="PT…S">`, `h3` name, kind. On load: words rise, screen grows, films rise one by one.
- `filmstudio-credits`: eyebrow "Featured film", `h2` title, line, facts (service, industry, `<time>` running time). A 2.39:1 still (`pg-filmstudio-scope`) at full width with the play mark (`kit.iconLink`, `btn-primary rounded-circle`), which grows on hover and focus. Then `h3` Credits, a `dl.pg-filmstudio-roll` (role right of the centre line, name left) and the client's words (`figure`/`blockquote`). On view the credits roll up (stagger 110).
- `filmstudio-formats`: `h2` "A cut for every screen and length", four formats: the frame drawn at its ratio (`pg-filmstudio-frame`, `aria-hidden`, `-r239`/`-r169`/`-r45`/`-r916`, one height), the ratio as `h3`, where it is shown, the lengths as badges; a note with Start a Project. Frames grow one by one on view.

## Why are these not reused blocks?
- The showreel is not `hero-split` or `photo-hero` (numbered stills): a player with its slate and its films at their timecodes.
- The title card is not `architecture-sheet` (facts as a `dl` beside a photograph, credits as a `ul`): a cinema-wide still with a play mark and end credits.
- The formats are not `features-grid` or `pricing-tiers`: frames compared by their drawn shape.

## Which blocks does a film studio site place instead?
- Services: `services-icon-cards`. Industries, why us: `features-grid`. Process: `steps-numbered` / `timeline-horizontal`. Spaces for hire: `locations-spaces`. Crew: `team-profiles`. Figures: `stats-counter`. Clients: `logos-grid`. Their words: `testimonial-grid`. Enquiry: `cta-inline`. Questions: `faq-accordion`. Projects and Journal stay list widgets. No general block was missing.

## What do its rules and texts follow?
- Each rule colours with a theme variable. Texts reuse the template's keys (hero line, projects, names, quote). The shared "Director" reads "Müze Müdürü", so the first credit is "Directed by" ("Yönetmen"). Ratios and "4K · 16:9" are plain text. No year or month.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family; `tests/designer_blocks_filmstudio_test.php` runs it under Node (`helpers/designer_family_run.php`): headings, no fixed colour or `pfm-` class, entrances, the dark band without outline buttons, five films, four frames, ten lengths; the player's attributes, `<time>`s, `ol`, `dl` and hidden drawings from the file; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
