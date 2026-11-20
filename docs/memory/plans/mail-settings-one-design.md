---
name: mail-settings-one-design
description: Plan mail-settings-one, the architect's design (picture mail-settings-one/email-settings.jpg): the two tabs, how e-mail leaves, the ways sheet, Mailboxes with no box
---

## Where is the picture?
- `plans/mail-settings-one/email-settings.jpg`, drawn from `email-settings.html` with the look A sheet kit (`plans/panel-design-language/sheet.css`, `boards.css`); boards A to E. Adding and removing a box (D, E): `mail-settings-one-design-box.md`.
- Asked of the owner as a picture question in the agent channel (2026-11-21); a step that builds one of these parts waits for his yes.

## (A) How are the category's two tabs drawn?
- Under the pane's head and purpose line: one segmented control [Segmented control and status], taller than a drawer's (34 px items, the label in the strong weight), each item its label, then a state pill. `role="tablist"`; the tab is kept in the address.
- Sending's pill: "Ready" green, "Check the domain" amber, "Not set up" grey. Mailboxes': "n reading" green, "Sign-in needed" amber, "No box" grey.
- A user with the right to one tab sees that pane and no control.

## (A) How is "How e-mail leaves" drawn?
- The first folded row of Sending, open when the category opens. Inside, one inner card with a row a kind of mail: a tile, the kind ("System e-mail", "Campaigns"), the way in one sentence ("Leaves through the Google account info@…"), "Change" quiet small.
- One meta line under the card says what each kind is: system e-mail is order, form, password and notice mail; campaigns are bulk sends.
- The sentence says what happens today on that site, whatever layer decides it; no paragraph explains an order of layers.

## (B) How is Change drawn?
- A sheet (a drawer; a bottom sheet under 576 px). Head: the kind's tile, "How should system e-mail leave?", one line naming the mail it covers.
- The ways as radio cards, the starting-point cards recipe (`.pg-start`, DX2), two a row, one under 576 px: "A Google account", "A Microsoft account", "A sending service", "Your own mail server". For campaigns a fifth comes first: "The same way as system e-mail".
- Under the cards a group label and only the chosen way's fields. A Google way: the account select, where an account connected for a mailbox is listed with "Connected for a mailbox" at the row's end, one line of help ("One account reads and sends; no second connection"), and a quiet "Connect another Google account".
- Foot: Save fill, Cancel quiet.

## (C) How is Mailboxes drawn with no box?
- The pane holds the list's empty state [A list's empty state]: a 44 px tile, "No mailbox yet", one sentence on what a box does ("Incoming e-mail lands in the channel you choose; the team answers there"), the fill "Add a mailbox", and one meta line: "Gmail and Microsoft 365 sign in with one click."
- The other folded rows of the pane (accounts, rules, blocked senders) show under it only once a box exists.

## What must be true?
- No part here adds a button rank, a pill tone or a card of its own: each is built from the recipes named, and the taller segmented control is one modifier of `.pg-seg`.
- At 390 px: the tabs keep one line (the pills may drop to a dot with the word as `title`), the ways are one a row, the sheet comes from the bottom.

Related: docs/memory/plans/mail-settings-one-design-box.md, docs/memory/plans/mail-settings-one.md
