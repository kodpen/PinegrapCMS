---
name: decisions-made-panel-4
description: Decisions list, panel part 4: the notifications bell (owner 2026-11-11): rows as the dashboard's Next up rows, module chips, unread only, asked of me, search, in-place actions, read when opened
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the date and the reason.

## What does a bell row look like? (owner, 2026-11-11)
- Owner (2026-11-11): "bildirim panelini geliştirelim daha fazla filtre ekleyelim ve bildirim kartlarını daha kullanışlı hale sokalım. bildirim kart görünümü yeni pano ile eşleşsin istiyorum 2. görsel sadece örnek" (image 2: the new dashboard's Next up card).
- His answer: "Pano satırı gibi." A module tag at the left, the title and one second line, one button at the right named by the job (Reply, Show, Join, Approve), the read state as a small dot that reads the row when pressed, delete in the row's menu, day headings (Today, Yesterday, Earlier).
- Reason: today's row is tall, with two full-width lines under every notification. Refused: only the colours changed; a two-line row with small icons.

## Which filters does the bell have? (owner, 2026-11-11)
- He chose all four offered: "Modül çipleri", "Yalnızca okunmamışlar", "Bana sorulanlar ve anmalar", "Arama kutusu".
- Module chips in place of Site: Shop, Accounting, Content, Team, System, named and coloured as the dashboard's tags, a chip only for a module with rows, each with its unread count.
- "Unread only" is a switch that works together with the chosen chip. "Asked of me" shows the rows that mention the person or ask them something, from a person or an agent. The search box searches the title and the excerpt.

## What does a bell row's button do? (owner, 2026-11-11)
- "Aç + hazır işlemler." The button opens the related place; what the dashboard already does in place is done from the row too: approve a flow or try it again, complete a task, join a meeting. No new kind of action is written.
- Refused: a button that only opens; a quick reply box on the row.

## When is a notification read? (owner, 2026-11-11)
- "Kart açılınca okunur." Opening the bell marks nothing read. A row is read when it is opened or its dot is pressed; "Mark all as read" stands at the top; the bell's count stays until the person has dealt with the rows.
- Refused: as before, everything read on opening the bell. That earlier rule was never a recorded decision.

## Where is it built?
- `plans/bell-panel.md` and `bell-panel-steps.md` (BP1-BP4); the technical calls (which `action` is which module, the tag hues outside `.pg-dash`, the order of the steps) are the architect's, written there.
