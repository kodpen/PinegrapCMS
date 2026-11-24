---
name: share-links-table-list
description: A table or checklist of an agent channel message shared by link (SL6): item_id as its number, the text as it is now, Share table by link…, the page, CSV, Markdown
---

## Which table or checklist is the item?
- The n-th table or the n-th checklist of the message's text, counted from 1 for each kind as the channel draws a message (`ws_share_link_text_blocks()` in `includes/workspace/share_links_text.php`, the same reading as `ws_render_body()`): a block of code holds none, consecutive checklist items are one list, a "::: Title" line right above a block is its title.
- `ws_share_links.item_id` holds n; the open-link check and the marks also match `message_id`, since every message has a table 1.
- Only a person's or an application's message (`ws_share_link_text_message()`): a guest's text and a mail are drawn without tables.

## What does the link read?
- The text as it is now: an edit shows through, and the link closes when the block is written away (no n-th block left).
- Its name: the title, else a table's header cells joined by " · " or a checklist's first item, tags as plain names, cut to 80 characters; "Table" / "Checklist" when empty. The conversation's line and the channel's Sharing use it.

## What does the address answer?
- A read-only page of the block drawn alone (`ws_share_link_text_page()` inside the shared page frame `ws_share_link_page()`): a table with its formulas worked out with the message's hesap names, a checklist with the ticks given since, its boxes disabled.
- `&raw=1`: a table as CSV (UTF-8 with a byte order mark, every cell quoted and passed through `csv_cell()`, a formula as its value); a checklist as Markdown, `- [x]` from the ticks given since, the title as `#` heading.

## What does the panel show?
- The payload's `text_shares` (`ws_share_links_text_map()`): per table and checklist kind, item_id, name, open, can_make; empty outside an agent channel, for an agent's answer drawn from another text and for a tag card.
- The message's More / context menu: "Share table by link…" / "Share checklist by link…", or "Stop sharing the table" / "Stop sharing the checklist"; with more than one block in the text the name follows after " · ".
- The mark (`shareTextMarks()`) sits on the titled block, else on `.ws-table-wrap` or `ul.ws-checklist`, matched by number in the drawn body.
- API: `kind` table or list, `item_id` the number.
