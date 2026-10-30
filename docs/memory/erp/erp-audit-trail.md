---
name: erp-audit-trail
description: ERP audit trail (erp_audit_log, 4.90): its two writers (events and ERP log lines), the calling-file rule, why it is never deleted, what a line carries, who may read it.
---

## What writes `erp_audit_log`? (`includes/erp/audit.php`, 2026.4.4 step 4.90)
- Two things only. Every event the module announces: `erp_event()` → `erp_audit_event()`, with the record's number and amount at that moment and the payload in `detail`. And every `log_activity()` line an ERP screen, the ERP API or an ERP job writes: `erp_audit_log_line()`, called from `log_activity()` (`includes/fn/forms.php`), event `log`.
- The log hook decides by the file that called `log_activity()` (`erp_audit_is_erp_file()`): a file under `includes/erp/`, or one whose name has `erp_` (`erp_*.php`, `add_erp_*.php`…). ERP work logged from another file (e.g. `includes/local_sale.php`) never reaches the trail: move the line into an ERP file, or announce the work with an event.

## Why is it never deleted?
- The site log is emptied after six months, shorter than any period an accountant or a tax audit looks back over. Nothing deletes from this table, and a backup keeps its rows (docs/memory/data/backup-rowless-tables.md).
- Writing never stops the action it records: a line that cannot be written is left out without an error. An event line rides on the document's connection, so a document that rolls back leaves no line.

## What does a line carry?
- `request_id`, shared by every line of one request (the screen shows one action as one entry; the core `audit_log` takes the same id inside the ERP), `source` (`panel`, `api`, `job`), user id and name (`erp_audit_actor()`: through the API the application's owner and the application's name), ip, `event`, `object_type`, `object_id`, `label`, `amount`, `currency`.
- `object_type` is the event name's second part. A new type needs its label and amount in `erp_audit_event()`, a row in `erp_audit_object_types()` (filter label and link) and its sentence in `erp_audit_event_text()`.

## Who may read it?
- `erp_audit.php`: role below 3, the ERP settings right, or the read-only accountant (`USER_ERP_READONLY`); others are refused and logged. `?type=&id=` follows one record; the invoice, quote and purchase order screens link there. Filters and a CSV export (`erp_audit_csv()`); its user, action, number-or-name and detail cells pass `pg_csv_formula_guard()`, the date, ids and amount do not (`core/csv-exports.md`).
