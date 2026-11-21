---
name: mail-settings-one-design-box
description: Plan mail-settings-one, the architect's design of adding and removing a mailbox (boards D, E of the picture): the three-step sheet, the provider sign-in buttons, the Remove dialog
---

## Where is the picture?
- Boards D1 to D3 and E of `plans/mail-settings-one/email-settings.jpg`; the other boards: `mail-settings-one-design.md`.

## (D) How is a mailbox added?
- One sheet, three steps on a step line (a number and a name; a finished step shows a green check): "Choose", "Sign in", "Details". There is no fourth "Done" step: Finish closes the sheet and the list shows the box with its "Reading" pill.
- Choose: the four starting points as radio cards (Gmail, Microsoft, Another server, No server), two a row; Continue fill, Cancel quiet.
- Sign in: an inner card, its content centred: the provider's own button and one line, "Pinegrap reads and answers this mailbox. Your password is not written to the site."
  - Another server: this step is the server's fields and "Test connection" instead.
  - While the provider's application is not set up: two radio cards, "Set up Google sign-in" (once, about 10 minutes; opens the numbered steps drawer of mailboxes-tidy MT3) and "Use an app password" (2 minutes; the password is written to the site).
- Details, after the return from the sign-in: a green result line ("info@… connected", "Connection tested: the inbox is read"), then "Which channel?" with the focus, Name and E-mail address filled, and "Other settings" folded (signature, automatic answer, routing).
- Foot: Back ghost at the left from the second step on, Cancel quiet, the step's fill last. Under 576 px the sheet comes from the bottom and the step line keeps the numbers, the names of the other steps hidden.

## (D2) Which provider buttons?
- Google: the "Sign in with Google" button of Google's brand guidelines, its light form in both panel themes: white fill, 1 px #747775 border, 4 px corners, 40 px high, the four-colour G unchanged at 18 px, the text #1F1F1F in Google Sans Medium, then Roboto, 14 px on a 20 px line; no web font is loaded.
- Microsoft: white fill, 1 px #8C8C8C border, square corners, 41 px high, the four-square logo at 21 px, the text #5E5E5E in Segoe UI Semibold 15 px.
- Neither takes a look A button class or tone; the logos are inline SVG, never redrawn or recoloured; the words are the provider's own wording in the panel's language.
- The brand rules are rechecked on the providers' pages by the step that builds the buttons, DX2b (`design-examples-patterns-mail.md`); EM5 copies them. A difference is asked of the architect.

## (E) How is Remove mailbox drawn?
- A dialog: a rose tile with the bin, "Remove the Support mailbox?", its address under the title.
- A list, a line an effect, each with one meta line where it helps:
  - what stays, a green check: "The conversations stay" (the channel and their count);
  - what goes, a rose cross: "Reading stops", "Its 3 rules are deleted", and "The stored password is deleted" when the box has one;
  - a blue info line when the box's account stays connected because sending uses it: "The Google account stays connected".
- Foot: Cancel quiet, "Remove mailbox" danger. The counts are read when the dialog opens.

## What must be true?
- With the provider's application set up, Add a mailbox › Gmail shows one sign-in button and no link to Connections.
- Remove never deletes a conversation, and its dialog lists every effect the removal has (a test compares the list with what the removal writes).

Related: docs/memory/plans/mail-settings-one-design.md, docs/memory/plans/mailboxes-tidy.md
