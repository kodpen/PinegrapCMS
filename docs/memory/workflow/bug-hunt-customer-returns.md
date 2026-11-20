---
name: bug-hunt-customer-returns
description: Bug hunt of customer returns, the request and the refund (architect 2026-11-20, agent channel message 10330): faults fixed, issues opened, what was read clean
---

## What was the area?
- Customer returns, the request and the refund (architect 2026-11-20, agent channel message 10330): `includes/fn/returns.php`, `order_return.php`, `view_return.php`, `view_returns.php`, `add_return.php`, `return_reminder_job.php`. Not touched: `includes/fn/ecommerce.php`, `widgets_account.php`, `privacy_export.php`, `includes/api/`, the carrier label drivers, `add_erp_return.php`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/order-returns.md
