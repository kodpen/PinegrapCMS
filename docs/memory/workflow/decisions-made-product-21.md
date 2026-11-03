---
name: decisions-made-product-21
description: Decisions list, product part 21 (2026-11-03 on): clearing the performance log
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Clearing the performance log follows the activity log's rule (2026-11-03, #5843)
- Owner (2026-11-02 night) delegated the question to the architect; decided: the "Clear all performance log data" button and the `submit_clear` branch of `view_performance_log.php` follow `MASS_DELETION == true || USER_ROLE < 1`, as the activity log's delete controls do; a role outside it sees no button and the server refuses. Reason: one rule for clearing logs; opening the screen (`manager`) and wiping diagnostics for good are separate rights.
