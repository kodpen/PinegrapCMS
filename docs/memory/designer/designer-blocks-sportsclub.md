---
name: designer-blocks-sportsclub
description: The Sports Club block family (sportsclub- prefix, its own palette group): match day, fixtures and results, league table, squad by position; the general blocks a club places instead; pg-sc-* rules; test
---

## Where does it live?
- `assets/js/style_designer_blocks_sportsclub.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `sportsclub-`, palette group "Sports Club" (`bi-trophy`). Built for the Sports Club template's polishing round ([[design-template-sportsclub]], step `sportsclub-1` of `plans/design-template-own-looks-order.md`); usable by any club or league side.

## Which blocks does it hold?
- `sportsclub-matchday`: a dark band (`data-bs-theme="dark"` + `bg-body text-body`): competition badge, the next match as two crests facing each other (`pg-sc-crest`, initials in a ring; a site swaps in its badge picture), the day in words and the kick-off as `<time datetime="15:00">`, the ground, "Buy tickets" / "Match preview"; beside it the last result card (score, result pill, report link). Its `h2` is visually hidden ("Mill Lane against Riverside Rovers"): the crests carry the look. Teams slide up on view (stagger 150), the result card fades in.
- `sportsclub-fixtures`: two columns, "Coming up" (an `ol`: day, `<time>`, opponent `h4`, competition, a Home match / Away match pill) and "Played" (opponent, competition and venue, score, result pill). Both lists rise on view (stagger 70).
- `sportsclub-table`: a league table in `table-responsive` (named region, hidden caption); short column heads (P, W, D, L, GD, Pts) with their full names visually hidden, `scope="col"`; the club name as `th scope="row"`; the club's own row `table-primary` with `aria-current="true"`; promotion and relegation rows marked by `pg-sc-up` / `pg-sc-down` (a 4 px inset line on the first cell) and a key below that names them in words (`pg-sc-mark`). The rows fade in on view.
- `sportsclub-squad`: a heading per position (goalkeepers, defenders, midfielders, forwards), a card per player: square picture (`pg-ratio-1x1`, core rule), shirt number (`pg-sc-number`), the name as a stretched link, a line (captain, top scorer). Each position's row rises on view (stagger 80).

## What does a club place instead of adding?
- Searched first (core, launch, skiresort, coworking, drivingschool, hours, locations, ferry). Training times are `hours-seasons` with a column per team; membership and season tickets `pricing-tiers`; the ground `features-alt` / `locations-cards`; sponsors `logos-grid` / `marquee-logos`; the academy path `steps-numbered`; honours `timeline-vertical`; club figures `stats-counter`; club events `events-cards`; coaches `team-grid`.
- The fixtures board is not `events-list` (a date and a title): a match has an opponent, home or away and a score. The squad is not `team-grid`: players are grouped by position and led by their shirt number.

## What do its rules and texts follow?
- Rules: `pg-sc-crest` (primary subtle ring), `pg-sc-score` (tabular figures, scores and the table), `pg-sc-up` / `pg-sc-down` (`--bs-success` / `--bs-danger`), `pg-sc-mark` (the key's swatch), `pg-sc-number`. Theme variables only, no `!important`.
- A result is an icon plus a word. Shared keys with a wrong Turkish value for this sector are not reused: "Won", "Lost", "Draw" (drawing), "Home" ("Ev"), "Away", "Position" ("Konum"), "Points" ("Köşe sayısı"); the family says "Win", "Drawn game", "Loss", "Home match" / "Away match", "Home side" / "Away side", "League position", "League points". Column letters are their own keys (G, B, M, AV, P in Turkish).
- No year or date: days in words ("Saturday", "Next Saturday", "In two weeks") and times of day only. Nothing moves by itself.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_sportsclub_test.php` runs the kit and the file under Node: a section with one `h2` and no skipped level per block, no fixed colour or `psp-`/`sp-` class, every `{var}` filled, no year, entrances kept by `_sdFxNormalize()`, the dark band, two crests and the kick-off time, the result pills, the table's scopes, own row and zones, a shirt number per player, the rules in the block stylesheet.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
