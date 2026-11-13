---
name: design-template-events-pages
description: Events template 2.0.0: home and programme on the Conference blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0: home and `programme` place the Conference blocks ([[designer-blocks-conference]]); their rules ship through `pg_design_template_user_styles(array('conference-hero', 'conference-agenda', 'conference-lineup'), $events_css)`.
- In `pages_public.php`: `$fx`, `$fade_in` (fade on view, 700 ms), `$rise($node, $stagger = 90)` (slide-up on view), `$detail`, `$second_button` (`btn border link-body-emphasis pg-conference-second`), `$track_pill($key, $tag)` (Bootstrap tint, the track's icon and name).
- `$conference_hero()`: the page's only `h1` (the Full Pass's title), venue and figures from `parts.php`, the badge holder a sample name, "Passes from %s" with the Day Pass price ([[design-template-events]]).
- `$agenda_slots($day_key, $anchors)` groups a day's sessions by time: a lone keynote is the keynote card, a lone session with no track and no speaker a quiet break line, the rest a row of cards. With `$anchors` each slot keeps `session-<key>` (the speakers page links there) and each talk with a speaker is a search section.
- `$conference_lineup()`: keynote speakers from the programme's keynotes, then each track's speakers without them; names link to `speakers#speaker-<key>`, Propose a talk to `speakers#call-for-speakers`.

## What does each page hold?
- Home: the opening hero (dark band, badge drops in on load, figures count up), day one's agenda on `bg-body-tertiary` (theme as `h2`, the track legend, Register and See the programme; slots rise), the line-up, Coming Events (`home_events`, now on tertiary), testimonials rising with `google_reviews`, sponsors, `home_posts`, the register band. Without anchors, so no id repeats the programme's.
- Programme: the page header, the days' pills beside the track legend, then one tab pane per day: its theme as `h2` and the day's agenda rising. Each day is a search section.

## What left?
- The `pev-hero` band and its rules, the featured speaker cards, the track cards and the How it works steps (`$step` in `parts.php`). The speakers page keeps its profile cards.

## How is it tested?
- `test_events_pages_on_conference_blocks` (`tests/design_template_events_assembly_test.php`): version at least 2.0.0, the three blocks' rules ship, every wanted class, interactions kept by `pg_fx_normalize()`, one `h1`, motion on the home and no `session-` id there, every session id and searchable talk on the programme, a dark hero with a light badge, eight home slots with parallel sessions side by side, every speaker once in the line-up.

Related: docs/memory/designer/design-template-events.md, docs/memory/designer/design-template-own-look.md
