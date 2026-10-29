---
name: decisions-made-workspace-2
description: Decisions list, workspace part 2 (2026-10-29 on): the channel menu holds only the channel's own items, creates in a chat go through /oluştur (/create), the customer channel's sales quote stays
---

## What may the channel menu hold?
- Only the channel's own settings and actions: channel settings, notifications, voice, make private, move to group, templates, share link, clear, archive, leave; and on a customer channel "New sales quote" (`sales_quote_new`). Creating or changing other records never goes there: it is a / command or the record's own screen (`workspace/README.md` key rules).
- Owner decision (2026-10-29, "kanalla ilgisi olmayan bir ayarın kanal ayarlarına girmesi aşırı mantıksız … bu tür bir hata yapılmasın bir daha"): a setting unrelated to the channel in the channel's menu makes no sense, and the mistake must not be repeated. "Make products" (`bulk_make_products`, #2278) leaves the menu (`plans/workspace-create-command.md`, S1).

## Where are records created inside a chat?
- Through one general command `/oluştur` (English `/create`): `/oluştur ürün <name> <price>` and `/oluştur grup <name>` make one record through the single-create preview card (`ws_quick_create_*`); several lines under `/oluştur ürün` make the bulk draft card (up to 200 rows; more goes to `import_products.php`). `/ürün` (`/product`) becomes an alias of `/oluştur ürün` (`plans/workspace-create-command.md`).
- Owner decision (2026-10-29, "sohbet içinde oluşturma penceresi … bu da mantıklı değil"): a creation window opened inside the chat is not the right shape either; a command is.

## Does the customer channel's "New sales quote" stay in the menu?
- Yes (owner, 2026-10-29): it is about the channel's own customer, so it is one of the channel's own actions.

Related: docs/memory/workflow/decisions-made-workspace.md (part 1), docs/memory/workflow/decisions-made.md
