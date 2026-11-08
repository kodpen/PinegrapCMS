---
name: decisions-made-product-22
description: Decisions list, product part 22 (2026-11-01 evening on): the panel's own table driver replacing DataTables
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## DataTables leaves the panel; a Pinegrap table driver replaces it (2026-11-03)
- Owner (2026-11-03): DataTables goes; the panel's own table driver takes its place, built and rolled out by the table driver plan (done with #8253, `ui/list-table-rollout.md`). Until a screen moves, everything continues as today: list fixes and server-side paging (`ui/server-paged-lists.md`) go on, nothing waits for the driver. Reason: the lists must keep working while the driver is built; the driver takes over the same row actions.

## How a list behaves under the driver (owner, 2026-11-03, `ui/list-table-rollout.md`)
- No row checkboxes: selection as in the File Manager (click one, Ctrl/Cmd toggles, Shift a range, arrows), kept by record id, so loading, sorting or searching never drops it. Reason: ticked boxes were lost on every DataTables redraw.
- Double-click or Enter opens the record and its name stays a link; a tap selects on a phone. A plain click, × or Escape that replaces 2+ selected rows offers "Önceki seçimi geri al" for 10 s.
- Ctrl+A selects the whole list by its condition, rows not loaded yet included; Ctrl+click excludes one. Bulk actions act on that condition with its exceptions.
- Rows load as the list scrolls; a heading click sorts without reloading the page; the list fills the window, its headings and the selection bar stay in sight; every screen is `container-fluid`.
- Hidden columns, column order and the details panel's open state are kept in the browser (localStorage) per user and list: no database rows. Reason: the owner wants no table rows for view settings.
- Every list has a details panel ("Ayrıntılar"); below 1024 px it opens as a side sheet. On a phone each row is a card that shows every visible column: nothing is dropped on a small screen.
- Quick edit is a mode button ("Hızlı düzenleme", F2): with it on, every editable cell is an input and saves as soon as it is left; a refused value is not saved and says why; the last save can be undone; a value saved on a selected row can be applied to the other selected rows. With it off, a click selects and a double-click opens.
- The move is gradual, screen by screen; DataTables is removed when the last screen has moved.
