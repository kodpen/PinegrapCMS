---
name: workspace-link-preview-card
description: The link preview card under a Workspace message (#9900): payload fields, where messageNode draws it and where not, the live redraw, "Remove preview" for the writer only, what is left out
---

## How is the card drawn?
- `ws_message_payloads()` reads the page's cards in one query (`ws_link_preview_cards_map()` over `ws_link_preview_card_rows_map()`: only `shown` rows with a title and a web address, nothing while the switch is off; the API reads the same map) and gives each message `link_preview` (the card's HTML, escaped by `ws_link_preview_card_html()`) and `can_remove_preview`.
- `messageNode()` (workspace.js) puts it under the message's text as `.ws-link-card`: site name, title, a description cut at two lines, the whole card a link with `target="_blank" rel="noopener noreferrer"`; no picture. It never widens the list (max 32rem, words break). Drawn wherever `messageNode()` draws: the channel, a discussion, the Decisions tab. Not in search hits, the pinned bar, a message reference popup, the export or a guest's page: they show text only.
- Live: the job touches the message (`ws_message_touch()`) once it is read, so open screens redraw it with its card on their next sync; no new poll.

## Who may remove a card?
- Only the writer (`ws_link_preview_can_remove()`: a person's own message, not deleted, a card drawn, not in an earlier version of the conversation): "Remove preview" in the message's menu, after Edit. The panel action `ws_link_preview_remove` sets the row `removed` for everyone and touches the message; the same address is never read again, an edit to another one is.

## Whose look is it?
- The owner's: he saw the card (site name, title, two-line description, no picture; light, dark, 390) and said yes in the agent channel (messages 6338, 6402). Keep that look.

## What is left out?
- The page's picture (fetched by the reader's browser it would give the reader's address to that site; a small copy kept on the site is a step of its own), more than one card on a message, and video players. Each waits for a later yes of the owner.

Record, reader and switch: `workspace-link-preview.md`. Tests: `tests/workspace_link_preview_test.php`.
