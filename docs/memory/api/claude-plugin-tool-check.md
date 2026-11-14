---
name: claude-plugin-tool-check
description: The site's MCP tools checked against Claude's directory rules (2026-11-14): name length, annotations, and the routes that move money or issue stored value, by route id and scope
---

## Do our tool names fit 64 characters?
- Yes. `pg_mcp_tool_name()` turns the route id's dots into underscores and cuts at 64; the longest route id is 39 characters (`store.ship_date_adjustments.delete_post`, itself no tool), so nothing is cut today.
- No two route ids become the same tool name. A future id over 64 would be cut in silence and `pg_mcp_routes_for()` keeps the first route of a name: CB2's test should fail on any offered id over 64 or any duplicate name.
- Tool names reach Claude as `mcp__plugin_<plugin>_<server>__<tool>`, limit 128 there; skills should name the bare tool (`pages_list`).

## Do our tools carry the annotations?
- Every tool has `title`, `readOnlyHint` (GET) and `destructiveHint` (a write that is not a `create` or `propose`), from `pg_mcp_tool_from_route()`.

## Which routes move money through an outside service?
- `returns.update` (POST /returns/{id}, `orders:write`) with `action` refund: a driver refund sends money back through the payment service (`api-returns.md`).
- `orders.labels.create` (POST /orders/{id}/shipments/label, `orders:write`): buys a carrier label, charged by the carrier.
- Both sit under `orders:write`, beside harmless writes (`orders.update` status/notes, `orders.ship` tracking), so a scope alone cannot keep them out.

## Which routes issue stored value?
- `gift_cards.create`, `gift_cards.update`, `gift_cards.delete`, `customers.credit.issue`, `customers.credit.void` (`gift_cards:write`): a balance a customer spends like money.

## Which routes only record money?
- `orders.create` (counter sale with its payment record, `orders:write`), `erp.receipts.create`, `erp.receipts.cancel`, `erp.expenses.pay`, `erp.cash_transfers.create` (`erp_cash:write`), `commissions.update` (marks paid; the payout happens outside, `commissions:write`).
- Book-keeping, no transfer; still finance under policy 1A-B, so a person confirms each one.

## What keeps a person in the loop today?
- The person grants scopes at the OAuth screen; `dry_run` on writes; Claude's apps can ask the person before each call of a tool whose `readOnlyHint` is false.
- Workspace change cards (`propose`) where the site proposes instead of writing.

## How is it settled?
- Architect (2026-11-14, msg 2493): the plugin's scope set holds no route that moves money or makes a cost; such routes carry a mark a test reads; dry run alone is no wall. Built in CB2: `api-plugin-scopes.md` (it also marks cheques and `customers.subscriptions.resume`).
- Policy 3F (the developer controls every endpoint) against sites that are their owners': the owner's, asked before CB3.

## Where were the rules read?
- `claude.com/docs/directory/publish`, `/plugins/pre-submission-checklist`, `/plugins/platform-support`, `/plugins/build`, `/plugins/overview`; `code.claude.com/docs/en/plugins-reference`, `/mcp`; Anthropic Software Directory Policy (support article 13145358).

Related: docs/memory/api/claude-plugin-census.md, docs/memory/api/api-mcp-server.md, docs/memory/api/api-returns.md
