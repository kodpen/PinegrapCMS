---
name: decisions-made-schema-6
description: Decisions list, schema part 6 (2026-10-30 midday on): ship_tos.district and states.plate_code for Turkish addresses, contacts.iys_sms_status for marketing SMS; rules in part 1
---

## Where are the rules and the earlier steps?
- `decisions-made-schema.md` (rules, 2026-10-27), `-2` (2026-10-28), `-3` and `-4` (2026-10-29), `-5` (2026-10-29 evening to 2026-10-30 morning). Reasons are on the issues.

## Which schema was approved on 2026-10-30 (midday)?
- Turkish addresses (#3569, architect, one step on the next free general label when pushed): `ship_tos.district VARCHAR(50) NOT NULL DEFAULT ''` and `states.plate_code VARCHAR(4) NOT NULL DEFAULT ''` by `install_add_column()`; the same step fills the plate codes of the 81 Turkish provinces from `pg_sales_map_tr_regions()` by province name (an unmatched row stays empty; a rerun fills only empty ones). The district is free text, optional, shown on checkout and the order screens when the country is Turkey or the chosen method's driver asks for it; the API `ship_tos` fields gain it; the drivers send `district` first and fall back to `city` as today. No district data set (its licence would be a separate decision).
- Marketing SMS (#3570, architect, the next free general label when pushed): `contacts.iys_sms_status VARCHAR(16) NOT NULL DEFAULT ''` by `install_add_column()`, the `iys_email_status` shape (11.0.39): what İYS last said about the number on the `MESAJ` channel.

Related: docs/memory/workflow/decisions-made-schema.md, docs/memory/workflow/decisions-made-schema-5.md
