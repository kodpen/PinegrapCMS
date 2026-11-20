---
name: event-tickets-key
description: The event tickets' own key (sealed in config_text, made once when the switch is saved on, resealed on a key reset) and why an order's deletion removes its tickets
module: event_tickets
---

## Which key are codes and links drawn under?
- The tickets' own key, not `ENCRYPTION_KEY`: 32 random bytes (64 hex) in config_text `event_tickets_key`, sealed with the site's key as `cipher:iv` (`pg_event_tickets_key_seal()` / `_open()`, which checks the 64-hex shape). A key reset seals it again (`pg_reseal_registry()` entry `event_tickets`), so issued codes keep matching.
- Made only by `pg_event_tickets_key_make()` when the switch is saved on and none exists, through `pg_config_text_claim()` (only a missing row takes it): it never changes, and never made during completion, where two completions at once could write two keys.
- Architect (2026-11-20, message 10086): a new salt and hash when the code does not match was refused, as it would void a printed or saved ticket. The door needs no key: it looks only at the hash.
- For TK2: the holder's page draws no QR when the sha256 of the redrawn code differs from the row's `code_hash`.

## What happens when an order is deleted?
- `delete_order.php` calls `pg_event_tickets_delete_for_order()` beside the returns and the shipping labels: a valid row left behind would still pass at the door, and a reused order line id would stay without tickets (unique line and unit, `INSERT IGNORE`). `pg_local_order_discard()` deletes only incomplete orders, which have none.
- Not in `privacy_erase.php`: a ticket holds no personal data of its own and reaches a person through its order. The personal data export lists a person's tickets without the code (`event-tickets-api.md`).

Test: `tests/event_tickets_test.php`, `tests/reseal_test.php` (`test_reseal_event_tickets_key`).

Related: docs/memory/commerce/event-tickets-model.md, docs/memory/platform/encryption-key-reseal-registry.md
