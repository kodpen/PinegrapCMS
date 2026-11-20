---
name: decisions-made-schema-29
description: Decisions list, schema part 29 (2026-11-20 on): Workspace calls tables; content and component_id on auto_dialogs; prototype_links.bundle_id; the event_tickets columns
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before is schema part 28 (decisions-made-schema-28.md).

## Which tables do Workspace calls add? (owner, 2026-11-20, poll 158, channel message 9839)
- "Evet, böyle olsun", in the open version 2026.11.0, to: `ws_calls` (a call: channel, who started it, kind voice or video, state, start and end time), `ws_call_members` (who joined and left when; a guest is a row too), `ws_call_signals` (the browsers' short-lived connection messages, deleted when the call ends and after 2 minutes).
- Said with it: sound and picture are stored nowhere and there is no recording.
- The architect's reading, not his words: the columns' names and types are the migration step's (CL1 of `plans/workspace-calls.md`), within what the poll names; a column beyond it is asked again.

## Which columns does PU6 add to `auto_dialogs`? (architect, 2026-11-20, agent channel, answer to K2's request 1584)
- `content` VARCHAR(10) NOT NULL DEFAULT 'url' after `place` (url or component, checked in code; missing or unknown reads as url) and `component_id` INT UNSIGNED NOT NULL DEFAULT 0 after it; no index, no backfill, no foreign key.
- Both are named by `plans/auto-dialog-popups.md` under the owner's yes to new columns (channel message 5139); not chosen: `component_id` alone, as a row keeps its address while it shows a component.

## Does `prototype_links` get a column for a release bundle's share link? (owner, 2026-11-20, poll 159, channel message 9931)
- "Evet, eklensin", to: `bundle_id` (INT, default 0: not tied to a bundle) on `prototype_links`, a table the open version 2026.11.0 added; existing links do not change.
- Not chosen: no share link for a bundle's preview.
- The architect's reading, not his words: INT UNSIGNED NOT NULL DEFAULT 0 with KEY `idx_bundle`, written into the table's CREATE of step 11.0.35 and added by `install_add_column()` where that step already ran; RB6 #10518 (`designer/release-bundle-share-link.md`).

## Which columns does the tickets table get? (architect, 2026-11-20, agent channel message 10086, answer to K1's request 1671)
- Under the owner's yes to the table and the product's column (poll 91, `decisions-made-schema-25.md`). Table `event_tickets`, plural as the commerce tables it joins; TK1 of `plans/event-tickets-steps.md` (#10556), step 11.0.246.
- `id`, `order_id`, `order_item_id`, `product_id`, `calendar_event_id`, `recurrence_number`, `used_at`, `used_by`, `voided_at`, `created_at` INT UNSIGNED; `unit` SMALLINT UNSIGNED (1 to 100); `code_salt` CHAR(32) and `code_hash` CHAR(64), ascii; `state` ENUM('valid','used','void') DEFAULT 'valid'. All NOT NULL, default 0 or ''.
- Keys: UNIQUE `idx_line_unit` (`order_item_id`, `unit`), UNIQUE `idx_code` (`code_hash`), `idx_order` (`order_id`), `idx_event` (`calendar_event_id`, `recurrence_number`, `state`), `idx_product` (`product_id`, `state`).
- `products.gives_tickets` TINYINT UNSIGNED NOT NULL DEFAULT 0, added in place where the server allows it.
- The code and the mail's link are signed with a ticket key of its own, never `ENCRYPTION_KEY`: a config_text value sealed with the site's key and listed in `pg_reseal_registry()`, made when the "Tickets" switch is saved on, never replaced. Not chosen: a new salt and hash after a key reset, which would refuse a ticket already printed. The door reads only the hash.
- Not in the erasure list: an erased person's order stays and the row names nobody. A deleted order's tickets are deleted with it: a row left behind would still pass at the door. The export lists a person's tickets with their order, without the code (TK5).

Related: docs/memory/workflow/decisions-made.md, docs/memory/release/released-vs-open-version.md
