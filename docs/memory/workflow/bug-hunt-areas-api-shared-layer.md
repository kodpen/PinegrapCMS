---
name: bug-hunt-areas-api-shared-layer
description: The bug hunt of the external API's shared layer (fault, manners, response, router, preconditions, idempotency), given in channel message 10241, area c: what it found and read clean
---

## What was the area? (channel message 10241, area c)
- The shared layer of the external API built by plan api-clean-modern AM3–AM10 (closed by #10464): `includes/api/fault.php`, `manners.php`, `response.php`, `router.php`, `preconditions.php`, `idempotency.php`.
- Not touched: `includes/api/schema.php`, `resources/commissions.php`, `resources/calendars.php`, `includes/api/outbound/` (held by TK2, TK3, TK4).

## What did the hunt find?
- In progress.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-4.md
