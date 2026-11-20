---
name: feedback-loop-screens-results
description: Plan feedback-loop, how the answer page and the results are put together (FD4, FD6): the public answer page, the results report, comments and frequent words, the contact card's scores
---

## Which screens, and by which rules?
- FD4: the public answer page. FD6: the results, the contact card's scores. FD2's screens and the words: `feedback-loop-screens.md`.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, the section named in brackets.

## How is the answer page laid out? (FD4, site output)
- The site's own theme, not the panel look: the question, the scale as one row of buttons with the chosen one marked (0 to 10 in two rows on a phone), the comment, the extra questions, one "Send" button.
- After the send: a thank-you line, the review invitation as one button, "Change my answer" for 7 days. "Don't ask me again" is a quiet link at the foot of both states.

## How are the results laid out? (FD6)
- A report screen (`ui/look-report-screens.md`): the tools row holds the period stepper, the survey and what it follows as selects, "Excel" quiet at the right.
- Figures [cards: figure]: the score (NPS, or the CSAT or effort mean), answers, answer rate, open follow-ups. Under them a table frame grouped by a segmented choice: what it follows, service, staff member, course, event, form, month.
- "Comments": gathered rows [rows and gathered rows], each a score pill, the comment and a link to its record; filters Low, Middle, High. Beside it "Frequent words", plain rows with counts.
- A staff member's row under 5 answers reads "Fewer than 5 answers"; that grouping is not offered below manager.
- The contact card gains "Scores": plain rows of date, survey and score pill.

Related: docs/memory/plans/feedback-loop-screens.md, docs/memory/plans/feedback-loop-steps.md
