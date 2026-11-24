---
name: decisions-made-schema-50
description: Decisions list, schema part 50 (2026-11-24 on): one column answer_kind on launch_summary_lines for a line that asks for files; no new table
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-49.md`.

## Does launch_summary_lines get a column for the answer's kind? (owner, 2026-11-24, in the architect's window)
- Yes: "eklensin sütun sorun yok".
- `answer_kind` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 (0 text, 1 one file, 2 several files), added by a migration step of the open version with `install_add_column()`.
- No new table: the files are rows of the files table, their ids a JSON list in the line's `answer` (`plans/launch-summary-files.md`, step LS2).
