---
name: privacy-settings-tidy-screens
description: Plan privacy-settings-tidy, its screen: the Visitors and privacy pane as folded rows, the "How long we keep data" row of value rows, the words and what must be true
---

## By which rules?
- `ui/screen-composition-rules.md`; parts copied from the Design examples screen, its section in brackets (`workflow/screen-described-in-plan.md`). "Value rows" comes with DX2b (`plans/design-examples-patterns-mail.md`) before VP2; the rest is there today. No part is new: nothing to ask the architect for a drawing.

## How is the pane laid out?
- Head: "Visitors and privacy" and one purpose line [Type and text]: what the site records of visitors, how long it keeps personal data, what it asks them.
- Folded rows [Settings pane], one open, each line saying its state, in this order: "Visitor tracking", "How long we keep data", "Cookie consent", "Legal documents", "KVKK notice", "Accessibility statement", "Privacy centre".
- Visitor tracking: the switch "Record visits" [Fields], under it "Remember a tracking code for" (days; a link's code is kept in a cookie so a later order counts for it) and "Paid tracking code mark" (a code holding this text counts as paid), always drawn; Google Analytics as [Connect row]: its pill, its sentence, "Manage in Connections" or "Connect in Connections".
- How long we keep data (VP2): "Keep visitor records for", "Remove IP addresses after" and the daily summary switch with its Database Engine link [Fields]; under them one inner card [Value rows], a row a period set elsewhere: Audit trail lines, Consent history, Firewall log, API log, Cancelled bookings, and the Privacy centre's Site chats, Passive contacts, Form submissions, Incomplete carts, Orders, Closed requests.
- A value row: tile, name, one sentence with today's value ("Kept 2 years", "Off: kept until deleted"), "Change" quiet opening its card (`#settings/<category>/<card id>`, the rules on `privacy_centre.php`). Consent history and Cancelled bookings (400 days, `commerce/booking-retention.md`) have no Change: fixed. Site chats: "Kept until deleted" until VP3. One meta line under the card: erasures and rule runs stay at least 3 years.
- Cookie consent: the switch, Cookie Policy Address, the help on held scripts and Google tags under a `details` "Your own scripts".
- Legal documents: the switch and its help, the two templates under their labels, "Fields you can use in a template" under a `details`; all drawn while off.
- KVKK notice: "Show the KVKK notice" [Fields, switch] (VP3), KVKK Notice Page [Fields, picker], Explicit Consent Sentence; until VP3 the row's line ends "used while Legal documents is on".
- Accessibility statement: the fields as today, "Print the statement" quiet; the line gives the statement's date or "Not filled".
- Privacy centre: the line is its sentence; "Open the Privacy centre" quiet.
- Under 576 px: rows full width, the value rows as DX2b draws them. Foot: Save the fill, one only.

## Which words?
- "Visitor tracking", "Record visits", "How long we keep data", "Change", "Kept until deleted", "KVKK notice", "Show the KVKK notice", "Your own scripts"; a row's name never starts with "Enable"; never "Pay Per Click", "Tracking Code Duration", "Log Retention" alone or a constant's name.

## What must be true?
- One look at "How long we keep data" shows every period the site applies and where each is set.
- Every field posts under today's name; a Save stores what it stored.
- `#settings/privacy/pgset-analytics`, `…/pgset-cookies`, `…/pgset-legal`, `…/pgset-a11y-statement`, `…/pgset-privacy-centre` open their rows; `visitor_retention_days` opens "How long we keep data".
- With Legal documents off, the templates and the KVKK fields are drawn; no popover is left, nothing more than one fold deep.
- The settings search finds "retention", "IP address" and "KVKK".
- The Privacy centre's inventory names the visitor records, the firewall log and the API log with their periods.

Related: docs/memory/plans/privacy-settings-tidy.md, docs/memory/plans/privacy-settings-tidy-steps.md
