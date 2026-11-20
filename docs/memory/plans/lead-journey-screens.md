---
name: lead-journey-screens
description: Plan lead-journey, how its screens are put together (LJ2 to LJ5): the Journey card, the Workspace customer card, the Interest card and its settings, the Hot contacts view, the Lead sources report
---

## Which screens, and by which rules?
- LJ2: the journey on the contact screen and the Workspace customer card. LJ3: the score block, the settings. LJ4: the Hot contacts view. LJ5: the Lead sources report.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, the section named in brackets (`workflow/screen-described-in-plan.md`).

## How is the journey shown on the contact screen? (LJ2)
- A card "Journey" [cards] on the contact's screen, under the cards it has today. Head line: "First came from" with the channel as a pill [status], the campaign, the landing page.
- Under it gathered rows [rows and gathered rows], newest first, a day a group: an icon tile [icon tiles and people] by kind (visit, form, newsletter, campaign click, download, booking, order, course step), one sentence, the time. A visit is one row, "Visited 6 pages, 4 minutes", which opens to its pages as plain rows.
- Filters as segments above the rows: All, Visits, Forms and bookings, Orders. Twenty rows, then "Show earlier".
- No journey: one grey line saying why ("No visits are tied to this contact: marketing cookies were not accepted, or there was no visit since."). No empty card frame.
- A contact with a panel role shows no Journey card.

## How is it shown on the Workspace customer card? (LJ2)
- A folded row "Journey" under the card's history: the first-came-from line and the last five rows as on the contact screen, then "Open the contact" (quiet). No filters there.

## How is the score shown? (LJ3)
- On the contact screen's status column, a card "Interest": the behaviour score and the fit score as two figures [cards: figure] of 100, a "Hot" pill from the site's threshold on, under them "Why" as the top three reasons in plain rows ("Came back within 7 days", "Opened the pricing page"), then "Interested in" as up to five pills.
- The contacts list gains a "Score" column, off by default in its columns menu.

## How are the settings laid out? (LJ3)
- Site Settings › Contacts gains a folded row "Interest score" (the settings pane of `plans/design-examples-patterns.md` DX1; before it lands, a card [cards]): one line on what it does and that it follows the cookie notice's marketing yes.
- Open: "Hot from" (the threshold), then "What counts" as rows, a row an act with its points field (pricing or contact page, a return within 7 days, a form, a booking, a campaign click, a download); the pages that count as pricing and contact as two page pickers. Folded under "Advanced": the half-life in days, the keep period in months.
- Foot: Save fill, Cancel quiet, "Back to the defaults" quiet.

## How is the Hot contacts view laid out? (LJ4)
- A view of the contacts list, beside its other views, on the list table driver: contact, score, the first reason, last visit, assignee. Row actions quiet small: Open, "Remind me" (the follow-up). No fill of its own.
- Empty: "No contact is hot today" and a link to the threshold's setting.

## How is the Lead sources report laid out? (LJ5)
- A report screen (`ui/look-report-screens.md`): the tools row holds the period stepper and "First touch" or "Last touch" as a segmented choice [segmented control], "Excel" quiet at the right.
- Figures [cards: figure]: leads, customers, and on a commerce site revenue.
- A table frame grouped by a segmented choice: Channel, Campaign, Landing page. Columns: leads, customers, the share that became customers, revenue (commerce only).
- One line under the head says the figures count only visitors who accepted marketing cookies.

## Which words?
- One line of help a part. On screen: "Journey", "Interest", "Hot", "First came from", "Lead sources"; never "browser key", "attribution", "decay" or a column's name.

Related: docs/memory/plans/lead-journey.md, docs/memory/plans/lead-journey-steps.md
