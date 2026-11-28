---
name: person-status-screens
description: Plan person-status, in words: the Status dialog, where a person opens it, the mark beside a name, the person card's line, the composer's line and the line under an agent question
---

## What is the Status dialog?
- A small modal titled "Status", the look's form modal, opened for the person's own status only.
- First a row of six choices as tiles with an icon of the look's set and the label (Available, Busy, In a meeting, Out, Sleeping, On leave), one chosen, a radio group; on a phone three across.
- "Note" under it: one text field, 80 characters with the count, placeholder "What should people know?".
- "Clear after": a select (Do not clear, 30 minutes, 1 hour, 4 hours, Today, Choose a time…); "Choose a time…" shows a date and time field (`pgDateField()`) under it. Choosing a kind sets its default until the person changes the select: Busy and In a meeting 1 hour, Out 4 hours, Sleeping the end of the person's quiet hours or else 08:00 the next morning, On leave today, Available none.
- Foot: "Clear status" on the left, shown only while one is set; Cancel and Save on the right. A refused field is said under it.

## Where is it opened?
- The panel top bar's user menu, a row above My Account: "Set a status", or the status itself (icon, label, "until 18:00") once one is set.
- The person's own card in a channel: the status line carries "Change"; with none, "Set a status".
- `/mystatus` (PS4) writes without the dialog.

## Where is a status shown?
- Beside a name, a 14 px icon in the kind's tone with a tooltip and a hidden label ("Out until 18:00: only following this channel"): a message's head, a member's row in Channel info (icon, label and end in words), a direct message's row in the side list, a row of the @ suggestions.
- Available with no note draws nothing beside a name; it is told only on the card.
- The person card: one line under the title: icon, label, the end ("until 18:00", "until tomorrow 08:00"), the note in quotes.
- The dot on the face stays the heartbeat and is drawn as today; the two never share a place.
- A status that ends while the screen is open goes with the next refresh of people; no new poll.

## What is the composer's line?
- While the words name a person with `@` whose status is not Available, and always in a direct message with such a person, one quiet line over the composer: "Erdal Güral is out until 18:00: only following this channel". Several people: one line each, at most two, then "and 2 more".
- It never stops the send.

## What is the line under an agent question?
- An open agent question whose asked people (`ws_agent_question_asked()`) all carry a status other than Available or are in their quiet hours gets one quiet line under its choices: "Erdal Güral is sleeping; an answer is expected after 08:00.". With no known end: "… is out.".
- It goes when one of them is back or the question is answered.

## What does PS2 check?
- The icon is never the only teller: label in the tooltip, the hidden text and the card.
- Words at 4.5:1 in both themes; the tiles reachable by the arrow keys as a radio group.
- No new component: a piece Design examples lacks is added there in the same step. Every text through `lang()` with `php tools/tr_add.php`.
