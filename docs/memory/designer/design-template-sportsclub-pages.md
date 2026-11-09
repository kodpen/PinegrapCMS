---
name: design-template-sportsclub-pages
description: Sports Club template 2.0.0: home, fixtures, results and teams on its palette blocks (match day, league table, squad by position), blocks.php, motion, tests
---

## How are home, fixtures, results and teams built?
- On the Sports Club palette blocks ([[designer-blocks-sportsclub]], step `sportsclub-2` of `plans/design-template-own-looks-order.md`, version 2.0.0). `pinegrap-sportsclub/blocks.php`, included right after `parts.php`, builds them with the blocks' structure and `pg-sc-` classes from the club's data; the rules ship through `pg_design_template_user_styles(array('sportsclub-matchday', 'sportsclub-table', 'sportsclub-squad'), …)`. Motion is `props._fx` (`$fx`), still under reduced motion.
- `$sc_matchday($head, $buttons, $trigger, $id, $topics)`: dark band (`data-bs-theme="dark"`), `$head` first (the home's h1 and tagline, the fixtures page's "Match day" h2), the competition badge, the first team's `next_first` match as two crests (ours `bi-shield-fill`, theirs `bi-shield`, home side first) with the day word and the kick-off `<time>`, the ground line, `$buttons`; beside it the `last_first` result card (score, outcome pill as icon and word from `$outcomes`, report, all results).
- `$sc_table()`: the league table of the club and five of `$opponents` (sample figures to keep up to date), own row `table-primary` + `aria-current`, promotion and relegation rows, the key in words; a search section `sp-league-table`.
- `$sc_squad($team)`: the team's `$players` under Goalkeepers, Defenders, Midfielders, Forwards, each card led by its number; the name opens `squad?team=`.

## Where does each page place them?
- Home: the match day as the way in (text, crests and buttons slide up on load, the result card fades in), the fixtures and results widgets (rise on view), the league table, the team cards (rise on view), club events, sponsors, voices with the Google reviews, news. The old hero (`psp-hero`, rule removed), the figure row and the academy story band left the home; `$figure_row` and `$story_band` stay for the club and academy pages.
- Fixtures: the match day band (on view) above the list. Results: the league table under the list. Teams: the senior and academy rows rise on view, then the first team's squad by position (`sp-first-squad`), then how to join.
- Team pages, club, ground, academy, sponsors and faq are unchanged.

## How is it tested?
- `test_sportsclub_assembly_signature_pages_on_blocks`: every `pg-` class's rule ships, interactions kept by `pg_fx_normalize()`, the home's motion, two crests, the dark band and the kick-off time on home and fixtures, no figure row or story band on the home, the own row on results, four shirt numbers on teams. Every test that includes `matches.php` or `pages_public.php` includes `blocks.php` after `parts.php`.

Related: docs/memory/designer/design-template-sportsclub.md
