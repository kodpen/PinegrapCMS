---
name: launch-journey-screens
description: Plan launch-journey, how its screens are put together (LJ2 to LJ5): the set-up card, the purpose choice, the go-live check, the first week and team cards, hint lines, the agency summary
---

## Which screens, and by which rules?
- LJ2: the dashboard's set-up card and the purpose choice. LJ3: the go-live check. LJ4: the First week and Start here cards, hint lines. LJ5: the summary drawer and the customer's page.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, its section named in brackets (`workflow/screen-described-in-plan.md`); a part it lacks is asked of the architect, never made.

## How is the set-up card laid out? (LJ2)
- A dashboard card [Cards]: head "Set up your site", "5 of 9 done", a pill [Segmented control and status]: Blocked, In progress, Ready.
- Steps as rows [Rows and gathered rows], blockers first: an icon tile [Icon tiles and people], the step's name, one line of state ("SPF found, DKIM missing"), "Do it" quiet [Buttons], or a check when done. A row's menu: "Not for this site" (not on a blocker).
- Foot: "Ready to go live?" the fill. Card menu: "Change purpose", "Agency summary", "Hide set-up".

## How is the purpose chosen? (LJ2)
- A dialog on the first dashboard: "What is this site for?", eight choices as large tiles with an icon and a line each, "Choose" the fill, "Later" quiet.
- Large choice tiles are not on the Design examples screen: asked of the architect (the walk-in-queue kiosk asks the same part).

## How is the go-live check laid out? (LJ3)
- A dialog: two gathered groups [Rows and gathered rows], "Must be fixed" and "Worth a look", a row each with its line and "Fix" quiet. Empty groups say "Nothing to fix".
- Foot: "Go live" the fill, Cancel quiet. With a blocker left, "Go live" asks again, naming each blocker, with a checkbox "I know, go live anyway" [Fields].

## How are the First week and Start here cards laid out? (LJ4)
- First week [Cards]: four rows [Rows and gathered rows] (first order or form entry, visitors, first mail delivered, alerts), a pill each: Waiting, Seen, Problem; "Day 3 of 7" in the head.
- Start here [Cards]: three to five rows by role, each a link to its screen and a line of what it is for; "Hide" in the card menu.
- A hint line under a screen's title [Type and text] with "Don't show again" as a link button [Buttons]; a callout part, if the Design examples screen has none, asked of the architect.

## How is the agency summary laid out? (LJ5)
- The drawer from the card menu: "What we need from you" lines [Rows and gathered rows], "Add a line", each with its remove; the link with Copy and "Withdraw link" (danger, confirm); the expiry [Fields].
- The customer's page, no panel menu: the site's name, "Done" steps as rows with a check, then each line with its answer box and "Send" [Fields]. Its look (the panel's guest look or the site's) is asked of the architect.

## Which words?
- "Set up your site", "Do it", "Not for this site", "Ready to go live?", "Must be fixed", "Worth a look", "Go live", "First week", "Start here", "What we need from you"; never "check id", "fact" or a setting's constant.

Related: docs/memory/plans/launch-journey.md, docs/memory/plans/launch-journey-steps.md
