---
name: main-menu-counts-active
description: Plan (owner 2026-11-28): the main menu's open row told by a filled tile, a "Notifications" row in the Workspace list, counts on the Orders, Forms and Products rows; steps MB1 to MB5
priority: 6
---

## What is asked?
- Owner (2026-11-28, agent channel message 15414): the Workspace row showed 29 and its list only a 6 on Tasks, "bildirimlerin nereden kaynaklandığı bilgisi net değil"; "siparişler vb. bildirimler (ürünlerde stok tükenmesi de olabilir) […] teyit edip geliştirmeye katalım".
- Users in tests could not tell the open row: its pale blue fill reads as one more blue tile, most of all on the narrow menu.
- He chose drawing B of the three the architect drew and said yes to the counts proposed: `workflow/decisions-made-panel-20.md`.

## What was found? (architect, 2026-11-28)
- The Workspace row's count is `ws_inbox_unread_count()`: the person's unread notification lines, listed only in the bell. The Workspace list has had no entry for them since #6281, so the count has no place in the panel it opens.
- Only Workspace, Communication with Incoming e-mail, ERP and Automation flows carry a count.
- The open row: `#menu .list-group-item.list-group-item-action.active` in `backend.src.css`.

## What did the architect settle? (not the owner's words)
- No table, no column. A step that finds it needs a stored value stops and asks the architect.
- Forms (2026-11-28, MB3's question): the count is the person's unread "A custom form was submitted" bell rows they may see (`pg_notification_unread_rows()`, `pg_notification_visible()`). The bell's read state is the stored mark, a person each.
- Orders counts what All Orders' quick filter "To ship" counts, with no period, through `pg_order_list_quick_sql()`: one rule, never a second copy of it.
- Products counts products that track stock and stand at zero, variants as the Products list counts them.
- A count is drawn only for a person who may open the list it leads to, is read once a page; no query that reads a whole large table (`visitors/`).
- Each new count has its hover sentence through `lang()` and an entry in the row's panel that opens the filtered list, as ERP's "Overdue" entry does.

## How is each screen put together?
- In words in `plans/main-menu-counts-active-screens.md`: the open row, the rail, a panel's current entry, the "Notifications" row, the quiet count.

## Which steps? (S under 400 product lines, M about 1,000; each waits for the one before it)
- MB1 (S) The open row and a panel's current entry: done #12251.
- MB2 (S) The Workspace list's "Notifications" row: done #12274.
- MB3 (M) Red count on Orders ("{var:1} order(s) to ship", the entry "To ship" opening `view_orders.php?quick=to_ship`).
- MB3b (S) Red count on Forms ("{var:1} unread submission(s)"), the entry "Unread" opening Submitted Forms narrowed to them. Opening a submission in Edit Submitted Form marks its bell row read for the person (`pg_notification_mark_read()`), so the count falls where the work is done.
- MB4 (S) The quiet count on Products ("{var:1} product(s) out of stock"), its dot, the entry "Out of stock" opening the Products list filtered.
- MB5 (S) Close-out: the menu at 1440, 1024 and 390 px in both themes, `ui/main-menu-counts.md` naming every row's count in one place, `ui/main-menu-overview.md` pointing at it, this file deleted.

## What does every step keep?
- Slots, gates and categories (`ui/main-menu-overview.md`); the tile's size and the icons' 1.2rem (`decisions-made-panel-19.md`); the widening without a stutter (`ui/main-menu-widening.md`); the rail's label hidden by opacity (`ui/main-menu-rail-label.md`).
- Contrast of 4.5:1 for the words and 3:1 for the white drawing on each filled tile in both themes (`ui/panel-pill-contrast.md`); a tone that fails gets a darker fill, never a darker drawing.
- The red pill for what waits for the person, the quiet count for what only informs.

## What is left out?
- Counts on other rows (Calendars, Visitors, Users): the owner's to ask for.
- The bell itself and what makes a notification.
