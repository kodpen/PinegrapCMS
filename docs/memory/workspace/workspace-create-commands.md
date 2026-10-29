---
name: workspace-create-commands
description: /create (/oluştur) product|group and its alias /product (/ürün) in a channel: kind word, parsers, the single-create card through ws_quick_create_input()/_save(), what Create sends
---

## What does /create (/oluştur) take? (PR #2967)
- `ws_command_create()`: the first word names the kind (`ws_command_parse_create()`, folded, `ws_command_create_kinds()`: ürün/urun/product, grup/group); no kind → error naming both forms. Registry key `create`, first arg `record_kind` with suggest `kinds` (Tab popup from `BOOT.command_values.kinds`), then the name (`multiple`: the rest of the line), price, #group.
- `/create product …` is `ws_command_product()`; `/product`, `/ürün`, `/urun` stay a command of their own (key `product`) running the same function: an alias, same parser, same card.
- `/create group <name>`: `ws_command_group()`, the rest of the line as `name` and `short_description` (`ws_command_group_fields()`), no price, a tag refused. Several group lines: an open architect question (`plans/workspace-create-command.md`).
- Several lines under `/create product` or `/product` (#2982): one bulk card of drafts under the command's own message (`workspace-bulk-create.md`); one line is the single card below.
- Both go through `ws_command_create_card()` (check, card, confirm → `ws_quick_create_save()`); the card's `preview.kind` heads it (`qc_command_title_group` for a group) and opens the right drawer under "All fields".

## How does /product read its text?
- `ws_command_parse_product()`: the first `<#product_group:N>` anywhere, a price as the last word (`149,90`, `149,90 TL`), the rest the name; one word is always the name. `ws_command_product_fields()` writes the name as short description too. Another tag left in the name is refused.
- Answer `create_preview`, checked by `ws_quick_create_input()`: rows, "On sale" tick (off), decision tick (on), Create, "All fields" (the new mode filled, with both ticks: `values.enabled`, `options.decision`; its decision in the card's own channel, a discussion too: `options.channelId`), Cancel. Enter draws it again; only Create writes (`confirm`, `on_sale`, `decision`) with `body`, the text the card was drawn from: an edited box made a product nobody saw.
- After a card writes (/task, /price too) or "All fields" creates, `clearSent()` empties the box only if it still holds that text: a draft typed since stays.
- Names: `ws_command_create_names()` (/create and /product), read with `ws_command_names()` through `ws_command_table()`. Offered with the product right.

