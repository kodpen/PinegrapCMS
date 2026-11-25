---
name: decisions-made-schema-51
description: Decisions list, schema part 51 (2026-11-24 on): the table security_finding of the Security Scan; the owner left the schema to the architect
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-50.md`.

## Does the Security Scan get a table? (owner, 2026-11-24, agent channel message 12847)
- Yes: "şema gerekliyse eklersin."
- `security_finding`, one row a finding: `id`; `kind` VARCHAR(40) ascii (the rule); `place` VARCHAR(500) (a path, or a record's type and id); `fingerprint` CHAR(40) ascii, unique (rule and place, so a second scan updates the row); `severity` TINYINT; `state` TINYINT (open, quarantined, ignored, gone); `detail` TEXT; `note` VARCHAR(255); `acted_by`; `first_seen`, `last_seen`, `acted_at` as Unix seconds.
- Keys: PRIMARY KEY (`id`), UNIQUE (`fingerprint`), `idx_state` (`state`, `severity`).
- No person's data: a finding about an account holds the user id only, so the table is not on the privacy lists. Rows in state gone are removed after 90 days.
- The last run (time, counts, where a cut scan resumes) is one `config_text` row, no column. The step (SC1 of `plans/security-scan.md`) brings any change of these columns to the architect before it is marked ready.
