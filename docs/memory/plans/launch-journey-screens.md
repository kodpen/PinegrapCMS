---
name: launch-journey-screens
description: Plan launch-journey, how its first screens are put together (SJ2, SJ3): the set-up card on the dashboard, the purpose choice as radio cards, the go-live check
---

## Which screens, and by which rules?
- SJ2: the dashboard's set-up card and the purpose choice. SJ3: the go-live check. SJ4 and SJ5 (First week, Start here, hint lines, the agency summary): `launch-journey-screens-later.md`.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, its section named in brackets (`workflow/screen-described-in-plan.md`). The parts it lacks were answered by the architect (2026-11-20, agent channel message 11146).
- A screen step builds a whole part (a screen with its drawer), never structure first and content later. Its worker renders it at 1440 and 390 px before ready and writes "Screen check" in the PR.

## How is the set-up card laid out? (SJ2)
- A dashboard card [Cards]: head "Set up your site", "5 of 9 done", a pill [Segmented control and status]: Blocked, In progress, Ready.
- Steps as rows [Rows and gathered rows], blockers first: an icon tile [Icon tiles and people], the step's name, one line of state ("SPF found, DKIM missing"), "Do it" quiet [Buttons], or a check when done. A row's menu: "Not for this site" (not on a blocker).
- Foot: "Ready to go live?" the fill. Card menu: "Change purpose", "Agency summary", "Hide set-up".

## How is the purpose chosen? (SJ2)
- A dialog on the first dashboard: "What is this site for?", eight choices as the starting point cards of the New application sheet (`includes/api/app_new_view.php`, `api/application-access-new-sheet.md`): a radio card with `.pg-icon-tile.pg-icon-tile-lg`, a name and one line; two a row, one under 576 px.
- "Choose" the fill, "Later" quiet. A site installed from a template whose key the catalogue maps opens with that purpose picked.

## How is the go-live check laid out? (SJ3)
- A dialog: two gathered groups [Rows and gathered rows], "Must be fixed" and "Worth a look", a row each with its line and "Fix" quiet. Empty groups say "Nothing to fix".
- Foot: "Go live" the fill, Cancel quiet. With a blocker left, "Go live" asks again, naming each blocker, with a checkbox "I know, go live anyway" [Fields]. The confirmation names what "Go live" itself changes: "Maintenance mode turns off" and "Search engines may index the site", each only when it applies.

## Which words?
- "Set up your site", "Do it", "Not for this site", "Ready to go live?", "Must be fixed", "Worth a look", "Go live", "First week", "Start here", "What we need from you"; never "check id", "fact" or a setting's constant.

## What must be true?
- The set-up card lists blockers first, and no blocker row offers "Not for this site".
- The purpose dialog has eight radio cards, two a row, one under 576 px, and one fill.
- The go-live check has two groups and one fill; with a blocker left it asks again and names each.

Related: docs/memory/plans/launch-journey.md, docs/memory/plans/launch-journey-steps.md, docs/memory/plans/launch-journey-screens-later.md
