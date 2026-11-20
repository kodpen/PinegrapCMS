---
name: feedback-loop-screens
description: Plan feedback-loop, how its panel screens are put together (FD2): the Surveys tab's list, starting points, a survey's edit screen in cards; each part's Design examples section
---

## Which screens, and by which rules?
- FD2: Communication › Surveys (list), Add and Edit survey, the test send. The answer page (FD4) and the results (FD6): `feedback-loop-screens-results.md`.
- `ui/screen-composition-rules.md`, `ui/messages-area-rules.md` for the tab; parts are copied from the Design examples screen, the section named in brackets (`workflow/screen-described-in-plan.md`).

## How is the Surveys tab laid out?
- The area's tab strip gains "Surveys". From FD6 link segments [segmented control] sit under it: "Surveys", "Results".
- A list on the list table driver: name, kind (NPS, CSAT, Effort), "Sent after", channel, an On or Off pill [status]; from FD4 on, sent and answered in the last 30 days and the score.
- One fill, "Add survey" [buttons]. Row actions quiet small: Edit, "Send me a test". The row's menu: Delete (danger, with a confirm) while nothing was sent; after that "Archive", which takes the survey off the list and keeps its answers in the results (`archived_at`).
- Empty: one line on what a survey does and the Add button.

## How does a new survey start?
- "Add survey" opens starting points (the sheet of `plans/design-examples-patterns.md` DX2; before it lands, cards [cards]): After a booking, After an event, After a course, After a form, After a delivery, After a mail conversation, Every few months. A card fills the kind, when it is sent and the default texts; the edit screen opens with the survey off.

## How are Add and Edit laid out?
- The edit layout (`ui/edit-screen-wide-layout.md`): form column, status column, save bar. Cards [cards] in this order:
  1. "Question": name, kind as a segmented choice [segmented control], the question, the comment label. "Extra questions" folded, at most three rows, each with its remove. The texts per language are tabs here, only on a site with more than one.
  2. "Sent after": what it follows, then only that one's filter (service, event, course, form with its days or field value, mail box, the months of the periodic one), the delay.
  3. "Sent by": E-mail, SMS, WhatsApp as a segmented choice; "Service message" or "Commercial" with one line on what commercial checks; the periodic NPS shows Commercial, fixed.
  4. "Low scores": who follows up when the record has nobody, who joins after 48 hours, the Workspace channel.
  5. "After the answer": the review invitation switch; with no Google place set, one line and a link to its setting.
- Status column: the On switch with its pill, the e-mail's preview (question and score buttons, not clickable), "Send me a test" (quiet), Delete or Archive (danger) at its foot.

## Which words?
- One line of help a card. On screen: "Survey", "Score", "Low score", "Follow-up", "Sent after"; never "detractor", "touchpoint" or a column's name.

Related: docs/memory/plans/feedback-loop.md, docs/memory/plans/feedback-loop-steps.md, docs/memory/plans/feedback-loop-screens-results.md
