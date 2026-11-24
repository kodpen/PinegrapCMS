---
name: bell-titles
description: A bell row's short title says what happened and with what ("New order #SP-1044"), the brief text under it; a Workspace title's name and verb; the push banner keeps the sentence (#8897)
---

## What does a bell title say?
- What happened and with what, in a few words, no greeting, no label with a colon (look A, `ui/look-sheet-numbers.md`). `pg_notification_display()` sets `short` and `brief`:
  - `new_order` "New order #SP-1044", text the total in bold; `order_return` "Return asked for #…", "Return sent back #…", "Refund due #…", "Refund overdue #…", text the sentence; `out_stock` "Out of stock", text the product; `form_submited` "Form submitted", text "Reference Code **AB12**"; `new_comment` "New comment" / "New product question"; `erp_auto_invoice` "Order #… not invoiced".
  - `prototype_comment` "New prototype comment", text "<guest> on <page>" (`pg_proto_comment_bell_words()`, #10042).
  - A Workspace row: `ws_bell_title()` (`includes/workspace/notify.php`): "<sender> mentioned you", "<sender> asked you", "<sender> wrote", "<agent> answered you"…; the channel is the place ("#sales", "A discussion in #sales"). Owner 2026-11-11, #8754.
- The device push banner keeps the whole sentence (below). `tests/bell_row_test.php` holds no short title with a colon.

## Where is it used?
- `pg_panel_get_notifications()` sends `short` as `title` and `brief` as `details` when set; the row is drawn as `bell-row.md` says.

## How is a Workspace title drawn?
- Split (BR3 #11154): `pg_notification_title_name($title, $who)` (`includes/notifications.php`, pure) finds the person's or application's name (`who` from `ws_bell_describe()`) in the translated sentence and wraps the rest in `.pg-list-row-title-verb`, before or after the name as the language puts it ("ayse.yilmaz <verb>sizi andı</verb>"); every part escaped.
- No name, or a sentence without it, is only escaped. Site rows are not split.
- Before, the whole sentence was one bold line ("Mimar Elçin mentioned you"), unlike the Design examples row.

## What of the sheet is not drawn yet?
- A person's round avatar in place of the tile.

## Why does a device push banner decode its title?
- The service worker shows `item.title` as text, but bell titles are escaped (`&` came as `&amp;`). `pg_panel_push_pending()` sends `pg_panel_push_title()` (strip tags, decode, trim). The push banner keeps the whole sentence, not the drawer's short title (above). `tests/push_banner_title_test.php` (#3549).

## Why is a bell title one line?
- Look A (DL3, #8897): the title is short ("New order #1044", `bell-row.md`) and stands on one line with the time at its right, ending in an ellipsis when it does not fit; the text under it has the rest in two lines. Before, long sentences as titles wrapped (#3549 era), since `nowrap` cut 44–50 character sentences.
- The server sends titles escaped (`pg_notification_display()`), so the script inserts them as they come. `tests/mail_queue_full_text_test.php` pins it.
