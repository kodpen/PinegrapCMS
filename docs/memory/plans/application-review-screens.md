---
name: application-review-screens
description: Plan application-review, how the programme's screens are put together (AR2, AR3): the programmes list, a programme's screen in tabs, Set-up as folded rows, the Reviewers tab
---

## Which screens, and by which rules?
- AR2: Forms › Application programmes (list), a programme's screen. AR3: its Reviewers tab. Reviewing, the juror's page, ranking and decisions: `application-review-screens-review.md`.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, the section named in brackets (`workflow/screen-described-in-plan.md`).

## How is the list laid out?
- A list on the list table driver: name, form, opens and closes, a pill [status] (Draft, Open, Closed), entries, round.
- One fill, "Add programme" [buttons], which asks only the name and the form in a small drawer and opens the programme as a draft. Row actions quiet small: Open; Delete in the row's menu, danger, only for a draft with no entries.

## How is a programme's screen laid out?
- Head: the name, the pill, one fill that follows the state: "Open for entries", "Close", then the round's own. Under it tabs [segmented control]: "Set-up", "Reviewers" (AR3), "Entries" (AR5), "Decisions" (AR6), "Report" (AR7). A tab shows from the step that builds it.
- Set-up is folded rows, one open at a time (the settings pane of `plans/design-examples-patterns.md` DX1; before it lands, cards [cards]), each with one line saying what is set:
  1. "Period": opens, closes, the text shown outside the period.
  2. "Categories": the form's choice field.
  3. "Eligibility": a rule a row (field, condition, value), "Add a rule", each with its remove.
  4. "Rounds and criteria": a round a card; inside it criteria rows (name, weight) with the weights' sum shown and amber until it is 100; "Copy from a template", "Save as a template"; reviewers per entry, the round's end.
  5. "Blind review": the switch, the fields hidden beyond the fixed ones, one line: files show as uploaded.
  6. "Decision e-mails": Accepted, Rejected, Waitlisted as tabs, each its subject and text.
- Foot: Save as the fill, Cancel quiet. A row whose values are missing carries a "Not set" pill, and "Open for entries" lists what is missing.

## How is the Reviewers tab laid out? (AR3)
- People as rows [icon tiles and people]: name, panel user or "Outside juror", categories, assigned and done. "Add reviewer" as the fill opens a drawer: a panel user, or name and e-mail. Row menu: "Copy the link" and "Send the link again" (outside juror), Remove (danger).
- "Give out entries" is a quiet button with a confirm that says how many entries go to how many reviewers; conflicts are gathered rows under the list.

## Which words?
- One line of help a row or card. On screen: "Programme", "Entry", "Reviewer", "Round", "Corrected mean"; never "leniency", "token" or a column's name.

Related: docs/memory/plans/application-review.md, docs/memory/plans/application-review-steps.md, docs/memory/plans/application-review-screens-review.md
