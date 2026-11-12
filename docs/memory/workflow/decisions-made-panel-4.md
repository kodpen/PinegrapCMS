---
name: decisions-made-panel-4
description: Decisions list, panel part 4: the bell (owner 2026-11-11): a right drawer, rows as the dashboard's Next up rows, module chips, unread only, asked of me, search, in-place actions, read when opened
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the date and the reason.
- The owner's request and his four answers are on #8670, his words on the row as built and the drawer on #8754 (the `Karar:` comments).

## What does a bell row look like? (owner, 2026-11-11, #8670)
- Owner (2026-11-11): "bildirim panelini geliştirelim daha fazla filtre ekleyelim ve bildirim kartlarını daha kullanışlı hale sokalım. bildirim kart görünümü yeni pano ile eşleşsin istiyorum 2. görsel sadece örnek" (image 2: the new dashboard's Next up card).
- His answer: "Pano satırı gibi." A module tag at the left, the title and one second line, one button at the right named by the job (Reply, Show, Join, Approve), the read state as a small dot that reads the row when pressed, delete in the row's menu, day headings (Today, Yesterday, Earlier).
- Reason: the row then was tall, with two full-width lines under every notification. Refused: only the colours changed; a two-line row with small icons.

## How does the bell's panel open, and how is a row laid out in it? (owner, 2026-11-11, #8754)
- A drawer from the right edge, full height; the row is laid out again for it.
- Owner (2026-11-11), in the architect's window with a picture of the bell after #8722: "mimar bey bu bildirim paneli gerçekten çok zorlayıcı olmuş daracık alanda boyle bir panel garip olmuş lütfen bunu da toparlattıralım ve modernleştirelim. özellikle yeni panodaki gibi modernize olsun istiyorum demiştim ama bu panel olmamış". Asked with a drawing of each option: "Sağdan tam boy çekmece (Önerilen)".
- Not chosen: "Açılır kutu, daha geniş" (today's dropdown, widened).
- The drawing he chose: a head with the name, close, "Mark all as read" and a menu; the chips; day headings; a row with the small tag, the dot and a short title on line one, the place, the time and the button on line two, the excerpt in two lines at most, the row's menu at the right. It replaces the entry above where they differ: the tag is no column, the title no whole sentence.
- The architect's, not the owner's words: about 30rem wide, the whole width on a phone; "Delete All Notifications" in the top menu; step BP2b, approved from its pictures before it merges.

## Which filters does the bell have? (owner, 2026-11-11, #8670)
- He chose all four offered: "Modül çipleri", "Yalnızca okunmamışlar", "Bana sorulanlar ve anmalar", "Arama kutusu".
- Module chips in place of Site: Shop, Accounting, Content, Team, System, named and coloured as the dashboard's tags, a chip only for a module with rows, each with its unread count.
- "Unread only" is a switch that works together with the chosen chip. "Asked of me" shows the rows that mention the person or ask them something, from a person or an agent. The search box searches the title and the excerpt.

## What does a bell row's button do? (owner, 2026-11-11, #8670)
- "Aç + hazır işlemler." The button opens the related place; what the dashboard already does in place is done from the row too: approve a flow or try it again, complete a task, join a meeting. No new kind of action is written.
- Refused: a button that only opens; a quick reply box on the row.

## When is a notification read? (owner, 2026-11-11, #8670)
- "Kart açılınca okunur." Opening the bell marks nothing read. A row is read when it is opened or its dot is pressed; "Mark all as read" stands at the top; the bell's count stays until the person has dealt with the rows.
- Refused: as before, everything read on opening the bell. That earlier rule was never a recorded decision.

## Where is it built?
- `ui/bell-row.md`, `bell-actions.md`, `notifications-menu.md`, `notifications-one-inbox.md`, `bell-read-rule.md`.
