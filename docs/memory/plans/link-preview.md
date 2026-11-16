---
name: link-preview
description: Plan (owner 2026-11-15, agent channel poll 54): a card under a message's web address with the page's title, description and site name, read once by the server; steps LP1 to LP3
priority: 6
---

## What is asked?
- The R&D agent's proposal is agent channel message 5356. The owner chose it in poll 54 (message 5366) as it was put to him: a card with title and description under a message's web address; the server reads the address once; it can be switched off in the settings; a small table (`workflow/decisions-made-workspace-35.md`, `workflow/decisions-made-schema-22.md`).
- Today only the site's own records have tags. Parts at hand: the address check of scheduled web calls (`ws_scheduled_host()`), the reader of what a page says about itself (`ws_note_html_meta()`).

## Which steps are there?
- LP1 (M) The record, the reader and the switch. Done: #9843.
  - A migration step adds one small table, one row per message whose first web address was read: the message, the address, title, description, site name, a state, when. Its columns and indexes go into the draft PR's body for the architect's confirmation before the step is written.
  - The general job, never the request, reads a new message's first http or https address once. Refused: an address inside the network (the resolved address is the one called, every redirect checked again, few redirects), anything but HTML, a body or a wait over the limits. An hourly cap per site. Texts are cut, kept plain and escaped where drawn.
  - A link to the site's own page takes its card from the site's data with no call. An edited message whose first address changed is read again; a deleted one loses its row.
  - A switch in Workspace Settings, on by default: off, nothing is read and no card is drawn. A demonstration site reads nothing. Pure functions with tests: the first address of a text (not in code, not a record tag), the address check, the cut of the page's fields.
- LP2 (M) after LP1: the card. Site name, title and a description cut at two lines under the message, the whole card a link in a new tab; it arrives live once read. The writer's "Remove preview" in the message's menu. No picture.
- LP3 (S) after LP2: the card's fields on a message read over the API; close-out: `workspace/workspace-link-preview.md`, this plan deleted.

## What is left out?
- The page's picture (fetched by the reader's browser it gives the reader's address to that site; a small copy kept on the site is a step of its own); more than one card on a message; video players. Each is a later yes of the owner.

## Whose look?
- LP2's card is the owner's: Workspace is a kept screen and the card is new on it. LP2 posts close-up pairs in the agent channel (a card, a long title, a message with no card; 1440 and 390, light and dark), the architect brings them to him, and the step is not marked ready before his look.
- LP1's switch is the architect's look. The architect's readings, not his words: the first address only, the table keyed by message, the default on.
