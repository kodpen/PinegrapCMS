---
name: decisions-made-schema-49
description: Decisions list, schema part 49 (2026-11-24 on): the agreed columns of prototype approval (PA1b): five on prototype_links, the table prototype_verdicts, no contact id, no retention rule
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-48.md`.

## Which columns does prototype approval get? (architect, 2026-11-24, PA1b #11298)
- The owner opened the change by poll 206 (`decisions-made-schema-46.md`); these are the columns agreed before the step went ready.
- `prototype_links`: `approval` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, `publish_rule` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, `approver_name` VARCHAR(60) NOT NULL DEFAULT '', `approver_email` VARCHAR(255) NOT NULL DEFAULT '', `answer_by` DATE NULL. No new index: a small table, the address looked up only by erasure and export.
- `prototype_verdicts`: `id` INT UNSIGNED AUTO_INCREMENT, `link_id`, `style_id`, `page_id` INT UNSIGNED NOT NULL DEFAULT 0, `verdict` VARCHAR(20) ascii (`approved`, `changes_requested`), `author_name` VARCHAR(60), `note` TEXT, `fingerprint` CHAR(40) ascii (the draft's sha1), `node_fingerprints` MEDIUMTEXT NULL (JSON, node id to sha1), `created_at` INT UNSIGNED.
- Keys: PRIMARY KEY (`id`), `idx_link_page` (`link_id`, `page_id`, `id`), `idx_page` (`page_id`, `id`), `idx_style` (`style_id`).
- Every verdict is kept; the newest of a page and link stands. No guest address, IP or contact id is stored in a verdict.

## Is the approver tied to a contact, and is there a retention rule? (architect, 2026-11-24)
- No contact id: the approver is a name and an address the designer types, so `pg_contact_holders()` gains nothing. The address is still a person's: `approver_email` is in the erasure and the export by address.
- No retention rule: verdicts are kept as prototype comments are (poll 72, `decisions-made-product-59.md`), until the design is deleted, Delete comments is used or the person is erased.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-schema-46.md
