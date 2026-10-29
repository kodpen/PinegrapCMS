---
name: decisions-made-schema-4
description: Decisions list, schema part 4 (2026-10-29 afternoon on): ws_flow_templates for staff-saved flow templates; rules in part 1
---

## Where are the rules and the earlier steps?
- `decisions-made-schema.md` (rules, 2026-10-27), `-2` (2026-10-28), `-3` (2026-10-29). Reasons are on the issues.

## Which schema was approved on 2026-10-29 (continued)?
- Flow templates G3 (#3120, architect, the next free Workspace label when pushed): `ws_flow_templates` by `install_create_table()`, in `get_tables()`: `template_key` VARCHAR(40) UNIQUE (`site_<id>`, never a built-in key), `category` VARCHAR(16), `title` VARCHAR(160), `help` VARCHAR(255), `icon` VARCHAR(40), `graph` MEDIUMTEXT (the normalized draft graph), `settings` TEXT (JSON in the built-ins' shape), `source_flow_id`, `created_by`, `updated_by`, `created_at`, `updated_at`, `binned_at` (the `ws_flows` conventions). Saved from a draft the person may edit; channel, person, time, url and address values become settings with the current values as defaults; secrets in box configs are dropped; `boxes`, `threads` and `motion` are computed on read. Listed to everyone who builds flows under "Saved on this site"; title, help and category edited and the row deleted by its creator or Manager and above; Use goes through the built-ins' setup and publish checks; erasure re-points or zeroes `created_by` / `updated_by` as for flows; G4 reads and writes the same row.

Related: docs/memory/workflow/decisions-made-schema.md, docs/memory/workflow/decisions-made-schema-3.md
