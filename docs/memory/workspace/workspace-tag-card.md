---
name: workspace-tag-card
description: A Workspace message that is one record tag and nothing else drawn as a card per reader (ws_tag_card_html(), plan step 9a): which bodies, what the card shows, rights, where it is drawn, what stays text
---

## Which messages get a card?
- A message whose whole body, spaces and blank lines around it aside, is one `#` tag (`<#order:7>`): any kind `ws_token_types_pattern()` knows (orders, products, accounts, quotes, tasks, plan items, channels, files...), except `form`, which keeps its own card (`ws_form_cards_html()`).
- Only whole messages, not a line inside a longer one: two tags, a tag with words, a tag in code, a list, a quote or bold stay text with the chip.
- Only messages of kind `message`, `decision` and `note` from a person or an application; a guest's text, an MCP agent's answer and a deleted message never.

## What does the card show, and to whom?
- `ws_tag_card_html($body, $refs)` (`includes/workspace/render.php`) builds it only from the tag as `ws_refs_resolve()` gave it to this reader: icon (with a channel's badge), label (a link where the tag has one), status as a pill, meta under it. That is the chip's own words and its hover title, nothing more.
- A tag the reader may not see (`known` false) or a record that is gone (`gone`) gets no card: the reader sees the chip exactly as before.
- No new query: `ws_message_payloads()` resolves the tags of all messages in one batch, and the card reuses those refs.

## Where is it drawn?
- `ws_message_payload()` (`messages.php`) puts the card in `html` instead of `ws_render_body()`, so every channel and thread list, the live updates and an edited message get it.
- The card head carries `data-ws-ref`; `chipMenus()` in `workspace.js` offers the tag menu on it as on a chip, reading its words as the record's name.
- Styles: `.ws-tag-card*` in `backend.src.css`, beside `.ws-form-card`.

## What stays as before?
- `ws_plain_text()` (push, e-mail, API `text`), API `body`, MCP, search and export: the tag's words or the raw token.
- Notes, task descriptions, the timeline and guest pages use `ws_render_body()` and draw the chip.

## Tests?
- `tests/workspace_tag_card_test.php`: bodies that make a card and those that do not, escaping, and a reader without the right getting no card and no extra data.
