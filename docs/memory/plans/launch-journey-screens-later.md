---
name: launch-journey-screens-later
description: Plan launch-journey, how its later screens are put together (SJ4, SJ5): the First week and Start here cards, the hint line, the agency summary drawer and the customer's page
---

## How are the First week and Start here cards laid out? (SJ4)
- First week [Cards]: four rows [Rows and gathered rows] (first order or form entry, visitors, first mail delivered, alerts), a pill each: Waiting, Seen, Problem; "Day 3 of 7" in the head.
- Start here [Cards]: three to five rows by role, each a link to its screen and a line of what it is for; "Hide" in the card menu.

## How is a hint line drawn? (SJ4)
- Under a screen's title: `.alert.theme-info.small`, one line, with a quiet "Don't show again" (architect, 2026-11-20, agent channel message 11146). Only on a screen the journey's catalogue names.

## How is the agency summary laid out? (SJ5)
- The drawer from the card menu: "What we need from you" lines [Rows and gathered rows], "Add a line", each with its remove; the link with Copy and "Withdraw link" (danger, confirm); the expiry [Fields].
- The customer's page: the menu-less shell (`output_header()`'s `hide_menu`), not the site's theme (the site may not be live yet). One `.pg-card` of gathered rows: the site's name, the "Done" steps with a check, then each line with its answer as a textarea and one fill "Send".

## What must be true?
- The First week card is drawn only in the seven days after the go-live time.
- A hint line has one "Don't show again" and is not drawn again once marked.
- The customer's page has no panel menu and shows no setting's value, only the lines and the steps' states.

Related: docs/memory/plans/launch-journey-screens.md, docs/memory/plans/launch-journey-steps.md
