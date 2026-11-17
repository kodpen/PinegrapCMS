---
name: message-parts-narrow-column
description: Every part of a Workspace message follows the width of its own column (the 320 px discussion panel, a phone): wrapping, minimum widths, the ws-poll container (#10030)
---

## What is the rule?
- Files: `assets/css/backend.src.css`, `assets/js/workspace.js`, `assets/js/workspace_page_card.js`.
- A part of a message (head, card, poll, file, player, list) follows the width of the column it stands in, by wrapping or a container query, never by the window's width alone (`workflow/look-narrowest-column.md`).
- The narrowest column is the discussion panel (`.ws-thread-panel`) on a wide window: 320 px (`THREAD_MIN` in `makeThreadView()`, kept in the `ws-thread-w` localStorage key), narrower than a phone (390). A `@media (max-width: 575.98px)` rule never reaches it.

## What does each part do in a narrow column? (#10030)
- Message head (`.ws-msg-meta`): wraps; the name keeps a line of its own and wraps between words only when it alone is wider; a mark (`.badge`: via, MCP agent) is cut with an ellipsis, the via mark's whole text in its title.
- File card (`.ws-file-card`): the name keeps at least 8rem (`flex: 1 1 8rem`), the buttons (`.ws-file-tools`) wrap as one group: all beside the name, or all under it at the right; the name's title is the whole file name.
- Voice player (`.ws-voice`): the name and time keep at least 7rem; speed, answer, "Aa" and download (`.ws-voice-tools`) wrap as one group at its right.
- Architect (2026-11-17, agent channel message 8533): a card's buttons wrap as one group, never one alone on a line, at every width from 320 to 390.
- Poll and agent question: `.ws-poll` is a named container (`container: ws-poll / inline-size`); `@container ws-poll (max-width: 30rem)` repeats the phone layout (counts under the option, the foot's plain buttons by their icon, a picture beside the options above them) and "Write your own answer" takes a line over Send.
- The `rows` shape in that narrow box (#10222): a row's own thumbnail (`.ws-poll-thumb`, 4.5rem) stays on the tick's line and the option's words take the whole width under it (`.ws-poll-thumb + .ws-poll-text`, `flex-basis: 100%`), so they read in lines of several words; the agent question's rows the same (#10222). Beside the text it left them about 90 px at 320 px. An option's long word wraps (`overflow-wrap: break-word`, not `anywhere`: that splits every word in a narrow split column).
- Canvas card head: the name keeps the icon's row and 8rem (`flex: 1 1 8rem`), the part chips wrap under it.
- Titles for cut text: the "/" and "@" lists' label, example and meta (`pickerText()` in `drawPicker()`), the discussion head's channel line, the upload toast's file name, a media bar's name, a page card's name.

## What was left as it is, and why?
- The composer toolbar, hover bar, its menu, reactions, task card, approval card, change card, chips, link card, mail card, checklist, highlight and its bar read in 320 and 390 as they are.
- The agent state line is #10029, fixed apart.
- Bug issue for what needs other words: a reply quote cut without its whole text as title, which is "go to the message" (#10223).

## Gotchas?
- A `@container` block written before a part's base rule loses to it on source order: `.ws-poll .ws-poll-split` carries the extra class for that.
- `container-type` gives inline-size containment: put it only on a box whose width comes from its parent (a block with `max-width`), never on a shrink-to-fit box, which would collapse to zero.
- `tests/workspace_narrow_column_test.php` reads the rules from the stylesheet.
