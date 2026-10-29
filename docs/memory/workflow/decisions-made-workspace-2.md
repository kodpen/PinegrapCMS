---
name: decisions-made-workspace-2
description: Decisions list, workspace part 2 (2026-10-29 on): the channel menu holds only the channel's own items; creates in a chat go through /oluştur (/create): its rights and kinds; the sales quote stays
---

## What may the channel menu hold?
- Only the channel's own settings and actions: channel settings, notifications, voice, make private, move to group, templates, share link, clear, archive, leave; and on a customer channel "New sales quote" (`sales_quote_new`). Creating or changing other records never goes there: it is a / command or the record's own screen (`workspace/README.md` key rules).
- Owner decision (2026-10-29, "kanalla ilgisi olmayan bir ayarın kanal ayarlarına girmesi aşırı mantıksız … bu tür bir hata yapılmasın bir daha"): a setting unrelated to the channel in the channel's menu makes no sense, and the mistake must not be repeated. "Make products" (`bulk_make_products`, #2278) leaves the menu (#2927, `workspace/workspace-create-commands.md`).

## Where are records created inside a chat?
- Through one general command `/oluştur` (English `/create`): `/oluştur ürün <name> <price>` and `/oluştur grup <name>` make one record through the single-create preview card (`ws_quick_create_*`); several lines under `/oluştur ürün` make the bulk draft card (up to 200 rows; more goes to `import_products.php`). `/ürün` (`/product`) becomes an alias of `/oluştur ürün` (#2967, #2982, `workspace/workspace-create-commands.md`).
- Owner decision (2026-10-29, "sohbet içinde oluşturma penceresi … bu da mantıklı değil"): a creation window opened inside the chat is not the right shape either; a command is.

## Does the customer channel's "New sales quote" stay in the menu?
- Yes (owner, 2026-10-29): it is about the channel's own customer, so it is one of the channel's own actions.

## Who may draft from a channel file, and what does /oluştur take? (#2999)
- Decided (architect, 2026-10-29, #2999; built in #3014, `workspace/workspace-bulk-sources.md`): `/oluştur ürün` as a reply to someone else's CSV/xlsx/xls/ods message is open to anyone who may read the message, post in the channel and change products, as the file button was: the drafts are the proposer's own and every row is checked again at Apply. `/oluştur grup` makes one record; several lines answer with a clear error (bulk groups only when asked for). No new kinds for now (task, customer, sales quote, workflow table row are asked when a need appears); `/görev` stays its own command, its parser reads `@people`, dates, `~estimate` and `!priority` anywhere.

## How does Run from a workflow table work? (F6c, #3122)
- Decided (architect, 2026-10-29): the table's rows are compiled once into a draft flow (`ws_flow_from_wtable()`, pure: a task box per row in row order, "after" arrows from the after column, the previous row otherwise; person to assignee, days to `due_in`); the builder reviews and publishes it and Run is the normal flow Run, one engine; a row's Done is shown per run, never written back into the table. A dialog on the table screen maps the step, person, days and after columns, prefilled by type, nothing stored on the table. Run asks for an optional customer (contacts the starter may read): the run's `subject_type = 'contact'` and `subject_id`, task titles carry the name, and a second Run for the same customer while a run is open answers that run (start key per flow and contact); no schema.

Related: docs/memory/workflow/decisions-made-workspace.md (part 1), docs/memory/workflow/decisions-made.md
