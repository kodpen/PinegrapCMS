---
name: decisions-made-workspace-5
description: Decisions list, workspace part 5 (2026-10-30 evening on): the Workspace list and channel screen redrawn; who builds the design; the bell as the one inbox; the panel's own colours
---

## How is the Workspace list laid out?
- As `plans/workspace-menu-layout.md` describes it: one search field, the places (Overview, Inbox, My Tasks), one Channels section (pinned, groups, the rest), My discussions, Tools tiles with More. The place and the access rules of #2545 stay (`decisions-made-workspace.md`).
- Owner decision (2026-10-30, "Menü çok karışık"; on the drawn proposal "Menü yapısı ve kanal görünümü uygun"): the list was hard to find one's way in.

## Does the channel screen change too?
- Yes: it is made again in the drawn design (`plans/workspace-channel-layout.md`). Every feature stays; a rarely needed item may move behind a menu or show on hover. On a phone the writing box rides on the keyboard and the message list keeps most of the height.
- Owner decision (2026-10-31): first "Sadece menüyü işleyelim kanallardaki değişiklikler olmasın" (2026-10-30), because on the drawn phone screen the box stayed under the keyboard and the list had a third of the screen; then "pc den bakınca workspace bayıldım", "burayı baştan yaratıyor ve modernleştiriyoruz … özellik gelsin eskisi gitmesin … gizlenmesi makul bilgi ve düğmeler gizlenebilir", "geliştirme sende".

## Who builds the Workspace design?
- The session the owner names in chat for the design, against the drawn design; coordinators do not hand its steps out and workers do not claim them (`plans/workspace-menu-layout.md`).
- Owner decision (2026-10-31, "buluttaki ajanlar tasarımı kafalarına göre yazarlarsa iyice karışır … tasarımı güncellemeye direkt sen gir"): sessions each drawing the design their own way would scatter it.

## Is the Workspace inbox a list of its own?
- No: the panel bell is the one inbox (`plans/workspace-menu-layout.md` M3). Since 4.83 every Workspace inbox row is also a bell row (`ws_bell_add()`), read in either place is read in both, so one item showed in two lists with two counts. The merge is in the view: `ws_inbox` stays for the API, sync and push.
- Owner decision (2026-10-31, "workspace gelen kutusu da normal site gelen kutusuna katmayı değerlendirelim", then "önerin kabul").

## Which colours does a Workspace design use?
- The panel's own: the bar and the menu in `--pg-navbar-bg`, the content in `--pg-bg-body`, cards in `--pg-card-bg`, the accent `--ws-accent`; light, dark and auto as the panel's theme switch.
- Owner decision (2026-10-30, "arkaplan rengi yazılımdan bağımsız olmasın"): a design's background must not stand apart from the software.

Related: docs/memory/workflow/decisions-made-workspace-4.md, docs/memory/workflow/decisions-made.md
