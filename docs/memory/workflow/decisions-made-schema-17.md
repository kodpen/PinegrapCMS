---
name: decisions-made-schema-17
description: Decisions list, schema part 17 (2026-11-12 on): the canvas-one tables ws_canvas_parts, ws_canvas_tables, ws_canvas_consents, via_message_id on table rows, ws_wtables.channel_id
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- All five are the questions of step CO0 of `plans/canvas-one.md` (PR #8922), option A of each issue, made by 2026.11.0 steps with the idempotent helpers. Running sites get empty tables and columns of 0: nothing changes on screen.
- A canvas's second part (owner, 2026-11-12, #8923): a new table `ws_canvas_parts`: `message_id`, `part` VARCHAR(8) (`drawing` | `page`), `file_id`, who made it; `UNIQUE (message_id, part)`, `idx_file (file_id)`.
  - Owner (2026-11-12), in the architect's window: "Yeni tablo (Önerilen)". Not chosen: a column on `ws_messages`; no schema (the id in the version document).
- The tables bound to a canvas (owner, 2026-11-12, #8924): a new table `ws_canvas_tables`: `message_id`, `table_id`, `access` VARCHAR(8) (`read` | `write`), who bound it; `UNIQUE (message_id, table_id)`, `idx_table (table_id)`; no foreign keys; at most 20 bindings a canvas, checked in PHP.
  - Owner (2026-11-12): "Yeni tablo (Önerilen)". Not chosen: no schema (the list in the page's version document).
- The reader's first-write answer (owner, 2026-11-12, #8925): a new table `ws_canvas_consents`: `message_id`, `user_id`, `page_version`, `answer` VARCHAR(8) (`yes` | `no`), `tables` TEXT (the write-bound table ids it covered); `UNIQUE (message_id, user_id, page_version)`.
  - Owner (2026-11-12): "Yeni tablo (Önerilen)". Not chosen: the browser's storage only.
  - A new page version, or a write to a table the answer did not cover, asks again. The privacy export lists a person's rows.
- "Through <canvas>" on a row (owner, 2026-11-12, #8926): `ws_wtable_rows.via_message_id` INT UNSIGNED NOT NULL DEFAULT 0 and the same on `ws_wtable_row_bin`, by `install_add_column()`: set by a page's write, 0 after any other write.
  - Owner (2026-11-12): "Satıra sütun (Önerilen)". Not chosen: a row history table; a Site Log line per write.
- A table of a channel (owner, 2026-11-12, #8927): `ws_wtables.channel_id` INT UNSIGNED NOT NULL DEFAULT 0, `idx_channel (channel_id, updated_at)`; `user_id` stays the maker.
  - Owner (2026-11-12): "Kanal tablosu (Önerilen)". Not chosen: a table owned by one canvas and binned with it; no schema (an edit level on a channel share).
  - Read by whoever reads the channel, edited by whoever may post in it, deleted and shared by its admins. A canvas's delete removes its bindings and leaves the table.
